<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $product;
if ( ! $product ) {
    return;
}

$product_id     = $product->get_id();
$cart_min_units = (int) get_option( 'wooconfig_min_units', get_option( 'wooconfig_default_min_units', 1 ) );
$cart_min_units = max( 1, $cart_min_units );
$min_units      = 1; // At individual product level, can configure from 1 unit

$adicionales = get_post_meta( $product_id, '_wooconfig_adicionales', true );
if ( ! is_array( $adicionales ) ) {
    $adicionales = array(
        'cuello_polo' => array( 'id' => 'cuello_polo', 'enabled' => 'yes', 'label' => __( 'Cuello polo', 'woo-config' ), 'price' => 2500 ),
        'manga_larga' => array( 'id' => 'manga_larga', 'enabled' => 'yes', 'label' => __( 'Manga larga', 'woo-config' ), 'price' => 2500 ),
    );
}

$active_adicionales = array();
foreach ( $adicionales as $key => $ad ) {
    if ( isset( $ad['enabled'] ) && 'yes' === $ad['enabled'] ) {
        $active_adicionales[$key] = $ad;
    }
}

$base_price = (float) $product->get_price();
?>

<div class="wooconfig-onpage-widget" id="wooconfig-onpage-widget" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-base-price="<?php echo esc_attr( $base_price ); ?>" data-min-units="1" data-cart-min-units="<?php echo esc_attr( $cart_min_units ); ?>">
    
    <!-- Configurar compra box -->
    <div class="wooconfig-box-section wooconfig-purchase-config">
        <h3 class="wooconfig-widget-title"><?php esc_html_e( 'Configurar compra:', 'woo-config' ); ?></h3>
        
        <div class="wooconfig-qty-row">
            <span class="wooconfig-qty-label"><?php esc_html_e( 'Total de productos', 'woo-config' ); ?></span>
            <div class="wooconfig-stepper-control">
                <button type="button" class="wooconfig-stepper-btn wooconfig-btn-minus" id="wooconfig-main-qty-minus">-</button>
                <input type="number" id="wooconfig-main-qty-input" class="wooconfig-stepper-input" value="1" min="1" step="1" />
                <button type="button" class="wooconfig-stepper-btn wooconfig-btn-plus" id="wooconfig-main-qty-plus">+</button>
            </div>
        </div>

        <?php if ( $cart_min_units > 1 ) : ?>
            <div class="wooconfig-cart-min-note" style="margin: 10px 0 0; padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; color: #475569; display: flex; align-items: center; gap: 6px;">
                <span>💡</span>
                <span><?php echo esc_html( sprintf( __( 'Pedido mínimo de confección: %d prendas en total en tu pedido (puedes combinar distintos modelos o productos).', 'woo-config' ), $cart_min_units ) ); ?></span>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $active_adicionales ) ) : ?>
            <div class="wooconfig-adicionales-section">
                <h4 class="wooconfig-sub-title"><?php esc_html_e( 'Adicionales (opcional)', 'woo-config' ); ?></h4>
                <p class="wooconfig-sub-hint"><?php esc_html_e( '¿Cuántos de estos productos tendrán adicionales?', 'woo-config' ); ?></p>

                <div class="wooconfig-adicionales-list">
                    <?php foreach ( $active_adicionales as $key => $ad ) : 
                        $ad_price = isset( $ad['price'] ) ? (float) $ad['price'] : 2500;
                        $ad_label = isset( $ad['label'] ) ? $ad['label'] : $key;
                    ?>
                        <div class="wooconfig-adicional-row" data-adicional-id="<?php echo esc_attr( $key ); ?>" data-price="<?php echo esc_attr( $ad_price ); ?>">
                            <div class="wooconfig-adicional-info">
                                <span class="wooconfig-adicional-name"><?php echo esc_html( $ad_label ); ?></span>
                                <span class="wooconfig-adicional-unit-price"><?php echo wc_price( $ad_price ); ?></span>
                            </div>
                            <div class="wooconfig-stepper-control wooconfig-stepper-sm">
                                <button type="button" class="wooconfig-stepper-btn wooconfig-adicional-minus" data-key="<?php echo esc_attr( $key ); ?>">-</button>
                                <input type="number" class="wooconfig-stepper-input wooconfig-adicional-input" id="wooconfig-ad-qty-<?php echo esc_attr( $key ); ?>" data-key="<?php echo esc_attr( $key ); ?>" value="0" min="0" />
                                <button type="button" class="wooconfig-stepper-btn wooconfig-adicional-plus" data-key="<?php echo esc_attr( $key ); ?>">+</button>
                            </div>
                            <div class="wooconfig-adicional-subtotal" id="wooconfig-ad-subtotal-<?php echo esc_attr( $key ); ?>">
                                <?php echo wc_price( 0 ); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $deposit_enabled = get_option( 'wooconfig_deposit_enabled', 'yes' );
        $deposit_percent = (int) get_option( 'wooconfig_deposit_percent', 50 );
        $allow_full      = get_option( 'wooconfig_allow_full_payment', 'yes' );

        $init_total   = $base_price * 1;
        $init_deposit = round( $init_total * ( $deposit_percent / 100 ) );
        $init_balance = $init_total - $init_deposit;
        ?>

        <!-- Live Calculated Total & Deposit Breakdown -->
        <div class="wooconfig-live-total-bar">
            <div class="wooconfig-total-left">
                <span class="wooconfig-total-text-label"><?php esc_html_e( 'MONTO TOTAL CONFECCIÓN', 'woo-config' ); ?></span>
                <span class="wooconfig-total-amount" id="wooconfig-onpage-total-amount"><?php echo wc_price( $init_total ); ?></span>
                <span class="wooconfig-total-tax-note"><?php esc_html_e( 'IVA INC', 'woo-config' ); ?></span>
            </div>
            <?php if ( 'yes' === $deposit_enabled ) : ?>
                <div class="wooconfig-deposit-highlight-tag">
                    <span class="wooconfig-deposit-tag-title"><?php echo esc_html( sprintf( __( 'Abono Inicial (%d%%)', 'woo-config' ), $deposit_percent ) ); ?></span>
                    <span class="wooconfig-deposit-tag-amount" id="wooconfig-onpage-deposit-tag-amount"><?php echo wc_price( $init_deposit ); ?></span>
                    <span class="wooconfig-deposit-tag-subtitle"><?php esc_html_e( 'Saldo al finalizar confección', 'woo-config' ); ?></span>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Selección de Flujo -->
    <div class="wooconfig-box-section wooconfig-flow-selector">
        <h3 class="wooconfig-widget-title"><?php esc_html_e( 'CONFIGURA TU EQUIPO:', 'woo-config' ); ?></h3>
        <p class="wooconfig-flow-subtitle"><?php esc_html_e( '¿Cómo prefieres ingresar las tallas, dorsales y otros datos?', 'woo-config' ); ?></p>

        <!-- Botón / Tarjeta Flujo 1: Rápido -->
        <div class="wooconfig-flow-card wooconfig-flow-card-quick" id="wooconfig-trigger-flow-quick">
            <div class="wooconfig-flow-card-header">
                <div class="wooconfig-flow-card-icon">
                    <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M31.153,0c2.675,0 4.847,2.172 4.847,4.847l0,26.307c0,2.675 -2.172,4.847 -4.847,4.847l-26.307,0c-2.675,0 -4.847,-2.172 -4.847,-4.847l0,-26.307c0,-2.675 2.172,-4.847 4.847,-4.847l26.307,0Z" fill="#96FF00"/>
                        <path d="M17.432,21.849l10.325,2.959l-10.325,2.959l-10.325,-2.959l10.325,-2.959Z" fill="none" stroke="#000000" stroke-width="1"/>
                        <path d="M17.432,19.337l10.325,2.959l-10.325,2.959l-10.325,-2.959l10.325,-2.959Z" fill="#96FF00" stroke="#000000" stroke-width="1"/>
                        <path d="M18.021,9.204c0.121,0 0.219,0.098 0.219,0.219l0,12.527c0,0.121 -0.098,0.219 -0.219,0.219l-1.188,0c-0.121,0 -0.219,-0.098 -0.219,-0.219l0,-12.527c0,-0.121 0.098,-0.219 0.219,-0.219l1.188,0Z" fill="#000000"/>
                        <path d="M18.425,8.432c0.085,0.085 0.085,0.224 0,0.31l-4.208,4.208c-0.085,0.085 -0.224,0.085 -0.31,-0l-0.84,-0.84c-0.085,-0.085 -0.085,-0.224 -0,-0.31l4.208,-4.208c0.085,-0.085 0.224,-0.085 0.31,0l0.84,0.84Z" fill="#000000"/>
                        <path d="M17.275,7.592c0.085,-0.085 0.224,-0.085 0.31,-0l4.117,4.117c0.085,0.085 0.085,0.224 -0,0.31l-0.84,0.84c-0.085,0.085 -0.224,0.085 -0.31,0l-4.117,-4.117c-0.085,-0.085 -0.085,-0.224 0,-0.31l0.84,-0.84Z" fill="#000000"/>
                    </svg>
                </div>
                <div class="wooconfig-flow-card-text">
                    <div class="wooconfig-flow-card-title-row">
                        <strong><?php esc_html_e( 'Subir archivo de datos', 'woo-config' ); ?></strong>
                        <span class="wooconfig-flow-badge"><?php esc_html_e( '⚡ Más rápido', 'woo-config' ); ?></span>
                    </div>
                    <span><?php esc_html_e( 'Sube una imagen o documento con las tallas y datos', 'woo-config' ); ?></span>
                </div>
                <div class="wooconfig-flow-toggle-arrow">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>
            </div>

            <!-- Panel desplegable Flujo 1 -->
            <div class="wooconfig-quick-panel" id="wooconfig-quick-panel" style="display: none;">
                <!-- Lista de archivos subidos en Flujo 1 -->
                <div class="wooconfig-files-list wooconfig-quick-files-list" id="wooconfig-quick-files-list"></div>

                <!-- Dropzone Flujo 1 -->
                <div class="wooconfig-dropzone wooconfig-quick-dropzone" id="wooconfig-quick-dropzone">
                    <input type="file" id="wooconfig-quick-file-input" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.xlsx,.xls,.doc,.docx,.ai,.eps,.zip" style="display: none;">
                    <div class="wooconfig-dropzone-inner" id="wooconfig-quick-upload-trigger">
                        <div class="wooconfig-upload-icon-circle">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                        </div>
                        <span class="wooconfig-dropzone-main-text"><?php esc_html_e( 'Haz clic aquí para subir o arrastra tus archivos', 'woo-config' ); ?></span>
                        <span class="wooconfig-dropzone-sub-text"><?php esc_html_e( 'Fotos de notas, listas de WhatsApp o planillas Excel', 'woo-config' ); ?></span>
                    </div>
                </div>

                <div class="wooconfig-upload-progress-wrapper" id="wooconfig-quick-upload-progress" style="display: none;">
                    <div class="wooconfig-progress-bar"><div class="wooconfig-progress-fill" id="wooconfig-quick-progress-fill"></div></div>
                    <span class="wooconfig-progress-text" id="wooconfig-quick-progress-text">Subiendo archivos...</span>
                </div>

                <!-- Textarea Descripción -->
                <div class="wooconfig-field-group" style="margin-top: 15px;">
                    <label for="wooconfig-quick-design-desc" class="wooconfig-field-label">
                        <?php esc_html_e( 'Describe el diseño del equipo (opcional):', 'woo-config' ); ?>
                    </label>
                    <textarea id="wooconfig-quick-design-desc" class="wooconfig-textarea" rows="3" placeholder="<?php echo esc_attr( "Ejemplo: Colores azul y blanco, escudo al centro, números en la espalda..." ); ?>"></textarea>
                </div>

                <!-- Botón Añadir al Carrito (Flujo 1) -->
                <button type="button" class="wooconfig-btn-action-primary" id="wooconfig-quick-add-to-cart-btn">
                    <span id="wooconfig-quick-btn-text"><?php esc_html_e( 'Añadir al carrito', 'woo-config' ); ?></span>
                    <span class="wooconfig-spinner" id="wooconfig-quick-spinner" style="display: none;"></span>
                </button>
            </div>
        </div>

        <div class="wooconfig-flow-divider">
            <span><?php esc_html_e( '— O BIEN —', 'woo-config' ); ?></span>
        </div>

        <!-- Botón / Tarjeta Flujo 2: Paso a Paso -->
        <div class="wooconfig-flow-card wooconfig-flow-card-wizard" id="wooconfig-trigger-flow-wizard">
            <div class="wooconfig-flow-card-header">
                <div class="wooconfig-flow-card-icon">
                    <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M31.153,0c2.675,0 4.847,2.172 4.847,4.847l0,26.307c0,2.675 -2.172,4.847 -4.847,4.847l-26.307,0c-2.675,0 -4.847,-2.172 -4.847,-4.847l0,-26.307c0,-2.675 2.172,-4.847 4.847,-4.847l26.307,0Z" fill="#FF5400"/>
                        <g>
                            <rect x="9.92" y="8.457" width="16.161" height="19.086" fill="none" stroke="#000000" stroke-width="1"/>
                            <rect x="12.959" y="11.714" width="10.87" height="11.287" fill="none" stroke="#000000" stroke-width="1"/>
                            <path d="M12.959,14.037l10.87,0" fill="none" stroke="#000000" stroke-width="1"/>
                            <path d="M12.959,16.278l10.87,0" fill="none" stroke="#000000" stroke-width="1"/>
                            <path d="M12.959,18.519l10.87,0" fill="none" stroke="#000000" stroke-width="1"/>
                            <path d="M12.959,20.76l10.87,0" fill="none" stroke="#000000" stroke-width="1"/>
                            <path d="M18.242,11.714l0,11.287" fill="none" stroke="#000000" stroke-width="1"/>
                        </g>
                        <path d="M24.378,21.825l-4.393,1.176l1.176,-4.393l8.672,-8.672l3.217,3.217l-8.672,8.672Z" fill="#FF5400" stroke="#000000" stroke-width="1"/>
                    </svg>
                </div>
                <div class="wooconfig-flow-card-text">
                    <div class="wooconfig-flow-card-title-row">
                        <strong><?php esc_html_e( 'Tabla de datos', 'woo-config' ); ?></strong>
                        <span class="wooconfig-flow-badge wooconfig-flow-badge-precise"><?php esc_html_e( '🎯 Más preciso', 'woo-config' ); ?></span>
                    </div>
                    <span><?php esc_html_e( 'Ingresa la lista de jugadores y números paso a paso en pantalla', 'woo-config' ); ?></span>
                </div>
                <div class="wooconfig-flow-card-action">
                    <span class="wooconfig-btn-arrow">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </span>
                </div>
            </div>
        </div>

    </div>

</div>
