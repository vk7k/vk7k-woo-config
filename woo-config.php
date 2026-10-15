<?php
/**
 * Plugin Name: WooConfig - Configurador de Ropa Deportiva y Corporativa
 * Plugin URI:  http://www.uxcribe.cl
 * Description: Configurador por pasos para pedidos mayoristas de indumentaria deportiva (Full Sublimación) y corporativa (Línea Importada) con soporte nativo para variaciones, inventario, subida de archivos y ficha de producción.
 * Version:     1.5.2
 * Author:      uxcribe.cl
 * Text Domain: woo-config
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WOOCONFIG_VERSION', '1.5.2' );
define( 'WOOCONFIG_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOOCONFIG_URL', plugin_dir_url( __FILE__ ) );
define( 'WOOCONFIG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

final class WooConfig {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        if ( file_exists( WOOCONFIG_PATH . 'includes/class-uxcribe-admin-menu.php' ) ) {
            require_once WOOCONFIG_PATH . 'includes/class-uxcribe-admin-menu.php';
        }
        require_once WOOCONFIG_PATH . 'includes/class-wc-config-admin.php';
        require_once WOOCONFIG_PATH . 'includes/class-wc-config-size-guide.php';
        require_once WOOCONFIG_PATH . 'includes/class-wc-config-uploader.php';
        require_once WOOCONFIG_PATH . 'includes/class-wc-config-cart.php';
        require_once WOOCONFIG_PATH . 'includes/class-wc-config-wholesale.php';
        require_once WOOCONFIG_PATH . 'includes/class-wc-config-order.php';
        require_once WOOCONFIG_PATH . 'includes/class-wc-config-pdf.php';
    }

    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Shortcode support for FSE / Page Builders
        add_shortcode( 'wooconfig_widget', array( $this, 'render_shortcode_widget' ) );

        // Single product hooks (Determined dynamically for Classic vs FSE themes)
        add_action( 'wp', array( $this, 'setup_product_rendering_hooks' ) );

        // Block Theme Filter (Gutenberg / FSE - replaces add-to-cart block on single product)
        add_filter( 'render_block', array( $this, 'filter_block_render' ), 10, 2 );

        // Footer modal
        add_action( 'wp_footer', array( $this, 'render_modal_template' ) );

        // WP-Cron garbage collection
        add_action( 'wooconfig_daily_cleanup', array( $this, 'do_daily_cleanup' ) );
    }

    public function do_daily_cleanup() {
        WC_Config_Uploader::clean_temp_files();

        $retention_days = absint( get_option( 'wooconfig_retention_days', 0 ) );
        if ( $retention_days > 0 ) {
            WC_Config_Uploader::clean_old_order_files( $retention_days );
        }
    }

    public function on_plugins_loaded() {
        load_plugin_textdomain( 'woo-config', false, dirname( WOOCONFIG_PLUGIN_BASENAME ) . '/languages' );

        // Declare HPOS compatibility
        add_action( 'before_woocommerce_init', function() {
            if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
            }
        });
    }

    public static function activate() {
        $upload_dir = wp_upload_dir();
        $base_dir   = $upload_dir['basedir'] . '/woo-config-uploads';
        $temp_dir   = $base_dir . '/temp';
        $orders_dir = $base_dir . '/orders';

        foreach ( array( $base_dir, $temp_dir, $orders_dir ) as $dir ) {
            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );
            }
            if ( ! file_exists( $dir . '/index.php' ) ) {
                file_put_contents( $dir . '/index.php', '<?php // Silence is golden' );
            }
            if ( ! file_exists( $dir . '/.htaccess' ) ) {
                $htaccess_content = "# Disable script execution\n<FilesMatch \"\.(php|phtml|php3|php4|php5|php7|phps|pl|py|cgi|sh)$\">\nOrder Deny,Allow\nDeny from all\n</FilesMatch>\nOptions -Indexes\n";
                file_put_contents( $dir . '/.htaccess', $htaccess_content );
            }
        }

        if ( ! wp_next_scheduled( 'wooconfig_daily_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'wooconfig_daily_cleanup' );
        }

        if ( false === get_option( 'wooconfig_min_units' ) ) {
            update_option( 'wooconfig_min_units', 1 );
        }
        if ( false === get_option( 'wooconfig_deposit_enabled' ) ) {
            update_option( 'wooconfig_deposit_enabled', 'yes' );
        }
        if ( false === get_option( 'wooconfig_deposit_percent' ) ) {
            update_option( 'wooconfig_deposit_percent', 50 );
        }
        if ( false === get_option( 'wooconfig_allow_full_payment' ) ) {
            update_option( 'wooconfig_allow_full_payment', 'yes' );
        }
        if ( false === get_option( 'wooconfig_max_file_size_mb' ) ) {
            update_option( 'wooconfig_max_file_size_mb', 20 );
        }
        if ( false === get_option( 'wooconfig_auto_delete_order_files' ) ) {
            update_option( 'wooconfig_auto_delete_order_files', 'yes' );
        }
        if ( false === get_option( 'wooconfig_retention_days' ) ) {
            update_option( 'wooconfig_retention_days', 0 );
        }
        if ( false === get_option( 'wooconfig_wholesale_enabled' ) ) {
            update_option( 'wooconfig_wholesale_enabled', 'yes' );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'wooconfig_daily_cleanup' );
    }

    public function enqueue_frontend_assets() {
        if ( is_cart() || is_checkout() ) {
            wp_enqueue_style(
                'wooconfig-modal-css',
                WOOCONFIG_URL . 'assets/css/modal.css',
                array(),
                WOOCONFIG_VERSION
            );
            return;
        }

        if ( ! is_product() && ! is_singular( 'product' ) ) {
            return;
        }

        global $post;
        $product = wc_get_product( $post->ID );
        if ( ! $product ) {
            return;
        }

        $config_type = get_post_meta( $product->get_id(), '_wooconfig_type', true );
        if ( empty( $config_type ) ) {
            $config_type = 'disabled';
        }

        wp_enqueue_style(
            'wooconfig-modal-css',
            WOOCONFIG_URL . 'assets/css/modal.css',
            array(),
            WOOCONFIG_VERSION
        );

        wp_enqueue_style(
            'wooconfig-unified-css',
            WOOCONFIG_URL . 'assets/css/unified-flow.css',
            array( 'wooconfig-modal-css' ),
            WOOCONFIG_VERSION
        );

        wp_enqueue_script(
            'wooconfig-frontend-js',
            WOOCONFIG_URL . 'assets/js/configurator.js',
            array( 'jquery' ),
            WOOCONFIG_VERSION,
            true
        );

        $variations_data  = array();
        $available_sizes  = array();
        $available_colors = array();

        if ( $product->is_type( 'variable' ) ) {
            $variations = $product->get_available_variations();
            foreach ( $variations as $var ) {
                $variation_obj = wc_get_product( $var['variation_id'] );
                if ( ! $variation_obj ) continue;

                $attributes = $var['attributes'];
                $size_val   = '';
                $color_val  = '';

                foreach ( $attributes as $attr_key => $attr_val ) {
                    if ( false !== stripos( $attr_key, 'talla' ) || false !== stripos( $attr_key, 'size' ) || false !== stripos( $attr_key, 'pa_talla' ) ) {
                        $size_val = $attr_val;
                    } elseif ( false !== stripos( $attr_key, 'color' ) || false !== stripos( $attr_key, 'pa_color' ) ) {
                        $color_val = $attr_val;
                    }
                }

                if ( empty( $size_val ) && ! empty( $attributes ) ) {
                    $keys = array_keys( $attributes );
                    $size_val = $attributes[$keys[0]];
                }

                if ( ! empty( $size_val ) && ! in_array( $size_val, $available_sizes ) ) {
                    $available_sizes[] = $size_val;
                }
                if ( ! empty( $color_val ) && ! in_array( $color_val, $available_colors ) ) {
                    $available_colors[] = $color_val;
                }

                $variations_data[] = array(
                    'variation_id'     => $var['variation_id'],
                    'attributes'       => $attributes,
                    'size'             => $size_val,
                    'color'            => $color_val,
                    'price'            => (float) $variation_obj->get_price(),
                    'price_html'       => wc_price( $variation_obj->get_price() ),
                    'is_in_stock'      => $variation_obj->is_in_stock(),
                    'max_qty'          => $variation_obj->get_max_purchase_quantity(),
                );
            }
        }

        if ( empty( $available_sizes ) ) {
            $product_attributes = $product->get_attributes();
            foreach ( $product_attributes as $attr_name => $attr_obj ) {
                if ( false !== stripos( $attr_name, 'talla' ) || false !== stripos( $attr_name, 'size' ) ) {
                    if ( is_object( $attr_obj ) && method_exists( $attr_obj, 'get_terms' ) ) {
                        $terms = $attr_obj->get_terms();
                        foreach ( $terms as $term ) {
                            $available_sizes[] = $term->name;
                        }
                    } elseif ( is_object( $attr_obj ) && method_exists( $attr_obj, 'get_options' ) ) {
                        $available_sizes = array_merge( $available_sizes, $attr_obj->get_options() );
                    }
                }
            }
        }

        if ( empty( $available_sizes ) ) {
            $available_sizes = array( '4', '6', '8', '10', '12', '14', '16', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL' );
        }

        // Normalize and sort sizes in logical progression (child sizes first, then adult sizes)
        $size_rank = array(
            '2' => 2, '4' => 4, '6' => 6, '8' => 8, '10' => 10, '12' => 12, '14' => 14, '16' => 16,
            'xs' => 20, 's' => 21, 'm' => 22, 'l' => 23, 'xl' => 24, 'xxl' => 25, '2xl' => 25, 'xxxl' => 26, '3xl' => 26, '4xl' => 27,
        );
        $clean_sizes = array();
        foreach ( $available_sizes as $sz ) {
            $sz_clean = trim( (string) $sz );
            if ( ! is_numeric( $sz_clean ) ) {
                $sz_clean = strtoupper( $sz_clean );
            }
            if ( ! empty( $sz_clean ) && ! in_array( $sz_clean, $clean_sizes ) ) {
                $clean_sizes[] = $sz_clean;
            }
        }
        usort( $clean_sizes, function( $a, $b ) use ( $size_rank ) {
            $rank_a = isset( $size_rank[ strtolower( $a ) ] ) ? $size_rank[ strtolower( $a ) ] : 99;
            $rank_b = isset( $size_rank[ strtolower( $b ) ] ) ? $size_rank[ strtolower( $b ) ] : 99;
            if ( $rank_a === $rank_b ) {
                return strnatcasecmp( $a, $b );
            }
            return $rank_a - $rank_b;
        });
        $available_sizes = $clean_sizes;
        $adicionales = get_post_meta( $product->get_id(), '_wooconfig_adicionales', true );
        if ( ! is_array( $adicionales ) ) {
            $adicionales = array(
                'cuello_polo' => array( 'id' => 'cuello_polo', 'enabled' => 'yes', 'label' => __( 'Cuello piqué', 'woo-config' ), 'price' => 2500 ),
                'manga_larga' => array( 'id' => 'manga_larga', 'enabled' => 'yes', 'label' => __( 'Manga larga', 'woo-config' ), 'price' => 2500 ),
            );
        } else {
            foreach ( $adicionales as $key => &$ad_item ) {
                if ( isset( $ad_item['label'] ) && false !== stripos( $ad_item['label'], 'polo' ) ) {
                    $ad_item['label'] = __( 'Cuello piqué', 'woo-config' );
                }
            }
            unset( $ad_item );
        }

        $active_adicionales = array();
        foreach ( $adicionales as $key => $ad ) {
            if ( isset( $ad['enabled'] ) && 'yes' === $ad['enabled'] ) {
                $active_adicionales[$key] = $ad;
            }
        }

        $size_guide_url = get_post_meta( $product->get_id(), '_wooconfig_size_guide_url', true );
        $size_guide_img = get_post_meta( $product->get_id(), '_wooconfig_size_guide_image', true );
        $btn_text       = get_post_meta( $product->get_id(), '_wooconfig_btn_text', true );
        if ( empty( $btn_text ) ) {
            $btn_text = __( 'Configurar y comprar este producto >>', 'woo-config' );
        }

        $cart_min_units  = (int) get_option( 'wooconfig_min_units', get_option( 'wooconfig_default_min_units', 1 ) );
        $cart_min_units  = max( 1, $cart_min_units );
        $deposit_enabled = get_option( 'wooconfig_deposit_enabled', 'yes' );
        $deposit_percent = (int) get_option( 'wooconfig_deposit_percent', 50 );
        $allow_full      = get_option( 'wooconfig_allow_full_payment', 'yes' );

        wp_localize_script( 'wooconfig-frontend-js', 'wooconfig_data', array(
            'ajax_url'           => admin_url( 'admin-ajax.php' ),
            'nonce'              => wp_create_nonce( 'wooconfig_nonce' ),
            'product_id'         => $product->get_id(),
            'product_title'      => $product->get_name(),
            'product_price'      => (float) $product->get_price(),
            'price_formatted'    => wc_price( $product->get_price() ),
            'btn_text'           => $btn_text,
            'config_type'        => $config_type,
            'min_units'          => 1,
            'cart_min_units'     => (int) $cart_min_units,
            'deposit_enabled'    => ( 'yes' === $deposit_enabled ),
            'deposit_percent'    => (int) $deposit_percent,
            'allow_full_payment' => ( 'yes' === $allow_full ),
            'currency_symbol'    => get_woocommerce_currency_symbol(),
            'adicionales'        => $active_adicionales,
            'variations'         => $variations_data,
            'available_sizes'    => array_values( array_unique( $available_sizes ) ),
            'available_colors'   => array_values( array_unique( $available_colors ) ),
            'size_guide_url'     => $size_guide_url,
            'size_guide_img'     => $size_guide_img,
            'max_file_size_mb'   => (int) get_option( 'wooconfig_max_file_size_mb', 20 ),
            'wholesale_enabled'  => WC_Config_Wholesale::is_enabled(),
            'wholesale_price'    => WC_Config_Wholesale::is_enabled() ? (float) get_post_meta( $product->get_id(), '_wooconfig_wholesale_price', true ) : 0,
            'wholesale_min_qty'  => max( 1, (int) get_post_meta( $product->get_id(), '_wooconfig_wholesale_min_qty', true ) ),
            'wholesale_cart_qty' => max( 1, (int) get_option( 'wooconfig_wholesale_cart_qty', 50 ) ),
            'wholesale_override' => ( 'yes' === get_option( 'wooconfig_wholesale_override_product_rules', 'yes' ) ),
            'help_images'        => array(
                'cantidades'  => WOOCONFIG_URL . 'assets/images/help-cantidades.svg',
                'adicionales' => WOOCONFIG_URL . 'assets/images/help-adicionales.svg',
                'diseno'      => WOOCONFIG_URL . 'assets/images/help-diseno.svg',
                'sponsors'    => WOOCONFIG_URL . 'assets/images/help-sponsors.svg',
                'jugadores'   => WOOCONFIG_URL . 'assets/images/help-jugadores.svg',
                'tabla'       => WOOCONFIG_URL . 'assets/images/help-tabla.svg',
            ),
            'cart_url'           => wc_get_cart_url(),
            'checkout_url'       => wc_get_checkout_url(),
            'i18n'               => array(
                'min_units_error' => __( 'Debes ingresar al menos 1 prenda.', 'woo-config' ),
                'select_size'     => __( 'Talla *', 'woo-config' ),
                'select_color'    => __( 'Color *', 'woo-config' ),
                'uploading'       => __( 'Subiendo archivo...', 'woo-config' ),
                'upload_error'    => __( 'Error al subir el archivo.', 'woo-config' ),
                'adding_to_cart'  => __( 'Añadiendo al carrito...', 'woo-config' ),
                'added_success'   => __( '¡Equipo añadido al carrito!', 'woo-config' ),
                'empty_desc_warn'      => __( 'Por favor ingresa una descripción para tu diseño.', 'woo-config' ),
                'empty_size_warn'      => __( 'Por favor selecciona la talla para todos los jugadores.', 'woo-config' ),
                'quick_no_files_error' => __( 'No es posible confeccionar tu pedido sin el detalle de las tallas, dorsales y apellidos. Por favor sube una foto o archivo con los datos de tu equipo (o utiliza el Personalizador paso a paso).', 'woo-config' ),
                'confirm_reset'        => __( '¿Estás seguro de que deseas limpiar y reiniciar la configuración?', 'woo-config' ),
                'deposit_badge'   => sprintf( __( 'Abono %d%% a pagar hoy', 'woo-config' ), (int) $deposit_percent ),
                'balance_badge'   => __( 'Saldo restante al terminar', 'woo-config' ),
                'next_adicionales' => __( 'Siguiente paso: Adicionales >>', 'woo-config' ),
                'next_design'      => __( 'Siguiente paso: Diseño >>', 'woo-config' ),
                'step3_design'     => __( 'Paso 3: Diseño', 'woo-config' ),
                'step2_design'     => __( 'Paso 2: Diseño', 'woo-config' ),
            ),
        ) );
    }

    public function enqueue_admin_assets( $hook ) {
        wp_enqueue_media();

        wp_enqueue_style(
            'wooconfig-admin-css',
            WOOCONFIG_URL . 'assets/css/admin.css',
            array(),
            WOOCONFIG_VERSION
        );

        wp_enqueue_script(
            'wooconfig-admin-js',
            WOOCONFIG_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            WOOCONFIG_VERSION,
            true
        );

        wp_localize_script( 'wooconfig-admin-js', 'wooconfig_admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wooconfig_admin_nonce' ),
            'i18n'     => array(
                'confirm_delete' => __( '¿Confirmas que deseas eliminar esta prenda del pedido?', 'woo-config' ),
                'saving'         => __( 'Guardando cambios...', 'woo-config' ),
                'saved'          => __( '¡Guardado correctamente!', 'woo-config' ),
                'error'          => __( 'Ocurrió un error al procesar la solicitud.', 'woo-config' ),
            ),
        ) );
    }

    public function setup_product_rendering_hooks() {
        if ( ! is_product() && ! is_singular( 'product' ) ) {
            return;
        }

        $is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

        // If it's a Classic Theme, attach to single product summary hook.
        // For Block (FSE) themes, we rely strictly on filter_block_render and [wooconfig_widget] shortcode.
        if ( ! $is_block_theme ) {
            $post_id = get_queried_object_id();
            if ( ! $post_id ) {
                global $post;
                $post_id = $post ? $post->ID : 0;
            }
            if ( $post_id ) {
                $config_type = get_post_meta( $post_id, '_wooconfig_type', true );
                if ( $config_type && 'disabled' !== $config_type ) {
                    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
                    add_action( 'woocommerce_single_product_summary', array( $this, 'render_configurator_button' ), 30 );
                }
            }
        }
    }

    private static $button_rendered = false;

    public function render_configurator_button() {
        if ( self::$button_rendered ) {
            return;
        }

        $main_id = get_queried_object_id();
        global $product;
        if ( ! $product ) return;

        if ( $main_id && (int) $product->get_id() !== (int) $main_id ) {
            return;
        }

        $config_type = get_post_meta( $product->get_id(), '_wooconfig_type', true );
        if ( ! $config_type || 'disabled' === $config_type ) {
            return;
        }

        self::$button_rendered = true;

        $widget_template = WOOCONFIG_PATH . 'templates/single-product-widget.php';
        if ( file_exists( $widget_template ) ) {
            include $widget_template;
        }
    }

    public function render_shortcode_widget( $atts ) {
        if ( self::$button_rendered ) {
            return '';
        }

        global $product;
        if ( ! $product ) {
            $post_id = get_the_ID();
            if ( ! $post_id ) {
                $post_id = get_queried_object_id();
            }
            if ( $post_id ) {
                $product = wc_get_product( $post_id );
            }
        }

        if ( ! $product ) {
            return '';
        }

        $config_type = get_post_meta( $product->get_id(), '_wooconfig_type', true );
        if ( ! $config_type || 'disabled' === $config_type ) {
            return '';
        }

        self::$button_rendered = true;
        $GLOBALS['product'] = $product;
        ob_start();
        $widget_template = WOOCONFIG_PATH . 'templates/single-product-widget.php';
        if ( file_exists( $widget_template ) ) {
            include $widget_template;
        }
        return ob_get_clean();
    }

    public function filter_block_render( $block_content, $block ) {
        if ( ! is_product() && ! is_singular( 'product' ) ) {
            return $block_content;
        }

        $target_blocks = array( 'woocommerce/add-to-cart-form', 'woocommerce/product-button' );
        if ( isset( $block['blockName'] ) && in_array( $block['blockName'], $target_blocks, true ) ) {
            if ( self::$button_rendered ) {
                return '';
            }

            $main_id = get_queried_object_id();
            if ( ! $main_id ) {
                global $post;
                $main_id = $post ? $post->ID : 0;
            }

            $product = wc_get_product( $main_id );
            if ( ! $product ) return $block_content;

            $config_type = get_post_meta( $product->get_id(), '_wooconfig_type', true );
            if ( ! $config_type || 'disabled' === $config_type ) {
                return $block_content;
            }

            self::$button_rendered = true;
            $GLOBALS['product'] = $product;
            ob_start();
            $widget_template = WOOCONFIG_PATH . 'templates/single-product-widget.php';
            if ( file_exists( $widget_template ) ) {
                include $widget_template;
            }
            return ob_get_clean();
        }

        return $block_content;
    }

    public function render_modal_template() {
        if ( ! is_product() && ! is_singular( 'product' ) ) {
            return;
        }

        global $post;
        $product = wc_get_product( $post->ID );
        if ( ! $product ) {
            return;
        }

        $config_type = get_post_meta( $product->get_id(), '_wooconfig_type', true );
        if ( $config_type && 'disabled' !== $config_type ) {
            include WOOCONFIG_PATH . 'templates/modal-configurator.php';
        }

        WC_Config_Size_Guide::render_modal( $product );
    }
}

register_activation_hook( __FILE__, array( 'WooConfig', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WooConfig', 'deactivate' ) );

WooConfig::instance();
