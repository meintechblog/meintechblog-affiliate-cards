<?php

declare(strict_types=1);

final class MTB_Affiliate_Tracking_Registry {
    private const TABLE_SUFFIX = 'mtb_affiliate_tracking_ids';
    private const DB_VERSION = '1.0';
    private const DB_VERSION_OPTION = 'mtb_affiliate_tracking_registry_db_version';

    public static function create_table(): void {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  tracking_id varchar(60) NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY tracking_id (tracking_id)
) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    public function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public function exists(string $trackingId): bool {
        global $wpdb;

        $table = $this->table_name();
        $count = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE tracking_id = %s", $trackingId)
        );

        return $count > 0;
    }

    public function register(string $trackingId): bool {
        global $wpdb;

        if ($trackingId === '') {
            return false;
        }

        if ($this->exists($trackingId)) {
            return false;
        }

        $result = $wpdb->insert(
            $this->table_name(),
            [
                'tracking_id' => $trackingId,
                'created_at'  => current_time('mysql', true),
            ],
            ['%s', '%s']
        );

        return $result !== false;
    }

    public static function needs_upgrade(): bool {
        return get_option(self::DB_VERSION_OPTION, '0') !== self::DB_VERSION;
    }
}
