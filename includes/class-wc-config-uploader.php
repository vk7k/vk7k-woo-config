<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Config_Uploader {

    const TOKEN_TTL             = 172800; // 48 hours.
    const MAX_FILES_REQUEST     = 10;
    const MAX_FILES_TOKEN       = 20;
    const MAX_BYTES_TOKEN       = 104857600; // 100 MB.
    const MAX_FILES_IP_HOUR     = 60;
    const MAX_BYTES_IP_HOUR     = 314572800; // 300 MB.
    const MAX_BYTES_GLOBAL_DAY  = 2147483648; // 2 GB.
    const SIGNED_URL_TTL        = 7776000; // 90 days.
    const TEMP_URL_TTL          = 172800; // 48 hours.

    public static function init() {
        add_action( 'wp_ajax_wooconfig_upload_files', array( __CLASS__, 'handle_ajax_upload' ) );
        add_action( 'wp_ajax_nopriv_wooconfig_upload_files', array( __CLASS__, 'handle_ajax_upload' ) );

        add_action( 'wp_ajax_wooconfig_upload_file', array( __CLASS__, 'handle_ajax_upload' ) );
        add_action( 'wp_ajax_nopriv_wooconfig_upload_file', array( __CLASS__, 'handle_ajax_upload' ) );

        add_action( 'wp_ajax_wooconfig_delete_file', array( __CLASS__, 'handle_ajax_delete' ) );
        add_action( 'wp_ajax_nopriv_wooconfig_delete_file', array( __CLASS__, 'handle_ajax_delete' ) );

        add_action( 'wp_ajax_wooconfig_download', array( __CLASS__, 'handle_download' ) );
        add_action( 'wp_ajax_nopriv_wooconfig_download', array( __CLASS__, 'handle_download' ) );
    }

    public static function get_upload_dirs() {
        $upload_dir  = wp_upload_dir();
        $legacy_base = $upload_dir['basedir'] . '/woo-config-uploads';
        $legacy_url  = $upload_dir['baseurl'] . '/woo-config-uploads';
        $base_dir    = self::get_private_base_dir();

        return array(
            'base_dir'          => $base_dir,
            'base_url'          => '',
            'temp_dir'          => $base_dir . '/temp',
            'temp_url'          => '',
            'orders_dir'        => $base_dir . '/orders',
            'orders_url'        => '',
            'legacy_base_dir'   => $legacy_base,
            'legacy_base_url'   => $legacy_url,
            'legacy_temp_dir'   => $legacy_base . '/temp',
            'legacy_temp_url'   => $legacy_url . '/temp',
            'legacy_orders_dir' => $legacy_base . '/orders',
            'legacy_orders_url' => $legacy_url . '/orders',
        );
    }

    /**
     * Store new files outside the document root whenever the host allows it.
     * This keeps them unreachable even when the web server ignores .htaccess.
     */
    private static function get_private_base_dir() {
        static $base_dir = null;

        if ( null !== $base_dir ) {
            return $base_dir;
        }

        if ( defined( 'WOOCONFIG_PRIVATE_DIR' ) && WOOCONFIG_PRIVATE_DIR ) {
            $base_dir = rtrim( (string) WOOCONFIG_PRIVATE_DIR, '/\\' );
            if ( ! is_dir( $base_dir ) ) {
                @wp_mkdir_p( $base_dir );
            }
            if ( is_dir( $base_dir ) && is_writable( $base_dir ) ) {
                return $base_dir;
            }
        }

        $upload_dir = wp_upload_dir();
        $dir_key    = get_option( 'wooconfig_private_dir_key' );

        if ( empty( $dir_key ) ) {
            $dir_key = wp_generate_password( 16, false, false );
            update_option( 'wooconfig_private_dir_key', $dir_key, false );
        }

        $candidates = array(
            dirname( ABSPATH ) . '/woo-config-private',
            WP_CONTENT_DIR . '/woo-config-private-' . $dir_key,
        );

        foreach ( $candidates as $candidate ) {
            if ( ! is_dir( $candidate ) ) {
                @wp_mkdir_p( $candidate );
            }
            if ( is_dir( $candidate ) && is_writable( $candidate ) ) {
                $base_dir = rtrim( $candidate, '/\\' );
                return $base_dir;
            }
        }

        // Last resort: inside uploads, protected with .htaccess where available.
        $base_dir = $upload_dir['basedir'] . '/woo-config-uploads/private';
        @wp_mkdir_p( $base_dir );

        return $base_dir;
    }

    public static function check_upload_directory_permissions() {
        $upload_dir = wp_upload_dir();
        $errors     = array();

        if ( ! empty( $upload_dir['error'] ) ) {
            $errors[] = sprintf( __( 'Directorio principal de subidas de WordPress no accesible: %s', 'woo-config' ), $upload_dir['error'] );
        }

        $dirs = self::get_upload_dirs();
        $paths_to_check = array(
            'Base'    => $dirs['base_dir'],
            'Temp'    => $dirs['temp_dir'],
            'Pedidos' => $dirs['orders_dir'],
        );

        foreach ( $paths_to_check as $label => $path ) {
            if ( ! file_exists( $path ) ) {
                $created = wp_mkdir_p( $path );
                if ( ! $created ) {
                    $errors[] = sprintf( __( 'No se pudo crear la carpeta [%s]: <code>%s</code>. Verifica los permisos del servidor.', 'woo-config' ), $label, esc_html( $path ) );
                    continue;
                }
            }

            if ( ! is_writable( $path ) ) {
                $errors[] = sprintf( __( 'La carpeta [%s] no tiene permisos de escritura: <code>%s</code>. Por favor establece permisos 755 o 775.', 'woo-config' ), $label, esc_html( $path ) );
                continue;
            }

            self::protect_private_directory( $path );
        }

        return $errors;
    }

    public static function get_htaccess_content() {
        return "# Disable script execution\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|phps|pl|py|cgi|sh)$\">\n\t<IfModule mod_authz_core.c>\n\t\tRequire all denied\n\t</IfModule>\n\t<IfModule !mod_authz_core.c>\n\t\tOrder Deny,Allow\n\t\tDeny from all\n\t</IfModule>\n</FilesMatch>\n";
    }

    private static function protect_directory( $path ) {
        if ( ! is_dir( $path ) || ! is_writable( $path ) ) {
            return;
        }

        if ( ! file_exists( $path . '/index.php' ) ) {
            @file_put_contents( $path . '/index.php', '<?php // Silence is golden' );
        }

        $htaccess = $path . '/.htaccess';
        $current  = file_exists( $htaccess ) ? (string) file_get_contents( $htaccess ) : '';
        $desired  = self::get_htaccess_content();

        // Replace our previous Apache 2.2 syntax with the portable version.
        if ( $current !== $desired && ( empty( $current ) || false !== strpos( $current, '# Disable script execution' ) ) ) {
            @file_put_contents( $htaccess, $desired );
        }
    }

    public static function get_private_htaccess_content() {
        return "# WooConfig private files\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder Deny,Allow\n\tDeny from all\n</IfModule>\n";
    }

    private static function protect_private_directory( $path ) {
        if ( ! is_dir( $path ) || ! is_writable( $path ) ) {
            return;
        }

        if ( ! file_exists( $path . '/index.php' ) ) {
            @file_put_contents( $path . '/index.php', '<?php // Silence is golden' );
        }

        $htaccess = $path . '/.htaccess';
        $current  = file_exists( $htaccess ) ? (string) file_get_contents( $htaccess ) : '';
        $desired  = self::get_private_htaccess_content();

        // Replace our previous script-only protection with deny-all.
        if ( $current !== $desired && ( empty( $current ) || false !== strpos( $current, '# Disable script execution' ) || false !== strpos( $current, '# WooConfig private files' ) ) ) {
            @file_put_contents( $htaccess, $desired );
        }
    }


    /**
     * Normalize an upload ownership token (hex string generated by the browser).
     */
    public static function sanitize_upload_token( $token ) {
        $token = is_string( $token ) ? strtolower( trim( $token ) ) : '';
        return preg_match( '/^[a-f0-9]{32,64}$/', $token ) ? $token : '';
    }

    private static function get_token_registry( $token ) {
        if ( empty( $token ) ) {
            return array();
        }

        $registry = get_transient( 'wooconfig_uploads_' . $token );
        return is_array( $registry ) ? $registry : array();
    }

    private static function save_token_registry( $token, $registry ) {
        if ( empty( $token ) ) {
            return;
        }

        set_transient( 'wooconfig_uploads_' . $token, $registry, self::TOKEN_TTL );
    }

    private static function get_token_usage( $token ) {
        $registry = self::get_token_registry( $token );
        $bytes    = 0;

        foreach ( $registry as $entry ) {
            $bytes += isset( $entry['file_size'] ) ? (int) $entry['file_size'] : 0;
        }

        return array(
            'files' => count( $registry ),
            'bytes' => $bytes,
        );
    }

    /**
     * Build the safe file payload returned to the frontend.
     */
    private static function format_file_data( $entry, $location = 'temp', $context_id = 0 ) {
        $size        = isset( $entry['file_size'] ) ? (int) $entry['file_size'] : 0;
        $stored_name = isset( $entry['stored_name'] ) ? $entry['stored_name'] : '';
        $file_url    = '';

        if ( ! empty( $stored_name ) ) {
            $file_url = 'order' === $location
                ? self::get_signed_file_url( 'order', $context_id, $stored_name )
                : self::get_signed_file_url( 'temp', $context_id, $stored_name );
        }

        return array(
            'file_id'        => isset( $entry['file_id'] ) ? $entry['file_id'] : '',
            'original_name'  => isset( $entry['original_name'] ) ? $entry['original_name'] : '',
            'stored_name'    => $stored_name,
            'file_url'       => $file_url,
            'file_size'      => $size,
            'size_formatted' => size_format( $size, 2 ),
            'extension'      => isset( $entry['extension'] ) ? $entry['extension'] : '',
            'is_image'       => ! empty( $entry['is_image'] ),
        );
    }

    /**
     * Build a capability URL valid without login (for emails, workshop sheets and previews).
     */
    public static function get_signed_file_url( $type, $id, $stored_name, $ttl = null ) {
        $type        = in_array( $type, array( 'temp', 'order' ), true ) ? $type : 'order';
        $stored_name = sanitize_file_name( $stored_name );

        if ( empty( $stored_name ) ) {
            return '';
        }

        if ( 'temp' === $type ) {
            $id = self::sanitize_upload_token( $id );
            if ( null === $ttl ) {
                $ttl = self::TEMP_URL_TTL;
            }
        } else {
            $id = absint( $id );
            if ( null === $ttl ) {
                $ttl = (int) apply_filters( 'wooconfig_signed_url_ttl', self::SIGNED_URL_TTL );
            }
        }

        if ( empty( $id ) ) {
            return '';
        }

        $ttl = max( 60, (int) $ttl );
        $exp = time() + $ttl;
        $sig = hash_hmac( 'sha256', $type . '|' . $id . '|' . $stored_name . '|' . $exp, wp_salt( 'auth' ) );

        return add_query_arg(
            array(
                'action' => 'wooconfig_download',
                'type'   => $type,
                'id'     => $id,
                'file'   => $stored_name,
                'exp'    => $exp,
                'sig'    => $sig,
            ),
            admin_url( 'admin-ajax.php' )
        );
    }

    public static function get_order_file_url( $order_id, $file ) {
        $stored_name = isset( $file['stored_name'] ) ? sanitize_file_name( $file['stored_name'] ) : '';

        if ( empty( $stored_name ) && ! empty( $file['file_url'] ) ) {
            $query = array();
            wp_parse_str( (string) wp_parse_url( $file['file_url'], PHP_URL_QUERY ), $query );

            if ( ! empty( $query['file'] ) ) {
                $stored_name = sanitize_file_name( $query['file'] );
            } else {
                $path        = wp_parse_url( $file['file_url'], PHP_URL_PATH );
                $stored_name = sanitize_file_name( basename( $path ) );
            }
        }

        if ( empty( $stored_name ) || empty( $order_id ) ) {
            return '';
        }

        return self::get_signed_file_url( 'order', $order_id, $stored_name );
    }

    /**
     * Check that a stored filename really belongs to the order before serving it.
     */
    private static function order_has_file( $order, $stored_name ) {
        $stored_name = sanitize_file_name( $stored_name );
        if ( empty( $stored_name ) || ! $order ) {
            return false;
        }

        $groups = $order->get_meta( '_wooconfig_order_groups' );
        if ( is_array( $groups ) ) {
            foreach ( $groups as $group ) {
                if ( empty( $group['files'] ) || ! is_array( $group['files'] ) ) {
                    continue;
                }
                foreach ( $group['files'] as $file ) {
                    $file_name = isset( $file['stored_name'] ) ? sanitize_file_name( $file['stored_name'] ) : '';
                    if ( empty( $file_name ) && ! empty( $file['file_url'] ) ) {
                        $query = array();
                        wp_parse_str( (string) wp_parse_url( $file['file_url'], PHP_URL_QUERY ), $query );
                        $file_name = ! empty( $query['file'] )
                            ? sanitize_file_name( $query['file'] )
                            : sanitize_file_name( basename( (string) wp_parse_url( $file['file_url'], PHP_URL_PATH ) ) );
                    }
                    if ( $file_name === $stored_name ) {
                        return true;
                    }
                }
            }
        }

        foreach ( $order->get_items() as $item ) {
            $files = $item->get_meta( '_wooconfig_files' );
            if ( empty( $files ) || ! is_array( $files ) ) {
                continue;
            }
            foreach ( $files as $file ) {
                $file_name = isset( $file['stored_name'] ) ? sanitize_file_name( $file['stored_name'] ) : '';
                if ( $file_name === $stored_name ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function handle_download() {
        $type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
        $id   = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
        $file = isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '';
        $exp  = isset( $_GET['exp'] ) ? absint( $_GET['exp'] ) : 0;
        $sig  = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';

        if ( ! in_array( $type, array( 'temp', 'order' ), true ) || empty( $file ) || empty( $exp ) || empty( $sig ) ) {
            self::deny_download();
        }

        if ( $exp < time() || ! preg_match( '/^[a-f0-9]{64}$/', $sig ) ) {
            self::deny_download();
        }

        if ( 'temp' === $type ) {
            $id = self::sanitize_upload_token( $id );
        } else {
            $id = absint( $id );
        }

        if ( empty( $id ) ) {
            self::deny_download();
        }

        $expected = hash_hmac( 'sha256', $type . '|' . $id . '|' . $file . '|' . $exp, wp_salt( 'auth' ) );

        if ( ! hash_equals( $expected, $sig ) ) {
            self::deny_download();
        }

        if ( 'temp' === $type ) {
            $registry = self::get_token_registry( $id );
            if ( ! isset( $registry[ $file ] ) ) {
                self::deny_download();
            }
        } else {
            $order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : false;

            if ( ! $order || ! self::order_has_file( $order, $file ) ) {
                self::deny_download();
            }
        }

        $real_path = self::resolve_stored_file( $type, $id, $file );

        if ( ! $real_path ) {
            self::deny_download();
        }

        $ext      = strtolower( pathinfo( $real_path, PATHINFO_EXTENSION ) );
        $is_image = in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true );
        $mime     = self::get_mime_for_extension( $ext );
        $filename = sanitize_file_name( basename( $real_path ) );

        while ( ob_get_level() ) {
            ob_end_clean();
        }

        header( 'Content-Type: ' . $mime );
        header( 'Content-Length: ' . (string) filesize( $real_path ) );
        header( 'Content-Disposition: ' . ( $is_image ? 'inline' : 'attachment' ) . '; filename="' . str_replace( '"', '', $filename ) . '"' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
        header( 'Referrer-Policy: no-referrer' );
        header( 'Cache-Control: private, no-store, max-age=0' );
        header( 'Pragma: no-cache' );

        readfile( $real_path );
        exit;
    }

    private static function deny_download() {
        wp_die(
            esc_html__( 'Enlace no válido o expirado.', 'woo-config' ),
            esc_html__( 'Acceso denegado', 'woo-config' ),
            array( 'response' => 403 )
        );
    }

    /**
     * Resolve a stored file in private storage first, then in legacy public storage.
     */
    private static function resolve_stored_file( $type, $id, $file ) {
        $dirs = self::get_upload_dirs();

        if ( 'temp' === $type ) {
            $bases = array( $dirs['temp_dir'], $dirs['legacy_temp_dir'] );
        } else {
            $bases = array(
                $dirs['orders_dir'] . '/' . $id,
                $dirs['legacy_orders_dir'] . '/' . $id,
            );
        }

        foreach ( $bases as $base ) {
            if ( empty( $base ) ) {
                continue;
            }

            $real_base = realpath( $base );
            $real_path = realpath( $base . '/' . $file );

            if ( $real_base && $real_path && 0 === strpos( $real_path, $real_base . DIRECTORY_SEPARATOR ) && is_file( $real_path ) ) {
                return $real_path;
            }
        }

        return false;
    }

    private static function get_mime_for_extension( $ext ) {
        $mimes = array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'pdf'  => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls'  => 'application/vnd.ms-excel',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ai'   => 'application/postscript',
            'eps'  => 'application/postscript',
            'zip'  => 'application/zip',
        );

        return isset( $mimes[ $ext ] ) ? $mimes[ $ext ] : 'application/octet-stream';
    }

    /**
     * Validate that every file referenced by the cart belongs to the upload token.
     *
     * @return array|WP_Error
     */
    public static function validate_owned_files( $files, $upload_token ) {
        if ( empty( $files ) ) {
            return array();
        }

        if ( ! is_array( $files ) ) {
            return new WP_Error( 'wooconfig_invalid_files', __( 'Los archivos enviados no son válidos.', 'woo-config' ) );
        }

        $upload_token = self::sanitize_upload_token( $upload_token );
        if ( empty( $upload_token ) ) {
            return new WP_Error( 'wooconfig_upload_token', __( 'La sesión de subida expiró. Recarga la página y vuelve a subir tus archivos.', 'woo-config' ) );
        }

        $registry = self::get_token_registry( $upload_token );
        if ( empty( $registry ) ) {
            return new WP_Error( 'wooconfig_upload_token', __( 'La sesión de subida expiró. Recarga la página y vuelve a subir tus archivos.', 'woo-config' ) );
        }

        $valid     = array();
        $seen      = array();
        $dirs      = self::get_upload_dirs();
        $temp_dirs = array( $dirs['temp_dir'], $dirs['legacy_temp_dir'] );

        foreach ( $files as $file ) {
            $stored_name = isset( $file['stored_name'] ) ? sanitize_file_name( $file['stored_name'] ) : '';
            $exists      = false;

            if ( ! empty( $stored_name ) && isset( $registry[ $stored_name ] ) ) {
                foreach ( $temp_dirs as $temp_dir ) {
                    if ( is_file( $temp_dir . '/' . $stored_name ) ) {
                        $exists = true;
                        break;
                    }
                }
            }

            if ( empty( $stored_name ) || ! $exists ) {
                return new WP_Error( 'wooconfig_invalid_file', __( 'Uno de los archivos ya no está disponible. Vuelve a subirlo e inténtalo de nuevo.', 'woo-config' ) );
            }

            if ( isset( $seen[ $stored_name ] ) ) {
                continue;
            }

            $seen[ $stored_name ] = true;
            $valid[]              = self::format_file_data( $registry[ $stored_name ], 'temp', $upload_token );
        }

        return $valid;
    }

    private static function get_client_ip() {
        $ip = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        // Only trust proxy headers when the site explicitly opts in.
        if ( apply_filters( 'wooconfig_trust_proxy_headers', false ) && class_exists( 'WC_Geolocation' ) ) {
            $forwarded_ip = WC_Geolocation::get_ip_address();
            if ( ! empty( $forwarded_ip ) ) {
                $ip = $forwarded_ip;
            }
        }

        return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
    }

    private static function get_rate_state( $ip ) {
        $key   = 'wooconfig_rate_' . md5( $ip );
        $state = get_transient( $key );

        if ( ! is_array( $state ) || empty( $state['start'] ) ) {
            $state = array( 'files' => 0, 'bytes' => 0, 'start' => time() );
        } elseif ( ( time() - (int) $state['start'] ) >= HOUR_IN_SECONDS ) {
            $state = array( 'files' => 0, 'bytes' => 0, 'start' => time() );
        }

        return array( $key, $state );
    }

    private static function check_rate_limit( $files_count, $bytes ) {
        $ip = self::get_client_ip();
        list( , $state ) = self::get_rate_state( $ip );

        $max_files = (int) apply_filters( 'wooconfig_rate_limit_files', self::MAX_FILES_IP_HOUR, $ip );
        $max_bytes = (int) apply_filters( 'wooconfig_rate_limit_bytes', self::MAX_BYTES_IP_HOUR, $ip );

        return ( $state['files'] + $files_count ) <= $max_files && ( $state['bytes'] + $bytes ) <= $max_bytes;
    }

    private static function bump_rate_limit( $files_count, $bytes ) {
        $ip = self::get_client_ip();
        list( $key, $state ) = self::get_rate_state( $ip );

        $state['files'] += (int) $files_count;
        $state['bytes'] += (int) $bytes;

        $ttl = max( 60, HOUR_IN_SECONDS - ( time() - (int) $state['start'] ) );
        set_transient( $key, $state, $ttl );
    }

    private static function check_global_limit( $bytes ) {
        $state = get_transient( 'wooconfig_global_usage_' . gmdate( 'Ymd' ) );
        if ( ! is_array( $state ) ) {
            $state = array( 'bytes' => 0 );
        }

        $max_bytes = (int) apply_filters( 'wooconfig_global_daily_bytes', self::MAX_BYTES_GLOBAL_DAY );

        return ( $state['bytes'] + $bytes ) <= $max_bytes;
    }

    private static function bump_global_limit( $bytes ) {
        $key   = 'wooconfig_global_usage_' . gmdate( 'Ymd' );
        $state = get_transient( $key );
        if ( ! is_array( $state ) ) {
            $state = array( 'bytes' => 0 );
        }

        $state['bytes'] += (int) $bytes;

        $ttl = max( 60, strtotime( 'tomorrow UTC' ) - time() );
        set_transient( $key, $state, $ttl );
    }

    /**
     * Validate extension + real MIME type, rejecting executable/script content.
     */
    private static function validate_file_type( $tmp_name, $name, $ext, $allowed_mimes ) {
        $dangerous_mimes = array(
            'text/html',
            'text/javascript',
            'application/javascript',
            'application/x-php',
            'text/x-php',
            'application/x-httpd-php',
            'application/x-sh',
            'application/x-csh',
            'application/x-msdownload',
            'application/x-executable',
            'application/x-dosexec',
        );

        $allowed_real_mimes = array(
            'jpg'  => array( 'image/jpeg' ),
            'jpeg' => array( 'image/jpeg' ),
            'png'  => array( 'image/png' ),
            'webp' => array( 'image/webp' ),
            'pdf'  => array( 'application/pdf' ),
            'xlsx' => array( 'application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ),
            'xls'  => array( 'application/vnd.ms-excel', 'application/CDFV2' ),
            'doc'  => array( 'application/msword', 'application/CDFV2' ),
            'docx' => array( 'application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ),
            'ai'   => array( 'application/postscript', 'application/pdf', 'application/illustrator' ),
            'eps'  => array( 'application/postscript', 'application/illustrator' ),
            'zip'  => array( 'application/zip', 'application/x-zip-compressed' ),
        );

        $real_mime = '';
        if ( function_exists( 'finfo_open' ) && is_file( $tmp_name ) ) {
            $finfo = finfo_open( FILEINFO_MIME_TYPE );
            if ( $finfo ) {
                $real_mime = finfo_file( $finfo, $tmp_name );
                finfo_close( $finfo );
            }
        }

        if ( ! empty( $real_mime ) && in_array( $real_mime, $dangerous_mimes, true ) ) {
            return false;
        }

        // WordPress does not recognize AI/EPS: require a valid finfo MIME type.
        if ( in_array( $ext, array( 'ai', 'eps' ), true ) ) {
            if ( empty( $real_mime ) || ! in_array( $real_mime, $allowed_real_mimes[ $ext ], true ) ) {
                return false;
            }
            return true;
        }

        $wp_filetype = wp_check_filetype_and_ext( $tmp_name, $name, $allowed_mimes );

        if ( empty( $wp_filetype['ext'] ) || empty( $wp_filetype['type'] ) ) {
            return false;
        }

        if ( strtolower( $wp_filetype['ext'] ) !== $ext ) {
            return false;
        }

        if ( in_array( $wp_filetype['type'], $dangerous_mimes, true ) ) {
            return false;
        }

        if ( ! empty( $real_mime ) && isset( $allowed_real_mimes[ $ext ] ) && ! in_array( $real_mime, $allowed_real_mimes[ $ext ], true ) ) {
            return false;
        }

        if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
            $image_info = @getimagesize( $tmp_name );
            if ( empty( $image_info['mime'] ) || ! in_array( $image_info['mime'], $allowed_real_mimes[ $ext ], true ) ) {
                return false;
            }
        }

        return true;
    }

    public static function handle_ajax_upload() {
        check_ajax_referer( 'wooconfig_nonce', 'nonce' );

        $upload_token = self::sanitize_upload_token( isset( $_POST['upload_token'] ) ? wp_unslash( $_POST['upload_token'] ) : '' );
        if ( empty( $upload_token ) ) {
            wp_send_json_error( array( 'message' => __( 'La sesión de subida expiró. Recarga la página e inténtalo de nuevo.', 'woo-config' ) ) );
        }

        if ( empty( $_FILES['files'] ) || ! isset( $_FILES['files']['name'] ) || ! is_array( $_FILES['files']['name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No se enviaron archivos.', 'woo-config' ) ) );
        }

        $files      = $_FILES['files'];
        $file_count = count( $files['name'] );
        $max_files  = (int) apply_filters( 'wooconfig_max_files_per_request', self::MAX_FILES_REQUEST );

        if ( $file_count < 1 ) {
            wp_send_json_error( array( 'message' => __( 'No se enviaron archivos.', 'woo-config' ) ) );
        }

        if ( $file_count > $max_files ) {
            wp_send_json_error( array( 'message' => sprintf( __( 'Puedes subir un máximo de %d archivos por vez.', 'woo-config' ), $max_files ) ) );
        }

        $dir_errors = self::check_upload_directory_permissions();
        if ( ! empty( $dir_errors ) ) {
            wp_send_json_error( array( 'message' => wp_strip_all_tags( implode( ' ', $dir_errors ) ) ) );
        }

        $dirs        = self::get_upload_dirs();
        $max_size_mb = (int) get_option( 'wooconfig_max_file_size_mb', 20 );
        $max_bytes   = $max_size_mb * 1024 * 1024;

        // Allowed extensions (SVG excluded to prevent Stored XSS vectors).
        $allowed_ext = array( 'jpg', 'jpeg', 'png', 'webp', 'pdf', 'xlsx', 'xls', 'doc', 'docx', 'ai', 'eps', 'zip' );

        $allowed_mimes = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
            'pdf'          => 'application/pdf',
            'xlsx'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls'          => 'application/vnd.ms-excel',
            'doc'          => 'application/msword',
            'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ai'           => 'application/postscript',
            'eps'          => 'application/postscript',
            'zip'          => 'application/zip',
        );

        $usage           = self::get_token_usage( $upload_token );
        $max_token_files = (int) apply_filters( 'wooconfig_max_files_per_token', self::MAX_FILES_TOKEN );
        $max_token_bytes = (int) apply_filters( 'wooconfig_max_bytes_per_token', self::MAX_BYTES_TOKEN );

        $prepared    = array();
        $total_bytes = 0;

        for ( $i = 0; $i < $file_count; $i++ ) {
            $name     = isset( $files['name'][ $i ] ) ? $files['name'][ $i ] : '';
            $tmp_name = isset( $files['tmp_name'][ $i ] ) ? $files['tmp_name'][ $i ] : '';
            $error    = isset( $files['error'][ $i ] ) ? $files['error'][ $i ] : UPLOAD_ERR_NO_FILE;

            if ( UPLOAD_ERR_OK !== $error || ! is_string( $name ) || empty( $name ) ) {
                continue;
            }

            if ( ! is_string( $tmp_name ) || ! is_uploaded_file( $tmp_name ) ) {
                continue;
            }

            $actual_size = @filesize( $tmp_name );
            if ( false === $actual_size || $actual_size <= 0 ) {
                continue;
            }

            if ( $actual_size > $max_bytes ) {
                wp_send_json_error( array( 'message' => sprintf( __( 'El archivo %s supera el tamaño máximo permitido de %dMB.', 'woo-config' ), sanitize_text_field( $name ), $max_size_mb ) ) );
            }

            $ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, $allowed_ext, true ) ) {
                wp_send_json_error( array( 'message' => sprintf( __( 'Tipo de archivo no permitido (%s). Solo se aceptan: %s', 'woo-config' ), sanitize_text_field( $ext ), implode( ', ', $allowed_ext ) ) ) );
            }

            if ( ! self::validate_file_type( $tmp_name, $name, $ext, $allowed_mimes ) ) {
                wp_send_json_error( array( 'message' => sprintf( __( 'Contenido de archivo sospechoso o no válido detectado para %s.', 'woo-config' ), sanitize_text_field( $name ) ) ) );
            }

            $safe_original_name = sanitize_file_name( $name );
            if ( empty( $safe_original_name ) ) {
                $safe_original_name = 'archivo.' . $ext;
            }

            $file_id         = wp_generate_uuid4();
            $stored_filename = $file_id . '_' . $safe_original_name;

            $prepared[] = array(
                'tmp_name'     => $tmp_name,
                'file_id'      => $file_id,
                'stored_name'  => $stored_filename,
                'original_name' => $safe_original_name,
                'extension'    => $ext,
                'file_size'    => (int) $actual_size,
                'is_image'     => in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ),
            );

            $total_bytes += (int) $actual_size;
        }

        if ( empty( $prepared ) ) {
            wp_send_json_error( array( 'message' => __( 'No se pudo guardar ningún archivo. Revisa el formato y los permisos de la carpeta de subidas.', 'woo-config' ) ) );
        }

        $prepared_count = count( $prepared );

        if ( ( $usage['files'] + $prepared_count ) > $max_token_files ) {
            wp_send_json_error( array( 'message' => sprintf( __( 'Alcanzaste el límite de %d archivos para esta sesión. Elimina algunos o finaliza tu pedido.', 'woo-config' ), $max_token_files ) ) );
        }

        if ( ( $usage['bytes'] + $total_bytes ) > $max_token_bytes ) {
            wp_send_json_error( array( 'message' => __( 'Alcanzaste el límite de almacenamiento para esta sesión. Elimina archivos o finaliza tu pedido.', 'woo-config' ) ) );
        }

        if ( ! self::check_rate_limit( count( $prepared ), $total_bytes ) ) {
            wp_send_json_error( array( 'message' => __( 'Demasiadas subidas en poco tiempo. Espera un momento e inténtalo de nuevo.', 'woo-config' ) ) );
        }

        if ( ! self::check_global_limit( $total_bytes ) ) {
            wp_send_json_error( array( 'message' => __( 'El servicio de subidas está temporalmente saturado. Inténtalo más tarde.', 'woo-config' ) ) );
        }

        $registry         = self::get_token_registry( $upload_token );
        $uploaded_results = array();
        $moved_bytes      = 0;

        foreach ( $prepared as $item ) {
            $destination = $dirs['temp_dir'] . '/' . $item['stored_name'];

            if ( ! move_uploaded_file( $item['tmp_name'], $destination ) ) {
                continue;
            }

            @chmod( $destination, 0644 );

            $entry = array(
                'file_id'       => $item['file_id'],
                'original_name' => $item['original_name'],
                'stored_name'   => $item['stored_name'],
                'file_size'     => $item['file_size'],
                'extension'     => $item['extension'],
                'is_image'      => $item['is_image'],
                'created'       => time(),
            );

            $registry[ $item['stored_name'] ] = $entry;
            $uploaded_results[]               = self::format_file_data( $entry, 'temp', $upload_token );
            $moved_bytes                     += $item['file_size'];
        }

        if ( empty( $uploaded_results ) ) {
            wp_send_json_error( array( 'message' => __( 'No se pudo guardar ningún archivo. Revisa los permisos de la carpeta de subidas.', 'woo-config' ) ) );
        }

        self::save_token_registry( $upload_token, $registry );
        self::bump_rate_limit( count( $uploaded_results ), $moved_bytes );
        self::bump_global_limit( $moved_bytes );

        wp_send_json_success( array( 'files' => $uploaded_results ) );
    }

    public static function handle_ajax_delete() {
        check_ajax_referer( 'wooconfig_nonce', 'nonce' );

        $upload_token = self::sanitize_upload_token( isset( $_POST['upload_token'] ) ? wp_unslash( $_POST['upload_token'] ) : '' );
        $stored_name  = isset( $_POST['stored_name'] ) ? sanitize_file_name( wp_unslash( $_POST['stored_name'] ) ) : '';

        if ( empty( $upload_token ) || empty( $stored_name ) ) {
            wp_send_json_error( array( 'message' => 'Invalid file' ) );
        }

        $registry = self::get_token_registry( $upload_token );
        if ( ! isset( $registry[ $stored_name ] ) ) {
            wp_send_json_error( array( 'message' => 'File not found' ) );
        }

        $dirs        = self::get_upload_dirs();
        $target_path = $dirs['temp_dir'] . '/' . $stored_name;
        $real_target = realpath( $target_path );
        $real_temp   = realpath( $dirs['temp_dir'] );

        if ( $real_target && $real_temp && 0 === strpos( $real_target, $real_temp . DIRECTORY_SEPARATOR ) && is_file( $real_target ) ) {
            if ( ! @unlink( $real_target ) && file_exists( $real_target ) ) {
                wp_send_json_error( array( 'message' => __( 'No se pudo eliminar el archivo. Inténtalo de nuevo.', 'woo-config' ) ) );
            }
        } elseif ( file_exists( $target_path ) ) {
            wp_send_json_error( array( 'message' => __( 'No se pudo eliminar el archivo. Inténtalo de nuevo.', 'woo-config' ) ) );
        }

        unset( $registry[ $stored_name ] );
        self::save_token_registry( $upload_token, $registry );

        wp_send_json_success( array( 'deleted' => true ) );
    }

    public static function move_temp_files_to_order( $files_array, $order_id, $upload_token = '' ) {
        if ( empty( $files_array ) || ! is_array( $files_array ) ) {
            return array();
        }

        $dirs         = self::get_upload_dirs();
        $order_id     = absint( $order_id );
        $order_dir    = $dirs['orders_dir'] . '/' . $order_id;
        $upload_token = self::sanitize_upload_token( $upload_token );
        $registry     = $upload_token ? self::get_token_registry( $upload_token ) : array();

        $real_temp         = realpath( $dirs['temp_dir'] );
        $moved             = array();
        $created_order_dir = false;

        $permanent_files = array();

        foreach ( $files_array as $file ) {
            $stored_name = isset( $file['stored_name'] ) ? sanitize_file_name( $file['stored_name'] ) : '';
            if ( empty( $stored_name ) ) {
                continue;
            }

            // New uploads always carry a token: the file must belong to that registry.
            if ( ! empty( $upload_token ) && ! isset( $registry[ $stored_name ] ) ) {
                continue;
            }

            $temp_file_path = $dirs['temp_dir'] . '/' . $stored_name;
            $real_temp_file = realpath( $temp_file_path );
            $temp_base      = $real_temp;

            // Fall back to the legacy public temp directory for in-flight uploads.
            if ( ! $real_temp_file || ! $temp_base || 0 !== strpos( $real_temp_file, $temp_base . DIRECTORY_SEPARATOR ) || ! is_file( $real_temp_file ) ) {
                $temp_base      = realpath( $dirs['legacy_temp_dir'] );
                $real_temp_file = realpath( $dirs['legacy_temp_dir'] . '/' . $stored_name );
            }

            if ( ! $real_temp_file || ! $temp_base || 0 !== strpos( $real_temp_file, $temp_base . DIRECTORY_SEPARATOR ) || ! is_file( $real_temp_file ) ) {
                continue;
            }

            if ( ! is_dir( $order_dir ) ) {
                wp_mkdir_p( $order_dir );
                $created_order_dir = true;
            }

            self::protect_private_directory( $order_dir );

            $order_file_path = $order_dir . '/' . $stored_name;
            if ( ! @rename( $real_temp_file, $order_file_path ) ) {
                continue;
            }

            @chmod( $order_file_path, 0644 );

            $entry = isset( $registry[ $stored_name ] ) ? $registry[ $stored_name ] : array();
            $ext   = strtolower( pathinfo( $stored_name, PATHINFO_EXTENSION ) );
            $size  = @filesize( $order_file_path );

            $permanent_files[] = array(
                'file_id'        => isset( $entry['file_id'] ) ? $entry['file_id'] : '',
                'original_name'  => ! empty( $entry['original_name'] ) ? $entry['original_name'] : $stored_name,
                'stored_name'    => $stored_name,
                'file_url'       => self::get_signed_file_url( 'order', $order_id, $stored_name ),
                'file_size'      => $size ? (int) $size : 0,
                'size_formatted' => size_format( $size ? (int) $size : 0, 2 ),
                'extension'      => $ext,
                'is_image'       => in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ),
                'category'       => isset( $file['category'] ) ? sanitize_text_field( $file['category'] ) : '',
            );

            $moved[] = $stored_name;
        }

        if ( empty( $permanent_files ) && $created_order_dir && is_dir( $order_dir ) ) {
            @rmdir( $order_dir );
        }

        if ( ! empty( $moved ) && ! empty( $registry ) ) {
            foreach ( $moved as $stored_name ) {
                unset( $registry[ $stored_name ] );
            }
            self::save_token_registry( $upload_token, $registry );
        }

        return $permanent_files;
    }

    public static function clean_temp_files() {
        $dirs     = self::get_upload_dirs();
        $temp_dirs = array( $dirs['temp_dir'], $dirs['legacy_temp_dir'] );
        $now      = time();
        $max_age  = 48 * 3600;

        foreach ( $temp_dirs as $temp_dir ) {
            if ( ! is_dir( $temp_dir ) ) {
                continue;
            }

            $files = scandir( $temp_dir );
            if ( ! is_array( $files ) ) {
                continue;
            }

            foreach ( $files as $file ) {
                if ( in_array( $file, array( '.', '..', 'index.php', '.htaccess' ), true ) ) {
                    continue;
                }

                $path = $temp_dir . '/' . $file;
                if ( is_file( $path ) && ( $now - filemtime( $path ) ) > $max_age ) {
                    @unlink( $path );
                }
            }
        }
    }

    /**
     * Delete all files and the directory for a specific order safely.
     */
    public static function delete_order_files( $order_id ) {
        $order_id = absint( $order_id );
        if ( ! $order_id ) {
            return false;
        }

        $dirs     = self::get_upload_dirs();
        $deleted  = false;
        $bases    = array( $dirs['orders_dir'], $dirs['legacy_orders_dir'] );

        foreach ( $bases as $base ) {
            $order_dir = $base . '/' . $order_id;
            if ( is_dir( $order_dir ) && self::delete_directory_recursive( $order_dir, $base ) ) {
                $deleted = true;
            }
        }

        return $deleted;
    }

    /**
     * Safely recursively delete a directory within an allowed base path.
     */
    public static function delete_directory_recursive( $dir, $base_allowed_dir ) {
        $real_dir  = realpath( $dir );
        $real_base = realpath( $base_allowed_dir );

        // Security check: ensure target is strictly inside allowed directory
        if ( ! $real_dir || ! $real_base || 0 !== strpos( $real_dir, $real_base ) || $real_dir === $real_base ) {
            return false;
        }

        $items = scandir( $real_dir );
        if ( false === $items ) {
            return false;
        }

        foreach ( $items as $item ) {
            if ( '.' === $item || '..' === $item ) {
                continue;
            }

            $path = $real_dir . DIRECTORY_SEPARATOR . $item;
            if ( is_dir( $path ) ) {
                self::delete_directory_recursive( $path, $base_allowed_dir );
            } else {
                @unlink( $path );
            }
        }

        return @rmdir( $real_dir );
    }

    /**
     * Calculate directory size and file count.
     */
    public static function get_dir_size_and_count( $dir ) {
        $size  = 0;
        $count = 0;

        if ( ! is_dir( $dir ) ) {
            return array( 'size' => 0, 'count' => 0 );
        }

        $items = scandir( $dir );
        if ( false === $items ) {
            return array( 'size' => 0, 'count' => 0 );
        }

        foreach ( $items as $item ) {
            if ( in_array( $item, array( '.', '..', 'index.php', '.htaccess' ), true ) ) {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if ( is_dir( $path ) ) {
                $sub = self::get_dir_size_and_count( $path );
                $size  += $sub['size'];
                $count += $sub['count'];
            } elseif ( is_file( $path ) ) {
                $size  += (int) filesize( $path );
                $count++;
            }
        }

        return array( 'size' => $size, 'count' => $count );
    }

    /**
     * Get global storage stats for WooConfig uploads.
     */
    public static function get_storage_stats() {
        $dirs       = self::get_upload_dirs();
        $temp_dirs  = array( $dirs['temp_dir'], $dirs['legacy_temp_dir'] );
        $order_dirs = array( $dirs['orders_dir'], $dirs['legacy_orders_dir'] );

        $temp_size  = 0;
        $temp_files = 0;
        foreach ( $temp_dirs as $temp_dir ) {
            $sub         = self::get_dir_size_and_count( $temp_dir );
            $temp_size  += $sub['size'];
            $temp_files += $sub['count'];
        }

        $order_ids = array();
        foreach ( $order_dirs as $orders_dir ) {
            if ( ! is_dir( $orders_dir ) ) {
                continue;
            }
            $folders = scandir( $orders_dir );
            if ( ! is_array( $folders ) ) {
                continue;
            }
            foreach ( $folders as $folder ) {
                if ( in_array( $folder, array( '.', '..', 'index.php', '.htaccess' ), true ) ) {
                    continue;
                }
                if ( is_numeric( $folder ) && is_dir( $orders_dir . '/' . $folder ) ) {
                    $order_ids[ absint( $folder ) ] = true;
                }
            }
        }

        $orders_size        = 0;
        $orders_files_count = 0;
        $orphan_count       = 0;
        $orphan_size        = 0;
        $orphan_files       = 0;

        foreach ( array_keys( $order_ids ) as $order_id ) {
            $order_size  = 0;
            $order_files = 0;

            foreach ( $order_dirs as $orders_dir ) {
                $sub          = self::get_dir_size_and_count( $orders_dir . '/' . $order_id );
                $order_size  += $sub['size'];
                $order_files += $sub['count'];
            }

            $orders_size        += $order_size;
            $orders_files_count += $order_files;

            $order_exists = false;
            if ( function_exists( 'wc_get_order' ) ) {
                $wc_order = wc_get_order( $order_id );
                if ( $wc_order && ! in_array( $wc_order->get_status(), array( 'trash', 'auto-draft' ), true ) ) {
                    $order_exists = true;
                }
            }

            if ( ! $order_exists ) {
                $orphan_count++;
                $orphan_size  += $order_size;
                $orphan_files += $order_files;
            }
        }

        $total_bytes = $temp_size + $orders_size;
        $total_files = $temp_files + $orders_files_count;

        return array(
            'temp_bytes'           => $temp_size,
            'temp_formatted'       => size_format( $temp_size, 2 ),
            'temp_files_count'     => $temp_files,
            'orders_bytes'         => $orders_size,
            'orders_formatted'     => size_format( $orders_size, 2 ),
            'orders_files_count'   => $orders_files_count,
            'orders_count'         => count( $order_ids ),
            'orphan_count'         => $orphan_count,
            'orphan_size'          => $orphan_size,
            'orphan_formatted'     => size_format( $orphan_size, 2 ),
            'orphan_files'         => $orphan_files,
            'total_bytes'          => $total_bytes,
            'total_formatted'      => size_format( $total_bytes, 2 ),
            'total_files'          => $total_files,
        );
    }

    /**
     * Clean all orphan order files (folders whose order ID does not exist in WooCommerce).
     */
    public static function clean_orphan_order_files() {
        $dirs   = self::get_upload_dirs();
        $bases  = array( $dirs['orders_dir'], $dirs['legacy_orders_dir'] );
        $cleaned = array(
            'folders'     => 0,
            'files'       => 0,
            'freed_bytes' => 0,
        );

        $order_ids = array();
        foreach ( $bases as $orders_dir ) {
            if ( ! is_dir( $orders_dir ) ) {
                continue;
            }
            $folders = scandir( $orders_dir );
            if ( ! is_array( $folders ) ) {
                continue;
            }
            foreach ( $folders as $folder ) {
                if ( in_array( $folder, array( '.', '..', 'index.php', '.htaccess' ), true ) ) {
                    continue;
                }
                if ( is_numeric( $folder ) && is_dir( $orders_dir . '/' . $folder ) ) {
                    $order_ids[ absint( $folder ) ] = true;
                }
            }
        }

        foreach ( array_keys( $order_ids ) as $order_id ) {
            $order_exists = false;
            if ( function_exists( 'wc_get_order' ) ) {
                $wc_order = wc_get_order( $order_id );
                if ( $wc_order && ! in_array( $wc_order->get_status(), array( 'trash', 'auto-draft' ), true ) ) {
                    $order_exists = true;
                }
            }

            if ( $order_exists ) {
                continue;
            }

            foreach ( $bases as $base ) {
                $folder_path = $base . '/' . $order_id;
                if ( ! is_dir( $folder_path ) ) {
                    continue;
                }

                $stats = self::get_dir_size_and_count( $folder_path );
                if ( self::delete_directory_recursive( $folder_path, $base ) ) {
                    $cleaned['folders']++;
                    $cleaned['files']       += $stats['count'];
                    $cleaned['freed_bytes'] += $stats['size'];
                }
            }
        }

        return $cleaned;
    }

    /**
     * Clean all temporary upload files immediately.
     */
    public static function clean_all_temp_files() {
        $dirs    = self::get_upload_dirs();
        $temp_dirs = array( $dirs['temp_dir'], $dirs['legacy_temp_dir'] );
        $cleaned = array(
            'files'       => 0,
            'freed_bytes' => 0,
        );

        foreach ( $temp_dirs as $temp_dir ) {
            if ( ! is_dir( $temp_dir ) ) {
                continue;
            }

            $files = scandir( $temp_dir );
            if ( ! is_array( $files ) ) {
                continue;
            }

            foreach ( $files as $file ) {
                if ( in_array( $file, array( '.', '..', 'index.php', '.htaccess' ), true ) ) {
                    continue;
                }

                $path = $temp_dir . '/' . $file;
                if ( is_file( $path ) ) {
                    $size = filesize( $path );
                    if ( @unlink( $path ) ) {
                        $cleaned['files']++;
                        $cleaned['freed_bytes'] += $size;
                    }
                }
            }
        }

        return $cleaned;
    }

    /**
     * Clean files of completed/cancelled orders older than a specified number of days.
     */
    public static function clean_old_order_files( $days = 90 ) {
        $days = absint( $days );
        if ( $days < 1 ) {
            return array( 'orders' => 0, 'files' => 0, 'freed_bytes' => 0 );
        }

        $dirs    = self::get_upload_dirs();
        $bases   = array( $dirs['orders_dir'], $dirs['legacy_orders_dir'] );
        $cleaned = array(
            'orders'      => 0,
            'files'       => 0,
            'freed_bytes' => 0,
        );

        $threshold_timestamp = time() - ( $days * DAY_IN_SECONDS );
        $order_ids           = array();

        foreach ( $bases as $orders_dir ) {
            if ( ! is_dir( $orders_dir ) ) {
                continue;
            }
            $folders = scandir( $orders_dir );
            if ( ! is_array( $folders ) ) {
                continue;
            }
            foreach ( $folders as $folder ) {
                if ( in_array( $folder, array( '.', '..', 'index.php', '.htaccess' ), true ) ) {
                    continue;
                }
                if ( is_numeric( $folder ) && is_dir( $orders_dir . '/' . $folder ) ) {
                    $order_ids[ absint( $folder ) ] = true;
                }
            }
        }

        foreach ( array_keys( $order_ids ) as $order_id ) {
            if ( ! function_exists( 'wc_get_order' ) ) {
                continue;
            }

            $wc_order = wc_get_order( $order_id );
            if ( ! $wc_order || ! in_array( $wc_order->get_status(), array( 'completed', 'cancelled', 'refunded' ), true ) ) {
                continue;
            }

            $order_date = $wc_order->get_date_created();
            $timestamp  = $order_date ? $order_date->getTimestamp() : 0;
            if ( ! $timestamp ) {
                foreach ( $bases as $base ) {
                    if ( is_dir( $base . '/' . $order_id ) ) {
                        $timestamp = filemtime( $base . '/' . $order_id );
                        break;
                    }
                }
            }

            if ( ! $timestamp || $timestamp >= $threshold_timestamp ) {
                continue;
            }

            $deleted_any = false;

            foreach ( $bases as $base ) {
                $folder_path = $base . '/' . $order_id;
                if ( ! is_dir( $folder_path ) ) {
                    continue;
                }

                $stats = self::get_dir_size_and_count( $folder_path );
                if ( self::delete_directory_recursive( $folder_path, $base ) ) {
                    $deleted_any = true;
                    $cleaned['files']       += $stats['count'];
                    $cleaned['freed_bytes'] += $stats['size'];
                }
            }

            if ( $deleted_any ) {
                $cleaned['orders']++;
                $wc_order->add_order_note( sprintf( __( '🧹 Archivos de personalización purgados automáticamente por política de almacenamiento (más de %d días).', 'woo-config' ), $days ) );
            }
        }

        return $cleaned;
    }
}

WC_Config_Uploader::init();
