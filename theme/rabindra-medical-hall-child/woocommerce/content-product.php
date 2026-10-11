<?php
/**
 * Custom WooCommerce Content Product Template for Rabindra Medical Hall
 *
 * Renders an alphabetical medicine table row card on catalog archives,
 * and maintains standard cards for related products / up-sells.
 *
 * @package RabindraMedicalHall
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
    return;
}

$is_archive_view = is_shop() || is_product_taxonomy() || is_product_category() || ( is_search() && 'product' === get_query_var( 'post_type' ) );

if ( $is_archive_view ) :
    $stock_status = $product->get_stock_status();
    $in_stock     = $product->is_in_stock();
    $stock_label  = $in_stock ? __( 'In Stock', 'rabindra-medical-hall' ) : __( 'Out of Stock', 'rabindra-medical-hall' );
    $price_html   = $product->get_price_html();
    $composition  = function_exists( 'rmh_get_product_composition' ) ? rmh_get_product_composition( $product->get_id() ) : '';
    $raw_brand    = get_post_meta( $product->get_id(), '_rmh_manufacturer', true );
    if ( ! $raw_brand ) {
        $raw_brand = get_post_meta( $product->get_id(), '_brand', true );
    }
    $brand = ( $raw_brand && ! in_array( strtoupper( trim( $raw_brand ) ), array( 'BLANK', '-BLANK-', 'GENERAL', 'GENERIC', 'GENRIC', '' ), true ) ) ? ucwords( strtolower( trim( $raw_brand ) ) ) : '';
    ?>
    <li <?php wc_product_class( 'rmh-medicine-row-item', $product ); ?>>
        <a href="<?php echo esc_url( get_permalink() ); ?>" class="rmh-medicine-row-card" title="<?php echo esc_attr( sprintf( __( 'View details for %s', 'rabindra-medical-hall' ), get_the_title() ) ); ?>">
            <div class="rmh-med-col rmh-med-col--name">
                <span class="rmh-med-name"><?php the_title(); ?></span>
                <?php if ( $composition || $brand ) : ?>
                    <span class="rmh-med-meta">
                        <?php if ( $composition ) : ?>
                            <span class="rmh-med-comp" title="<?php echo esc_attr( $composition ); ?>"><?php echo esc_html( $composition ); ?></span>
                        <?php endif; ?>
                        <?php if ( $brand ) : ?>
                            <span class="rmh-med-brand">• <?php echo esc_html( $brand ); ?></span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="rmh-med-col rmh-med-col--status">
                <span class="rmh-stock-badge rmh-stock-badge--<?php echo esc_attr( $stock_status ); ?>">
                    <span class="rmh-stock-dot"></span>
                    <?php echo esc_html( $stock_label ); ?>
                </span>
            </div>

            <div class="rmh-med-col rmh-med-col--price">
                <span class="rmh-med-price"><?php echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            </div>

            <div class="rmh-med-col rmh-med-col--action">
                <span class="rmh-btn-view">
                    <?php esc_html_e( 'View Product', 'rabindra-medical-hall' ); ?>
                    <span class="rmh-btn-arrow" aria-hidden="true">&rarr;</span>
                </span>
            </div>
        </a>
    </li>
    <?php
else :
    // Standard card for related products or upsells.
    ?>
    <li <?php wc_product_class( 'rmh-shop-product-card', $product ); ?>>
        <?php
        /**
         * Hook: woocommerce_before_shop_loop_item.
         */
        do_action( 'woocommerce_before_shop_loop_item' );

        /**
         * Hook: woocommerce_before_shop_loop_item_title.
         */
        do_action( 'woocommerce_before_shop_loop_item_title' );

        /**
         * Hook: woocommerce_shop_loop_item_title.
         */
        do_action( 'woocommerce_shop_loop_item_title' );

        /**
         * Hook: woocommerce_after_shop_loop_item_title.
         */
        do_action( 'woocommerce_after_shop_loop_item_title' );

        /**
         * Hook: woocommerce_after_shop_loop_item.
         */
        do_action( 'woocommerce_after_shop_loop_item' );
        ?>
    </li>
    <?php
endif;
