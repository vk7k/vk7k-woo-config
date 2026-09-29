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
$base_price     = (float) $product->get_price();

$adicionales = get_post_meta( $product_id, '_wooconfig_adicionales', true );
if ( ! is_array( $adicionales ) ) {
    $adicionales = array(
        'cuello_polo' => array( 'id' => 'cuello_polo', 'enabled' => 'yes', 'label' => __( 'Cuello piqué', 'woo-config' ), 'price' => 2500 ),
        'manga_larga' => array( 'id' => 'manga_larga', 'enabled' => 'yes', 'label' => __( 'Manga larga', 'woo-config' ), 'price' => 2500 ),
    );
} else {
    foreach ( $adicionales as $key => &$ad_item ) {
        if ( isset( $ad_item['label'] ) && false !== stripos( $ad_item['label'], 'polo' ) ) {
            $ad_item['label'] = __( 'Cuello piqué', 'woo-config' );
        }
    }
    unset( $ad_item );
}

$active_adicionales = array();
foreach ( $adicionales as $key => $ad ) {
    if ( isset( $ad['enabled'] ) && 'yes' === $ad['enabled'] ) {
        $active_adicionales[$key] = $ad;
    }
}

$deposit_enabled = get_option( 'wooconfig_deposit_enabled', 'yes' );
$deposit_percent = (int) get_option( 'wooconfig_deposit_percent', 50 );
$init_qty        = max( 1, $cart_min_units );
$init_total      = $base_price * $init_qty;
$init_deposit    = round( $init_total * ( $deposit_percent / 100 ) );
?>

<div class="wooconfig-widget-container wooconfig-unified-widget" id="wooconfig-onpage-widget" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-base-price="<?php echo esc_attr( $base_price ); ?>" data-min-units="1" data-cart-min-units="<?php echo esc_attr( $cart_min_units ); ?>">
    
    <!-- Top Header & 4-Segment Progress Bar -->
    <div class="wooconfig-widget-top-bar">
        <div class="wooconfig-widget-title-row">
            <h3 class="wooconfig-widget-main-title"><?php esc_html_e( 'Configura tu compra', 'woo-config' ); ?></h3>
            <span class="wooconfig-widget-step-badge" id="wooconfig-current-step-badge"><?php esc_html_e( 'Paso 1 de 4', 'woo-config' ); ?></span>
        </div>
        
        <!-- 4-Segment Progress Bar -->
        <div class="wooconfig-stepper-progress-bar">
            <div class="wooconfig-prog-segment is-active" data-step="1" id="wooconfig-prog-seg-1" title="<?php esc_attr_e( '1. Cantidades', 'woo-config' ); ?>"></div>
            <div class="wooconfig-prog-segment" data-step="2" id="wooconfig-prog-seg-2" title="<?php esc_attr_e( '2. Diseño', 'woo-config' ); ?>"></div>
            <div class="wooconfig-prog-segment" data-step="3" id="wooconfig-prog-seg-3" title="<?php esc_attr_e( '3. Sponsors', 'woo-config' ); ?>"></div>
            <div class="wooconfig-prog-segment" data-step="4" id="wooconfig-prog-seg-4" title="<?php esc_attr_e( '4. Jugadores y tallas', 'woo-config' ); ?>"></div>
        </div>

        <!-- Stepper Labels (Subtle tabs) -->
        <div class="wooconfig-stepper-labels">
            <span class="wooconfig-stepper-label is-active" data-step="1"><?php esc_html_e( '1. Cantidades', 'woo-config' ); ?></span>
            <span class="wooconfig-stepper-label" data-step="2"><?php esc_html_e( '2. Diseño', 'woo-config' ); ?></span>
            <span class="wooconfig-stepper-label" data-step="3"><?php esc_html_e( '3. Sponsors', 'woo-config' ); ?></span>
            <span class="wooconfig-stepper-label" data-step="4"><?php esc_html_e( '4. Jugadores', 'woo-config' ); ?></span>
        </div>
    </div>

    <!-- Global Validation Error Banner (When "Añadir al carrito" fails) -->
    <div class="wooconfig-validation-banner" id="wooconfig-validation-banner" style="display: none;">
        <div class="wooconfig-validation-banner-inner">
            <span class="wooconfig-validation-banner-icon">⚠️</span>
            <div class="wooconfig-validation-banner-content">
                <strong><?php esc_html_e( 'Por favor revisa los siguientes pasos antes de continuar:', 'woo-config' ); ?></strong>
                <ul id="wooconfig-validation-errors-list"></ul>
            </div>
        </div>
    </div>

    <!-- ================= STEP 1: CANTIDADES ================= -->
    <div class="wooconfig-step-view is-active" id="wooconfig-step-1">
        <div class="wooconfig-step-header">
            <div class="wooconfig-step-title-col">
                <h2 class="wooconfig-step-number-title"><?php esc_html_e( '1 - Cantidades', 'woo-config' ); ?></h2>
                <p class="wooconfig-step-desc"><?php esc_html_e( 'Indica la cantidad que deseas ordenar', 'woo-config' ); ?></p>
            </div>
            <button type="button" class="wooconfig-btn-ver-ejemplo" data-help="cantidades">
                <span class="wooconfig-ejemplo-icon">?</span>
                <span class="wooconfig-ejemplo-text"><?php esc_html_e( 'ver ejemplo', 'woo-config' ); ?></span>
            </button>
        </div>

        <div class="wooconfig-step-body">
            <!-- Card 1: Cantidad de prendas o sets -->
            <div class="wooconfig-card-box">
                <div class="wooconfig-card-header-row">
                    <span class="wooconfig-card-header-title"><?php esc_html_e( 'Cantidad de prendas o sets', 'woo-config' ); ?></span>
                    <button type="button" class="wooconfig-help-circle-btn" data-help="cantidades" title="<?php esc_attr_e( 'Ver instrucciones', 'woo-config' ); ?>">?</button>
                </div>

                <div class="wooconfig-qty-item-row">
                    <span class="wooconfig-qty-item-name"><?php esc_html_e( 'Total de productos', 'woo-config' ); ?></span>
                    <div class="wooconfig-stepper-control">
                        <button type="button" class="wooconfig-stepper-btn" id="wooconfig-main-qty-minus">-</button>
                        <input type="number" id="wooconfig-main-qty-input" class="wooconfig-stepper-input" value="<?php echo esc_attr( $init_qty ); ?>" min="1" step="1" />
                        <button type="button" class="wooconfig-stepper-btn" id="wooconfig-main-qty-plus">+</button>
                    </div>
                    <span class="wooconfig-qty-item-price" id="wooconfig-main-subtotal"><?php echo wc_price( $init_total ); ?></span>
                </div>

                <?php if ( $cart_min_units > 1 ) : ?>
                    <div class="wooconfig-cart-min-note">
                        <span>💡</span>
                        <span><?php echo esc_html( sprintf( __( 'Pedido mínimo de confección: %d prendas en total en tu pedido.', 'woo-config' ), $cart_min_units ) ); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card 2: Cantidad de adicionales (si existen) -->
            <?php if ( ! empty( $active_adicionales ) ) : ?>
                <div class="wooconfig-card-box">
                    <div class="wooconfig-card-header-row">
                        <span class="wooconfig-card-header-title"><?php esc_html_e( 'Cantidad de adicionales', 'woo-config' ); ?></span>
                        <button type="button" class="wooconfig-help-circle-btn" data-help="adicionales" title="<?php esc_attr_e( 'Ver instrucciones', 'woo-config' ); ?>">?</button>
                    </div>

                    <div class="wooconfig-adicionales-list">
                        <?php foreach ( $active_adicionales as $key => $ad ) : 
                            $ad_price = isset( $ad['price'] ) ? (float) $ad['price'] : 2500;
                            $ad_label = isset( $ad['label'] ) ? $ad['label'] : $key;
                        ?>
                            <div class="wooconfig-qty-item-row wooconfig-adicional-row" data-adicional-id="<?php echo esc_attr( $key ); ?>" data-price="<?php echo esc_attr( $ad_price ); ?>">
                                <span class="wooconfig-qty-item-name"><?php echo esc_html( sprintf( __( 'Con %s', 'woo-config' ), mb_strtolower( $ad_label, 'UTF-8' ) ) ); ?></span>
                                <div class="wooconfig-stepper-control">
                                    <button type="button" class="wooconfig-stepper-btn wooconfig-adicional-minus" data-key="<?php echo esc_attr( $key ); ?>">-</button>
                                    <input type="number" class="wooconfig-stepper-input wooconfig-adicional-input" id="wooconfig-ad-qty-<?php echo esc_attr( $key ); ?>" data-key="<?php echo esc_attr( $key ); ?>" value="0" min="0" />
                                    <button type="button" class="wooconfig-stepper-btn wooconfig-adicional-plus" data-key="<?php echo esc_attr( $key ); ?>">+</button>
                                </div>
                                <span class="wooconfig-qty-item-price" id="wooconfig-ad-subtotal-<?php echo esc_attr( $key ); ?>"><?php echo wc_price( 0 ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="wooconfig-step-footer">
            <!-- Resumen Valor Total -->
            <div class="wooconfig-modal-total-bar">
                <div class="wooconfig-total-top-line">
                    <span class="wooconfig-modal-total-label"><?php esc_html_e( 'Valor total', 'woo-config' ); ?></span>
                    <div class="wooconfig-modal-total-sub-details">
                        <span class="wooconfig-live-units-label" id="wooconfig-total-units-text"><?php echo esc_html( sprintf( _n( '%d unidad', '%d unidades', $init_qty, 'woo-config' ), $init_qty ) ); ?></span>
                        <span class="wooconfig-live-ads-label" id="wooconfig-total-ads-text"><?php esc_html_e( '0 adicionales', 'woo-config' ); ?></span>
                    </div>
                    <span class="wooconfig-modal-total-amount" id="wooconfig-onpage-total-amount"><?php echo wc_price( $init_total ); ?></span>
                </div>
                <?php if ( 'yes' === $deposit_enabled ) : ?>
                    <div class="wooconfig-deposit-info-pill">
                        <span><?php echo esc_html( sprintf( __( 'Abono Inicial (%d%% hoy):', 'woo-config' ), $deposit_percent ) ); ?> <strong class="wooconfig-live-deposit-amount" id="wooconfig-onpage-deposit-tag-amount"><?php echo wc_price( $init_deposit ); ?></strong></span>
                        <span class="wooconfig-deposit-balance-hint"><?php esc_html_e( 'Saldo al finalizar confección', 'woo-config' ); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer Navigation -->
            <div class="wooconfig-modal-nav-footer">
                <button type="button" class="wooconfig-btn-green-next" id="wooconfig-step1-goto-step2">
                    <span><?php esc_html_e( 'Siguiente: Diseño', 'woo-config' ); ?></span>
                    <svg class="wooconfig-chevron-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- ================= STEP 2: DISEÑO ================= -->
    <div class="wooconfig-step-view" id="wooconfig-step-2" style="display: none;">
        <div class="wooconfig-step-header">
            <div class="wooconfig-step-title-col">
                <h2 class="wooconfig-step-number-title"><?php esc_html_e( '2 - Diseño', 'woo-config' ); ?></h2>
                <p class="wooconfig-step-desc"><?php esc_html_e( 'Sube aquí un archivo del producto', 'woo-config' ); ?></p>
            </div>
            <button type="button" class="wooconfig-btn-ver-ejemplo" data-help="diseno">
                <span class="wooconfig-ejemplo-icon">?</span>
                <span class="wooconfig-ejemplo-text"><?php esc_html_e( 'ver ejemplo', 'woo-config' ); ?></span>
            </button>
        </div>

        <div class="wooconfig-step-body">
            <!-- Uploaded Files List (Diseño) -->
            <div class="wooconfig-uploaded-files-list" id="wooconfig-design-files-list"></div>

            <!-- Dashed Dropzone (Diseño) -->
            <div class="wooconfig-dashed-dropzone" id="wooconfig-design-dropzone">
                <input type="file" id="wooconfig-design-file-input" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.ai,.eps,.zip" style="display: none;">
                <div class="wooconfig-dropzone-inner" id="wooconfig-design-dropzone-trigger">
                    <div class="wooconfig-dropzone-icon-circle">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                    </div>
                    <span class="wooconfig-dropzone-text-primary"><?php esc_html_e( 'Arrastra o sube aquí un archivo con el diseño de tu equipo', 'woo-config' ); ?></span>
                    <span class="wooconfig-dropzone-text-secondary"><?php esc_html_e( '(max 20mb)', 'woo-config' ); ?></span>
                </div>
            </div>

            <!-- Upload Progress (Diseño) -->
            <div class="wooconfig-upload-progress-wrapper" id="wooconfig-design-progress" style="display: none;">
                <div class="wooconfig-progress-bar"><div class="wooconfig-progress-fill" id="wooconfig-design-progress-fill"></div></div>
                <span class="wooconfig-progress-text" id="wooconfig-design-progress-text"><?php esc_html_e( 'Subiendo archivo...', 'woo-config' ); ?></span>
            </div>

            <!-- Textarea Notas (Diseño) -->
            <div class="wooconfig-notes-group">
                <label for="wooconfig-design-notes" class="wooconfig-notes-label"><?php esc_html_e( 'Notas (opcional):', 'woo-config' ); ?></label>
                <textarea id="wooconfig-design-notes" class="wooconfig-textarea" rows="3" placeholder="<?php echo esc_attr( 'Cualquier nota o enlace Drive, dropbox, etc.' ); ?>"></textarea>
            </div>
        </div>

        <div class="wooconfig-step-footer">
            <!-- Resumen Valor Total -->
            <div class="wooconfig-modal-total-bar">
                <div class="wooconfig-total-top-line">
                    <span class="wooconfig-modal-total-label"><?php esc_html_e( 'Valor total', 'woo-config' ); ?></span>
                    <div class="wooconfig-modal-total-sub-details">
                        <span class="wooconfig-live-units-label"><?php echo esc_html( sprintf( _n( '%d unidad', '%d unidades', $init_qty, 'woo-config' ), $init_qty ) ); ?></span>
                        <span class="wooconfig-live-ads-label"><?php esc_html_e( '0 adicionales', 'woo-config' ); ?></span>
                    </div>
                    <span class="wooconfig-modal-total-amount"><?php echo wc_price( $init_total ); ?></span>
                </div>
                <?php if ( 'yes' === $deposit_enabled ) : ?>
                    <div class="wooconfig-deposit-info-pill">
                        <span><?php echo esc_html( sprintf( __( 'Abono Inicial (%d%% hoy):', 'woo-config' ), $deposit_percent ) ); ?> <strong class="wooconfig-live-deposit-amount"><?php echo wc_price( $init_deposit ); ?></strong></span>
                        <span class="wooconfig-deposit-balance-hint"><?php esc_html_e( 'Saldo al finalizar confección', 'woo-config' ); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer Navigation -->
            <div class="wooconfig-modal-nav-footer">
                <button type="button" class="wooconfig-btn-nav-back" id="wooconfig-step2-back-to-step1">
                    <span>❮ <?php esc_html_e( 'Volver a Cantidades', 'woo-config' ); ?></span>
                </button>
                <button type="button" class="wooconfig-btn-green-next" id="wooconfig-step2-goto-step3">
                    <span><?php esc_html_e( 'Siguiente: Sponsors', 'woo-config' ); ?></span>
                    <svg class="wooconfig-chevron-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- ================= STEP 3: SPONSORS ================= -->
    <div class="wooconfig-step-view" id="wooconfig-step-3" style="display: none;">
        <div class="wooconfig-step-header">
            <div class="wooconfig-step-title-col">
                <h2 class="wooconfig-step-number-title"><?php esc_html_e( '3 - Sponsors', 'woo-config' ); ?></h2>
                <p class="wooconfig-step-desc"><?php esc_html_e( 'Sube los logos de los sponsors', 'woo-config' ); ?></p>
            </div>
            <button type="button" class="wooconfig-btn-ver-ejemplo" data-help="sponsors">
                <span class="wooconfig-ejemplo-icon">?</span>
                <span class="wooconfig-ejemplo-text"><?php esc_html_e( 'ver ejemplo', 'woo-config' ); ?></span>
            </button>
        </div>

        <div class="wooconfig-step-body">
            <!-- Uploaded Files List (Sponsors) -->
            <div class="wooconfig-uploaded-files-list" id="wooconfig-sponsors-files-list"></div>

            <!-- Dashed Dropzone (Sponsors) -->
            <div class="wooconfig-dashed-dropzone" id="wooconfig-sponsors-dropzone">
                <input type="file" id="wooconfig-sponsors-file-input" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.ai,.eps,.svg,.zip" style="display: none;">
                <div class="wooconfig-dropzone-inner" id="wooconfig-sponsors-dropzone-trigger">
                    <div class="wooconfig-dropzone-icon-circle">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                    </div>
                    <span class="wooconfig-dropzone-text-primary"><?php esc_html_e( 'Arrastra o sube aquí los logos de los sponsors (alta resolución o vector)', 'woo-config' ); ?></span>
                </div>
            </div>

            <!-- Upload Progress (Sponsors) -->
            <div class="wooconfig-upload-progress-wrapper" id="wooconfig-sponsors-progress" style="display: none;">
                <div class="wooconfig-progress-bar"><div class="wooconfig-progress-fill" id="wooconfig-sponsors-progress-fill"></div></div>
                <span class="wooconfig-progress-text" id="wooconfig-sponsors-progress-text"><?php esc_html_e( 'Subiendo archivo...', 'woo-config' ); ?></span>
            </div>

            <!-- Textarea Notas (Sponsors) -->
            <div class="wooconfig-notes-group">
                <label for="wooconfig-sponsors-notes" class="wooconfig-notes-label"><?php esc_html_e( 'Notas (opcional):', 'woo-config' ); ?></label>
                <textarea id="wooconfig-sponsors-notes" class="wooconfig-textarea" rows="3" placeholder="<?php echo esc_attr( 'Cualquier nota o enlace Drive, dropbox, etc.' ); ?>"></textarea>
            </div>
        </div>

        <div class="wooconfig-step-footer">
            <!-- Resumen Valor Total -->
            <div class="wooconfig-modal-total-bar">
                <div class="wooconfig-total-top-line">
                    <span class="wooconfig-modal-total-label"><?php esc_html_e( 'Valor total', 'woo-config' ); ?></span>
                    <div class="wooconfig-modal-total-sub-details">
                        <span class="wooconfig-live-units-label"><?php echo esc_html( sprintf( _n( '%d unidad', '%d unidades', $init_qty, 'woo-config' ), $init_qty ) ); ?></span>
                        <span class="wooconfig-live-ads-label"><?php esc_html_e( '0 adicionales', 'woo-config' ); ?></span>
                    </div>
                    <span class="wooconfig-modal-total-amount"><?php echo wc_price( $init_total ); ?></span>
                </div>
                <?php if ( 'yes' === $deposit_enabled ) : ?>
                    <div class="wooconfig-deposit-info-pill">
                        <span><?php echo esc_html( sprintf( __( 'Abono Inicial (%d%% hoy):', 'woo-config' ), $deposit_percent ) ); ?> <strong class="wooconfig-live-deposit-amount"><?php echo wc_price( $init_deposit ); ?></strong></span>
                        <span class="wooconfig-deposit-balance-hint"><?php esc_html_e( 'Saldo al finalizar confección', 'woo-config' ); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer Navigation -->
            <div class="wooconfig-modal-nav-footer">
                <button type="button" class="wooconfig-btn-nav-back" id="wooconfig-step3-back-to-step2">
                    <span>❮ <?php esc_html_e( 'Volver a Diseño', 'woo-config' ); ?></span>
                </button>
                <button type="button" class="wooconfig-btn-green-next" id="wooconfig-step3-goto-step4">
                    <span><?php esc_html_e( 'Siguiente: Jugadores y tallas', 'woo-config' ); ?></span>
                    <svg class="wooconfig-chevron-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- ================= STEP 4: JUGADORES Y TALLAS ================= -->
    <div class="wooconfig-step-view" id="wooconfig-step-4" style="display: none;">
        <div class="wooconfig-step-header">
            <div class="wooconfig-step-title-col">
                <h2 class="wooconfig-step-number-title"><?php esc_html_e( '4 - Jugadores y tallas', 'woo-config' ); ?></h2>
                <p class="wooconfig-step-desc"><?php esc_html_e( 'Sube la identificación de los jugadores y las tallas.', 'woo-config' ); ?></p>
            </div>
            <button type="button" class="wooconfig-btn-ver-ejemplo" data-help="jugadores">
                <span class="wooconfig-ejemplo-icon">?</span>
                <span class="wooconfig-ejemplo-text"><?php esc_html_e( 'ver ejemplo', 'woo-config' ); ?></span>
            </button>
        </div>

        <div class="wooconfig-step-body">
            <!-- Uploaded Files List (Jugadores) -->
            <div class="wooconfig-uploaded-files-list" id="wooconfig-players-files-list"></div>

            <!-- Dashed Dropzone (Jugadores max 1 archivo) -->
            <div class="wooconfig-dashed-dropzone" id="wooconfig-players-dropzone">
                <input type="file" id="wooconfig-players-file-input" accept=".jpg,.jpeg,.png,.webp,.pdf,.xlsx,.xls,.doc,.docx,.zip" style="display: none;">
                <div class="wooconfig-dropzone-inner" id="wooconfig-players-dropzone-trigger">
                    <div class="wooconfig-dropzone-icon-circle">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                    </div>
                    <span class="wooconfig-dropzone-text-primary"><?php esc_html_e( 'Sube un archivo con los datos de cada jugador (max 1)', 'woo-config' ); ?></span>
                </div>
            </div>

            <!-- Upload Progress (Jugadores) -->
            <div class="wooconfig-upload-progress-wrapper" id="wooconfig-players-progress" style="display: none;">
                <div class="wooconfig-progress-bar"><div class="wooconfig-progress-fill" id="wooconfig-players-progress-fill"></div></div>
                <span class="wooconfig-progress-text" id="wooconfig-players-progress-text"><?php esc_html_e( 'Subiendo archivo...', 'woo-config' ); ?></span>
            </div>

            <!-- Option Separator -->
            <div class="wooconfig-table-opt-prompt">
                <p><?php esc_html_e( 'Si prefieres, puedes llenar manualmente aquí:', 'woo-config' ); ?></p>
                <div class="wooconfig-table-trigger-row">
                    <button type="button" class="wooconfig-btn-green-table" id="wooconfig-btn-open-table">
                        <svg class="wooconfig-grid-ico" width="28" height="20" viewBox="0 0 28 20" fill="none" stroke="#000000" stroke-width="1.8">
                            <rect x="1" y="1" width="26" height="18" rx="2" stroke="#000000"/>
                            <line x1="1" y1="7" x2="27" y2="7"/>
                            <line x1="1" y1="13" x2="27" y2="13"/>
                            <line x1="9" y1="1" x2="9" y2="19"/>
                            <line x1="18" y1="1" x2="18" y2="19"/>
                        </svg>
                        <span id="wooconfig-table-btn-label"><?php esc_html_e( 'Usar tabla (opcional)', 'woo-config' ); ?></span>
                    </button>
                    <button type="button" class="wooconfig-help-circle-btn" data-help="tabla" title="<?php esc_attr_e( 'Instrucciones de la tabla', 'woo-config' ); ?>">?</button>
                </div>
                <div class="wooconfig-table-saved-status" id="wooconfig-table-saved-badge" style="display: none;">
                    <span>✓</span> <span id="wooconfig-table-saved-text"><?php esc_html_e( 'Tabla guardada correctamente', 'woo-config' ); ?></span>
                    <button type="button" class="wooconfig-table-clear-btn" id="wooconfig-btn-clear-table" title="<?php esc_attr_e( 'Limpiar tabla', 'woo-config' ); ?>">✖</button>
                </div>
            </div>
        </div>

        <div class="wooconfig-step-footer">
            <!-- Resumen Valor Total -->
            <div class="wooconfig-modal-total-bar">
                <div class="wooconfig-total-top-line">
                    <span class="wooconfig-modal-total-label"><?php esc_html_e( 'Valor total', 'woo-config' ); ?></span>
                    <div class="wooconfig-modal-total-sub-details">
                        <span class="wooconfig-live-units-label"><?php echo esc_html( sprintf( _n( '%d unidad', '%d unidades', $init_qty, 'woo-config' ), $init_qty ) ); ?></span>
                        <span class="wooconfig-live-ads-label"><?php esc_html_e( '0 adicionales', 'woo-config' ); ?></span>
                    </div>
                    <span class="wooconfig-modal-total-amount"><?php echo wc_price( $init_total ); ?></span>
                </div>
                <?php if ( 'yes' === $deposit_enabled ) : ?>
                    <div class="wooconfig-deposit-info-pill">
                        <span><?php echo esc_html( sprintf( __( 'Abono Inicial (%d%% hoy):', 'woo-config' ), $deposit_percent ) ); ?> <strong class="wooconfig-live-deposit-amount"><?php echo wc_price( $init_deposit ); ?></strong></span>
                        <span class="wooconfig-deposit-balance-hint"><?php esc_html_e( 'Saldo al finalizar confección', 'woo-config' ); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer Navigation -->
            <div class="wooconfig-modal-nav-footer">
                <button type="button" class="wooconfig-btn-nav-back" id="wooconfig-step4-back-to-step3">
                    <span>❮ <?php esc_html_e( 'Volver a Sponsors', 'woo-config' ); ?></span>
                </button>
                <button type="button" class="wooconfig-btn-green-next" id="wooconfig-submit-cart-btn">
                    <span id="wooconfig-submit-cart-label"><?php esc_html_e( 'Añadir y ver carrito', 'woo-config' ); ?></span>
                    <span class="wooconfig-spinner" id="wooconfig-submit-spinner" style="display: none;"></span>
                    <svg class="wooconfig-chevron-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>
        </div>
    </div>

</div>
