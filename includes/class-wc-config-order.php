<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Config_Order {

    public static function init() {
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_order_line_item_meta' ), 10, 4 );
        add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'process_order_files_and_groups' ), 10, 3 );
        add_action( 'add_meta_boxes', array( __CLASS__, 'register_order_metabox' ) );
        add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'render_email_order_team_details' ), 10, 4 );

        // Balance payment redirect endpoint
        add_action( 'template_redirect', array( __CLASS__, 'handle_pay_balance_redirect' ) );

        // Track when child balance order is paid
        add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'check_balance_order_payment' ), 10, 4 );

        // Customer "Mi Cuenta" / Order Details view (Option 2)
        add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'render_customer_balance_section' ), 15, 1 );

        // Admin manual balance status toggle action
        add_action( 'admin_init', array( __CLASS__, 'handle_admin_manual_balance_actions' ) );

        // Storage cleanup: delete order files when order is permanently deleted
        add_action( 'woocommerce_delete_order', array( __CLASS__, 'handle_order_deletion' ), 10, 1 );
        add_action( 'before_delete_post', array( __CLASS__, 'handle_post_deletion' ), 10, 1 );

        // Preferred Contact & 24h Sketch Notice Hooks
        add_action( 'woocommerce_after_checkout_billing_form', array( __CLASS__, 'render_preferred_contact_checkout_field' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'save_preferred_contact_order_meta' ) );
        add_action( 'woocommerce_thankyou', array( __CLASS__, 'render_thankyou_sketch_notice' ), 20, 1 );
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_admin_order_preferred_contact' ) );

        // Admin AJAX endpoints for backend order editing & workshop preview
        add_action( 'wp_ajax_wooconfig_admin_get_configurable_products', array( __CLASS__, 'ajax_get_configurable_products' ) );
        add_action( 'wp_ajax_wooconfig_admin_save_group', array( __CLASS__, 'ajax_save_group' ) );
        add_action( 'wp_ajax_wooconfig_admin_delete_item', array( __CLASS__, 'ajax_delete_item' ) );
        add_action( 'wp_ajax_wooconfig_admin_update_item', array( __CLASS__, 'ajax_update_item' ) );
        add_action( 'wp_ajax_wooconfig_admin_add_item', array( __CLASS__, 'ajax_add_item' ) );
        add_action( 'wp_ajax_wooconfig_admin_preview_sheet', array( __CLASS__, 'ajax_preview_sheet' ) );
        add_action( 'wp_ajax_wooconfig_admin_convert_standard_items', array( __CLASS__, 'ajax_convert_standard_items' ) );
    }

    public static function handle_order_deletion( $order_id ) {
        if ( 'yes' !== get_option( 'wooconfig_auto_delete_order_files', 'yes' ) ) {
            return;
        }
        WC_Config_Uploader::delete_order_files( $order_id );
    }

    public static function handle_post_deletion( $post_id ) {
        if ( 'yes' !== get_option( 'wooconfig_auto_delete_order_files', 'yes' ) ) {
            return;
        }
        if ( 'shop_order' === get_post_type( $post_id ) ) {
            WC_Config_Uploader::delete_order_files( $post_id );
        }
    }

    public static function save_order_line_item_meta( $item, $cart_item_key, $values, $order ) {
        $keys = array(
            '_wooconfig_flow'                => '_wooconfig_flow',
            '_wooconfig_upload_token'        => '_wooconfig_upload_token',
            '_wooconfig_group_id'            => '_wooconfig_group_id',
            '_wooconfig_group_title'         => '_wooconfig_group_title',
            '_wooconfig_item_index'          => '_wooconfig_item_index',
            '_wooconfig_total_items'         => '_wooconfig_total_items',
            '_wooconfig_is_deposit'          => '_wooconfig_is_deposit',
            '_wooconfig_payment_mode'        => '_wooconfig_payment_mode',
            '_wooconfig_deposit_percent'     => '_wooconfig_deposit_percent',
            '_wooconfig_full_unit_price'     => '_wooconfig_full_unit_price',
            '_wooconfig_deposit_unit_price'  => '_wooconfig_deposit_unit_price',
            '_wooconfig_balance_unit_due'    => '_wooconfig_balance_unit_due',
            '_wooconfig_dorsal'              => __( 'Dorsal (#)', 'woo-config' ),
            '_wooconfig_player_name'         => __( 'Nombre / Alias', 'woo-config' ),
            '_wooconfig_size'                => __( 'Talla', 'woo-config' ),
            '_wooconfig_color'               => __( 'Color', 'woo-config' ),
            '_wooconfig_adicionales_list'    => __( 'Adicionales', 'woo-config' ),
            '_wooconfig_adicionales_summary' => __( 'Adicionales incluidos', 'woo-config' ),
            '_wooconfig_design_desc'         => '_wooconfig_design_desc',
            '_wooconfig_files'               => '_wooconfig_files',
        );

        foreach ( $keys as $cart_key => $meta_label ) {
            if ( isset( $values[$cart_key] ) && '' !== $values[$cart_key] ) {
                if ( is_array( $values[$cart_key] ) ) {
                    $item->add_meta_data( $meta_label, $values[$cart_key] );
                } else {
                    $item->add_meta_data( $meta_label, sanitize_text_field( $values[$cart_key] ) );
                }
            }
        }
    }

    public static function process_order_files_and_groups( $order_id, $posted_data, $order ) {
        $existing_groups     = $order->get_meta( '_wooconfig_order_groups' );
        $groups              = is_array( $existing_groups ) ? $existing_groups : array();
        $has_deposit         = false;
        $order_full_total    = 0;
        $order_deposit_total = 0;
        $order_balance_total = 0;

        foreach ( $order->get_items() as $item_id => $item ) {
            $group_id = $item->get_meta( '_wooconfig_group_id' );
            if ( empty( $group_id ) ) continue;

            $is_dep = $item->get_meta( '_wooconfig_is_deposit' );
            if ( 'yes' === $is_dep ) {
                $has_deposit = true;
                $full_unit   = (float) $item->get_meta( '_wooconfig_full_unit_price' );
                $dep_unit    = (float) $item->get_meta( '_wooconfig_deposit_unit_price' );
                $bal_unit    = (float) $item->get_meta( '_wooconfig_balance_unit_due' );
                $qty         = $item->get_quantity();

                $order_full_total    += ( $full_unit * $qty );
                $order_deposit_total += ( $dep_unit * $qty );
                $order_balance_total += ( $bal_unit * $qty );
            }

            if ( ! isset( $groups[$group_id] ) ) {
                $raw_files    = $item->get_meta( '_wooconfig_files' );
                $design_desc  = $item->get_meta( '_wooconfig_design_desc' );
                $flow         = $item->get_meta( '_wooconfig_flow' );
                $upload_token = $item->get_meta( '_wooconfig_upload_token' );

                $permanent_files = WC_Config_Uploader::move_temp_files_to_order( $raw_files, $order_id, $upload_token );
                $expected_files  = is_array( $raw_files ) ? count( $raw_files ) : 0;

                if ( $expected_files > count( $permanent_files ) ) {
                    $order->add_order_note( sprintf(
                        __( 'Atención: no se pudieron adjuntar %d de %d archivo(s) del grupo %s. Revisa la ficha de producción antes de confeccionar.', 'woo-config' ),
                        $expected_files - count( $permanent_files ),
                        $expected_files,
                        $item->get_meta( '_wooconfig_group_title' )
                    ) );
                }

                $groups[$group_id] = array(
                    'group_id'    => $group_id,
                    'flow'        => $flow ? $flow : 'wizard',
                    'group_title' => $item->get_meta( '_wooconfig_group_title' ),
                    'design_desc' => $design_desc,
                    'files'       => $permanent_files,
                );
            }
        }

        if ( ! empty( $groups ) ) {
            $order->update_meta_data( '_wooconfig_order_groups', $groups );
        }

        if ( $has_deposit ) {
            $order->update_meta_data( '_wooconfig_has_deposit', 'yes' );
            $order->update_meta_data( '_wooconfig_order_full_total', $order_full_total );
            $order->update_meta_data( '_wooconfig_order_deposit_total', $order_deposit_total );
            $order->update_meta_data( '_wooconfig_order_balance_total', $order_balance_total );
            $order->update_meta_data( '_wooconfig_balance_status', 'pending' );
        }

        $order->save();
    }

    public static function get_balance_payment_url( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }
        if ( ! $order ) return '';

        return add_query_arg( array(
            'wooconfig_action' => 'pay_balance',
            'order_id'         => $order->get_id(),
            'order_key'        => $order->get_order_key(),
        ), home_url( '/' ) );
    }

    public static function handle_pay_balance_redirect() {
        if ( ! isset( $_GET['wooconfig_action'] ) || 'pay_balance' !== $_GET['wooconfig_action'] ) {
            return;
        }

        $order_id  = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        $order_key = isset( $_GET['order_key'] ) ? sanitize_text_field( $_GET['order_key'] ) : '';

        $parent_order = wc_get_order( $order_id );
        if ( ! $parent_order || $parent_order->get_order_key() !== $order_key ) {
            wp_die( esc_html__( 'Pedido no válido o enlace expirado.', 'woo-config' ) );
        }

        $balance_status = $parent_order->get_meta( '_wooconfig_balance_status' );
        if ( 'paid' === $balance_status ) {
            wc_add_notice( __( 'El saldo de este pedido ya se encuentra 100% pagado. ¡Gracias!', 'woo-config' ), 'success' );
            wp_safe_redirect( $parent_order->get_view_order_url() );
            exit;
        }

        $balance_amount = (float) $parent_order->get_meta( '_wooconfig_order_balance_total' );
        if ( $balance_amount <= 0 ) {
            wp_safe_redirect( $parent_order->get_view_order_url() );
            exit;
        }

        // Check if existing child balance order is reusable
        $balance_order_id = absint( $parent_order->get_meta( '_wooconfig_balance_order_id' ) );
        $balance_order    = $balance_order_id ? wc_get_order( $balance_order_id ) : false;

        if ( ! $balance_order || in_array( $balance_order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) ) {
            $balance_order = wc_create_order( array(
                'customer_id' => $parent_order->get_customer_id(),
                'parent'      => $parent_order->get_id(),
                'status'      => 'pending',
            ) );

            $item_name = sprintf( __( 'Saldo Confección (50%%) - Pedido #%d', 'woo-config' ), $parent_order->get_id() );
            $item      = new WC_Order_Item_Fee();
            $item->set_name( $item_name );
            $item->set_amount( $balance_amount );
            $item->set_total( $balance_amount );
            $item->set_tax_status( 'none' );
            $balance_order->add_item( $item );

            $balance_order->set_address( $parent_order->get_address( 'billing' ), 'billing' );
            $balance_order->set_address( $parent_order->get_address( 'shipping' ), 'shipping' );
            $balance_order->update_meta_data( '_wooconfig_parent_order_id', $parent_order->get_id() );
            $balance_order->update_meta_data( '_wooconfig_is_balance_order', 'yes' );
            $balance_order->calculate_totals();
            $balance_order->save();

            $parent_order->update_meta_data( '_wooconfig_balance_order_id', $balance_order->get_id() );
            $parent_order->save();
        }

        wp_safe_redirect( $balance_order->get_checkout_payment_url( true ) );
        exit;
    }

    public static function check_balance_order_payment( $order_id, $old_status, $new_status, $order ) {
        if ( ! $order ) {
            $order = wc_get_order( $order_id );
        }
        if ( ! $order ) return;

        $parent_order_id = absint( $order->get_meta( '_wooconfig_parent_order_id' ) );
        if ( $parent_order_id > 0 && in_array( $new_status, array( 'processing', 'completed' ), true ) ) {
            $parent_order = wc_get_order( $parent_order_id );
            if ( $parent_order ) {
                $parent_order->update_meta_data( '_wooconfig_balance_status', 'paid' );
                $parent_order->update_meta_data( '_wooconfig_balance_paid_date', current_time( 'mysql' ) );
                $parent_order->add_order_note( sprintf( __( '✅ Saldo final pagado exitosamente mediante pedido de saldo #%d (%s).', 'woo-config' ), $order_id, wc_price( $order->get_total() ) ) );
                $parent_order->save();
            }
        }
    }

    public static function handle_admin_manual_balance_actions() {
        if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( isset( $_GET['wooconfig_manual_balance_action'], $_GET['order_id'], $_GET['_wpnonce'] ) ) {
            $order_id = absint( $_GET['order_id'] );
            if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'wooconfig_balance_toggle_' . $order_id ) ) {
                return;
            }

            $order = wc_get_order( $order_id );
            if ( ! $order ) return;

            $action = sanitize_text_field( $_GET['wooconfig_manual_balance_action'] );
            if ( 'mark_paid' === $action ) {
                $order->update_meta_data( '_wooconfig_balance_status', 'paid' );
                $order->update_meta_data( '_wooconfig_balance_paid_date', current_time( 'mysql' ) );
                $order->add_order_note( __( '✅ Saldo marcado manualmente como PAGADO por el administrador.', 'woo-config' ) );
                $order->save();
            } elseif ( 'mark_pending' === $action ) {
                $order->update_meta_data( '_wooconfig_balance_status', 'pending' );
                $order->delete_meta_data( '_wooconfig_balance_paid_date' );
                $order->add_order_note( __( '⏳ Saldo marcado nuevamente como PENDIENTE por el administrador.', 'woo-config' ) );
                $order->save();
            }

            wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'post.php?post=' . $order_id . '&action=edit' ) );
            exit;
        }
    }

    public static function register_order_metabox() {
        $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id( 'shop-order' )
            : 'shop_order';

        add_meta_box(
            'wooconfig_order_teams',
            __( '🎽 Ficha de Equipos, Anticipos y WhatsApp (WooConfig)', 'woo-config' ),
            array( __CLASS__, 'render_order_metabox' ),
            $screen,
            'normal',
            'high'
        );
    }

    public static function render_order_metabox( $post_or_order_object ) {
        $order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
        if ( ! $order ) return;

        $groups              = $order->get_meta( '_wooconfig_order_groups' );
        $items               = $order->get_items();
        $has_wooconfig_items = false;
        $standard_configurable = array();

        foreach ( $items as $item_id => $item ) {
            if ( $item->get_meta( '_wooconfig_group_id' ) ) {
                $has_wooconfig_items = true;
            } else {
                $product_id = $item->get_product_id();
                if ( $product_id ) {
                    $wc_type = get_post_meta( $product_id, '_wooconfig_type', true );
                    if ( ! empty( $wc_type ) && 'disabled' !== $wc_type ) {
                        $standard_configurable[] = array(
                            'item_id'      => $item_id,
                            'product_id'   => $product_id,
                            'product_name' => $item->get_name(),
                            'quantity'     => $item->get_quantity(),
                        );
                    }
                }
            }
        }

        $has_deposit         = 'yes' === $order->get_meta( '_wooconfig_has_deposit' );
        $order_full_total    = (float) $order->get_meta( '_wooconfig_order_full_total' );
        $order_deposit_total = (float) $order->get_meta( '_wooconfig_order_deposit_total' );
        $order_balance_total = (float) $order->get_meta( '_wooconfig_order_balance_total' );
        $balance_status      = $order->get_meta( '_wooconfig_balance_status' );
        if ( empty( $balance_status ) ) {
            $balance_status = 'pending';
        }

        $pdf_url     = admin_url( 'admin.php?action=wooconfig_production_sheet&order_id=' . $order->get_id() . '&_wpnonce=' . wp_create_nonce( 'wooconfig_pdf_' . $order->get_id() ) );
        $payment_url = self::get_balance_payment_url( $order );

        // Sanitize phone for WhatsApp
        $raw_phone     = $order->get_billing_phone();
        $clean_phone   = preg_replace( '/[^0-9]/', '', $raw_phone );
        if ( ! empty( $clean_phone ) ) {
            if ( 9 === strlen( $clean_phone ) && '9' === substr( $clean_phone, 0, 1 ) ) {
                $clean_phone = '56' . $clean_phone;
            } elseif ( 8 === strlen( $clean_phone ) ) {
                $clean_phone = '569' . $clean_phone;
            }
        }

        $cust_name = $order->get_billing_first_name() ? $order->get_billing_first_name() : __( 'Cliente', 'woo-config' );
        
        $wa_msg = sprintf(
            __( "¡Hola %s! 👋 Te escribimos del taller de CAFU.\nTu pedido #%d ya se encuentra listo / en fase final de confección. 🎽✨\n\n*Resumen de cuenta:*\n- Total confección: %s\n- Abono recibido: %s\n- Saldo pendiente: %s\n\nPuedes pagar el saldo restante en línea con tarjetas (Webpay / Débito / Crédito) o transferencia en el siguiente enlace seguro:\n👉 %s\n\n¡Una vez confirmado tu pago procedemos con el despacho inmediato! 🚚📦", 'woo-config' ),
            $cust_name,
            $order->get_id(),
            html_entity_decode( strip_tags( wc_price( $order_full_total ) ) ),
            html_entity_decode( strip_tags( wc_price( $order_deposit_total ) ) ),
            html_entity_decode( strip_tags( wc_price( $order_balance_total ) ) ),
            $payment_url
        );

        $wa_url = 'https://api.whatsapp.com/send?phone=' . $clean_phone . '&text=' . rawurlencode( $wa_msg );
        $toggle_paid_nonce = wp_create_nonce( 'wooconfig_balance_toggle_' . $order->get_id() );
        $preferred_contact = $order->get_meta( '_wooconfig_preferred_contact' );
        ?>

        <div class="wooconfig-admin-order-box">

            <?php if ( ! empty( $standard_configurable ) ) : ?>
                <div class="wooconfig-desglosar-banner">
                    <div class="wooconfig-desglosar-icon">⚡</div>
                    <div class="wooconfig-desglosar-body">
                        <strong style="font-size: 14px; color: #1e293b;"><?php esc_html_e( 'Artículos configurables detectados sin personalizar', 'woo-config' ); ?></strong>
                        <p style="margin: 6px 0 0; font-size: 12.5px; color: #64748b;">
                            <?php esc_html_e( 'Estos productos fueron añadidos con el botón nativo de WooCommerce. Puedes desglosarlos en uniformes individuales con dorsal, nombre y talla.', 'woo-config' ); ?>
                        </p>
                        <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 8px;">
                            <?php foreach ( $standard_configurable as $sci ) : ?>
                                <div class="wooconfig-desglosar-item-row">
                                    <span>
                                        <strong><?php echo esc_html( $sci['product_name'] ); ?></strong>
                                        <span class="wooconfig-desglosar-qty">× <?php echo esc_html( $sci['quantity'] ); ?></span>
                                    </span>
                                    <button type="button" class="button button-primary wooconfig-btn-desglosar"
                                        data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
                                        data-item-id="<?php echo esc_attr( $sci['item_id'] ); ?>"
                                        data-product-id="<?php echo esc_attr( $sci['product_id'] ); ?>"
                                        data-product-name="<?php echo esc_attr( $sci['product_name'] ); ?>"
                                        data-quantity="<?php echo esc_attr( $sci['quantity'] ); ?>">
                                        ⚡ <?php printf( esc_html__( 'Configurar Tallas y Dorsales (%d prendas)', 'woo-config' ), $sci['quantity'] ); ?>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( ! $has_wooconfig_items && empty( $standard_configurable ) ) : ?>
                <div class="wooconfig-empty-state">
                    <div style="font-size: 48px; margin-bottom: 12px; opacity: 0.6;">🎽</div>
                    <p style="margin: 0 0 4px; font-size: 15px; font-weight: 700; color: #1e293b;">
                        <?php esc_html_e( 'Este pedido no contiene productos personalizados todavía.', 'woo-config' ); ?>
                    </p>
                    <p style="margin: 0 0 18px; font-size: 13px; color: #64748b;">
                        <?php esc_html_e( 'Puedes crear un equipo completo desde cero con dorsales, nombres y tallas para cada prenda.', 'woo-config' ); ?>
                    </p>
                    <button type="button" class="button button-primary button-hero" id="wooconfig-btn-create-from-scratch" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
                        ➕ <?php esc_html_e( 'Crear y Configurar Equipo desde Cero', 'woo-config' ); ?>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $preferred_contact ) ) : ?>
                <div style="background: #e0f2fe; border: 1.5px solid #7dd3fc; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; color: #0369a1; display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-weight: 700; font-size: 13.5px; display: flex; align-items: center; gap: 6px;">
                        <span>💬</span> <?php esc_html_e( 'Medio de contacto preferido indicado por el cliente para el envío del boceto:', 'woo-config' ); ?>
                    </span>
                    <strong style="background: #0284c7; color: #fff; padding: 4px 14px; border-radius: 20px; font-size: 13px; font-weight: 800;">
                        <?php echo esc_html( $preferred_contact ); ?>
                    </strong>
                </div>
            <?php endif; ?>

            <?php if ( $has_deposit ) : ?>
                <!-- Panel de Control de Saldo y WhatsApp (Opción 1) -->
                <div style="background: linear-gradient(135deg, #1f2937, #111827); border-radius: 8px; padding: 18px 20px; color: #fff; margin-bottom: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.12); padding-bottom: 12px; margin-bottom: 14px;">
                        <h3 style="margin: 0; color: #94ec00; font-size: 16px; font-weight: 800;">
                            💳 ESTADO DE PAGO DE ANTICIPO Y SALDO
                        </h3>
                        <div>
                            <?php if ( 'paid' === $balance_status ) : ?>
                                <span style="background: #10b981; color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 800;">
                                    ✅ SALDO 100% PAGADO
                                </span>
                            <?php else : ?>
                                <span style="background: #f59e0b; color: #000; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 800;">
                                    ⏳ SALDO PENDIENTE DE COBRO
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 18px;">
                        <div style="background: rgba(255,255,255,0.06); padding: 12px; border-radius: 6px;">
                            <span style="font-size: 11px; color: #9ca3af; text-transform: uppercase; font-weight: 700; display: block;">Total Confección:</span>
                            <span style="font-size: 18px; font-weight: 900; color: #fff;"><?php echo wc_price( $order_full_total ); ?></span>
                        </div>
                        <div style="background: rgba(255,255,255,0.06); padding: 12px; border-radius: 6px;">
                            <span style="font-size: 11px; color: #9ca3af; text-transform: uppercase; font-weight: 700; display: block;">Abonado Hoy (50%):</span>
                            <span style="font-size: 18px; font-weight: 900; color: #94ec00;"><?php echo wc_price( $order_deposit_total ); ?></span>
                            <span style="font-size: 11px; color: #10b981; display: block;">(Pagado al inicio)</span>
                        </div>
                        <div style="background: rgba(255,255,255,0.06); padding: 12px; border-radius: 6px;">
                            <span style="font-size: 11px; color: #9ca3af; text-transform: uppercase; font-weight: 700; display: block;">Saldo Pendiente (50%):</span>
                            <span style="font-size: 18px; font-weight: 900; color: <?php echo ( 'paid' === $balance_status ? '#10b981' : '#f87171' ); ?>;">
                                <?php echo wc_price( $order_balance_total ); ?>
                            </span>
                            <span style="font-size: 11px; color: #d1d5db; display: block;">
                                <?php echo ( 'paid' === $balance_status ? 'Pagado' : 'A liquidar para envío' ); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Botones de Acción de Cobro (Opción 1: WhatsApp 1 Clic) -->
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; background: rgba(0,0,0,0.25); padding: 12px 15px; border-radius: 6px;">
                        <?php if ( ! empty( $clean_phone ) ) : ?>
                            <a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" class="button button-primary" style="background: #25D366; border-color: #25D366; color: #fff; font-weight: 800; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; height: 34px;">
                                💬 Cobrar Saldo por WhatsApp (1 Clic)
                            </a>
                        <?php else : ?>
                            <span style="color: #f87171; font-size: 12px;">⚠ El cliente no ingresó número telefónico.</span>
                        <?php endif; ?>

                        <input type="text" readonly value="<?php echo esc_url( $payment_url ); ?>" style="flex: 1; min-width: 240px; background: #fff; color: #111; font-size: 12px; padding: 6px 10px;" id="wooconfig-admin-payurl-input" />
                        <button type="button" class="button button-secondary" onclick="navigator.clipboard.writeText(document.getElementById('wooconfig-admin-payurl-input').value); alert('Enlace de pago copiado al portapapeles.');">
                            🔗 Copiar Enlace
                        </button>

                        <?php if ( 'paid' === $balance_status ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?wooconfig_manual_balance_action=mark_pending&order_id=' . $order->get_id() . '&_wpnonce=' . $toggle_paid_nonce ) ); ?>" class="button button-link-delete" style="font-size: 11px; color: #bbb;" onclick="return confirm('¿Marcar saldo nuevamente como pendiente?');">
                                Revertir a Pendiente
                            </a>
                        <?php else : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?wooconfig_manual_balance_action=mark_paid&order_id=' . $order->get_id() . '&_wpnonce=' . $toggle_paid_nonce ) ); ?>" class="button button-secondary" style="font-size: 11px;" onclick="return confirm('¿Confirmas que recibiste el saldo por fuera (efectivo/transferencia manual)?');">
                                ✔ Marcar Saldo como Pagado Manual
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="wooconfig-admin-toolbar" style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border: 1px solid #c3c4c7; border-radius: 4px; padding: 12px 16px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <strong style="font-size: 15px; color: #1e293b;">🎽 Personalización de Prendas y Equipos</strong>
                    <span class="badge" style="background: #2271b1; color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 700;">
                        <?php echo count( $items ); ?> prendas
                    </span>
                </div>
                <div class="wooconfig-admin-toolbar-actions" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <button type="button" class="button button-primary" id="wooconfig-btn-open-add-group" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
                        ➕ Añadir Prendas / Equipo al Pedido
                    </button>
                    <a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" class="button button-secondary">
                        📄 Ficha Oficial de Taller (PDF)
                    </a>
                </div>
            </div>

            <?php
            $grouped_items = array();
            foreach ( $items as $item_id => $item ) {
                $gid = $item->get_meta( '_wooconfig_group_id' );
                if ( empty( $gid ) ) {
                    $gid = 'sin_grupo';
                }
                $grouped_items[$gid][] = $item;
            }

            foreach ( $grouped_items as $gid => $group_item_list ) :
                $group_meta  = isset( $groups[$gid] ) ? $groups[$gid] : array();
                $first_item  = $group_item_list[0];
                $flow        = isset( $group_meta['flow'] ) ? $group_meta['flow'] : $first_item->get_meta( '_wooconfig_flow' );
                $group_title = $first_item->get_meta( '_wooconfig_group_title' );
                if ( empty( $group_title ) ) {
                    $group_title = isset( $group_meta['group_title'] ) ? $group_meta['group_title'] : __( 'Equipo / Lote de Confección', 'woo-config' );
                }
                $design_desc = isset( $group_meta['design_desc'] ) ? $group_meta['design_desc'] : $first_item->get_meta( '_wooconfig_design_desc' );
                $files       = isset( $group_meta['files'] ) ? $group_meta['files'] : $first_item->get_meta( '_wooconfig_files' );
                ?>
                <div class="wooconfig-group-card" data-group-id="<?php echo esc_attr( $gid ); ?>" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 18px; margin-bottom: 25px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #2271b1; padding-bottom: 10px; margin-bottom: 15px; flex-wrap: wrap; gap: 8px;">
                        <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #1d2327;">
                            👕 <?php echo esc_html( $group_title ); ?>
                        </h4>
                        <div style="display: flex; gap: 6px; align-items: center;">
                            <span style="background: #f0f0f1; padding: 3px 8px; border-radius: 3px; font-weight: 600; font-size: 12px; color: #50575e;">
                                <?php echo count( $group_item_list ); ?> prendas
                            </span>
                            <button type="button" class="button button-small wooconfig-btn-add-item-row" data-group-id="<?php echo esc_attr( $gid ); ?>" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
                                ➕ Añadir Prenda
                            </button>
                            <button type="button" class="button button-small wooconfig-btn-preview-group" data-group-id="<?php echo esc_attr( $gid ); ?>" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" data-group-title="<?php echo esc_attr( $group_title ); ?>">
                                👁 Previsualizar Ficha
                            </button>
                        </div>
                    </div>

                    <table class="widefat fixed striped wooconfig-items-table" style="margin-bottom: 15px;">
                        <thead>
                            <tr>
                                <th style="width: 45px; text-align: center;">#</th>
                                <th>Prenda</th>
                                <th style="width: 90px;">Dorsal</th>
                                <th style="width: 170px;">Nombre / Alias</th>
                                <th style="width: 100px;">Talla</th>
                                <th>Adicionales</th>
                                <th style="width: 130px; text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $group_item_list as $i => $item ) : 
                                $item_id        = $item->get_id();
                                $dorsal_val     = $item->get_meta( __( 'Dorsal (#)', 'woo-config' ) );
                                $name_val       = $item->get_meta( __( 'Nombre / Alias', 'woo-config' ) );
                                $size_val       = $item->get_meta( __( 'Talla', 'woo-config' ) );
                                $ads            = $item->get_meta( __( 'Adicionales', 'woo-config' ) );
                                $ads_text       = is_array( $ads ) ? implode( ', ', $ads ) : ( $ads ? $ads : '-' );
                                $clean_ads_text = html_entity_decode( wp_strip_all_tags( $ads_text ) );
                            ?>
                                <tr class="wooconfig-item-row" data-item-id="<?php echo esc_attr( $item_id ); ?>" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
                                    <td style="text-align: center; vertical-align: middle;">
                                        <strong>#<?php echo esc_html( $item->get_meta( '_wooconfig_item_index' ) ? $item->get_meta( '_wooconfig_item_index' ) : ($i + 1) ); ?></strong>
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <strong><?php echo esc_html( $item->get_name() ); ?></strong>
                                        <div style="font-size: 11px; color: #646970;">ID: <?php echo esc_html( $item_id ); ?> | <?php echo wc_price( $item->get_total() ); ?></div>
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <span class="wooconfig-view-mode"><strong style="color: #0073aa; font-size: 14px;"><?php echo esc_html( $dorsal_val ? $dorsal_val : '-' ); ?></strong></span>
                                        <input type="text" class="wooconfig-edit-dorsal wooconfig-edit-mode" value="<?php echo esc_attr( $dorsal_val ); ?>" style="display: none; width: 75px;" placeholder="Ej: 10" />
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <span class="wooconfig-view-mode"><strong style="text-transform: uppercase;"><?php echo esc_html( $name_val ? $name_val : '-' ); ?></strong></span>
                                        <input type="text" class="wooconfig-edit-name wooconfig-edit-mode" value="<?php echo esc_attr( $name_val ); ?>" style="display: none; width: 100%; text-transform: uppercase;" placeholder="Ej: PÉREZ" />
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <span class="wooconfig-view-mode"><span style="display: inline-block; background: #e0e0e0; padding: 2px 8px; border-radius: 3px; font-weight: 700;"><?php echo esc_html( $size_val ? $size_val : '-' ); ?></span></span>
                                        <input type="text" class="wooconfig-edit-size wooconfig-edit-mode" value="<?php echo esc_attr( $size_val ); ?>" style="display: none; width: 80px;" placeholder="Ej: M" />
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <span style="font-size: 12px; color: #222; font-weight: 600;"><?php echo esc_html( $clean_ads_text ); ?></span>
                                    </td>
                                    <td style="text-align: center; vertical-align: middle;">
                                        <div class="wooconfig-row-actions">
                                            <button type="button" class="button button-small wooconfig-btn-edit-row wooconfig-view-mode" title="Editar talla y dorsal">
                                                ✏ Editar
                                            </button>
                                            <button type="button" class="button button-small button-primary wooconfig-btn-save-row wooconfig-edit-mode" style="display: none;">
                                                💾 Guardar
                                            </button>
                                            <button type="button" class="button button-small wooconfig-btn-cancel-row wooconfig-edit-mode" style="display: none;">
                                                ✕
                                            </button>
                                            <button type="button" class="button button-small button-link-delete wooconfig-btn-delete-row" title="Eliminar prenda">
                                                🗑
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px 15px; margin-bottom: 15px;">
                        <strong style="display: block; margin-bottom: 4px; color: #1e293b; font-size: 13px;">📝 Instrucciones de Diseño y Observaciones:</strong>
                        <p style="margin: 0; white-space: pre-wrap; font-size: 13px; line-height: 1.5; color: #334155;">
                            <?php echo esc_html( $design_desc ? $design_desc : '(Sin descripción de diseño ingresada)' ); ?>
                        </p>
                    </div>

                    <?php if ( ! empty( $files ) && is_array( $files ) ) : ?>
                        <div>
                            <strong style="display: block; margin-bottom: 8px; color: #1e293b; font-size: 13px;">📁 Archivos y Logos Adjuntos:</strong>
                            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                                <?php foreach ( $files as $f ) :
                                    $file_url  = isset( $f['file_url'] ) ? $f['file_url'] : '';
                                    $orig_name = isset( $f['original_name'] ) ? $f['original_name'] : 'Archivo adjunto';
                                    $is_img    = isset( $f['is_image'] ) ? $f['is_image'] : false;
                                    ?>
                                    <div style="border: 1px solid #ccd0d4; border-radius: 4px; padding: 8px 10px; background: #fff; display: flex; align-items: center; gap: 10px; max-width: 320px;">
                                        <?php if ( $is_img && ! empty( $file_url ) ) : ?>
                                            <a href="<?php echo esc_url( $file_url ); ?>" target="_blank">
                                                <img src="<?php echo esc_url( $file_url ); ?>" style="width: 44px; height: 44px; object-fit: cover; border-radius: 3px; border: 1px solid #c3c4c7;">
                                            </a>
                                        <?php else : ?>
                                            <div style="width: 44px; height: 44px; background: #f0f0f1; border-radius: 3px; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                                                📄
                                            </div>
                                        <?php endif; ?>
                                        <div style="overflow: hidden;">
                                            <div style="font-weight: 600; font-size: 12px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?php echo esc_attr( $orig_name ); ?>">
                                                <?php echo esc_html( $orig_name ); ?>
                                            </div>
                                            <a href="<?php echo esc_url( $file_url ); ?>" target="_blank" download class="button button-small" style="margin-top: 3px;">
                                                ⬇ Descargar
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <!-- Modal para Añadir Prendas / Equipo al Pedido (Enhanced con Tabs) -->
            <div id="wooconfig-admin-modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 100000; align-items: center; justify-content: center;">
                <div class="wooconfig-modal-container" style="background: #fff; border-radius: 6px; width: 95%; max-width: 960px; max-height: 92vh; display: flex; flex-direction: column; box-shadow: 0 10px 30px rgba(0,0,0,0.3); overflow: hidden;">
                    <div class="wooconfig-modal-header" style="padding: 14px 20px; border-bottom: 1px solid #dcdcde; display: flex; justify-content: space-between; align-items: center; background: #f6f7f7;">
                        <h2 style="margin: 0; font-size: 16px; font-weight: 700; color: #1d2327;" id="wooconfig-modal-title">
                            ➕ <?php printf( esc_html__( 'Añadir Prendas / Uniformes al Pedido #%d', 'woo-config' ), $order->get_id() ); ?>
                        </h2>
                        <button type="button" class="button-link" id="wooconfig-modal-close-btn" style="font-size: 20px; line-height: 1; color: #646970; text-decoration: none; cursor: pointer;">
                            ✕
                        </button>
                    </div>

                    <div class="wooconfig-modal-body" style="padding: 20px; overflow-y: auto; flex: 1;">
                        <input type="hidden" id="wooconfig-modal-convert-item-id" value="0" />

                        <!-- Step 1: Producto base y Título del Grupo -->
                        <div class="wooconfig-modal-step-section">
                            <h3 class="wooconfig-modal-step-heading">1. <?php esc_html_e( 'Selecciona la Prenda / Producto', 'woo-config' ); ?></h3>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Producto Configurable:', 'woo-config' ); ?></label>
                                    <select id="wooconfig-modal-product-select" style="width: 100%;">
                                        <option value="">-- <?php esc_html_e( 'Cargando productos...', 'woo-config' ); ?> --</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Nombre del Equipo / Grupo:', 'woo-config' ); ?></label>
                                    <input type="text" id="wooconfig-modal-group-title" style="width: 100%;" placeholder="<?php esc_attr_e( 'Ej: Camisetas Titulares / Titular Adulto', 'woo-config' ); ?>" />
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Diseño y Sponsors -->
                        <div class="wooconfig-modal-step-section">
                            <h3 class="wooconfig-modal-step-heading">2. <?php esc_html_e( 'Diseño, Sponsors y Archivos', 'woo-config' ); ?></h3>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 12px;">
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Instrucciones de Diseño:', 'woo-config' ); ?></label>
                                    <textarea id="wooconfig-modal-design-notes" rows="3" style="width: 100%;" placeholder="<?php esc_attr_e( 'Colores, detalles, franjas, cuello, vivos...', 'woo-config' ); ?>"></textarea>
                                </div>
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Observaciones de Sponsors / Publicidad:', 'woo-config' ); ?></label>
                                    <textarea id="wooconfig-modal-sponsor-notes" rows="3" style="width: 100%;" placeholder="<?php esc_attr_e( 'Ubicación de logos (pecho, espalda, mangas)...', 'woo-config' ); ?>"></textarea>
                                </div>
                            </div>

                            <div>
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Archivos Adjuntos (Bocetos, logos en alta resolución, escudos, planillas):', 'woo-config' ); ?></label>
                                <button type="button" class="button button-secondary" id="wooconfig-modal-upload-files-btn">
                                    📁 <?php esc_html_e( 'Seleccionar o Subir Archivos (Biblioteca de Medios)', 'woo-config' ); ?>
                                </button>
                                <div id="wooconfig-modal-files-list" style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;"></div>
                            </div>
                        </div>

                        <!-- Step 3: Método de Ingreso con Tabs -->
                        <div class="wooconfig-modal-step-section">
                            <h3 class="wooconfig-modal-step-heading">3. <?php esc_html_e( '¿Cómo deseas ingresar los uniformes?', 'woo-config' ); ?></h3>

                            <!-- Tab Navigation -->
                            <div class="wooconfig-input-tabs">
                                <button type="button" class="wooconfig-input-tab is-active" data-tab="paste">
                                    📋 <?php esc_html_e( 'Pegar desde Excel / WhatsApp', 'woo-config' ); ?>
                                </button>
                                <button type="button" class="wooconfig-input-tab" data-tab="manual">
                                    ✍ <?php esc_html_e( 'Tabla Manual', 'woo-config' ); ?>
                                </button>
                                <button type="button" class="wooconfig-input-tab" data-tab="quick">
                                    📁 <?php esc_html_e( 'Modo Rápido (Planilla / Foto)', 'woo-config' ); ?>
                                </button>
                            </div>

                            <!-- Tab A: Pegar desde Excel / WhatsApp -->
                            <div class="wooconfig-input-panel is-active" data-panel="paste">
                                <p style="margin: 0 0 8px; font-size: 13px; color: #475569;">
                                    <?php esc_html_e( 'Copia las filas desde Excel, Google Sheets o WhatsApp y pégalas aquí. Formato:', 'woo-config' ); ?>
                                    <code style="font-size: 12px;">Dorsal | Nombre | Talla | Adicionales</code>
                                </p>
                                <textarea id="wooconfig-paste-area" rows="7" style="width: 100%; font-family: monospace; font-size: 13px; border: 2px dashed #94a3b8; border-radius: 4px; padding: 12px; background: #f8fafc; resize: vertical;" placeholder="10&#9;JUAN PÉREZ&#9;L&#10;7&#9;CARLOS SOTO&#9;M&#9;manga larga&#10;1&#9;DIEGO BRAVO&#9;XL&#10;&#10;También acepta: comas, punto y coma, guiones, o múltiples espacios como separador."></textarea>
                                <div style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                                    <button type="button" class="button button-primary" id="wooconfig-paste-parse-btn">
                                        📋 <?php esc_html_e( 'Cargar a la Tabla', 'woo-config' ); ?>
                                    </button>
                                    <span id="wooconfig-paste-result" style="font-size: 12px; color: #059669; font-weight: 600; display: none;"></span>
                                </div>
                                <p class="description" style="margin-top: 8px;">
                                    <?php esc_html_e( 'Detecta automáticamente tallas (S, M, L, XL, 4-16), dorsales numéricos, nombres y adicionales (manga larga, piqué).', 'woo-config' ); ?>
                                </p>
                            </div>

                            <!-- Tab B: Tabla Manual -->
                            <div class="wooconfig-input-panel" data-panel="manual">
                                <div class="wooconfig-batch-controls">
                                    <label style="font-weight: 600; font-size: 13px;"><?php esc_html_e( 'Cantidad de prendas:', 'woo-config' ); ?></label>
                                    <input type="number" id="wooconfig-batch-qty" min="1" max="100" value="10" style="width: 70px;">
                                    <button type="button" class="button button-primary" id="wooconfig-batch-generate-btn">
                                        ⚡ <?php esc_html_e( 'Generar Filas', 'woo-config' ); ?>
                                    </button>
                                    <span class="description" style="margin-left: 6px;">
                                        <?php esc_html_e( 'Genera filas con talla M y dorsales correlativos.', 'woo-config' ); ?>
                                    </span>
                                </div>
                                <div style="display: flex; gap: 6px; margin-top: 10px;">
                                    <button type="button" class="button button-secondary button-small" id="wooconfig-modal-add-row-btn">
                                        ➕ <?php esc_html_e( 'Añadir 1 Fila', 'woo-config' ); ?>
                                    </button>
                                    <button type="button" class="button button-secondary button-small" id="wooconfig-modal-add-5rows-btn">
                                        ➕ <?php esc_html_e( 'Añadir 5 Filas', 'woo-config' ); ?>
                                    </button>
                                    <button type="button" class="button button-link-delete button-small" id="wooconfig-modal-clear-table-btn" style="margin-left: auto;">
                                        🗑 <?php esc_html_e( 'Limpiar Tabla', 'woo-config' ); ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Tab C: Modo Rápido (Planilla / Foto) -->
                            <div class="wooconfig-input-panel" data-panel="quick">
                                <div class="wooconfig-quick-mode-box">
                                    <div class="wooconfig-quick-mode-icon">📋</div>
                                    <div>
                                        <strong style="font-size: 14px; color: #1e293b;"><?php esc_html_e( 'Modo Rápido: El cliente envió una planilla o foto', 'woo-config' ); ?></strong>
                                        <p style="margin: 4px 0 12px; font-size: 12.5px; color: #64748b;">
                                            <?php esc_html_e( 'Ingresa la cantidad de prendas y sube el archivo adjunto en la sección de Archivos (paso 2). Se generarán las prendas con talla predeterminada; los datos exactos quedan en el archivo.', 'woo-config' ); ?>
                                        </p>
                                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                            <div>
                                                <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Cantidad de prendas:', 'woo-config' ); ?></label>
                                                <input type="number" id="wooconfig-quick-qty" min="1" max="100" value="10" style="width: 70px;">
                                            </div>
                                            <div>
                                                <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Talla predeterminada:', 'woo-config' ); ?></label>
                                                <select id="wooconfig-quick-default-size" style="width: 80px;">
                                                    <option value="S">S</option>
                                                    <option value="M" selected>M</option>
                                                    <option value="L">L</option>
                                                    <option value="XL">XL</option>
                                                </select>
                                            </div>
                                            <div style="margin-top: 18px;">
                                                <button type="button" class="button button-primary" id="wooconfig-quick-generate-btn">
                                                    ⚡ <?php esc_html_e( 'Preparar Prendas', 'woo-config' ); ?>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="description" style="margin-top: 10px;">
                                            ⚠ <?php esc_html_e( 'Recuerda adjuntar la planilla o foto en la sección de Archivos (paso 2) antes de guardar.', 'woo-config' ); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabla Compartida de Uniformes (visible en Tab A y B) -->
                            <div id="wooconfig-modal-table-wrapper">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin: 12px 0 6px;">
                                    <span style="font-size: 13px; font-weight: 600; color: #1e293b;" id="wooconfig-table-count-label"></span>
                                </div>
                                <table class="widefat fixed striped" id="wooconfig-modal-players-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px; text-align: center;">#</th>
                                            <th style="width: 80px;"><?php esc_html_e( 'Dorsal', 'woo-config' ); ?></th>
                                            <th><?php esc_html_e( 'Nombre / Alias', 'woo-config' ); ?></th>
                                            <th style="width: 110px;"><?php esc_html_e( 'Talla', 'woo-config' ); ?></th>
                                            <th><?php esc_html_e( 'Adicionales', 'woo-config' ); ?></th>
                                            <th style="width: 45px; text-align: center;">✕</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Rows added via JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Step 4: Anticipo y Saldo -->
                        <div class="wooconfig-modal-step-section">
                            <h3 class="wooconfig-modal-step-heading">4. <?php esc_html_e( 'Anticipo y Saldo', 'woo-config' ); ?></h3>
                            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                                <label class="wooconfig-payment-option">
                                    <input type="radio" name="wooconfig_modal_payment_mode" value="full" checked>
                                    <span class="wooconfig-payment-option-label">
                                        <strong>💯 <?php esc_html_e( '100% Pago completo', 'woo-config' ); ?></strong>
                                        <small><?php esc_html_e( 'El cliente paga el total al confirmar', 'woo-config' ); ?></small>
                                    </span>
                                </label>
                                <label class="wooconfig-payment-option">
                                    <input type="radio" name="wooconfig_modal_payment_mode" value="deposit">
                                    <span class="wooconfig-payment-option-label">
                                        <strong>💳 <?php esc_html_e( '50% Anticipo', 'woo-config' ); ?></strong>
                                        <small><?php esc_html_e( 'Saldo restante al retirar / pre-despacho', 'woo-config' ); ?></small>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="wooconfig-modal-footer" style="padding: 12px 20px; border-top: 1px solid #dcdcde; background: #f6f7f7; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <button type="button" class="button button-secondary" id="wooconfig-modal-preview-btn">
                                👁 <?php esc_html_e( 'Previsualizar Ficha de Taller (Borrador)', 'woo-config' ); ?>
                            </button>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="button button-secondary" id="wooconfig-modal-cancel-btn">
                                <?php esc_html_e( 'Cancelar', 'woo-config' ); ?>
                            </button>
                            <button type="button" class="button button-primary" id="wooconfig-modal-save-btn">
                                <?php esc_html_e( 'Guardar y Añadir al Pedido', 'woo-config' ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_customer_balance_section( $order ) {
        if ( ! $order ) return;

        $has_deposit = 'yes' === $order->get_meta( '_wooconfig_has_deposit' );
        if ( ! $has_deposit ) return;

        $order_full_total    = (float) $order->get_meta( '_wooconfig_order_full_total' );
        $order_deposit_total = (float) $order->get_meta( '_wooconfig_order_deposit_total' );
        $order_balance_total = (float) $order->get_meta( '_wooconfig_order_balance_total' );
        $balance_status      = $order->get_meta( '_wooconfig_balance_status' );
        if ( empty( $balance_status ) ) {
            $balance_status = 'pending';
        }

        $is_initial_paid = $order->is_paid();
        ?>
        <div class="wooconfig-customer-deposit-card" style="margin: 30px 0; background: #fff; border: 2px solid <?php echo ( 'paid' === $balance_status ? '#10b981' : '#94ec00' ); ?>; border-radius: 10px; padding: 22px; box-shadow: 0 4px 15px rgba(0,0,0,0.06);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #111;">
                    🎽 <?php esc_html_e( 'Resumen de Pago y Saldo de Confección', 'woo-config' ); ?>
                </h3>
                <?php if ( 'paid' === $balance_status ) : ?>
                    <span style="background: #10b981; color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 800;">
                        ✓ <?php esc_html_e( 'Saldo Pagado', 'woo-config' ); ?>
                    </span>
                <?php else : ?>
                    <span style="background: #f59e0b; color: #000; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 800;">
                        ⏳ <?php esc_html_e( 'Saldo Pendiente', 'woo-config' ); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 18px;">
                <div style="background: #f9fafb; padding: 10px 14px; border-radius: 6px; border: 1px solid #e5e7eb;">
                    <span style="font-size: 11px; color: #6b7280; font-weight: 700; display: block;"><?php esc_html_e( 'Total Confección:', 'woo-config' ); ?></span>
                    <strong style="font-size: 17px; color: #111;"><?php echo wc_price( $order_full_total ); ?></strong>
                </div>
                <div style="background: #f9fafb; padding: 10px 14px; border-radius: 6px; border: 1px solid #e5e7eb;">
                    <span style="font-size: 11px; color: #6b7280; font-weight: 700; display: block;"><?php echo $is_initial_paid ? esc_html__( 'Abono Pagado (50%):', 'woo-config' ) : esc_html__( 'Abono Inicial (50%):', 'woo-config' ); ?></span>
                    <strong style="font-size: 17px; color: <?php echo $is_initial_paid ? '#059669' : '#d97706'; ?>;"><?php echo wc_price( $order_deposit_total ); ?></strong>
                </div>
                <div style="background: #f9fafb; padding: 10px 14px; border-radius: 6px; border: 1px solid #e5e7eb;">
                    <span style="font-size: 11px; color: #6b7280; font-weight: 700; display: block;"><?php esc_html_e( 'Saldo Restante (50%):', 'woo-config' ); ?></span>
                    <strong style="font-size: 17px; color: <?php echo ( 'paid' === $balance_status ? '#059669' : '#dc2626' ); ?>;"><?php echo wc_price( $order_balance_total ); ?></strong>
                </div>
            </div>

            <?php if ( 'paid' !== $balance_status ) : ?>
                <?php if ( $is_initial_paid ) : ?>
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 14px;">
                        <p style="margin: 0; font-size: 13px; color: #166534; line-height: 1.5;">
                            ℹ <strong><?php esc_html_e( 'Tu boceto está en proceso de elaboración.', 'woo-config' ); ?></strong> <?php esc_html_e( 'Recibirás la propuesta de diseño dentro de un plazo máximo de 24 horas. Una vez que apruebes el boceto, tu pedido iniciará confección.', 'woo-config' ); ?>
                        </p>
                    </div>
                <?php else : ?>
                    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 14px;">
                        <p style="margin: 0; font-size: 13px; color: #92400e; line-height: 1.5;">
                            ℹ <strong><?php esc_html_e( 'El pago inicial da inicio a la elaboración de tu boceto (24 horas).', 'woo-config' ); ?></strong> <?php esc_html_e( 'Una vez confirmado tu abono (50%), prepararemos tu diseño. Tras tu aprobación del boceto, se iniciará la confección de tu pedido.', 'woo-config' ); ?>
                        </p>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <p style="margin: 0; font-size: 13px; color: #059669; font-weight: 600;">
                    ✓ <?php esc_html_e( '¡Gracias! El saldo total ha sido cubierto. Una vez aprobado tu boceto y finalizada la confección, tu pedido será despachado de inmediato.', 'woo-config' ); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_email_order_team_details( $order, $sent_to_admin, $plain_text, $email ) {
        $sketch_notice_enabled = get_option( 'wooconfig_sketch_notice_enabled', 'yes' );
        $notice_text           = get_option( 'wooconfig_sketch_notice_text', __( 'Luego de realizar el pago inicial, recibirás el boceto de tu diseño dentro de un plazo máximo de 24 horas a través del medio de contacto que señalaste.', 'woo-config' ) );
        $preferred_contact     = $order ? $order->get_meta( '_wooconfig_preferred_contact' ) : '';

        if ( 'yes' === $sketch_notice_enabled && ! empty( $notice_text ) ) {
            if ( $plain_text ) {
                echo "\n========================================\n";
                echo "INFORMACION SOBRE TU BOCETO (24 HORAS)\n";
                echo "========================================\n";
                echo $notice_text . "\n";
                if ( ! empty( $preferred_contact ) ) {
                    echo "Medio de contacto preferido seleccionado: " . $preferred_contact . "\n";
                }
                echo "\n";
            } else {
                ?>
                <div style="margin-top: 20px; margin-bottom: 20px; padding: 15px; border: 1.5px solid #22c55e; border-radius: 6px; background-color: #f0fdf4; color: #15803d;">
                    <h3 style="margin: 0 0 8px 0; color: #166534; font-size: 15px;">🎨 Información sobre tu Boceto (24 Horas)</h3>
                    <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #15803d;">
                        <?php echo esc_html( $notice_text ); ?>
                    </p>
                    <?php if ( ! empty( $preferred_contact ) ) : ?>
                        <p style="margin: 10px 0 0 0; font-size: 12.5px; font-weight: bold; color: #166534; border-top: 1px dashed #bbf7d0; padding-top: 8px;">
                            💬 Medio de contacto preferido: <?php echo esc_html( $preferred_contact ); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php
            }
        }

        $groups = $order ? $order->get_meta( '_wooconfig_order_groups' ) : array();
        if ( empty( $groups ) || ! is_array( $groups ) ) return;

        if ( $plain_text ) {
            echo "\n========================================\n";
            echo "DETALLES DE PERSONALIZACIÓN DEL EQUIPO\n";
            echo "========================================\n\n";
            foreach ( $groups as $g ) {
                echo "Diseño: " . $g['design_desc'] . "\n";
                if ( ! empty( $g['files'] ) ) {
                    echo "Archivos:\n";
                    foreach ( $g['files'] as $f ) {
                        echo "- " . $f['original_name'] . ": " . $f['file_url'] . "\n";
                    }
                }
                echo "\n";
            }
        } else {
            ?>
            <div style="margin-top: 25px; padding: 15px; border: 1px solid #e5e5e5; border-radius: 6px; background-color: #fcfcfc;">
                <h3 style="margin-top: 0; color: #111; border-bottom: 2px solid #94ec00; padding-bottom: 8px;">
                    🎽 Personalización de Equipo (WooConfig)
                </h3>
                <?php foreach ( $groups as $g ) : ?>
                    <div style="margin-bottom: 15px;">
                        <p style="margin: 0 0 8px;"><strong>Descripción del diseño:</strong><br><?php echo nl2br( esc_html( $g['design_desc'] ) ); ?></p>
                        <?php if ( ! empty( $g['files'] ) && is_array( $g['files'] ) ) : ?>
                            <p style="margin: 0 0 5px;"><strong>Enlaces a archivos de diseño adjuntos:</strong></p>
                            <ul style="margin: 0; padding-left: 20px;">
                                <?php foreach ( $g['files'] as $f ) : ?>
                                    <li>
                                        <a href="<?php echo esc_url( $f['file_url'] ); ?>" target="_blank" style="color: #0073aa; text-decoration: underline;">
                                            <?php echo esc_html( $f['original_name'] ); ?> (Descargar)
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
        }
    }

    public static function render_preferred_contact_checkout_field( $checkout ) {
        if ( 'yes' !== get_option( 'wooconfig_preferred_contact_enabled', 'yes' ) ) {
            return;
        }

        $label        = get_option( 'wooconfig_preferred_contact_label', __( 'Medio de contacto preferido para recepción de boceto', 'woo-config' ) );
        $options_raw  = get_option( 'wooconfig_preferred_contact_options', 'WhatsApp, Correo electrónico, Llamada telefónica' );
        $options_list = array_map( 'trim', explode( ',', $options_raw ) );

        $options = array();
        foreach ( $options_list as $opt ) {
            if ( ! empty( $opt ) ) {
                $options[$opt] = $opt;
            }
        }

        if ( empty( $options ) ) {
            $options = array(
                'WhatsApp'           => 'WhatsApp',
                'Correo electrónico' => 'Correo electrónico',
                'Llamada telefónica' => 'Llamada telefónica',
            );
        }

        echo '<div class="wooconfig-preferred-contact-wrapper" style="margin-top: 15px; margin-bottom: 15px; background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">';
        woocommerce_form_field( 'wooconfig_preferred_contact', array(
            'type'        => 'select',
            'class'       => array( 'wooconfig-preferred-contact-field', 'form-row-wide' ),
            'label'       => '💬 ' . esc_html( $label ),
            'required'    => true,
            'options'     => $options,
        ), $checkout->get_value( 'wooconfig_preferred_contact' ) );
        echo '</div>';
    }

    public static function save_preferred_contact_order_meta( $order_id ) {
        if ( isset( $_POST['wooconfig_preferred_contact'] ) && ! empty( $_POST['wooconfig_preferred_contact'] ) ) {
            $preferred_contact = sanitize_text_field( $_POST['wooconfig_preferred_contact'] );
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->update_meta_data( '_wooconfig_preferred_contact', $preferred_contact );
                $order->save();
            }
        }
    }

    public static function render_thankyou_sketch_notice( $order_id ) {
        if ( ! $order_id ) return;
        if ( 'yes' !== get_option( 'wooconfig_sketch_notice_enabled', 'yes' ) ) return;

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $notice_text       = get_option( 'wooconfig_sketch_notice_text', __( 'Luego de realizar el pago inicial, recibirás el boceto de tu diseño dentro de un plazo máximo de 24 horas a través del medio de contacto que señalaste.', 'woo-config' ) );
        $preferred_contact = $order->get_meta( '_wooconfig_preferred_contact' );

        ?>
        <div class="wooconfig-thankyou-sketch-notice" style="background: #f0fdf4; border: 2px solid #bbf7d0; border-radius: 10px; padding: 20px 22px; margin: 25px 0; color: #14532d; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
            <h3 style="margin: 0 0 10px 0; font-size: 17px; font-weight: 800; color: #166534; display: flex; align-items: center; gap: 8px;">
                <span>🎨</span> <?php esc_html_e( 'Información Importante sobre tu Boceto (24 Horas)', 'woo-config' ); ?>
            </h3>
            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #15803d;">
                <?php echo esc_html( $notice_text ); ?>
            </p>
            <?php if ( ! empty( $preferred_contact ) ) : ?>
                <div style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed #bbf7d0; font-size: 13.5px; color: #166534;">
                    <strong>📱 <?php esc_html_e( 'Medio de contacto seleccionado:', 'woo-config' ); ?></strong>
                    <span style="background: #dcfce7; padding: 3px 10px; border-radius: 4px; font-weight: 700; color: #14532d; margin-left: 6px;">
                        <?php echo esc_html( $preferred_contact ); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_admin_order_preferred_contact( $order ) {
        if ( ! $order ) return;
        $preferred_contact = $order->get_meta( '_wooconfig_preferred_contact' );
        if ( ! empty( $preferred_contact ) ) {
            echo '<p style="margin-top: 12px;"><strong>' . esc_html__( 'Medio de contacto preferido (Boceto):', 'woo-config' ) . '</strong><br><span style="display:inline-block; background:#0284c7; color:#fff; padding:4px 12px; border-radius:4px; font-weight:700; margin-top:4px;">💬 ' . esc_html( $preferred_contact ) . '</span></p>';
        }
    }

    /* ==========================================================================
       AJAX ENDPOINTS FOR BACKEND ORDER MANAGEMENT & WORKSHOP PREVIEW
       ========================================================================== */

    public static function ajax_get_configurable_products() {
        check_ajax_referer( 'wooconfig_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'woo-config' ) ) );
        }

        $args = array(
            'status' => 'publish',
            'limit'  => 100,
            'return' => 'ids',
        );
        $product_ids = wc_get_products( $args );
        $list = array();

        $default_sizes = array( '4', '6', '8', '10', '12', '14', '16', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL' );

        foreach ( $product_ids as $pid ) {
            $type = get_post_meta( $pid, '_wooconfig_type', true );
            if ( empty( $type ) || 'disabled' === $type ) {
                continue;
            }

            $product = wc_get_product( $pid );
            if ( ! $product ) continue;

            $sizes = array();
            $attrs = $product->get_attributes();
            foreach ( $attrs as $attr_name => $attr_obj ) {
                if ( false !== stripos( $attr_name, 'talla' ) || false !== stripos( $attr_name, 'size' ) ) {
                    if ( is_object( $attr_obj ) && method_exists( $attr_obj, 'get_terms' ) ) {
                        foreach ( $attr_obj->get_terms() as $t ) {
                            $sizes[] = $t->name;
                        }
                    } elseif ( is_object( $attr_obj ) && method_exists( $attr_obj, 'get_options' ) ) {
                        $sizes = array_merge( $sizes, $attr_obj->get_options() );
                    }
                }
            }
            if ( empty( $sizes ) ) {
                $sizes = $default_sizes;
            }
            $sizes = array_values( array_unique( $sizes ) );

            $adicionales = get_post_meta( $pid, '_wooconfig_adicionales', true );
            if ( ! is_array( $adicionales ) ) {
                $adicionales = array(
                    'cuello_polo' => array( 'id' => 'cuello_polo', 'enabled' => 'yes', 'label' => __( 'Cuello piqué', 'woo-config' ), 'price' => 2500 ),
                    'manga_larga' => array( 'id' => 'manga_larga', 'enabled' => 'yes', 'label' => __( 'Manga larga', 'woo-config' ), 'price' => 2500 ),
                );
            }
            $active_ads = array();
            foreach ( $adicionales as $k => $ad ) {
                if ( isset( $ad['enabled'] ) && 'yes' === $ad['enabled'] ) {
                    $active_ads[$k] = $ad;
                }
            }

            $list[] = array(
                'id'          => $pid,
                'name'        => $product->get_name(),
                'price'       => (float) $product->get_price(),
                'price_html'  => wc_price( $product->get_price() ),
                'sizes'       => $sizes,
                'adicionales' => $active_ads,
            );
        }

        wp_send_json_success( array( 'products' => $list ) );
    }

    public static function ajax_save_group() {
        check_ajax_referer( 'wooconfig_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'woo-config' ) ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $order    = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( array( 'message' => __( 'Pedido no encontrado.', 'woo-config' ) ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $product    = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => __( 'Producto seleccionado no válido.', 'woo-config' ) ) );
        }

        $group_title = isset( $_POST['group_title'] ) ? sanitize_text_field( wp_unslash( $_POST['group_title'] ) ) : '';
        if ( empty( $group_title ) ) {
            $group_title = sprintf( __( 'Equipo: %s', 'woo-config' ), $product->get_name() );
        }

        $design_notes  = isset( $_POST['design_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['design_notes'] ) ) : '';
        $sponsor_notes = isset( $_POST['sponsor_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sponsor_notes'] ) ) : '';

        $desc_parts = array();
        if ( ! empty( $design_notes ) ) {
            $desc_parts[] = __( 'Diseño:', 'woo-config' ) . ' ' . $design_notes;
        }
        if ( ! empty( $sponsor_notes ) ) {
            $desc_parts[] = __( 'Sponsors:', 'woo-config' ) . ' ' . $sponsor_notes;
        }
        $combined_desc = implode( "\n\n", $desc_parts );

        $files_raw = isset( $_POST['files'] ) ? wp_unslash( $_POST['files'] ) : '[]';
        $files     = json_decode( $files_raw, true );
        if ( ! is_array( $files ) ) {
            $files = array();
        }

        $items_raw = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '[]';
        $items_data = json_decode( $items_raw, true );
        if ( ! is_array( $items_data ) || empty( $items_data ) ) {
            wp_send_json_error( array( 'message' => __( 'Debes añadir al menos 1 prenda o uniforme con su talla.', 'woo-config' ) ) );
        }

        // Product configured adicionales
        $product_adicionales = get_post_meta( $product_id, '_wooconfig_adicionales', true );
        if ( ! is_array( $product_adicionales ) ) {
            $product_adicionales = array(
                'cuello_polo' => array( 'id' => 'cuello_polo', 'enabled' => 'yes', 'label' => __( 'Cuello piqué', 'woo-config' ), 'price' => 2500 ),
                'manga_larga' => array( 'id' => 'manga_larga', 'enabled' => 'yes', 'label' => __( 'Manga larga', 'woo-config' ), 'price' => 2500 ),
            );
        }

        $base_price = (float) $product->get_price();
        $group_id   = 'group_admin_' . wp_generate_uuid4();

        // Save new group metadata
        $groups = $order->get_meta( '_wooconfig_order_groups' );
        if ( ! is_array( $groups ) ) {
            $groups = array();
        }
        $groups[$group_id] = array(
            'group_id'    => $group_id,
            'flow'        => 'wizard',
            'group_title' => $group_title,
            'design_desc' => $combined_desc,
            'files'       => $files,
        );
        $order->update_meta_data( '_wooconfig_order_groups', $groups );

        // Add line items
        $idx = 1;
        foreach ( $items_data as $row ) {
            $dorsal = isset( $row['dorsal'] ) ? sanitize_text_field( $row['dorsal'] ) : '';
            $name   = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
            $size   = isset( $row['size'] ) ? sanitize_text_field( $row['size'] ) : 'M';

            $assigned_ads = isset( $row['adicionales'] ) && is_array( $row['adicionales'] ) ? $row['adicionales'] : array();
            $ad_labels = array();
            $ad_cost   = 0;
            foreach ( $assigned_ads as $ad_key ) {
                if ( isset( $product_adicionales[$ad_key] ) ) {
                    $ad_info = $product_adicionales[$ad_key];
                    $cost    = (float) $ad_info['price'];
                    $ad_cost += $cost;
                    $ad_labels[] = $ad_info['label'] . ' (+' . wc_price( $cost ) . ')';
                }
            }

            $unit_price = $base_price + $ad_cost;

            $item = new WC_Order_Item_Product();
            $item->set_product( $product );
            $item->set_name( $product->get_name() );
            $item->set_quantity( 1 );
            $item->set_subtotal( $unit_price );
            $item->set_total( $unit_price );

            $item->add_meta_data( '_wooconfig_group_id', $group_id );
            $item->add_meta_data( '_wooconfig_group_title', $group_title );
            $item->add_meta_data( '_wooconfig_item_index', $idx );
            $item->add_meta_data( '_wooconfig_flow', 'wizard' );
            $item->add_meta_data( __( 'Dorsal (#)', 'woo-config' ), $dorsal );
            $item->add_meta_data( __( 'Nombre / Alias', 'woo-config' ), $name );
            $item->add_meta_data( __( 'Talla', 'woo-config' ), $size );
            $item->add_meta_data( __( 'Adicionales', 'woo-config' ), $ad_labels );
            $item->add_meta_data( '_wooconfig_adicionales_raw', $assigned_ads );
            $item->add_meta_data( '_wooconfig_full_unit_price', $unit_price );

            $order->add_item( $item );
            $idx++;
        }

        // Remove original standard item if this is a desglosar/conversion
        $convert_item_id = isset( $_POST['convert_item_id'] ) ? absint( $_POST['convert_item_id'] ) : 0;
        if ( $convert_item_id > 0 ) {
            $convert_item = $order->get_item( $convert_item_id );
            if ( $convert_item ) {
                $order->remove_item( $convert_item_id );
                $order->add_order_note( sprintf(
                    __( '⚡ Artículo estándar "%s" (×%d) desglosado en %d uniformes individuales con dorsales y tallas.', 'woo-config' ),
                    $convert_item->get_name(),
                    $convert_item->get_quantity(),
                    count( $items_data )
                ) );
            }
        }

        // Handle payment mode (deposit vs full)
        $payment_mode = isset( $_POST['payment_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_mode'] ) ) : 'full';
        if ( 'deposit' === $payment_mode ) {
            $deposit_percent = 50;
            $total_items_cost = 0;
            foreach ( $items_data as $row ) {
                $assigned_ads = isset( $row['adicionales'] ) && is_array( $row['adicionales'] ) ? $row['adicionales'] : array();
                $ad_cost = 0;
                foreach ( $assigned_ads as $ad_key ) {
                    if ( isset( $product_adicionales[$ad_key] ) ) {
                        $ad_cost += (float) $product_adicionales[$ad_key]['price'];
                    }
                }
                $total_items_cost += $base_price + $ad_cost;
            }
            $deposit_total = round( $total_items_cost * ( $deposit_percent / 100 ), 0 );
            $balance_total = $total_items_cost - $deposit_total;

            $order->update_meta_data( '_wooconfig_has_deposit', 'yes' );
            $existing_full = (float) $order->get_meta( '_wooconfig_order_full_total' );
            $existing_dep  = (float) $order->get_meta( '_wooconfig_order_deposit_total' );
            $existing_bal  = (float) $order->get_meta( '_wooconfig_order_balance_total' );
            $order->update_meta_data( '_wooconfig_order_full_total', $existing_full + $total_items_cost );
            $order->update_meta_data( '_wooconfig_order_deposit_total', $existing_dep + $deposit_total );
            $order->update_meta_data( '_wooconfig_order_balance_total', $existing_bal + $balance_total );
            if ( ! $order->get_meta( '_wooconfig_balance_status' ) ) {
                $order->update_meta_data( '_wooconfig_balance_status', 'pending' );
            }
        }

        $order->calculate_totals();
        $order->save();

        wp_send_json_success( array(
            'message'  => __( 'Equipo y uniformes añadidos correctamente al pedido.', 'woo-config' ),
            'group_id' => $group_id,
        ) );
    }

    public static function ajax_delete_item() {
        check_ajax_referer( 'wooconfig_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'woo-config' ) ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $item_id  = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( array( 'message' => __( 'Pedido no encontrado.', 'woo-config' ) ) );
        }

        $item = $order->get_item( $item_id );
        if ( ! $item ) {
            wp_send_json_error( array( 'message' => __( 'Prenda no encontrada.', 'woo-config' ) ) );
        }

        $order->remove_item( $item_id );
        $order->calculate_totals();
        $order->save();

        wp_send_json_success( array( 'message' => __( 'Prenda eliminada correctamente.', 'woo-config' ) ) );
    }

    public static function ajax_update_item() {
        check_ajax_referer( 'wooconfig_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'woo-config' ) ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $item_id  = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( array( 'message' => __( 'Pedido no encontrado.', 'woo-config' ) ) );
        }

        $item = $order->get_item( $item_id );
        if ( ! $item ) {
            wp_send_json_error( array( 'message' => __( 'Prenda no encontrada.', 'woo-config' ) ) );
        }

        if ( isset( $_POST['dorsal'] ) ) {
            $item->update_meta_data( __( 'Dorsal (#)', 'woo-config' ), sanitize_text_field( wp_unslash( $_POST['dorsal'] ) ) );
        }
        if ( isset( $_POST['name'] ) ) {
            $item->update_meta_data( __( 'Nombre / Alias', 'woo-config' ), sanitize_text_field( wp_unslash( $_POST['name'] ) ) );
        }
        if ( isset( $_POST['size'] ) ) {
            $item->update_meta_data( __( 'Talla', 'woo-config' ), sanitize_text_field( wp_unslash( $_POST['size'] ) ) );
        }

        $item->save();
        $order->save();

        wp_send_json_success( array( 'message' => __( 'Prenda actualizada correctamente.', 'woo-config' ) ) );
    }

    public static function ajax_add_item() {
        check_ajax_referer( 'wooconfig_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'woo-config' ) ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $group_id = isset( $_POST['group_id'] ) ? sanitize_text_field( wp_unslash( $_POST['group_id'] ) ) : '';

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( array( 'message' => __( 'Pedido no encontrado.', 'woo-config' ) ) );
        }

        // Find reference item in group to inherit product and group title
        $reference_item = null;
        $max_index = 0;
        foreach ( $order->get_items() as $it ) {
            if ( $it->get_meta( '_wooconfig_group_id' ) === $group_id ) {
                $reference_item = $it;
                $idx = (int) $it->get_meta( '_wooconfig_item_index' );
                if ( $idx > $max_index ) {
                    $max_index = $idx;
                }
            }
        }

        $product_id = $reference_item ? $reference_item->get_product_id() : ( isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0 );
        $product    = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => __( 'Producto no encontrado.', 'woo-config' ) ) );
        }

        $dorsal = isset( $_POST['dorsal'] ) ? sanitize_text_field( wp_unslash( $_POST['dorsal'] ) ) : '';
        $name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $size   = isset( $_POST['size'] ) ? sanitize_text_field( wp_unslash( $_POST['size'] ) ) : 'M';

        $base_price = (float) $product->get_price();

        $item = new WC_Order_Item_Product();
        $item->set_product( $product );
        $item->set_name( $product->get_name() );
        $item->set_quantity( 1 );
        $item->set_subtotal( $base_price );
        $item->set_total( $base_price );

        $item->add_meta_data( '_wooconfig_group_id', $group_id );
        $item->add_meta_data( '_wooconfig_group_title', $reference_item ? $reference_item->get_meta( '_wooconfig_group_title' ) : $product->get_name() );
        $item->add_meta_data( '_wooconfig_item_index', $max_index + 1 );
        $item->add_meta_data( __( 'Dorsal (#)', 'woo-config' ), $dorsal );
        $item->add_meta_data( __( 'Nombre / Alias', 'woo-config' ), $name );
        $item->add_meta_data( __( 'Talla', 'woo-config' ), $size );
        $item->add_meta_data( '_wooconfig_full_unit_price', $base_price );

        $order->add_item( $item );
        $order->calculate_totals();
        $order->save();

        wp_send_json_success( array(
            'message' => __( 'Prenda añadida correctamente.', 'woo-config' ),
            'item_id' => $item->get_id(),
        ) );
    }

    public static function ajax_preview_sheet() {
        check_ajax_referer( 'wooconfig_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'woo-config' ) ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $order    = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( array( 'message' => __( 'Pedido no encontrado.', 'woo-config' ) ) );
        }

        $group_title = isset( $_POST['group_title'] ) ? sanitize_text_field( wp_unslash( $_POST['group_title'] ) ) : 'Vista Previa de Taller';
        $design_desc = isset( $_POST['design_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['design_desc'] ) ) : '';
        $files_raw   = isset( $_POST['files'] ) ? wp_unslash( $_POST['files'] ) : '[]';
        $files       = json_decode( $files_raw, true );
        if ( ! is_array( $files ) ) $files = array();

        $items_raw   = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '[]';
        $items       = json_decode( $items_raw, true );
        if ( ! is_array( $items ) ) $items = array();

        $draft_data = array(
            'group_title' => $group_title,
            'design_desc' => $design_desc,
            'files'       => $files,
            'items'       => $items,
        );

        $draft_key = 'wooconfig_draft_' . wp_generate_uuid4();
        set_transient( $draft_key, $draft_data, 15 * MINUTE_IN_SECONDS );

        $preview_url = admin_url( 'admin.php?action=wooconfig_production_sheet&order_id=' . $order_id . '&draft_key=' . $draft_key . '&_wpnonce=' . wp_create_nonce( 'wooconfig_pdf_' . $order_id ) );

        wp_send_json_success( array(
            'preview_url' => $preview_url,
            'draft_key'   => $draft_key,
        ) );
    }

    /**
     * AJAX: Convert standard WooCommerce items into individual WooConfig uniforms.
     * Used for direct conversion without going through the full modal.
     */
    public static function ajax_convert_standard_items() {
        check_ajax_referer( 'wooconfig_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'woo-config' ) ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $item_id  = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( array( 'message' => __( 'Pedido no encontrado.', 'woo-config' ) ) );
        }

        $original_item = $order->get_item( $item_id );
        if ( ! $original_item ) {
            wp_send_json_error( array( 'message' => __( 'Artículo no encontrado.', 'woo-config' ) ) );
        }

        $product_id = $original_item->get_product_id();
        $product    = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => __( 'Producto no encontrado.', 'woo-config' ) ) );
        }

        $quantity    = $original_item->get_quantity();
        $base_price  = (float) $product->get_price();
        $group_id    = 'group_admin_' . wp_generate_uuid4();
        $group_title = sprintf( __( 'Equipo: %s', 'woo-config' ), $product->get_name() );

        // Create group meta
        $groups = $order->get_meta( '_wooconfig_order_groups' );
        if ( ! is_array( $groups ) ) {
            $groups = array();
        }
        $groups[ $group_id ] = array(
            'group_id'    => $group_id,
            'flow'        => 'wizard',
            'group_title' => $group_title,
            'design_desc' => sprintf( __( 'Desglosado automáticamente desde artículo estándar ×%d.', 'woo-config' ), $quantity ),
            'files'       => array(),
        );
        $order->update_meta_data( '_wooconfig_order_groups', $groups );

        // Create individual items
        for ( $i = 1; $i <= $quantity; $i++ ) {
            $item = new WC_Order_Item_Product();
            $item->set_product( $product );
            $item->set_name( $product->get_name() );
            $item->set_quantity( 1 );
            $item->set_subtotal( $base_price );
            $item->set_total( $base_price );

            $item->add_meta_data( '_wooconfig_group_id', $group_id );
            $item->add_meta_data( '_wooconfig_group_title', $group_title );
            $item->add_meta_data( '_wooconfig_item_index', $i );
            $item->add_meta_data( '_wooconfig_flow', 'wizard' );
            $item->add_meta_data( __( 'Dorsal (#)', 'woo-config' ), '' );
            $item->add_meta_data( __( 'Nombre / Alias', 'woo-config' ), '' );
            $item->add_meta_data( __( 'Talla', 'woo-config' ), 'M' );
            $item->add_meta_data( '_wooconfig_full_unit_price', $base_price );

            $order->add_item( $item );
        }

        // Remove original item
        $order->remove_item( $item_id );
        $order->add_order_note( sprintf(
            __( '⚡ Artículo estándar "%s" (×%d) desglosado en %d uniformes individuales.', 'woo-config' ),
            $product->get_name(),
            $quantity,
            $quantity
        ) );

        $order->calculate_totals();
        $order->save();

        wp_send_json_success( array(
            'message'  => sprintf( __( '%d prendas desglosadas correctamente. Ahora puedes configurar tallas y dorsales.', 'woo-config' ), $quantity ),
            'group_id' => $group_id,
        ) );
    }
}

WC_Config_Order::init();
