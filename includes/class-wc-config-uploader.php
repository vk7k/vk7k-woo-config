<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Config_Uploader {

    public static function init() {
        add_action( 'wp_ajax_wooconfig_upload_files', array( __CLASS__, 'handle_ajax_upload' ) );
        add_action( 'wp_ajax_nopriv_wooconfig_upload_files', array( __CLASS__, 'handle_ajax_upload' ) );

        add_action( 'wp_ajax_wooconfig_upload_file', array( __CLASS__, 'handle_ajax_upload' ) );
        add_action( 'wp_ajax_nopriv_wooconfig_upload_file', array( __CLASS__, 'handle_ajax_upload' ) );

        add_action( 'wp_ajax_wooconfig_delete_file', array( __CLASS__, 'handle_ajax_delete' ) );
        add_action( 'wp_ajax_nopriv_wooconfig_delete_file', array( __CLASS__, 'handle_ajax_delete' ) );
    }

    public static function get_upload_dirs() {
        $upload_dir = wp_upload_dir();
        $base_dir   = $upload_dir['basedir'] . '/woo-config-uploads';
        $base_url   = $upload_dir['baseurl'] . '/woo-config-uploads';

        return array(
            'base_dir'   => $base_dir,
            'base_url'   => $base_url,
            'temp_dir'   => $base_dir . '/temp',
            'temp_url'   => $base_url . '/temp',
            'orders_dir' => $base_dir . '/orders',
            'orders_url' => $base_url . '/orders',
        );
    }

    public static function check_upload_directory_permissions() {
        $upload_dir = wp_upload_dir();
        $errors     = array();

        if ( ! empty( $upload_dir['error'] ) ) {
            $errors[] = sprintf( __( 'Directorio principal de subidas de WordPress no accesible: %s', 'woo-config' ), $upload_dir['error'] );
            return $errors;
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
            }

            // Ensure index.php and .htaccess protection
            if ( is_writable( $path ) ) {
                if ( ! file_exists( $path . '/index.php' ) ) {
                    @file_put_contents( $path . '/index.php', '<?php // Silence is golden' );
                }
                if ( ! file_exists( $path . '/.htaccess' ) ) {
                    $htaccess_content = "# Disable script execution\n<FilesMatch \"\.(php|phtml|php3|php4|php5|php7|phps|pl|py|cgi|sh)$\">\nOrder Deny,Allow\nDeny from all\n</FilesMatch>\nOptions -Indexes\n";
                    @file_put_contents( $path . '/.htaccess', $htaccess_content );
                }
            }
        }

        return $errors;
    }

    public static function handle_ajax_upload() {
        check_ajax_referer( 'wooconfig_nonce', 'nonce' );

        if ( empty( $_FILES['files'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No se enviaron archivos.', 'woo-config' ) ) );
        }

        $dirs = self::get_upload_dirs();
        if ( ! file_exists( $dirs['temp_dir'] ) ) {
            wp_mkdir_p( $dirs['temp_dir'] );
        }

        if ( ! is_writable( $dirs['temp_dir'] ) ) {
            wp_send_json_error( array(
                'message' => __( 'Error del servidor: La carpeta temporal de subidas no tiene permisos de escritura. Por favor contacta al administrador.', 'woo-config' )
            ) );
        }

        $max_size_mb = (int) get_option( 'wooconfig_max_file_size_mb', 20 );
        $max_bytes   = $max_size_mb * 1024 * 1024;
        
        // Allowed extensions (SVG excluded to prevent Stored XSS vectors)
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

        $uploaded_results = array();
        $files = $_FILES['files'];

        $file_count = is_array( $files['name'] ) ? count( $files['name'] ) : 1;

        for ( $i = 0; $i < $file_count; $i++ ) {
            $name     = is_array( $files['name'] ) ? $files['name'][$i] : $files['name'];
            $tmp_name = is_array( $files['tmp_name'] ) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $size     = is_array( $files['size'] ) ? $files['size'][$i] : $files['size'];
            $error    = is_array( $files['error'] ) ? $files['error'][$i] : $files['error'];

            if ( $error !== UPLOAD_ERR_OK ) {
                continue;
            }

            if ( $size > $max_bytes ) {
                wp_send_json_error( array( 'message' => sprintf( __( 'El archivo %s supera el tamaño máximo permitido de %dMB.', 'woo-config' ), esc_html( $name ), $max_size_mb ) ) );
            }

            $ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, $allowed_ext, true ) ) {
                wp_send_json_error( array( 'message' => sprintf( __( 'Tipo de archivo no permitido (%s). Solo se aceptan: %s', 'woo-config' ), esc_html( $ext ), implode( ', ', $allowed_ext ) ) ) );
            }

            // Deep MIME-type check via WordPress check filetype
            $wp_filetype = wp_check_filetype_and_ext( $tmp_name, $name, $allowed_mimes );
            $ext_check   = ! empty( $wp_filetype['ext'] ) ? $wp_filetype['ext'] : $ext;

            // Extra MIME validation if finfo is available
            if ( function_exists( 'finfo_open' ) && ! empty( $tmp_name ) && file_exists( $tmp_name ) ) {
                $finfo     = finfo_open( FILEINFO_MIME_TYPE );
                $real_mime = finfo_file( $finfo, $tmp_name );
                finfo_close( $finfo );

                // Block executable scripts masquerading as images/documents
                $dangerous_mimes = array(
                    'text/html', 'text/javascript', 'application/x-php', 'text/x-php',
                    'application/x-httpd-php', 'application/x-sh', 'application/x-csh',
                    'application/x-msdownload', 'application/x-executable'
                );

                if ( in_array( $real_mime, $dangerous_mimes, true ) ) {
                    wp_send_json_error( array( 'message' => sprintf( __( 'Contenido de archivo sospechoso o no válido detectado para %s.', 'woo-config' ), esc_html( $name ) ) ) );
                }
            }

            $safe_original_name = sanitize_file_name( $name );
            $file_id            = wp_generate_uuid4();
            $stored_filename    = $file_id . '_' . $safe_original_name;
            $destination        = $dirs['temp_dir'] . '/' . $stored_filename;

            if ( move_uploaded_file( $tmp_name, $destination ) ) {
                $uploaded_results[] = array(
                    'file_id'        => $file_id,
                    'original_name'  => $safe_original_name,
                    'stored_name'    => $stored_filename,
                    'file_url'       => $dirs['temp_url'] . '/' . $stored_filename,
                    'file_size'      => $size,
                    'size_formatted' => size_format( $size, 2 ),
                    'extension'      => $ext,
                    'is_image'       => in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ),
                );
            }
        }

        if ( empty( $uploaded_results ) ) {
            wp_send_json_error( array( 'message' => __( 'No se pudo guardar ningún archivo. Revisa los permisos de la carpeta de subidas.', 'woo-config' ) ) );
        }

        wp_send_json_success( array( 'files' => $uploaded_results ) );
    }

    public static function handle_ajax_delete() {
        check_ajax_referer( 'wooconfig_nonce', 'nonce' );

        $stored_name = isset( $_POST['stored_name'] ) ? sanitize_file_name( $_POST['stored_name'] ) : '';
        if ( empty( $stored_name ) ) {
            wp_send_json_error( array( 'message' => 'Invalid file' ) );
        }

        $dirs = self::get_upload_dirs();
        $target_path = $dirs['temp_dir'] . '/' . $stored_name;

        if ( file_exists( $target_path ) && is_file( $target_path ) ) {
            unlink( $target_path );
            wp_send_json_success( array( 'deleted' => true ) );
        }

        wp_send_json_error( array( 'message' => 'File not found' ) );
    }

    public static function move_temp_files_to_order( $files_array, $order_id ) {
        if ( empty( $files_array ) || ! is_array( $files_array ) ) {
            return array();
        }

        $dirs = self::get_upload_dirs();
        $order_dir = $dirs['orders_dir'] . '/' . $order_id;
        $order_url = $dirs['orders_url'] . '/' . $order_id;

        if ( ! file_exists( $order_dir ) ) {
            wp_mkdir_p( $order_dir );
            file_put_contents( $order_dir . '/index.php', '<?php // Silence is golden' );
        }

        $permanent_files = array();

        foreach ( $files_array as $file ) {
            $stored_name = isset( $file['stored_name'] ) ? sanitize_file_name( $file['stored_name'] ) : '';
            if ( empty( $stored_name ) ) continue;

            $temp_file_path = $dirs['temp_dir'] . '/' . $stored_name;
            $order_file_path = $order_dir . '/' . $stored_name;

            if ( file_exists( $temp_file_path ) ) {
                rename( $temp_file_path, $order_file_path );
                $file['file_url'] = $order_url . '/' . $stored_name;
            }

            $permanent_files[] = $file;
        }

        return $permanent_files;
    }

    public static function clean_temp_files() {
        $dirs = self::get_upload_dirs();
        $temp_dir = $dirs['temp_dir'];

        if ( ! is_dir( $temp_dir ) ) return;

        $files = scandir( $temp_dir );
        $now = time();
        $max_age = 48 * 3600;

        foreach ( $files as $file ) {
            if ( in_array( $file, array( '.', '..', 'index.php', '.htaccess' ) ) ) continue;

            $path = $temp_dir . '/' . $file;
            if ( is_file( $path ) && ( $now - filemtime( $path ) ) > $max_age ) {
                unlink( $path );
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

        $dirs = self::get_upload_dirs();
        $order_dir = $dirs['orders_dir'] . '/' . $order_id;

        if ( ! is_dir( $order_dir ) ) {
            return false;
        }

        return self::delete_directory_recursive( $order_dir, $dirs['orders_dir'] );
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
        $dirs = self::get_upload_dirs();

        // Check temp
        $temp_stats = self::get_dir_size_and_count( $dirs['temp_dir'] );

        // Check orders
        $orders_size        = 0;
        $orders_files_count = 0;
        $orders_count       = 0;
        $orphan_count       = 0;
        $orphan_size        = 0;
        $orphan_files       = 0;

        if ( is_dir( $dirs['orders_dir'] ) ) {
            $order_folders = scandir( $dirs['orders_dir'] );
            if ( is_array( $order_folders ) ) {
                foreach ( $order_folders as $folder ) {
                    if ( in_array( $folder, array( '.', '..', 'index.php', '.htaccess' ), true ) ) {
                        continue;
                    }

                    $folder_path = $dirs['orders_dir'] . '/' . $folder;
                    if ( is_dir( $folder_path ) && is_numeric( $folder ) ) {
                        $orders_count++;
                        $sub = self::get_dir_size_and_count( $folder_path );
                        $orders_size        += $sub['size'];
                        $orders_files_count += $sub['count'];

                        // Check if order exists in DB
                        $order_id = absint( $folder );
                        $order_exists = false;
                        if ( function_exists( 'wc_get_order' ) ) {
                            $wc_order = wc_get_order( $order_id );
                            if ( $wc_order && ! in_array( $wc_order->get_status(), array( 'trash', 'auto-draft' ), true ) ) {
                                $order_exists = true;
                            }
                        }

                        if ( ! $order_exists ) {
                            $orphan_count++;
                            $orphan_size  += $sub['size'];
                            $orphan_files += $sub['count'];
                        }
                    }
                }
            }
        }

        $total_bytes = $temp_stats['size'] + $orders_size;
        $total_files = $temp_stats['count'] + $orders_files_count;

        return array(
            'temp_bytes'           => $temp_stats['size'],
            'temp_formatted'       => size_format( $temp_stats['size'], 2 ),
            'temp_files_count'     => $temp_stats['count'],
            'orders_bytes'         => $orders_size,
            'orders_formatted'     => size_format( $orders_size, 2 ),
            'orders_files_count'   => $orders_files_count,
            'orders_count'         => $orders_count,
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
        $dirs = self::get_upload_dirs();
        $cleaned = array(
            'folders'     => 0,
            'files'       => 0,
            'freed_bytes' => 0,
        );

        if ( ! is_dir( $dirs['orders_dir'] ) ) {
            return $cleaned;
        }

        $order_folders = scandir( $dirs['orders_dir'] );
        if ( ! is_array( $order_folders ) ) {
            return $cleaned;
        }

        foreach ( $order_folders as $folder ) {
            if ( in_array( $folder, array( '.', '..', 'index.php', '.htaccess' ), true ) ) {
                continue;
            }

            $folder_path = $dirs['orders_dir'] . '/' . $folder;
            if ( is_dir( $folder_path ) && is_numeric( $folder ) ) {
                $order_id = absint( $folder );
                $order_exists = false;
                if ( function_exists( 'wc_get_order' ) ) {
                    $wc_order = wc_get_order( $order_id );
                    if ( $wc_order && ! in_array( $wc_order->get_status(), array( 'trash', 'auto-draft' ), true ) ) {
                        $order_exists = true;
                    }
                }

                if ( ! $order_exists ) {
                    $stats = self::get_dir_size_and_count( $folder_path );
                    if ( self::delete_directory_recursive( $folder_path, $dirs['orders_dir'] ) ) {
                        $cleaned['folders']++;
                        $cleaned['files']       += $stats['count'];
                        $cleaned['freed_bytes'] += $stats['size'];
                    }
                }
            }
        }

        return $cleaned;
    }

    /**
     * Clean all temporary upload files immediately.
     */
    public static function clean_all_temp_files() {
        $dirs = self::get_upload_dirs();
        $cleaned = array(
            'files'       => 0,
            'freed_bytes' => 0,
        );

        if ( ! is_dir( $dirs['temp_dir'] ) ) {
            return $cleaned;
        }

        $files = scandir( $dirs['temp_dir'] );
        if ( ! is_array( $files ) ) {
            return $cleaned;
        }

        foreach ( $files as $file ) {
            if ( in_array( $file, array( '.', '..', 'index.php', '.htaccess' ), true ) ) {
                continue;
            }

            $path = $dirs['temp_dir'] . '/' . $file;
            if ( is_file( $path ) ) {
                $size = filesize( $path );
                if ( @unlink( $path ) ) {
                    $cleaned['files']++;
                    $cleaned['freed_bytes'] += $size;
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

        $dirs = self::get_upload_dirs();
        $cleaned = array(
            'orders'      => 0,
            'files'       => 0,
            'freed_bytes' => 0,
        );

        if ( ! is_dir( $dirs['orders_dir'] ) ) {
            return $cleaned;
        }

        $threshold_timestamp = time() - ( $days * DAY_IN_SECONDS );
        $order_folders = scandir( $dirs['orders_dir'] );

        if ( ! is_array( $order_folders ) ) {
            return $cleaned;
        }

        foreach ( $order_folders as $folder ) {
            if ( in_array( $folder, array( '.', '..', 'index.php', '.htaccess' ), true ) ) {
                continue;
            }

            $folder_path = $dirs['orders_dir'] . '/' . $folder;
            if ( is_dir( $folder_path ) && is_numeric( $folder ) ) {
                $order_id = absint( $folder );
                if ( function_exists( 'wc_get_order' ) ) {
                    $wc_order = wc_get_order( $order_id );
                    if ( $wc_order && in_array( $wc_order->get_status(), array( 'completed', 'cancelled', 'refunded' ), true ) ) {
                        $order_date = $wc_order->get_date_created();
                        $timestamp  = $order_date ? $order_date->getTimestamp() : filemtime( $folder_path );

                        if ( $timestamp < $threshold_timestamp ) {
                            $stats = self::get_dir_size_and_count( $folder_path );
                            if ( self::delete_directory_recursive( $folder_path, $dirs['orders_dir'] ) ) {
                                $cleaned['orders']++;
                                $cleaned['files']       += $stats['count'];
                                $cleaned['freed_bytes'] += $stats['size'];
                                $wc_order->add_order_note( sprintf( __( '🧹 Archivos de personalización purgados automáticamente por política de almacenamiento (más de %d días).', 'woo-config' ), $days ) );
                            }
                        }
                    }
                }
            }
        }

        return $cleaned;
    }
}

WC_Config_Uploader::init();
