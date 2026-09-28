<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Config_Cart {

    public static function init() {
        add_action( 'wp_ajax_wooconfig_add_team_to_cart', array( __CLASS__, 'handle_add_team_to_cart' ) );
        add_action( 'wp_ajax_nopriv_wooconfig_add_team_to_cart', array( __CLASS__, 'handle_add_team_to_cart' ) );

        add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'calculate_custom_cart_item_prices' ), 20, 1 );
        add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'apply_deposit_balance_deduction' ), 20, 1 );
        add_filter( 'woocommerce_cart_item_name', array( __CLASS__, 'filter_cart_item_name_html' ), 20, 3 );
        add_filter( 'woocommerce_cart_item_price', array( __CLASS__, 'filter_cart_item_price_html' ), 20, 3 );
        add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'filter_cart_item_quantity' ), 20, 3 );
        add_filter( 'woocommerce_update_cart_validation', array( __CLASS__, 'prevent_cart_item_qty_change' ), 10, 4 );
        add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'render_cart_item_custom_data' ), 10, 2 );
        add_filter( 'woocommerce_cart_item_permalink', array( __CLASS__, 'filter_cart_item_permalink' ), 10, 3 );
        add_filter( 'woocommerce_check_cart_items', array( __CLASS__, 'validate_cart_items' ) );
    }

    public static function handle_add_team_to_cart() {
        check_ajax_referer( 'wooconfig_nonce', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $product    = wc_get_product( $product_id );

        if ( ! $product ) {
            wp_send_json_error( array( 'message' => __( 'Producto no encontrado.', 'woo-config' ) ) );
        }

        $flow = isset( $_POST['flow'] ) ? sanitize_text_field( $_POST['flow'] ) : 'wizard';

        $min_units = (int) get_option( 'wooconfig_min_units', get_option( 'wooconfig_default_min_units', 1 ) );
        $min_units = max( 1, $min_units );

        $design_desc = isset( $_POST['design_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['design_desc'] ) ) : '';
        $files_json  = isset( $_POST['files'] ) ? wp_unslash( $_POST['files'] ) : '[]';
        $files       = json_decode( $files_json, true );
        if ( ! is_array( $files ) ) {
            $files = array();
        }

        // Product configured adicionales
        $product_adicionales = get_post_meta( $product_id, '_wooconfig_adicionales', true );
        if ( ! is_array( $product_adicionales ) ) {
            $product_adicionales = array(
                'cuello_polo' => array( 'id' => 'cuello_polo', 'enabled' => 'yes', 'label' => __( 'Cuello polo', 'woo-config' ), 'price' => 2500 ),
                'manga_larga' => array( 'id' => 'manga_larga', 'enabled' => 'yes', 'label' => __( 'Manga larga', 'woo-config' ), 'price' => 2500 ),
            );
        }

        $base_price = (float) $product->get_price();

        // Global Deposit configuration
        $deposit_enabled = get_option( 'wooconfig_deposit_enabled', 'yes' );
        $deposit_percent = (int) get_option( 'wooconfig_deposit_percent', 50 );
        if ( $deposit_percent < 1 || $deposit_percent > 99 ) {
            $deposit_percent = 50;
        }

        $payment_mode = isset( $_POST['payment_mode'] ) ? sanitize_text_field( $_POST['payment_mode'] ) : 'deposit';
        $is_deposit   = ( 'yes' === $deposit_enabled && 'deposit' === $payment_mode );

        // ================= FLOW 3: UNIFIED 4-STEP CONFIGURATION =================
        if ( 'unified' === $flow ) {
            $qty = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;
            if ( $qty < 1 ) {
                wp_send_json_error( array( 'message' => __( 'Debes ingresar al menos 1 prenda.', 'woo-config' ) ) );
            }

            // Decode design, sponsor and player files
            $files_design_json   = isset( $_POST['files_design'] ) ? wp_unslash( $_POST['files_design'] ) : '[]';
            $files_design        = json_decode( $files_design_json, true );
            if ( ! is_array( $files_design ) ) $files_design = array();

            $files_sponsors_json = isset( $_POST['files_sponsors'] ) ? wp_unslash( $_POST['files_sponsors'] ) : '[]';
            $files_sponsors      = json_decode( $files_sponsors_json, true );
            if ( ! is_array( $files_sponsors ) ) $files_sponsors = array();

            $files_players_json  = isset( $_POST['files_players'] ) ? wp_unslash( $_POST['files_players'] ) : '[]';
            $files_players       = json_decode( $files_players_json, true );
            if ( ! is_array( $files_players ) ) $files_players = array();

            $design_notes  = isset( $_POST['design_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['design_notes'] ) ) : '';
            $sponsor_notes = isset( $_POST['sponsor_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sponsor_notes'] ) ) : '';

            $use_table    = ( isset( $_POST['use_table'] ) && ( '1' === $_POST['use_table'] || 'true' === $_POST['use_table'] ) );
            $players_json = isset( $_POST['players'] ) ? wp_unslash( $_POST['players'] ) : '[]';
            $players      = json_decode( $players_json, true );
            if ( ! is_array( $players ) ) $players = array();

            // Validation server side:
            if ( empty( $files_design ) ) {
                wp_send_json_error( array( 'message' => __( 'Paso 2 (Diseño): Debes subir al menos un archivo o imagen con el diseño.', 'woo-config' ) ) );
            }
            if ( empty( $files_sponsors ) ) {
                wp_send_json_error( array( 'message' => __( 'Paso 3 (Sponsors): Debes subir los logos de los sponsors.', 'woo-config' ) ) );
            }
            if ( empty( $files_players ) && ! $use_table ) {
                wp_send_json_error( array( 'message' => __( 'Paso 4 (Jugadores y tallas): Debes subir un archivo con las tallas o completar la tabla manual.', 'woo-config' ) ) );
            }
            if ( $use_table && empty( $players ) ) {
                wp_send_json_error( array( 'message' => __( 'Paso 4: Por favor completa la tabla de jugadores.', 'woo-config' ) ) );
            }

            // Tag files for clarity
            foreach ( $files_design as &$fd ) { $fd['category'] = __( 'Diseño', 'woo-config' ); }
            unset($fd);
            foreach ( $files_sponsors as &$fs ) { $fs['category'] = __( 'Sponsors', 'woo-config' ); }
            unset($fs);
            foreach ( $files_players as &$fp ) { $fp['category'] = __( 'Tallas y Jugadores', 'woo-config' ); }
            unset($fp);

            $all_files = array_merge( $files_design, $files_sponsors, $files_players );

            // Combine notes
            $notes_parts = array();
            if ( ! empty( $design_notes ) ) {
                $notes_parts[] = __( 'Diseño:', 'woo-config' ) . ' ' . $design_notes;
            }
            if ( ! empty( $sponsor_notes ) ) {
                $notes_parts[] = __( 'Sponsors:', 'woo-config' ) . ' ' . $sponsor_notes;
            }
            $combined_desc = implode( "\n\n", $notes_parts );

            $adicionales_counts_json = isset( $_POST['adicionales_counts'] ) ? wp_unslash( $_POST['adicionales_counts'] ) : '{}';
            $adicionales_counts      = json_decode( $adicionales_counts_json, true );
            if ( ! is_array( $adicionales_counts ) ) {
                $adicionales_counts = array();
            }

            $extra_total = 0;
            $adicionales_summary_list = array();
            foreach ( $adicionales_counts as $ad_key => $ad_qty ) {
                $ad_qty = absint( $ad_qty );
                if ( $ad_qty > 0 && isset( $product_adicionales[$ad_key] ) ) {
                    $unit_cost = (float) $product_adicionales[$ad_key]['price'];
                    $extra_total += ( $ad_qty * $unit_cost );
                    $formatted_ad_price = html_entity_decode( wp_strip_all_tags( wc_price( $ad_qty * $unit_cost ) ) );
                    $adicionales_summary_list[] = sprintf(
                        '%dx %s (+%s)',
                        $ad_qty,
                        $product_adicionales[$ad_key]['label'],
                        $formatted_ad_price
                    );
                }
            }

            $group_id = 'unified_' . wp_generate_uuid4();

            if ( $use_table && ! empty( $players ) ) {
                $group_title = sprintf( __( 'Equipo / Set: %s (%d prendas)', 'woo-config' ), $product->get_name(), count( $players ) );
                $added_count = 0;
                $index = 1;

                $variations_info = array();
                if ( $product->is_type( 'variable' ) ) {
                    $available_variations = $product->get_available_variations();
                    foreach ( $available_variations as $var ) {
                        $var_id     = $var['variation_id'];
                        $attributes = $var['attributes'];
                        $size_val   = '';
                        foreach ( $attributes as $attr_k => $attr_v ) {
                            if ( false !== stripos( $attr_k, 'talla' ) || false !== stripos( $attr_k, 'size' ) ) {
                                $size_val = strtolower( trim( $attr_v ) );
                            }
                        }
                        if ( empty( $size_val ) && ! empty( $attributes ) ) {
                            $keys = array_keys( $attributes );
                            $size_val = strtolower( trim( $attributes[$keys[0]] ) );
                        }
                        $variations_info[] = array( 'id' => $var_id, 'attributes' => $attributes, 'size' => $size_val );
                    }
                }

                foreach ( $players as $p ) {
                    $dorsal = isset( $p['dorsal'] ) ? sanitize_text_field( $p['dorsal'] ) : '';
                    $name   = isset( $p['name'] ) ? sanitize_text_field( $p['name'] ) : '';
                    $size   = isset( $p['size'] ) ? sanitize_text_field( $p['size'] ) : '';
                    if ( empty( $size ) ) {
                        wp_send_json_error( array( 'message' => sprintf( __( 'Por favor selecciona la talla para la prenda #%d.', 'woo-config' ), $index ) ) );
                    }

                    $assigned_ad_raw = isset( $p['adicionales'] ) && is_array( $p['adicionales'] ) ? $p['adicionales'] : array();
                    $player_adicionales_labels = array();
                    $player_adicionales_cost   = 0;

                    foreach ( $assigned_ad_raw as $ad_key ) {
                        if ( isset( $product_adicionales[$ad_key] ) ) {
                            $ad_obj  = $product_adicionales[$ad_key];
                            $ad_cost = (float) $ad_obj['price'];
                            $player_adicionales_cost += $ad_cost;
                            $formatted_single_ad_price = html_entity_decode( wp_strip_all_tags( wc_price( $ad_cost ) ) );
                            $player_adicionales_labels[] = $ad_obj['label'] . ' (+' . $formatted_single_ad_price . ')';
                        }
                    }

                    $player_full_unit_price = $base_price + $player_adicionales_cost;
                    if ( $is_deposit ) {
                        $deposit_unit_price = round( $player_full_unit_price * ( $deposit_percent / 100 ) );
                        $balance_unit_due   = $player_full_unit_price - $deposit_unit_price;
                    } else {
                        $deposit_unit_price = $player_full_unit_price;
                        $balance_unit_due   = 0;
                    }

                    $variation_id = 0;
                    $variation_attributes = array();
                    if ( $product->is_type( 'variable' ) && ! empty( $variations_info ) ) {
                        $target_size = strtolower( trim( $size ) );
                        $matched = null;
                        foreach ( $variations_info as $vinfo ) {
                            if ( $vinfo['size'] === $target_size ) {
                                $matched = $vinfo;
                                break;
                            }
                        }
                        if ( ! $matched ) $matched = $variations_info[0];
                        $variation_id         = $matched['id'];
                        $variation_attributes = $matched['attributes'];
                    }

                    $cart_item_data = array(
                        '_wooconfig_flow'                => 'unified',
                        '_wooconfig_group_id'            => $group_id,
                        '_wooconfig_group_title'         => $group_title,
                        '_wooconfig_item_index'          => $index,
                        '_wooconfig_total_items'         => count( $players ),
                        '_wooconfig_is_deposit'          => $is_deposit ? 'yes' : 'no',
                        '_wooconfig_payment_mode'        => $is_deposit ? 'deposit' : 'full',
                        '_wooconfig_deposit_percent'     => $deposit_percent,
                        '_wooconfig_full_unit_price'     => $player_full_unit_price,
                        '_wooconfig_deposit_unit_price'  => $deposit_unit_price,
                        '_wooconfig_balance_unit_due'    => $balance_unit_due,
                        '_wooconfig_custom_price'        => $player_full_unit_price,
                        '_wooconfig_dorsal'              => $dorsal,
                        '_wooconfig_player_name'         => $name,
                        '_wooconfig_size'                => $size,
                        '_wooconfig_adicionales_raw'     => $assigned_ad_raw,
                        '_wooconfig_adicionales_list'    => $player_adicionales_labels,
                        '_wooconfig_base_price'          => $base_price,
                        '_wooconfig_design_desc'         => $combined_desc,
                        '_wooconfig_files'               => $all_files,
                        '_wooconfig_files_design'        => $files_design,
                        '_wooconfig_files_sponsors'      => $files_sponsors,
                        '_wooconfig_files_players'       => $files_players,
                        '_wooconfig_unique_key'          => md5( $group_id . '_' . $index . '_' . microtime() ),
                    );

                    $key = WC()->cart->add_to_cart( $product_id, 1, $variation_id, $variation_attributes, $cart_item_data );
                    if ( $key ) $added_count++;
                    $index++;
                }

                if ( $added_count > 0 ) {
                    wp_send_json_success( array(
                        'message'      => sprintf( __( '¡Se agregaron %d prendas al carrito!', 'woo-config' ), $added_count ),
                        'cart_url'     => wc_get_cart_url(),
                        'checkout_url' => wc_get_checkout_url(),
                        'count'        => $added_count,
                    ) );
                } else {
                    wp_send_json_error( array( 'message' => __( 'No se pudieron añadir las prendas al carrito.', 'woo-config' ) ) );
                }
            } else {
                $full_unit_price = $base_price + ( $qty > 0 ? ( $extra_total / $qty ) : 0 );
                if ( $is_deposit ) {
                    $deposit_unit_price = round( $full_unit_price * ( $deposit_percent / 100 ) );
                    $balance_unit_due   = $full_unit_price - $deposit_unit_price;
                } else {
                    $deposit_unit_price = $full_unit_price;
                    $balance_unit_due   = 0;
                }

                $cart_item_data = array(
                    '_wooconfig_flow'                => 'unified',
                    '_wooconfig_group_id'            => $group_id,
                    '_wooconfig_group_title'         => sprintf( __( 'Equipo / Set: %s (%d prendas)', 'woo-config' ), $product->get_name(), $qty ),
                    '_wooconfig_total_items'         => $qty,
                    '_wooconfig_is_deposit'          => $is_deposit ? 'yes' : 'no',
                    '_wooconfig_payment_mode'        => $is_deposit ? 'deposit' : 'full',
                    '_wooconfig_deposit_percent'     => $deposit_percent,
                    '_wooconfig_full_unit_price'     => $full_unit_price,
                    '_wooconfig_deposit_unit_price'  => $deposit_unit_price,
                    '_wooconfig_balance_unit_due'    => $balance_unit_due,
                    '_wooconfig_custom_price'        => $full_unit_price,
                    '_wooconfig_base_price'          => $base_price,
                    '_wooconfig_adicionales_summary' => $adicionales_summary_list,
                    '_wooconfig_adicionales_counts'  => $adicionales_counts,
                    '_wooconfig_design_desc'         => $combined_desc,
                    '_wooconfig_files'               => $all_files,
                    '_wooconfig_files_design'        => $files_design,
                    '_wooconfig_files_sponsors'      => $files_sponsors,
                    '_wooconfig_files_players'       => $files_players,
                    '_wooconfig_unique_key'          => md5( $group_id . '_' . microtime() ),
                );

                $variation_id         = 0;
                $variation_attributes = array();
                if ( $product->is_type( 'variable' ) ) {
                    $available_variations = $product->get_available_variations();
                    if ( ! empty( $available_variations ) ) {
                        $variation_id         = $available_variations[0]['variation_id'];
                        $variation_attributes = $available_variations[0]['attributes'];
                    }
                }

                $cart_item_key = WC()->cart->add_to_cart( $product_id, $qty, $variation_id, $variation_attributes, $cart_item_data );
                if ( $cart_item_key ) {
                    wp_send_json_success( array(
                        'message'      => sprintf( __( '¡Se agregaron %d prendas al carrito!', 'woo-config' ), $qty ),
                        'cart_url'     => wc_get_cart_url(),
                        'checkout_url' => wc_get_checkout_url(),
                        'count'        => $qty,
                    ) );
                } else {
                    wp_send_json_error( array( 'message' => __( 'No se pudo añadir al carrito.', 'woo-config' ) ) );
                }
            }
        }

        // ================= FLOW 1: QUICK CONFIGURATION =================
        if ( 'quick' === $flow ) {
            if ( empty( $files ) ) {
                wp_send_json_error( array(
                    'message' => __( 'No es posible confeccionar tu pedido sin el detalle de las tallas, dorsales y apellidos. Por favor sube una foto o archivo con los datos de tu equipo (o utiliza el Personalizador paso a paso).', 'woo-config' )
                ) );
            }

            $qty = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;
            if ( $qty < 1 ) {
                wp_send_json_error( array( 'message' => __( 'Debes ingresar al menos 1 prenda.', 'woo-config' ) ) );
            }

            $adicionales_counts_json = isset( $_POST['adicionales_counts'] ) ? wp_unslash( $_POST['adicionales_counts'] ) : '{}';
            $adicionales_counts      = json_decode( $adicionales_counts_json, true );
            if ( ! is_array( $adicionales_counts ) ) {
                $adicionales_counts = array();
            }

            $extra_total = 0;
            $adicionales_summary_list = array();

            foreach ( $adicionales_counts as $ad_key => $ad_qty ) {
                $ad_qty = absint( $ad_qty );
                if ( $ad_qty > 0 && isset( $product_adicionales[$ad_key] ) ) {
                    $unit_cost = (float) $product_adicionales[$ad_key]['price'];
                    $extra_total += ( $ad_qty * $unit_cost );
                    $formatted_ad_price = html_entity_decode( wp_strip_all_tags( wc_price( $ad_qty * $unit_cost ) ) );
                    $adicionales_summary_list[] = sprintf(
                        '%dx %s (+%s)',
                        $ad_qty,
                        $product_adicionales[$ad_key]['label'],
                        $formatted_ad_price
                    );
                }
            }

            $full_unit_price = $base_price + ( $qty > 0 ? ( $extra_total / $qty ) : 0 );

            if ( $is_deposit ) {
                $deposit_unit_price = round( $full_unit_price * ( $deposit_percent / 100 ) );
                $balance_unit_due   = $full_unit_price - $deposit_unit_price;
            } else {
                $deposit_unit_price = $full_unit_price;
                $balance_unit_due   = 0;
            }

            $group_id = 'quick_' . wp_generate_uuid4();

            $cart_item_data = array(
                '_wooconfig_flow'                => 'quick',
                '_wooconfig_group_id'            => $group_id,
                '_wooconfig_group_title'         => sprintf( __( 'Pedido Rápido: %s (%d prendas)', 'woo-config' ), $product->get_name(), $qty ),
                '_wooconfig_total_items'         => $qty,
                '_wooconfig_is_deposit'          => $is_deposit ? 'yes' : 'no',
                '_wooconfig_payment_mode'        => $is_deposit ? 'deposit' : 'full',
                '_wooconfig_deposit_percent'     => $deposit_percent,
                '_wooconfig_full_unit_price'     => $full_unit_price,
                '_wooconfig_deposit_unit_price'  => $deposit_unit_price,
                '_wooconfig_balance_unit_due'    => $balance_unit_due,
                '_wooconfig_custom_price'        => $full_unit_price,
                '_wooconfig_base_price'          => $base_price,
                '_wooconfig_adicionales_summary' => $adicionales_summary_list,
                '_wooconfig_adicionales_counts'  => $adicionales_counts,
                '_wooconfig_design_desc'         => $design_desc,
                '_wooconfig_files'               => $files,
                '_wooconfig_unique_key'          => md5( $group_id . '_' . microtime() ),
            );

            $variation_id         = 0;
            $variation_attributes = array();

            if ( $product->is_type( 'variable' ) ) {
                $available_variations = $product->get_available_variations();
                if ( ! empty( $available_variations ) ) {
                    $variation_id         = $available_variations[0]['variation_id'];
                    $variation_attributes = $available_variations[0]['attributes'];
                }
            }

            $cart_item_key = WC()->cart->add_to_cart(
                $product_id,
                $qty,
                $variation_id,
                $variation_attributes,
                $cart_item_data
            );

            if ( $cart_item_key ) {
                wp_send_json_success( array(
                    'message'      => sprintf( __( '¡Se agregaron %d prendas al carrito!', 'woo-config' ), $qty ),
                    'cart_url'     => wc_get_cart_url(),
                    'checkout_url' => wc_get_checkout_url(),
                    'count'        => $qty,
                ) );
            } else {
                $notices = wc_get_notices( 'error' );
                $error_msg = __( 'No se pudo añadir al carrito.', 'woo-config' );
                if ( ! empty( $notices ) ) {
                    $error_msg = strip_tags( $notices[0]['notice'] );
                    wc_clear_notices();
                }
                wp_send_json_error( array( 'message' => $error_msg ) );
            }
        }

        // ================= FLOW 2: WIZARD STEP BY STEP =================
        $players_json = isset( $_POST['players'] ) ? wp_unslash( $_POST['players'] ) : '[]';
        $players      = json_decode( $players_json, true );

        if ( empty( $players ) || ! is_array( $players ) || count( $players ) < 1 ) {
            wp_send_json_error( array( 'message' => __( 'Debes ingresar al menos un jugador/prenda.', 'woo-config' ) ) );
        }

        $group_id    = 'team_' . wp_generate_uuid4();
        $group_title = sprintf( __( 'Equipo / Set: %s (%d prendas)', 'woo-config' ), $product->get_name(), count( $players ) );

        $added_count = 0;
        $variations_info = array();

        if ( $product->is_type( 'variable' ) ) {
            $available_variations = $product->get_available_variations();
            foreach ( $available_variations as $var ) {
                $var_id     = $var['variation_id'];
                $attributes = $var['attributes'];
                $size_val   = '';
                $color_val  = '';

                foreach ( $attributes as $attr_k => $attr_v ) {
                    if ( false !== stripos( $attr_k, 'talla' ) || false !== stripos( $attr_k, 'size' ) ) {
                        $size_val = strtolower( trim( $attr_v ) );
                    } elseif ( false !== stripos( $attr_k, 'color' ) ) {
                        $color_val = strtolower( trim( $attr_v ) );
                    }
                }
                if ( empty( $size_val ) && ! empty( $attributes ) ) {
                    $keys = array_keys( $attributes );
                    $size_val = strtolower( trim( $attributes[$keys[0]] ) );
                }

                $variations_info[] = array(
                    'id'         => $var_id,
                    'attributes' => $attributes,
                    'size'       => $size_val,
                    'color'      => $color_val,
                );
            }
        }

        $index = 1;
        foreach ( $players as $p ) {
            $dorsal          = isset( $p['dorsal'] ) ? sanitize_text_field( $p['dorsal'] ) : '';
            $name            = isset( $p['name'] ) ? sanitize_text_field( $p['name'] ) : '';
            $size            = isset( $p['size'] ) ? sanitize_text_field( $p['size'] ) : '';
            $color           = isset( $p['color'] ) ? sanitize_text_field( $p['color'] ) : '';

            if ( empty( $size ) ) {
                wp_send_json_error( array( 'message' => sprintf( __( 'Por favor selecciona la talla para la prenda #%d.', 'woo-config' ), $index ) ) );
            }

            $assigned_ad_raw = isset( $p['adicionales'] ) && is_array( $p['adicionales'] ) ? $p['adicionales'] : array();

            $player_adicionales_labels = array();
            $player_adicionales_cost   = 0;

            foreach ( $assigned_ad_raw as $ad_key ) {
                if ( isset( $product_adicionales[$ad_key] ) ) {
                    $ad_obj  = $product_adicionales[$ad_key];
                    $ad_cost = (float) $ad_obj['price'];
                    $player_adicionales_cost += $ad_cost;
                    $formatted_single_ad_price = html_entity_decode( wp_strip_all_tags( wc_price( $ad_cost ) ) );
                    $player_adicionales_labels[] = $ad_obj['label'] . ' (+' . $formatted_single_ad_price . ')';
                }
            }

            $player_full_unit_price = $base_price + $player_adicionales_cost;

            if ( $is_deposit ) {
                $deposit_unit_price = round( $player_full_unit_price * ( $deposit_percent / 100 ) );
                $balance_unit_due   = $player_full_unit_price - $deposit_unit_price;
            } else {
                $deposit_unit_price = $player_full_unit_price;
                $balance_unit_due   = 0;
            }

            $variation_id = 0;
            $variation_attributes = array();

            if ( $product->is_type( 'variable' ) && ! empty( $variations_info ) ) {
                $target_size  = strtolower( trim( $size ) );
                $target_color = strtolower( trim( $color ) );

                $matched = null;
                if ( ! empty( $target_color ) ) {
                    foreach ( $variations_info as $vinfo ) {
                        if ( $vinfo['size'] === $target_size && $vinfo['color'] === $target_color ) {
                            $matched = $vinfo;
                            break;
                        }
                    }
                }
                if ( ! $matched ) {
                    foreach ( $variations_info as $vinfo ) {
                        if ( $vinfo['size'] === $target_size ) {
                            $matched = $vinfo;
                            break;
                        }
                    }
                }
                if ( ! $matched ) {
                    $matched = $variations_info[0];
                }

                $variation_id         = $matched['id'];
                $variation_attributes = $matched['attributes'];
            }

            $cart_item_data = array(
                '_wooconfig_flow'                => 'wizard',
                '_wooconfig_group_id'            => $group_id,
                '_wooconfig_group_title'         => $group_title,
                '_wooconfig_item_index'          => $index,
                '_wooconfig_total_items'         => count( $players ),
                '_wooconfig_is_deposit'          => $is_deposit ? 'yes' : 'no',
                '_wooconfig_payment_mode'        => $is_deposit ? 'deposit' : 'full',
                '_wooconfig_deposit_percent'     => $deposit_percent,
                '_wooconfig_full_unit_price'     => $player_full_unit_price,
                '_wooconfig_deposit_unit_price'  => $deposit_unit_price,
                '_wooconfig_balance_unit_due'    => $balance_unit_due,
                '_wooconfig_custom_price'        => $player_full_unit_price,
                '_wooconfig_dorsal'              => $dorsal,
                '_wooconfig_player_name'         => $name,
                '_wooconfig_size'                => $size,
                '_wooconfig_color'               => $color,
                '_wooconfig_adicionales_raw'     => $assigned_ad_raw,
                '_wooconfig_adicionales_list'    => $player_adicionales_labels,
                '_wooconfig_base_price'          => $base_price,
                '_wooconfig_design_desc'         => $design_desc,
                '_wooconfig_files'               => $files,
                '_wooconfig_unique_key'          => md5( $group_id . '_' . $index . '_' . microtime() ),
            );

            $cart_item_key = WC()->cart->add_to_cart(
                $product_id,
                1,
                $variation_id,
                $variation_attributes,
                $cart_item_data
            );

            if ( $cart_item_key ) {
                $added_count++;
            }
            $index++;
        }

        if ( $added_count > 0 ) {
            wp_send_json_success( array(
                'message'      => sprintf( __( '¡Se agregaron %d prendas al carrito!', 'woo-config' ), $added_count ),
                'cart_url'     => wc_get_cart_url(),
                'checkout_url' => wc_get_checkout_url(),
                'count'        => $added_count,
            ) );
        } else {
            $notices = wc_get_notices( 'error' );
            $error_msg = __( 'No se pudieron añadir las prendas al carrito.', 'woo-config' );
            if ( ! empty( $notices ) ) {
                $error_msg = strip_tags( $notices[0]['notice'] );
                wc_clear_notices();
            }
            wp_send_json_error( array( 'message' => $error_msg ) );
        }
    }

    public static function calculate_custom_cart_item_prices( $cart ) {
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['_wooconfig_custom_price'] ) && (float) $cart_item['_wooconfig_custom_price'] > 0 ) {
                $price = (float) $cart_item['_wooconfig_custom_price'];
                $cart_item['data']->set_price( $price );
                $cart_item['data']->set_regular_price( $price );
                $cart_item['data']->set_sale_price( '' );
            }
        }
    }

    public static function apply_deposit_balance_deduction( $cart ) {
        $total_balance_deduction = 0;

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( ! empty( $cart_item['_wooconfig_is_deposit'] ) && 'yes' === $cart_item['_wooconfig_is_deposit'] ) {
                $balance_unit = isset( $cart_item['_wooconfig_balance_unit_due'] ) ? (float) $cart_item['_wooconfig_balance_unit_due'] : 0;
                $qty          = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;
                $total_balance_deduction += ( $balance_unit * $qty );
            }
        }

        if ( $total_balance_deduction > 0 ) {
            $deposit_percent = (int) get_option( 'wooconfig_deposit_percent', 50 );
            $balance_percent = 100 - $deposit_percent;
            $fee_label       = sprintf( __( 'Saldo a pagar al terminar confección (%d%%)', 'woo-config' ), $balance_percent );
            $cart->add_fee( $fee_label, -$total_balance_deduction, false );
        }
    }

    public static function filter_cart_item_name_html( $product_name, $cart_item, $cart_item_key ) {
        if ( empty( $cart_item['_wooconfig_group_id'] ) ) {
            return $product_name;
        }

        $flow = isset( $cart_item['_wooconfig_flow'] ) ? $cart_item['_wooconfig_flow'] : 'wizard';
        $output = '<div class="wooconfig-cart-title-wrapper" style="margin-bottom: 4px;">';
        $output .= '<div class="wooconfig-cart-prod-title" style="font-weight: 700; color: #111; font-size: 15px; line-height: 1.3;">' . $product_name . '</div>';

        if ( 'quick' === $flow ) {
            $qty = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;
            $output .= '<div class="wooconfig-cart-badges" style="margin-top: 6px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">';
            $output .= '<span style="background: #111827; color: #94ec00; font-weight: 800; font-size: 11px; padding: 3px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">📁 ' . esc_html__( 'Configuración Rápida', 'woo-config' ) . '</span>';
            $output .= '<span style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; font-weight: 700; font-size: 11px; padding: 2px 8px; border-radius: 4px;">' . sprintf( esc_html__( 'Set de %d prendas', 'woo-config' ), $qty ) . '</span>';
            $output .= '</div>';
        } else {
            $dorsal = isset( $cart_item['_wooconfig_dorsal'] ) ? trim( $cart_item['_wooconfig_dorsal'] ) : '';
            $name   = isset( $cart_item['_wooconfig_player_name'] ) ? trim( $cart_item['_wooconfig_player_name'] ) : '';
            $size   = isset( $cart_item['_wooconfig_size'] ) ? trim( $cart_item['_wooconfig_size'] ) : '';
            $index  = isset( $cart_item['_wooconfig_item_index'] ) ? absint( $cart_item['_wooconfig_item_index'] ) : 1;
            $total  = isset( $cart_item['_wooconfig_total_items'] ) ? absint( $cart_item['_wooconfig_total_items'] ) : 1;

            $player_identity = '';
            if ( '' !== $dorsal && '' !== $name ) {
                $player_identity = '#' . esc_html( $dorsal ) . ' · ' . esc_html( mb_strtoupper( $name, 'UTF-8' ) );
            } elseif ( '' !== $dorsal ) {
                $player_identity = 'Dorsal #' . esc_html( $dorsal );
            } elseif ( '' !== $name ) {
                $player_identity = esc_html( mb_strtoupper( $name, 'UTF-8' ) );
            } else {
                $player_identity = sprintf( esc_html__( 'Prenda #%d', 'woo-config' ), $index );
            }

            $output .= '<div class="wooconfig-cart-badges" style="margin-top: 6px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">';
            
            // High-visibility Player Identity Badge
            $output .= '<span style="background: #111827; color: #94ec00; font-weight: 800; font-size: 12px; padding: 3px 9px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px; letter-spacing: 0.3px; box-shadow: 0 1px 2px rgba(0,0,0,0.08);">';
            $output .= '🎽 ' . $player_identity;
            $output .= '</span>';

            // Size badge
            if ( ! empty( $size ) ) {
                $output .= '<span style="background: #f3f4f6; color: #1f2937; border: 1px solid #d1d5db; font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 4px;">';
                $output .= sprintf( esc_html__( 'Talla: %s', 'woo-config' ), esc_html( strtoupper( $size ) ) );
                $output .= '</span>';
            }

            // Group / Sequence badge
            $output .= '<span style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-weight: 600; font-size: 11px; padding: 3px 8px; border-radius: 4px;">';
            $output .= sprintf( esc_html__( 'Prenda %d de %d', 'woo-config' ), $index, $total );
            $output .= '</span>';

            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
    }

    public static function filter_cart_item_price_html( $price_html, $cart_item, $cart_item_key ) {
        if ( empty( $cart_item['_wooconfig_group_id'] ) ) {
            return $price_html;
        }

        $flow = isset( $cart_item['_wooconfig_flow'] ) ? $cart_item['_wooconfig_flow'] : 'wizard';
        $product_id = isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0;
        $product_adicionales = get_post_meta( $product_id, '_wooconfig_adicionales', true );
        if ( ! is_array( $product_adicionales ) ) {
            $product_adicionales = array();
        }

        $pills_html = '';

        if ( 'wizard' === $flow && ! empty( $cart_item['_wooconfig_adicionales_raw'] ) && is_array( $cart_item['_wooconfig_adicionales_raw'] ) ) {
            foreach ( $cart_item['_wooconfig_adicionales_raw'] as $ad_key ) {
                if ( isset( $product_adicionales[$ad_key] ) ) {
                    $label = $product_adicionales[$ad_key]['label'];
                    $cost  = (float) $product_adicionales[$ad_key]['price'];
                    $pills_html .= '<span class="wooconfig-cart-ad-pill" style="display: inline-flex; align-items: center; background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: 700; white-space: nowrap;">+ ' . esc_html( $label ) . ' (' . wc_price( $cost ) . ')</span>';
                }
            }
        } elseif ( 'quick' === $flow && ! empty( $cart_item['_wooconfig_adicionales_counts'] ) && is_array( $cart_item['_wooconfig_adicionales_counts'] ) ) {
            foreach ( $cart_item['_wooconfig_adicionales_counts'] as $ad_key => $count ) {
                $count = absint( $count );
                if ( $count > 0 && isset( $product_adicionales[$ad_key] ) ) {
                    $label = $product_adicionales[$ad_key]['label'];
                    $cost  = (float) $product_adicionales[$ad_key]['price'];
                    $pills_html .= '<span class="wooconfig-cart-ad-pill" style="display: inline-flex; align-items: center; background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: 700; white-space: nowrap;">+ ' . $count . 'x ' . esc_html( $label ) . ' (' . wc_price( $count * $cost ) . ')</span>';
                }
            }
        }

        if ( ! empty( $pills_html ) ) {
            return '<div class="wooconfig-price-pills-wrap" style="display: flex; align-items: center; flex-wrap: wrap; gap: 6px;">' . $price_html . ' ' . $pills_html . '</div>';
        }

        return $price_html;
    }

    public static function render_cart_item_custom_data( $item_data, $cart_item ) {
        if ( empty( $cart_item['_wooconfig_group_id'] ) ) {
            return $item_data;
        }

        $flow       = isset( $cart_item['_wooconfig_flow'] ) ? $cart_item['_wooconfig_flow'] : 'wizard';
        $product_id = isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0;
        $product_adicionales = get_post_meta( $product_id, '_wooconfig_adicionales', true );
        if ( ! is_array( $product_adicionales ) ) {
            $product_adicionales = array();
        }

        if ( 'quick' === $flow ) {
            $qty = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;
            $item_data[] = array(
                'key'   => __( '📁 Tipo', 'woo-config' ),
                'value' => sprintf( __( 'Pedido Rápido (%d prendas)', 'woo-config' ), $qty ),
            );

            if ( ! empty( $cart_item['_wooconfig_adicionales_counts'] ) && is_array( $cart_item['_wooconfig_adicionales_counts'] ) ) {
                foreach ( $cart_item['_wooconfig_adicionales_counts'] as $ad_key => $count ) {
                    $count = absint( $count );
                    if ( $count > 0 && isset( $product_adicionales[$ad_key] ) ) {
                        $label = $product_adicionales[$ad_key]['label'];
                        $cost  = (float) $product_adicionales[$ad_key]['price'];
                        $item_data[] = array(
                            'key'   => '✨ ' . esc_html( $label ),
                            'value' => sprintf( __( '%d unidades (+%s)', 'woo-config' ), $count, html_entity_decode( wp_strip_all_tags( wc_price( $count * $cost ) ) ) ),
                        );
                    }
                }
            }
        } else {
            $dorsal = isset( $cart_item['_wooconfig_dorsal'] ) ? trim( $cart_item['_wooconfig_dorsal'] ) : '';
            $name   = isset( $cart_item['_wooconfig_player_name'] ) ? trim( $cart_item['_wooconfig_player_name'] ) : '';
            $index  = isset( $cart_item['_wooconfig_item_index'] ) ? absint( $cart_item['_wooconfig_item_index'] ) : 1;
            $total  = isset( $cart_item['_wooconfig_total_items'] ) ? absint( $cart_item['_wooconfig_total_items'] ) : 1;

            $player_str = '';
            if ( '' !== $dorsal && '' !== $name ) {
                $player_str = '#' . $dorsal . ' · ' . mb_strtoupper( $name, 'UTF-8' );
            } elseif ( '' !== $dorsal ) {
                $player_str = 'Dorsal #' . $dorsal;
            } elseif ( '' !== $name ) {
                $player_str = mb_strtoupper( $name, 'UTF-8' );
            }

            if ( ! empty( $player_str ) ) {
                $item_data[] = array(
                    'key'   => __( '🎽 Jugador', 'woo-config' ),
                    'value' => $player_str,
                );
            }

            $item_data[] = array(
                'key'   => __( '📋 Equipo', 'woo-config' ),
                'value' => sprintf( __( 'Prenda %d de %d', 'woo-config' ), $index, $total ),
            );

            if ( ! empty( $cart_item['_wooconfig_adicionales_raw'] ) && is_array( $cart_item['_wooconfig_adicionales_raw'] ) ) {
                foreach ( $cart_item['_wooconfig_adicionales_raw'] as $ad_key ) {
                    if ( isset( $product_adicionales[$ad_key] ) ) {
                        $label = $product_adicionales[$ad_key]['label'];
                        $cost  = (float) $product_adicionales[$ad_key]['price'];
                        $item_data[] = array(
                            'key'   => '✨ ' . esc_html( $label ),
                            'value' => '(+' . html_entity_decode( wp_strip_all_tags( wc_price( $cost ) ) ) . ')',
                        );
                    }
                }
            }
        }

        if ( ! empty( $cart_item['_wooconfig_design_desc'] ) ) {
            $item_data[] = array(
                'key'   => __( '📝 Diseño', 'woo-config' ),
                'value' => esc_html( $cart_item['_wooconfig_design_desc'] ),
            );
        }

        if ( ! empty( $cart_item['_wooconfig_files'] ) && is_array( $cart_item['_wooconfig_files'] ) ) {
            $file_names = wp_list_pluck( $cart_item['_wooconfig_files'], 'original_name' );
            $item_data[] = array(
                'key'   => __( '📁 Adjuntos', 'woo-config' ),
                'value' => esc_html( implode( ', ', $file_names ) ),
            );
        }

        return $item_data;
    }

    public static function filter_cart_item_quantity( $product_quantity, $cart_item_key, $cart_item = array() ) {
        if ( is_array( $cart_item ) && ! empty( $cart_item['_wooconfig_group_id'] ) ) {
            $qty = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;
            return '<span class="wooconfig-locked-qty" style="font-weight: 700; font-size: 15px; padding: 4px 8px; display: inline-block;">' . $qty . '</span>';
        }
        return $product_quantity;
    }

    public static function prevent_cart_item_qty_change( $passed, $cart_item_key, $values, $quantity ) {
        if ( ! empty( $values['_wooconfig_group_id'] ) ) {
            if ( (int) $quantity !== (int) $values['quantity'] ) {
                wc_add_notice( __( 'Los pedidos configurados para equipo tienen cantidades fijas validadas. Si deseas modificar la cantidad, por favor reconfigura el producto.', 'woo-config' ), 'error' );
                return false;
            }
        }
        return $passed;
    }

    public static function filter_cart_item_permalink( $permalink, $cart_item, $cart_item_key ) {
        if ( ! empty( $cart_item['_wooconfig_group_id'] ) || ! empty( $cart_item['_wooconfig_flow'] ) ) {
            return false;
        }
        return $permalink;
    }

    public static function validate_cart_items() {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return true;
        }

        $min_units = (int) get_option( 'wooconfig_min_units', get_option( 'wooconfig_default_min_units', 1 ) );
        if ( $min_units <= 1 ) {
            return true;
        }

        $configured_count = 0;
        if ( WC()->cart && ! WC()->cart->is_empty() ) {
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                if ( ! empty( $cart_item['_wooconfig_group_id'] ) || ! empty( $cart_item['_wooconfig_flow'] ) ) {
                    $configured_count += isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;
                }
            }
        }

        if ( $configured_count > 0 && $configured_count < $min_units ) {
            $needed = $min_units - $configured_count;
            wc_add_notice(
                sprintf(
                    __( 'El pedido mínimo para confección es de <strong>%d prendas en total</strong> en el carrito. Actualmente tienes <strong>%d</strong> (te faltan %d). Puedes combinar distintos productos o modelos para alcanzar el mínimo.', 'woo-config' ),
                    $min_units,
                    $configured_count,
                    $needed
                ),
                'error'
            );
            return false;
        }

        return true;
    }
}

WC_Config_Cart::init();
