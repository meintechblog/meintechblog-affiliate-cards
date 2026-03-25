<?php

declare(strict_types=1);

final class MTB_Affiliate_Product_Library {
    private const TABLE_SUFFIX = 'mtb_affiliate_products';
    private const DB_VERSION = '1.0';
    private const DB_VERSION_OPTION = 'mtb_affiliate_products_db_version';

    public static function create_table(): void {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  asin varchar(10) NOT NULL,
  title varchar(500) NOT NULL DEFAULT '',
  detail_url text NOT NULL,
  image_url text NOT NULL,
  received_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY asin (asin),
  KEY received_at (received_at)
) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    public function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    /**
     * Insert a product into the library.
     *
     * @param array $product Accepts: asin (required), title, detail_url, image_url (all optional).
     * @return int|false Inserted row ID on success, false on failure.
     */
    public function insert(array $product): int|false {
        global $wpdb;

        $asin = trim((string) ($product['asin'] ?? ''));
        if ($asin === '') {
            return false;
        }

        $data = [
            'asin'        => $asin,
            'title'       => (string) ($product['title'] ?? ''),
            'detail_url'  => (string) ($product['detail_url'] ?? ''),
            'image_url'   => (string) ($product['image_url'] ?? ''),
            'received_at' => current_time('mysql', true),
        ];

        $result = $wpdb->insert(
            $this->table_name(),
            $data,
            ['%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get the most recently received products.
     *
     * @param int $limit Maximum number of products to return (default 20).
     * @return array Array of associative arrays.
     */
    public function get_recent(int $limit = 20): array {
        global $wpdb;

        $table = $this->table_name();

        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY received_at DESC LIMIT %d", $limit),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Get the Nth most recently received product.
     *
     * @param int $n 1 = most recent, 2 = second most recent, etc.
     * @return array|null Associative array or null if not found.
     */
    public function get_last(int $n = 1): ?array {
        global $wpdb;

        if ($n < 1) {
            $n = 1;
        }

        $table = $this->table_name();
        $offset = $n - 1;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY received_at DESC LIMIT %d OFFSET %d", 1, $offset),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public static function needs_upgrade(): bool {
        return get_option(self::DB_VERSION_OPTION, '0') !== self::DB_VERSION;
    }
}
