<?php

declare(strict_types=1);

/**
 * WP_List_Table subclass for the Produkt-Bibliothek admin page.
 *
 * WP_List_Table is not autoloaded outside admin context — guarded below.
 */
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class MTB_Affiliate_Product_Library_List_Table extends WP_List_Table {

    private MTB_Affiliate_Product_Library $library;

    public function __construct( MTB_Affiliate_Product_Library $library ) {
        parent::__construct( [
            'singular' => 'produkt',
            'plural'   => 'produkte',
            'ajax'     => false,
        ] );
        $this->library = $library;
    }

    public function get_columns(): array {
        return [
            'cb'          => '<input type="checkbox">',
            'asin'        => __( 'ASIN', 'meintechblog-affiliate-cards' ),
            'title'       => __( 'Titel', 'meintechblog-affiliate-cards' ),
            'received_at' => __( 'Empfangen', 'meintechblog-affiliate-cards' ),
        ];
    }

    public function get_bulk_actions(): array {
        return [ 'delete' => __( 'Löschen', 'meintechblog-affiliate-cards' ) ];
    }

    protected function column_cb( $item ): string {
        return '<input type="checkbox" name="product_ids[]" value="' . (int) $item['id'] . '">';
    }

    protected function column_default( $item, $column_name ): string {
        return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
    }

    public function process_bulk_action(): void {
        if ( $this->current_action() !== 'delete' ) {
            return;
        }
        check_admin_referer( 'bulk-produkte' );
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $ids = array_map( 'intval', (array) ( $_POST['product_ids'] ?? [] ) );
        if ( $ids !== [] ) {
            $this->library->delete_by_ids( $ids );
        }
    }

    public function prepare_items(): void {
        $this->process_bulk_action();
        $this->_column_headers = [ $this->get_columns(), [], [] ];
        $this->items           = $this->library->get_recent( 200 );
    }
}
