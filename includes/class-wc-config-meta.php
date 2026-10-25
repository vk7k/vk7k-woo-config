<?php
/**
 * Claves de metadatos de vk7k-woo-config.
 *
 * Convención: `_vk7k_wconfig_*` en pedidos, líneas y productos.
 *
 * Antes convivían tres formatos: `_wooconfig_*`, claves sin prefijo (`Talla`, `Dorsal (#)`)
 * y —peor— claves construidas con la cadena traducida ( `__( 'Talla', 'woo-config' )` ),
 * de modo que cambiar el idioma del sitio dejaba los datos ilegibles. Aquí queda todo con
 * clave fija y la etiqueta legible se aplica sólo al mostrar (format_item_meta()).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VK7K_WConfig_Meta {

    const PREFIX = '_vk7k_wconfig_';

    /**
     * Campos de una línea de prenda: clave nueva => claves antiguas posibles.
     *
     * @return array
     */
    public static function item_map() {
        return array(
            self::PREFIX . 'talla'               => array( '_wooconfig_size', '_vk7k_wconfig_size', 'Talla' ),
            self::PREFIX . 'dorsal'              => array( '_wooconfig_dorsal', 'Dorsal (#)' ),
            self::PREFIX . 'nombre'              => array( '_wooconfig_player_name', '_vk7k_wconfig_player_name', 'Nombre / Alias' ),
            self::PREFIX . 'adicionales_list'    => array( '_wooconfig_adicionales_list', 'Adicionales' ),
            self::PREFIX . 'color'               => array( '_wooconfig_color', 'Color' ),
            self::PREFIX . 'adicionales_summary' => array( '_wooconfig_adicionales_summary', 'Adicionales incluidos' ),
        );
    }

    /**
     * Resto de claves (pedido, producto y líneas internas): clave nueva => clave antigua.
     *
     * @return array
     */
    public static function map() {
        $keys = array(
            'adicionales', 'adicionales_counts', 'adicionales_raw', 'adicional_cuello_enabled',
            'adicional_cuello_label', 'adicional_cuello_price', 'adicional_manga_enabled',
            'adicional_manga_label', 'adicional_manga_price', 'allow_full_payment',
            'balance_order_id', 'balance_paid_date', 'balance_status', 'balance_unit_due',
            'base_price', 'btn_text', 'custom_price', 'deposit_enabled', 'deposit_percent',
            'deposit_unit_price', 'design_desc', 'files', 'files_design', 'files_players',
            'files_sponsors', 'flow', 'full_unit_price', 'group_id', 'group_title',
            'has_deposit', 'is_balance_order', 'is_deposit', 'is_wholesale', 'item_index',
            'min_units', 'order_balance_total', 'order_deposit_total', 'order_full_total',
            'order_groups', 'parent_order_id', 'payment_mode', 'preferred_contact',
            'show_widget_size_guide', 'size_guide_image', 'size_guide_url', 'total_items',
            'type', 'upload_token', 'wholesale_applied', 'wholesale_min_qty',
            'wholesale_price', 'wholesale_reason',
        );

        $out = array();
        foreach ( $keys as $k ) {
            $out[ self::PREFIX . $k ] = '_wooconfig_' . $k;
        }

        return $out;
    }

    /**
     * Lee una clave lógica con respaldo en la antigua.
     *
     * @param WC_Data $object  Objeto.
     * @param string  $name    Nombre corto.
     * @param mixed   $default Valor por defecto.
     * @return mixed
     */
    public static function get( $object, $name, $default = '' ) {
        if ( ! $object instanceof WC_Data ) {
            return $default;
        }

        $key = self::PREFIX . $name;
        $val = $object->get_meta( $key );

        if ( self::is_empty( $val ) ) {
            $map = self::map();
            if ( ! empty( $map[ $key ] ) ) {
                $legacy = $object->get_meta( $map[ $key ] );
                if ( ! self::is_empty( $legacy ) ) {
                    return $legacy;
                }
            }
        }

        return self::is_empty( $val ) ? $default : $val;
    }

    /**
     * Campo de una línea (talla, dorsal, nombre, adicionales, color).
     *
     * @param WC_Order_Item $item  Línea.
     * @param string        $field Campo.
     * @return mixed
     */
    public static function item_field( $item, $field ) {
        if ( ! $item instanceof WC_Order_Item ) {
            return '';
        }

        $map = self::item_map();
        $key = self::PREFIX . $field;

        if ( empty( $map[ $key ] ) ) {
            return '';
        }

        foreach ( array_merge( array( $key ), $map[ $key ] ) as $candidate ) {
            $val = $item->get_meta( $candidate );
            if ( ! self::is_empty( $val ) ) {
                return $val;
            }
        }

        return '';
    }

    /**
     * ¿Vacío a efectos prácticos?
     *
     * @param mixed $val Valor.
     * @return bool
     */
    private static function is_empty( $val ) {
        return '' === $val || null === $val || false === $val || array() === $val;
    }

    /**
     * Traduce las claves internas a la etiqueta legible al mostrar artículos.
     *
     * WooCommerce oculta las claves que empiezan con `_`, así que aquí se reinyectan con
     * su etiqueta: el cliente y el taller siguen viendo Talla, Dorsal, Nombre y Adicionales.
     *
     * @param array         $formatted Meta formateada.
     * @param WC_Order_Item $item      Línea.
     * @return array
     */
    public static function format_item_meta( $formatted, $item ) {
        if ( ! is_array( $formatted ) || ! $item instanceof WC_Order_Item ) {
            return $formatted;
        }

        $labels = array(
            'talla'               => __( 'Talla', 'woo-config' ),
            'dorsal'              => __( 'Dorsal (#)', 'woo-config' ),
            'nombre'              => __( 'Nombre / Alias', 'woo-config' ),
            'adicionales_list'    => __( 'Adicionales', 'woo-config' ),
            'adicionales_summary' => __( 'Adicionales incluidos', 'woo-config' ),
            'color'               => __( 'Color', 'woo-config' ),
        );

        foreach ( $labels as $field => $label ) {
            $val = self::item_field( $item, $field );

            if ( self::is_empty( $val ) ) {
                continue;
            }

            $text = is_array( $val ) ? implode( ', ', array_filter( array_map( 'strval', $val ) ) ) : (string) $val;

            if ( '' === trim( $text ) ) {
                continue;
            }

            $meta               = new stdClass();
            $meta->key          = $label;
            $meta->value        = $text;
            $meta->display_key  = $label;
            $meta->display_value = $text;

            $formatted[] = $meta;
        }

        return $formatted;
    }

    /**
     * Copia las claves antiguas a las nuevas en pedidos, líneas y productos. Idempotente.
     *
     * @param bool $dry_run Sólo informar.
     * @return array
     */
    public static function migrate( $dry_run = false ) {
        global $wpdb;

        // wc_orders_meta usa `id` como PK; las tablas heredadas usan `meta_id`.
        $tables = array(
            array( $wpdb->prefix . 'wc_orders_meta', 'order_id', 'id' ),
            array( $wpdb->prefix . 'woocommerce_order_itemmeta', 'order_item_id', 'meta_id' ),
        );

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->postmeta}'" ) === $wpdb->postmeta ) {
            $tables[] = array( $wpdb->postmeta, 'post_id', 'meta_id' );
        }

        $pairs = array();
        foreach ( self::map() as $new => $old ) {
            $pairs[] = array( $new, $old );
        }
        foreach ( self::item_map() as $new => $olds ) {
            foreach ( $olds as $old ) {
                $pairs[] = array( $new, $old );
            }
        }

        $report = array( 'scanned' => 0, 'migrated' => 0, 'skipped' => 0, 'failed' => 0, 'deduped' => 0, 'by_key' => array() );

        foreach ( $pairs as $pair ) {
            list( $new, $old ) = $pair;

            if ( '' === $old || $new === $old ) {
                continue;
            }

            foreach ( $tables as $tbl ) {
                list( $table, $id_column ) = $tbl;

                $rows = $wpdb->get_results(
                    $wpdb->prepare( "SELECT {$id_column} AS oid, meta_value FROM {$table} WHERE meta_key = %s", $old )
                );

                foreach ( $rows as $row ) {
                    $report['scanned']++;

                    $exists = $wpdb->get_var(
                        $wpdb->prepare( "SELECT 1 FROM {$table} WHERE meta_key = %s AND {$id_column} = %d LIMIT 1", $new, $row->oid )
                    );

                    if ( $exists ) {
                        $report['skipped']++;
                        continue;
                    }

                    if ( ! $dry_run ) {
                        $ok = $wpdb->insert(
                            $table,
                            array( $id_column => $row->oid, 'meta_key' => $new, 'meta_value' => $row->meta_value )
                        );

                        if ( ! $ok ) {
                            $report['failed']++;
                            continue;
                        }

                        wp_cache_delete( $row->oid, 'orders' );
                    }

                    $report['migrated']++;
                    $report['by_key'][ $old ] = ( $report['by_key'][ $old ] ?? 0 ) + 1;
                }
            }
        }

        // Limpieza: una sola fila por (documento, clave nueva). No repite trabajo si ya está limpio.
        foreach ( self::known_new_keys() as $key ) {
            foreach ( $tables as $tbl ) {
                list( $table, $id_column, $pk_column ) = $tbl;

                $dups = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT {$pk_column} AS pk, {$id_column} AS oid FROM {$table} WHERE meta_key = %s ORDER BY {$id_column}, {$pk_column}",
                        $key
                    )
                );

                $seen = array();
                foreach ( $dups as $d ) {
                    if ( isset( $seen[ $d->oid ] ) ) {
                        if ( ! $dry_run ) {
                            $wpdb->delete( $table, array( $pk_column => $d->pk ) );
                            wp_cache_delete( $d->oid, 'orders' );
                        }
                        $report['deduped']++;
                    } else {
                        $seen[ $d->oid ] = true;
                    }
                }
            }
        }

        return $report;
    }

    /**
     * Todas las claves nuevas que administra la clase.
     *
     * @return array
     */
    public static function known_new_keys() {
        return array_merge( array_keys( self::map() ), array_keys( self::item_map() ) );
    }
}
