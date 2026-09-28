<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( empty( $config ) ) {
    return;
}

$gender_default = isset( $config['gender_default'] ) ? $config['gender_default'] : 'masculino';
$garment_scope  = isset( $config['garment_scope'] ) ? $config['garment_scope'] : 'all';
$top_label      = isset( $config['top_label'] ) ? $config['top_label'] : __( 'Camiseta / Polera', 'woo-config' );
$bottom_label   = isset( $config['bottom_label'] ) ? $config['bottom_label'] : __( 'Short Deportivo', 'woo-config' );
$tables         = isset( $config['tables'] ) ? $config['tables'] : array();
$image_url      = isset( $config['image_url'] ) ? $config['image_url'] : '';
?>

<!-- WooConfig Size Guide Standalone Modal Popup -->
<div class="wooconfig-guide-overlay" id="wooconfig-guide-dialog" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="wooconfig-guide-title">
    <div class="wooconfig-guide-modal-container">
        
        <!-- Header -->
        <div class="wooconfig-guide-modal-header">
            <div class="wooconfig-guide-title-wrap">
                <div class="wooconfig-guide-icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                        <line x1="6" y1="8" x2="6" y2="16"></line>
                        <line x1="10" y1="8" x2="10" y2="12"></line>
                        <line x1="14" y1="8" x2="14" y2="16"></line>
                        <line x1="18" y1="8" x2="18" y2="12"></line>
                    </svg>
                </div>
                <div>
                    <h3 id="wooconfig-guide-title" class="wooconfig-guide-modal-heading">
                        <?php esc_html_e( 'Guía y Tabla de Tallas Oficial', 'woo-config' ); ?>
                    </h3>
                    <p class="wooconfig-guide-modal-subheading">
                        <?php echo esc_html( $config['product_name'] ); ?>
                    </p>
                </div>
            </div>
            <button type="button" class="wooconfig-guide-close-btn" id="wooconfig-close-guide-btn" title="<?php esc_attr_e( 'Cerrar guía de tallas', 'woo-config' ); ?>">✖</button>
        </div>

        <!-- Gender / View Selector Tabs -->
        <div class="wooconfig-guide-tabs-bar">
            <button type="button" class="wooconfig-guide-tab-btn <?php echo ( 'masculino' === $gender_default ) ? 'is-active' : ''; ?>" data-gender="masculino">
                <span>👨 <?php esc_html_e( 'Corte Masculino / Varón', 'woo-config' ); ?></span>
            </button>
            <button type="button" class="wooconfig-guide-tab-btn <?php echo ( 'femenino' === $gender_default ) ? 'is-active' : ''; ?>" data-gender="femenino">
                <span>👩 <?php esc_html_e( 'Corte Femenino / Dama', 'woo-config' ); ?></span>
            </button>
            <?php if ( ! empty( $image_url ) ) : ?>
                <button type="button" class="wooconfig-guide-tab-btn" data-gender="image">
                    <span>🖼️ <?php esc_html_e( 'Infografía Oficial', 'woo-config' ); ?></span>
                </button>
            <?php endif; ?>
        </div>

        <!-- Modal Body Content -->
        <div class="wooconfig-guide-modal-body">
            
            <?php foreach ( array( 'masculino', 'femenino' ) as $gender_key ) : 
                $is_active_panel = ( $gender_key === $gender_default );
                $gender_data = isset( $tables[ $gender_key ] ) ? $tables[ $gender_key ] : array();
                $gender_title = ( 'masculino' === $gender_key ) ? __( 'Corte Masculino (Varón)', 'woo-config' ) : __( 'Corte Femenino (Dama)', 'woo-config' );
            ?>
                <div class="wooconfig-guide-gender-panel" id="wooconfig-guide-panel-<?php echo esc_attr( $gender_key ); ?>" style="<?php echo $is_active_panel ? 'display: block;' : 'display: none;'; ?>">
                    
                    <div class="wooconfig-guide-gender-badge-row">
                        <span class="wooconfig-gender-badge">
                            <?php echo ( 'masculino' === $gender_key ) ? '👨 ' : '👩 '; ?>
                            <?php echo esc_html( $gender_title ); ?>
                        </span>
                        <span class="wooconfig-unit-badge"><?php esc_html_e( 'Medidas en centímetros (cm)', 'woo-config' ); ?></span>
                    </div>

                    <!-- ================= TABLA INFANTIL ================= -->
                    <div class="wooconfig-guide-table-block">
                        <div class="wooconfig-guide-table-title">
                            <span>👶 <?php esc_html_e( 'Tallas Infantiles (Niño / Niña)', 'woo-config' ); ?></span>
                            <span class="wooconfig-guide-tag-sm"><?php esc_html_e( 'Talla 2 a 16', 'woo-config' ); ?></span>
                        </div>
                        <div class="wooconfig-table-responsive-wrapper">
                            <table class="wooconfig-table-sizes-modern">
                                <thead>
                                    <tr>
                                        <th class="col-talla"><?php esc_html_e( 'Talla', 'woo-config' ); ?></th>
                                        <?php if ( 'bottom' !== $garment_scope ) : ?>
                                            <th class="col-top-largo"><?php echo esc_html( $top_label . ' - ' . __( 'Largo (cm)', 'woo-config' ) ); ?></th>
                                            <th class="col-top-ancho"><?php echo esc_html( $top_label . ' - ' . __( 'Ancho (cm)', 'woo-config' ) ); ?></th>
                                        <?php endif; ?>
                                        <?php if ( 'top' !== $garment_scope ) : ?>
                                            <th class="col-bottom-largo"><?php echo esc_html( $bottom_label . ' - ' . __( 'Largo (cm)', 'woo-config' ) ); ?></th>
                                            <th class="col-bottom-ancho"><?php echo esc_html( $bottom_label . ' - ' . __( 'Ancho (cm)', 'woo-config' ) ); ?></th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $infantil_rows = isset( $gender_data['infantil'] ) ? $gender_data['infantil'] : array();
                                    foreach ( $infantil_rows as $row ) : 
                                    ?>
                                        <tr>
                                            <td class="col-talla"><strong><?php echo esc_html( $row['talla'] ); ?></strong></td>
                                            <?php if ( 'bottom' !== $garment_scope ) : ?>
                                                <td class="col-top-largo"><?php echo esc_html( $row['top_largo'] ); ?> cm</td>
                                                <td class="col-top-ancho"><?php echo esc_html( $row['top_ancho'] ); ?> cm</td>
                                            <?php endif; ?>
                                            <?php if ( 'top' !== $garment_scope ) : ?>
                                                <td class="col-bottom-largo"><?php echo esc_html( $row['bottom_largo'] ); ?> cm</td>
                                                <td class="col-bottom-ancho"><?php echo esc_html( $row['bottom_ancho'] ); ?> cm</td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ================= TABLA ADULTO ================= -->
                    <div class="wooconfig-guide-table-block" style="margin-top: 18px;">
                        <div class="wooconfig-guide-table-title">
                            <span>🧑 <?php esc_html_e( 'Tallas Adulto', 'woo-config' ); ?></span>
                            <span class="wooconfig-guide-tag-sm"><?php echo ( 'femenino' === $gender_key ) ? esc_html__( 'Talla XS a 3XL', 'woo-config' ) : esc_html__( 'Talla S a 3XL', 'woo-config' ); ?></span>
                        </div>
                        <div class="wooconfig-table-responsive-wrapper">
                            <table class="wooconfig-table-sizes-modern">
                                <thead>
                                    <tr>
                                        <th class="col-talla"><?php esc_html_e( 'Talla', 'woo-config' ); ?></th>
                                        <?php if ( 'bottom' !== $garment_scope ) : ?>
                                            <th class="col-top-largo"><?php echo esc_html( $top_label . ' - ' . __( 'Largo (cm)', 'woo-config' ) ); ?></th>
                                            <th class="col-top-ancho"><?php echo esc_html( $top_label . ' - ' . __( 'Ancho (cm)', 'woo-config' ) ); ?></th>
                                        <?php endif; ?>
                                        <?php if ( 'top' !== $garment_scope ) : ?>
                                            <th class="col-bottom-largo"><?php echo esc_html( $bottom_label . ' - ' . __( 'Largo (cm)', 'woo-config' ) ); ?></th>
                                            <th class="col-bottom-ancho"><?php echo esc_html( $bottom_label . ' - ' . __( 'Ancho (cm)', 'woo-config' ) ); ?></th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $adulto_rows = isset( $gender_data['adulto'] ) ? $gender_data['adulto'] : array();
                                    foreach ( $adulto_rows as $row ) : 
                                    ?>
                                        <tr>
                                            <td class="col-talla"><strong><?php echo esc_html( $row['talla'] ); ?></strong></td>
                                            <?php if ( 'bottom' !== $garment_scope ) : ?>
                                                <td class="col-top-largo"><?php echo esc_html( $row['top_largo'] ); ?> cm</td>
                                                <td class="col-top-ancho"><?php echo esc_html( $row['top_ancho'] ); ?> cm</td>
                                            <?php endif; ?>
                                            <?php if ( 'top' !== $garment_scope ) : ?>
                                                <td class="col-bottom-largo"><?php echo esc_html( $row['bottom_largo'] ); ?> cm</td>
                                                <td class="col-bottom-ancho"><?php echo esc_html( $row['bottom_ancho'] ); ?> cm</td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>

            <!-- ================= INFOGRAFÍA / IMAGEN OFICIAL ================= -->
            <?php if ( ! empty( $image_url ) ) : ?>
                <div class="wooconfig-guide-gender-panel" id="wooconfig-guide-panel-image" style="display: none;">
                    <div class="wooconfig-guide-image-container">
                        <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $config['product_name'] ); ?> - Guía de tallas" class="wooconfig-guide-full-image" />
                    </div>
                </div>
            <?php endif; ?>

            <!-- ================= CÓMO TOMAR LAS MEDIDAS ================= -->
            <div class="wooconfig-how-to-measure-box">
                <div class="wooconfig-how-to-title">
                    <span>📐</span>
                    <strong><?php esc_html_e( '¿Cómo tomar las medidas de tus prendas?', 'woo-config' ); ?></strong>
                </div>
                <div class="wooconfig-how-to-grid">
                    <?php if ( 'bottom' !== $garment_scope ) : ?>
                        <div class="wooconfig-how-to-item">
                            <span class="wooconfig-how-to-icon">👕</span>
                            <div>
                                <strong><?php echo esc_html( $top_label ); ?>:</strong>
                                <ul>
                                    <li><strong><?php esc_html_e( 'Largo:', 'woo-config' ); ?></strong> <?php esc_html_e( 'Desde la costura del hombro (junto al cuello) verticalmente hasta el borde inferior.', 'woo-config' ); ?></li>
                                    <li><strong><?php esc_html_e( 'Ancho:', 'woo-config' ); ?></strong> <?php esc_html_e( 'De axila a axila a la altura del pecho sobre prenda extendida plana.', 'woo-config' ); ?></li>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ( 'top' !== $garment_scope ) : ?>
                        <div class="wooconfig-how-to-item">
                            <span class="wooconfig-how-to-icon">🩳</span>
                            <div>
                                <strong><?php echo esc_html( $bottom_label ); ?>:</strong>
                                <ul>
                                    <li><strong><?php esc_html_e( 'Largo:', 'woo-config' ); ?></strong> <?php esc_html_e( 'Desde la pretina superior de la cintura hasta el dobladillo inferior.', 'woo-config' ); ?></li>
                                    <li><strong><?php esc_html_e( 'Ancho:', 'woo-config' ); ?></strong> <?php esc_html_e( 'Ancho de la cintura/pretina en reposo (de lado a lado sobre plano).', 'woo-config' ); ?></li>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="wooconfig-measure-footnote">
                    💡 <em><?php esc_html_e( 'Recomendación: Mide una prenda deportiva que te quede cómoda extendida sobre una mesa y compara las medidas con esta tabla. Tolerancia estándar: ±1 cm.', 'woo-config' ); ?></em>
                </p>
            </div>

        </div>

        <!-- Footer -->
        <div class="wooconfig-guide-modal-footer">
            <button type="button" class="wooconfig-btn-guide-close-bottom" id="wooconfig-btn-close-guide-action">
                <?php esc_html_e( 'Entendido / Cerrar', 'woo-config' ); ?>
            </button>
        </div>

    </div>
</div>
