<?php

declare(strict_types=1);

require_once __DIR__ . '/class-mtb-affiliate-rest-controller.php';
require_once __DIR__ . '/class-mtb-affiliate-token-prepass.php';
require_once __DIR__ . '/class-mtb-affiliate-product-library-list-table.php';

final class MTB_Affiliate_Plugin {
    private static ?MTB_Affiliate_Plugin $instance = null;

    private MTB_Affiliate_Settings $settings;
    private MTB_Affiliate_Block $block;
    private MTB_Affiliate_Audit_Service $auditService;
    private MTB_Affiliate_Amazon_Client $amazonClient;
    private MTB_Affiliate_Product_Library $productLibrary;
    private MTB_Affiliate_Rest_Controller $restController;
    private MTB_Affiliate_Tracking_Registry $trackingRegistry;
    private MTB_Affiliate_Url_Resolver $urlResolver;
    private MTB_Affiliate_Telegram_Handler $telegramHandler;

    private function __construct() {
        $this->settings         = new MTB_Affiliate_Settings();
        $this->auditService     = new MTB_Affiliate_Audit_Service();
        $this->amazonClient     = new MTB_Affiliate_Amazon_Client();
        $this->trackingRegistry = new MTB_Affiliate_Tracking_Registry();
        $this->urlResolver      = new MTB_Affiliate_Url_Resolver();
        $this->productLibrary   = new MTB_Affiliate_Product_Library();
        $this->telegramHandler  = new MTB_Affiliate_Telegram_Handler(
            $this->settings,
            $this->urlResolver,
            $this->trackingRegistry,
            $this->productLibrary
        );
        $this->block          = new MTB_Affiliate_Block($this->settings, $this->amazonClient);
        $this->restController = new MTB_Affiliate_Rest_Controller(
            $this->settings,
            $this->amazonClient,
            null,
            $this->telegramHandler,
            $this->productLibrary
        );
    }

    public static function instance(): MTB_Affiliate_Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action( 'admin_menu', [ $this, 'register_product_library_menu' ] );
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [$this, 'register_assets']);
        add_action('init', [$this->block, 'register']);
        add_action('rest_api_init', [$this->restController, 'register_routes']);
        add_action('save_post', [$this, 'handle_save_post'], 20, 3);
        add_action('save_post', [$this, 'handle_save_post_sync_library'], 30, 2);
        add_action('admin_post_mtb_affiliate_audit', [$this, 'handle_audit_admin_post']);
        add_action('wp_ajax_mtb_check_webhook_status', [$this, 'ajax_check_webhook_status']);
    }

    public static function activate(): void {
        $settings = new MTB_Affiliate_Settings();
        $settings->save($settings->defaults());
        MTB_Affiliate_Tracking_Registry::create_table();
        MTB_Affiliate_Product_Library::create_table();
    }

    public function ajax_check_webhook_status(): void {
        if (function_exists('check_ajax_referer')) {
            check_ajax_referer('mtb_webhook_status_check', 'nonce');
        }
        if (! function_exists('current_user_can') || ! current_user_can('manage_options')) {
            wp_die();
        }

        $settings = $this->settings->get_all();
        $botToken = $settings['telegram_bot_token'] ?? '';
        if ($botToken === '') {
            wp_send_json(['active' => false, 'error' => 'Kein Bot-Token konfiguriert.']);
            return;
        }

        $response = wp_remote_get(
            'https://api.telegram.org/bot' . $botToken . '/getWebhookInfo',
            ['timeout' => 10]
        );

        if (is_wp_error($response)) {
            wp_send_json(['active' => false, 'error' => $response->get_error_message()]);
            return;
        }

        $body       = json_decode((string) wp_remote_retrieve_body($response), true);
        $webhookUrl = $body['result']['url'] ?? '';
        $active     = $webhookUrl !== '' && str_contains($webhookUrl, 'telegram');

        wp_send_json([
            'active'  => $active,
            'url'     => $webhookUrl,
            'pending' => (int) ($body['result']['pending_update_count'] ?? 0),
        ]);
    }

    public function register_settings(): void {
        $this->settings->register();
    }

    public function register_settings_page(): void {
        if (! function_exists('add_options_page')) {
            return;
        }

        add_options_page(
            'Affiliate Card',
            'Affiliate Card',
            'manage_options',
            'mtb-affiliate-cards',
            [$this, 'render_settings_page']
        );
    }

    public function register_product_library_menu(): void {
        if ( ! function_exists( 'add_menu_page' ) ) {
            return;
        }

        add_menu_page(
            __( 'Affiliate Cards', 'meintechblog-affiliate-cards' ),
            __( 'Affiliate Cards', 'meintechblog-affiliate-cards' ),
            'manage_options',
            'mtb-affiliate-cards-menu',
            [ $this, 'render_product_library_page' ],
            'dashicons-products',
            58
        );

        add_submenu_page(
            'mtb-affiliate-cards-menu',
            __( 'Produkt-Bibliothek', 'meintechblog-affiliate-cards' ),
            __( 'Produkt-Bibliothek', 'meintechblog-affiliate-cards' ),
            'manage_options',
            'mtb-affiliate-cards-menu',
            [ $this, 'render_product_library_page' ]
        );
    }

    public function render_product_library_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $table = new MTB_Affiliate_Product_Library_List_Table( $this->productLibrary );
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Produkt-Bibliothek', 'meintechblog-affiliate-cards' ); ?></h1>
            <form method="post">
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    public function register_assets(): void {
        if (! function_exists('wp_register_style')) {
            return;
        }

        wp_register_style(
            'mtb-affiliate-cards-frontend',
            MTB_AFFILIATE_CARDS_URL . 'assets/frontend.css',
            [],
            MTB_AFFILIATE_CARDS_VERSION
        );

        wp_register_style(
            'mtb-affiliate-cards-admin',
            MTB_AFFILIATE_CARDS_URL . 'assets/admin.css',
            [],
            MTB_AFFILIATE_CARDS_VERSION
        );
    }

    public function render_settings_page(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = $this->settings->get_all();
        $tab = $this->current_admin_tab();
        ?>
        <div class="wrap">
            <h1>Affiliate Card</h1>
            <p>Grundkonfiguration und Audit-Werkzeuge fuer die nativen Amazon-Affiliate-Cards auf meintechblog.de.</p>
            <nav class="nav-tab-wrapper">
                <a class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>" href="?page=mtb-affiliate-cards&tab=settings">Einstellungen</a>
                <a class="nav-tab <?php echo $tab === 'audit' ? 'nav-tab-active' : ''; ?>" href="?page=mtb-affiliate-cards&tab=audit">Affiliate Audit</a>
                <a class="nav-tab <?php echo $tab === 'telegram' ? 'nav-tab-active' : ''; ?>" href="?page=mtb-affiliate-cards&tab=telegram">Telegram Bot</a>
            </nav>
            <?php if ($tab === 'audit') : ?>
                <?php $this->render_audit_tab(); ?>
            <?php elseif ($tab === 'telegram') : ?>
                <?php $this->render_telegram_tab(); ?>
            <?php else : ?>
                <form method="post" action="options.php">
                    <?php if (function_exists('settings_fields')) : ?>
                        <?php settings_fields($this->settings->settings_group()); ?>
                    <?php endif; ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="mtb-cta-label">CTA-Text</label></th>
                            <td><input id="mtb-cta-label" type="text" class="regular-text" name="<?php echo esc_attr($this->settings->option_name()); ?>[cta_label]" value="<?php echo esc_attr($settings['cta_label']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mtb-badge-mode">Badge-Modus</label></th>
                            <td>
                                <select id="mtb-badge-mode" name="<?php echo esc_attr($this->settings->option_name()); ?>[badge_mode]">
                                    <option value="auto" <?php selected($settings['badge_mode'], 'auto'); ?>>Automatisch</option>
                                    <option value="video" <?php selected($settings['badge_mode'], 'video'); ?>>Immer Im Video verwendet</option>
                                    <option value="setup" <?php selected($settings['badge_mode'], 'setup'); ?>>Immer Passend zu diesem Setup</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mtb-marketplace">Marketplace</label></th>
                            <td><input id="mtb-marketplace" type="text" class="regular-text" name="<?php echo esc_attr($this->settings->option_name()); ?>[marketplace]" value="<?php echo esc_attr($settings['marketplace']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mtb-client-id">Amazon Client ID</label></th>
                            <td><input id="mtb-client-id" type="text" class="regular-text code" name="<?php echo esc_attr($this->settings->option_name()); ?>[client_id]" value="<?php echo esc_attr($settings['client_id']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mtb-client-secret">Amazon Client Secret</label></th>
                            <td><input id="mtb-client-secret" type="password" class="regular-text code" name="<?php echo esc_attr($this->settings->option_name()); ?>[client_secret]" value="<?php echo esc_attr($settings['client_secret']); ?>" autocomplete="new-password"></td>
                        </tr>
                        <tr>
                            <th scope="row">Kurze Titel bevorzugen</th>
                            <td><label><input type="checkbox" name="<?php echo esc_attr($this->settings->option_name()); ?>[auto_shorten_titles]" value="1" <?php checked($settings['auto_shorten_titles']); ?>> Automatische Kürzung im aktuellen Live-Stil aktivieren</label></td>
                        </tr>
                    </table>
                    <?php if (function_exists('submit_button')) : ?>
                        <?php submit_button('Einstellungen speichern'); ?>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_audit_tab(): void {
        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style('mtb-affiliate-cards-admin');
        }

        $search = trim((string) ($_GET['mtb-audit-search'] ?? ''));
        $statusFilter = trim((string) ($_GET['mtb-audit-status'] ?? ''));
        $rows = $this->filter_audit_rows($this->auditService->list_recent_rows(50), $search, $statusFilter);
        $summary = [
            'Offen' => 0,
            'Legacy-Fälle' => 0,
            'Manuell prüfen' => 0,
            'Geradegezogen' => 0,
            'Fehler' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'legacy') {
                $summary['Legacy-Fälle']++;
            } elseif ($status === 'manuell_pruefen') {
                $summary['Manuell prüfen']++;
            } elseif ($status === 'geradegezogen') {
                $summary['Geradegezogen']++;
            } elseif ($status === 'fehler') {
                $summary['Fehler']++;
            } else {
                $summary['Offen']++;
            }
        }
        ?>
        <div class="mtb-affiliate-audit">
            <?php $this->render_audit_notice(); ?>
            <div class="mtb-affiliate-audit-summary">
                <?php foreach ($summary as $label => $count) : ?>
                    <div class="mtb-affiliate-audit-card">
                        <strong><?php echo $label; ?></strong>
                        <span><?php echo $count; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="get" class="mtb-affiliate-audit-filters">
                <input type="hidden" name="page" value="mtb-affiliate-cards">
                <input type="hidden" name="tab" value="audit">
                <input type="search" name="mtb-audit-search" value="<?php echo esc_attr($search); ?>" placeholder="Titel oder ASIN suchen">
                <select name="mtb-audit-status">
                    <option value="">Alle Stati</option>
                    <option value="offen" <?php echo selected($statusFilter, 'offen'); ?>>Offen</option>
                    <option value="legacy" <?php echo selected($statusFilter, 'legacy'); ?>>Legacy-Fälle</option>
                    <option value="manuell_pruefen" <?php echo selected($statusFilter, 'manuell_pruefen'); ?>>Manuell prüfen</option>
                    <option value="geradegezogen" <?php echo selected($statusFilter, 'geradegezogen'); ?>>Geradegezogen</option>
                    <option value="fehler" <?php echo selected($statusFilter, 'fehler'); ?>>Fehler</option>
                </select>
                <button type="submit" class="button">Filtern</button>
            </form>
            <table class="widefat striped mtb-affiliate-audit-table">
                <thead>
                    <tr>
                        <th scope="col">Datum</th>
                        <th scope="col">Beitrag</th>
                        <th scope="col">Status</th>
                        <th scope="col">Affiliate-Funde</th>
                        <th scope="col">Tracking-ID</th>
                        <th scope="col">Cards</th>
                        <th scope="col">Letzte Prüfung</th>
                        <th scope="col">Kurzlog</th>
                        <th scope="col">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []) : ?>
                        <tr>
                            <td colspan="9">Noch keine Audit-Daten vorhanden.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><?php echo esc_attr(substr((string) $row['date'], 0, 10)); ?></td>
                                <td>
                                    <a href="<?php echo esc_attr((string) $row['edit_link']); ?>"><?php echo esc_attr((string) $row['title']); ?></a>
                                    <?php if (($row['asins'] ?? []) !== []) : ?>
                                        <div class="mtb-affiliate-audit-meta"><?php echo esc_attr(implode(', ', (array) $row['asins'])); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="mtb-affiliate-status-badge is-<?php echo esc_attr((string) $row['status']); ?>"><?php echo esc_attr($this->human_audit_status((string) $row['status'])); ?></span></td>
                                <td><?php echo esc_attr((string) $row['affiliate_finds']); ?></td>
                                <td><span class="mtb-affiliate-tracking-pill is-<?php echo esc_attr((string) $row['tracking']); ?>"><?php echo esc_attr($this->human_tracking_status((string) $row['tracking'])); ?></span></td>
                                <td><?php echo esc_attr((string) $row['card_blocks']); ?></td>
                                <td><?php echo esc_attr((string) $row['checked_at']); ?></td>
                                <td><?php echo esc_attr((string) $row['short_log']); ?></td>
                                <td>
                                    <div class="mtb-affiliate-audit-actions">
                                        <?php $this->render_audit_action_form((int) $row['id'], 'check', 'Prüfen'); ?>
                                        <?php $this->render_audit_action_form((int) $row['id'], 'straighten', 'Geradeziehen'); ?>
                                        <a class="button button-link" href="<?php echo esc_attr((string) $row['edit_link']); ?>">Öffnen</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_telegram_tab(): void {
        $settings = $this->settings->get_all();
        $optionName = $this->settings->option_name();
        $webhookUrl = function_exists('rest_url') ? rest_url('mtb-affiliate-cards/v1/telegram') : '';
        $ajaxUrl = function_exists('admin_url') ? admin_url('admin-ajax.php') : '';
        $nonce = function_exists('wp_create_nonce') ? wp_create_nonce('mtb_webhook_status_check') : '';
        ?>
        <form method="post" action="options.php">
            <?php if (function_exists('settings_fields')) : ?>
                <?php settings_fields($this->settings->settings_group()); ?>
            <?php endif; ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="mtb-bot-token">Bot-Token</label></th>
                    <td><input id="mtb-bot-token" type="password" class="regular-text code" name="<?php echo esc_attr($optionName); ?>[telegram_bot_token]" value="<?php echo esc_attr($settings['telegram_bot_token']); ?>" autocomplete="new-password"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mtb-chat-id">Chat-ID <small>(optional)</small></label></th>
                    <td>
                        <input id="mtb-chat-id" type="text" class="regular-text code" name="<?php echo esc_attr($optionName); ?>[telegram_chat_id]" value="<?php echo esc_attr($settings['telegram_chat_id']); ?>">
                        <p class="description">Nur Nachrichten von dieser Chat-ID verarbeiten. Leer = alle erlauben.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mtb-webhook-secret">Webhook-Secret</label></th>
                    <td>
                        <input id="mtb-webhook-secret" type="text" class="regular-text code" name="<?php echo esc_attr($optionName); ?>[telegram_webhook_secret]" value="<?php echo esc_attr($settings['telegram_webhook_secret']); ?>" readonly>
                        <p class="description">Wird automatisch generiert. Bei setWebhook als <code>secret_token</code> Parameter angeben.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Webhook-URL</th>
                    <td>
                        <input type="text" class="regular-text code" value="<?php echo esc_attr($webhookUrl); ?>" readonly>
                        <p class="description">Diese URL bei Telegram <code>setWebhook</code> registrieren.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Webhook-Status</th>
                    <td>
                        <span id="mtb-webhook-status-badge" style="display:inline-block;padding:3px 10px;border-radius:3px;background:#999;color:#fff;font-size:13px;">Unbekannt</span>
                        <button type="button" id="mtb-check-webhook-btn" class="button" style="margin-left:8px;">Status pruefen</button>
                        <span id="mtb-webhook-status-detail" style="margin-left:8px;color:#666;"></span>
                    </td>
                </tr>
            </table>
            <?php if (function_exists('submit_button')) : ?>
                <?php submit_button('Einstellungen speichern'); ?>
            <?php endif; ?>
        </form>
        <script>
        (function() {
            var btn = document.getElementById('mtb-check-webhook-btn');
            var badge = document.getElementById('mtb-webhook-status-badge');
            var detail = document.getElementById('mtb-webhook-status-detail');
            if (!btn) return;
            btn.addEventListener('click', function() {
                btn.disabled = true;
                badge.textContent = 'Pruefe...';
                badge.style.background = '#999';
                detail.textContent = '';
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo esc_js($ajaxUrl); ?>');
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    btn.disabled = false;
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.active) {
                            badge.textContent = 'Aktiv';
                            badge.style.background = '#46b450';
                            detail.textContent = 'Pending: ' + (data.pending || 0);
                        } else {
                            badge.textContent = 'Inaktiv';
                            badge.style.background = '#dc3232';
                            detail.textContent = data.error || 'Kein Webhook registriert.';
                        }
                    } catch(e) {
                        badge.textContent = 'Fehler';
                        badge.style.background = '#dc3232';
                    }
                };
                xhr.onerror = function() {
                    btn.disabled = false;
                    badge.textContent = 'Fehler';
                    badge.style.background = '#dc3232';
                };
                xhr.send('action=mtb_check_webhook_status&nonce=<?php echo esc_js($nonce); ?>');
            });
        })();
        </script>
        <?php
    }

    public function handle_audit_admin_post(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        $postId = (int) ($_POST['post_id'] ?? 0);
        $task = trim((string) ($_POST['mtb_audit_task'] ?? ''));
        if ($postId <= 0 || ! in_array($task, ['check', 'straighten'], true)) {
            $this->redirect_to_audit_tab([
                'mtb-audit-result' => 'invalid',
            ]);
            return;
        }

        if (function_exists('check_admin_referer')) {
            check_admin_referer('mtb_affiliate_audit_' . $postId . '_' . $task, 'mtb_affiliate_audit_nonce');
        }

        $state = $this->run_affiliate_audit($postId, $task === 'straighten');

        $this->redirect_to_audit_tab([
            'mtb-audit-post' => $postId,
            'mtb-audit-result' => $task,
            'mtb-audit-status' => (string) ($state['status'] ?? ''),
        ]);
    }

    public function handle_save_post(int $postId, $post = null, bool $update = false): void {
        if (! function_exists('wp_update_post') || ! function_exists('remove_action') || ! function_exists('add_action')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (function_exists('wp_is_post_revision') && wp_is_post_revision($postId)) {
            return;
        }

        if (function_exists('wp_is_post_autosave') && wp_is_post_autosave($postId)) {
            return;
        }

        if (! is_object($post) && function_exists('get_post')) {
            $post = get_post($postId);
        }

        if (! is_object($post) || ! isset($post->post_content)) {
            return;
        }

        $postContent = (string) $post->post_content;
        if (stripos($postContent, 'amazon:') === false) {
            return;
        }

        // --- Token pre-pass: resolve shorthand tokens to ASINs ---
        $prepass     = new MTB_Affiliate_Token_Prepass($this->productLibrary);
        $postContent = $prepass->resolve($postContent);

        // If pre-pass consumed all tokens and no amazon: markers remain, bail early.
        if (stripos($postContent, 'amazon:') === false) {
            // Pre-pass resolved tokens but no ASINs were produced (e.g. no products in library).
            // Still update the post to remove the dead tokens.
            if ($postContent !== (string) $post->post_content) {
                remove_action('save_post', [$this, 'handle_save_post'], 20);
                wp_update_post(['ID' => $postId, 'post_content' => $postContent]);
                add_action('save_post', [$this, 'handle_save_post'], 20, 3);
            }
            return;
        }

        $settings = $this->settings->get_all();
        $processor = new MTB_Affiliate_Post_Processor(
            null,
            [
                'badgeMode' => $settings['badge_mode'],
                'ctaLabel' => $settings['cta_label'],
                'autoShortenTitles' => $settings['auto_shorten_titles'],
            ],
            function (array $asins) use ($settings, $post): array {
                return $this->resolve_items_for_save($asins, $settings, $post);
            }
        );

        $processed = $processor->process($postContent);
        if ($processed['asins'] === [] || $processed['content'] === $postContent) {
            return;
        }

        remove_action('save_post', [$this, 'handle_save_post'], 20);

        wp_update_post([
            'ID' => $postId,
            'post_content' => $processed['content'],
        ]);

        add_action('save_post', [$this, 'handle_save_post'], 20, 3);
    }

    /**
     * Sync affiliate card block data back to the product library on every save.
     * Runs at priority 30 (after handle_save_post at 20) so processed content is used.
     */
    public function handle_save_post_sync_library(int $postId, $post = null): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (function_exists('wp_is_post_revision') && wp_is_post_revision($postId)) {
            return;
        }
        if (! is_object($post) && function_exists('get_post')) {
            $post = get_post($postId);
        }
        if (! is_object($post) || ! isset($post->post_content)) {
            return;
        }
        $content = (string) $post->post_content;

        // Sync block data to product library
        if (stripos($content, 'meintechblog/affiliate-cards') !== false) {
            $this->sync_blocks_to_library($content);
        }

        // Update inline affiliate link tracking IDs to match post date
        if (stripos($content, 'amazon.de/dp/') !== false) {
            $updated = $this->update_inline_affiliate_tags($content, (string) ($post->post_date ?? ''));
            if ($updated !== $content) {
                remove_action('save_post', [$this, 'handle_save_post_sync_library'], 30);
                wp_update_post(['ID' => $postId, 'post_content' => $updated]);
                add_action('save_post', [$this, 'handle_save_post_sync_library'], 30, 2);
            }
        }
    }

    /**
     * Update tracking IDs in inline affiliate links to match the post date.
     * Replaces meintechblog-YYMMDD-21 tags with the correct date-derived tag.
     */
    private function update_inline_affiliate_tags(string $content, string $postDate): string {
        $correctTag = $this->amazonClient->derive_partner_tag($postDate);

        // Match amazon.de/dp/ links with a meintechblog-*-21 tag parameter
        return preg_replace_callback(
            '/(https?:\/\/(?:www\.)?amazon\.de\/dp\/[A-Z0-9]{10}\?tag=)(meintechblog-\d{6}-21)/',
            function ($matches) use ($correctTag) {
                return $matches[1] . $correctTag;
            },
            $content
        ) ?? $content;
    }

    /**
     * Extract affiliate card block data and update the product library.
     * When authors set a title, benefit, or image in the editor, those
     * values flow back into the library for future reuse.
     */
    private function sync_blocks_to_library(string $content): void {
        if (! function_exists('parse_blocks')) {
            return;
        }

        $blocks = parse_blocks($content);
        foreach ($blocks as $block) {
            if (($block['blockName'] ?? '') !== 'meintechblog/affiliate-cards') {
                continue;
            }

            $attrs = $block['attrs'] ?? [];
            $items = $attrs['items'] ?? [];
            $item  = $items[0] ?? [];
            $asin  = strtoupper(trim((string) ($item['asin'] ?? '')));

            if ($asin === '' || ! preg_match('/^[A-Z0-9]{10}$/', $asin)) {
                continue;
            }

            $updates = [];

            // Title: prefer titleOverride > item title > amazonTitle (top-level attr)
            $title = (string) ($item['titleOverride'] ?? '');
            if ($title === '') { $title = (string) ($item['title'] ?? ''); }
            if ($title === '' || $title === $asin) { $title = (string) ($attrs['amazonTitle'] ?? ''); }
            if ($title !== '' && $title !== $asin) {
                $updates['title'] = $title;
            }

            // Benefit from item
            if (! empty($item['benefit'])) {
                $updates['benefit'] = (string) $item['benefit'];
            }

            // Image: prefer item image_url > first from top-level images array
            $imageUrl = (string) ($item['image_url'] ?? '');
            if ($imageUrl === '' && ! empty($attrs['images']) && is_array($attrs['images'])) {
                $imageUrl = (string) ($attrs['images'][0] ?? '');
            }
            if ($imageUrl !== '') {
                $updates['image_url'] = $imageUrl;
            }

            // Detail URL
            $detailUrl = (string) ($item['detail_url'] ?? '');
            if ($detailUrl === '') { $detailUrl = (string) ($attrs['detailUrl'] ?? ''); }
            if ($detailUrl !== '') {
                $updates['detail_url'] = $detailUrl;
            }

            if ($updates !== []) {
                $this->productLibrary->update_by_asin($asin, $updates);
            }
        }
    }

    private function run_affiliate_audit(int $postId, bool $straighten): array {
        if (! function_exists('get_post')) {
            return $this->auditService->default_state();
        }

        $post = get_post($postId);
        if (! is_object($post) || ! isset($post->post_content)) {
            return $this->auditService->default_state();
        }

        $content = (string) $post->post_content;
        $settings = $this->settings->get_all();

        if ($straighten) {
            $processor = new MTB_Affiliate_Post_Processor(
                null,
                [
                    'badgeMode' => $settings['badge_mode'],
                    'ctaLabel' => $settings['cta_label'],
                    'autoShortenTitles' => $settings['auto_shorten_titles'],
                ],
                function (array $asins) use ($settings, $post): array {
                    return $this->resolve_items_for_save($asins, $settings, $post);
                }
            );

            $processed = $processor->process($content);
            $processedContent = (string) ($processed['content'] ?? $content);
            $normalizedContent = $this->normalize_existing_affiliate_tracking($processedContent, $post, $settings);
            $safeToPersist = strpos($normalizedContent, 'amazon:') === false;

            if ($normalizedContent !== $content && $safeToPersist && function_exists('wp_update_post')) {
                wp_update_post([
                    'ID' => $postId,
                    'post_content' => $normalizedContent,
                ]);
                $content = $normalizedContent;
            }
        }

        $state = $this->auditService->scan_post_content($content);
        $hasUnresolvedMarkers = strpos($content, 'amazon:') !== false;
        $trackingIssue = ($state['tracking'] ?? '') === 'abweichend';
        $legacyCandidate = ! $hasUnresolvedMarkers
            && (int) ($state['counts']['affiliate_finds'] ?? 0) > 0
            && (int) ($state['counts']['card_blocks'] ?? 0) === 0;

        if ($straighten) {
            $state['status'] = ($hasUnresolvedMarkers || $trackingIssue) ? 'manuell_pruefen' : 'geradegezogen';
            $state['timestamps']['straightened_at'] = gmdate('c');
        } else {
            if ($trackingIssue) {
                $state['status'] = 'manuell_pruefen';
            } elseif ($legacyCandidate) {
                $state['status'] = 'legacy';
            } else {
                $state['status'] = 'geprueft';
            }
        }

        $state['timestamps']['checked_at'] = gmdate('c');
        $state['short_log'] = $this->auditService->build_short_log($state);

        if (function_exists('update_post_meta')) {
            update_post_meta($postId, $this->auditService->meta_key(), $state);
        }

        return $state;
    }

    private function resolve_items_for_save(array $asins, array $settings, object $post): array {
        if ($asins === [] || $settings['client_id'] === '' || $settings['client_secret'] === '') {
            return [];
        }

        try {
            $existingPartnerTag = $this->extract_existing_partner_tag((string) ($post->post_content ?? ''));
            $derivedPartnerTag = $this->amazonClient->derive_partner_tag((string) ($post->post_date ?? ''));
            $resolvedItems = $this->fetch_items_for_partner_tag($asins, $settings, $derivedPartnerTag);

            if ($resolvedItems === [] && $existingPartnerTag !== null && $existingPartnerTag !== '' && $existingPartnerTag !== $derivedPartnerTag) {
                $resolvedItems = $this->fetch_items_for_partner_tag($asins, $settings, $existingPartnerTag);
            }

            if ($resolvedItems === []) {
                return [];
            }

            $byAsin = [];
            foreach ($resolvedItems as $item) {
                $asin = trim((string) ($item['asin'] ?? ''));
                if ($asin !== '') {
                    $byAsin[$asin] = $item;
                }
            }

            $orderedResolvedItems = [];
            foreach ($asins as $asin) {
                if (isset($byAsin[$asin])) {
                    $orderedResolvedItems[] = $byAsin[$asin];
                }
            }

            return $orderedResolvedItems;
        } catch (Throwable $exception) {
            return [];
        }
    }

    private function normalize_existing_affiliate_tracking(string $content, object $post, array $settings): string {
        $derivedTag = trim($this->amazonClient->derive_partner_tag((string) ($post->post_date ?? '')));
        if ($content === '' || $derivedTag === '' || $derivedTag === 'meintechblog-000000-21') {
            return $content;
        }

        $existingTag = trim((string) ($this->extract_existing_partner_tag($content) ?? ''));
        $tagToUse = $this->resolve_normalized_partner_tag($content, $derivedTag, $existingTag, $settings);
        if ($tagToUse === '') {
            return $content;
        }

        $pattern = '/https?:\/\/(?:www\.)?amazon\.[^\s"\']+?\/dp\/[A-Z0-9]{10}[^\s"\']*/i';
        $normalized = preg_replace_callback($pattern, function (array $matches) use ($tagToUse): string {
            $url = (string) ($matches[0] ?? '');
            return $this->replace_partner_tag_in_url($url, $tagToUse);
        }, $content);

        return is_string($normalized) ? $normalized : $content;
    }

    private function resolve_normalized_partner_tag(string $content, string $derivedTag, string $existingTag, array $settings): string {
        $candidateAsins = $this->extract_affiliate_asins_from_content($content);
        if ($candidateAsins === [] || trim((string) ($settings['client_id'] ?? '')) === '' || trim((string) ($settings['client_secret'] ?? '')) === '') {
            return $existingTag !== '' ? $existingTag : '';
        }

        if ($derivedTag !== '' && $this->fetch_items_for_partner_tag($candidateAsins, $settings, $derivedTag) !== []) {
            return $derivedTag;
        }

        if ($existingTag !== '' && $this->fetch_items_for_partner_tag($candidateAsins, $settings, $existingTag) !== []) {
            return $existingTag;
        }

        return '';
    }

    private function replace_partner_tag_in_url(string $url, string $partnerTag): string {
        if ($url === '') {
            return $url;
        }

        $delimiter = '&';
        if (strpos($url, '\\u0026') !== false) {
            $delimiter = '\\u0026';
        } elseif (strpos($url, '&amp;') !== false) {
            $delimiter = '&amp;';
        }

        $normalizedUrl = str_replace(['\\u0026', '&amp;'], '&', $url);
        $parts = parse_url($normalizedUrl);
        $query = isset($parts['query']) && is_string($parts['query']) ? $parts['query'] : '';
        if ($query === '') {
            return $url;
        }

        parse_str($query, $params);
        if (! isset($params['tag'])) {
            return $url;
        }

        $params['tag'] = $partnerTag;
        $rebuiltQuery = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        if ($delimiter !== '&') {
            $rebuiltQuery = str_replace('&', $delimiter, $rebuiltQuery);
        }

        $rebuiltUrl = '';
        if (isset($parts['scheme']) && is_string($parts['scheme'])) {
            $rebuiltUrl .= $parts['scheme'] . '://';
        }
        if (isset($parts['user']) && is_string($parts['user']) && $parts['user'] !== '') {
            $rebuiltUrl .= $parts['user'];
            if (isset($parts['pass']) && is_string($parts['pass']) && $parts['pass'] !== '') {
                $rebuiltUrl .= ':' . $parts['pass'];
            }
            $rebuiltUrl .= '@';
        }
        if (isset($parts['host']) && is_string($parts['host'])) {
            $rebuiltUrl .= $parts['host'];
        }
        if (isset($parts['port']) && is_int($parts['port'])) {
            $rebuiltUrl .= ':' . $parts['port'];
        }
        $rebuiltUrl .= (string) ($parts['path'] ?? '');
        if ($rebuiltQuery !== '') {
            $rebuiltUrl .= '?' . $rebuiltQuery;
        }
        if (isset($parts['fragment']) && is_string($parts['fragment']) && $parts['fragment'] !== '') {
            $rebuiltUrl .= '#' . $parts['fragment'];
        }

        return $rebuiltUrl !== '' ? $rebuiltUrl : $url;
    }

    private function extract_existing_partner_tag(string $content): ?string {
        if ($content === '') {
            return null;
        }

        if (! preg_match_all('/https?:\/\/(?:www\.)?amazon\.[^\s"\']+/i', $content, $matches)) {
            return null;
        }

        foreach (($matches[0] ?? []) as $url) {
            $tag = $this->amazonClient->extract_partner_tag((string) $url);
            if ($tag !== null && $tag !== '') {
                return $tag;
            }
        }

        return null;
    }

    private function extract_affiliate_asins_from_content(string $content): array {
        if ($content === '') {
            return [];
        }

        if (! preg_match_all('/\/dp\/([A-Z0-9]{10})/i', $content, $matches)) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn(string $asin): string => strtoupper($asin),
            $matches[1] ?? []
        )));
    }

    private function fetch_items_for_partner_tag(array $asins, array $settings, string $partnerTag): array {
        try {
            return $this->amazonClient->get_items($asins, [
                'client_id' => $settings['client_id'],
                'client_secret' => $settings['client_secret'],
                'marketplace' => $settings['marketplace'],
                'partner_tag' => $partnerTag,
            ]);
        } catch (Throwable $exception) {
            return [];
        }
    }

    private function current_admin_tab(): string {
        $tab = trim((string) ($_GET['tab'] ?? 'settings'));
        return in_array($tab, ['settings', 'audit', 'telegram'], true) ? $tab : 'settings';
    }

    private function filter_audit_rows(array $rows, string $search, string $statusFilter): array {
        return array_values(array_filter($rows, static function (array $row) use ($search, $statusFilter): bool {
            if ($statusFilter !== '' && (string) ($row['status'] ?? '') !== $statusFilter) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystacks = [
                (string) ($row['title'] ?? ''),
                (string) ($row['short_log'] ?? ''),
                implode(' ', array_map('strval', (array) ($row['asins'] ?? []))),
            ];

            foreach ($haystacks as $haystack) {
                if ($haystack !== '' && stripos($haystack, $search) !== false) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function human_audit_status(string $status): string {
        $map = [
            'offen' => 'Offen',
            'legacy' => 'Legacy-Fall',
            'geprueft' => 'Geprüft',
            'geradegezogen' => 'Geradegezogen',
            'manuell_pruefen' => 'Manuell prüfen',
            'fehler' => 'Fehler',
        ];

        return $map[$status] ?? ucfirst($status);
    }

    private function human_tracking_status(string $tracking): string {
        $map = [
            'ok' => 'OK',
            'abweichend' => 'Abweichend',
            'keine_funde' => 'Nicht nötig',
            'unklar' => 'Unklar',
            '' => 'Unklar',
        ];

        return $map[$tracking] ?? $tracking;
    }

    private function render_audit_action_form(int $postId, string $task, string $label): void {
        $actionUrl = function_exists('admin_url') ? admin_url('admin-post.php') : 'admin-post.php';
        ?>
        <form method="post" action="<?php echo esc_attr($actionUrl); ?>">
            <input type="hidden" name="action" value="mtb_affiliate_audit">
            <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $postId); ?>">
            <input type="hidden" name="mtb_audit_task" value="<?php echo esc_attr($task); ?>">
            <?php if (function_exists('wp_nonce_field')) : ?>
                <?php wp_nonce_field('mtb_affiliate_audit_' . $postId . '_' . $task, 'mtb_affiliate_audit_nonce'); ?>
            <?php endif; ?>
            <button type="submit" class="button <?php echo $task === 'straighten' ? 'button-primary' : ''; ?>"><?php echo esc_attr($label); ?></button>
        </form>
        <?php
    }

    private function render_audit_notice(): void {
        $result = trim((string) ($_GET['mtb-audit-result'] ?? ''));
        $status = trim((string) ($_GET['mtb-audit-status'] ?? ''));
        $postId = (int) ($_GET['mtb-audit-post'] ?? 0);

        if ($result === '') {
            return;
        }

        $message = '';
        if ($result === 'check') {
            $message = sprintf('Beitrag %d wurde geprüft. Status: %s.', $postId, $this->human_audit_status($status));
        } elseif ($result === 'straighten') {
            $message = sprintf('Beitrag %d wurde geradegezogen. Status: %s.', $postId, $this->human_audit_status($status));
        } else {
            $message = 'Audit-Aktion konnte nicht verarbeitet werden.';
        }
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_attr($message); ?></p>
        </div>
        <?php
    }

    private function redirect_to_audit_tab(array $args): void {
        $baseUrl = function_exists('admin_url')
            ? admin_url('options-general.php?page=mtb-affiliate-cards&tab=audit')
            : 'options-general.php?page=mtb-affiliate-cards&tab=audit';

        $url = $baseUrl;
        if (function_exists('add_query_arg')) {
            $url = add_query_arg($args, $baseUrl);
        } elseif ($args !== []) {
            $separator = str_contains($baseUrl, '?') ? '&' : '?';
            $url = $baseUrl . $separator . http_build_query($args);
        }

        if (function_exists('wp_safe_redirect')) {
            wp_safe_redirect($url);
        }
    }
}
