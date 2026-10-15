<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Config_Wholesale {

    /**
     * Check if the wholesale system is enabled globally
     *
     * @return bool
     */
    public static function is_enabled() {
        return 'yes' === get_option( 'wooconfig_wholesale_enabled', 'yes' );
    }

    /**
     * Check if a specific user (or current logged in user) is a wholesale customer
     *
     * @param int|WP_User|null $user
     * @return bool
     */
    public static function is_user_wholesale( $user = null ) {
        if ( null === $user ) {
            if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
                return false;
            }
            $user = wp_get_current_user();
        } elseif ( is_numeric( $user ) ) {
            $user = get_user_by( 'id', absint( $user ) );
        }

        if ( ! $user || ! is_a( $user, 'WP_User' ) ) {
            return false;
        }

        // Check user role
        if ( in_array( 'wholesale_customer', (array) $user->roles, true ) ) {
            return true;
        }

        // Check user meta flag
        if ( 'yes' === get_user_meta( $user->ID, '_wooconfig_is_wholesale', true ) ) {
            return true;
        }

        return false;
    }

    /**
     * Check if wholesale price tags and badges should be displayed in catalog / product pages
     *
     * @return bool
     */
    public static function are_prices_visible() {
        if ( ! self::is_enabled() ) {
            return false;
        }

        // If current user is a registered wholesale customer, always show wholesale prices
        if ( self::is_user_wholesale() ) {
            return true;
        }

        return 'yes' === get_option( 'wooconfig_wholesale_show_prices', 'no' );
    }

    public static function init() {
        if ( ! self::is_enabled() ) {
            return;
        }

        // Register custom wholesale role
        add_action( 'init', array( __CLASS__, 'register_wholesale_role' ) );

        // Enqueue frontend wholesale assets (CSS & JS for wholesale notice and info modal)
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        // Render wholesale terms modal in footer
        add_action( 'wp_footer', array( __CLASS__, 'render_wholesale_modal' ) );

        // Filter WooCommerce price html across catalog, single product, and loops (evaluated inside the callback via are_prices_visible)
        add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'append_wholesale_price_html' ), 25, 2 );
        add_filter( 'render_block', array( __CLASS__, 'filter_block_price_html' ), 10, 2 );

        // Wholesale customer body class
        add_filter( 'body_class', array( __CLASS__, 'add_body_class' ) );

        // Cart totals calculations & dynamic pricing
        add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_wholesale_cart_pricing' ), 25, 1 );

        // Shortcodes & Gutenberg Blocks
        add_action( 'init', array( __CLASS__, 'register_blocks_and_shortcodes' ) );

        // Feedback notice in cart and checkout
        add_action( 'woocommerce_before_cart', array( __CLASS__, 'render_cart_wholesale_notice' ) );
        add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'render_cart_wholesale_notice' ) );

        // AJAX fragments for dynamic cart refresh if needed
        add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'add_cart_fragments' ) );

        // User profile wholesale fields in backend
        add_action( 'show_user_profile', array( __CLASS__, 'render_user_profile_fields' ) );
        add_action( 'edit_user_profile', array( __CLASS__, 'render_user_profile_fields' ) );
        add_action( 'personal_options_update', array( __CLASS__, 'save_user_profile_fields' ) );
        add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_profile_fields' ) );

        // User admin list columns
        add_filter( 'manage_users_columns', array( __CLASS__, 'add_user_column' ) );
        add_filter( 'manage_users_custom_column', array( __CLASS__, 'render_user_column_content' ), 10, 3 );

        // My Account dashboard wholesale banner / greeting
        add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'render_my_account_wholesale_box' ), 5 );

        // Login redirect for wholesale customers
        add_filter( 'woocommerce_login_redirect', array( __CLASS__, 'wholesale_login_redirect' ), 10, 2 );
        add_filter( 'login_redirect', array( __CLASS__, 'wholesale_login_redirect' ), 10, 3 );
    }

    /**
     * Redirect wholesale customers to designated page upon login
     */
    public static function wholesale_login_redirect( $redirect_to, $user = null, $user_param = null ) {
        if ( ! $user || is_wp_error( $user ) ) {
            if ( $user_param && is_a( $user_param, 'WP_User' ) ) {
                $user = $user_param;
            } elseif ( is_user_logged_in() ) {
                $user = wp_get_current_user();
            }
        }

        if ( $user && is_a( $user, 'WP_User' ) && self::is_user_wholesale( $user ) ) {
            $configured_url = trim( get_option( 'wooconfig_wholesale_login_redirect', '' ) );
            if ( ! empty( $configured_url ) ) {
                return esc_url_raw( $configured_url );
            }
            return wc_get_page_permalink( 'myaccount' );
        }

        return $redirect_to;
    }

    /**
     * Register 'wholesale_customer' role if not present
     */
    public static function register_wholesale_role() {
        if ( ! get_role( 'wholesale_customer' ) ) {
            $customer_role = get_role( 'customer' );
            $caps = $customer_role ? $customer_role->capabilities : array( 'read' => true );
            add_role( 'wholesale_customer', __( 'Cliente Mayorista', 'woo-config' ), $caps );
        }
    }

    /**
     * Add body class for wholesale customers
     */
    public static function add_body_class( $classes ) {
        if ( self::is_user_wholesale() ) {
            $classes[] = 'cafu-wholesale-customer';
        }
        return $classes;
    }

    /**
     * Enqueue wholesale CSS and JavaScript
     */
    public static function enqueue_assets() {
        if ( ! self::is_enabled() ) {
            return;
        }

        wp_enqueue_style(
            'wooconfig-wholesale-css',
            WOOCONFIG_URL . 'assets/css/wholesale.css',
            array(),
            WOOCONFIG_VERSION
        );

        wp_enqueue_script(
            'wooconfig-wholesale-js',
            WOOCONFIG_URL . 'assets/js/wholesale.js',
            array( 'jquery' ),
            WOOCONFIG_VERSION,
            true
        );
    }

    /**
     * Get wholesale configuration options with defaults
     */
    public static function get_options() {
        return array(
            'enabled'         => self::is_enabled(),
            'show_prices'     => self::are_prices_visible(),
            'cart_threshold'  => max( 1, (int) get_option( 'wooconfig_wholesale_cart_qty', 50 ) ),
            'global_override' => 'yes' === get_option( 'wooconfig_wholesale_override_product_rules', 'yes' ),
            'modal_title'     => get_option( 'wooconfig_wholesale_modal_title', __( 'Condiciones de Precios por Mayor', 'woo-config' ) ),
            'modal_content'   => get_option( 'wooconfig_wholesale_modal_content', self::get_default_modal_content() ),
        );
    }

    /**
     * Default modal content for wholesale conditions
     */
    public static function get_default_modal_content() {
        $threshold = max( 1, (int) get_option( 'wooconfig_wholesale_cart_qty', 50 ) );
        return '<p>' . sprintf( __( 'En <strong>Cafu</strong> fabricamos y confeccionamos todas nuestras prendas en taller propio. Cualquier cliente puede acceder a nuestras tarifas mayoristas mediante dos modalidades:', 'woo-config' ), $threshold ) . '</p>' .
               '<ul>' .
               '<li><strong>' . sprintf( __( 'Regla Global de Pedido (%d o más unidades):', 'woo-config' ), $threshold ) . '</strong> ' . sprintf( __( 'Si tu carrito suma un total de %d o más prendas (puedes combinar distintos modelos, diseños y tallas), automáticamente todas las prendas elegibles se cobran a precio mayorista.', 'woo-config' ), $threshold ) . '</li>' .
               '<li><strong>' . __( 'Regla Individual por Producto:', 'woo-config' ) . '</strong> ' . __( 'Cada producto cuenta con una cantidad mínima requerida para activar su precio por mayor de forma independiente.', 'woo-config' ) . '</li>' .
               '</ul>' .
               '<p>' . __( 'Los opcionales de confección (como cuellos piqué o mangas largas) se sumarán al precio mayorista según tu selección.', 'woo-config' ) . '</p>';
    }

    /**
     * Render the Wholesale Terms Modal Lightbox in footer
     */
    public static function render_wholesale_modal() {
        $opts = self::get_options();
        ?>
        <div class="wooconfig-wholesale-overlay" id="wooconfig-wholesale-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="wooconfig-wholesale-modal-title">
            <div class="wooconfig-wholesale-modal-box">
                <div class="wooconfig-wholesale-modal-header">
                    <div class="wooconfig-wholesale-header-title-wrap">
                        <span class="wooconfig-wholesale-icon">🏷️</span>
                        <h3 id="wooconfig-wholesale-modal-title" class="wooconfig-wholesale-title">
                            <?php echo esc_html( $opts['modal_title'] ); ?>
                        </h3>
                    </div>
                    <button type="button" class="wooconfig-wholesale-close-btn" id="wooconfig-wholesale-close-btn" aria-label="<?php esc_attr_e( 'Cerrar', 'woo-config' ); ?>">✖</button>
                </div>
                <div class="wooconfig-wholesale-modal-body">
                    <?php echo wp_kses_post( wpautop( $opts['modal_content'] ) ); ?>
                </div>
                <div class="wooconfig-wholesale-modal-footer">
                    <button type="button" class="wooconfig-wholesale-btn-ok" id="wooconfig-wholesale-btn-ok">
                        <?php esc_html_e( 'Entendido', 'woo-config' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Append wholesale price tag and info trigger to catalog/product price HTML
     *
     * @param string $price_html
     * @param WC_Product $product
     * @return string
     */
    public static function append_wholesale_price_html( $price_html, $product ) {
        if ( ! self::is_enabled() ) {
            return $price_html;
        }

        if ( is_admin() && ! wp_doing_ajax() ) {
            return $price_html;
        }

        if ( ! is_a( $product, 'WC_Product' ) ) {
            return $price_html;
        }

        $product_id = $product->get_id();
        $parent_id  = $product->get_parent_id();
        $target_id  = $parent_id > 0 ? $parent_id : $product_id;

        $wholesale_price = get_post_meta( $target_id, '_wooconfig_wholesale_price', true );
        if ( '' === $wholesale_price || null === $wholesale_price || (float) $wholesale_price <= 0 ) {
            return $price_html;
        }

        $min_qty = (int) get_post_meta( $target_id, '_wooconfig_wholesale_min_qty', true );
        if ( $min_qty < 1 ) {
            $min_qty = 1;
        }

        $formatted_wholesale_price = wc_price( (float) $wholesale_price );
        $min_label = ( 1 === $min_qty )
            ? __( 'mín. 1 ud.*', 'woo-config' )
            : sprintf( __( 'mín. %d uds.*', 'woo-config' ), $min_qty );

        $tag_html = self::render_price_badge( $target_id );
        return $price_html . $tag_html;
    }

    /**
     * Render standalone wholesale price badge HTML for a product
     *
     * @param int|WC_Product $product
     * @param array $args
     * @return string
     */
    public static function render_price_badge( $product = null, $args = array() ) {
        if ( ! self::is_enabled() || ! self::are_prices_visible() ) {
            return '';
        }

        if ( ! $product ) {
            global $product;
            if ( ! $product ) {
                $pid = get_the_ID();
                if ( ! $pid ) {
                    $pid = get_queried_object_id();
                }
                if ( $pid ) {
                    $product = wc_get_product( $pid );
                }
            }
        } elseif ( is_numeric( $product ) ) {
            $product = wc_get_product( $product );
        }

        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return '';
        }

        $product_id = $product->get_id();
        $parent_id  = $product->get_parent_id();
        $target_id  = $parent_id > 0 ? $parent_id : $product_id;

        $wholesale_price = get_post_meta( $target_id, '_wooconfig_wholesale_price', true );
        if ( '' === $wholesale_price || null === $wholesale_price || (float) $wholesale_price <= 0 ) {
            return '';
        }

        $min_qty = (int) get_post_meta( $target_id, '_wooconfig_wholesale_min_qty', true );
        if ( $min_qty < 1 ) {
            $min_qty = 1;
        }

        $formatted_wholesale_price = wc_price( (float) $wholesale_price );
        $min_qty_html = '';
        if ( $min_qty > 1 ) {
            $min_qty_html = '<span class="wooconfig-ws-min">(' . sprintf( esc_html__( 'mín. %d uds.', 'woo-config' ), $min_qty ) . ')</span> ';
        }

        $extra_class = isset( $args['class'] ) ? ' ' . esc_attr( $args['class'] ) : '';

        return '<div class="wooconfig-wholesale-price-tag' . $extra_class . '">' .
            '<span class="wooconfig-ws-label">' . esc_html__( 'x mayor:', 'woo-config' ) . '</span> ' .
            '<strong class="wooconfig-ws-amount">' . $formatted_wholesale_price . '</strong> ' .
            $min_qty_html .
            '<button type="button" class="wooconfig-ws-info-btn" data-action="open-wholesale-modal" title="' . esc_attr__( 'Ver condiciones mayoristas', 'woo-config' ) . '" aria-label="' . esc_attr__( 'Información de precio mayorista', 'woo-config' ) . '">i</button>' .
        '</div>';
    }

    /**
     * Register Gutenberg block and Shortcode for wholesale price
     */
    public static function register_blocks_and_shortcodes() {
        // Shortcodes: [wooconfig_wholesale_price]
        add_shortcode( 'wooconfig_wholesale_price', array( __CLASS__, 'render_shortcode' ) );
        add_shortcode( 'wooconfig_precio_mayorista', array( __CLASS__, 'render_shortcode' ) );

        // Conditional visibility shortcodes for FSE / Gutenberg
        add_shortcode( 'wooconfig_is_wholesale', array( __CLASS__, 'render_conditional_wholesale_shortcode' ) );
        add_shortcode( 'wooconfig_not_wholesale', array( __CLASS__, 'render_conditional_not_wholesale_shortcode' ) );

        // Wholesale Home & Portal Banner Shortcode
        add_shortcode( 'wooconfig_wholesale_banner', array( __CLASS__, 'render_wholesale_banner_shortcode' ) );

        // Gutenberg Block: wooconfig/wholesale-price
        if ( function_exists( 'register_block_type' ) ) {
            register_block_type( 'wooconfig/wholesale-price', array(
                'editor_script'   => 'wooconfig-blocks-js',
                'editor_style'    => 'wooconfig-modal-css',
                'render_callback' => array( __CLASS__, 'render_block' ),
                'attributes'      => array(
                    'align' => array(
                        'type'    => 'string',
                        'default' => 'left',
                    ),
                    'className' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                ),
            ) );
        }
    }

    /**
     * Shortcode callback
     */
    public static function render_shortcode( $atts ) {
        $a = shortcode_atts( array(
            'product_id' => 0,
            'class'      => '',
        ), $atts, 'wooconfig_wholesale_price' );

        $product = ! empty( $a['product_id'] ) ? wc_get_product( absint( $a['product_id'] ) ) : null;
        return self::render_price_badge( $product, array( 'class' => $a['class'] ) );
    }

    /**
     * Block callback
     */
    public static function render_block( $attributes, $content ) {
        $align = ! empty( $attributes['align'] ) ? 'align' . $attributes['align'] : '';
        $custom_class = ! empty( $attributes['className'] ) ? $attributes['className'] : '';
        $classes = trim( $align . ' ' . $custom_class );

        return self::render_price_badge( null, array( 'class' => $classes ) );
    }

    /**
     * Shortcode: [wooconfig_is_wholesale] ... [/wooconfig_is_wholesale]
     * Displays content ONLY to logged in wholesale customers
     */
    public static function render_conditional_wholesale_shortcode( $atts, $content = null ) {
        if ( self::is_user_wholesale() ) {
            return do_shortcode( $content );
        }
        return '';
    }

    /**
     * Shortcode: [wooconfig_not_wholesale] ... [/wooconfig_not_wholesale]
     * Displays content to visitors or non-wholesale customers
     */
    public static function render_conditional_not_wholesale_shortcode( $atts, $content = null ) {
        if ( ! self::is_user_wholesale() ) {
            return do_shortcode( $content );
        }
        return '';
    }

    /**
     * Shortcode: [wooconfig_wholesale_banner]
     * Renders responsive wholesale portal teaser banner for Home and pages
     */
    public static function render_wholesale_banner_shortcode( $atts ) {
        $a = shortcode_atts( array(
            'image_id' => 2158,
            'link'     => '',
            'class'    => '',
        ), $atts, 'wooconfig_wholesale_banner' );

        $is_ws    = self::is_user_wholesale();
        $dest_url = ! empty( $a['link'] ) ? $a['link'] : ( $is_ws ? wc_get_page_permalink( 'myaccount' ) : wc_get_page_permalink( 'myaccount' ) );
        $img_src  = wp_get_attachment_image_url( absint( $a['image_id'] ), 'full' );

        if ( ! $img_src ) {
            $img_src = home_url( '/wp-content/uploads/2026/10/banner-cafu-mayorista.jpg' );
        }

        $extra_class = ! empty( $a['class'] ) ? ' ' . esc_attr( $a['class'] ) : '';

        ob_start();
        ?>
        <div class="cafu-wholesale-home-banner-wrap<?php echo $extra_class; ?>" style="margin: 20px 0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
            <a href="<?php echo esc_url( $dest_url ); ?>" style="display: block; line-height: 0; text-decoration: none; position: relative;">
                <img src="<?php echo esc_url( $img_src ); ?>" alt="<?php esc_attr_e( 'Zona Mayorista Cafú - Indumentaria Deportiva por Mayor', 'woo-config' ); ?>" style="width: 100%; height: auto; display: block; border-radius: 12px; transition: transform 0.3s ease;" onmouseover="this.style.transform='scale(1.008)';" onmouseout="this.style.transform='none';" />
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Filter Block Theme product-price block if needed
     */
    public static function filter_block_price_html( $block_content, $block ) {
        if ( ! self::is_enabled() || ! self::are_prices_visible() ) {
            return $block_content;
        }

        if ( is_admin() || empty( $block['blockName'] ) ) {
            return $block_content;
        }

        // If the block is woocommerce/product-price and the tag isn't inside it yet
        if ( 'woocommerce/product-price' === $block['blockName'] && false === strpos( $block_content, 'wooconfig-wholesale-price-tag' ) ) {
            global $product;
            if ( ! $product ) {
                $pid = get_the_ID();
                if ( $pid ) {
                    $product = wc_get_product( $pid );
                }
            }
            if ( $product ) {
                $product_id = $product->get_id();
                $wholesale_price = get_post_meta( $product_id, '_wooconfig_wholesale_price', true );
                if ( '' !== $wholesale_price && (float) $wholesale_price > 0 ) {
                    $min_qty = max( 1, (int) get_post_meta( $product_id, '_wooconfig_wholesale_min_qty', true ) );
                    $formatted_wholesale_price = wc_price( (float) $wholesale_price );
                    $min_label = ( 1 === $min_qty )
                        ? __( 'mín. 1 ud.*', 'woo-config' )
                        : sprintf( __( 'mín. %d uds.*', 'woo-config' ), $min_qty );

                    $tag_html = '<div class="wooconfig-wholesale-price-tag">' .
                        '<span class="wooconfig-ws-label">' . esc_html__( 'Mayorista:', 'woo-config' ) . '</span> ' .
                        '<strong class="wooconfig-ws-amount">' . $formatted_wholesale_price . '</strong> ' .
                        '<span class="wooconfig-ws-min">(' . esc_html( $min_label ) . ')</span> ' .
                        '<button type="button" class="wooconfig-ws-info-btn" data-action="open-wholesale-modal" title="' . esc_attr__( 'Ver condiciones mayoristas', 'woo-config' ) . '">i</button>' .
                    '</div>';

                    $block_content .= $tag_html;
                }
            }
        }

        return $block_content;
    }

    /**
     * Calculate cart evaluation for wholesale pricing
     *
     * @param WC_Cart $cart
     * @return array
     */
    public static function evaluate_cart_wholesale_status( $cart ) {
        $opts = self::get_options();
        $global_threshold = $opts['cart_threshold'];
        $global_override  = $opts['global_override'];

        $total_cart_units = 0;
        $product_quantities = array(); // target_id => total qty in cart

        // First pass: sum quantities
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $qty = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;
            $total_cart_units += $qty;

            $product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
            if ( $product_id > 0 ) {
                if ( ! isset( $product_quantities[$product_id] ) ) {
                    $product_quantities[$product_id] = 0;
                }
                $product_quantities[$product_id] += $qty;
            }
        }

        $global_qualified = ( $total_cart_units >= $global_threshold );

        return array(
            'total_units'       => $total_cart_units,
            'global_threshold'  => $global_threshold,
            'global_override'   => $global_override,
            'global_qualified'  => $global_qualified,
            'units_remaining'   => max( 0, $global_threshold - $total_cart_units ),
            'product_quantities'=> $product_quantities,
        );
    }

    /**
     * Hook: woocommerce_before_calculate_totals
     * Recalculates unit prices based on wholesale qualification
     */
    public static function apply_wholesale_cart_pricing( $cart ) {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        if ( did_action( 'wooconfig_applied_wholesale_pricing' ) >= 1 ) {
            // Prevent recursive loop within same request
        }

        $status = self::evaluate_cart_wholesale_status( $cart );

        foreach ( $cart->get_cart() as $cart_item_key => &$cart_item ) {
            $product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
            if ( ! $product_id ) {
                continue;
            }

            $wholesale_price_meta = get_post_meta( $product_id, '_wooconfig_wholesale_price', true );
            $has_wholesale_price  = ( '' !== $wholesale_price_meta && null !== $wholesale_price_meta && (float) $wholesale_price_meta > 0 );

            if ( ! $has_wholesale_price ) {
                $cart_item['_wooconfig_wholesale_applied'] = false;
                continue;
            }

            $wholesale_price = (float) $wholesale_price_meta;
            $min_qty         = max( 1, (int) get_post_meta( $product_id, '_wooconfig_wholesale_min_qty', true ) );
            $product_qty     = isset( $status['product_quantities'][$product_id] ) ? $status['product_quantities'][$product_id] : 0;

            // Determine if wholesale applies
            $applies = false;
            $reason  = '';

            if ( $status['global_qualified'] && $status['global_override'] ) {
                $applies = true;
                $reason  = 'global_cart';
            } elseif ( $product_qty >= $min_qty ) {
                $applies = true;
                $reason  = 'product_min';
            }

            $cart_item['_wooconfig_wholesale_applied'] = $applies;
            $cart_item['_wooconfig_wholesale_reason']  = $reason;

            // Compute appropriate base price
            $regular_base_price = isset( $cart_item['_wooconfig_base_price'] )
                ? (float) $cart_item['_wooconfig_base_price']
                : (float) $cart_item['data']->get_regular_price();

            if ( $regular_base_price <= 0 ) {
                $regular_base_price = (float) $cart_item['data']->get_price();
            }

            $effective_base_price = $applies ? $wholesale_price : $regular_base_price;

            // If this is a WooConfig custom product (with extra add-ons/players/deposits)
            if ( isset( $cart_item['_wooconfig_group_id'] ) ) {
                // Calculate add-on cost
                $extra_cost = 0;

                // Flow 3 table players:
                if ( ! empty( $cart_item['_wooconfig_adicionales_raw'] ) && is_array( $cart_item['_wooconfig_adicionales_raw'] ) ) {
                    $prod_adicionales = get_post_meta( $product_id, '_wooconfig_adicionales', true );
                    if ( is_array( $prod_adicionales ) ) {
                        foreach ( $cart_item['_wooconfig_adicionales_raw'] as $ad_k ) {
                            if ( isset( $prod_adicionales[$ad_k]['price'] ) ) {
                                $extra_cost += (float) $prod_adicionales[$ad_k]['price'];
                            }
                        }
                    }
                } elseif ( ! empty( $cart_item['_wooconfig_adicionales_counts'] ) && is_array( $cart_item['_wooconfig_adicionales_counts'] ) ) {
                    // Wizard summary / Quick flow
                    $prod_adicionales = get_post_meta( $product_id, '_wooconfig_adicionales', true );
                    $total_item_qty   = isset( $cart_item['_wooconfig_total_items'] ) ? absint( $cart_item['_wooconfig_total_items'] ) : 1;
                    $item_qty         = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;
                    $calc_qty         = max( 1, $total_item_qty > 0 ? $total_item_qty : $item_qty );

                    $raw_extra_sum = 0;
                    if ( is_array( $prod_adicionales ) ) {
                        foreach ( $cart_item['_wooconfig_adicionales_counts'] as $ad_k => $count ) {
                            if ( isset( $prod_adicionales[$ad_k]['price'] ) ) {
                                $raw_extra_sum += ( absint( $count ) * (float) $prod_adicionales[$ad_k]['price'] );
                            }
                        }
                    }
                    $extra_cost = $raw_extra_sum / $calc_qty;
                }

                $full_unit_price = $effective_base_price + $extra_cost;
                $is_deposit      = ( ! empty( $cart_item['_wooconfig_is_deposit'] ) && 'yes' === $cart_item['_wooconfig_is_deposit'] );
                $deposit_pct     = isset( $cart_item['_wooconfig_deposit_percent'] ) ? absint( $cart_item['_wooconfig_deposit_percent'] ) : 50;

                if ( $is_deposit ) {
                    $deposit_unit_price = round( $full_unit_price * ( $deposit_pct / 100 ) );
                    $balance_unit_due   = $full_unit_price - $deposit_unit_price;
                } else {
                    $deposit_unit_price = $full_unit_price;
                    $balance_unit_due   = 0;
                }

                $cart_item['_wooconfig_full_unit_price']    = $full_unit_price;
                $cart_item['_wooconfig_deposit_unit_price'] = $deposit_unit_price;
                $cart_item['_wooconfig_balance_unit_due']   = $balance_unit_due;
                $cart_item['_wooconfig_custom_price']       = $full_unit_price;

                $cart_item['data']->set_price( $full_unit_price );
                $cart_item['data']->set_regular_price( $full_unit_price );
                $cart_item['data']->set_sale_price( '' );
            } else {
                // Standard WooCommerce product
                $cart_item['data']->set_price( $effective_base_price );
                $cart_item['data']->set_sale_price( $applies ? $wholesale_price : '' );
            }

            $cart->cart_contents[$cart_item_key] = $cart_item;
        }
        unset( $cart_item );

        do_action( 'wooconfig_applied_wholesale_pricing' );
    }

    /**
     * Render banner / notice in cart or checkout showing wholesale progress
     */
    public static function render_cart_wholesale_notice() {
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            return;
        }

        $status = self::evaluate_cart_wholesale_status( WC()->cart );
        $has_wholesale_items = false;

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $pid = isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0;
            $ws_meta = get_post_meta( $pid, '_wooconfig_wholesale_price', true );
            if ( '' !== $ws_meta && (float) $ws_meta > 0 ) {
                $has_wholesale_items = true;
                break;
            }
        }

        if ( ! $has_wholesale_items ) {
            return;
        }

        ?>
        <div class="wooconfig-wholesale-cart-notice <?php echo $status['global_qualified'] ? 'is-qualified' : 'is-pending'; ?>" id="wooconfig-wholesale-cart-box">
            <div class="wooconfig-wholesale-notice-inner">
                <span class="wooconfig-wholesale-notice-icon">
                    <?php echo $status['global_qualified'] ? '🎉' : '🏷️'; ?>
                </span>
                <div class="wooconfig-wholesale-notice-text">
                    <?php if ( $status['global_qualified'] ) : ?>
                        <strong><?php esc_html_e( '¡Tarifa Mayorista Activada!', 'woo-config' ); ?></strong>
                        <p><?php echo sprintf( esc_html__( 'Tu pedido acumula %d prendas (superando la meta de %d unidades). Se ha aplicado el precio mayorista a todos los productos elegibles.', 'woo-config' ), $status['total_units'], $status['global_threshold'] ); ?></p>
                    <?php else : ?>
                        <strong><?php esc_html_e( 'Beneficio Mayorista Disponible', 'woo-config' ); ?></strong>
                        <p><?php echo sprintf( esc_html__( 'Llevas %1$d prendas en tu pedido. ¡Agrega %2$d prenda(s) más para activar automáticamente la tarifa mayorista global en todo tu carrito!', 'woo-config' ), $status['total_units'], $status['units_remaining'] ); ?></p>
                    <?php endif; ?>
                </div>
                <button type="button" class="wooconfig-ws-info-btn-text" data-action="open-wholesale-modal">
                    <?php esc_html_e( 'Ver condiciones', 'woo-config' ); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render wholesale checkbox in WordPress user profile
     */
    public static function render_user_profile_fields( $user ) {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_users' ) ) {
            return;
        }

        $is_wholesale = self::is_user_wholesale( $user );
        ?>
        <h2><?php esc_html_e( 'Condición Mayorista (WooConfig)', 'woo-config' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="wooconfig_is_wholesale"><?php esc_html_e( 'Cliente Mayorista', 'woo-config' ); ?></label>
                </th>
                <td>
                    <label for="wooconfig_is_wholesale" style="font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="wooconfig_is_wholesale" id="wooconfig_is_wholesale" value="yes" <?php checked( $is_wholesale ); ?> />
                        <?php esc_html_e( 'Marcar este usuario como Cliente Mayorista', 'woo-config' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'Al estar marcado (o tener el rol "Cliente Mayorista"), el cliente verá siempre activos los precios mayoristas en el catálogo y productos al iniciar sesión.', 'woo-config' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save user wholesale status
     */
    public static function save_user_profile_fields( $user_id ) {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_users' ) ) {
            return;
        }

        $is_ws = ( isset( $_POST['wooconfig_is_wholesale'] ) && 'yes' === $_POST['wooconfig_is_wholesale'] ) ? 'yes' : 'no';
        update_user_meta( $user_id, '_wooconfig_is_wholesale', $is_ws );

        $user = get_user_by( 'id', $user_id );
        if ( $user ) {
            if ( 'yes' === $is_ws ) {
                if ( ! in_array( 'wholesale_customer', (array) $user->roles, true ) && in_array( 'customer', (array) $user->roles, true ) ) {
                    $user->set_role( 'wholesale_customer' );
                }
            } else {
                if ( in_array( 'wholesale_customer', (array) $user->roles, true ) ) {
                    $user->set_role( 'customer' );
                }
            }
        }
    }

    /**
     * Add column to users admin table
     */
    public static function add_user_column( $columns ) {
        $columns['wooconfig_wholesale'] = __( 'Mayorista', 'woo-config' );
        return $columns;
    }

    /**
     * Render user column content
     */
    public static function render_user_column_content( $val, $column_name, $user_id ) {
        if ( 'wooconfig_wholesale' === $column_name ) {
            if ( self::is_user_wholesale( $user_id ) ) {
                return '<span style="display:inline-block; padding: 2px 8px; border-radius: 4px; background: #e0f2fe; color: #0284c7; font-weight: 700; font-size: 11px;">🏷️ MAYORISTA</span>';
            }
            return '<span style="color: #94a3b8; font-size: 11px;">Regular</span>';
        }
        return $val;
    }

    /**
     * Render customized wholesale welcome and action banner in WooCommerce My Account dashboard
     */
    public static function render_my_account_wholesale_box() {
        $is_ws = self::is_user_wholesale();
        $opts  = self::get_options();
        $threshold = $opts['cart_threshold'];

        if ( $is_ws ) :
        ?>
        <div class="cafu-myaccount-wholesale-card cafu-myaccount-wholesale-card--active" style="margin-bottom: 25px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; border-radius: 12px; padding: 24px 28px; box-shadow: 0 4px 15px rgba(0,0,0,0.12); position: relative; overflow: hidden; border: 1.5px solid #334155;">
            <div style="position: absolute; right: -20px; top: -20px; width: 140px; height: 140px; background: radial-gradient(circle, rgba(148,236,0,0.18) 0%, transparent 70%); pointer-events: none;"></div>
            
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                <div>
                    <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; background: rgba(148,236,0,0.15); color: #94ec00; font-weight: 800; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">
                        ✨ Cuenta Mayorista Oficial
                    </span>
                    <h3 style="margin: 0 0 6px 0; color: #ffffff; font-size: 20px; font-weight: 800; letter-spacing: -0.3px;">
                        Bienvenido a tu Espacio Mayorista Cafú
                    </h3>
                    <p style="margin: 0; color: #94a3b8; font-size: 14px; max-width: 580px; line-height: 1.5;">
                        Tienes acceso preferente y tarifas por mayor activadas en todo nuestro catálogo deportivo. Recuerda que para activar la tarifa mayorista tu pedido debe sumar un mínimo de <strong><?php echo esc_html( $threshold ); ?> prendas en total</strong> (puedes combinar distintos modelos y tallas).
                    </p>
                </div>

                <div>
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: #94ec00; color: #000000; padding: 12px 24px; border-radius: 8px; font-weight: 800; font-size: 14px; text-decoration: none; transition: transform 0.15s ease, background 0.15s ease;" onmouseover="this.style.background='#a6ff00'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='#94ec00'; this.style.transform='none';">
                        <span>Ir al Catálogo</span>
                        <span style="font-size: 16px;">➔</span>
                    </a>
                </div>
            </div>
        </div>
        <?php else : ?>
        <div class="cafu-myaccount-wholesale-card cafu-myaccount-wholesale-card--regular" style="margin-bottom: 25px; background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 12px; padding: 20px 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                <div>
                    <strong style="display: block; font-size: 15px; color: #0f172a; margin-bottom: 4px;">
                        🏷️ ¿Comprando para tu club, colegio o empresa?
                    </strong>
                    <span style="font-size: 13px; color: #64748b; line-height: 1.4;">
                        Fabricamos uniformes completos desde 10 y 50 unidades con precios por mayor y boceto digital gratuito.
                    </span>
                </div>
                <button type="button" class="button" data-action="open-wholesale-modal" style="font-weight: 700; font-size: 13px;">
                    Ver Condiciones Mayoristas
                </button>
            </div>
        </div>
        <?php
        endif;
    }
}

WC_Config_Wholesale::init();
