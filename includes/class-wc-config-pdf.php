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

        // Support draft preview without saving to database permanently
        if ( isset( $_GET['draft_key'] ) ) {
            $draft_key  = sanitize_text_field( $_GET['draft_key'] );
            $draft_data = get_transient( 'wooconfig_draft_' . $draft_key );
            if ( ! $draft_data ) {
                wp_die( __( 'El borrador de previsualización ha caducado o no existe.', 'woo-config' ) );
            }
            $is_draft = true;
            $order_id = isset( $draft_data['order_id'] ) ? absint( $draft_data['order_id'] ) : 0;
            $order    = $order_id ? wc_get_order( $order_id ) : false;
            include WOOCONFIG_PATH . 'templates/production-sheet.php';
            exit;
        }

        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        check_admin_referer( 'wooconfig_pdf_' . $order_id );

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_die( __( 'Pedido no encontrado.', 'woo-config' ) );
        }

        $is_draft = false;
        include WOOCONFIG_PATH . 'templates/production-sheet.php';
        exit;
    }
}

WC_Config_PDF::init();
