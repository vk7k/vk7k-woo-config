<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Config_PDF {

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'handle_production_sheet_request' ) );
    }

    public static function handle_production_sheet_request() {
        if ( ! isset( $_GET['action'] ) || 'wooconfig_production_sheet' !== $_GET['action'] ) {
            return;
        }

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( __( 'Permisos insuficientes para ver esta ficha de producción.', 'woo-config' ) );
        }

        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        check_admin_referer( 'wooconfig_pdf_' . $order_id );

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_die( __( 'Pedido no encontrado.', 'woo-config' ) );
        }

        include WOOCONFIG_PATH . 'templates/production-sheet.php';
        exit;
    }
}

WC_Config_PDF::init();
