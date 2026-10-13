<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Config_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
        add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_product_tab_panel' ) );
        add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_meta' ) );
        add_action( 'woocommerce_new_product', array( __CLASS__, 'set_new_product_defaults' ), 10, 1 );
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_fields' ) );
        add_action( 'admin_notices', array( __CLASS__, 'display_permission_admin_notices' ) );
    }

    public static function register_admin_menu() {
        if ( class_exists( 'Uxcribe_Admin_Menu' ) ) {
            Uxcribe_Admin_Menu::add_subpage(
                __( 'Ajustes WooConfig', 'woo-config' ),
                __( 'WooConfig', 'woo-config' ),
                'manage_woocommerce',
                'wooconfig-settings',
                array( __CLASS__, 'render_settings_page' )
            );
        } else {
            add_submenu_page(
                'woocommerce',
                __( 'Ajustes WooConfig', 'woo-config' ),
                __( 'WooConfig', 'woo-config' ),
                'manage_woocommerce',
                'wooconfig-settings',
                array( __CLASS__, 'render_settings_page' )
            );
        }
    }

    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'woo-config' ) );
        }

        $message = '';
        $message_type = 'success';

        // Process Settings Form
        if ( isset( $_POST['wooconfig_save_settings'] ) && check_admin_referer( 'wooconfig_save_settings_action', 'wooconfig_settings_nonce' ) ) {
            $min_units = isset( $_POST['wooconfig_min_units'] ) ? max( 1, absint( $_POST['wooconfig_min_units'] ) ) : 1;
            update_option( 'wooconfig_min_units', $min_units );
            update_option( 'wooconfig_default_min_units', $min_units );

            $deposit_enabled = isset( $_POST['wooconfig_deposit_enabled'] ) ? 'yes' : 'no';
            update_option( 'wooconfig_deposit_enabled', $deposit_enabled );

            $deposit_percent = isset( $_POST['wooconfig_deposit_percent'] ) ? absint( $_POST['wooconfig_deposit_percent'] ) : 50;
            if ( $deposit_percent < 1 || $deposit_percent > 99 ) {
                $deposit_percent = 50;
            }
            update_option( 'wooconfig_deposit_percent', $deposit_percent );

            $allow_full = isset( $_POST['wooconfig_allow_full_payment'] ) ? 'yes' : 'no';
            update_option( 'wooconfig_allow_full_payment', $allow_full );

            $auto_delete = isset( $_POST['wooconfig_auto_delete_order_files'] ) ? 'yes' : 'no';
            update_option( 'wooconfig_auto_delete_order_files', $auto_delete );

            $retention_days = isset( $_POST['wooconfig_retention_days'] ) ? absint( $_POST['wooconfig_retention_days'] ) : 0;
            update_option( 'wooconfig_retention_days', $retention_days );

            $sketch_notice_enabled = isset( $_POST['wooconfig_sketch_notice_enabled'] ) ? 'yes' : 'no';
            update_option( 'wooconfig_sketch_notice_enabled', $sketch_notice_enabled );

            $sketch_notice_text = isset( $_POST['wooconfig_sketch_notice_text'] ) ? sanitize_textarea_field( $_POST['wooconfig_sketch_notice_text'] ) : '';
            update_option( 'wooconfig_sketch_notice_text', $sketch_notice_text );

            $preferred_contact_enabled = isset( $_POST['wooconfig_preferred_contact_enabled'] ) ? 'yes' : 'no';
            update_option( 'wooconfig_preferred_contact_enabled', $preferred_contact_enabled );

            $preferred_contact_label = isset( $_POST['wooconfig_preferred_contact_label'] ) ? sanitize_text_field( $_POST['wooconfig_preferred_contact_label'] ) : '';
            update_option( 'wooconfig_preferred_contact_label', $preferred_contact_label );

            $preferred_contact_options = isset( $_POST['wooconfig_preferred_contact_options'] ) ? sanitize_text_field( $_POST['wooconfig_preferred_contact_options'] ) : '';
            update_option( 'wooconfig_preferred_contact_options', $preferred_contact_options );

            // Venta Mayorista / Precios por Mayor
            $wholesale_enabled = isset( $_POST['wooconfig_wholesale_enabled'] ) ? 'yes' : 'no';
            update_option( 'wooconfig_wholesale_enabled', $wholesale_enabled );

            $wholesale_cart_qty = isset( $_POST['wooconfig_wholesale_cart_qty'] ) ? max( 1, absint( $_POST['wooconfig_wholesale_cart_qty'] ) ) : 50;
            update_option( 'wooconfig_wholesale_cart_qty', $wholesale_cart_qty );

            $wholesale_override = isset( $_POST['wooconfig_wholesale_override_product_rules'] ) ? 'yes' : 'no';
            update_option( 'wooconfig_wholesale_override_product_rules', $wholesale_override );

            $wholesale_modal_title = isset( $_POST['wooconfig_wholesale_modal_title'] ) ? sanitize_text_field( $_POST['wooconfig_wholesale_modal_title'] ) : '';
            update_option( 'wooconfig_wholesale_modal_title', $wholesale_modal_title );

            $wholesale_modal_content = isset( $_POST['wooconfig_wholesale_modal_content'] ) ? wp_kses_post( $_POST['wooconfig_wholesale_modal_content'] ) : '';
            update_option( 'wooconfig_wholesale_modal_content', $wholesale_modal_content );

            $message = __( 'Ajustes de WooConfig guardados correctamente.', 'woo-config' );
        }

        // Process Manual Storage Cleanup Actions
        if ( isset( $_POST['wooconfig_action_clean_temp'] ) && check_admin_referer( 'wooconfig_storage_actions', 'wooconfig_storage_nonce' ) ) {
            $cleaned = WC_Config_Uploader::clean_all_temp_files();
            $message = sprintf(
                __( '🧹 Limpieza de temporales completada: Se eliminaron %d archivos temporales liberando %s de espacio.', 'woo-config' ),
                $cleaned['files'],
                size_format( $cleaned['freed_bytes'], 2 )
            );
        }

        if ( isset( $_POST['wooconfig_action_clean_orphans'] ) && check_admin_referer( 'wooconfig_storage_actions', 'wooconfig_storage_nonce' ) ) {
            $cleaned = WC_Config_Uploader::clean_orphan_order_files();
            if ( $cleaned['folders'] > 0 ) {
                $message = sprintf(
                    __( '🔍 Limpieza de huérfanos completada: Se eliminaron %d carpetas de pedidos inexistentes (%d archivos), liberando %s.', 'woo-config' ),
                    $cleaned['folders'],
                    $cleaned['files'],
                    size_format( $cleaned['freed_bytes'], 2 )
                );
            } else {
                $message = __( '✨ No se encontraron carpetas huérfanas. Todo el almacenamiento de pedidos se encuentra sincronizado con la base de datos.', 'woo-config' );
            }
        }

        $min_units       = (int) get_option( 'wooconfig_min_units', get_option( 'wooconfig_default_min_units', 1 ) );
        $deposit_enabled = get_option( 'wooconfig_deposit_enabled', 'yes' );
        $deposit_percent = (int) get_option( 'wooconfig_deposit_percent', 50 );
        $allow_full      = get_option( 'wooconfig_allow_full_payment', 'yes' );
        $auto_delete     = get_option( 'wooconfig_auto_delete_order_files', 'yes' );
        $retention_days            = (int) get_option( 'wooconfig_retention_days', 0 );
        $sketch_notice_enabled     = get_option( 'wooconfig_sketch_notice_enabled', 'yes' );
        $sketch_notice_text        = get_option( 'wooconfig_sketch_notice_text', __( 'Luego de realizar el pago inicial, recibirás el boceto de tu diseño dentro de un plazo máximo de 24 horas a través del medio de contacto que señalaste.', 'woo-config' ) );
        $preferred_contact_enabled = get_option( 'wooconfig_preferred_contact_enabled', 'yes' );
        $preferred_contact_label   = get_option( 'wooconfig_preferred_contact_label', __( 'Medio de contacto preferido para recepción de boceto', 'woo-config' ) );
        $preferred_contact_options = get_option( 'wooconfig_preferred_contact_options', 'WhatsApp, Correo electrónico, Llamada telefónica' );
        
        $wholesale_enabled         = get_option( 'wooconfig_wholesale_enabled', 'yes' );
        $wholesale_cart_qty        = max( 1, (int) get_option( 'wooconfig_wholesale_cart_qty', 50 ) );
        $wholesale_override        = get_option( 'wooconfig_wholesale_override_product_rules', 'yes' );
        $wholesale_modal_title     = get_option( 'wooconfig_wholesale_modal_title', __( 'Condiciones de Precios por Mayor', 'woo-config' ) );
        $wholesale_modal_content   = get_option( 'wooconfig_wholesale_modal_content', WC_Config_Wholesale::get_default_modal_content() );

        // Fetch Live Storage Statistics
        $stats = WC_Config_Uploader::get_storage_stats();
        ?>
        <div class="wrap wooconfig-admin-settings-wrap" style="max-width: 960px;">
            <h1 style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                <span style="font-size: 28px;">⚙️</span>
                <span><?php esc_html_e( 'WooConfig - Ajustes Globales y Almacenamiento', 'woo-config' ); ?></span>
            </h1>

            <?php if ( ! empty( $message ) ) : ?>
                <div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible" style="padding: 12px 16px; border-left-color: #10b981; margin: 15px 0 20px; font-size: 14px; font-weight: 600;">
                    <p style="margin: 0; color: #065f46;">
                        <?php echo esc_html( $message ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Panel de Estadísticas de Almacenamiento en Vivo -->
            <div class="postbox" style="border-radius: 8px; border: 1px solid #c3c4c7; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px;">
                <div class="postbox-header" style="padding: 14px 18px; border-bottom: 1px solid #dcdcde; background: linear-gradient(135deg, #1f2937, #111827); color: #fff;">
                    <h2 style="font-size: 15px; font-weight: 700; margin: 0; color: #94ec00; display: flex; align-items: center; gap: 8px;">
                        <span>💾</span> <?php esc_html_e( 'Diagnóstico de Almacenamiento en Disco (WooConfig)', 'woo-config' ); ?>
                    </h2>
                </div>
                <div class="inside" style="padding: 20px 22px; margin: 0;">
                    <p style="font-size: 13px; color: #50575e; margin: 0 0 18px;">
                        <?php esc_html_e( 'Monitoreo en tiempo real del espacio en disco consumido por los archivos de diseño, logos y planillas subidos por los clientes.', 'woo-config' ); ?>
                    </p>

                    <!-- Tarjetas de métricas -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 22px;">
                        <!-- Total General -->
                        <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 14px 16px;">
                            <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 4px;">
                                📊 <?php esc_html_e( 'Total en Disco', 'woo-config' ); ?>
                            </span>
                            <strong style="font-size: 22px; color: #0f172a; display: block; font-weight: 800;">
                                <?php echo esc_html( $stats['total_formatted'] ); ?>
                            </strong>
                            <span style="font-size: 12px; color: #64748b;">
                                <?php printf( esc_html__( '%d archivos totales', 'woo-config' ), $stats['total_files'] ); ?>
                            </span>
                        </div>

                        <!-- Pedidos Activos -->
                        <div style="background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 8px; padding: 14px 16px;">
                            <span style="font-size: 11px; font-weight: 700; color: #166534; text-transform: uppercase; display: block; margin-bottom: 4px;">
                                📦 <?php esc_html_e( 'Archivos en Pedidos', 'woo-config' ); ?>
                            </span>
                            <strong style="font-size: 22px; color: #14532d; display: block; font-weight: 800;">
                                <?php echo esc_html( $stats['orders_formatted'] ); ?>
                            </strong>
                            <span style="font-size: 12px; color: #166534;">
                                <?php printf( esc_html__( '%d archivos en %d pedidos', 'woo-config' ), $stats['orders_files_count'], $stats['orders_count'] ); ?>
                            </span>
                        </div>

                        <!-- Temporales -->
                        <div style="background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 8px; padding: 14px 16px;">
                            <span style="font-size: 11px; font-weight: 700; color: #92400e; text-transform: uppercase; display: block; margin-bottom: 4px;">
                                ⏳ <?php esc_html_e( 'Archivos Temporales', 'woo-config' ); ?>
                            </span>
                            <strong style="font-size: 22px; color: #78350f; display: block; font-weight: 800;">
                                <?php echo esc_html( $stats['temp_formatted'] ); ?>
                            </strong>
                            <span style="font-size: 12px; color: #92400e;">
                                <?php printf( esc_html__( '%d archivos (pre-compra)', 'woo-config' ), $stats['temp_files_count'] ); ?>
                            </span>
                        </div>

                        <!-- Huérfanos -->
                        <div style="background: <?php echo $stats['orphan_count'] > 0 ? '#fef2f2' : '#f8fafc'; ?>; border: 1.5px solid <?php echo $stats['orphan_count'] > 0 ? '#fecaca' : '#e2e8f0'; ?>; border-radius: 8px; padding: 14px 16px;">
                            <span style="font-size: 11px; font-weight: 700; color: <?php echo $stats['orphan_count'] > 0 ? '#991b1b' : '#64748b'; ?>; text-transform: uppercase; display: block; margin-bottom: 4px;">
                                🗑️ <?php esc_html_e( 'Carpetas Huérfanas', 'woo-config' ); ?>
                            </span>
                            <strong style="font-size: 22px; color: <?php echo $stats['orphan_count'] > 0 ? '#dc2626' : '#0f172a'; ?>; display: block; font-weight: 800;">
                                <?php echo esc_html( $stats['orphan_formatted'] ); ?>
                            </strong>
                            <span style="font-size: 12px; color: <?php echo $stats['orphan_count'] > 0 ? '#991b1b' : '#64748b'; ?>;">
                                <?php printf( esc_html__( '%d pedidos borrados', 'woo-config' ), $stats['orphan_count'] ); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Botones de Acción Inmediata de Almacenamiento -->
                    <div style="background: #f1f5f9; border-radius: 6px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <strong style="display: block; font-size: 13px; color: #1e293b; margin-bottom: 2px;">
                                🛠️ <?php esc_html_e( 'Acciones de Mantenimiento Manual', 'woo-config' ); ?>
                            </strong>
                            <span style="font-size: 12px; color: #64748b;">
                                <?php esc_html_e( 'Libera espacio en el servidor de forma inmediata sin esperar la tarea programada.', 'woo-config' ); ?>
                            </span>
                        </div>

                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <form method="post" action="" onsubmit="return confirm('¿Confirmas que deseas vaciar todos los archivos temporales pre-compra pendientes?');">
                                <?php wp_nonce_field( 'wooconfig_storage_actions', 'wooconfig_storage_nonce' ); ?>
                                <button type="submit" name="wooconfig_action_clean_temp" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 5px;">
                                    <span>🧹</span> <?php esc_html_e( 'Limpiar Temporales Ahora', 'woo-config' ); ?>
                                </button>
                            </form>

                            <form method="post" action="" onsubmit="return confirm('¿Confirmas que deseas eliminar las carpetas de pedidos que ya no existen en WooCommerce?');">
                                <?php wp_nonce_field( 'wooconfig_storage_actions', 'wooconfig_storage_nonce' ); ?>
                                <button type="submit" name="wooconfig_action_clean_orphans" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 5px; <?php echo $stats['orphan_count'] > 0 ? 'border-color: #dc2626; color: #dc2626;' : ''; ?>">
                                    <span>🔍</span> <?php esc_html_e( 'Limpiar Carpetas Huérfanas', 'woo-config' ); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de Configuración Principal -->
            <form method="post" action="">
                <?php wp_nonce_field( 'wooconfig_save_settings_action', 'wooconfig_settings_nonce' ); ?>

                <!-- Sección 1: Gestión y Retención de Almacenamiento -->
                <div class="postbox" style="border-radius: 8px; border: 1px solid #c3c4c7; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px;">
                    <div class="postbox-header" style="padding: 14px 18px; border-bottom: 1px solid #dcdcde; background: #fafafa;">
                        <h2 style="font-size: 15px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                            <span>🗄️</span> <?php esc_html_e( 'Políticas Automáticas de Almacenamiento y Eliminación', 'woo-config' ); ?>
                        </h2>
                    </div>
                    <div class="inside" style="padding: 20px 22px; margin: 0;">
                        <table class="form-table" role="presentation" style="margin: 0;">
                            <tbody>
                                <tr>
                                    <th scope="row" style="width: 240px; font-weight: 600; padding: 12px 0;">
                                        <?php esc_html_e( 'Eliminación al borrar pedidos', 'woo-config' ); ?>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <label for="wooconfig_auto_delete_order_files" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">
                                            <input name="wooconfig_auto_delete_order_files" type="checkbox" id="wooconfig_auto_delete_order_files" value="yes" <?php checked( 'yes' === $auto_delete ); ?> style="margin: 0;" />
                                            <?php esc_html_e( 'Eliminar automáticamente los archivos del servidor al borrar permanentemente un pedido en WooCommerce', 'woo-config' ); ?>
                                        </label>
                                        <p class="description" style="margin-top: 6px;">
                                            <?php esc_html_e( 'Recomendado. Al vaciar la papelera de pedidos o borrar un pedido definitivamente, su carpeta /orders/{order_id}/ será eliminada de inmediato para no acumular archivos huérfanos.', 'woo-config' ); ?>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" style="width: 240px; font-weight: 600; padding: 12px 0;">
                                        <label for="wooconfig_retention_days"><?php esc_html_e( 'Purga automática de pedidos antiguos', 'woo-config' ); ?></label>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <select name="wooconfig_retention_days" id="wooconfig_retention_days" style="padding: 6px 10px; font-size: 14px;">
                                            <option value="0" <?php selected( 0, $retention_days ); ?>><?php esc_html_e( 'Desactivado (Conservar archivos indefinidamente)', 'woo-config' ); ?></option>
                                            <option value="60" <?php selected( 60, $retention_days ); ?>><?php esc_html_e( 'Purgar archivos tras 60 días (2 meses)', 'woo-config' ); ?></option>
                                            <option value="90" <?php selected( 90, $retention_days ); ?>><?php esc_html_e( 'Purgar archivos tras 90 días (3 meses)', 'woo-config' ); ?></option>
                                            <option value="180" <?php selected( 180, $retention_days ); ?>><?php esc_html_e( 'Purgar archivos tras 180 días (6 meses)', 'woo-config' ); ?></option>
                                            <option value="365" <?php selected( 365, $retention_days ); ?>><?php esc_html_e( 'Purgar archivos tras 365 días (1 año)', 'woo-config' ); ?></option>
                                        </select>
                                        <p class="description" style="margin-top: 6px;">
                                            <?php esc_html_e( 'Elimina automáticamente los archivos adjuntos de pedidos completados o cancelados que superen la antigüedad seleccionada durante el mantenimiento diario.', 'woo-config' ); ?>
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sección 2: Pedido Mínimo -->
                <div class="postbox" style="border-radius: 8px; border: 1px solid #c3c4c7; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px;">
                    <div class="postbox-header" style="padding: 14px 18px; border-bottom: 1px solid #dcdcde; background: #fafafa;">
                        <h2 style="font-size: 15px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                            <span>📦</span> <?php esc_html_e( 'Cantidad Mínima de Confección (Nivel Carrito)', 'woo-config' ); ?>
                        </h2>
                    </div>
                    <div class="inside" style="padding: 20px 22px; margin: 0;">
                        <p style="font-size: 13px; color: #50575e; margin: 0 0 16px;">
                            <?php esc_html_e( 'Establece el número mínimo de prendas personalizadas requeridas para procesar un pedido de equipo. Esta regla se aplica a nivel de carrito y en todos los productos configurables.', 'woo-config' ); ?>
                        </p>
                        <table class="form-table" role="presentation" style="margin: 0;">
                            <tbody>
                                <tr>
                                    <th scope="row" style="width: 240px; font-weight: 600; padding: 10px 0;">
                                        <label for="wooconfig_min_units"><?php esc_html_e( 'Pedido mínimo global (unidades)', 'woo-config' ); ?></label>
                                    </th>
                                    <td style="padding: 10px 0;">
                                        <input name="wooconfig_min_units" type="number" id="wooconfig_min_units" value="<?php echo esc_attr( $min_units ); ?>" min="1" step="1" class="small-text" style="width: 100px; padding: 6px 10px; font-size: 14px; font-weight: 700;" />
                                        <p class="description" style="margin-top: 6px;">
                                            <?php esc_html_e( 'Total de prendas personalizadas que el cliente debe acumular en su carrito para poder finalizar la compra (puede combinar distintos modelos y productos).', 'woo-config' ); ?>
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sección: Precios por Mayor y Reglas de Carrito -->
                <div class="postbox" style="border-radius: 8px; border: 1px solid #c3c4c7; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px;">
                    <div class="postbox-header" style="padding: 14px 18px; border-bottom: 1px solid #dcdcde; background: #fafafa;">
                        <h2 style="font-size: 15px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                            <span>🏷️</span> <?php esc_html_e( 'Precios Mayoristas y Reglas de Volumen (Nivel Carrito)', 'woo-config' ); ?>
                        </h2>
                    </div>
                    <div class="inside" style="padding: 20px 22px; margin: 0;">
                        <p style="font-size: 13px; color: #50575e; margin: 0 0 16px;">
                            <?php esc_html_e( 'Define las condiciones globales de volumen para que cualquier cliente active tarifas mayoristas en su pedido al cruzar el umbral en el carrito.', 'woo-config' ); ?>
                        </p>
                        <table class="form-table" role="presentation" style="margin: 0;">
                            <tbody>
                                <tr>
                                    <th scope="row" style="width: 260px; font-weight: 600; padding: 12px 0;">
                                        <?php esc_html_e( 'Funcionalidad Mayorista', 'woo-config' ); ?>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <label for="wooconfig_wholesale_enabled" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">
                                            <input name="wooconfig_wholesale_enabled" type="checkbox" id="wooconfig_wholesale_enabled" value="yes" <?php checked( 'yes' === $wholesale_enabled ); ?> style="margin: 0;" />
                                            <?php esc_html_e( 'Activar funcionalidad de precios y tarifas mayoristas en la tienda', 'woo-config' ); ?>
                                        </label>
                                        <p class="description" style="margin-top: 6px;">
                                            <?php esc_html_e( 'Si se desmarca, se desactivará por completo la funcionalidad de mayorista: se ocultarán los widgets y etiquetas de precio mayorista, modales informativos de condiciones, avisos en carrito y el recálculo dinámico de precios mayoristas.', 'woo-config' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width: 260px; font-weight: 600; padding: 12px 0;">
                                        <label for="wooconfig_wholesale_cart_qty"><?php esc_html_e( 'Umbral global de carrito (unidades)', 'woo-config' ); ?></label>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <input name="wooconfig_wholesale_cart_qty" type="number" id="wooconfig_wholesale_cart_qty" value="<?php echo esc_attr( $wholesale_cart_qty ); ?>" min="1" step="1" class="small-text" style="width: 100px; padding: 6px 10px; font-size: 14px; font-weight: 700;" />
                                        <p class="description" style="margin-top: 6px;">
                                            <?php esc_html_e( 'Cantidad total de prendas en el carrito requeridas para activar la tarifa mayorista global (por defecto: 50).', 'woo-config' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width: 260px; font-weight: 600; padding: 12px 0;">
                                        <?php esc_html_e( 'Anulación de mínimos individuales', 'woo-config' ); ?>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <label for="wooconfig_wholesale_override_product_rules" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">
                                            <input name="wooconfig_wholesale_override_product_rules" type="checkbox" id="wooconfig_wholesale_override_product_rules" value="yes" <?php checked( 'yes' === $wholesale_override ); ?> style="margin: 0;" />
                                            <?php esc_html_e( 'Anular la cantidad de productos para precio mayorista individuales cuando se alcanza el umbral de carrito', 'woo-config' ); ?>
                                        </label>
                                        <p class="description" style="margin-top: 6px;">
                                            <?php esc_html_e( 'Si está marcado, cuando el carrito sume la cantidad de productos indicada arriba, no importan las reglas mínimas por producto: se activa el precio mayorista para todos los productos del carro que tengan precio mayorista asignado.', 'woo-config' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width: 260px; font-weight: 600; padding: 12px 0;">
                                        <label for="wooconfig_wholesale_modal_title"><?php esc_html_e( 'Título de la ventana de condiciones (i)', 'woo-config' ); ?></label>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <input name="wooconfig_wholesale_modal_title" type="text" id="wooconfig_wholesale_modal_title" value="<?php echo esc_attr( $wholesale_modal_title ); ?>" class="large-text" style="padding: 6px 10px; font-size: 14px;" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width: 260px; font-weight: 600; padding: 12px 0;">
                                        <label for="wooconfig_wholesale_modal_content"><?php esc_html_e( 'Contenido explicativo del Lightbox (i)', 'woo-config' ); ?></label>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <textarea name="wooconfig_wholesale_modal_content" id="wooconfig_wholesale_modal_content" rows="6" class="large-text" style="padding: 8px; font-size: 13px; font-family: monospace;"><?php echo esc_textarea( $wholesale_modal_content ); ?></textarea>
                                        <p class="description" style="margin-top: 6px;">
                                            <?php esc_html_e( 'Texto y formato HTML que verá el cliente al hacer clic sobre el icono informativo (i) junto al precio mayorista en la ficha o catálogo.', 'woo-config' ); ?>
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sección 3: Pagos de Anticipo / Abonos -->
                <div class="postbox" style="border-radius: 8px; border: 1px solid #c3c4c7; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px;">
                    <div class="postbox-header" style="padding: 14px 18px; border-bottom: 1px solid #dcdcde; background: #fafafa;">
                        <h2 style="font-size: 15px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                            <span>💳</span> <?php esc_html_e( 'Pagos de Anticipo / Abonos para Confección', 'woo-config' ); ?>
                        </h2>
                    </div>
                    <div class="inside" style="padding: 20px 22px; margin: 0;">
                        <p style="font-size: 13px; color: #50575e; margin: 0 0 16px;">
                            <?php esc_html_e( 'Permite que los clientes inicien la confección de su pedido abonando un porcentaje inicial hoy, pagando el saldo restante antes del despacho o retiro.', 'woo-config' ); ?>
                        </p>
                        <table class="form-table" role="presentation" style="margin: 0;">
                            <tbody>
                                <tr>
                                    <th scope="row" style="width: 240px; font-weight: 600; padding: 12px 0;">
                                        <?php esc_html_e( 'Habilitar abonos', 'woo-config' ); ?>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <label for="wooconfig_deposit_enabled" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">
                                            <input name="wooconfig_deposit_enabled" type="checkbox" id="wooconfig_deposit_enabled" value="yes" <?php checked( 'yes' === $deposit_enabled ); ?> style="margin: 0;" />
                                            <?php esc_html_e( 'Activar pagos de anticipo / abonos en la tienda', 'woo-config' ); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width: 240px; font-weight: 600; padding: 12px 0;">
                                        <label for="wooconfig_deposit_percent"><?php esc_html_e( 'Porcentaje de anticipo (%)', 'woo-config' ); ?></label>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <input name="wooconfig_deposit_percent" type="number" id="wooconfig_deposit_percent" value="<?php echo esc_attr( $deposit_percent ); ?>" min="1" max="99" step="1" style="width: 90px; padding: 6px 10px; font-size: 14px; font-weight: 700;" />
                                            <span style="font-weight: 700; font-size: 15px;">%</span>
                                        </div>
                                        <p class="description" style="margin-top: 6px;">
                                            <?php esc_html_e( 'Porcentaje del valor total de confección a pagar en el checkout inicial (ej. 50%). El saldo restante se cobrará al finalizar la confección.', 'woo-config' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width: 240px; font-weight: 600; padding: 12px 0;">
                                        <?php esc_html_e( 'Opción de Pago 100%', 'woo-config' ); ?>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <label for="wooconfig_allow_full_payment" style="display: inline-flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                                            <input name="wooconfig_allow_full_payment" type="checkbox" id="wooconfig_allow_full_payment" value="yes" <?php checked( 'yes' === $allow_full ); ?> style="margin: 0;" />
                                            <?php esc_html_e( 'Permitir al cliente optar por pagar el 100% total hoy si lo prefiere', 'woo-config' ); ?>
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sección 4: Notificación de Boceto y Medio de Contacto Preferido -->
                <div class="postbox" style="border-radius: 8px; border: 1px solid #c3c4c7; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px;">
                    <div class="postbox-header" style="padding: 14px 18px; border-bottom: 1px solid #dcdcde; background: #fafafa;">
                        <h2 style="font-size: 15px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                            <span>🎨</span> <?php esc_html_e( 'Gestión de Bocetos y Medio de Contacto Preferido', 'woo-config' ); ?>
                        </h2>
                    </div>
                    <div class="inside" style="padding: 20px 22px; margin: 0;">
                        <p style="font-size: 13px; color: #50575e; margin: 0 0 16px;">
                            <?php esc_html_e( 'Configura la notificación al cliente informando sobre el plazo de 24 horas para la entrega de su boceto tras el pago inicial, y habilita el campo de selección de medio de contacto en el checkout.', 'woo-config' ); ?>
                        </p>
                        <table class="form-table" role="presentation" style="margin: 0;">
                            <tbody>
                                <tr>
                                    <th scope="row" style="width: 240px; font-weight: 600; padding: 12px 0;">
                                        <?php esc_html_e( 'Aviso de boceto en 24h', 'woo-config' ); ?>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <label for="wooconfig_sketch_notice_enabled" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">
                                            <input name="wooconfig_sketch_notice_enabled" type="checkbox" id="wooconfig_sketch_notice_enabled" value="yes" <?php checked( 'yes' === $sketch_notice_enabled ); ?> style="margin: 0;" />
                                            <?php esc_html_e( 'Mostrar mensaje de aviso sobre entrega de boceto en 24 horas (en confirmación de compra y correos)', 'woo-config' ); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width: 240px; font-weight: 600; padding: 12px 0;">
                                        <label for="wooconfig_sketch_notice_text"><?php esc_html_e( 'Texto del aviso del boceto', 'woo-config' ); ?></label>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <textarea name="wooconfig_sketch_notice_text" id="wooconfig_sketch_notice_text" rows="3" class="large-text" style="padding: 8px; font-size: 13px;"><?php echo esc_textarea( $sketch_notice_text ); ?></textarea>
                                        <p class="description" style="margin-top: 6px;">
                                            <?php esc_html_e( 'Mensaje explicativo que se mostrará al cliente en la página de pedido completado y en el email de confirmación.', 'woo-config' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width: 240px; font-weight: 600; padding: 12px 0;">
                                        <?php esc_html_e( 'Campo de contacto preferido', 'woo-config' ); ?>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <label for="wooconfig_preferred_contact_enabled" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">
                                            <input name="wooconfig_preferred_contact_enabled" type="checkbox" id="wooconfig_preferred_contact_enabled" value="yes" <?php checked( 'yes' === $preferred_contact_enabled ); ?> style="margin: 0;" />
                                            <?php esc_html_e( 'Activar campo desplegable de medio de contacto preferido en la página de checkout', 'woo-config' ); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width: 240px; font-weight: 600; padding: 12px 0;">
                                        <label for="wooconfig_preferred_contact_label"><?php esc_html_e( 'Título del campo en Checkout', 'woo-config' ); ?></label>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <input name="wooconfig_preferred_contact_label" type="text" id="wooconfig_preferred_contact_label" value="<?php echo esc_attr( $preferred_contact_label ); ?>" class="large-text" style="padding: 6px 10px; font-size: 14px;" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width: 240px; font-weight: 600; padding: 12px 0;">
                                        <label for="wooconfig_preferred_contact_options"><?php esc_html_e( 'Opciones de contacto', 'woo-config' ); ?></label>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <input name="wooconfig_preferred_contact_options" type="text" id="wooconfig_preferred_contact_options" value="<?php echo esc_attr( $preferred_contact_options ); ?>" class="large-text" style="padding: 6px 10px; font-size: 14px;" />
                                        <p class="description" style="margin-top: 6px;">
                                            <?php esc_html_e( 'Ingresa las opciones separadas por comas (ej. WhatsApp, Correo electrónico, Llamada telefónica).', 'woo-config' ); ?>
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="margin-top: 25px;">
                    <button type="submit" name="wooconfig_save_settings" class="button button-primary button-hero" style="font-weight: 700; padding: 6px 24px;">
                        <?php esc_html_e( 'Guardar Ajustes', 'woo-config' ); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    public static function display_permission_admin_notices() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $permission_errors = WC_Config_Uploader::check_upload_directory_permissions();
        if ( ! empty( $permission_errors ) ) {
            ?>
            <div class="notice notice-error is-dismissible" style="border-left-color: #dc2626; padding: 12px 16px;">
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <span style="font-size: 24px; line-height: 1;">🚨</span>
                    <div>
                        <strong style="font-size: 14px; color: #b91c1c; display: block; margin-bottom: 6px;">
                            <?php esc_html_e( 'Alerta de Seguridad y Permisos - WooConfig', 'woo-config' ); ?>
                        </strong>
                        <p style="margin: 0 0 8px; font-size: 13px; color: #333; line-height: 1.4;">
                            <?php esc_html_e( 'Se detectaron problemas con los permisos en las carpetas de subida de archivos del configurador. Los clientes no podrán adjuntar archivos ni completar pedidos si no se corrigen estos permisos:', 'woo-config' ); ?>
                        </p>
                        <ul style="margin: 5px 0 10px 20px; list-style-type: disc; color: #991b1b; font-size: 13px;">
                            <?php foreach ( $permission_errors as $err ) : ?>
                                <li style="margin-bottom: 4px;"><?php echo wp_kses_post( $err ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p style="margin: 0; font-size: 12px; color: #555; background: #fff; padding: 6px 10px; border-radius: 4px; border: 1px solid #e5e7eb; display: inline-block;">
                            💡 <?php esc_html_e( 'Solución recomendada: Ejecuta en la terminal de tu servidor <code>chmod -R 755 wp-content/uploads/woo-config-uploads</code> o asigna el usuario web correspondiente (ej. <code>chown -R www-data:www-data wp-content/uploads/woo-config-uploads</code>).', 'woo-config' ); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    public static function add_product_tab( $tabs ) {
        $tabs['wooconfig_tab'] = array(
            'label'    => __( 'Configurador WooConfig', 'woo-config' ),
            'target'   => 'wooconfig_product_data',
            'class'    => array( 'show_if_simple', 'show_if_variable' ),
            'priority' => 65,
        );
        return $tabs;
    }

    public static function render_product_tab_panel() {
        global $post;
        ?>
        <div id="wooconfig_product_data" class="panel woocommerce_options_panel hidden">
            <!-- Banner informativo de gestión global -->
            <div style="margin: 15px; padding: 14px 18px; background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap;">
                <div>
                    <strong style="color: #166534; font-size: 13px; display: block; margin-bottom: 4px;">
                        ℹ️ <?php esc_html_e( 'Gestión Global de Pedido Mínimo y Anticipos', 'woo-config' ); ?>
                    </strong>
                    <span style="color: #14532d; font-size: 12px; line-height: 1.4; display: block;">
                        <?php esc_html_e( 'La cantidad mínima de prendas y los pagos de anticipo (abonos) ahora se gestionan de forma centralizada para toda la tienda.', 'woo-config' ); ?>
                    </span>
                </div>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wooconfig-settings' ) ); ?>" class="button button-primary" target="_blank" style="white-space: nowrap;">
                    <?php esc_html_e( 'Configurar en Uxcribe.cl > WooConfig →', 'woo-config' ); ?>
                </a>
            </div>

            <div class="options_group">
                <?php
                woocommerce_wp_select( array(
                    'id'          => '_wooconfig_type',
                    'label'       => __( 'Tipo de Configurador', 'woo-config' ),
                    'description' => __( 'Selecciona el modo de personalización para este producto.', 'woo-config' ),
                    'desc_tip'    => true,
                    'options'     => array(
                        'disabled'       => __( 'Desactivado (Producto Estándar)', 'woo-config' ),
                        'full_sublimado' => __( 'Full Sublimado (Deportivo: Dorsal + Nombre + Talla + Diseño)', 'woo-config' ),
                        'importado'      => __( 'Línea Importada (Stock/Color + Talla + Personalización)', 'woo-config' ),
                    ),
                ) );

                woocommerce_wp_text_input( array(
                    'id'          => '_wooconfig_btn_text',
                    'label'       => __( 'Texto del Botón', 'woo-config' ),
                    'description' => __( 'Texto mostrado en el botón que abre el modal.', 'woo-config' ),
                    'desc_tip'    => true,
                    'placeholder' => __( 'Configurar y comprar este producto >>', 'woo-config' ),
                ) );

                woocommerce_wp_text_input( array(
                    'id'          => '_wooconfig_size_guide_url',
                    'label'       => __( 'URL Guía de Tallas (Opcional)', 'woo-config' ),
                    'description' => __( 'Enlace a la página o PDF de la tabla de medidas.', 'woo-config' ),
                    'desc_tip'    => true,
                ) );

                woocommerce_wp_text_input( array(
                    'id'          => '_wooconfig_size_guide_image',
                    'label'       => __( 'URL Imagen Guía de Tallas (Opcional)', 'woo-config' ),
                    'description' => __( 'Imagen de la tabla de tallas que se abrirá en popup al presionar "Guía de tallas".', 'woo-config' ),
                    'desc_tip'    => true,
                ) );

                woocommerce_wp_checkbox( array(
                    'id'          => '_wooconfig_show_widget_size_guide',
                    'label'       => __( 'Guía de Tallas en Widget', 'woo-config' ),
                    'description' => __( 'Mostrar el botón de guía de tallas dentro del cuadro de configuración de compra (puedes desactivarlo si prefieres posicionarlo manualmente con el bloque de Gutenberg o shortcode).', 'woo-config' ),
                    'default'     => 'yes',
                ) );
                ?>
            </div>

            <!-- Grupo: Tarifa Mayorista por Producto -->
            <?php if ( WC_Config_Wholesale::is_enabled() ) : ?>
            <div class="options_group">
                <h4 style="margin: 15px; font-size: 14px; font-weight: 700; color: #1e293b;">
                    🏷️ <?php esc_html_e( 'Precio Mayorista y Reglas de Producto', 'woo-config' ); ?>
                </h4>
                <p style="margin: 0 15px 15px; font-size: 12px; color: #64748b;">
                    <?php esc_html_e( 'Configura la tarifa mayorista para este producto y el mínimo de unidades necesario para activarla individualmente. Si el carrito del cliente suma 50 o más unidades en total (o el umbral global), se activará automáticamente ignorando este mínimo si la regla de override está activa.', 'woo-config' ); ?>
                </p>

                <?php
                woocommerce_wp_text_input( array(
                    'id'          => '_wooconfig_wholesale_price',
                    'label'       => __( 'Precio Mayorista ($)', 'woo-config' ),
                    'placeholder' => __( 'Ej: 14990 (dejar vacío si no aplica)', 'woo-config' ),
                    'description' => __( 'Precio base por unidad al comprar por mayor. Si se deja en blanco, el producto mantendrá su precio regular.', 'woo-config' ),
                    'desc_tip'    => true,
                    'type'        => 'text',
                    'data_type'   => 'price',
                ) );

                woocommerce_wp_text_input( array(
                    'id'          => '_wooconfig_wholesale_min_qty',
                    'label'       => __( 'Mínimo de unidades (Individual)', 'woo-config' ),
                    'placeholder' => '1',
                    'description' => __( 'Cantidad mínima de unidades requerida de este producto para activar su precio mayorista independientemente del carrito global. Por defecto: 1.', 'woo-config' ),
                    'desc_tip'    => true,
                    'type'        => 'number',
                    'custom_attributes' => array(
                        'step' => '1',
                        'min'  => '1',
                    ),
                ) );
                ?>
            </div>
            <?php else : ?>
            <div class="options_group">
                <div style="margin: 15px; padding: 12px 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                    <span style="font-size: 13px; color: #991b1b;">
                        🏷️ <strong><?php esc_html_e( 'Módulo de Precios Mayoristas Desactivado:', 'woo-config' ); ?></strong>
                        <?php esc_html_e( 'La funcionalidad de precios por mayor se encuentra desactivada globalmente en los ajustes de WooConfig.', 'woo-config' ); ?>
                    </span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wooconfig-settings' ) ); ?>" class="button button-small" target="_blank">
                        <?php esc_html_e( 'Activar en Ajustes →', 'woo-config' ); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <div class="options_group">
                <h4 style="margin: 15px; font-size: 14px; font-weight: 700; color: #333;">
                    <?php esc_html_e( 'Adicionales de Confección (Add-ons / Extras)', 'woo-config' ); ?>
                </h4>
                <p style="margin: 0 15px 15px; font-size: 12px; color: #666;">
                    <?php esc_html_e( 'Activa y fija el precio de los adicionales disponibles para este producto (ej. Cuello polo, Manga larga).', 'woo-config' ); ?>
                </p>

                <?php
                $adicionales = get_post_meta( $post->ID, '_wooconfig_adicionales', true );
                if ( ! is_array( $adicionales ) ) {
                    $adicionales = array(
                        'cuello_polo' => array( 'enabled' => 'yes', 'price' => 2500, 'label' => __( 'Cuello piqué', 'woo-config' ) ),
                        'manga_larga' => array( 'enabled' => 'yes', 'price' => 2500, 'label' => __( 'Manga larga', 'woo-config' ) ),
                    );
                }

                $cuello_enabled = isset( $adicionales['cuello_polo']['enabled'] ) && 'yes' === $adicionales['cuello_polo']['enabled'];
                $cuello_price   = isset( $adicionales['cuello_polo']['price'] ) ? (float) $adicionales['cuello_polo']['price'] : 2500;
                $cuello_label   = ! empty( $adicionales['cuello_polo']['label'] ) ? $adicionales['cuello_polo']['label'] : __( 'Cuello piqué', 'woo-config' );
                if ( 'Cuello polo' === $cuello_label ) {
                    $cuello_label = __( 'Cuello piqué', 'woo-config' );
                }

                $manga_enabled  = isset( $adicionales['manga_larga']['enabled'] ) && 'yes' === $adicionales['manga_larga']['enabled'];
                $manga_price    = isset( $adicionales['manga_larga']['price'] ) ? (float) $adicionales['manga_larga']['price'] : 2500;
                $manga_label    = ! empty( $adicionales['manga_larga']['label'] ) ? $adicionales['manga_larga']['label'] : __( 'Manga larga', 'woo-config' );
                ?>

                <div class="wooconfig-admin-box" style="padding: 14px 18px; background: #fafafa; border: 1px solid #e5e5e5; margin: 0 15px 15px; border-radius: 6px;">
                    <strong style="display: block; margin-bottom: 8px; font-size: 13px; color: #111;"><?php esc_html_e( '1. Cuello Piqué', 'woo-config' ); ?></strong>
                    <div style="margin: 8px 0; display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="_wooconfig_adicional_cuello_enabled" name="_wooconfig_adicional_cuello_enabled" value="yes" <?php checked( $cuello_enabled, true ); ?> style="margin: 0 !important; float: none !important;" />
                        <label for="_wooconfig_adicional_cuello_enabled" style="float: none !important; margin: 0 !important; width: auto !important; font-weight: 600; cursor: pointer; display: inline !important;">
                            <?php esc_html_e( 'Habilitar Cuello Piqué para este producto', 'woo-config' ); ?>
                        </label>
                    </div>
                    <div style="margin: 10px 0 4px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span style="font-size: 13px; color: #333; font-weight: 500;"><?php esc_html_e( 'Nombre / Etiqueta:', 'woo-config' ); ?></span>
                        <input type="text" name="_wooconfig_adicional_cuello_label" value="<?php echo esc_attr( $cuello_label ); ?>" style="width: 180px; margin: 0 !important; float: none !important;" />
                        <span style="font-size: 13px; color: #333; font-weight: 500; margin-left: 10px;"><?php esc_html_e( 'Precio adicional ($):', 'woo-config' ); ?></span>
                        <input type="number" step="100" min="0" name="_wooconfig_adicional_cuello_price" value="<?php echo esc_attr( $cuello_price ); ?>" style="width: 100px; margin: 0 !important; float: none !important;" />
                    </div>
                </div>

                <div class="wooconfig-admin-box" style="padding: 14px 18px; background: #fafafa; border: 1px solid #e5e5e5; margin: 0 15px 15px; border-radius: 6px;">
                    <strong style="display: block; margin-bottom: 8px; font-size: 13px; color: #111;"><?php esc_html_e( '2. Manga Larga', 'woo-config' ); ?></strong>
                    <div style="margin: 8px 0; display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="_wooconfig_adicional_manga_enabled" name="_wooconfig_adicional_manga_enabled" value="yes" <?php checked( $manga_enabled, true ); ?> style="margin: 0 !important; float: none !important;" />
                        <label for="_wooconfig_adicional_manga_enabled" style="float: none !important; margin: 0 !important; width: auto !important; font-weight: 600; cursor: pointer; display: inline !important;">
                            <?php esc_html_e( 'Habilitar Manga Larga para este producto', 'woo-config' ); ?>
                        </label>
                    </div>
                    <div style="margin: 10px 0 4px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span style="font-size: 13px; color: #333; font-weight: 500;"><?php esc_html_e( 'Nombre / Etiqueta:', 'woo-config' ); ?></span>
                        <input type="text" name="_wooconfig_adicional_manga_label" value="<?php echo esc_attr( $manga_label ); ?>" style="width: 180px; margin: 0 !important; float: none !important;" />
                        <span style="font-size: 13px; color: #333; font-weight: 500; margin-left: 10px;"><?php esc_html_e( 'Precio adicional ($):', 'woo-config' ); ?></span>
                        <input type="number" step="100" min="0" name="_wooconfig_adicional_manga_price" value="<?php echo esc_attr( $manga_price ); ?>" style="width: 100px; margin: 0 !important; float: none !important;" />
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function save_product_meta( $post_id ) {
        $type = isset( $_POST['_wooconfig_type'] ) ? sanitize_text_field( $_POST['_wooconfig_type'] ) : 'disabled';
        update_post_meta( $post_id, '_wooconfig_type', $type );

        if ( isset( $_POST['_wooconfig_btn_text'] ) ) {
            update_post_meta( $post_id, '_wooconfig_btn_text', sanitize_text_field( $_POST['_wooconfig_btn_text'] ) );
        }

        if ( isset( $_POST['_wooconfig_size_guide_url'] ) ) {
            update_post_meta( $post_id, '_wooconfig_size_guide_url', esc_url_raw( $_POST['_wooconfig_size_guide_url'] ) );
        }

        if ( isset( $_POST['_wooconfig_size_guide_image'] ) ) {
            update_post_meta( $post_id, '_wooconfig_size_guide_image', esc_url_raw( $_POST['_wooconfig_size_guide_image'] ) );
        }

        $show_widget_guide = isset( $_POST['_wooconfig_show_widget_size_guide'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_wooconfig_show_widget_size_guide', $show_widget_guide );

        // Save Wholesale Product Meta
        if ( isset( $_POST['_wooconfig_wholesale_price'] ) ) {
            $ws_raw = sanitize_text_field( $_POST['_wooconfig_wholesale_price'] );
            if ( '' === $ws_raw ) {
                delete_post_meta( $post_id, '_wooconfig_wholesale_price' );
            } else {
                update_post_meta( $post_id, '_wooconfig_wholesale_price', wc_format_decimal( $ws_raw ) );
            }
        }

        if ( isset( $_POST['_wooconfig_wholesale_min_qty'] ) ) {
            $ws_min = max( 1, absint( $_POST['_wooconfig_wholesale_min_qty'] ) );
            update_post_meta( $post_id, '_wooconfig_wholesale_min_qty', $ws_min );
        }

        // Save Adicionales
        $adicionales = array();
        
        $cuello_enabled = isset( $_POST['_wooconfig_adicional_cuello_enabled'] ) ? 'yes' : 'no';
        $cuello_label   = isset( $_POST['_wooconfig_adicional_cuello_label'] ) ? sanitize_text_field( $_POST['_wooconfig_adicional_cuello_label'] ) : 'Cuello piqué';
        $cuello_price   = isset( $_POST['_wooconfig_adicional_cuello_price'] ) ? (float) $_POST['_wooconfig_adicional_cuello_price'] : 2500;
        
        $adicionales['cuello_polo'] = array(
            'id'      => 'cuello_polo',
            'enabled' => $cuello_enabled,
            'label'   => $cuello_label,
            'price'   => $cuello_price,
        );

        $manga_enabled = isset( $_POST['_wooconfig_adicional_manga_enabled'] ) ? 'yes' : 'no';
        $manga_label   = isset( $_POST['_wooconfig_adicional_manga_label'] ) ? sanitize_text_field( $_POST['_wooconfig_adicional_manga_label'] ) : 'Manga larga';
        $manga_price   = isset( $_POST['_wooconfig_adicional_manga_price'] ) ? (float) $_POST['_wooconfig_adicional_manga_price'] : 2500;

        $adicionales['manga_larga'] = array(
            'id'      => 'manga_larga',
            'enabled' => $manga_enabled,
            'label'   => $manga_label,
            'price'   => $manga_price,
        );

        update_post_meta( $post_id, '_wooconfig_adicionales', $adicionales );
    }

    public static function set_new_product_defaults( $product_id ) {
        if ( '' === get_post_meta( $product_id, '_wooconfig_deposit_enabled', true ) ) {
            update_post_meta( $product_id, '_wooconfig_deposit_enabled', 'yes' );
        }
        if ( '' === get_post_meta( $product_id, '_wooconfig_deposit_percent', true ) ) {
            update_post_meta( $product_id, '_wooconfig_deposit_percent', 50 );
        }
        if ( '' === get_post_meta( $product_id, '_wooconfig_allow_full_payment', true ) ) {
            update_post_meta( $product_id, '_wooconfig_allow_full_payment', 'yes' );
        }
    }

    public static function register_rest_fields() {
        $fields = array(
            'wooconfig_type'               => array(
                'meta_key' => '_wooconfig_type',
                'type'     => 'string',
                'sanitize' => function( $val ) {
                    $allowed = array( 'disabled', 'full_sublimado', 'importado' );
                    return in_array( $val, $allowed, true ) ? $val : 'disabled';
                },
            ),
            'wooconfig_min_units'          => array(
                'meta_key' => '_wooconfig_min_units',
                'type'     => 'integer',
                'sanitize' => function( $val ) {
                    return '' !== $val ? max( 1, absint( $val ) ) : '';
                },
            ),
            'wooconfig_btn_text'           => array(
                'meta_key' => '_wooconfig_btn_text',
                'type'     => 'string',
                'sanitize' => 'sanitize_text_field',
            ),
            'wooconfig_size_guide_url'     => array(
                'meta_key' => '_wooconfig_size_guide_url',
                'type'     => 'string',
                'sanitize' => 'esc_url_raw',
            ),
            'wooconfig_size_guide_image'   => array(
                'meta_key' => '_wooconfig_size_guide_image',
                'type'     => 'string',
                'sanitize' => 'esc_url_raw',
            ),
            'wooconfig_adicionales'        => array(
                'meta_key' => '_wooconfig_adicionales',
                'type'     => 'object',
                'sanitize' => function( $val ) {
                    return is_array( $val ) ? $val : array();
                },
            ),
            'wooconfig_deposit_enabled'    => array(
                'meta_key' => '_wooconfig_deposit_enabled',
                'type'     => 'string',
                'sanitize' => function( $val ) {
                    return 'yes' === $val ? 'yes' : 'no';
                },
            ),
            'wooconfig_deposit_percent'    => array(
                'meta_key' => '_wooconfig_deposit_percent',
                'type'     => 'integer',
                'sanitize' => function( $val ) {
                    $pct = absint( $val );
                    return ( $pct >= 1 && $pct <= 99 ) ? $pct : 50;
                },
            ),
            'wooconfig_allow_full_payment' => array(
                'meta_key' => '_wooconfig_allow_full_payment',
                'type'     => 'string',
                'sanitize' => function( $val ) {
                    return 'yes' === $val ? 'yes' : 'no';
                },
            ),
        );

        foreach ( $fields as $rest_field => $config ) {
            $meta_key  = $config['meta_key'];
            $sanitizer = $config['sanitize'];

            register_rest_field( 'product', $rest_field, array(
                'get_callback' => function( $product_arr ) use ( $meta_key ) {
                    return get_post_meta( $product_arr['id'], $meta_key, true );
                },
                'update_callback' => function( $value, $product_obj ) use ( $meta_key, $sanitizer ) {
                    $clean_value = is_callable( $sanitizer ) ? call_user_func( $sanitizer, $value ) : $value;
                    update_post_meta( $product_obj->get_id(), $meta_key, $clean_value );
                    return true;
                },
                'schema' => array(
                    'description' => 'WooConfig meta field ' . $rest_field,
                    'type'        => $config['type'],
                    'context'     => array( 'view', 'edit' ),
                ),
            ) );
        }
    }
}

WC_Config_Admin::init();
