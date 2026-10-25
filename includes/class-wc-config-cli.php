<?php
/**
 * Comandos WP-CLI de vk7k-woo-config.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VK7K_WConfig_CLI {

    public static function init() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::add_command( 'vk7k-wconfig', __CLASS__ );
        }
    }

    /**
     * Migra las claves antiguas (`_wooconfig_*` y las claves sin prefijo) a `_vk7k_wconfig_*`.
     *
     * Recorre pedidos, líneas de pedido y productos. Copia, no borra. Idempotente.
     *
     * ## OPTIONS
     * [--dry-run]
     * : Informa sin escribir.
     *
     * ## EXAMPLES
     *     wp vk7k-wconfig meta-migrate --dry-run
     *     wp vk7k-wconfig meta-migrate
     *
     * @subcommand meta-migrate
     */
    public function meta_migrate( $args = array(), $assoc_args = array() ) {
        $dry    = is_array( $assoc_args ) && in_array( 'dry-run', array_keys( $assoc_args ), true );
        $report = VK7K_WConfig_Meta::migrate( $dry );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( wp_json_encode( array(
                'dry_run'  => (bool) $dry,
                'scanned'  => $report['scanned'],
                'migrated' => $report['migrated'],
                'skipped'  => $report['skipped'],
                'by_key'   => $report['by_key'],
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
        }
    }
}

VK7K_WConfig_CLI::init();
