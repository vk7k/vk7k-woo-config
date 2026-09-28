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
        $groups              = array();
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
                $raw_files   = $item->get_meta( '_wooconfig_files' );
                $design_desc = $item->get_meta( '_wooconfig_design_desc' );
                $flow        = $item->get_meta( '_wooconfig_flow' );

                $permanent_files = WC_Config_Uploader::move_temp_files_to_order( $raw_files, $order_id );

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

        foreach ( $items as $item ) {
            if ( $item->get_meta( '_wooconfig_group_id' ) ) {
                $has_wooconfig_items = true;
                break;
            }
        }

        if ( ! $has_wooconfig_items ) {
            echo '<p style="color: #666; font-style: italic;">' . esc_html__( 'Este pedido no contiene productos personalizados con WooConfig.', 'woo-config' ) . '</p>';
            return;
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

            <div class="wooconfig-admin-header-actions" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">Detalle de Personalización por Equipo</h3>
                <a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" class="button button-primary button-large" style="background: #111; border-color: #111; color: #fff;">
                    📄 Imprimir / Generar Ficha de Producción (PDF)
                </a>
            </div>

            <?php
            $grouped_items = array();
            foreach ( $items as $item_id => $item ) {
                $gid = $item->get_meta( '_wooconfig_group_id' );
                if ( empty( $gid ) ) continue;
                $grouped_items[$gid][] = $item;
            }

            foreach ( $grouped_items as $gid => $group_item_list ) :
                $group_meta  = isset( $groups[$gid] ) ? $groups[$gid] : array();
                $first_item  = $group_item_list[0];
                $flow        = isset( $group_meta['flow'] ) ? $group_meta['flow'] : $first_item->get_meta( '_wooconfig_flow' );
                $group_title = $first_item->get_meta( '_wooconfig_group_title' );
                $design_desc = isset( $group_meta['design_desc'] ) ? $group_meta['design_desc'] : $first_item->get_meta( '_wooconfig_design_desc' );
                $files       = isset( $group_meta['files'] ) ? $group_meta['files'] : $first_item->get_meta( '_wooconfig_files' );
                ?>
                <div class="wooconfig-group-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 18px; margin-bottom: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #94ec00; padding-bottom: 10px; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-size: 16px; font-weight: 700;">
                            👕 <?php echo esc_html( $group_title ? $group_title : 'Equipo Personalizado' ); ?>
                        </h4>
                        <span style="background: #f0f0f1; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 13px;">
                            <?php if ( 'quick' === $flow ) : ?>
                                📁 Configuración Rápida (<?php echo esc_html( $first_item->get_quantity() ); ?> unidades)
                            <?php else : ?>
                                📋 Wizard Paso a Paso (<?php echo count( $group_item_list ); ?> prendas)
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php if ( 'quick' === $flow ) : 
                        $ad_summary = $first_item->get_meta( __( 'Adicionales incluidos', 'woo-config' ) );
                        $clean_ad_summary = array();
                        if ( is_array( $ad_summary ) ) {
                            foreach ( $ad_summary as $as_item ) {
                                $clean_ad_summary[] = html_entity_decode( wp_strip_all_tags( $as_item ) );
                            }
                        } elseif ( $ad_summary ) {
                            $clean_ad_summary[] = html_entity_decode( wp_strip_all_tags( $ad_summary ) );
                        }
                    ?>
                        <div style="background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 6px; padding: 12px 15px; margin-bottom: 15px;">
                            <strong style="display: block; margin-bottom: 4px; color: #111;">📦 Resumen del Pedido Rápido:</strong>
                            <p style="margin: 3px 0;"><strong>Cantidad total:</strong> <?php echo esc_html( $first_item->get_quantity() ); ?> prendas</p>
                            <?php if ( ! empty( $clean_ad_summary ) ) : ?>
                                <p style="margin: 3px 0;"><strong>Adicionales:</strong> <?php echo esc_html( implode( ', ', $clean_ad_summary ) ); ?></p>
                            <?php endif; ?>
                            <p style="margin: 3px 0; color: #666; font-size: 12px;"><em>* Los detalles de tallas y números se encuentran en los archivos adjuntos a continuación.</em></p>
                        </div>
                    <?php else : ?>
                        <table class="widefat fixed striped" style="margin-bottom: 20px;">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Prenda</th>
                                    <th style="width: 80px;">Dorsal</th>
                                    <th>Nombre / Alias</th>
                                    <th style="width: 90px;">Talla</th>
                                    <th>Adicionales</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $group_item_list as $i => $item ) : 
                                    $ads = $item->get_meta( __( 'Adicionales', 'woo-config' ) );
                                    $ads_text = is_array( $ads ) ? implode( ', ', $ads ) : ( $ads ? $ads : '-' );
                                    $clean_ads_text = html_entity_decode( wp_strip_all_tags( $ads_text ) );
                                ?>
                                    <tr>
                                        <td><strong>#<?php echo esc_html( $item->get_meta( '_wooconfig_item_index' ) ? $item->get_meta( '_wooconfig_item_index' ) : ($i + 1) ); ?></strong></td>
                                        <td><?php echo esc_html( $item->get_name() ); ?></td>
                                        <td><strong style="color: #0073aa; font-size: 15px;"><?php echo esc_html( $item->get_meta( __( 'Dorsal (#)', 'woo-config' ) ) ); ?></strong></td>
                                        <td><strong style="text-transform: uppercase;"><?php echo esc_html( $item->get_meta( __( 'Nombre / Alias', 'woo-config' ) ) ); ?></strong></td>
                                        <td><span style="display: inline-block; background: #e0e0e0; padding: 2px 8px; border-radius: 3px; font-weight: 700;"><?php echo esc_html( $item->get_meta( __( 'Talla', 'woo-config' ) ) ); ?></span></td>
                                        <td><span style="font-size: 12px; color: #222; font-weight: 600;"><?php echo esc_html( $clean_ads_text ); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <div style="background: #fdfdfd; border: 1px solid #e2e4e7; border-radius: 6px; padding: 12px 15px; margin-bottom: 15px;">
                        <strong style="display: block; margin-bottom: 6px; color: #111;">📝 Descripción e Instrucciones de Diseño:</strong>
                        <p style="margin: 0; white-space: pre-wrap; font-size: 14px; line-height: 1.5; color: #333;">
                            <?php echo esc_html( $design_desc ? $design_desc : '(Sin descripción de diseño ingresada)' ); ?>
                        </p>
                    </div>

                    <?php if ( ! empty( $files ) && is_array( $files ) ) : ?>
                        <div>
                            <strong style="display: block; margin-bottom: 8px; color: #111;">📁 Archivos Adjuntos / Referencias / Planillas:</strong>
                            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                                <?php foreach ( $files as $f ) :
                                    $file_url  = isset( $f['file_url'] ) ? $f['file_url'] : '';
                                    $orig_name = isset( $f['original_name'] ) ? $f['original_name'] : 'Archivo adjunto';
                                    $is_img    = isset( $f['is_image'] ) ? $f['is_image'] : false;
                                    ?>
                                    <div style="border: 1px solid #ddd; border-radius: 6px; padding: 10px; background: #fafafa; display: flex; align-items: center; gap: 10px; max-width: 320px;">
                                        <?php if ( $is_img && ! empty( $file_url ) ) : ?>
                                            <a href="<?php echo esc_url( $file_url ); ?>" target="_blank">
                                                <img src="<?php echo esc_url( $file_url ); ?>" style="width: 48px; height: 48px; object-fit: cover; border-radius: 4px; border: 1px solid #ccc;">
                                            </a>
                                        <?php else : ?>
                                            <div style="width: 48px; height: 48px; background: #e5e5e5; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                                                📄
                                            </div>
                                        <?php endif; ?>
                                        <div style="overflow: hidden;">
                                            <div style="font-weight: 600; font-size: 13px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?php echo esc_attr( $orig_name ); ?>">
                                                <?php echo esc_html( $orig_name ); ?>
                                            </div>
                                            <a href="<?php echo esc_url( $file_url ); ?>" target="_blank" download class="button button-small" style="margin-top: 4px;">
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
        $payment_url     = self::get_balance_payment_url( $order );
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
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 14px; margin-bottom: 15px;">
                        <p style="margin: 0; font-size: 13px; color: #166534; line-height: 1.5;">
                            ℹ <strong><?php esc_html_e( 'Tu boceto está en proceso de elaboración.', 'woo-config' ); ?></strong> <?php esc_html_e( 'Recibirás la propuesta de diseño dentro de un plazo máximo de 24 horas. Una vez que apruebes el boceto, tu pedido iniciará confección. Puedes liquidar el saldo restante en cualquier momento para agilizar el despacho.', 'woo-config' ); ?>
                        </p>
                    </div>
                <?php else : ?>
                    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 14px; margin-bottom: 15px;">
                        <p style="margin: 0; font-size: 13px; color: #92400e; line-height: 1.5;">
                            ℹ <strong><?php esc_html_e( 'El pago inicial da inicio a la elaboración de tu boceto (24 horas).', 'woo-config' ); ?></strong> <?php esc_html_e( 'Una vez confirmado tu abono (50%), prepararemos tu diseño. Tras tu aprobación del boceto, se iniciará la confección de tu pedido.', 'woo-config' ); ?>
                        </p>
                    </div>
                <?php endif; ?>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="<?php echo esc_url( $payment_url ); ?>" class="button button-primary" style="background: #111; border-color: #111; color: #fff; padding: 12px 20px; font-weight: 800; font-size: 14px; border-radius: 6px; text-decoration: none;">
                        💳 <?php esc_html_e( 'Pagar Saldo Pendiente en Línea (Webpay / Tarjetas)', 'woo-config' ); ?>
                    </a>
                </div>
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
}

WC_Config_Order::init();
