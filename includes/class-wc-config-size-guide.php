<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Config_Size_Guide {

    private static $modal_rendered = false;

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_blocks_and_shortcodes' ) );
        add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
    }

    public static function register_blocks_and_shortcodes() {
        // Shortcodes
        add_shortcode( 'wooconfig_size_guide', array( __CLASS__, 'render_shortcode' ) );
        add_shortcode( 'wooconfig_size_guide_button', array( __CLASS__, 'render_shortcode' ) );

        // Gutenberg Block: Size Guide Button
        if ( function_exists( 'register_block_type' ) ) {
            register_block_type( 'wooconfig/size-guide-button', array(
                'editor_script'   => 'wooconfig-blocks-js',
                'editor_style'    => 'wooconfig-modal-css',
                'render_callback' => array( __CLASS__, 'render_block_button' ),
                'attributes'      => array(
                    'buttonText'  => array(
                        'type'    => 'string',
                        'default' => __( 'Guía de tallas', 'woo-config' ),
                    ),
                    'buttonStyle' => array(
                        'type'    => 'string',
                        'default' => 'default',
                    ),
                    'align'       => array(
                        'type'    => 'string',
                        'default' => 'left',
                    ),
                ),
            ) );
        }
    }

    public static function enqueue_editor_assets() {
        wp_enqueue_script(
            'wooconfig-blocks-js',
            WOOCONFIG_URL . 'assets/js/blocks.js',
            array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor' ),
            WOOCONFIG_VERSION,
            true
        );

        wp_enqueue_style(
            'wooconfig-modal-css',
            WOOCONFIG_URL . 'assets/css/modal.css',
            array(),
            WOOCONFIG_VERSION
        );
    }

    /**
     * Render button HTML (for standalone usage in Gutenberg, shortcode, or template)
     *
     * @param array $args
     * @return string
     */
    public static function render_button( $args = array() ) {
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

        // Always ensure CSS and JS are loaded when the button is rendered
        if ( ! wp_style_is( 'wooconfig-modal-css', 'enqueued' ) ) {
            wp_enqueue_style(
                'wooconfig-modal-css',
                WOOCONFIG_URL . 'assets/css/modal.css',
                array(),
                WOOCONFIG_VERSION
            );
        }
        if ( ! wp_script_is( 'wooconfig-frontend-js', 'enqueued' ) ) {
            wp_enqueue_script(
                'wooconfig-frontend-js',
                WOOCONFIG_URL . 'assets/js/configurator.js',
                array( 'jquery' ),
                WOOCONFIG_VERSION,
                true
            );
        }

        // Ensure modal HTML is printed in footer
        add_action( 'wp_footer', function() use ( $product ) {
            self::render_modal( $product );
        }, 30 );

        $text        = ! empty( $args['text'] ) ? sanitize_text_field( $args['text'] ) : __( 'Guía de tallas', 'woo-config' );
        $align       = ! empty( $args['align'] ) ? sanitize_text_field( $args['align'] ) : 'left';
        $style       = ! empty( $args['style'] ) ? sanitize_text_field( $args['style'] ) : 'default';
        $extra_class = ! empty( $args['class'] ) ? sanitize_text_field( $args['class'] ) : '';

        $wrapper_style = '';
        if ( 'center' === $align ) {
            $wrapper_style = 'display: flex; justify-content: center;';
        } elseif ( 'right' === $align ) {
            $wrapper_style = 'display: flex; justify-content: flex-end;';
        }

        $btn_style_class = 'wooconfig-btn-style-' . esc_attr( $style );

        ob_start();
        ?>
        <div class="wooconfig-size-guide-standalone-wrapper <?php echo esc_attr( $extra_class ); ?>" style="<?php echo esc_attr( $wrapper_style ); ?>">
            <button type="button" class="wooconfig-btn-guide wooconfig-btn-standalone <?php echo esc_attr( $btn_style_class ); ?>" data-action="open-size-guide" id="wooconfig-standalone-btn-size-guide" title="<?php esc_attr_e( 'Ver tabla de medidas y guía de tallas', 'woo-config' ); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <line x1="6" y1="8" x2="6" y2="16"></line>
                    <line x1="10" y1="8" x2="10" y2="12"></line>
                    <line x1="14" y1="8" x2="14" y2="16"></line>
                    <line x1="18" y1="8" x2="18" y2="12"></line>
                </svg>
                <span><?php echo esc_html( $text ); ?></span>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode callback
     */
    public static function render_shortcode( $atts ) {
        $a = shortcode_atts( array(
            'text'  => __( 'Guía de tallas', 'woo-config' ),
            'align' => 'left',
            'style' => 'default',
            'class' => '',
        ), $atts, 'wooconfig_size_guide' );

        return self::render_button( $a );
    }

    /**
     * Gutenberg block callback
     */
    public static function render_block_button( $attributes ) {
        $args = array(
            'text'  => ! empty( $attributes['buttonText'] ) ? $attributes['buttonText'] : __( 'Guía de tallas', 'woo-config' ),
            'style' => ! empty( $attributes['buttonStyle'] ) ? $attributes['buttonStyle'] : 'default',
            'align' => ! empty( $attributes['align'] ) ? $attributes['align'] : 'left',
        );

        return self::render_button( $args );
    }

    /**
     * Get consolidated size chart data based on product taxonomy and title.
     *
     * @param int|WC_Product $product
     * @return array
     */
    public static function get_guide_config( $product ) {
        if ( is_numeric( $product ) ) {
            $product = wc_get_product( $product );
        }

        if ( ! $product ) {
            return self::get_default_config();
        }

        $product_id   = $product->get_id();
        $title        = mb_strtolower( $product->get_name(), 'UTF-8' );
        $categories   = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'slugs' ) );
        if ( is_wp_error( $categories ) ) {
            $categories = array();
        }

        // 1. Determine Discipline: 'buzo' vs 'futbol' (replicated for basketball, volleyball, rugby, etc.)
        $is_buzo = in_array( 'buzos-y-abrigo', $categories, true ) 
            || false !== strpos( $title, 'buzo' ) 
            || false !== strpos( $title, 'polerón de buzo' ) 
            || false !== strpos( $title, 'poleron de buzo' ) 
            || false !== strpos( $title, 'pantalón de buzo' ) 
            || false !== strpos( $title, 'pantalon de buzo' );

        $discipline = $is_buzo ? 'buzo' : 'futbol';

        // 2. Determine Gender Cut: 'femenino' vs 'masculino'
        $is_femenino = false !== strpos( $title, 'femenin' ) 
            || false !== strpos( $title, 'dama' ) 
            || false !== strpos( $title, 'mujer' );

        $gender_default = $is_femenino ? 'femenino' : 'masculino';

        // 3. Determine Garment Scope: 'all', 'top', or 'bottom'
        $is_full_set = false !== strpos( $title, 'uniforme' ) 
            || false !== strpos( $title, 'indumentaria' ) 
            || false !== strpos( $title, 'buzo full' ) 
            || false !== strpos( $title, 'conjunto' );

        if ( $is_full_set ) {
            $garment_scope = 'all';
        } elseif ( false !== strpos( $title, 'short' ) || ( false !== strpos( $title, 'pantalón' ) || false !== strpos( $title, 'pantalon' ) ) ) {
            $garment_scope = 'bottom';
        } elseif ( false !== strpos( $title, 'camiseta' ) || false !== strpos( $title, 'polera' ) || false !== strpos( $title, 'polerón' ) || false !== strpos( $title, 'poleron' ) || false !== strpos( $title, 'chaqueta' ) ) {
            $garment_scope = 'top';
        } else {
            $garment_scope = 'all';
        }

        // Custom admin overrides
        $custom_img = get_post_meta( $product_id, '_wooconfig_size_guide_image', true );
        $custom_url = get_post_meta( $product_id, '_wooconfig_size_guide_url', true );

        if ( 'buzo' === $discipline ) {
            $top_label    = __( 'Chaqueta / Polerón', 'woo-config' );
            $bottom_label = __( 'Pantalón Buzo', 'woo-config' );
            $discipline_title = __( 'Buzos Deportivos', 'woo-config' );
            $img_default = WOOCONFIG_URL . 'assets/images/guia de tallas buzo.jpeg';
        } else {
            $top_label    = __( 'Camiseta / Polera', 'woo-config' );
            $bottom_label = __( 'Short Deportivo', 'woo-config' );
            $discipline_title = __( 'Uniformes e Indumentaria Deportiva', 'woo-config' );
            $img_default = WOOCONFIG_URL . 'assets/images/guia de tallas futbol.jpeg';
        }

        return array(
            'product_id'        => $product_id,
            'product_name'      => $product->get_name(),
            'discipline'        => $discipline,
            'discipline_title'  => $discipline_title,
            'gender_default'    => $gender_default,
            'garment_scope'     => $garment_scope,
            'top_label'         => $top_label,
            'bottom_label'      => $bottom_label,
            'image_url'         => ! empty( $custom_img ) ? $custom_img : $img_default,
            'external_url'      => $custom_url,
            'tables'            => self::get_tables_data( $discipline ),
        );
    }

    /**
     * Default config fallback
     */
    public static function get_default_config() {
        return array(
            'product_id'       => 0,
            'product_name'     => __( 'Prendas Deportivas', 'woo-config' ),
            'discipline'       => 'futbol',
            'discipline_title' => __( 'Uniformes e Indumentaria Deportiva', 'woo-config' ),
            'gender_default'   => 'masculino',
            'garment_scope'    => 'all',
            'top_label'        => __( 'Camiseta / Polera', 'woo-config' ),
            'bottom_label'     => __( 'Short Deportivo', 'woo-config' ),
            'image_url'        => WOOCONFIG_URL . 'assets/images/guia de tallas futbol.jpeg',
            'external_url'     => '',
            'tables'           => self::get_tables_data( 'futbol' ),
        );
    }

    /**
     * Raw size table data in cm
     *
     * @param string $discipline 'buzo' or 'futbol'
     * @return array
     */
    public static function get_tables_data( $discipline = 'futbol' ) {
        if ( 'buzo' === $discipline ) {
            return array(
                'masculino' => array(
                    'infantil' => array(
                        array( 'talla' => '2',  'top_largo' => 46, 'top_ancho' => 39, 'bottom_largo' => 75,  'bottom_ancho' => 36 ),
                        array( 'talla' => '4',  'top_largo' => 49, 'top_ancho' => 41, 'bottom_largo' => 78,  'bottom_ancho' => 38 ),
                        array( 'talla' => '6',  'top_largo' => 52, 'top_ancho' => 43, 'bottom_largo' => 81,  'bottom_ancho' => 40 ),
                        array( 'talla' => '8',  'top_largo' => 55, 'top_ancho' => 45, 'bottom_largo' => 84,  'bottom_ancho' => 42 ),
                        array( 'talla' => '10', 'top_largo' => 58, 'top_ancho' => 47, 'bottom_largo' => 87,  'bottom_ancho' => 44 ),
                        array( 'talla' => '12', 'top_largo' => 61, 'top_ancho' => 49, 'bottom_largo' => 90,  'bottom_ancho' => 46 ),
                        array( 'talla' => '14', 'top_largo' => 64, 'top_ancho' => 51, 'bottom_largo' => 93,  'bottom_ancho' => 48 ),
                        array( 'talla' => '16', 'top_largo' => 67, 'top_ancho' => 55, 'bottom_largo' => 96,  'bottom_ancho' => 50 ),
                    ),
                    'adulto' => array(
                        array( 'talla' => 'S',    'top_largo' => 69, 'top_ancho' => 55, 'bottom_largo' => 99,  'bottom_ancho' => 51 ),
                        array( 'talla' => 'M',    'top_largo' => 71, 'top_ancho' => 57, 'bottom_largo' => 101, 'bottom_ancho' => 53 ),
                        array( 'talla' => 'L',    'top_largo' => 73, 'top_ancho' => 59, 'bottom_largo' => 103, 'bottom_ancho' => 55 ),
                        array( 'talla' => 'XL',   'top_largo' => 75, 'top_ancho' => 61, 'bottom_largo' => 105, 'bottom_ancho' => 57 ),
                        array( 'talla' => 'XXL',  'top_largo' => 77, 'top_ancho' => 63, 'bottom_largo' => 107, 'bottom_ancho' => 59 ),
                        array( 'talla' => 'XXXL', 'top_largo' => 79, 'top_ancho' => 65, 'bottom_largo' => 109, 'bottom_ancho' => 61 ),
                    ),
                ),
                'femenino' => array(
                    'infantil' => array(
                        array( 'talla' => '2',  'top_largo' => 44, 'top_ancho' => 37, 'bottom_largo' => 73,  'bottom_ancho' => 34 ),
                        array( 'talla' => '4',  'top_largo' => 47, 'top_ancho' => 39, 'bottom_largo' => 76,  'bottom_ancho' => 36 ),
                        array( 'talla' => '6',  'top_largo' => 50, 'top_ancho' => 41, 'bottom_largo' => 79,  'bottom_ancho' => 38 ),
                        array( 'talla' => '8',  'top_largo' => 53, 'top_ancho' => 43, 'bottom_largo' => 82,  'bottom_ancho' => 40 ),
                        array( 'talla' => '10', 'top_largo' => 56, 'top_ancho' => 45, 'bottom_largo' => 85,  'bottom_ancho' => 42 ),
                        array( 'talla' => '12', 'top_largo' => 59, 'top_ancho' => 47, 'bottom_largo' => 88,  'bottom_ancho' => 44 ),
                        array( 'talla' => '14', 'top_largo' => 62, 'top_ancho' => 49, 'bottom_largo' => 91,  'bottom_ancho' => 46 ),
                        array( 'talla' => '16', 'top_largo' => 65, 'top_ancho' => 51, 'bottom_largo' => 93,  'bottom_ancho' => 48 ),
                    ),
                    'adulto' => array(
                        array( 'talla' => 'XS',   'top_largo' => 64, 'top_ancho' => 51, 'bottom_largo' => 93,  'bottom_ancho' => 48 ),
                        array( 'talla' => 'S',    'top_largo' => 67, 'top_ancho' => 53, 'bottom_largo' => 96,  'bottom_ancho' => 50 ),
                        array( 'talla' => 'M',    'top_largo' => 69, 'top_ancho' => 55, 'bottom_largo' => 99,  'bottom_ancho' => 52 ),
                        array( 'talla' => 'L',    'top_largo' => 71, 'top_ancho' => 57, 'bottom_largo' => 101, 'bottom_ancho' => 54 ),
                        array( 'talla' => 'XL',   'top_largo' => 73, 'top_ancho' => 59, 'bottom_largo' => 103, 'bottom_ancho' => 56 ),
                        array( 'talla' => 'XXL',  'top_largo' => 75, 'top_ancho' => 61, 'bottom_largo' => 105, 'bottom_ancho' => 58 ),
                        array( 'talla' => 'XXXL', 'top_largo' => 77, 'top_ancho' => 63, 'bottom_largo' => 107, 'bottom_ancho' => 60 ),
                    ),
                ),
            );
        }

        // Default Fútbol / Otros deportes (Básquetbol, Voleibol, Rugby, Cuerpo Técnico, etc.)
        return array(
            'masculino' => array(
                'infantil' => array(
                    array( 'talla' => '2',  'top_largo' => 47, 'top_ancho' => 35,   'bottom_largo' => 28, 'bottom_ancho' => 35 ),
                    array( 'talla' => '4',  'top_largo' => 50, 'top_ancho' => 37,   'bottom_largo' => 30, 'bottom_ancho' => 37 ),
                    array( 'talla' => '6',  'top_largo' => 53, 'top_ancho' => 39,   'bottom_largo' => 32, 'bottom_ancho' => 39 ),
                    array( 'talla' => '8',  'top_largo' => 56, 'top_ancho' => 41,   'bottom_largo' => 34, 'bottom_ancho' => 41 ),
                    array( 'talla' => '10', 'top_largo' => 59, 'top_ancho' => 43,   'bottom_largo' => 36, 'bottom_ancho' => 43 ),
                    array( 'talla' => '12', 'top_largo' => 62, 'top_ancho' => 45,   'bottom_largo' => 38, 'bottom_ancho' => 45 ),
                    array( 'talla' => '14', 'top_largo' => 65, 'top_ancho' => 47,   'bottom_largo' => 40, 'bottom_ancho' => 47 ),
                    array( 'talla' => '16', 'top_largo' => 68, 'top_ancho' => 49,   'bottom_largo' => 42, 'bottom_ancho' => 49 ),
                ),
                'adulto' => array(
                    array( 'talla' => 'S',    'top_largo' => 72, 'top_ancho' => 50.5, 'bottom_largo' => 44, 'bottom_ancho' => 51 ),
                    array( 'talla' => 'M',    'top_largo' => 74, 'top_ancho' => 52.5, 'bottom_largo' => 46, 'bottom_ancho' => 53 ),
                    array( 'talla' => 'L',    'top_largo' => 76, 'top_ancho' => 54.5, 'bottom_largo' => 48, 'bottom_ancho' => 55 ),
                    array( 'talla' => 'XL',   'top_largo' => 78, 'top_ancho' => 56.5, 'bottom_largo' => 50, 'bottom_ancho' => 57 ),
                    array( 'talla' => 'XXL',  'top_largo' => 80, 'top_ancho' => 58.5, 'bottom_largo' => 52, 'bottom_ancho' => 59 ),
                    array( 'talla' => 'XXXL', 'top_largo' => 82, 'top_ancho' => 60.5, 'bottom_largo' => 54, 'bottom_ancho' => 61 ),
                ),
            ),
            'femenino' => array(
                'infantil' => array(
                    array( 'talla' => '2',  'top_largo' => 47, 'top_ancho' => 35, 'bottom_largo' => 28, 'bottom_ancho' => 35 ),
                    array( 'talla' => '4',  'top_largo' => 50, 'top_ancho' => 37, 'bottom_largo' => 30, 'bottom_ancho' => 37 ),
                    array( 'talla' => '6',  'top_largo' => 53, 'top_ancho' => 39, 'bottom_largo' => 32, 'bottom_ancho' => 39 ),
                    array( 'talla' => '8',  'top_largo' => 56, 'top_ancho' => 41, 'bottom_largo' => 34, 'bottom_ancho' => 41 ),
                    array( 'talla' => '10', 'top_largo' => 59, 'top_ancho' => 43, 'bottom_largo' => 36, 'bottom_ancho' => 43 ),
                    array( 'talla' => '12', 'top_largo' => 62, 'top_ancho' => 45, 'bottom_largo' => 38, 'bottom_ancho' => 45 ),
                    array( 'talla' => '14', 'top_largo' => 65, 'top_ancho' => 47, 'bottom_largo' => 40, 'bottom_ancho' => 47 ),
                    array( 'talla' => '16', 'top_largo' => 68, 'top_ancho' => 49, 'bottom_largo' => 42, 'bottom_ancho' => 49 ),
                ),
                'adulto' => array(
                    array( 'talla' => 'XS',   'top_largo' => 66, 'top_ancho' => 48, 'bottom_largo' => 40, 'bottom_ancho' => 47 ),
                    array( 'talla' => 'S',    'top_largo' => 68, 'top_ancho' => 50, 'bottom_largo' => 42, 'bottom_ancho' => 49 ),
                    array( 'talla' => 'M',    'top_largo' => 70, 'top_ancho' => 52, 'bottom_largo' => 44, 'bottom_ancho' => 50 ),
                    array( 'talla' => 'L',    'top_largo' => 72, 'top_ancho' => 54, 'bottom_largo' => 46, 'bottom_ancho' => 51 ),
                    array( 'talla' => 'XL',   'top_largo' => 74, 'top_ancho' => 56, 'bottom_largo' => 48, 'bottom_ancho' => 53 ),
                    array( 'talla' => 'XXL',  'top_largo' => 77, 'top_ancho' => 58, 'bottom_largo' => 50, 'bottom_ancho' => 55 ),
                    array( 'talla' => 'XXXL', 'top_largo' => 79, 'top_ancho' => 60, 'bottom_largo' => 52, 'bottom_ancho' => 57 ),
                ),
            ),
        );
    }

    /**
     * Render the Size Guide Modal HTML (can be embedded in footer)
     *
     * @param int|WC_Product $product
     */
    public static function render_modal( $product ) {
        if ( self::$modal_rendered ) {
            return;
        }

        self::$modal_rendered = true;
        $config = self::get_guide_config( $product );
        include WOOCONFIG_PATH . 'templates/size-guide-modal.php';
    }
}

WC_Config_Size_Guide::init();
