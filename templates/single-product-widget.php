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

$deposit_enabled = get_option( 'wooconfig_deposit_enabled', 'yes' );
$deposit_percent = (int) get_option( 'wooconfig_deposit_percent', 50 );
$init_qty        = max( 1, $cart_min_units );
$init_total      = $base_price * $init_qty;
$init_deposit    = round( $init_total * ( $deposit_percent / 100 ) );
?>

<div class="wooconfig-onpage-widget wooconfig-unified-widget" id="wooconfig-onpage-widget" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-base-price="<?php echo esc_attr( $base_price ); ?>" data-min-units="1" data-cart-min-units="<?php echo esc_attr( $cart_min_units ); ?>">
    
    <!-- Top Step 1 Progress Bar Accent -->
    <div class="wooconfig-step1-accent-bar">
        <div class="wooconfig-step1-accent-fill"></div>
    </div>

    <!-- Step 1 Heading -->
    <div class="wooconfig-step1-header">
        <h2 class="wooconfig-step1-title"><?php esc_html_e( '1- Cantidades', 'woo-config' ); ?></h2>
        <p class="wooconfig-step1-subtitle"><?php esc_html_e( 'Indica aquí la cantidad que deseas ordenar', 'woo-config' ); ?></p>
    </div>

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

    <!-- Card 3: Valor total bar -->
    <div class="wooconfig-card-box wooconfig-total-card">
        <div class="wooconfig-total-row">
            <span class="wooconfig-total-main-label"><?php esc_html_e( 'Valor total', 'woo-config' ); ?></span>
            <div class="wooconfig-total-sub-details">
                <span id="wooconfig-total-units-text"><?php echo esc_html( sprintf( _n( '%d unidad', '%d unidades', $init_qty, 'woo-config' ), $init_qty ) ); ?></span>
                <span id="wooconfig-total-ads-text"><?php esc_html_e( '0 adicionales', 'woo-config' ); ?></span>
            </div>
            <span class="wooconfig-total-big-price" id="wooconfig-onpage-total-amount"><?php echo wc_price( $init_total ); ?></span>
        </div>
        
        <?php if ( 'yes' === $deposit_enabled ) : ?>
            <div class="wooconfig-deposit-info-pill">
                <span><?php echo esc_html( sprintf( __( 'Abono Inicial (%d%% hoy):', 'woo-config' ), $deposit_percent ) ); ?> <strong id="wooconfig-onpage-deposit-tag-amount"><?php echo wc_price( $init_deposit ); ?></strong></span>
                <span class="wooconfig-deposit-balance-hint"><?php esc_html_e( 'Saldo al finalizar confección', 'woo-config' ); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- CTA Button to Step 2 -->
    <button type="button" class="wooconfig-btn-green-next" id="wooconfig-btn-start-config">
        <span><?php esc_html_e( 'Siguiente: Diseño', 'woo-config' ); ?></span>
        <svg class="wooconfig-chevron-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
    </button>

</div>
