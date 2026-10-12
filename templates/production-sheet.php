<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_draft = isset( $is_draft ) && $is_draft;
if ( $is_draft && isset( $draft_data ) ) {
    $groups = array(
        'draft_preview' => array(
            'group_id'    => 'draft_preview',
            'flow'        => 'wizard',
            'group_title' => $draft_data['group_title'],
            'design_desc' => $draft_data['design_desc'],
            'files'       => $draft_data['files'],
        )
    );
    $items = array();
    foreach ( $draft_data['items'] as $d_idx => $d_item ) {
        $mock_item = new WC_Order_Item_Product();
        $mock_item->set_name( $d_item['product_name'] );
        $mock_item->set_quantity( 1 );
        $mock_item->add_meta_data( '_wooconfig_group_id', 'draft_preview' );
        $mock_item->add_meta_data( '_wooconfig_item_index', $d_idx + 1 );
        $mock_item->add_meta_data( __( 'Dorsal (#)', 'woo-config' ), $d_item['dorsal'] );
        $mock_item->add_meta_data( __( 'Nombre / Alias', 'woo-config' ), $d_item['name'] );
        $mock_item->add_meta_data( __( 'Talla', 'woo-config' ), $d_item['size'] );
        $mock_item->add_meta_data( __( 'Adicionales', 'woo-config' ), $d_item['adicionales'] );
        $items[] = $mock_item;
    }
} else {
    $groups = $order->get_meta( '_wooconfig_order_groups' );
    $items  = $order->get_items();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha de Producción - Pedido #<?php echo esc_html( $order->get_order_number() ); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #111; padding: 25px; font-size: 13px; line-height: 1.4; background: #fff; }
        .sheet-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #94ec00; padding-bottom: 15px; margin-bottom: 20px; }
        .logo-box h1 { font-size: 24px; font-weight: 900; letter-spacing: -1px; margin-bottom: 3px; }
        .logo-box span { color: #666; font-size: 12px; }
        .order-meta-box { text-align: right; }
        .order-meta-box h2 { font-size: 20px; font-weight: 800; color: #111; }
        .order-meta-box p { color: #555; font-size: 12px; }
        
        .client-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 6px; padding: 12px 15px; margin-bottom: 20px; }
        .client-info-grid div p { margin-bottom: 3px; }
        .client-info-grid strong { color: #222; }

        .team-block { margin-bottom: 30px; page-break-inside: avoid; border: 1px solid #ccc; border-radius: 6px; padding: 15px; }
        .team-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        .team-header h3 { font-size: 16px; font-weight: 700; }

        .spec-box { background: #fff8e5; border-left: 4px solid #ffba00; padding: 10px 12px; margin-bottom: 15px; font-size: 12px; }
        .spec-box strong { display: block; margin-bottom: 4px; color: #7a5800; font-size: 13px; }

        .quick-summary-box { background: #eef9e8; border-left: 4px solid #94ec00; padding: 10px 12px; margin-bottom: 15px; font-size: 13px; }
        .quick-summary-box strong { color: #1a5a00; }

        table.production-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.production-table th, table.production-table td { border: 1px solid #ddd; padding: 6px 10px; text-align: left; }
        table.production-table th { background: #f2f2f2; font-weight: 700; text-transform: uppercase; font-size: 11px; }
        table.production-table tr:nth-child(even) { background: #fafafa; }
        .dorsal-badge { font-weight: 800; font-size: 15px; color: #000; }
        .size-badge { font-weight: 700; background: #e0e0e0; padding: 2px 6px; border-radius: 3px; }
        .ads-badge { font-size: 11px; font-weight: 600; color: #333; background: #fff3cd; padding: 2px 5px; border-radius: 3px; border: 1px solid #ffeeba; }
        .player-name { text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }

        .files-box { margin-top: 10px; }
        .files-box strong { display: block; margin-bottom: 6px; }
        .file-links { display: flex; gap: 10px; flex-wrap: wrap; }
        .file-badge { border: 1px solid #bbb; border-radius: 4px; padding: 4px 8px; font-size: 11px; background: #f5f5f5; text-decoration: none; color: #0073aa; font-weight: 600; }

        .print-btn-bar { margin-bottom: 20px; text-align: right; }
        .print-btn { background: #111; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; font-weight: 700; cursor: pointer; font-size: 14px; }
        @media print {
            .print-btn-bar { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="print-btn-bar">
        <button class="print-btn" onclick="window.print();">🖨 Imprimir / Guardar como PDF</button>
    </div>

    <div class="sheet-header">
        <div class="logo-box">
            <h1>CAFU - FICHA TÉCNICA DE PRODUCCIÓN</h1>
            <span>Taller de Confección, Sublimación y Personalización</span>
        </div>
        <div class="order-meta-box">
            <?php if ( $is_draft ) : ?>
                <h2>PREVISUALIZACIÓN DE TALLER</h2>
                <p>Fecha: <strong><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></strong></p>
                <p>Modo: <strong style="color: #0073aa;">Borrador / Previsualización en vivo</strong></p>
            <?php else : ?>
                <h2>ORDEN #<?php echo esc_html( $order->get_order_number() ); ?></h2>
                <p>Fecha: <?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></p>
                <p>Estado: <strong><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></strong></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="client-info-grid">
        <div>
            <p><strong>Cliente:</strong> <?php echo esc_html( $order ? $order->get_formatted_billing_full_name() : ( isset( $draft_data['client_name'] ) ? $draft_data['client_name'] : 'Cliente / Taller' ) ); ?></p>
            <p><strong>Email:</strong> <?php echo esc_html( $order ? $order->get_billing_email() : ( isset( $draft_data['client_email'] ) ? $draft_data['client_email'] : '-' ) ); ?></p>
            <p><strong>Teléfono:</strong> <?php echo esc_html( $order ? $order->get_billing_phone() : ( isset( $draft_data['client_phone'] ) ? $draft_data['client_phone'] : '-' ) ); ?></p>
        </div>
        <div>
            <p><strong>Dirección de Envío:</strong> <?php echo esc_html( $order ? ( $order->get_formatted_shipping_address() ? $order->get_formatted_shipping_address() : $order->get_formatted_billing_address() ) : 'Dirección por definir' ); ?></p>
            <p><strong>Nota / Medio de contacto:</strong> <?php echo esc_html( $order ? ( $order->get_customer_note() ? $order->get_customer_note() : 'Sin notas adicionales' ) : 'Preparación interna desde backend' ); ?></p>
        </div>
    </div>

    <?php
    $grouped_items = array();
    foreach ( $items as $item ) {
        $gid = $item->get_meta( '_wooconfig_group_id' );
        if ( empty( $gid ) ) continue;
        $grouped_items[$gid][] = $item;
    }

    foreach ( $grouped_items as $gid => $group_item_list ) :
        $group_meta  = isset( $groups[$gid] ) ? $groups[$gid] : array();
        $first_item  = $group_item_list[0];
        $flow        = isset( $group_meta['flow'] ) ? $group_meta['flow'] : $first_item->get_meta( '_wooconfig_flow' );
        $group_title = $first_item->get_meta( '_wooconfig_group_title' );
        $design_desc = isset( $group_meta['design_desc'] ) ? $group_meta['design_desc'] : $first_item->get_meta( '_wooconfig_design_desc' );
        $files       = isset( $group_meta['files'] ) ? $group_meta['files'] : $first_item->get_meta( '_wooconfig_files' );
    ?>
        <div class="team-block">
            <div class="team-header">
                <h3>👕 <?php echo esc_html( $group_title ? $group_title : 'Equipo Personalizado' ); ?></h3>
                <span><strong>Modo: <?php echo 'quick' === $flow ? 'Configuración Rápida (Planilla/Foto)' : 'Wizard Paso a Paso'; ?></strong></span>
            </div>

            <?php if ( 'quick' === $flow ) : 
                $ad_summary = $first_item->get_meta( __( 'Adicionales incluidos', 'woo-config' ) );
                $clean_ad_summary = array();
                if ( is_array( $ad_summary ) ) {
                    foreach ( $ad_summary as $as_item ) {
                        $clean_ad_summary[] = html_entity_decode( wp_strip_all_tags( $as_item ) );
                    }
                } elseif ( $ad_summary ) {
                    $clean_ad_summary[] = html_entity_decode( wp_strip_all_tags( $ad_summary ) );
                }
            ?>
                <div class="quick-summary-box">
                    <p><strong>Total prendas a confeccionar:</strong> <?php echo esc_html( $first_item->get_quantity() ); ?> unidades</p>
                    <?php if ( ! empty( $clean_ad_summary ) ) : ?>
                        <p><strong>Adicionales de confección:</strong> <?php echo esc_html( implode( ', ', $clean_ad_summary ) ); ?></p>
                    <?php endif; ?>
                    <p style="font-size: 11px; color: #444; margin-top: 4px;"><em>* Ver detalle de tallas, dorsales y nombres en los archivos adjuntos abajo.</em></p>
                </div>
            <?php else : ?>
                <table class="production-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th style="width: 80px;">Dorsal</th>
                            <th>Nombre / Apellido / Alias</th>
                            <th style="width: 80px;">Talla</th>
                            <th>Adicionales</th>
                            <th>Producto / Modelo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $group_item_list as $i => $item ) : 
                            $ads = $item->get_meta( __( 'Adicionales', 'woo-config' ) );
                            $ads_text = is_array( $ads ) ? implode( ', ', $ads ) : ( $ads ? $ads : '-' );
                            $clean_ads_text = html_entity_decode( wp_strip_all_tags( $ads_text ) );
                        ?>
                            <tr>
                                <td><?php echo esc_html( $item->get_meta( '_wooconfig_item_index' ) ? $item->get_meta( '_wooconfig_item_index' ) : ($i + 1) ); ?></td>
                                <td><span class="dorsal-badge"><?php echo esc_html( $item->get_meta( __( 'Dorsal (#)', 'woo-config' ) ) ); ?></span></td>
                                <td><span class="player-name"><?php echo esc_html( $item->get_meta( __( 'Nombre / Alias', 'woo-config' ) ) ); ?></span></td>
                                <td><span class="size-badge"><?php echo esc_html( $item->get_meta( __( 'Talla', 'woo-config' ) ) ); ?></span></td>
                                <td><span class="ads-badge"><?php echo esc_html( $clean_ads_text ); ?></span></td>
                                <td><?php echo esc_html( $item->get_name() ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="spec-box">
                <strong>📝 ESPECIFICACIONES Y NOTAS DE DISEÑO:</strong>
                <?php echo nl2br( esc_html( $design_desc ? $design_desc : 'Sin descripción' ) ); ?>
            </div>

            <?php if ( ! empty( $files ) && is_array( $files ) ) : ?>
                <div class="files-box">
                    <strong>📁 ARCHIVOS DE DISEÑO / FOTOS / PLANILLAS ADJUNTAS:</strong>
                    <div class="file-links">
                        <?php foreach ( $files as $f ) : ?>
                            <a href="<?php echo esc_url( $f['file_url'] ); ?>" target="_blank" class="file-badge">
                                🔗 <?php echo esc_html( $f['original_name'] ); ?> (Ver / Descargar)
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

</body>
</html>
