<?php

declare(strict_types=1);

/**
 * Click tracking for affiliate links — per ASIN, per post, per day.
 *
 * Self-contained: owns its table, registers its own REST routes, and enqueues a tiny
 * front-end beacon script. Counts clicks WITHOUT a redirect (see concept Codex-Refute R3):
 * the DOM link stays a direct amazon.de/dp/ASIN?tag=… URL (no cloaking, no single point
 * of failure, migration-safe). A delegated click/auxclick listener fires
 * navigator.sendBeacon() and lets the navigation proceed normally. No PII is stored —
 * only daily counters.
 */
final class MTB_Affiliate_Click_Tracker {
    private const TABLE_SUFFIX = 'mtb_affiliate_clicks';
    private const DB_VERSION = '1.0';
    private const DB_VERSION_OPTION = 'mtb_affiliate_clicks_db_version';
    private const RL_WINDOW_SECONDS = 60;
    private const RL_MAX_PER_WINDOW = 40; // per-IP cap; excess silently not counted

    public static function create_table(): void {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset = $wpdb->get_charset_collate();

        // day buckets keep it PII-free and compact; UNIQUE(asin,post_id,day) enables atomic upsert.
        $sql = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  asin varchar(10) NOT NULL,
  post_id bigint(20) unsigned NOT NULL,
  day date NOT NULL,
  clicks int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY asin_post_day (asin,post_id,day),
  KEY post_id (post_id),
  KEY day (day)
) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    public static function needs_upgrade(): bool {
        return get_option(self::DB_VERSION_OPTION, '0') !== self::DB_VERSION;
    }

    public function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public function register_routes(): void {
        if (! function_exists('register_rest_route')) {
            return;
        }

        // Public: receives a click beacon. No redirect, only a counter increment.
        register_rest_route('mtb-affiliate-cards/v1', '/click', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_click'],
            'permission_callback' => '__return_true',
            'args'                => [
                'asin'   => ['type' => 'string',  'required' => true],
                'postId' => ['type' => 'integer', 'required' => true],
            ],
        ]);

        // Auth: aggregated click data for the web-app / agent.
        register_rest_route('mtb-affiliate-cards/v1', '/clicks', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_clicks'],
            'permission_callback' => [$this, 'can_read_clicks'],
            'args'                => [
                'post_id' => ['type' => 'integer', 'required' => false],
                'asin'    => ['type' => 'string',  'required' => false],
                'since'   => ['type' => 'string',  'required' => false], // Y-m-d
            ],
        ]);
    }

    public function can_read_clicks(): bool {
        if (! function_exists('current_user_can')) {
            return true;
        }
        return (bool) current_user_can('edit_posts');
    }

    /**
     * Enqueue the front-end beacon on singular posts only.
     */
    public function enqueue_frontend(): void {
        if (! function_exists('is_singular') || ! is_singular('post')) {
            return;
        }
        if (! function_exists('wp_enqueue_script')) {
            return;
        }
        $post_id = (int) (function_exists('get_the_ID') ? get_the_ID() : 0);
        if ($post_id <= 0) {
            return;
        }

        wp_enqueue_script(
            'mtb-affiliate-click-beacon',
            MTB_AFFILIATE_CARDS_URL . 'assets/click-beacon.js',
            [],
            MTB_AFFILIATE_CARDS_VERSION,
            true
        );
        wp_localize_script('mtb-affiliate-click-beacon', 'mtbAffiliateClick', [
            'endpoint' => esc_url_raw(rest_url('mtb-affiliate-cards/v1/click')),
            'postId'   => $post_id,
        ]);
    }

    /**
     * Record one click. Validates strictly, rate-limits per IP, upserts atomically.
     * Always returns 200 (a tracking beacon must never surface an error to the reader).
     */
    public function handle_click($request): \WP_REST_Response {
        $asin    = strtoupper(trim((string) $request->get_param('asin')));
        $post_id = (int) $request->get_param('postId');

        if (! preg_match('/^[A-Z0-9]{10}$/', $asin)) {
            return new \WP_REST_Response(['ok' => false, 'reason' => 'bad_asin'], 200);
        }
        if ($post_id <= 0 || get_post_status($post_id) !== 'publish') {
            return new \WP_REST_Response(['ok' => false, 'reason' => 'bad_post'], 200);
        }
        if (! $this->allow_request()) {
            return new \WP_REST_Response(['ok' => false, 'reason' => 'rate_limited'], 200);
        }

        $this->record($asin, $post_id);
        return new \WP_REST_Response(['ok' => true], 200);
    }

    public function record(string $asin, int $post_id): void {
        global $wpdb;
        $table = $this->table_name();
        $day   = gmdate('Y-m-d');

        // Atomic increment — no read-modify-write race under concurrent clicks.
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (asin, post_id, day, clicks) VALUES (%s, %d, %s, 1)
             ON DUPLICATE KEY UPDATE clicks = clicks + 1",
            $asin,
            $post_id,
            $day
        ));
    }

    /**
     * Per-IP rate limit via transient. Returns false when the window cap is exceeded.
     * IP is hashed (no raw IP stored) — PII-free.
     */
    private function allow_request(): bool {
        if (! function_exists('get_transient')) {
            return true;
        }
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        if ($ip === '') {
            return true;
        }
        $key   = 'mtb_click_rl_' . md5($ip);
        $count = (int) get_transient($key);
        if ($count >= self::RL_MAX_PER_WINDOW) {
            return false;
        }
        set_transient($key, $count + 1, self::RL_WINDOW_SECONDS);
        return true;
    }

    /**
     * Aggregated click data. Optional filters: post_id, asin, since (Y-m-d).
     *
     * @return \WP_REST_Response
     */
    public function get_clicks($request): \WP_REST_Response {
        global $wpdb;
        $table = $this->table_name();

        $where  = ['1=1'];
        $params = [];

        $post_id = (int) $request->get_param('post_id');
        if ($post_id > 0) {
            $where[]  = 'post_id = %d';
            $params[] = $post_id;
        }
        $asin = strtoupper(trim((string) $request->get_param('asin')));
        if (preg_match('/^[A-Z0-9]{10}$/', $asin)) {
            $where[]  = 'asin = %s';
            $params[] = $asin;
        }
        $since = (string) $request->get_param('since');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
            $where[]  = 'day >= %s';
            $params[] = $since;
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT asin, post_id, SUM(clicks) AS clicks, MAX(day) AS last_day
                FROM {$table} WHERE {$where_sql}
                GROUP BY asin, post_id ORDER BY clicks DESC LIMIT 5000";

        $rows = $params === []
            ? $wpdb->get_results($sql, ARRAY_A)
            : $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $rows = is_array($rows) ? $rows : [];
        $out  = array_map(static function (array $r): array {
            return [
                'asin'     => (string) $r['asin'],
                'post_id'  => (int) $r['post_id'],
                'clicks'   => (int) $r['clicks'],
                'last_day' => (string) $r['last_day'],
            ];
        }, $rows);

        return new \WP_REST_Response(['items' => $out, 'count' => count($out)], 200);
    }
}
