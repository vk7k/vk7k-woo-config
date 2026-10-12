<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Config_Wholesale {

    public static function init() {
        // Enqueue frontend wholesale assets (CSS & JS for wholesale notice and info modal)
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        // Render wholesale terms modal in footer
        add_action( 'wp_footer', array( __CLASS__, 'render_wholesale_modal' ) );

        // Filter WooCommerce price html across catalog, single product, and loops
        add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'append_wholesale_price_html' ), 25, 2 );

        // In Single Product page: if block theme doesn't trigger woocommerce_get_price_html or needs extra badge
        add_filter( 'render_block', array( __CLASS__, 'filter_block_price_html' ), 10, 2 );

        // Cart totals calculations & dynamic pricing
        add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_wholesale_cart_pricing' ), 25, 1 );

        // Feedback notice in cart and checkout
        add_action( 'woocommerce_before_cart', array( __CLASS__, 'render_cart_wholesale_notice' ) );
        add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'render_cart_wholesale_notice' ) );

        // AJAX fragments for dynamic cart refresh if needed
        add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'add_cart_fragments' ) );
    }

    /**
     * Enqueue wholesale CSS and JavaScript
     */
    public static function enqueue_assets() {
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

        $tag_html = '<div class="wooconfig-wholesale-price-tag">' .
            '<span class="wooconfig-ws-label">' . esc_html__( 'Mayorista:', 'woo-config' ) . '</span> ' .
            '<strong class="wooconfig-ws-amount">' . $formatted_wholesale_price . '</strong> ' .
            '<span class="wooconfig-ws-min">(' . esc_html( $min_label ) . ')</span> ' .
            '<button type="button" class="wooconfig-ws-info-btn" data-action="open-wholesale-modal" title="' . esc_attr__( 'Ver condiciones mayoristas', 'woo-config' ) . '" aria-label="' . esc_attr__( 'Información de precio mayorista', 'woo-config' ) . '">i</button>' .
        '</div>';

        return $price_html . $tag_html;
    }

    /**
     * Filter Block Theme product-price block if needed
     */
    public static function filter_block_price_html( $block_content, $block ) {
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
     * Fragment to refresh notice on cart updates
     */
    public static function add_cart_fragments( $fragments ) {
        ob_start();
        self::render_cart_wholesale_notice();
        $fragments['div#wooconfig-wholesale-cart-box'] = ob_get_clean();
        return $fragments;
    }
}

WC_Config_Wholesale::init();
