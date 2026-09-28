<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<!-- WooConfig Main 4-Step Modal Root Wrapper -->
<div id="wooconfig-modal-wrapper" class="wooconfig-modal-wrapper" style="display: none;">
    <div class="wooconfig-overlay" id="wooconfig-overlay"></div>

    <div class="wooconfig-modal-container" role="dialog" aria-modal="true">

        <!-- Top Header & Stepper Progress Bar -->
        <div class="wooconfig-modal-top-bar">
            <div class="wooconfig-modal-title-row">
                <h3 class="wooconfig-modal-main-title"><?php esc_html_e( 'Configura tu compra', 'woo-config' ); ?></h3>
                <button type="button" class="wooconfig-modal-close-x" id="wooconfig-header-close-btn" title="<?php esc_attr_e( 'Cerrar', 'woo-config' ); ?>">✖</button>
            </div>
            
            <!-- 4-Segment Progress Bar -->
            <div class="wooconfig-stepper-progress-bar">
                <div class="wooconfig-prog-segment is-completed" data-step="1"></div>
                <div class="wooconfig-prog-segment is-active" data-step="2" id="wooconfig-prog-seg-2"></div>
                <div class="wooconfig-prog-segment" data-step="3" id="wooconfig-prog-seg-3"></div>
                <div class="wooconfig-prog-segment" data-step="4" id="wooconfig-prog-seg-4"></div>
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

        <!-- ================= STEP 2: DISEÑO ================= -->
        <div class="wooconfig-step-view is-active" id="wooconfig-step-2">
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
                    <span class="wooconfig-modal-total-label"><?php esc_html_e( 'Valor total', 'woo-config' ); ?></span>
                    <div class="wooconfig-modal-total-sub-details">
                        <span class="wooconfig-live-units-label">10 unidades</span>
                        <span class="wooconfig-live-ads-label">0 adicionales</span>
                    </div>
                    <span class="wooconfig-modal-total-amount">$0</span>
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
                    <span class="wooconfig-modal-total-label"><?php esc_html_e( 'Valor total', 'woo-config' ); ?></span>
                    <div class="wooconfig-modal-total-sub-details">
                        <span class="wooconfig-live-units-label">10 unidades</span>
                        <span class="wooconfig-live-ads-label">0 adicionales</span>
                    </div>
                    <span class="wooconfig-modal-total-amount">$0</span>
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
                    <span class="wooconfig-modal-total-label"><?php esc_html_e( 'Valor total', 'woo-config' ); ?></span>
                    <div class="wooconfig-modal-total-sub-details">
                        <span class="wooconfig-live-units-label">10 unidades</span>
                        <span class="wooconfig-live-ads-label">0 adicionales</span>
                    </div>
                    <span class="wooconfig-modal-total-amount">$0</span>
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
</div>

<!-- ================= SUB-MODAL: TABLA DE JUGADORES ================= -->
<div id="wooconfig-table-modal" class="wooconfig-table-modal-wrapper" style="display: none;">
    <div class="wooconfig-table-overlay" id="wooconfig-table-overlay"></div>

    <div class="wooconfig-table-dialog" role="dialog" aria-modal="true">
        <div class="wooconfig-table-header">
            <h3 class="wooconfig-table-dialog-title"><?php esc_html_e( 'Tabla de jugadores', 'woo-config' ); ?></h3>
            <button type="button" class="wooconfig-table-close-x" id="wooconfig-close-table-x-btn" title="<?php esc_attr_e( 'Cerrar', 'woo-config' ); ?>">✖</button>
        </div>

        <div class="wooconfig-table-instruction-row">
            <p class="wooconfig-table-instruction-text">
                <?php esc_html_e( 'Asigna los datos de cada jugador. Si necesitas cambiar cantidad o adicionales, ve al paso 1 y regresa.', 'woo-config' ); ?>
            </p>
            <button type="button" class="wooconfig-help-circle-btn" data-help="tabla" title="<?php esc_attr_e( 'Ver instrucciones', 'woo-config' ); ?>">?</button>
        </div>

        <!-- Adicionales Disponibles vs Asignados Live Badges -->
        <div class="wooconfig-table-ads-badges-wrap" id="wooconfig-table-ads-badges"></div>

        <!-- Live warning banner for adicionales limit -->
        <div class="wooconfig-table-ad-warning" id="wooconfig-table-ad-warning" style="display: none;">
            <span class="wooconfig-ad-warning-text"></span>
        </div>

        <!-- Scrollable Table Container -->
        <div class="wooconfig-table-scroll-wrap">
            <table class="wooconfig-interactive-table" id="wooconfig-interactive-table">
                <thead>
                    <tr id="wooconfig-table-thead-row">
                        <th class="col-prenda">#</th>
                        <th class="col-dorsal" title="<?php esc_attr_e( 'Número de dorsal', 'woo-config' ); ?>"><?php esc_html_e( 'N°', 'woo-config' ); ?></th>
                        <th class="col-name"><?php esc_html_e( 'Nombre', 'woo-config' ); ?></th>
                        <th class="col-size"><?php esc_html_e( 'Talla', 'woo-config' ); ?></th>
                        <!-- Dynamic columns for adicionales appended via JS -->
                    </tr>
                </thead>
                <tbody id="wooconfig-table-tbody">
                    <!-- Dynamic rows rendered via JS -->
                </tbody>
            </table>
        </div>

        <div class="wooconfig-table-footer">
            <button type="button" class="wooconfig-btn-green-save" id="wooconfig-btn-save-table">
                <?php esc_html_e( 'Guardar y cerrar', 'woo-config' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- ================= HELP / EXAMPLE MODAL POPUP ================= -->
<div id="wooconfig-help-modal" class="wooconfig-help-modal-wrapper" style="display: none;">
    <div class="wooconfig-help-overlay" id="wooconfig-help-overlay"></div>
    <div class="wooconfig-help-dialog">
        <div class="wooconfig-help-header">
            <h4 class="wooconfig-help-title" id="wooconfig-help-modal-title"><?php esc_html_e( 'Instrucciones', 'woo-config' ); ?></h4>
            <button type="button" class="wooconfig-help-close-x" id="wooconfig-help-close-btn">✖</button>
        </div>
        <div class="wooconfig-help-body">
            <img id="wooconfig-help-modal-img" src="" alt="Instrucciones" class="wooconfig-help-vertical-img" />
        </div>
        <div class="wooconfig-help-footer">
            <button type="button" class="wooconfig-btn-close-help" id="wooconfig-btn-close-help-action">
                <?php esc_html_e( 'Entendido / Continuar', 'woo-config' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- ================= SUCCESS MODAL DIALOG ================= -->
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
