<?php

declare(strict_types=1);

/**
 * Availability checker for affiliate ASINs — the dead-link marker (concept Komponente 1).
 *
 * Periodically probes every affiliate ASIN via the Creators-API (server-side, Tier-0 — not
 * scraping over the house IP) and classifies it. ONE source of truth: the availability table,
 * keyed by ASIN (global). "Which posts have a dead ASIN" is a query-time JOIN, no roll-up cache.
 *
 * Design + Codex-Refute (RC1–RC7) in docs/DESIGN-availability-checker.md. Key safety properties:
 * - Account-wide auth failure (token fail / catalog 401/403) ABORTS the whole scan + alarms,
 *   never writes per-ASIN status (RC1/RC2) — an account problem must not become 988 "suspicious".
 * - Only a POSITIVE "no buyable offer" signal → unavailable_temp; offers absent/unparseable → error
 *   (retry, keep last good status), so a parser shape-mismatch can't mass-mark live products (RC3).
 * - not_found ONLY from a per-ASIN InvalidParameterValue (RC3); ItemNotAccessible → api_inaccessible.
 * - Hysteresis: a "no offer" ASIN becomes a repair candidate only after >=3 consecutive scans (RC1).
 * - Resumable chunked cron with an option-lock + stale-lock watchdog + idempotent kickoff (RC5).
 * - Repair itself is human-gated and NOT in this class (concept R6).
 */
final class MTB_Affiliate_Availability_Checker {
    private const TABLE_SUFFIX        = 'mtb_affiliate_availability';
    private const DB_VERSION          = '1.0';
    private const DB_VERSION_OPTION   = 'mtb_affiliate_availability_db_version';

    private const WORKLIST_OPTION     = 'mtb_affiliate_availability_worklist';
    private const CURSOR_OPTION       = 'mtb_affiliate_availability_cursor';
    private const LOCK_OPTION         = 'mtb_affiliate_availability_lock';
    private const AUTH_ALARM_OPTION   = 'mtb_affiliate_availability_alarm';
    private const STREAK_OPTION       = 'mtb_affiliate_availability_error_streak';
    private const NOTE_OPTION         = 'mtb_affiliate_availability_note';
    private const PARTNER_TAG_OPTION  = 'mtb_affiliate_availability_partner_tag';

    public const KICKOFF_HOOK         = 'mtb_affiliate_availability_kickoff';
    public const TICK_HOOK            = 'mtb_affiliate_availability_tick';
    private const CRON_SCHEDULE       = 'mtb_weekly';

    private const BATCH_SIZE          = 10;   // Creators-API getItems max per call
    private const TICK_DELAY          = 5;    // seconds between batch ticks (quota-gentle)
    private const BACKOFF_DELAY       = 120;  // seconds after a 429
    private const LOCK_TTL            = 300;  // a lock older than this is reclaimed (watchdog)
    private const ERROR_STREAK_ALARM  = 12;   // consecutive all-error/0-available batches -> alarm

    private const DEFAULT_PARTNER_TAG = 'meintechblog-230807-21'; // registered tag (138x used)
    private const CANARY_ASIN         = 'B07ZQMG51R';             // known-live (stub/shape guard)

    private ?MTB_Affiliate_Amazon_Client $client;
    private ?MTB_Affiliate_Settings $settings;
    private ?MTB_Affiliate_Audit_Service $audit;

    public function __construct(
        ?MTB_Affiliate_Amazon_Client $client = null,
        ?MTB_Affiliate_Settings $settings = null,
        ?MTB_Affiliate_Audit_Service $audit = null
    ) {
        $this->client   = $client;
        $this->settings = $settings;
        $this->audit    = $audit;
    }

    private function client(): MTB_Affiliate_Amazon_Client {
        if ($this->client === null) {
            $this->client = new MTB_Affiliate_Amazon_Client();
        }
        return $this->client;
    }

    private function settings(): MTB_Affiliate_Settings {
        if ($this->settings === null) {
            $this->settings = new MTB_Affiliate_Settings();
        }
        return $this->settings;
    }

    private function audit(): MTB_Affiliate_Audit_Service {
        if ($this->audit === null) {
            $this->audit = new MTB_Affiliate_Audit_Service();
        }
        return $this->audit;
    }

    // ---- schema -----------------------------------------------------------

    public static function create_table(): void {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
  asin varchar(10) NOT NULL,
  status varchar(20) NOT NULL,
  price_text varchar(64) NULL,
  title varchar(255) NULL,
  image_url text NULL,
  consecutive_unavailable_count int unsigned NOT NULL DEFAULT 0,
  first_seen_unavailable_at datetime NULL,
  last_status_change_at datetime NULL,
  checked_at datetime NOT NULL,
  http_note varchar(255) NULL,
  PRIMARY KEY  (asin),
  KEY status (status),
  KEY checked_at (checked_at)
) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    public static function needs_upgrade(): bool {
        return get_option(self::DB_VERSION_OPTION, '0') !== self::DB_VERSION;
    }

    /** Single-flight idempotent upgrade for the FTP/git file-update path (see Click-Tracker RC). */
    public static function maybe_upgrade(): void {
        if (! self::needs_upgrade()) {
            return;
        }
        if (function_exists('get_transient')) {
            if (get_transient('mtb_availability_upgrading')) {
                return;
            }
            set_transient('mtb_availability_upgrading', 1, 30);
        }
        self::create_table();
        if (function_exists('delete_transient')) {
            delete_transient('mtb_availability_upgrading');
        }
    }

    public function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    // ---- cron registration ------------------------------------------------

    /** Self-register the weekly cron after a plain file-update (no activation hook fires). */
    public function ensure_scheduled(): void {
        if (function_exists('wp_next_scheduled') && ! wp_next_scheduled(self::KICKOFF_HOOK)) {
            wp_schedule_event(time() + 3600, self::CRON_SCHEDULE, self::KICKOFF_HOOK);
        }
    }

    public function add_cron_schedule(array $schedules): array {
        if (! isset($schedules[self::CRON_SCHEDULE])) {
            $schedules[self::CRON_SCHEDULE] = ['interval' => WEEK_IN_SECONDS, 'display' => 'Once Weekly (MTB)'];
        }
        return $schedules;
    }

    private function schedule_tick(int $delay = self::TICK_DELAY): void {
        if (function_exists('wp_schedule_single_event')) {
            wp_schedule_single_event(time() + max(1, $delay), self::TICK_HOOK);
        }
    }

    // ---- REST -------------------------------------------------------------

    public function register_routes(): void {
        if (! function_exists('register_rest_route')) {
            return;
        }
        register_rest_route('mtb-affiliate-cards/v1', '/availability', [
            'methods'             => 'GET',
            'callback'            => [$this, 'rest_list'],
            'permission_callback' => [$this, 'can_read'],
        ]);
        register_rest_route('mtb-affiliate-cards/v1', '/availability/posts', [
            'methods'             => 'GET',
            'callback'            => [$this, 'rest_posts'],
            'permission_callback' => [$this, 'can_read'],
        ]);
        register_rest_route('mtb-affiliate-cards/v1', '/availability/scan', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_scan'],
            'permission_callback' => [$this, 'can_manage'],
        ]);
    }

    public function can_read(): bool {
        return ! function_exists('current_user_can') || (bool) current_user_can('edit_posts');
    }

    public function can_manage(): bool {
        return ! function_exists('current_user_can') || (bool) current_user_can('manage_options');
    }

    public function rest_list($request): \WP_REST_Response {
        global $wpdb;
        $table = $this->table_name();
        $status = strtolower(trim((string) $request->get_param('status')));
        $repair = (string) $request->get_param('repair') === '1';

        if ($repair) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE status = %s OR (status = %s AND consecutive_unavailable_count >= 3)
                 ORDER BY checked_at DESC LIMIT 5000",
                'not_found', 'unavailable_temp'
            ), ARRAY_A);
        } elseif (in_array($status, ['available', 'unavailable_temp', 'not_found', 'api_inaccessible', 'error', 'replaced'], true)) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s ORDER BY checked_at DESC LIMIT 5000", $status
            ), ARRAY_A);
        } else {
            $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY checked_at DESC LIMIT 5000", ARRAY_A);
        }

        $rows  = is_array($rows) ? $rows : [];
        $alarm = get_option(self::AUTH_ALARM_OPTION, '');
        return new \WP_REST_Response([
            'items' => array_map([$this, 'shape_row'], $rows),
            'count' => count($rows),
            'alarm' => $alarm !== '' ? $alarm : null,
            'cursor'=> (int) get_option(self::CURSOR_OPTION, 0),
            'worklist_size' => count((array) get_option(self::WORKLIST_OPTION, [])),
        ], 200);
    }

    /** Query-time JOIN: which published posts contain repair-candidate ASINs (RC5, no cache). */
    public function rest_posts($request): \WP_REST_Response {
        global $wpdb;
        $table = $this->table_name();
        $candidates = $wpdb->get_col($wpdb->prepare(
            "SELECT asin FROM {$table}
             WHERE status = %s OR (status = %s AND consecutive_unavailable_count >= 3)",
            'not_found', 'unavailable_temp'
        ));
        $candidates = array_flip(array_map('strtoupper', (array) $candidates));
        if ($candidates === []) {
            return new \WP_REST_Response(['items' => [], 'count' => 0], 200);
        }

        $out = [];
        foreach ($this->iterate_post_asins() as $postId => $asins) {
            $dead = array_values(array_filter($asins, static fn(string $a): bool => isset($candidates[$a])));
            if ($dead !== []) {
                $out[] = ['post_id' => $postId, 'dead_asins' => $dead];
            }
        }
        return new \WP_REST_Response(['items' => $out, 'count' => count($out)], 200);
    }

    public function rest_scan($request): \WP_REST_Response {
        $asins = $request->get_param('asins');
        $debug = (bool) $request->get_param('debug');

        if (is_array($asins) && $asins !== []) {
            // Synchronous eich-stichprobe / targeted scan — does NOT touch cron state.
            $asins = array_values(array_filter(array_map('strtoupper', array_map('strval', $asins)),
                static fn(string $a): bool => (bool) preg_match('/^[A-Z0-9]{10}$/', $a)));
            $context = $this->get_context();
            $r = $this->client()->classify_items($asins, $context, $debug);
            $abort = $this->apply_batch($asins, $r);
            $statuses = [];
            foreach ($asins as $a) {
                $statuses[$a] = ($r['auth_state'] === 'ok') ? $this->classify_asin($a, $r) : ('(' . $r['auth_state'] . ')');
            }
            $resp = [
                'mode'        => 'sync',
                'auth_state'  => $r['auth_state'],
                'http_status' => $r['http_status'],
                'result'      => $abort,
                'statuses'    => $statuses,
                'items'       => $r['items'],
                'errors'      => $r['errors'],
            ];
            if ($debug) {
                $resp['raw']        = $r['raw'];
                $resp['raw_errors'] = $r['raw_errors'];
            }
            return new \WP_REST_Response($resp, 200);
        }

        // No explicit asins → kick off the resumable full scan.
        $this->kickoff();
        return new \WP_REST_Response([
            'mode'          => 'kickoff',
            'worklist_size' => count((array) get_option(self::WORKLIST_OPTION, [])),
            'cursor'        => (int) get_option(self::CURSOR_OPTION, 0),
            'note'          => get_option(self::NOTE_OPTION, ''),
        ], 200);
    }

    private function shape_row(array $r): array {
        return [
            'asin'    => (string) $r['asin'],
            'status'  => (string) $r['status'],
            'price'   => $r['price_text'] !== null ? (string) $r['price_text'] : null,
            'title'   => $r['title'] !== null ? (string) $r['title'] : null,
            'image'   => $r['image_url'] !== null ? (string) $r['image_url'] : null,
            'unavailable_streak' => (int) $r['consecutive_unavailable_count'],
            'first_seen_unavailable_at' => $r['first_seen_unavailable_at'],
            'last_status_change_at'     => $r['last_status_change_at'],
            'checked_at' => (string) $r['checked_at'],
            'note'    => $r['http_note'] !== null ? (string) $r['http_note'] : null,
        ];
    }

    // ---- cron run ---------------------------------------------------------

    public function kickoff(): void {
        $worklist = (array) get_option(self::WORKLIST_OPTION, []);
        $cursor   = (int) get_option(self::CURSOR_OPTION, -1);
        // Idempotent: a scan already mid-cursor → just keep it ticking, never restart.
        if ($worklist !== [] && $cursor >= 0 && $cursor < count($worklist)) {
            $this->schedule_tick();
            return;
        }
        $asins = $this->collect_asins();
        if ($asins === []) {
            update_option(self::NOTE_OPTION, 'kickoff_aborted_empty_worklist', false);
            return; // RC5: never clobber a good worklist with an empty one
        }
        update_option(self::WORKLIST_OPTION, $asins, false);
        update_option(self::CURSOR_OPTION, 0, false);
        delete_option(self::AUTH_ALARM_OPTION);
        delete_option(self::STREAK_OPTION);
        update_option(self::NOTE_OPTION, 'scan_started_' . gmdate('c') . '_size_' . count($asins), false);
        $this->schedule_tick();
    }

    public function tick(): void {
        $owner = $this->acquire_lock();
        if ($owner === '') {
            return; // another tick holds the lock
        }
        try {
            $worklist = (array) get_option(self::WORKLIST_OPTION, []);
            $cursor   = (int) get_option(self::CURSOR_OPTION, 0);
            if ($worklist === [] || $cursor >= count($worklist)) {
                return; // done
            }

            $context = $this->get_context();

            // RC2/RC4 runtime stub/shape guard on the first batch.
            if ($cursor === 0 && ! $this->canary_ok($context)) {
                update_option(self::AUTH_ALARM_OPTION, 'canary_failed_' . gmdate('c'), false);
                return; // abort scan
            }

            $batch = array_slice($worklist, $cursor, self::BATCH_SIZE);
            $r     = $this->client()->classify_items($batch, $context);
            $verdict = $this->apply_batch($batch, $r);

            if ($verdict === 'abort') {
                update_option(self::AUTH_ALARM_OPTION, $r['auth_state'] . '_' . gmdate('c'), false);
                return;
            }
            if ($verdict === 'backoff') {
                $this->schedule_tick(self::BACKOFF_DELAY); // 429 — retry same cursor later
                return;
            }

            $next = $cursor + count($batch);
            update_option(self::CURSOR_OPTION, $next, false);
            if ($next < count($worklist)) {
                $this->schedule_tick(self::TICK_DELAY);
            } else {
                update_option(self::NOTE_OPTION, 'scan_complete_' . gmdate('c'), false);
            }
        } finally {
            $this->release_lock($owner);
        }
    }

    /** @return 'ok'|'abort'|'backoff' */
    private function apply_batch(array $batch, array $r): string {
        switch ($r['auth_state']) {
            case 'token_failed':
            case 'access_revoked':
            case 'config_error':
                return 'abort';
            case 'rate_limited':
                return 'backoff';
            case 'transient_error':
                foreach ($batch as $asin) {
                    $this->record_status(strtoupper((string) $asin), 'error', ['http_note' => 'transient_' . $r['http_status']]);
                }
                $this->bump_error_streak(true, 0);
                return 'ok';
            case 'ok':
            default:
                $available = 0;
                foreach ($batch as $asin) {
                    $asin   = strtoupper((string) $asin);
                    $status = $this->classify_asin($asin, $r);
                    if ($status === 'available') {
                        $available++;
                    }
                    $it = $r['items'][$asin] ?? [];
                    $this->record_status($asin, $status, [
                        'price_text' => $it['price_text'] ?? null,
                        'title'      => $it['title'] ?? null,
                        'image_url'  => $it['image_url'] ?? null,
                        'http_note'  => $r['errors'][$asin] ?? null,
                    ]);
                }
                $this->bump_error_streak(false, $available);
                return 'ok';
        }
    }

    /** Pure classification from a classify_items() result (RC3). */
    public function classify_asin(string $asin, array $r): string {
        $asin = strtoupper($asin);
        if (isset($r['items'][$asin])) {
            $it = $r['items'][$asin];
            if (! empty($it['offers_present'])) {
                // available = a listing exists (buyable). Price is OPTIONAL — a live BuyBox item can
                // return a listing with no price (MAP "Preis erst im Warenkorb"); verified live on the
                // canary B07ZQMG51R 2026-06-03. Price must NOT gate availability. Empty listings = no
                // current offer = unavailable_temp.
                return ! empty($it['has_listing']) ? 'available' : 'unavailable_temp';
            }
            return 'error'; // item present but offers absent/unparseable → transient, keep last status
        }
        if (isset($r['errors'][$asin])) {
            $code = (string) $r['errors'][$asin];
            if (stripos($code, 'InvalidParameterValue') !== false) {
                return 'not_found';
            }
            return 'api_inaccessible'; // ItemNotAccessible or any other item-level error — never "dead"
        }
        return 'error'; // requested but neither item nor resolvable error → ambiguous, retry
    }

    /** Hysteresis-aware upsert (RC1/RC7). 'error' keeps the last good status; 'replaced' is terminal. */
    private function record_status(string $asin, string $status, array $fields): void {
        global $wpdb;
        $table = $this->table_name();
        $now   = gmdate('Y-m-d H:i:s');

        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE asin = %s", $asin), ARRAY_A);

        // 'replaced' is terminal — a scan never downgrades it.
        if ($existing && ($existing['status'] ?? '') === 'replaced') {
            $wpdb->update($table, ['checked_at' => $now], ['asin' => $asin]);
            return;
        }

        // transient 'error' must not clobber a known status / streak (RC1/RC7).
        if ($status === 'error') {
            if ($existing) {
                $wpdb->update($table,
                    ['checked_at' => $now, 'http_note' => (string) ($fields['http_note'] ?? 'error')],
                    ['asin' => $asin]);
            } else {
                $wpdb->insert($table, [
                    'asin' => $asin, 'status' => 'error', 'consecutive_unavailable_count' => 0,
                    'checked_at' => $now, 'http_note' => (string) ($fields['http_note'] ?? 'error'),
                    'last_status_change_at' => $now,
                ]);
            }
            return;
        }

        $prevStatus = $existing['status'] ?? null;
        $count      = (int) ($existing['consecutive_unavailable_count'] ?? 0);
        $firstSeen  = $existing['first_seen_unavailable_at'] ?? null;

        if ($status === 'unavailable_temp') {
            if ($prevStatus === 'unavailable_temp') {
                $count++;
            } else {
                $count     = 1;
                $firstSeen = $now;
            }
        } else { // available / not_found / api_inaccessible
            $count     = 0;
            $firstSeen = null;
        }

        $lastChange = ($prevStatus !== $status) ? $now : ($existing['last_status_change_at'] ?? $now);

        $data = [
            'asin'                          => $asin,
            'status'                        => $status,
            'price_text'                    => $fields['price_text'] ?? null,
            'title'                         => $fields['title'] ?? null,
            'image_url'                     => $fields['image_url'] ?? null,
            'consecutive_unavailable_count' => $count,
            'first_seen_unavailable_at'     => $firstSeen,
            'last_status_change_at'         => $lastChange,
            'checked_at'                    => $now,
            'http_note'                     => $fields['http_note'] ?? null,
        ];

        if ($existing) {
            $wpdb->update($table, $data, ['asin' => $asin]);
        } else {
            $wpdb->insert($table, $data);
        }
    }

    private function bump_error_streak(bool $hadError, int $available): void {
        if ($available > 0) {
            delete_option(self::STREAK_OPTION);
            return;
        }
        if ($hadError) {
            $s = (int) get_option(self::STREAK_OPTION, 0) + 1;
            update_option(self::STREAK_OPTION, $s, false);
            if ($s >= self::ERROR_STREAK_ALARM && get_option(self::AUTH_ALARM_OPTION, '') === '') {
                update_option(self::AUTH_ALARM_OPTION, 'error_streak_' . $s . '_' . gmdate('c'), false);
            }
        }
    }

    private function canary_ok(array $context): bool {
        $r = $this->client()->classify_items([self::CANARY_ASIN], $context);
        if ($r['auth_state'] !== 'ok') {
            return false;
        }
        return $this->classify_asin(self::CANARY_ASIN, $r) === 'available';
    }

    // ---- lock (option-based, with stale-lock watchdog) --------------------

    private function acquire_lock(): string {
        $lock = get_option(self::LOCK_OPTION, null);
        $now  = time();
        if (is_array($lock) && isset($lock['started_at']) && ($now - (int) $lock['started_at']) < self::LOCK_TTL) {
            return ''; // active lock
        }
        $owner = uniqid('mtbavail_', true);
        update_option(self::LOCK_OPTION, ['owner' => $owner, 'started_at' => $now], false);
        $check = get_option(self::LOCK_OPTION, null);
        return (is_array($check) && ($check['owner'] ?? '') === $owner) ? $owner : '';
    }

    private function release_lock(string $owner): void {
        $lock = get_option(self::LOCK_OPTION, null);
        if (is_array($lock) && ($lock['owner'] ?? '') === $owner) {
            delete_option(self::LOCK_OPTION);
        }
    }

    // ---- ASIN source (RC6: reuse the audit extractor, single source) ------

    private function get_context(): array {
        $s = $this->settings()->get_all();
        return [
            'client_id'     => (string) ($s['client_id'] ?? ''),
            'client_secret' => (string) ($s['client_secret'] ?? ''),
            'marketplace'   => (string) ($s['marketplace'] ?? 'www.amazon.de'),
            'partner_tag'   => $this->partner_tag(),
        ];
    }

    private function partner_tag(): string {
        $t = trim((string) get_option(self::PARTNER_TAG_OPTION, ''));
        return $t !== '' ? $t : self::DEFAULT_PARTNER_TAG;
    }

    private function collect_asins(): array {
        $asins = [];
        foreach ($this->iterate_post_asins() as $postAsins) {
            foreach ($postAsins as $a) {
                $asins[$a] = true;
            }
        }
        // exclude terminal 'replaced'
        global $wpdb;
        $replaced = $wpdb->get_col($wpdb->prepare(
            "SELECT asin FROM {$this->table_name()} WHERE status = %s", 'replaced'));
        foreach ((array) $replaced as $r) {
            unset($asins[strtoupper((string) $r)]);
        }
        return array_keys($asins);
    }

    /**
     * Yields post_id => [ASIN,…] for published posts, using the audit service's extractor (RC6),
     * which covers amazon:ASIN markers and /dp/ASIN links (incl. card-block detail URLs).
     *
     * @return iterable<int,string[]>
     */
    private function iterate_post_asins(): iterable {
        global $wpdb;
        $audit = $this->audit();
        $rows  = $wpdb->get_results(
            "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status='publish' AND post_type='post'",
            ARRAY_A
        );
        foreach ((array) $rows as $row) {
            $content = (string) ($row['post_content'] ?? '');
            if ($content === '') {
                continue;
            }
            $state = $audit->scan_post_content($content);
            $out   = [];
            foreach ((array) ($state['asins'] ?? []) as $a) {
                $a = strtoupper((string) $a);
                if (preg_match('/^[A-Z0-9]{10}$/', $a)) {
                    $out[$a] = true;
                }
            }
            yield (int) $row['ID'] => array_keys($out);
        }
    }
}
