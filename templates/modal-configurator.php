<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!-- WooConfig Modal Root Wrapper -->
<div id="wooconfig-modal-wrapper" class="wooconfig-modal-wrapper" style="display: none;">
    <div class="wooconfig-overlay" id="wooconfig-overlay"></div>

    <div class="wooconfig-modal-container" role="dialog" aria-modal="true">

        <!-- ================= STEP 1: CONFIGURAR EQUIPO ================= -->
        <div class="wooconfig-step-view" id="wooconfig-step-1">
            <div class="wooconfig-header">
                <h2><?php esc_html_e( 'Paso 1: Configurar equipo', 'woo-config' ); ?></h2>
                <button type="button" class="wooconfig-modal-header-close" id="wooconfig-header-close-btn" title="<?php esc_attr_e( 'Cerrar', 'woo-config' ); ?>">✖</button>
            </div>

            <div class="wooconfig-info-bar">
                <div class="wooconfig-info-text">
                    <p class="wooconfig-min-badge" id="wooconfig-min-badge-text">
                        <?php echo esc_html( sprintf( __( 'Pedido mínimo: %d unidad', 'woo-config' ), 1 ) ); ?>
                    </p>
                    <p class="wooconfig-hint">
                        <?php esc_html_e( 'Indica el número (dorsal), nombre del jugador y su talla.', 'woo-config' ); ?>
                    </p>
                </div>
                <div class="wooconfig-info-actions">
                    <button type="button" class="wooconfig-btn-guide" data-action="open-size-guide" id="wooconfig-btn-size-guide">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                            <line x1="6" y1="8" x2="6" y2="16"></line>
                            <line x1="10" y1="8" x2="10" y2="12"></line>
                            <line x1="14" y1="8" x2="14" y2="16"></line>
                            <line x1="18" y1="8" x2="18" y2="12"></line>
                        </svg>
                        <span><?php esc_html_e( 'Guía de tallas', 'woo-config' ); ?></span>
                    </button>
                    <button type="button" class="wooconfig-btn-reset" id="wooconfig-btn-reset-form" title="<?php esc_attr_e( 'Limpiar formulario', 'woo-config' ); ?>">
                        <span>🔄 <?php esc_html_e( 'Limpiar', 'woo-config' ); ?></span>
                    </button>
                </div>
            </div>

            <!-- Stepper de cantidad en Modal -->
            <div class="wooconfig-modal-qty-bar">
                <span class="wooconfig-modal-qty-label"><?php esc_html_e( 'Total de productos:', 'woo-config' ); ?></span>
                <div class="wooconfig-stepper-control wooconfig-stepper-sm">
                    <button type="button" class="wooconfig-stepper-btn" id="wooconfig-modal-qty-minus">-</button>
                    <input type="number" id="wooconfig-modal-qty-input" class="wooconfig-stepper-input" value="5" min="1" step="1" />
                    <button type="button" class="wooconfig-stepper-btn" id="wooconfig-modal-qty-plus">+</button>
                </div>
            </div>

            <div class="wooconfig-body">
                <div class="wooconfig-players-list" id="wooconfig-players-list">
                    <!-- Dynamic player cards rendered via JS -->
                </div>

                <div class="wooconfig-add-player-wrapper">
                    <button type="button" class="wooconfig-btn-add-player" id="wooconfig-add-player-btn">
                        <span class="wooconfig-plus-icon">+</span> <?php esc_html_e( 'Agregar jugador', 'woo-config' ); ?>
                    </button>
                </div>
            </div>

            <div class="wooconfig-footer">
                <button type="button" class="wooconfig-btn-close" id="wooconfig-close-step-1">
                    <span class="wooconfig-close-icon">✖</span>
                    <span><?php esc_html_e( 'Cerrar', 'woo-config' ); ?></span>
                </button>

                <div class="wooconfig-summary-block">
                    <div class="wooconfig-summary-title" id="wooconfig-summary-product-title"></div>
                    <div class="wooconfig-summary-qty" id="wooconfig-summary-qty-text">0 unidades</div>
                    <div class="wooconfig-summary-total" id="wooconfig-summary-total-price">$0</div>
                </div>

                <button type="button" class="wooconfig-btn-next" id="wooconfig-goto-step-2">
                    <span id="wooconfig-step1-next-btn-text"><?php esc_html_e( 'Siguiente paso: Adicionales >>', 'woo-config' ); ?></span>
                </button>
            </div>
        </div>

        <!-- ================= STEP 2: ADICIONALES ================= -->
        <div class="wooconfig-step-view" id="wooconfig-step-2" style="display: none;">
            <div class="wooconfig-header">
                <h2><?php esc_html_e( 'Paso 2: Adicionales', 'woo-config' ); ?></h2>
                <button type="button" class="wooconfig-modal-header-close" id="wooconfig-close-step-2-x" title="<?php esc_attr_e( 'Cerrar', 'woo-config' ); ?>">✖</button>
            </div>

            <div class="wooconfig-info-bar">
                <div class="wooconfig-info-text">
                    <p class="wooconfig-hint" style="font-size: 13px; font-weight: 600; color: #111;">
                        <?php esc_html_e( 'Asigne los adicionales a cada jugador:', 'woo-config' ); ?>
                    </p>
                </div>
            </div>

            <!-- Adicionales global counts summary in Step 2 -->
            <div class="wooconfig-step2-adicionales-summary" id="wooconfig-step2-adicionales-summary">
                <!-- Injected via JS with counters and subtotals -->
            </div>

            <div class="wooconfig-body">
                <div class="wooconfig-step2-players-list" id="wooconfig-step2-players-list">
                    <!-- Dynamic player rows with additional toggles rendered via JS -->
                </div>
            </div>

            <div class="wooconfig-footer">
                <button type="button" class="wooconfig-btn-back" id="wooconfig-step2-backto-step1">
                    <span class="wooconfig-back-icon">❮❮</span>
                </button>

                <div class="wooconfig-summary-block">
                    <div class="wooconfig-summary-title" id="wooconfig-step2-product-title"></div>
                    <div class="wooconfig-summary-qty" id="wooconfig-step2-qty-text">0 unidades</div>
                    <div class="wooconfig-summary-total" id="wooconfig-step2-total-price">$0</div>
                </div>

                <button type="button" class="wooconfig-btn-next" id="wooconfig-goto-step-3">
                    <span><?php esc_html_e( 'Siguiente paso: Diseño >>', 'woo-config' ); ?></span>
                </button>
            </div>
        </div>

        <!-- ================= STEP 3: DISEÑO ================= -->
        <div class="wooconfig-step-view" id="wooconfig-step-3" style="display: none;">
            <div class="wooconfig-header">
                <h2 id="wooconfig-step3-title"><?php esc_html_e( 'Paso 3: Diseño', 'woo-config' ); ?></h2>
                <button type="button" class="wooconfig-modal-header-close" id="wooconfig-close-step-3-x" title="<?php esc_attr_e( 'Cerrar', 'woo-config' ); ?>">✖</button>
            </div>

            <div class="wooconfig-body">
                <div class="wooconfig-design-section">
                    <h3 class="wooconfig-section-title"><?php esc_html_e( 'Describe tu diseño', 'woo-config' ); ?></h3>
                    <label for="wooconfig-design-desc" class="wooconfig-field-label">
                        <?php esc_html_e( 'Describe el diseño del equipo', 'woo-config' ); ?> <span class="required">*</span>
                    </label>
                    <textarea id="wooconfig-design-desc" class="wooconfig-textarea" rows="5" placeholder="<?php echo esc_attr( "Ejemplos:\n- Uniforme selección chilena 2026\n- Como en la imagen adjunta\n- Ayúdenos a diseñar" ); ?>"></textarea>
                </div>

                <div class="wooconfig-upload-section">
                    <label class="wooconfig-field-label">
                        <?php esc_html_e( 'Aquí puedes cargar imágenes o diseños', 'woo-config' ); ?>
                    </label>
                    <p class="wooconfig-field-sublabel">
                        <?php esc_html_e( 'instrucción (usa referencias o diseño, boceto, planilla Excel, foto cuaderno etc)---', 'woo-config' ); ?>
                    </p>

                    <div class="wooconfig-dropzone" id="wooconfig-dropzone">
                        <input type="file" id="wooconfig-file-input" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.xlsx,.xls,.doc,.docx,.ai,.eps,.zip" style="display: none;">
                        <button type="button" class="wooconfig-btn-upload" id="wooconfig-btn-trigger-upload">
                            <?php esc_html_e( 'Subir archivos (jpg, pdf, ai)', 'woo-config' ); ?>
                        </button>
                        <p class="wooconfig-dropzone-hint"><?php esc_html_e( 'O arrastra tus archivos aquí (Máx 20MB cada uno)', 'woo-config' ); ?></p>
                    </div>

                    <div class="wooconfig-upload-progress-wrapper" id="wooconfig-upload-progress" style="display: none;">
                        <div class="wooconfig-progress-bar"><div class="wooconfig-progress-fill" id="wooconfig-progress-fill"></div></div>
                        <span class="wooconfig-progress-text" id="wooconfig-progress-text">Subiendo archivos...</span>
                    </div>

                    <div class="wooconfig-files-list" id="wooconfig-files-list"></div>
                </div>

                <!-- Modal Step 3 Payment Breakdown Box -->
                <div class="wooconfig-step3-payment-summary" id="wooconfig-step3-payment-box">
                    <div class="wooconfig-step3-payment-title">
                        💳 <?php esc_html_e( 'Resumen de Pago y Anticipo', 'woo-config' ); ?>
                    </div>
                    <div class="wooconfig-step3-payment-rows">
                        <div class="wooconfig-prow">
                            <span><?php esc_html_e( 'Total confección:', 'woo-config' ); ?></span>
                            <strong id="wooconfig-modal-full-total">$0</strong>
                        </div>
                        <div class="wooconfig-prow wooconfig-prow-deposit">
                            <span><?php esc_html_e( 'Abono inicial hoy:', 'woo-config' ); ?></span>
                            <strong id="wooconfig-modal-deposit-val">$0</strong>
                        </div>
                        <div class="wooconfig-prow wooconfig-prow-balance">
                            <span><?php esc_html_e( 'Saldo al terminar confección:', 'woo-config' ); ?></span>
                            <strong id="wooconfig-modal-balance-val">$0</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wooconfig-footer">
                <button type="button" class="wooconfig-btn-close" id="wooconfig-step3-backto-step2">
                    <span class="wooconfig-back-icon">❮❮</span>
                </button>

                <div class="wooconfig-summary-block">
                    <div class="wooconfig-summary-title" id="wooconfig-step3-product-title"></div>
                    <div class="wooconfig-summary-qty" id="wooconfig-step3-qty-text">0 unidades</div>
                    <div class="wooconfig-summary-total" id="wooconfig-step3-total-price">$0</div>
                </div>

                <button type="button" class="wooconfig-btn-submit" id="wooconfig-submit-cart">
                    <span id="wooconfig-submit-btn-text"><?php esc_html_e( 'Añadir al carrito', 'woo-config' ); ?></span>
                    <span class="wooconfig-spinner" id="wooconfig-submit-spinner" style="display: none;"></span>
                </button>
            </div>
        </div>

        <!-- ================= SUCCESS TOAST / MODAL ================= -->
        <div class="wooconfig-success-overlay" id="wooconfig-success-dialog" style="display: none;">
            <div class="wooconfig-success-card">
                <div class="wooconfig-success-icon">✓</div>
                <h3><?php esc_html_e( '¡Tu equipo ha sido añadido al carrito!', 'woo-config' ); ?></h3>
                <p id="wooconfig-success-subtitle"><?php esc_html_e( 'Las prendas personalizadas se encuentran listas para el pago.', 'woo-config' ); ?></p>
                <div class="wooconfig-success-actions">
                    <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="wooconfig-btn-primary" id="wooconfig-btn-go-checkout">
                        <?php esc_html_e( 'Ir a pagar / Carrito', 'woo-config' ); ?>
                    </a>
                    <button type="button" class="wooconfig-btn-secondary" id="wooconfig-btn-configure-another">
                        <?php esc_html_e( 'Configurar otro equipo', 'woo-config' ); ?>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>
