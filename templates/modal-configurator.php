<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

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
