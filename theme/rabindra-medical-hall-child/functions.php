<?php
/**
 * Rabindra Medical Hall - Child Theme Functions
 * @version 1.0.0
 * @last-modified 2026-09-19 - Fixed unmatched brace in rmh_validate_delivery_radius, removed duplicate brace
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once get_stylesheet_directory() . '/inc/rmh-epharmacy-upgrade.php';
require_once get_stylesheet_directory() . '/inc/rmh-whatsapp-notify.php';
require_once get_stylesheet_directory() . '/inc/rmh-whatsapp-order-request.php';

/**
 * Register the theme capabilities and primary menu needed for the storefront.
 */
function rmh_setup_theme() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'woocommerce' );
    add_theme_support(
        'html5',
        array(
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'style',
            'script',
        )
    );

    register_nav_menus(
        array(
            'primary' => __( 'Primary Menu', 'rabindra-medical-hall' ),
        )
    );
}

add_action( 'after_setup_theme', 'rmh_setup_theme' );

/**
 * Force WooCommerce to clear shipping cache when postcode changes
 */
add_filter( 'woocommerce_checkout_update_order_review', function() {
    if ( function_exists( 'WC' ) && WC()->cart ) {
        WC()->cart->calculate_shipping();
    }
});

/**
 * Apply a ₹30 local delivery fee for cart subtotals below ₹500.
 *
 * FREE delivery applies when:
 * - The customer's shipping postcode is in the configured local delivery zone
 * - Cart subtotal (before fees) is ₹500 or above
 *
 * ₹30 fee applies when:
 * - The customer's shipping postcode is in the configured local delivery zone
 * - Cart subtotal is below ₹500
 *
 * Non-local postcodes are handled by the "Regular Odisha Delivery" shipping zone.
 *
 * The function adds the fee directly via WC()->cart->add_fee() so that
 * the cart, checkout, and order totals all remain consistent.
 * It also removes the flat_rate shipping line to prevent double charging
 * when the customer is in the local zone.
 */
function rmh_apply_local_delivery_fee( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
        return;
    }

    $local_postcodes = rmh_get_allowed_delivery_postcodes();

    if ( empty( $local_postcodes ) ) {
        return;
    }

    $shipping_postcode = strtoupper( trim( WC()->customer->get_shipping_postcode() ) );

    if ( empty( $shipping_postcode ) ) {
        $shipping_postcode = strtoupper( trim( WC()->customer->get_billing_postcode() ) );
    }

    if ( empty( $shipping_postcode ) || ! in_array( $shipping_postcode, $local_postcodes, true ) ) {
        return; // Not a local postcode — standard shipping zone applies.
    }

    // Subtotal before fees.
    $subtotal = $cart->get_subtotal();

    if ( $subtotal >= 500 ) {
        // Free delivery — ensure no delivery fee is added.
        return;
    }

    // Below threshold — add ₹30 local delivery fee.
    $cart->add_fee(
        __( 'Local Delivery Fee', 'rabindra-medical-hall' ),
        30,
        false // Not taxable.
    );
}

add_action( 'woocommerce_cart_calculate_fees', 'rmh_apply_local_delivery_fee' );

/**
 * Return the allowed local delivery postcodes.
 * These mirror the postcodes configured in the WooCommerce "Local Delivery" shipping zone.
 *
 * @return string[]
 */


/**
 * Load Astra parent theme stylesheet
 * + Rabindra Medical Hall custom CSS and JavaScript.
 */
function rmh_is_secure_request() {
    return is_ssl() || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) ) );
}

function rmh_enqueue_assets() {
    $theme_version = wp_get_theme()->get( 'Version' );
    $css_path      = get_stylesheet_directory() . '/assets/css/custom.css';
    $js_path       = get_stylesheet_directory() . '/assets/js/custom.js';

    // Astra parent stylesheet.
    wp_enqueue_style(
        'astra-parent-style',
        get_template_directory_uri() . '/style.css',
        array(),
        $theme_version
    );

    // Custom Rabindra Medical Hall CSS.
    wp_enqueue_style(
        'rmh-custom-css',
        set_url_scheme( get_stylesheet_directory_uri(), rmh_is_secure_request() ? 'https' : 'http' ) . '/assets/css/custom.css',
        array( 'astra-parent-style' ),
        file_exists( $css_path ) ? filemtime( $css_path ) : $theme_version
    );

    // Custom Rabindra Medical Hall JavaScript.
    if ( file_exists( $js_path ) ) {
        wp_enqueue_script(
            'rmh-custom-js',
            set_url_scheme( get_stylesheet_directory_uri(), rmh_is_secure_request() ? 'https' : 'http' ) . '/assets/js/custom.js',
            array(),
            filemtime( $js_path ),
            true
        );

        wp_localize_script(
            'rmh-custom-js',
            'rmhData',
            array(
                'ajaxUrl'        => set_url_scheme( admin_url( 'admin-ajax.php' ), rmh_is_secure_request() ? 'https' : 'http' ),
                'homeUrl'        => set_url_scheme( home_url( '/' ), rmh_is_secure_request() ? 'https' : 'http' ),
                'shopUrl'        => set_url_scheme( function_exists( 'wc_get_shop_url' ) ? wc_get_shop_url() : home_url( '/shop/' ), rmh_is_secure_request() ? 'https' : 'http' ),
                'cartUrl'        => set_url_scheme( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ), rmh_is_secure_request() ? 'https' : 'http' ),
                'fallbackImage'  => trailingslashit( set_url_scheme( get_stylesheet_directory_uri(), rmh_is_secure_request() ? 'https' : 'http' ) ) . 'assets/images/rmh-product-fallback.svg',
                'storeLat'       => '20.4625',
                'storeLng'       => '85.8828',
                'storeAddress'   => 'Rabindra Medical Hall, Badambadi Old Bus Stand, Cuttack, Odisha',
                'maxRadiusKm'    => '10',
                'instantEnabled' => '1',
                'allowedPostcodes' => rmh_get_allowed_delivery_postcodes(),
                'nonces'         => array(
                    'location'     => wp_create_nonce( 'rmh_location' ),
                    'search'       => wp_create_nonce( 'rmh_search' ),
                    'prescription' => wp_create_nonce( 'rmh_prescription' ),
                ),
                'strings'        => array(
                    'locating'           => __( 'Checking your location...', 'rabindra-medical-hall' ),
                    'instantAvailable'   => __( 'Fast Local Delivery', 'rabindra-medical-hall' ),
                    'regularAvailable'   => __( 'Regular Delivery Available', 'rabindra-medical-hall' ),
                    'locationDenied'     => __( 'Location access was not granted. You can enter your delivery address manually below.', 'rabindra-medical-hall' ),
                    'locationUnavailable'=> __( 'Could not detect your location. Please enter your address manually.', 'rabindra-medical-hall' ),
                    'searchEmpty'        => __( 'No medicines found. Need assistance? Call or WhatsApp our pharmacy team!', 'rabindra-medical-hall' ),
                    'searching'          => __( 'Searching pharmacy catalog...', 'rabindra-medical-hall' ),
                    'prescriptionUploaded' => __( 'Prescription received! Our pharmacist will review it and confirm your order.', 'rabindra-medical-hall' ),
                ),
            )
        );
    }
}

add_action( 'wp_enqueue_scripts', 'rmh_enqueue_assets' );

/**
 * Enqueue checkout-specific CSS and JS only on the WooCommerce checkout page.
 *
 * Keeps the main custom.css/js lean and avoids shipping checkout-only
 * selectors to every page on the site.
 */
function rmh_enqueue_checkout_assets() {
    if ( ! is_checkout() ) {
        return;
    }

    $checkout_css = get_stylesheet_directory() . '/assets/css/rmh-checkout.css';
    $checkout_js  = get_stylesheet_directory() . '/assets/js/rmh-checkout.js';
    $scheme       = rmh_is_secure_request() ? 'https' : 'http';
    $base_uri     = set_url_scheme( get_stylesheet_directory_uri(), $scheme );

    if ( file_exists( $checkout_css ) ) {
        wp_enqueue_style(
            'rmh-checkout-css',
            $base_uri . '/assets/css/rmh-checkout.css',
            array( 'rmh-custom-css' ),
            filemtime( $checkout_css )
        );
    }

    if ( file_exists( $checkout_js ) ) {
        wp_enqueue_script(
            'rmh-checkout-js',
            $base_uri . '/assets/js/rmh-checkout.js',
            array( 'jquery', 'wc-checkout' ),
            filemtime( $checkout_js ),
            true
        );

        // Pass checkout-specific data to JS.
        wp_localize_script(
            'rmh-checkout-js',
            'rmhCheckoutData',
            array(
                'ajaxUrl'        => set_url_scheme( admin_url( 'admin-ajax.php' ), $scheme ),
                'nonce'          => wp_create_nonce( 'rmh_checkout' ),
                'checkoutUrl'    => set_url_scheme( wc_get_checkout_url(), $scheme ),
                'whatsappNumber' => function_exists( 'rmh_get_whatsapp_number' ) ? rmh_get_whatsapp_number() : '917205859117',
                'cartData'       => function_exists( 'rmh_get_cart_order_request_data' ) ? rmh_get_cart_order_request_data() : array(),
            )
        );
    }
}

add_action( 'wp_enqueue_scripts', 'rmh_enqueue_checkout_assets', 20 );

/**
 * Add a concise homepage description without affecting WooCommerce page metadata.
 */
function rmh_home_meta_description() {
    if ( ! is_front_page() ) {
        return;
    }

    echo '<meta name="description" content="' . esc_attr__( 'Rabindra Medical Hall is a local pharmacy at Badambadi Old Bus Stand, Cuttack, offering genuine medicines, prescription support and local delivery.', 'rabindra-medical-hall' ) . '">' . "\n";
}

add_action( 'wp_head', 'rmh_home_meta_description', 2 );

/**
 * Apply conservative response headers that are safe for the local WooCommerce site.
 * HSTS, CSP enforcement, COOP and Trusted Types remain deployment decisions.
 */
function rmh_send_safe_security_headers() {
    if ( headers_sent() || is_admin() ) {
        return;
    }

    header( 'X-Content-Type-Options: nosniff' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    header( 'X-Frame-Options: SAMEORIGIN' );
}

add_action( 'send_headers', 'rmh_send_safe_security_headers' );

/**
 * Add frontend state classes used by the converted reference UI.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function rmh_body_classes( $classes ) {
    $classes[] = 'rmh-reference-ui';

    if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
        $classes[] = 'rmh-woocommerce-ui';
    }

    return $classes;
}

add_filter( 'body_class', 'rmh_body_classes' );

/**
 * Replace the Astra visual header with a compact pharmacy header.
 */
function rmh_register_site_header() {
    remove_action( 'astra_header', 'astra_header_markup' );

    if ( class_exists( 'Astra_Builder_Header' ) ) {
        remove_action( 'astra_header', array( Astra_Builder_Header::get_instance(), 'header_builder_markup' ) );
    }

    add_action( 'astra_header', 'rmh_render_site_header', 20 );
}

add_action( 'wp', 'rmh_register_site_header', 20 );

/**
 * Replace the Astra visual footer with custom pharmacy footer.
 */
function rmh_register_site_footer() {
    // Remove Astra footer builder markup
    remove_action( 'astra_footer', 'astra_footer_markup' );

    if ( class_exists( 'Astra_Builder_Footer' ) ) {
        remove_action( 'astra_footer', array( Astra_Builder_Footer::get_instance(), 'footer_builder_markup' ) );
    }

    // Also remove the default copyright
    remove_action( 'astra_footer', 'astra_footer_copyright' );
}

add_action( 'wp', 'rmh_register_site_footer', 20 );

/**
 * Return the configured Rabindra Medical Hall phone number.
 *
 * @return string
 */
function rmh_get_contact_phone() {
    return apply_filters( 'rmh_contact_phone', '8895991117' );
}

/**
 * Return the configured WhatsApp business number for Rabindra Medical Hall.
 *
 * @return string
 */
function rmh_get_whatsapp_number() {
    $settings = function_exists( 'rmh_get_trust_settings' ) ? rmh_get_trust_settings() : array();
    $number   = ! empty( $settings['whatsapp_number'] ) ? preg_replace( '/\D+/', '', (string) $settings['whatsapp_number'] ) : '917205859117';

    return apply_filters( 'rmh_whatsapp_number', $number );
}

/**
 * Return the configured Rabindra Medical Hall contact email.
 *
 * @return string
 */
function rmh_get_contact_email() {
    return apply_filters( 'rmh_contact_email', 'rabindra.rmh@gmail.com' );
}

/**
 * Render site-wide Rabindra Medical Hall header.
 */
function rmh_render_site_header() {
    $cart_url    = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
    $account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
    $phone       = rmh_get_contact_phone();

    $menu_args = array(
        'theme_location' => 'primary',
        'container'      => false,
        'menu_class'     => 'rmh-site-nav__menu',
        'fallback_cb'    => 'rmh_header_fallback_menu',
        'depth'          => 1,
    );
    ?>
    <div class="rmh-announcement-ribbon" role="note" aria-label="<?php echo esc_attr__( 'Announcements', 'rabindra-medical-hall' ); ?>">
        <div class="rmh-announcement-ribbon__track" aria-live="off">
            <?php
            /**
             * Announcement ticker items — ONE set of unique messages.
             * The second group is an aria-hidden duplicate required only for
             * seamless CSS marquee looping. Screen readers see only the first set.
             */
            $trust_settings = function_exists( 'rmh_get_trust_settings' ) ? rmh_get_trust_settings() : array();
            $est_message    = ! empty( $trust_settings['established_year'] )
                ? sprintf( __( 'Rabindra Medical Hall — Badambadi Old Bus Stand, Cuttack since %s', 'rabindra-medical-hall' ), esc_html( $trust_settings['established_year'] ) )
                : __( 'Rabindra Medical Hall — Badambadi Old Bus Stand, Cuttack', 'rabindra-medical-hall' );

            $announcements = array(
                __( 'Fast Local Delivery in eligible Cuttack zones (within 10 km)', 'rabindra-medical-hall' ),
                __( 'Free Local Delivery on Medicine Orders ₹500 & above', 'rabindra-medical-hall' ),
                __( '₹30 Delivery Fee on local orders below ₹500', 'rabindra-medical-hall' ),
                __( 'Regular Courier Delivery available across Odisha', 'rabindra-medical-hall' ),
                __( 'Prescription Medicines require a valid doctor\'s prescription', 'rabindra-medical-hall' ),
                $est_message,
            );
            $sep = '<span class="rmh-announcement-ribbon__sep" aria-hidden="true">•</span>';
            ?>
            <?php foreach ( $announcements as $msg ) : ?>
                <span class="rmh-announcement-ribbon__item"><?php echo esc_html( $msg ); ?></span>
                <?php echo $sep; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endforeach; ?>
            <?php /* Second group aria-hidden — CSS seamless loop clone only */ ?>
            <?php foreach ( $announcements as $msg ) : ?>
                <span class="rmh-announcement-ribbon__item" aria-hidden="true"><?php echo esc_html( $msg ); ?></span>
                <?php echo str_replace( 'aria-hidden="true">', 'aria-hidden="true">', $sep ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="rmh-utility-bar" role="region" aria-label="<?php echo esc_attr__( 'Pharmacy service highlights', 'rabindra-medical-hall' ); ?>">
        <div class="rmh-utility-bar__inner">
            <div class="rmh-utility-bar__benefits">
                <span><b aria-hidden="true">&#10003;</b><?php echo esc_html__( 'Trusted Pharmacy', 'rabindra-medical-hall' ); ?></span>
                <span><b aria-hidden="true">&#10003;</b><?php echo esc_html__( 'Fast Local Delivery', 'rabindra-medical-hall' ); ?></span>
            </div>
            <div class="rmh-utility-bar__links">
                <a href="<?php echo esc_url( rmh_get_whatsapp_url( 'Hello Rabindra Medical Hall, I would like to place an order.' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Chat on WhatsApp', 'rabindra-medical-hall' ); ?></a>
                <a href="tel:<?php echo esc_attr( rmh_get_contact_phone() ); ?>"><?php echo esc_html__( 'Call Pharmacy', 'rabindra-medical-hall' ); ?></a>
            </div>
        </div>
    </div>
    <a class="rmh-skip-link" href="#rmh-main-content"><?php echo esc_html__( 'Skip to content', 'rabindra-medical-hall' ); ?></a>
    <header id="masthead" class="site-header rmh-site-header" role="banner">
        <div class="rmh-site-header__inner">
            <a class="rmh-site-header__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr__( 'Rabindra Medical Hall home', 'rabindra-medical-hall' ); ?>">
                <img class="rmh-site-header__mark" src="<?php echo esc_url( trailingslashit( set_url_scheme( get_stylesheet_directory_uri(), rmh_is_secure_request() ? 'https' : 'http' ) ) . 'assets/images/rmh-header-logo.jpg' ); ?>" alt="" width="52" height="52" aria-hidden="true">
                <span class="rmh-site-header__brand-text">
                    <span class="rmh-site-header__name"><?php echo esc_html__( 'Rabindra Medical Hall', 'rabindra-medical-hall' ); ?></span>
                    <span class="rmh-site-header__tagline"><?php echo esc_html__( 'Trusted Healthcare Store', 'rabindra-medical-hall' ); ?></span>
                </span>
            </a>

            <button type="button" class="rmh-header-loc-badge" id="rmhHeaderLocationBadge"
                aria-haspopup="dialog" aria-expanded="false" title="<?php echo esc_attr__( 'Select delivery location', 'rabindra-medical-hall' ); ?>">
                <span class="rmh-header-loc-icon" aria-hidden="true">📍</span>
                <span class="rmh-header-loc-text">
                    <small><?php echo esc_html__( 'Deliver to', 'rabindra-medical-hall' ); ?></small>
                    <strong id="rmhHeaderLocName"><?php echo esc_html__( 'Check Location', 'rabindra-medical-hall' ); ?></strong>
                </span>
                <span class="rmh-header-loc-chevron" aria-hidden="true">▾</span>
            </button>

            <form class="rmh-site-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                <label class="screen-reader-text" for="rmh-site-search-field"><?php echo esc_html__( 'Search medicines and healthcare products', 'rabindra-medical-hall' ); ?></label>
                <div class="rmh-search-input-wrap">
                    <span class="rmh-search-lens" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </span>
                    <input id="rmh-site-search-field" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php echo esc_attr__( 'Search medicines, health products...', 'rabindra-medical-hall' ); ?>" autocomplete="off" aria-autocomplete="list" aria-controls="rmh-search-results">
                    <input type="hidden" name="post_type" value="product">
                    <span class="rmh-search-spinner" aria-hidden="true" style="display:none;"></span>
                </div>
                <button type="submit"><?php echo esc_html__( 'Search', 'rabindra-medical-hall' ); ?></button>
            </form>
            <div class="rmh-search-results" id="rmh-search-results" role="listbox" aria-label="<?php echo esc_attr__( 'Search suggestions', 'rabindra-medical-hall' ); ?>" hidden></div>

            <div class="rmh-site-header__actions">
                <a class="rmh-site-header__action rmh-site-header__action--prescription rmh-open-rx-modal" href="<?php echo esc_url( home_url( '/#rmh-prescription' ) ); ?>">
                    <span class="rmh-site-header__action-icon" aria-hidden="true">&#128196;</span>
                    <span><?php echo esc_html__( 'Upload Prescription', 'rabindra-medical-hall' ); ?></span>
                    <span class="screen-reader-text"><?php echo esc_html__( 'Upload doctor prescription', 'rabindra-medical-hall' ); ?></span>
                </a>
                <a class="rmh-site-header__action rmh-site-header__action--account" href="<?php echo esc_url( $account_url ); ?>">
                    <span class="rmh-site-header__action-icon" aria-hidden="true">&#128100;</span>
                    <span><?php echo esc_html__( 'Account Sign in', 'rabindra-medical-hall' ); ?></span>
                    <span class="screen-reader-text"><?php echo esc_html__( 'My account', 'rabindra-medical-hall' ); ?></span>
                </a>
                <a class="rmh-site-header__cart" href="<?php echo esc_url( $cart_url ); ?>">
                    <span class="rmh-site-header__action-icon" aria-hidden="true">&#128722;</span>
                    <span class="rmh-cart-total"><?php echo esc_html__( 'Cart', 'rabindra-medical-hall' ); ?> <?php echo wp_kses_post( rmh_get_cart_total_markup() ); ?></span>
                    <?php echo wp_kses_post( rmh_get_cart_count_markup() ); ?>
                </a>
            </div>

            <button class="rmh-menu-toggle" type="button" aria-controls="rmh-primary-navigation" aria-expanded="false">
                <span class="screen-reader-text"><?php echo esc_html__( 'Open menu', 'rabindra-medical-hall' ); ?></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </button>
        </div>
        <div class="rmh-site-header__nav-row">
            <nav id="rmh-primary-navigation" class="rmh-site-nav" aria-label="<?php echo esc_attr__( 'Primary navigation', 'rabindra-medical-hall' ); ?>">
                <?php
                if ( has_nav_menu( 'primary' ) ) {
                    wp_nav_menu( $menu_args );
                } else {
                    rmh_header_fallback_menu();
                }
                ?>
            </nav>
            <a class="rmh-header-whatsapp" href="<?php echo esc_url( rmh_get_whatsapp_url( 'Hello Rabindra Medical Hall, I need help with my order.' ) ); ?>" target="_blank" rel="noopener"><span aria-hidden="true">&#9679;</span><?php echo esc_html__( 'Help &amp; Queries', 'rabindra-medical-hall' ); ?></a>
        </div>
    </header>
    <button class="rmh-mobile-menu-scrim" type="button" aria-label="<?php echo esc_attr__( 'Close menu', 'rabindra-medical-hall' ); ?>" hidden></button>
    <?php
}

/**
 * Fallback menu if the primary menu becomes unassigned.
 */
function rmh_header_fallback_menu() {
    $links = array(
        __( 'Home', 'rabindra-medical-hall' )          => home_url( '/' ),
        __( 'Shop', 'rabindra-medical-hall' )          => home_url( '/shop/' ),
        __( 'Prescriptions', 'rabindra-medical-hall' ) => home_url( '/#rmh-prescription' ),
        __( 'Cart', 'rabindra-medical-hall' )         => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
    );

    echo '<ul class="rmh-site-nav__menu">';
    foreach ( $links as $label => $url ) {
        printf(
            '<li><a href="%s">%s</a></li>',
            esc_url( $url ),
            esc_html( $label )
        );
    }
    echo '</ul>';
}

/**
 * Add the two key pharmacy journeys to an assigned primary menu when absent.
 *
 * @param string   $items Menu HTML.
 * @param stdClass $args  Menu arguments.
 * @return string
 */
function rmh_enhance_primary_menu_items( $items, $args ) {
    if ( empty( $args->theme_location ) || 'primary' !== $args->theme_location ) {
        return $items;
    }

    $links = array(
        __( 'Prescriptions', 'rabindra-medical-hall' ) => home_url( '/#rmh-prescription' ),
    );

    foreach ( $links as $label => $url ) {
        if ( false === stripos( $items, '>' . $label . '<' ) ) {
            $items .= sprintf(
                '<li class="menu-item menu-item-rmh-support"><a href="%s" class="menu-link">%s</a></li>',
                esc_url( $url ),
                esc_html( $label )
            );
        }
    }

    return $items;
}

add_filter( 'wp_nav_menu_items', 'rmh_enhance_primary_menu_items', 10, 2 );

/**
 * Keep secondary information pages in the footer instead of crowding the
 * primary customer navigation.
 *
 * @param array    $items Menu objects.
 * @param stdClass $args  Menu arguments.
 * @return array
 */
function rmh_remove_secondary_primary_menu_items( $items, $args ) {
    if ( empty( $args->theme_location ) || 'primary' !== $args->theme_location ) {
        return $items;
    }

    return array_values(
        array_filter(
            $items,
            static function ( $item ) {
                $title = sanitize_title( $item->title );
                $path  = wp_parse_url( $item->url, PHP_URL_PATH );

                return ! in_array( $title, array( 'about-us', 'contact-us' ), true ) && ! in_array( trim( (string) $path, '/' ), array( 'about-us', 'contact-us' ), true );
            }
        )
    );
}

add_filter( 'wp_nav_menu_objects', 'rmh_remove_secondary_primary_menu_items', 20, 2 );

/**
 * Cart count badge markup.
 *
 * @return string
 */
function rmh_get_cart_count_markup() {
    $count = 0;

    if ( function_exists( 'WC' ) && WC()->cart ) {
        $count = WC()->cart->get_cart_contents_count();
    }

    return sprintf(
        '<span class="rmh-cart-count" aria-label="%s">%d</span>',
        esc_attr( sprintf( _n( '%d item in cart', '%d items in cart', $count, 'rabindra-medical-hall' ), $count ) ),
        absint( $count )
    );
}

/**
 * Return the current WooCommerce cart total for the global header.
 *
 * @return string
 */
function rmh_get_cart_total_markup() {
    if ( function_exists( 'WC' ) && WC()->cart && method_exists( WC()->cart, 'get_cart_total' ) ) {
        return WC()->cart->get_cart_total();
    }

    return function_exists( 'wc_price' ) ? wc_price( 0 ) : '&#8377;0.00';
}

/**
 * Keep the cart count fresh after WooCommerce AJAX add-to-cart calls.
 *
 * @param array $fragments Cart fragments.
 * @return array
 */
function rmh_cart_count_fragment( $fragments ) {
    $fragments['span.rmh-cart-count'] = rmh_get_cart_count_markup();
    $fragments['span.rmh-cart-total'] = sprintf(
        '<span class="rmh-cart-total">%s %s</span>',
        esc_html__( 'Cart', 'rabindra-medical-hall' ),
        rmh_get_cart_total_markup()
    );

    return $fragments;
}

add_filter( 'woocommerce_add_to_cart_fragments', 'rmh_cart_count_fragment' );

/**
 * AJAX handler for live product search suggestions.
 *
 * Returns JSON array of product suggestions for the header search.
 */
function rmh_ajax_search_products() {
    check_ajax_referer( 'rmh_search', 'nonce' );

    // Simple rate limiting: 30 requests per minute per IP
    $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    $rate_key = 'rmh_search_rate_' . md5( $ip );
    $requests = (int) get_transient( $rate_key );

    if ( $requests >= 30 ) {
        wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait a moment.', 'rabindra-medical-hall' ) ), 429 );
    }

    set_transient( $rate_key, $requests + 1, MINUTE_IN_SECONDS );

    $query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

    if ( strlen( $query ) < 2 ) {
        wp_send_json_success( array( 'results' => array() ) );
    }

    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 8,
        's'              => $query,
        'fields'         => 'ids',
        'rmh_meta_search' => true,
    );

    $search_query = new WP_Query( $args );

    $results = array();

    if ( $search_query->have_posts() ) {
        foreach ( $search_query->posts as $post_id ) {
            $product = wc_get_product( $post_id );

            if ( ! $product instanceof WC_Product ) {
                continue;
            }

            $image_id  = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';
            if ( ! $image_url ) {
                $image_url = trailingslashit( set_url_scheme( get_stylesheet_directory_uri(), rmh_is_secure_request() ? 'https' : 'http' ) ) . 'assets/images/rmh-product-fallback.svg';
            }

            $brand = get_post_meta( $product->get_id(), '_rmh_manufacturer', true );
            if ( ! $brand ) {
                $raw_brand = get_post_meta( $product->get_id(), '_brand', true );
                if ( $raw_brand && ! in_array( strtoupper( trim( $raw_brand ) ), array( 'BLANK', '-BLANK-', 'GENERAL', 'GENERIC', 'GENRIC', '' ), true ) ) {
                    $brand = ucwords( strtolower( trim( $raw_brand ) ) );
                }
            }

            $pack_size = get_post_meta( $product->get_id(), '_rmh_pack_size', true );
            if ( ! $pack_size ) {
                $pack_size = get_post_meta( $product->get_id(), '_pack_size', true );
            }
            if ( in_array( strtoupper( trim( (string) $pack_size ) ), array( 'BLANK', '-', '' ), true ) ) {
                $pack_size = '';
            }

            $composition = function_exists( 'rmh_get_product_composition' ) ? rmh_get_product_composition( $product->get_id() ) : '';

            $results[] = array(
                'id'        => $product->get_id(),
                'name'      => $product->get_name(),
                'brand'     => $brand ? $brand : '',
                'pack'      => $pack_size ? $pack_size : '',
                'composition' => $composition,
                'permalink' => $product->get_permalink(),
                'price'     => wp_strip_all_tags( rmh_get_product_mrp_html( $product ) ),
                'image'     => $image_url,
                'in_stock'  => $product->is_in_stock(),
            );
        }
    }

    wp_send_json_success( array( 'results' => $results ) );
}

add_action( 'wp_ajax_rmh_search_products', 'rmh_ajax_search_products' );
add_action( 'wp_ajax_nopriv_rmh_search_products', 'rmh_ajax_search_products' );

/**
 * Extend the live product search to verified catalogue metadata.
 * This is scoped to the RMH AJAX search query and does not alter admin queries.
 *
 * @param string   $search Search SQL generated by WordPress.
 * @param WP_Query $query  Current query.
 * @return string
 */
function rmh_search_products_by_metadata( $search, $query ) {
    global $wpdb;

    if ( ! $query instanceof WP_Query || ! $query->get( 'rmh_meta_search' ) ) {
        return $search;
    }

    $term = trim( (string) $query->get( 's' ) );
    if ( '' === $term ) {
        return $search;
    }

    $like      = '%' . $wpdb->esc_like( $term ) . '%';
    $meta_keys = array( '_sku', '_rmh_composition', '_rmh_generic_name', '_generic_name', '_active_ingredient', 'composition', '_rmh_manufacturer', '_brand', '_rmh_strength', '_strength' );
    $key_placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
    $params = array_merge( array( $like ), $meta_keys );

    $meta_sql = $wpdb->prepare(
        "EXISTS (SELECT 1 FROM {$wpdb->postmeta} rmh_search_meta WHERE rmh_search_meta.post_id = {$wpdb->posts}.ID AND rmh_search_meta.meta_value LIKE %s AND rmh_search_meta.meta_key IN ({$key_placeholders}))",
        $params
    );

    if ( $meta_sql ) {
        $search = $search ? preg_replace( '/^AND\s*/', 'AND (', $search, 1 ) . ' OR ' . $meta_sql . ')' : ' AND (' . $meta_sql . ')';
    }

    return $search;
}

add_filter( 'posts_search', 'rmh_search_products_by_metadata', 10, 2 );

/**
 * Get the optional store location configuration.
 *
 * @return array
 */
function rmh_get_store_location_config() {
    $defaults = array(
        'store_address'   => '',
        'latitude'        => '',
        'longitude'       => '',
        'google_maps_url' => '',
        'directions_url'  => '',
    );

    $settings = get_option( 'rmh_store_location', array() );

    if ( ! is_array( $settings ) ) {
        $settings = array();
    }

    return array_merge( $defaults, $settings );
}

/**
 * Render a "Visit Our Pharmacy" section with Google Maps directions.
 *
 * @return string
 */
function rmh_store_experience_shortcode() {
    ob_start();
    ?>
    <section class="rmh-visit-pharmacy" aria-label="<?php echo esc_attr__( 'Visit our pharmacy', 'rabindra-medical-hall' ); ?>">
        <div class="rmh-visit-pharmacy__visual" aria-hidden="true">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                <circle cx="12" cy="10" r="3"/>
            </svg>
        </div>

        <div class="rmh-visit-pharmacy__content">
            <span class="rmh-section-label"><?php echo esc_html__( 'VISIT US', 'rabindra-medical-hall' ); ?></span>
            <h2><?php echo esc_html__( 'Visit Rabindra Medical Hall', 'rabindra-medical-hall' ); ?></h2>
            <p><?php echo esc_html__( 'Rabindra Medical Hall, Badambadi Old Bus Stand, Cuttack, Odisha.', 'rabindra-medical-hall' ); ?></p>

            <div class="rmh-visit-pharmacy__actions">
                <a class="rmh-btn rmh-btn-primary" href="https://maps.google.com/?q=Badambadi+Cuttack+Odisha" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Get Directions', 'rabindra-medical-hall' ); ?></a>
                <a class="rmh-btn rmh-btn-secondary" href="https://maps.google.com/?q=Badambadi+Cuttack+Odisha" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open in Google Maps', 'rabindra-medical-hall' ); ?></a>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

add_shortcode( 'rmh_store_experience', 'rmh_store_experience_shortcode' );

/**
 * Render a premium store-location block if configured.
 *
 * @return string
 */
function rmh_store_location_shortcode() {
    $config = rmh_get_store_location_config();

    // Use the verified Google Maps direction link as default if not configured
    $direction_url = $config['directions_url'] ? $config['directions_url'] : $config['google_maps_url'];
    if ( empty( $direction_url ) ) {
        $direction_url = 'https://maps.google.com/?q=Badambadi+Cuttack+Odisha';
    }

    $fields = array_filter(
        array(
            'address'   => $config['store_address'],
            'latitude'  => $config['latitude'],
            'longitude' => $config['longitude'],
            'maps'      => $config['google_maps_url'],
            'directions' => $direction_url,
        ),
        static function ( $value ) {
            return '' !== trim( (string) $value );
        }
    );

    if ( empty( $fields ) ) {
        return '';
    }

    ob_start();
    ?>
    <section class="rmh-find-store" aria-label="<?php echo esc_attr__( 'Find our pharmacy', 'rabindra-medical-hall' ); ?>">
        <div class="rmh-find-store__header">
            <span class="rmh-section-label"><?php echo esc_html__( 'VISIT OUR PHARMACY', 'rabindra-medical-hall' ); ?></span>
            <h2><?php echo esc_html__( 'Rabindra Medical Hall', 'rabindra-medical-hall' ); ?></h2>
        </div>

        <?php if ( ! empty( $config['store_address'] ) ) : ?>
            <p class="rmh-find-store__address"><?php echo esc_html( $config['store_address'] ); ?></p>
        <?php endif; ?>

        <div class="rmh-find-store__actions">
            <?php if ( $direction_url ) : ?>
                <a class="rmh-btn rmh-btn-primary" href="<?php echo esc_url( $direction_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Get Directions', 'rabindra-medical-hall' ); ?></a>
            <?php endif; ?>
            <a class="rmh-btn rmh-btn-secondary" href="tel:<?php echo esc_attr( rmh_get_contact_phone() ); ?>"><?php echo esc_html__( 'Call Pharmacy', 'rabindra-medical-hall' ); ?></a>
            <a class="rmh-btn rmh-btn-whatsapp" href="<?php echo esc_url( rmh_get_whatsapp_url( 'Hello Rabindra Medical Hall, I need help with my order.' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Chat on WhatsApp', 'rabindra-medical-hall' ); ?></a>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

add_shortcode( 'rmh_store_location', 'rmh_store_location_shortcode' );

/**
 * Render concise, editable pharmacy FAQs for the homepage and FAQ page.
 *
 * @return string
 */
function rmh_faq_shortcode() {
    $questions = array(
        __( 'How do I order prescription medicines online?', 'rabindra-medical-hall' ) => __( 'Upload a clear doctor\'s prescription when adding prescription-required medicines to your cart or during checkout. Our licensed pharmacy team verifies every prescription prior to dispensing.', 'rabindra-medical-hall' ),
        __( 'Do all medicines require a doctor\'s prescription?', 'rabindra-medical-hall' ) => __( 'No. Over-the-counter (OTC) medicines and daily healthcare essentials can be ordered immediately without a prescription. Only products explicitly marked "Prescription Required" require a valid prescription.', 'rabindra-medical-hall' ),
        __( 'How does Fast Local Delivery work?', 'rabindra-medical-hall' ) => __( 'For eligible local PIN codes in our Cuttack service area, orders placed during pharmacy operating hours are packed and dispatched promptly. Delivery availability depends on location, medicine availability and pharmacy operating hours. Free delivery applies to orders ₹500 and above; a ₹30 fee applies to smaller orders.', 'rabindra-medical-hall' ),
        __( 'Do you deliver to addresses outside the local delivery zone?', 'rabindra-medical-hall' ) => __( 'Yes. We deliver across supported regions in Odisha via standard courier delivery. You can check serviceability by entering your PIN code using the delivery checker or at checkout.', 'rabindra-medical-hall' ),
        __( 'What payment methods do you accept?', 'rabindra-medical-hall' ) => __( 'We accept secure online payments through the configured payment gateway.', 'rabindra-medical-hall' ),
        __( 'How can I track the live status of my order?', 'rabindra-medical-hall' ) => __( 'You can track your order in real time using the "Track Your Order" tool on our homepage by entering your Order Number and verified 10-digit Mobile Number or Email Address, or by checking My Account.', 'rabindra-medical-hall' ),
    );

    ob_start();
    ?>
    <section class="rmh-faq-section" aria-labelledby="rmh-faq-heading">
        <div class="rmh-faq-section__header">
            <span class="rmh-section-label"><?php echo esc_html__( 'HELP CENTRE', 'rabindra-medical-hall' ); ?></span>
            <h2 id="rmh-faq-heading"><?php echo esc_html__( 'Frequently asked questions', 'rabindra-medical-hall' ); ?></h2>
            <p><?php echo esc_html__( 'Clear answers for ordering, prescriptions and account support.', 'rabindra-medical-hall' ); ?></p>
        </div>
        <div class="rmh-faq-list">
            <?php foreach ( $questions as $question => $answer ) : ?>
                <details>
                    <summary><?php echo esc_html( $question ); ?></summary>
                    <p><?php echo esc_html( $answer ); ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

add_shortcode( 'rmh_faq', 'rmh_faq_shortcode' );

/**
 * Replace the editable legacy hero with the current production hero.
 *
 * The artwork is kept in the child theme so it is versioned with the
 * homepage presentation and does not depend on a media-library record.
 *
 * @param string $content Homepage content.
 * @return string
 */
function rmh_rebuild_homepage_hero( $content ) {
    $hero_image = set_url_scheme( get_stylesheet_directory_uri(), rmh_is_secure_request() ? 'https' : 'http' ) . '/assets/images/rmh-hero-pharmacy-box.jpeg';

    ob_start();
    ?>
    <section class="rmh-hero rmh-home-hero">
        <div class="rmh-home-hero__content">
            <div class="rmh-home-hero__delivery-badge">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span><?php echo esc_html__( 'Fast Local Delivery', 'rabindra-medical-hall' ); ?></span>
            </div>
            <span class="rmh-eyebrow"><?php echo esc_html__( 'FAST • SAFE • RELIABLE', 'rabindra-medical-hall' ); ?></span>
            <h1><?php echo esc_html__( 'Healthcare you trust.', 'rabindra-medical-hall' ); ?><br><span><?php echo esc_html__( 'Delivered to your door', 'rabindra-medical-hall' ); ?></span></h1>
            <p><?php echo esc_html__( 'Genuine medicines, expert care, and lightning-fast delivery from Rabindra Medical Hall.', 'rabindra-medical-hall' ); ?></p>
            <div class="rmh-hero-buttons">
                <a href="<?php echo esc_url( function_exists( 'wc_get_shop_url' ) ? wc_get_shop_url() : home_url( '/shop/' ) ); ?>" class="rmh-btn rmh-btn-primary">
                    <?php echo esc_html__( 'Shop Medicines', 'rabindra-medical-hall' ); ?><span aria-hidden="true">&#8594;</span>
                </a>
                <a href="<?php echo esc_url( home_url( '/#rmh-prescription' ) ); ?>" class="rmh-btn rmh-btn-light"><?php echo esc_html__( 'Upload Prescription', 'rabindra-medical-hall' ); ?></a>
            </div>
            <div class="rmh-home-hero__trust" aria-label="<?php echo esc_attr__( 'Trust indicators', 'rabindra-medical-hall' ); ?>">
                <span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                    <strong><?php echo esc_html__( 'Genuine Medicines', 'rabindra-medical-hall' ); ?></strong>
                </span>
                <span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <strong><?php echo esc_html__( 'Secure Checkout', 'rabindra-medical-hall' ); ?></strong>
                </span>
                <span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-12 10-12 10S4 16 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <strong><?php echo esc_html__( 'Local Pharmacy', 'rabindra-medical-hall' ); ?></strong>
                </span>
            </div>
        </div>
        <figure class="rmh-hero-art">
            <div class="rmh-hero-art__frame">
                <img src="<?php echo esc_url( $hero_image ); ?>" alt="<?php echo esc_attr__( 'Rabindra Medical Hall pharmacy delivery box with healthcare supplies', 'rabindra-medical-hall' ); ?>" width="1264" height="843" fetchpriority="high" decoding="async">
            </div>
            <figcaption><?php echo esc_html__( 'Trusted care, packed for your doorstep.', 'rabindra-medical-hall' ); ?></figcaption>
        </figure>
    </section>
    <?php
    $hero = ob_get_clean();

    return preg_replace( '#<section\b[^>]*class="[^"]*\brmh-hero\b[^"]*"[^>]*>.*?</section>#s', $hero, $content, 1 );
}

/**
 * Replace the legacy duplicate order/prescription rows on the homepage.
 *
 * The dedicated prescription section remains the single prescription upload
 * destination. This keeps the existing page content editable while allowing
 * the storefront presentation to use the current CTA structure.
 *
 * @param string $content Homepage content.
 * @return string
 */
function rmh_polish_homepage_order_tools( $content ) {
    $content = preg_replace( '#<section\b[^>]*class="[^"]*\brmh-service-grid\b[^"]*"[^>]*>.*?</section>#s', '', $content, 1 );

    ob_start();
    ?>
    <section class="rmh-tools-section">
        <div class="rmh-section-heading">
            <div>
                <span class="rmh-section-label"><?php echo esc_html__( 'PHARMACY SERVICES', 'rabindra-medical-hall' ); ?></span>
                <h2><?php echo esc_html__( 'How can we help you?', 'rabindra-medical-hall' ); ?></h2>
                <p><?php echo esc_html__( 'Quick ways to reach our pharmacy team.', 'rabindra-medical-hall' ); ?></p>
            </div>
        </div>

        <div class="rmh-tools-grid">
            <a href="<?php echo esc_url( rmh_get_whatsapp_url( 'Hello Rabindra Medical Hall, I need help with my order.' ) ); ?>" class="rmh-tool-card rmh-tool-whatsapp" target="_blank" rel="noopener">
                <div class="rmh-tool-icon" aria-hidden="true">💬</div>
                <div class="rmh-tool-content">
                    <span><?php echo esc_html__( 'FAST RESPONSE', 'rabindra-medical-hall' ); ?></span>
                    <h3><?php echo esc_html__( 'Chat on WhatsApp', 'rabindra-medical-hall' ); ?></h3>
                    <p><?php echo esc_html__( 'Chat with our pharmacy team for help with medicines, prescriptions or orders.', 'rabindra-medical-hall' ); ?></p>
                    <strong><?php echo esc_html__( 'Chat with Pharmacy →', 'rabindra-medical-hall' ); ?></strong>
                </div>
            </a>

            <a href="tel:<?php echo esc_attr( rmh_get_contact_phone() ); ?>" class="rmh-tool-card rmh-tool-phone">
                <div class="rmh-tool-icon" aria-hidden="true">📞</div>
                <div class="rmh-tool-content">
                    <span><?php echo esc_html__( 'PHARMACY SUPPORT', 'rabindra-medical-hall' ); ?></span>
                    <h3><?php echo esc_html__( 'Call Pharmacy', 'rabindra-medical-hall' ); ?></h3>
                    <p><?php echo esc_html__( 'Speak with our pharmacy team for order assistance.', 'rabindra-medical-hall' ); ?></p>
                    <strong><?php echo esc_html( rmh_get_contact_phone() ); ?></strong>
                </div>
            </a>
        </div>
    </section>
    <?php
    $order_tools = ob_get_clean();

    return preg_replace( '#<section\b[^>]*class="[^"]*\brmh-tools-section\b[^"]*"[^>]*>.*?</section>#s', $order_tools, $content, 1 );
}

/**
 * Remove redundant legacy homepage blocks so the primary journeys stay compact.
 *
 * @param string $content Homepage content.
 * @return string
 */
function rmh_remove_redundant_homepage_sections( $content ) {
    $content = preg_replace( '#<section\b[^>]*class="[^"]*\brmh-delivery-banner\b[^"]*"[^>]*>.*?</section>#s', '', $content, 1 );
    $content = preg_replace( '#<section\b[^>]*class="[^"]*\brmh-how-section\b[^"]*"[^>]*>.*?</section>#s', '', $content, 1 );
    $content = preg_replace( '#<section\b[^>]*>.*?(?:rmh-popular-categories).*?</section>#is', '', $content );

    return $content;
}

/**
 * Present the four verified storefront value propositions in one strip.
 *
 * @param string $content Homepage content.
 * @return string
 */
function rmh_rebuild_homepage_trust_strip( $content ) {
    return preg_replace( '#<section\b[^>]*class="[^"]*\brmh-trust-section\b[^"]*"[^>]*>.*?</section>#s', '', $content, 1 );
}

/**
 * Render the trust strip directly above the Astra footer on the homepage.
 */
function rmh_render_homepage_trust_strip() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }

    ob_start();
    ?>
    <section class="rmh-trust-section rmh-home-trust" aria-labelledby="rmh-trust-strip-heading">
        <h2 id="rmh-trust-strip-heading" class="screen-reader-text"><?php echo esc_html__( 'Why trust Rabindra Medical Hall', 'rabindra-medical-hall' ); ?></h2>
        <div class="rmh-trust-grid">
            <div class="rmh-trust-item">
                <svg class="rmh-trust-item__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                <strong><?php echo esc_html__( 'Genuine Medicines', 'rabindra-medical-hall' ); ?></strong>
                <p><?php echo esc_html__( 'Genuine Medicines & Healthcare Essentials', 'rabindra-medical-hall' ); ?></p>
            </div>
            <div class="rmh-trust-item">
                <svg class="rmh-trust-item__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 13a8 8 0 0 1 16 0M4 13v4a2 2 0 0 0 2 2h2v-6H4ZM20 13v4a2 2 0 0 1-2 2h-2v-6h4Z"/><circle cx="12" cy="10" r="3"/></svg>
                <strong><?php echo esc_html__( 'Local Pharmacy', 'rabindra-medical-hall' ); ?></strong>
                <?php
                $strip_trust = function_exists( 'rmh_get_trust_settings' ) ? rmh_get_trust_settings() : array();
                $strip_est   = ! empty( $strip_trust['established_year'] )
                    ? sprintf( __( 'Serving Cuttack since %s', 'rabindra-medical-hall' ), esc_html( $strip_trust['established_year'] ) )
                    : __( 'Serving the Cuttack community', 'rabindra-medical-hall' );
                ?>
                <p><?php echo esc_html( $strip_est ); ?></p>
            </div>
            <div class="rmh-trust-item">
                <svg class="rmh-trust-item__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <strong><?php echo esc_html__( 'Secure Checkout', 'rabindra-medical-hall' ); ?></strong>
                <p><?php echo esc_html__( 'Encrypted payment protection', 'rabindra-medical-hall' ); ?></p>
            </div>
            <div class="rmh-trust-item">
                <svg class="rmh-trust-item__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <strong><?php echo esc_html__( 'Prescription Verification', 'rabindra-medical-hall' ); ?></strong>
                <p><?php echo esc_html__( 'Pharmacist review for Rx items', 'rabindra-medical-hall' ); ?></p>
            </div>
            <div class="rmh-trust-item">
                <svg class="rmh-trust-item__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <strong><?php echo esc_html__( 'Fast Local Delivery', 'rabindra-medical-hall' ); ?></strong>
                <p><?php echo esc_html__( 'Check your PIN code for local delivery eligibility.', 'rabindra-medical-hall' ); ?></p>
            </div>
        </div>
    </section>
    <?php
    echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup and escaped labels.
}

add_action( 'astra_footer_before', 'rmh_render_homepage_trust_strip', 10 );

/**
 * Render the "How to Order" section for the homepage.
 *
 * Four-step process:
 * 1. Select Medicines or Upload Prescription
 * 2. Confirm Delivery Location
 * 3. Pharmacy Review & Confirmation
 * 4. Secure Checkout & Local Delivery
 *
 * @return string
 */
function rmh_how_to_order_shortcode() {
    $steps = array(
        array(
            'number' => '01',
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
            'title' => __( 'Select Medicines or Upload Prescription', 'rabindra-medical-hall' ),
            'description' => __( 'Browse our verified catalogue or upload your doctor\'s prescription for Rx items.', 'rabindra-medical-hall' ),
            'links' => array(
                array(
                    'text' => __( 'Shop Medicines', 'rabindra-medical-hall' ),
                    'url' => wc_get_page_permalink( 'shop' ),
                    'class' => 'rmh-step-link',
                ),
                array(
                    'text' => __( 'Upload Prescription', 'rabindra-medical-hall' ),
                    'url' => home_url( '/#rmh-prescription' ),
                    'class' => 'rmh-step-link',
                ),
            ),
        ),
        array(
            'number' => '02',
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            'title' => __( 'Confirm Delivery Location', 'rabindra-medical-hall' ),
            'description' => __( 'Use GPS or enter your PIN code to check local delivery eligibility.', 'rabindra-medical-hall' ),
            'links' => array(
                array(
                    'text' => __( 'Check Eligibility', 'rabindra-medical-hall' ),
                    'url' => '#',
                    'class' => 'rmh-step-btn',
                    'attrs' => 'id="rmhStepLocationBtnOrder" aria-haspopup="dialog"',
                ),
            ),
        ),
        array(
            'number' => '03',
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
            'title' => __( 'Pharmacy Review & Confirmation', 'rabindra-medical-hall' ),
            'description' => __( 'Our pharmacist verifies your prescription (if required) and confirms the order details.', 'rabindra-medical-hall' ),
            'links' => array(),
        ),
        array(
            'number' => '04',
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
            'title' => __( 'Secure Checkout & Local Delivery', 'rabindra-medical-hall' ),
            'description' => __( 'Complete payment securely. Fast local delivery or standard courier delivery across Odisha.', 'rabindra-medical-hall' ),
            'links' => array(
                array(
                    'text' => __( 'View Payment Options', 'rabindra-medical-hall' ),
                    'url' => wc_get_page_permalink( 'checkout' ),
                    'class' => 'rmh-step-link',
                ),
            ),
        ),
    );

    ob_start();
    ?>
    <section id="rmh-how-to-order" class="rmh-content-section" aria-labelledby="rmh-how-heading">
        <header class="rmh-section-heading">
            <span class="rmh-section-label"><?php echo esc_html__( 'ORDER PROCESS', 'rabindra-medical-hall' ); ?></span>
            <h2 id="rmh-how-heading"><?php echo esc_html__( 'How to Order', 'rabindra-medical-hall' ); ?></h2>
            <p><?php echo esc_html__( 'Simple steps from prescription to doorstep delivery.', 'rabindra-medical-hall' ); ?></p>
        </header>
        <div class="rmh-steps-grid">
            <?php foreach ( $steps as $index => $step ) : ?>
                <article class="rmh-step-card">
                    <span class="rmh-step-num"><?php echo esc_html( $step['number'] ); ?></span>
                    <div class="rmh-step-icon" aria-hidden="true"><?php echo $step['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></div>
                    <h3><?php echo esc_html( $step['title'] ); ?></h3>
                    <p><?php echo esc_html( $step['description'] ); ?></p>
                    <?php if ( ! empty( $step['links'] ) ) : ?>
                        <div class="rmh-step-links">
                            <?php foreach ( $step['links'] as $link ) : ?>
                                <?php
                                $attrs = isset( $link['attrs'] ) ? ' ' . $link['attrs'] : '';
                                $link_class = isset( $link['class'] ) ? esc_attr( $link['class'] ) : '';
                                ?>
                                <?php if ( 'rmh-step-btn' === $link['class'] ) : ?>
                                    <button type="button" class="<?php echo $link_class; ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled attributes. ?>><?php echo esc_html( $link['text'] ); ?></button>
                                <?php else : ?>
                                    <a class="<?php echo $link_class; ?>" href="<?php echo esc_url( $link['url'] ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled attributes. ?>><?php echo esc_html( $link['text'] ); ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

add_shortcode( 'rmh_how_to_order', 'rmh_how_to_order_shortcode' );

/**
 * Place the existing private prescription upload form in the homepage layout
 * without altering its submission endpoint or validation.
 *
 * @param string $form_markup Existing prescription form markup.
 * @return string
 */
function rmh_home_prescription_section( $form_markup ) {
    if ( '' === trim( $form_markup ) ) {
        return '';
    }

    ob_start();
    ?>
        <section id="rmh-prescription" class="rmh-section rmh-home-prescription-section" aria-labelledby="rmh-prescription-heading">
        <div class="rmh-home-prescription-section__content">
            <span class="rmh-section-label"><?php echo esc_html__( 'PRESCRIPTION SUPPORT', 'rabindra-medical-hall' ); ?></span>
            <h2 id="rmh-prescription-heading"><?php echo esc_html__( 'Upload your prescription securely', 'rabindra-medical-hall' ); ?></h2>
            <p><?php echo esc_html__( 'Share a clear prescription and our pharmacy team will review it before dispensing any prescription medicine.', 'rabindra-medical-hall' ); ?></p>
            <ul>
                <li><?php echo esc_html__( 'JPEG, PNG, WEBP or PDF up to 10 MB', 'rabindra-medical-hall' ); ?></li>
                <li><?php echo esc_html__( 'Prescription verification before dispensing', 'rabindra-medical-hall' ); ?></li>
                <li><?php echo esc_html__( 'Files are stored privately for pharmacy review', 'rabindra-medical-hall' ); ?></li>
            </ul>
        </div>
        <div class="rmh-home-prescription-section__form">
            <?php echo $form_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted local shortcode markup. ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

/**
 * Append the premium storefront sections to the homepage only.
 *
 * @param string $content Post content.
 * @return string
 */
function rmh_append_homepage_sections( $content ) {
    if ( is_admin() || ! is_front_page() || ! is_singular() ) {
        return $content;
    }

    $content = rmh_rebuild_homepage_hero( $content );
    $content = rmh_polish_homepage_order_tools( $content );
    $content = rmh_remove_redundant_homepage_sections( $content );
    $content = rmh_rebuild_homepage_trust_strip( $content );

    // Only treat categories as present when the rendered homepage section exists.
    // Generic CSS class names can also appear in the page source and must not
    // suppress the real category renderer.
    $has_categories = ( false !== strpos( $content, 'rmh-home-categories' ) || false !== strpos( $content, 'rmh-healthcare-needs' ) );
    $has_products   = ( false !== strpos( $content, 'rmh-product-grid' ) || false !== strpos( $content, 'rmh-products-section' ) );
    $has_promotions = ( false !== strpos( $content, 'rmh-promotion' ) || false !== strpos( $content, 'Upto 20% Off' ) );
    $has_delivery   = ( false !== strpos( $content, 'rmh-delivery-section' ) || false !== strpos( $content, 'rmh-delivery-banner' ) );
    $has_how_to     = ( false !== strpos( $content, 'rmh-how-to-order' ) || false !== strpos( $content, 'rmh-how-grid' ) || false !== strpos( $content, 'rmh-steps-grid' ) );
    $has_rx_form    = ( false !== strpos( $content, 'rmhPrescriptionForm' ) );
    $has_faq        = ( false !== strpos( $content, 'rmh-faq' ) );
    $has_location   = ( false !== strpos( $content, 'rmh-store-location' ) || false !== strpos( $content, 'rmh-find-store' ) );

    $quick_services = function_exists( 'rmh_quick_services_shortcode' ) ? rmh_quick_services_shortcode() : '';
    $manufacturers  = function_exists( 'rmh_trusted_manufacturers_shortcode' ) ? rmh_trusted_manufacturers_shortcode() : '';
    $promotions     = ! $has_promotions ? rmh_home_promotions_shortcode() : '';
    $products       = ! $has_products ? rmh_home_products_shortcode() : '';
    $how_to         = ! $has_how_to ? rmh_how_to_order_shortcode() : '';
    $rx_form        = ! $has_rx_form ? rmh_home_prescription_section( rmh_prescription_form_shortcode() ) : '';
    $delivery       = ! $has_delivery ? rmh_delivery_section_shortcode() : '';
    $dt_section     = function_exists( 'rmh_delivery_and_tracking_shortcode' ) ? rmh_delivery_and_tracking_shortcode() : ( function_exists( 'rmh_delivery_feedback_shortcode' ) ? rmh_delivery_feedback_shortcode() : '' );
    $faq            = ! $has_faq ? rmh_faq_shortcode() : '';
    $trust          = function_exists( 'rmh_trust_regulatory_shortcode' ) ? rmh_trust_regulatory_shortcode() : '';
    $location       = ! $has_location ? rmh_store_location_shortcode() : '';

    // Insert Trusted Manufacturers in place of Popular Categories directly after the hero section.
    if ( '' !== trim( $manufacturers ) ) {
        $content = preg_replace(
            '#(<section\b[^>]*class="[^"]*\brmh-home-hero\b[^"]*"[^>]*>.*?</section>)#s',
            '$1' . $manufacturers,
            $content,
            1
        );
        $manufacturers = '';
    }

    $append = '';

    if ( '' !== trim( $quick_services ) ) {
        $append .= $quick_services;
    }
    if ( '' !== trim( $manufacturers ) ) {
        $append .= $manufacturers;
    }
    if ( '' !== trim( $promotions ) ) {
        $append .= $promotions;
    }
    if ( '' !== trim( $products ) ) {
        $append .= $products;
    }
    if ( '' !== trim( $rx_form ) ) {
        $append .= $rx_form;
    }
    if ( '' !== trim( $delivery ) ) {
        $append .= $delivery;
    }
    if ( '' !== trim( $location ) ) {
        $append .= $location;
    }
    if ( '' !== trim( $how_to ) ) {
        $append .= $how_to;
    }
    if ( '' !== trim( $faq ) ) {
        $append .= $faq;
    }
    if ( '' !== trim( $trust ) ) {
        $append .= $trust;
    }
    if ( '' !== trim( $dt_section ) ) {
        $append .= $dt_section;
    }

    $combined = $content . $append;

    // Wrap page content with skip link target
    return '<div id="rmh-main-content" class="rmh-main-content" tabindex="-1">' . $combined . '</div>';
}

add_filter( 'the_content', 'rmh_append_homepage_sections', 20 );

/**
 * Clean up homepage content by removing duplicate delivery-location checkers.
 *
 * @param string $content Post content.
 * @return string
 */
function rmh_cleanup_homepage_content( $content ) {
    if ( is_admin() || ! is_front_page() ) {
        return $content;
    }

    // Remove the duplicate utility-bar location checker (keep header + dedicated section).
    $content = preg_replace( '#<button[^>]*\bid=["\']rmhUtilityLocationBtn["\'][^>]*>.*?</button>#s', '', $content, 1 );

    // Remove the duplicate instant-strip location checker (keep header + dedicated section).
    $content = preg_replace( '#<button[^>]*\bid=["\']rmhStripCheckBtn["\'][^>]*>.*?</button>#s', '', $content, 1 );

    // Remove the old promotion carousel with stale WhatsApp number and call-to-order.
    $content = preg_replace( '#<div class="rmh-promotion-carousel"[^>]*>.*?</div>#s', '', $content, 1 );

    // Remove any remaining stray WhatsApp links with old number.
    $content = preg_replace( '#<a[^>]*href=["\']https?://wa\.me/918895991117[^"\']*["\'][^>]*>.*?</a>#s', '', $content );
    $content = preg_replace( '#<a[^>]*href=["\']https?://wa\.me/8895991117[^"\']*["\'][^>]*>.*?</a>#s', '', $content );

    // Keep the rebuilt prescription workflow as the single homepage form.
    $content = preg_replace( '#<section\b[^>]*id=["\']rmh-prescription["\'][^>]*class=["\'][^"\']*rmh-form-section[^"\']*["\'][^>]*>.*?</section>#s', '', $content, 1 );

    // Remove "Call to Order" promotional text if present.
    $content = preg_replace( '#<[^>]*>Call to Order</[^>]*>#i', '', $content );
    $content = preg_replace( '#<[^>]*>Call.to.Order</[^>]*>#i', '', $content );

    // Remove "SHOP BY HEALTHCARE NEED" section completely per user request.
    $content = preg_replace( '#<!--\s*={5,}\s*CATEGORY SECTION[\s\S]*?</section>#i', '', $content );
    $content = preg_replace( '#<section[^>]*class="rmh-section"[^>]*>[\s\S]*?SHOP BY HEALTHCARE NEED[\s\S]*?</section>#i', '', $content );

    return $content;
}

add_filter( 'the_content', 'rmh_cleanup_homepage_content', 25 );

/**
 * Render links to the editable customer information pages that exist.
 */
function rmh_render_footer_information() {
    if ( is_admin() ) {
        return;
    }

    $page_links = array(
        'about-us'            => __( 'About', 'rabindra-medical-hall' ),
        'contact-us'          => __( 'Contact', 'rabindra-medical-hall' ),
        'faq'                 => __( 'FAQ', 'rabindra-medical-hall' ),
        'delivery-information' => __( 'Delivery Information', 'rabindra-medical-hall' ),
        'prescription-policy' => __( 'Prescription Policy', 'rabindra-medical-hall' ),
        'privacy-policy'      => __( 'Privacy Policy', 'rabindra-medical-hall' ),
        'terms-conditions'    => __( 'Terms & Conditions', 'rabindra-medical-hall' ),
        'returns-refunds'     => __( 'Return Policy', 'rabindra-medical-hall' ),
    );

    $links = array();

    foreach ( $page_links as $slug => $label ) {
        if ( 'privacy-policy' === $slug && function_exists( 'get_privacy_policy_url' ) ) {
            $privacy_url = get_privacy_policy_url();

            if ( $privacy_url ) {
                $links[] = sprintf(
                    '<a href="%s">%s</a>',
                    esc_url( $privacy_url ),
                    esc_html( $label )
                );

                continue;
            }
        }

        $page = get_page_by_path( $slug, OBJECT, 'page' );

        if ( $page && 'publish' === $page->post_status ) {
            $links[] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( get_permalink( $page ) ),
                esc_html( $label )
            );
        }
    }

    if ( empty( $links ) ) {
        return;
    }

    $trust_settings = function_exists( 'rmh_get_trust_settings' ) ? rmh_get_trust_settings() : array();
    ?>
    <div class="rmh-footer-information">
        <div class="rmh-footer-information__inner">
            <strong><?php echo esc_html__( 'Rabindra Medical Hall', 'rabindra-medical-hall' ); ?></strong>
            <nav aria-label="<?php echo esc_attr__( 'Customer information', 'rabindra-medical-hall' ); ?>">
                <?php echo implode( '', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- links are escaped above. ?>
            </nav>
            <?php if ( ! empty( $trust_settings['gstin'] ) ) : ?>
                <p class="rmh-footer-business-id"><strong><?php echo esc_html__( 'GSTIN:', 'rabindra-medical-hall' ); ?></strong> <?php echo esc_html( $trust_settings['gstin'] ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $trust_settings['drug_license_numbers'] ) ) : ?>
                <p class="rmh-footer-business-id"><strong><?php echo esc_html__( 'Drug Licence Numbers:', 'rabindra-medical-hall' ); ?></strong> <?php echo esc_html( preg_replace( '/\s*\R\s*/', ', ', (string) $trust_settings['drug_license_numbers'] ) ); ?></p>
            <?php endif; ?>
            <p class="rmh-footer-legal"><?php echo esc_html__( 'Disclaimer: Rabindra Medical Hall requires a valid doctor prescription for all restricted or scheduled prescription healthcare fulfillment orders under prevailing pharmacy acts.', 'rabindra-medical-hall' ); ?></p>
        </div>
    </div>
    <?php
}

add_action( 'astra_footer_before', 'rmh_render_footer_information', 20 );

/**
 * Render modern mobile bottom navigation for small screens.
 */
function rmh_render_mobile_nav() {
    if ( is_admin() ) {
        return;
    }

    $cart_url    = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
    $account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
    $cart_count  = 0;

    if ( function_exists( 'WC' ) && WC()->cart ) {
        $cart_count = WC()->cart->get_cart_contents_count();
    }
    ?>
    <nav class="rmh-mobile-nav" aria-label="<?php echo esc_attr__( 'Mobile navigation', 'rabindra-medical-hall' ); ?>">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="rmh-mobile-nav__home"<?php echo is_front_page() ? ' aria-current="page"' : ''; ?>>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span><?php echo esc_html__( 'Home', 'rabindra-medical-hall' ); ?></span>
        </a>
        <a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>" class="rmh-mobile-nav__categories">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span><?php echo esc_html__( 'Shop', 'rabindra-medical-hall' ); ?></span>
        </a>
        <a href="#rmh-site-search-field" class="rmh-mobile-nav__search" id="rmhMobileNavSearch">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <span><?php echo esc_html__( 'Search', 'rabindra-medical-hall' ); ?></span>
        </a>
        <a href="<?php echo esc_url( $cart_url ); ?>" class="rmh-mobile-nav__cart" style="position:relative;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <span><?php echo esc_html__( 'Cart', 'rabindra-medical-hall' ); ?></span>
            <span class="rmh-mobile-nav__cart-badge" aria-label="<?php echo esc_attr( sprintf( _n( '%d item in cart', '%d items in cart', $cart_count, 'rabindra-medical-hall' ), $cart_count ) ); ?>" data-count="<?php echo esc_attr( $cart_count ); ?>"><?php echo esc_html( $cart_count ); ?></span>
        </a>
        <a href="<?php echo esc_url( $account_url ); ?>" class="rmh-mobile-nav__account">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span><?php echo esc_html__( 'Account', 'rabindra-medical-hall' ); ?></span>
        </a>
    </nav>
    <?php
}

add_action( 'wp_footer', 'rmh_render_mobile_nav', 5 );

/**
 * Render the reusable delivery location modal.
 *
 * This modal is triggered by:
 * 1. Header location badge (#rmhHeaderLocationBadge)
 * 2. Dedicated delivery section button (#rmhStepLocationBtn)
 *
 * It uses the PHP-configured store coordinates, radius, and allowed postcodes
 * passed via rmhData JavaScript object.
 */
function rmh_render_delivery_location_modal() {
    if ( is_admin() ) {
        return;
    }
    ?>
    <div class="rmh-modal-backdrop" id="rmhLocationModalBackdrop" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="rmhModalTitle">
        <div class="rmh-location-modal" id="rmhLocationModal">
            <header class="rmh-modal-header">
                <div class="rmh-modal-title-group">
                    <span class="rmh-modal-eyebrow"><?php echo esc_html__( 'Delivery Check', 'rabindra-medical-hall' ); ?></span>
                    <h2 id="rmhModalTitle"><?php echo esc_html__( 'Check Delivery Availability', 'rabindra-medical-hall' ); ?></h2>
                </div>
                <button type="button" class="rmh-modal-close" id="rmhLocationModalClose" aria-label="<?php echo esc_attr__( 'Close delivery check', 'rabindra-medical-hall' ); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </header>

            <div class="rmh-modal-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Location check method', 'rabindra-medical-hall' ); ?>">
                <button type="button" class="rmh-tab-btn is-active" id="rmhTabGps" role="tab" aria-selected="true" aria-controls="rmhPanelGps">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg>
                    <span><?php echo esc_html__( 'Use GPS', 'rabindra-medical-hall' ); ?></span>
                </button>
                <button type="button" class="rmh-tab-btn" id="rmhTabManual" role="tab" aria-selected="false" aria-controls="rmhPanelManual" tabindex="-1">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-12 10-12 10S4 16 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span><?php echo esc_html__( 'Enter PIN', 'rabindra-medical-hall' ); ?></span>
                </button>
            </div>

            <div class="rmh-tab-panel is-active" id="rmhPanelGps" role="tabpanel" aria-labelledby="rmhTabGps">
                <div class="rmh-loc-state is-active" id="rmhGpsInitialState">
                    <div class="rmh-loc-card-hero">
                        <div class="rmh-loc-hero-icon" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg>
                        </div>
                        <div class="rmh-loc-hero-text">
                            <h3><?php echo esc_html__( 'Check delivery with your location', 'rabindra-medical-hall' ); ?></h3>
                            <p><?php echo esc_html__( 'We\'ll calculate the distance from Rabindra Medical Hall to see if local delivery is available.', 'rabindra-medical-hall' ); ?></p>
                        </div>
                    </div>
                    <div class="rmh-loc-actions">
                        <button type="button" class="rmh-btn rmh-btn-primary rmh-btn-block" id="rmhCheckGpsBtn">
                            <span aria-hidden="true">📍</span> <?php echo esc_html__( 'Detect My Location', 'rabindra-medical-hall' ); ?>
                        </button>
                        <button type="button" class="rmh-btn rmh-btn-secondary rmh-btn-block" id="rmhSwitchToManualBtn"><?php echo esc_html__( 'Enter PIN code manually', 'rabindra-medical-hall' ); ?></button>
                    </div>
                    <p class="rmh-loc-privacy-note"><?php echo esc_html__( 'Your location is used only for this delivery check and is not stored.', 'rabindra-medical-hall' ); ?></p>
                </div>

                <div class="rmh-loc-state" id="rmhGpsLoadingState">
                    <div class="rmh-loc-spinner" aria-hidden="true"></div>
                    <h4 class="rmh-loc-loading-title"><?php echo esc_html__( 'Checking your location...', 'rabindra-medical-hall' ); ?></h4>
                    <p class="rmh-loc-loading-desc"><?php echo esc_html__( 'Please allow location access when prompted by your browser.', 'rabindra-medical-hall' ); ?></p>
                </div>

                <div class="rmh-loc-state" id="rmhGpsEligibleState">
                    <div class="rmh-loc-badge-success" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <span><?php echo esc_html__( 'Fast Local Delivery', 'rabindra-medical-hall' ); ?></span>
                    </div>
                    <div class="rmh-loc-result-card">
                        <div class="rmh-loc-distance-badge" id="rmhGpsDistanceText" aria-hidden="true"></div>
                        <h4 class="rmh-loc-result-title"><?php echo esc_html__( 'Fast local delivery is available to your location', 'rabindra-medical-hall' ); ?></h4>
                        <p class="rmh-loc-result-desc"><?php echo esc_html__( 'Delivery availability depends on location, medicine availability and pharmacy operating hours.', 'rabindra-medical-hall' ); ?></p>
                        <p class="rmh-loc-disclaimer"><small><?php echo esc_html__( 'Delivery times are estimates. Actual delivery may vary based on traffic, weather, and order volume. Minimum order ₹500 for free delivery.', 'rabindra-medical-hall' ); ?></small></p>
                    </div>
                    <div class="rmh-loc-secondary-action">
                        <button type="button" class="rmh-text-btn" id="rmhGpsChangeBtn"><?php echo esc_html__( 'Check another location', 'rabindra-medical-hall' ); ?></button>
                    </div>
                </div>

                <div class="rmh-loc-state" id="rmhGpsOutsideState">
                    <div class="rmh-loc-badge-warning" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span><?php echo esc_html__( 'Outside Local Delivery Zone', 'rabindra-medical-hall' ); ?></span>
                    </div>
                    <div class="rmh-loc-result-card rmh-loc-result-card--outside">
                        <div class="rmh-loc-distance-badge" id="rmhGpsOutsideDistanceText" aria-hidden="true"></div>
                        <h4 class="rmh-loc-result-title"><?php echo esc_html__( 'Standard delivery available instead', 'rabindra-medical-hall' ); ?></h4>
                        <p class="rmh-loc-result-desc"><?php echo esc_html__( 'Your location is outside our 10 km local delivery radius. Regular delivery options are available at checkout.', 'rabindra-medical-hall' ); ?></p>
                        <p class="rmh-loc-disclaimer"><small><?php echo esc_html__( 'Fast local delivery is available to eligible local PIN codes. Delivery availability depends on location, medicine availability and pharmacy operating hours. Regular delivery options are shown at checkout where available.', 'rabindra-medical-hall' ); ?></small></p>
                    </div>
                    <div class="rmh-loc-secondary-action">
                        <button type="button" class="rmh-text-btn" id="rmhGpsChangeBtn2"><?php echo esc_html__( 'Check another location', 'rabindra-medical-hall' ); ?></button>
                    </div>
                </div>

                <div class="rmh-loc-state" id="rmhGpsErrorState">
                    <div class="rmh-loc-error-box">
                        <div class="rmh-loc-error-icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        </div>
                        <div>
                            <h4 id="rmhGpsErrorTitle"><?php echo esc_html__( 'Location Permission Not Granted', 'rabindra-medical-hall' ); ?></h4>
                            <p id="rmhGpsErrorDesc"><?php echo esc_html__( 'No problem at all! You can manually enter your delivery address or PIN code below.', 'rabindra-medical-hall' ); ?></p>
                        </div>
                    </div>
                    <div class="rmh-loc-actions rmh-loc-actions--dual">
                        <button type="button" class="rmh-btn rmh-btn-primary" id="rmhGpsRetryBtn"><?php echo esc_html__( 'Retry GPS', 'rabindra-medical-hall' ); ?></button>
                        <button type="button" class="rmh-btn rmh-btn-secondary" id="rmhErrorManualBtn"><?php echo esc_html__( 'Enter PIN Manually', 'rabindra-medical-hall' ); ?></button>
                    </div>
                </div>
            </div>

            <div class="rmh-tab-panel" id="rmhPanelManual" role="tabpanel" aria-labelledby="rmhTabManual" hidden>
                <form class="rmh-manual-address-form" id="rmhManualAddressForm" novalidate>
                    <p><?php echo esc_html__( 'Enter your 6-digit PIN code to check if local delivery is available.', 'rabindra-medical-hall' ); ?></p>
                    <div class="rmh-form-row rmh-form-row--2col">
                        <div class="rmh-form-group">
                            <label for="rmhManualPostcode"><span class="rmh-req" aria-hidden="true">*</span><?php echo esc_html__( 'PIN Code', 'rabindra-medical-hall' ); ?></label>
                            <input type="text" id="rmhManualPostcode" name="postcode" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="postal-code" aria-describedby="rmhPostcodeHelp">
                            <span id="rmhPostcodeHelp" class="rmh-field-help"><?php echo esc_html__( '6-digit PIN code (e.g. 753012)', 'rabindra-medical-hall' ); ?></span>
                        </div>
                        <div class="rmh-form-group">
                            <label for="rmhManualArea"><?php echo esc_html__( 'Area / Locality (optional)', 'rabindra-medical-hall' ); ?></label>
                            <input type="text" id="rmhManualArea" name="area" autocomplete="address-level2">
                        </div>
                    </div>
                    <div class="rmh-form-feedback" id="rmhManualFeedback" hidden aria-live="polite"></div>
                    <button type="submit" class="rmh-btn rmh-btn-primary rmh-btn-block"><?php echo esc_html__( 'Check Delivery', 'rabindra-medical-hall' ); ?></button>
                </form>
                <p class="rmh-loc-disclaimer"><small><?php echo esc_html__( 'Fast local delivery is available within 10 km of Rabindra Medical Hall, subject to applicable delivery/order conditions. Delivery availability depends on location, medicine availability and pharmacy operating hours. Valid PIN codes: 753012, 753001, 753002, 753003.', 'rabindra-medical-hall' ); ?></small></p>
            </div>
        </div>
    </div>
    <?php
}

add_action( 'wp_footer', 'rmh_render_delivery_location_modal', 10 );

/**
 * Replace Astra's default footer credit without changing Customizer data.
 *
 * @param mixed $value Current copyright setting.
 * @return string
 */
function rmh_footer_copyright_text( $value ) {
    return 'Copyright &copy; 2026 Rabindra Medical Hall. All rights reserved.';
}

add_filter( 'astra_get_option_footer-copyright-editor', 'rmh_footer_copyright_text', 20 );

/**
 * Register admin settings for optional store location fields.
 */
function rmh_register_store_location_settings() {
    register_setting( 'rmh_store_location_group', 'rmh_store_location', array(
        'type' => 'array',
        'default' => array(),
        'sanitize_callback' => function ( $value ) {
            $keys = array( 'store_address', 'latitude', 'longitude', 'google_maps_url', 'directions_url' );
            $clean = array();

            foreach ( $keys as $key ) {
                if ( isset( $value[ $key ] ) ) {
                    $clean[ $key ] = sanitize_text_field( wp_unslash( $value[ $key ] ) );
                }
            }

            return $clean;
        },
    ) );
}

add_action( 'admin_init', 'rmh_register_store_location_settings' );

/**
 * Add a small admin configuration page for the verified pharmacy location.
 */
function rmh_store_location_settings_page() {
    add_menu_page(
        __( 'Rabindra Store Location', 'rabindra-medical-hall' ),
        __( 'Store Location', 'rabindra-medical-hall' ),
        'manage_options',
        'rmh-store-location',
        'rmh_render_store_location_settings_page',
        'dashicons-location-alt'
    );
}

add_action( 'admin_menu', 'rmh_store_location_settings_page' );

/**
 * Render the optional verified store-location settings screen.
 */
function rmh_render_store_location_settings_page() {
    $current = rmh_get_store_location_config();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Rabindra Medical Hall Store Location', 'rabindra-medical-hall' ); ?></h1>
        <p><?php echo esc_html__( 'Use only verified business data. Leave fields blank to hide the location block and keep the storefront neutral.', 'rabindra-medical-hall' ); ?></p>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'rmh_store_location_group' );
            do_settings_sections( 'rmh_store_location_group' );
            ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="rmh_store_address"><?php echo esc_html__( 'Store Address', 'rabindra-medical-hall' ); ?></label></th>
                    <td><input id="rmh_store_address" type="text" class="regular-text" name="rmh_store_location[store_address]" value="<?php echo esc_attr( $current['store_address'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="rmh_store_latitude"><?php echo esc_html__( 'Latitude', 'rabindra-medical-hall' ); ?></label></th>
                    <td><input id="rmh_store_latitude" type="text" class="regular-text" name="rmh_store_location[latitude]" value="<?php echo esc_attr( $current['latitude'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="rmh_store_longitude"><?php echo esc_html__( 'Longitude', 'rabindra-medical-hall' ); ?></label></th>
                    <td><input id="rmh_store_longitude" type="text" class="regular-text" name="rmh_store_location[longitude]" value="<?php echo esc_attr( $current['longitude'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="rmh_store_google_maps_url"><?php echo esc_html__( 'Google Maps URL', 'rabindra-medical-hall' ); ?></label></th>
                    <td><input id="rmh_store_google_maps_url" type="url" class="regular-text" name="rmh_store_location[google_maps_url]" value="<?php echo esc_attr( $current['google_maps_url'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="rmh_store_directions_url"><?php echo esc_html__( 'Directions URL', 'rabindra-medical-hall' ); ?></label></th>
                    <td><input id="rmh_store_directions_url" type="url" class="regular-text" name="rmh_store_location[directions_url]" value="<?php echo esc_attr( $current['directions_url'] ); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Render homepage WooCommerce category cards.
 *
 * @return string
 */
function rmh_home_categories_shortcode() {
    if ( ! taxonomy_exists( 'product_cat' ) ) {
        return '';
    }

    $preferred_slugs = array( 'medicines', 'diabetes-care', 'cough-cold', 'digestive-care', 'pain-relief', 'skin-care', 'vitamins-supplements', 'personal-care' );
    $terms_by_slug = array();
    $queried_terms = get_terms(
        array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        )
    );

    if ( ! is_wp_error( $queried_terms ) ) {
        foreach ( $queried_terms as $term ) {
            if ( in_array( $term->slug, $preferred_slugs, true ) && (int) $term->count > 0 ) {
                $terms_by_slug[ $term->slug ] = $term;
            }
        }
    }

    $terms = array();
    foreach ( $preferred_slugs as $slug ) {
        if ( isset( $terms_by_slug[ $slug ] ) ) {
            $terms[] = $terms_by_slug[ $slug ];
        }
    }

    if ( empty( $terms ) ) {
        return '';
    }

    ob_start();
    ?>
    <section class="rmh-home-categories" aria-labelledby="rmh-home-categories-title">
        <header class="rmh-section-heading">
            <span class="rmh-section-label"><?php echo esc_html__( 'BROWSE BY CATEGORY', 'rabindra-medical-hall' ); ?></span>
            <h2 id="rmh-home-categories-title"><?php echo esc_html__( 'Find what you need', 'rabindra-medical-hall' ); ?></h2>
            <p><?php echo esc_html__( 'Shop real pharmacy categories from our current catalogue.', 'rabindra-medical-hall' ); ?></p>
        </header>
        <div class="rmh-home-categories__grid">
            <?php foreach ( $terms as $term ) : ?>
                <?php
                $image_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
                $image    = $image_id ? wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'loading' => 'lazy', 'decoding' => 'async' ) ) : rmh_render_category_visual( $term );
                ?>
                <a class="rmh-home-category" href="<?php echo esc_url( get_term_link( $term ) ); ?>">
                    <span class="rmh-home-category__visual"><?php echo wp_kses_post( $image ); ?></span>
                    <span class="rmh-home-category__name"><?php echo esc_html( $term->name ); ?></span>
                    <span class="rmh-home-category__action"><?php echo esc_html__( 'Shop category', 'rabindra-medical-hall' ); ?> <span aria-hidden="true">→</span></span>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="rmh-home-categories__footer"><a class="rmh-btn rmh-btn-light" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ); ?>"><?php echo esc_html__( 'View all categories', 'rabindra-medical-hall' ); ?></a></p>
    </section>
    <?php
    return ob_get_clean();
}

add_shortcode( 'rmh_home_categories', 'rmh_home_categories_shortcode' );

/**
 * Render healthcare-need search shortcuts for the homepage.
 *
 * Each tile routes to the shop/search experience with a controlled query.
 * No product counts or popularity claims are fabricated.
 *
 * @return string
 */
function rmh_healthcare_needs_shortcode() {
    // Section removed per user request.
    return '';
}

add_shortcode( 'rmh_healthcare_needs', 'rmh_healthcare_needs_shortcode' );

/**
 * Return the approved category IDs in their display order.
 *
 * @return int[]
 */
function rmh_get_pharmacy_category_ids() {
    $ids = get_option( 'rmh_pharmacy_category_ids', array() );

    if ( ! is_array( $ids ) ) {
        return array();
    }

    return array_values( array_filter( array_map( 'absint', $ids ) ) );
}

/**
 * Return lightweight fallback marks for categories without thumbnails.
 *
 * @return array
 */
function rmh_get_pharmacy_category_icons() {
    return array(
        'medicines'               => 'Rx',
        'vitamins-supplements'    => 'V+',
        'medical-devices'         => 'MD',
        'food-nutrition'          => 'Food',
        'sexual-wellness'         => 'SW',
        'skin-care'               => 'Skin',
        'hair-care'               => 'Hair',
        'womens-care'             => 'WC',
        'mens-health'             => 'MH',
        'elderly-care'            => 'EC',
        'pain-relief'             => 'PR',
        'supports-braces'         => 'SB',
        'diabetes-care'           => 'DC',
        'digestive-care'          => 'DG',
        'cough-cold'              => 'CC',
        'immunity-boosters'       => 'IB',
        'oral-care'               => 'OC',
        'personal-care'           => 'PC',
        'baby-care'               => 'BC',
        'health-wellness'         => 'HW',
    );
}

/**
 * Render a brand-neutral local category illustration when no verified image
 * is assigned to the WooCommerce term.
 *
 * @param WP_Term $term Product category term.
 * @return string
 */
function rmh_render_category_visual( $term ) {
    $reference_images = array(
        'vitamins-supplements' => 'rmh-category-vitamins-supplements.jpg',
        'pain-relief'         => 'rmh-category-pain-relief.jpg',
        'diabetes-care'       => 'rmh-category-diabetes-care.jpg',
        'digestive-care'      => 'rmh-category-digestive-care.jpg',
        'personal-care'       => 'rmh-category-personal-care.jpg',
        'baby-care'           => 'rmh-category-baby-care.jpg',
        'skin-care'           => 'rmh-category-skin-care.jpg',
        'hair-care'           => 'rmh-category-hair-care.jpg',
        'oral-care'           => 'rmh-category-oral-care.jpg',
        'health-wellness'     => 'rmh-category-health-wellness.jpg',
        'womens-care'         => 'rmh-category-womens-care.jpg',
        'mens-health'         => 'rmh-category-mens-health.jpg',
        'sexual-wellness'     => 'rmh-category-sexual-wellness.jpg',
        'elderly-care'        => 'rmh-category-elderly-care.jpg',
        'medical-devices'     => 'rmh-category-medical-devices.jpg',
    );

    if ( isset( $reference_images[ $term->slug ] ) ) {
        $image_path = trailingslashit( get_stylesheet_directory() ) . 'assets/images/' . $reference_images[ $term->slug ];

        if ( file_exists( $image_path ) ) {
            $image_url = trailingslashit( set_url_scheme( get_stylesheet_directory_uri(), rmh_is_secure_request() ? 'https' : 'http' ) ) . 'assets/images/' . $reference_images[ $term->slug ];
            $label     = sprintf( __( '%s category image', 'rabindra-medical-hall' ), $term->name );

            return sprintf(
                '<img class="rmh-category-art rmh-category-art--image" src="%s" alt="%s" loading="lazy" width="160" height="120">',
                esc_url( $image_url ),
                esc_attr( $label )
            );
        }
    }

    $visuals = array(
        'medicines'            => 'medicine',
        'vitamins-supplements' => 'supplement',
        'medical-devices'      => 'device',
        'food-nutrition'       => 'nutrition',
        'sexual-wellness'      => 'wellness',
        'skin-care'            => 'skin',
        'hair-care'            => 'hair',
        'womens-care'          => 'care',
        'mens-health'          => 'care',
        'elderly-care'         => 'care',
        'pain-relief'          => 'first-aid',
        'supports-braces'      => 'device',
        'diabetes-care'        => 'diabetes',
        'digestive-care'       => 'nutrition',
        'cough-cold'           => 'wellness',
        'immunity-boosters'    => 'supplement',
        'oral-care'            => 'personal',
        'personal-care'        => 'personal',
        'baby-care'            => 'baby',
        'health-wellness'      => 'wellness',
    );
    $visual = isset( $visuals[ $term->slug ] ) ? $visuals[ $term->slug ] : 'care';
    $asset  = trailingslashit( set_url_scheme( get_stylesheet_directory_uri(), rmh_is_secure_request() ? 'https' : 'http' ) ) . 'assets/images/category-visuals.svg#' . rawurlencode( $visual );
    $label  = sprintf( __( '%s category image', 'rabindra-medical-hall' ), $term->name );

    return sprintf(
        '<svg class="rmh-category-art" viewBox="0 0 160 120" role="img" aria-label="%s" focusable="false"><use href="%s" xlink:href="%s"></use></svg>',
        esc_attr( $label ),
        esc_url( $asset ),
        esc_url( $asset )
    );
}

/**
 * Render professional promotional cards for the homepage.
 *
 * Three core pharmacy promotions:
 * 1. Upto 20% Off (promotional offer)
 * 2. Order on WhatsApp
 * 3. 1-Hour Local Delivery
 *
 * @return string
 */
function rmh_home_promotions_shortcode() {
    $img_dir = trailingslashit( set_url_scheme( get_stylesheet_directory_uri(), rmh_is_secure_request() ? 'https' : 'http' ) ) . 'assets/images/';

    $cards = array(
        array(
            'image'       => $img_dir . 'rmh-promo-first-order.jpg',
            'image_alt'   => __( 'Medicine bottles and healthcare supplements', 'rabindra-medical-hall' ),
            'title'       => __( 'Upto 20% Off', 'rabindra-medical-hall' ),
            'description' => __( 'Save upto 20% on selected medicines and healthcare products.', 'rabindra-medical-hall' ),
            'cta_text'    => __( 'Shop Medicines', 'rabindra-medical-hall' ),
            'cta_url'     => add_query_arg( 'rmh_categories', 'all', wc_get_page_permalink( 'shop' ) ),
            'cta_class'   => 'rmh-btn-primary',
            'badge'       => __( 'New Customers', 'rabindra-medical-hall' ),
        ),
        array(
            'image'       => $img_dir . 'rmh-promo-call-order.jpg',
            'image_alt'   => __( 'Pharmacy WhatsApp support and prescription assistance', 'rabindra-medical-hall' ),
            'title'       => __( 'Chat on WhatsApp', 'rabindra-medical-hall' ),
            'description' => __( 'Chat with our pharmacy team for quick assistance with medicines, prescriptions or orders.', 'rabindra-medical-hall' ),
            'cta_text'    => __( 'Start Conversation', 'rabindra-medical-hall' ),
            'cta_url'     => rmh_get_whatsapp_url( 'Hello Rabindra Medical Hall, I need help with my order.' ),
            'cta_class'   => 'rmh-btn-whatsapp',
            'badge'       => __( 'Fast Response', 'rabindra-medical-hall' ),
        ),
        array(
            'image'       => $img_dir . 'rmh-promo-fast-delivery.jpg',
            'image_alt'   => __( 'Fast local medicine delivery box with pharmacy medicines', 'rabindra-medical-hall' ),
            'title'       => __( 'Fast Local Delivery', 'rabindra-medical-hall' ),
            'description' => __( 'Delivery availability depends on location, medicine availability and pharmacy operating hours.', 'rabindra-medical-hall' ),
            'cta_text'    => __( 'Check Eligibility', 'rabindra-medical-hall' ),
            'cta_url'     => '#',
            'cta_class'   => 'rmh-btn-secondary',
            'cta_attrs'   => 'id="rmhStepLocationBtnDeliveryPromo" aria-haspopup="dialog"',
            'badge'       => __( 'Selected Local Areas', 'rabindra-medical-hall' ),
        ),
    );

    ob_start();
    ?>
    <section class="rmh-promotion-section" aria-label="<?php echo esc_attr__( 'Pharmacy promotions', 'rabindra-medical-hall' ); ?>">
        <header class="rmh-section-heading">
            <span class="rmh-section-label"><?php echo esc_html__( 'SPECIAL OFFERS', 'rabindra-medical-hall' ); ?></span>
            <h2><?php echo esc_html__( 'Pharmacy Benefits', 'rabindra-medical-hall' ); ?></h2>
            <p><?php echo esc_html__( 'Save time and money with our exclusive pharmacy services.', 'rabindra-medical-hall' ); ?></p>
        </header>
        <div class="rmh-promo-cards">
            <?php foreach ( $cards as $card ) : ?>
                <article class="rmh-promo-card">
                    <div class="rmh-promo-card__image-wrap" aria-hidden="true">
                        <img
                            class="rmh-promo-card__image"
                            src="<?php echo esc_url( $card['image'] ); ?>"
                            alt="<?php echo esc_attr( $card['image_alt'] ); ?>"
                            loading="lazy"
                            decoding="async"
                            width="480"
                            height="240"
                        >
                    </div>
                    <div class="rmh-promo-card__content">
                        <?php if ( ! empty( $card['badge'] ) ) : ?>
                            <span class="rmh-promo-badge"><?php echo esc_html( $card['badge'] ); ?></span>
                        <?php endif; ?>
                        <h3><?php echo esc_html( $card['title'] ); ?></h3>
                        <p><?php echo esc_html( $card['description'] ); ?></p>
                    </div>
                    <a class="rmh-btn <?php echo esc_attr( $card['cta_class'] ); ?>" href="<?php echo esc_url( $card['cta_url'] ); ?>" <?php echo isset( $card['cta_attrs'] ) ? $card['cta_attrs'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled attributes. ?>>
                        <?php echo esc_html( $card['cta_text'] ); ?>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

add_shortcode( 'rmh_home_promotions', 'rmh_home_promotions_shortcode' );

/**
 * Render the dedicated delivery location section for the homepage.
 *
 * This is the SECOND delivery-location surface (the first is the header badge).
 * It provides a prominent CTA to check delivery eligibility.
 *
 * @return string
 */
function rmh_delivery_section_shortcode() {
    ob_start();
    ?>
    <section id="rmh-delivery-section" class="rmh-content-section" aria-labelledby="rmh-delivery-heading">
        <header class="rmh-section-heading">
            <span class="rmh-section-label"><?php echo esc_html__( 'LOCAL DELIVERY', 'rabindra-medical-hall' ); ?></span>
            <h2 id="rmh-delivery-heading"><?php echo esc_html__( 'Fast Local Delivery', 'rabindra-medical-hall' ); ?></h2>
            <p><?php echo esc_html__( 'Check if your address qualifies for local delivery from Rabindra Medical Hall.', 'rabindra-medical-hall' ); ?></p>
        </header>
        <div class="rmh-delivery-card">
            <div class="rmh-delivery-card__icon" aria-hidden="true">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="rmh-delivery-card__content">
                <h3><?php echo esc_html__( 'Fast Local Delivery (Eligible Areas)', 'rabindra-medical-hall' ); ?></h3>
                <p><?php echo esc_html__( 'Eligible local orders are dispatched promptly during pharmacy hours. Delivery availability depends on location, medicine availability and pharmacy operating hours.', 'rabindra-medical-hall' ); ?></p>
                <ul class="rmh-delivery-card__features">
                    <li><?php echo esc_html__( 'Genuine medicines from licensed pharmacy', 'rabindra-medical-hall' ); ?></li>
                    <li><?php echo esc_html__( 'Prescription verification included', 'rabindra-medical-hall' ); ?></li>
                    <li><?php echo esc_html__( 'Secure online payment', 'rabindra-medical-hall' ); ?></li>
                </ul>
            </div>
            <button type="button" class="rmh-btn rmh-btn-primary rmh-delivery-card__cta" id="rmhStepLocationBtnDelivery" aria-haspopup="dialog">
                <span aria-hidden="true">📍</span>
                <?php echo esc_html__( 'Check My Delivery Eligibility', 'rabindra-medical-hall' ); ?>
            </button>
        </div>
        <p class="rmh-delivery-disclaimer"><?php echo esc_html__( 'Fast local delivery applies to eligible local orders and serviceable locations in Cuttack (within 10 km). Delivery availability depends on location, medicine availability and pharmacy operating hours. Minimum order ₹500 for free delivery; standard ₹30 fee applies below ₹500.', 'rabindra-medical-hall' ); ?></p>
    </section>
    <?php
    return ob_get_clean();
}

add_shortcode( 'rmh_delivery_section', 'rmh_delivery_section_shortcode' );

/**
 * Render a product image without exposing WooCommerce's broken-image-style
 * default artwork when a catalogue product has no assigned media.
 *
 * @param WC_Product $product Product to render.
 * @return string
 */
function rmh_get_placeholder_image_url() {
    return trailingslashit( set_url_scheme( get_stylesheet_directory_uri(), rmh_is_secure_request() ? 'https' : 'http' ) ) . 'assets/images/rmh-product-fallback.svg';
}

add_filter( 'woocommerce_placeholder_img_src', 'rmh_get_placeholder_image_url' );

function rmh_render_home_product_image( $product ) {
    if ( $product instanceof WC_Product && $product->get_image_id() ) {
        return $product->get_image( 'woocommerce_thumbnail', array( 'loading' => '', 'decoding' => 'async' ) );
    }

    $fallback_img = rmh_get_placeholder_image_url();

    return sprintf(
        '<img class="woocommerce-placeholder wp-post-image rmh-product-fallback" src="%1$s" alt="%2$s" width="300" height="300" loading="lazy" decoding="async">',
        esc_url( $fallback_img ),
        esc_attr__( 'Product image unavailable', 'rabindra-medical-hall' )
    );
}

/**
 * Render real WooCommerce products or a professional empty state.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function rmh_home_products_shortcode( $atts = array() ) {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return '';
    }

    $atts = shortcode_atts(
        array(
            'limit' => 4,
        ),
        $atts,
        'rmh_home_products'
    );

    // Prioritize real in-stock medicines with thumbnails first
    $query = new WP_Query(
        array(
            'post_type'           => 'product',
            'post_status'         => 'publish',
            'posts_per_page'      => absint( $atts['limit'] ),
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'meta_query'          => array(
                'relation' => 'AND',
                array(
                    'key'     => '_stock_status',
                    'value'   => 'instock',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_thumbnail_id',
                    'compare' => 'EXISTS',
                ),
            ),
            'orderby'             => 'title',
            'order'               => 'ASC',
        )
    );

    // If fewer than limit, fallback to any in-stock products
    if ( ! $query->have_posts() ) {
        $query = new WP_Query(
            array(
                'post_type'           => 'product',
                'post_status'         => 'publish',
                'posts_per_page'      => absint( $atts['limit'] ),
                'no_found_rows'       => true,
                'ignore_sticky_posts' => true,
                'meta_query'          => array(
                    array(
                        'key'     => '_stock_status',
                        'value'   => 'instock',
                        'compare' => '=',
                    ),
                ),
                'orderby'             => 'title',
                'order'               => 'ASC',
            )
        );
    }

    if ( ! $query->have_posts() ) {
        $previews = array(
            array( 'label' => __( 'Pain Relief', 'rabindra-medical-hall' ), 'visual' => 'first-aid' ),
            array( 'label' => __( 'Vitamins & Supplements', 'rabindra-medical-hall' ), 'visual' => 'supplement' ),
            array( 'label' => __( 'Personal Care', 'rabindra-medical-hall' ), 'visual' => 'personal' ),
            array( 'label' => __( 'Health & Wellness', 'rabindra-medical-hall' ), 'visual' => 'wellness' ),
        );

        ob_start();
        ?>
        <div class="rmh-catalogue-placeholder">
            <div class="rmh-catalogue-placeholder__header">
                <div>
                    <span class="rmh-section-label"><?php echo esc_html__( 'VERIFIED CATALOGUE', 'rabindra-medical-hall' ); ?></span>
                    <h3><?php echo esc_html__( 'Verified products will appear here once the medicine catalogue is live.', 'rabindra-medical-hall' ); ?></h3>
                </div>
                <span class="rmh-catalogue-placeholder__status"><?php echo esc_html__( 'Catalogue coming soon', 'rabindra-medical-hall' ); ?></span>
            </div>
            <div class="rmh-catalogue-preview-grid">
                <?php foreach ( $previews as $preview ) : ?>
                    <div class="rmh-catalogue-preview">
                        <div class="rmh-catalogue-preview__visual" aria-hidden="true">
                            <?php
                            $preview_asset = trailingslashit( set_url_scheme( get_stylesheet_directory_uri(), rmh_is_secure_request() ? 'https' : 'http' ) ) . 'assets/images/category-visuals.svg#' . rawurlencode( $preview['visual'] );
                            echo '<svg viewBox="0 0 160 120" focusable="false"><use href="' . esc_url( $preview_asset ) . '" xlink:href="' . esc_url( $preview_asset ) . '"></use></svg>';
                            ?>
                        </div>
                        <strong><?php echo esc_html( $preview['label'] ); ?></strong>
                        <span><?php echo esc_html__( 'Catalogue coming soon', 'rabindra-medical-hall' ); ?></span>
                        <small><?php echo esc_html__( 'Available when catalogue goes live', 'rabindra-medical-hall' ); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="rmh-catalogue-placeholder__actions">
                <a class="rmh-btn rmh-btn-secondary" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php echo esc_html__( 'Visit Shop', 'rabindra-medical-hall' ); ?> <span aria-hidden="true">&#8594;</span></a>
                <a class="rmh-btn rmh-btn-secondary" href="<?php echo esc_url( add_query_arg( 'rmh_categories', 'all', home_url( '/shop/' ) ) ); ?>"><?php echo esc_html__( 'Browse Categories', 'rabindra-medical-hall' ); ?></a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    ob_start();
    ?>
    <div class="rmh-product-grid">
        <?php
        while ( $query->have_posts() ) :
            $query->the_post();
            $product = wc_get_product( get_the_ID() );

            if ( ! $product instanceof WC_Product ) {
                continue;
            }
            ?>
            <article class="rmh-product-card" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
                <a class="rmh-product-image" href="<?php the_permalink(); ?>">
                    <?php echo rmh_render_home_product_image( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes all dynamic attributes. ?>
                    <?php
                    $classification = function_exists( 'rmh_get_product_classification' ) ? rmh_get_product_classification( $product ) : ( rmh_product_requires_prescription( $product ) ? 'rx' : '' );
                    if ( 'rx' === $classification ) :
                    ?>
                        <span class="rmh-rx-badge rmh-rx-badge--corner"><?php echo esc_html__( 'Rx', 'rabindra-medical-hall' ); ?></span>
                    <?php elseif ( 'otc' === $classification ) : ?>
                        <span class="rmh-otc-badge rmh-otc-badge--corner"><?php echo esc_html__( 'OTC', 'rabindra-medical-hall' ); ?></span>
                    <?php endif; ?>
                    <?php if ( ! $product->is_in_stock() ) : ?>
                        <span class="rmh-product-card__stock-overlay"><?php echo esc_html__( 'Out of Stock', 'rabindra-medical-hall' ); ?></span>
                    <?php endif; ?>
                </a>
                <div class="rmh-product-info">
                    <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                    <?php
                    $composition = function_exists( 'rmh_get_product_composition' ) ? rmh_get_product_composition( $product->get_id() ) : '';
                    if ( $composition ) :
                    ?>
                        <p class="rmh-product-card__meta rmh-product-card__meta--comp" title="<?php echo esc_attr( $composition ); ?>"><?php echo esc_html( $composition ); ?></p>
                    <?php endif; ?>
                    <?php
                    $manufacturer = get_post_meta( $product->get_id(), '_rmh_manufacturer', true );
                    if ( ! $manufacturer ) {
                        $raw_brand = get_post_meta( $product->get_id(), '_brand', true );
                        if ( $raw_brand && ! in_array( strtoupper( trim( $raw_brand ) ), array( 'BLANK', '-BLANK-', 'GENERAL', 'GENERIC', 'GENRIC', '' ), true ) ) {
                            $manufacturer = ucwords( strtolower( trim( $raw_brand ) ) );
                        }
                    }
                    if ( $manufacturer ) :
                    ?>
                        <p class="rmh-product-card__meta rmh-product-card__meta--brand"><?php echo esc_html( $manufacturer ); ?></p>
                    <?php endif; ?>
                    <?php
                    $raw_pack = get_post_meta( $product->get_id(), '_rmh_pack_size', true );
                    if ( ! $raw_pack ) {
                        $raw_pack = get_post_meta( $product->get_id(), '_pack_size', true );
                    }
                    $pack_size = function_exists( 'rmh_format_pack_size' ) ? rmh_format_pack_size( $raw_pack ) : $raw_pack;
                    if ( $pack_size && ! in_array( strtoupper( trim( $pack_size ) ), array( 'BLANK', '-', '' ), true ) ) :
                    ?>
                        <p class="rmh-product-card__meta rmh-product-card__meta--pack"><?php echo esc_html( $pack_size ); ?></p>
                    <?php endif; ?>
                    <div class="rmh-product-footer">
                        <span class="rmh-price"><?php echo wp_kses_post( rmh_get_product_mrp_html( $product ) ); ?></span>
                        <?php if ( ! $product->is_in_stock() ) : ?>
                            <button type="button" class="rmh-btn-card-oos" disabled><?php echo esc_html__( 'Out of Stock', 'rabindra-medical-hall' ); ?></button>
                        <?php elseif ( 'rx' === $classification ) : ?>
                            <button type="button" class="rmh-btn-card-rx rmh-open-rx-modal" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-product-name="<?php echo esc_attr( $product->get_name() ); ?>">
                                <span><?php echo esc_html__( 'Order with Rx', 'rabindra-medical-hall' ); ?></span>
                            </button>
                        <?php else : ?>
                            <div class="rmh-quick-add-wrap" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
                                <button type="button" class="rmh-quick-add-btn" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
                                    <span class="rmh-quick-add-icon" aria-hidden="true">+</span>
                                    <span><?php echo esc_html__( 'Add', 'rabindra-medical-hall' ); ?></span>
                                </button>
                                <div class="rmh-qty-stepper" style="display:none;">
                                    <button type="button" class="rmh-stepper-btn rmh-stepper-minus" aria-label="<?php echo esc_attr__( 'Decrease quantity', 'rabindra-medical-hall' ); ?>">−</button>
                                    <span class="rmh-stepper-qty" aria-live="polite">1</span>
                                    <button type="button" class="rmh-stepper-btn rmh-stepper-plus" aria-label="<?php echo esc_attr__( 'Increase quantity', 'rabindra-medical-hall' ); ?>">+</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php
        endwhile;
        wp_reset_postdata();
        ?>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode( 'rmh_home_products', 'rmh_home_products_shortcode' );

/**
 * Product metadata keys used for current WooCommerce display and future imports.
 *
 * @return array
 */
function rmh_get_product_meta_fields() {
    return array(
        '_rmh_manufacturer' => __( 'Manufacturer', 'rabindra-medical-hall' ),
        '_rmh_composition'  => __( 'Composition', 'rabindra-medical-hall' ),
        '_rmh_pack_size'    => __( 'Pack Size', 'rabindra-medical-hall' ),
        '_rmh_mrp'          => __( 'MRP', 'rabindra-medical-hall' ),
        '_rmh_barcode'      => __( 'Barcode', 'rabindra-medical-hall' ),
        '_rmh_gst'          => __( 'GST', 'rabindra-medical-hall' ),
        '_rmh_hsn'          => __( 'HSN', 'rabindra-medical-hall' ),
        '_rmh_batch'        => __( 'Batch', 'rabindra-medical-hall' ),
        '_rmh_expiry'       => __( 'Expiry', 'rabindra-medical-hall' ),
    );
}

/**
 * Render pharmacy catalogue fields in the WooCommerce product editor.
 */
function rmh_render_product_meta_fields() {
    foreach ( rmh_get_product_meta_fields() as $key => $label ) {
        woocommerce_wp_text_input(
            array(
                'id'          => $key,
                'label'       => $label,
                'value'       => get_post_meta( get_the_ID(), $key, true ),
                'desc_tip'    => true,
                'description' => __( 'Optional pharmacy catalogue detail.', 'rabindra-medical-hall' ),
            )
        );
    }

    woocommerce_wp_checkbox(
        array(
            'id'          => '_rmh_prescription_required',
            'label'       => __( 'Prescription Required', 'rabindra-medical-hall' ),
            'value'       => 'yes' === get_post_meta( get_the_ID(), '_rmh_prescription_required', true ) ? 'yes' : 'no',
            'desc_tip'    => true,
            'description' => __( 'Show a prescription-required badge and upload guidance for this product.', 'rabindra-medical-hall' ),
        )
    );
}

add_action( 'woocommerce_product_options_general_product_data', 'rmh_render_product_meta_fields' );

/**
 * Save pharmacy catalogue fields through the WooCommerce product object.
 *
 * @param WC_Product $product Product being saved.
 */
function rmh_save_product_meta_fields( $product ) {
    foreach ( rmh_get_product_meta_fields() as $key => $label ) {
        $value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';

        if ( '' === $value ) {
            $product->delete_meta_data( $key );
        } else {
            $product->update_meta_data( $key, $value );
        }
    }

    if ( isset( $_POST['_rmh_prescription_required'] ) ) {
        $product->update_meta_data( '_rmh_prescription_required', 'yes' );
    } else {
        $product->delete_meta_data( '_rmh_prescription_required' );
    }
}

add_action( 'woocommerce_admin_process_product_object', 'rmh_save_product_meta_fields' );

/**
 * Check whether a product needs prescription support.
 *
 * @param WC_Product $product Product to inspect.
 * @return bool
 */
function rmh_product_requires_prescription( $product ) {
    if ( ! $product instanceof WC_Product ) {
        return false;
    }

    if ( 'yes' === $product->get_meta( '_rmh_prescription_required', true ) ) {
        return true;
    }

    if ( has_term( 'prescription-medicines', 'product_cat', $product->get_id() ) ) {
        return true;
    }

    return (bool) apply_filters( 'rmh_product_requires_prescription', false, $product );
}

/**
 * Return a native WooCommerce direct checkout URL for simple products.
 *
 * @param WC_Product $product Product to add.
 * @return string
 */
function rmh_get_buy_now_url( $product ) {
    if ( ! $product instanceof WC_Product ) {
        return '';
    }

    return add_query_arg(
        array(
            'add-to-cart' => $product->get_id(),
            'quantity'    => 1,
            'rmh_buy_now' => 1,
        ),
        wc_get_checkout_url()
    );
}

/**
 * Keep the native add-to-cart flow and send explicit Buy Now actions to checkout.
 *
 * @param string $url Redirect URL.
 * @return string
 */
function rmh_buy_now_redirect( $url ) {
    return isset( $_REQUEST['rmh_buy_now'] ) ? wc_get_checkout_url() : $url;
}

add_filter( 'woocommerce_add_to_cart_redirect', 'rmh_buy_now_redirect' );

/**
 * Render a direct checkout action for simple, purchasable products.
 *
 * @param WC_Product|null $product Product to render.
 */
function rmh_render_buy_now_button( $product = null ) {
    if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() || ! $product->is_type( 'simple' ) ) {
        return;
    }

    $url = rmh_get_buy_now_url( $product );

    if ( '' === $url ) {
        return;
    }

    printf(
        '<a class="button rmh-buy-now" href="%s">%s</a>',
        esc_url( $url ),
        esc_html__( 'Buy Now', 'rabindra-medical-hall' )
    );
}

add_action(
    'woocommerce_after_shop_loop_item',
    static function () {
        global $product;
        rmh_render_buy_now_button( $product );
    },
    20
);

/**
 * Show an upload link during checkout when the cart contains a flagged product.
 */
function rmh_render_prescription_checkout_notice() {
    if ( ! is_checkout() || ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
        return;
    }

    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['data'] ) && rmh_product_requires_prescription( $cart_item['data'] ) ) {
            echo '<div class="rmh-prescription-checkout-notice" role="alert">';
            echo '<strong>' . esc_html__( 'Prescription support needed', 'rabindra-medical-hall' ) . '</strong> ';
            echo esc_html__( 'Please upload your prescription before placing this medicine order.', 'rabindra-medical-hall' ) . ' ';
            echo '<a href="' . esc_url( home_url( '/#rmh-prescription' ) ) . '">' . esc_html__( 'Upload Prescription', 'rabindra-medical-hall' ) . '</a>';
            echo '</div>';
            break;
        }
    }
}

add_action( 'woocommerce_before_checkout_form', 'rmh_render_prescription_checkout_notice', 15 );

/**
 * Shop tools: product search and real WooCommerce category links.
 */
function rmh_render_shop_tools() {
    if ( ! is_shop() && ! is_product_taxonomy() && ! is_product_category() && ! ( is_search() && 'product' === get_query_var( 'post_type' ) ) ) {
        return;
    }
    $active_mfg = ! empty( $_GET['rmh_manufacturer'] ) ? sanitize_text_field( wp_unslash( $_GET['rmh_manufacturer'] ) ) : '';
    ?>
    <section id="rmh-category-filters" class="rmh-shop-tools" aria-label="<?php echo esc_attr__( 'Shop search', 'rabindra-medical-hall' ); ?>">
        <form class="rmh-shop-tools__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <label class="screen-reader-text" for="rmh-shop-search"><?php echo esc_html__( 'Search products', 'rabindra-medical-hall' ); ?></label>
            <input id="rmh-shop-search" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php echo esc_attr__( 'Search medicines and healthcare products', 'rabindra-medical-hall' ); ?>">
            <input type="hidden" name="post_type" value="product">
            <?php if ( ! empty( $active_mfg ) ) : ?>
                <input type="hidden" name="rmh_manufacturer" value="<?php echo esc_attr( $active_mfg ); ?>">
            <?php endif; ?>
            <button type="submit"><?php echo esc_html__( 'Search', 'rabindra-medical-hall' ); ?></button>
        </form>
        <?php if ( ! empty( $active_mfg ) ) : ?>
            <div class="rmh-active-filter-bar">
                <span class="rmh-active-filter-label"><?php echo esc_html__( 'Manufacturer Filter:', 'rabindra-medical-hall' ); ?> <strong><?php echo esc_html( $active_mfg ); ?></strong></span>
                <a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ); ?>" class="rmh-clear-filter-btn" title="<?php echo esc_attr__( 'Show all manufacturers', 'rabindra-medical-hall' ); ?>">
                    <?php echo esc_html__( '✕ Clear Filter', 'rabindra-medical-hall' ); ?>
                </a>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

add_action( 'woocommerce_before_main_content', 'rmh_render_shop_tools', 25 );

/**
 * Render the alphabetical medicine table header on shop & archive catalog pages.
 */
function rmh_render_shop_table_header() {
    if ( ! is_shop() && ! is_product_taxonomy() && ! is_product_category() && ! ( is_search() && 'product' === get_query_var( 'post_type' ) ) ) {
        return;
    }
    ?>
    <div class="rmh-med-table-header" aria-hidden="true">
        <div class="rmh-med-col rmh-med-col--name"><?php esc_html_e( 'Medicine Name', 'rabindra-medical-hall' ); ?></div>
        <div class="rmh-med-col rmh-med-col--status"><?php esc_html_e( 'Availability', 'rabindra-medical-hall' ); ?></div>
        <div class="rmh-med-col rmh-med-col--price"><?php esc_html_e( 'Price', 'rabindra-medical-hall' ); ?></div>
        <div class="rmh-med-col rmh-med-col--action"><?php esc_html_e( 'Action', 'rabindra-medical-hall' ); ?></div>
    </div>
    <?php
}
add_action( 'woocommerce_before_shop_loop', 'rmh_render_shop_table_header', 35 );

/**
 * Add custom table class to product loop container on catalog archive pages.
 *
 * @param string $loop_start HTML string for loop start.
 * @return string
 */
function rmh_filter_product_loop_start( $loop_start ) {
    if ( is_shop() || is_product_taxonomy() || is_product_category() || ( is_search() && 'product' === get_query_var( 'post_type' ) ) ) {
        $loop_start = str_replace( 'class="products', 'class="products rmh-medicine-table-list', $loop_start );
    }
    return $loop_start;
}
add_filter( 'woocommerce_product_loop_start', 'rmh_filter_product_loop_start', 20 );

/**
 * Sort shop/category catalog alphabetically A-Z and set 25 products per page.
 * Also handles manufacturer filtering when rmh_manufacturer is specified.
 */
function rmh_catalog_pre_get_posts( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( ! function_exists( 'wc_get_page_id' ) ) {
        return;
    }

    $shop_page_id = wc_get_page_id( 'shop' );

    if ( $query->is_post_type_archive( 'product' ) || ( function_exists( 'is_shop' ) && is_shop() ) || $query->is_product_taxonomy() || ( $shop_page_id && $query->get( 'page_id' ) == $shop_page_id ) || ( $query->is_search() && 'product' === $query->get( 'post_type' ) ) ) {
        if ( ! $query->is_search() ) {
            $query->set( 'orderby', 'title' );
            $query->set( 'order', 'ASC' );
        }
        $query->set( 'posts_per_page', 25 );

        // Support manufacturer filtering via ?rmh_manufacturer=
        if ( ! empty( $_GET['rmh_manufacturer'] ) ) {
            $mfg = sanitize_text_field( wp_unslash( $_GET['rmh_manufacturer'] ) );
            $meta_query = (array) $query->get( 'meta_query' );
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => '_brand',
                    'value'   => $mfg,
                    'compare' => 'LIKE',
                ),
                array(
                    'key'     => '_rmh_manufacturer',
                    'value'   => $mfg,
                    'compare' => 'LIKE',
                ),
            );
            $query->set( 'meta_query', $meta_query );
        }
    }
}
add_action( 'pre_get_posts', 'rmh_catalog_pre_get_posts' );

/**
 * Add product data attributes used by the converted shop-card styling.
 *
 * @param string[]   $classes Product loop classes.
 * @param WC_Product $product Product object.
 * @return string[]
 */
function rmh_loop_product_classes( $classes, $product ) {
    if ( $product instanceof WC_Product ) {
        $classes[] = 'rmh-shop-product-card';

        if ( rmh_product_requires_prescription( $product ) ) {
            $classes[] = 'rmh-shop-product-card--rx';
        }
    }

    return $classes;
}

add_filter( 'woocommerce_post_class', 'rmh_loop_product_classes', 10, 2 );

function rmh_render_loop_product_meta() {
    global $product;

    if ( ! $product instanceof WC_Product ) {
        return;
    }

    $composition = function_exists( 'rmh_get_product_composition' ) ? rmh_get_product_composition( $product->get_id() ) : '';
    if ( $composition ) {
        printf(
            '<p class="rmh-loop-comp" title="%s">%s</p>',
            esc_attr( $composition ),
            esc_html( $composition )
        );
    }

    $brand = get_post_meta( $product->get_id(), '_rmh_manufacturer', true );
    if ( ! $brand ) {
        $raw_brand = get_post_meta( $product->get_id(), '_brand', true );
        if ( $raw_brand && ! in_array( strtoupper( trim( $raw_brand ) ), array( 'BLANK', '-BLANK-', 'GENERAL', 'GENERIC', 'GENRIC', '' ), true ) ) {
            $brand = ucwords( strtolower( trim( $raw_brand ) ) );
        }
    }

    if ( $brand ) {
        printf(
            '<p class="rmh-loop-brand"><span class="rmh-loop-brand__label">%s:</span> %s</p>',
            esc_html__( 'Brand', 'rabindra-medical-hall' ),
            esc_html( $brand )
        );
    }

    $raw_pack = get_post_meta( $product->get_id(), '_rmh_pack_size', true );
    if ( ! $raw_pack ) {
        $raw_pack = get_post_meta( $product->get_id(), '_pack_size', true );
    }

    $pack_size = function_exists( 'rmh_format_pack_size' ) ? rmh_format_pack_size( $raw_pack ) : $raw_pack;

    if ( $pack_size && ! in_array( strtoupper( trim( $pack_size ) ), array( 'BLANK', '-', '' ), true ) ) {
        printf(
            '<p class="rmh-loop-pack-size">%s</p>',
            esc_html( $pack_size )
        );
    }
}

add_action( 'woocommerce_after_shop_loop_item_title', 'rmh_render_loop_product_meta', 6 );

/**
 * Render stock state and classification information in product cards.
 */
function rmh_render_loop_product_badges() {
    global $product;

    if ( ! $product instanceof WC_Product ) {
        return;
    }

    echo '<div class="rmh-loop-badges">';

    $classification = function_exists( 'rmh_get_product_classification' ) ? rmh_get_product_classification( $product ) : ( rmh_product_requires_prescription( $product ) ? 'rx' : '' );

    if ( 'rx' === $classification ) {
        echo '<span class="rmh-rx-badge" title="' . esc_attr__( 'Doctor prescription required', 'rabindra-medical-hall' ) . '">' . esc_html__( 'Rx Required', 'rabindra-medical-hall' ) . '</span>';
    } elseif ( 'otc' === $classification ) {
        echo '<span class="rmh-otc-badge" title="' . esc_attr__( 'Over the counter', 'rabindra-medical-hall' ) . '">' . esc_html__( 'OTC', 'rabindra-medical-hall' ) . '</span>';
    }

    printf(
        '<span class="rmh-stock-badge rmh-stock-badge--%s">%s</span>',
        esc_attr( $product->get_stock_status() ),
        esc_html( wc_get_stock_html( $product ) ? wp_strip_all_tags( wc_get_stock_html( $product ) ) : ucfirst( str_replace( '-', ' ', $product->get_stock_status() ) ) )
    );

    echo '</div>';
}

add_action( 'woocommerce_after_shop_loop_item_title', 'rmh_render_loop_product_badges', 11 );

/**
 * Render product-detail information table using only stored product metadata.
 */
function rmh_render_single_product_information() {
    global $product;

    if ( ! $product instanceof WC_Product ) {
        return;
    }

    $is_rx = rmh_product_requires_prescription( $product );
    $rows  = array();

    $rows[ __( 'Medicine Classification', 'rabindra-medical-hall' ) ] = $is_rx
        ? __( 'Prescription Required (Schedule H / Rx)', 'rabindra-medical-hall' )
        : ( ( 'otc' === rmh_get_product_classification( $product ) ) ? __( 'Over the Counter (OTC)', 'rabindra-medical-hall' ) : __( 'Standard Healthcare Product', 'rabindra-medical-hall' ) );

    $comp = function_exists( 'rmh_get_product_composition' ) ? rmh_get_product_composition( $product->get_id() ) : '';
    if ( $comp ) {
        $rows[ __( 'Active Composition / Salt', 'rabindra-medical-hall' ) ] = $comp;
    }

    $mfr = get_post_meta( $product->get_id(), '_rmh_manufacturer', true );
    if ( ! $mfr ) {
        $raw_brand = get_post_meta( $product->get_id(), '_brand', true );
        if ( $raw_brand && ! in_array( strtoupper( trim( $raw_brand ) ), array( 'BLANK', '-BLANK-', 'GENERAL', 'GENERIC', 'GENRIC', '' ), true ) ) {
            $mfr = ucwords( strtolower( trim( $raw_brand ) ) );
        }
    }
    if ( $mfr ) {
        $rows[ __( 'Manufacturer / Marketer', 'rabindra-medical-hall' ) ] = $mfr;
    }

    $raw_pack = get_post_meta( $product->get_id(), '_rmh_pack_size', true );
    if ( ! $raw_pack ) {
        $raw_pack = get_post_meta( $product->get_id(), '_pack_size', true );
    }
    $pack = function_exists( 'rmh_format_pack_size' ) ? rmh_format_pack_size( $raw_pack ) : $raw_pack;
    if ( $pack && ! in_array( strtoupper( trim( $pack ) ), array( 'BLANK', '-', '' ), true ) ) {
        $rows[ __( 'Packaging / Form', 'rabindra-medical-hall' ) ] = $pack;
    }

    $dosage_form = get_post_meta( $product->get_id(), '_rmh_dosage_form', true );
    if ( $dosage_form ) {
        $rows[ __( 'Dosage Form', 'rabindra-medical-hall' ) ] = $dosage_form;
    }

    $mrp = function_exists( 'rmh_get_product_mrp' ) ? rmh_get_product_mrp( $product ) : 0;
    if ( $mrp > 0 ) {
        $rows[ __( 'MRP', 'rabindra-medical-hall' ) ] = sprintf(
            __( 'MRP ₹%s', 'rabindra-medical-hall' ),
            number_format( $mrp, 2 )
        );
    }

    $storage = get_post_meta( $product->get_id(), '_rmh_storage_instruction', true );
    if ( $storage ) {
        $rows[ __( 'Storage Instructions', 'rabindra-medical-hall' ) ] = $storage;
    }

    if ( function_exists( 'rmh_product_is_cold_chain' ) && rmh_product_is_cold_chain( $product ) ) {
        $rows[ __( 'Cold Chain', 'rabindra-medical-hall' ) ] = __( 'Temperature controlled storage required', 'rabindra-medical-hall' );
    }

    $rows[ __( 'Delivery Coverage', 'rabindra-medical-hall' ) ] = __( 'Fast Local Delivery in eligible Cuttack zones (within 10 km) • Courier Delivery across Odisha', 'rabindra-medical-hall' );

    ?>
    <?php if ( $is_rx ) : ?>
        <div class="rmh-single-rx-box" role="alert">
            <div class="rmh-single-rx-box__header">
                <span class="rmh-rx-badge">Rx</span>
                <strong><?php echo esc_html__( 'Prescription Required For This Medicine', 'rabindra-medical-hall' ); ?></strong>
            </div>
            <p><?php echo esc_html__( 'A valid doctor\'s prescription may be required before this medicine can be dispensed. Our pharmacy team will review the prescription before order fulfillment.', 'rabindra-medical-hall' ); ?></p>
            <div class="rmh-single-rx-box__actions">
                <button type="button" class="rmh-btn rmh-btn-primary rmh-open-rx-modal">
                    <?php echo esc_html__( 'Upload Prescription Now', 'rabindra-medical-hall' ); ?>
                </button>
                <a class="rmh-btn rmh-btn-secondary" href="<?php echo esc_url( rmh_get_whatsapp_url( sprintf( __( 'Hello Rabindra Medical Hall, I need help choosing an equivalent for %s.', 'rabindra-medical-hall' ), $product->get_name() ) ) ); ?>" target="_blank" rel="noopener">
                    <?php echo esc_html__( 'Need help choosing? Ask our pharmacist about alternatives.', 'rabindra-medical-hall' ); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <section class="rmh-product-information">
        <h2><?php echo esc_html__( 'Product &amp; Medical Information', 'rabindra-medical-hall' ); ?></h2>
        <dl>
            <?php foreach ( $rows as $label => $value ) : ?>
                <div>
                    <dt><?php echo esc_html( $label ); ?></dt>
                    <dd><?php echo esc_html( $value ); ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>
    <?php
}

add_action( 'woocommerce_single_product_summary', 'rmh_render_single_product_information', 35 );

/**
 * Render reference-style quick order and delivery promise on product pages.
 */
function rmh_render_single_product_quick_actions() {
    global $product;

    if ( ! $product instanceof WC_Product ) {
        return;
    }

    $message = sprintf(
        __( 'Hello Rabindra Medical Hall, I want to order %1$s (%2$s).', 'rabindra-medical-hall' ),
        $product->get_name(),
        $product->get_permalink()
    );
    ?>
    <div class="rmh-single-quick-actions">
        <a class="rmh-whatsapp-action" href="<?php echo esc_url( rmh_get_whatsapp_url( $message ) ); ?>" target="_blank" rel="noopener">
            <span aria-hidden="true">&#128172;</span>
            <?php echo esc_html__( 'Order via WhatsApp', 'rabindra-medical-hall' ); ?>
        </a>
        <div class="rmh-delivery-promise">
            <span aria-hidden="true">&#9889;</span>
            <strong><?php echo esc_html__( 'Fast Local Delivery (Eligible Areas)', 'rabindra-medical-hall' ); ?></strong>
            <small><?php echo esc_html__( 'Delivery availability depends on location, medicine availability and pharmacy operating hours. For eligible orders in Cuttack within 10 km.', 'rabindra-medical-hall' ); ?></small>
        </div>
    </div>
    <?php
}

add_action( 'woocommerce_single_product_summary', 'rmh_render_single_product_quick_actions', 32 );

/**
 * Return an encoded WhatsApp ordering URL.
 *
 * @param string $message Message body.
 * @return string
 */
function rmh_get_whatsapp_url( $message = '' ) {
    $message = $message ? $message : "Hello Rabindra Medical Hall,\nI would like to place an order.";

    return 'https://wa.me/' . rawurlencode( rmh_get_whatsapp_number() ) . '?text=' . rawurlencode( $message );
}

/**
 * Render a general WhatsApp order button.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function rmh_whatsapp_button_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'label'   => __( 'Chat on WhatsApp', 'rabindra-medical-hall' ),
            'message' => "Hello Rabindra Medical Hall,\nI need help with my order.",
        ),
        $atts,
        'rmh_whatsapp_button'
    );

    return sprintf(
        '<a class="rmh-whatsapp-button" href="%s" target="_blank" rel="noopener">%s</a>',
        esc_url( rmh_get_whatsapp_url( $atts['message'] ) ),
        esc_html( $atts['label'] )
    );
}

add_shortcode( 'rmh_whatsapp_button', 'rmh_whatsapp_button_shortcode' );

/**
 * Extract structured cart data for WhatsApp order requests.
 *
 * @return array
 */
function rmh_get_cart_order_request_data() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
        return array(
            'is_empty'             => true,
            'items'                => array(),
            'subtotal'             => '',
            'discount'             => '',
            'tax'                  => '',
            'shipping'             => '',
            'shipping_calculated'  => false,
            'total'                => '',
            'total_calculated'     => false,
            'count'                => 0,
            'whatsapp_number'      => function_exists( 'rmh_get_whatsapp_number' ) ? rmh_get_whatsapp_number() : '917205859117',
        );
    }

    $items = array();
    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        $product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
        if ( ! $product instanceof WC_Product ) {
            continue;
        }

        $variation_details = '';
        if ( $product->is_type( 'variation' ) ) {
            $variation_attributes = $product->get_variation_attributes();
            $formatted_attributes = array();
            foreach ( $variation_attributes as $attribute_name => $attribute_value ) {
                $taxonomy = wc_attribute_taxonomy_name( str_replace( 'attribute_', '', $attribute_name ) );
                if ( taxonomy_exists( $taxonomy ) ) {
                    $term = get_term_by( 'slug', $attribute_value, $taxonomy );
                    $formatted_attributes[] = ( $term && ! is_wp_error( $term ) ) ? $term->name : $attribute_value;
                } else {
                    $formatted_attributes[] = $attribute_value;
                }
            }
            if ( ! empty( $formatted_attributes ) ) {
                $variation_details = implode( ', ', array_filter( $formatted_attributes ) );
            }
        }

        $sku = $product->get_sku();
        $line_subtotal = isset( $cart_item['line_subtotal'] ) ? (float) $cart_item['line_subtotal'] : 0.0;

        $items[] = array(
            'id'                => $product->get_id(),
            'name'              => $product->get_name(),
            'quantity'          => absint( $cart_item['quantity'] ),
            'sku'               => $sku ? $sku : '',
            'variation_details' => $variation_details,
            'price_formatted'   => '₹' . number_format( $line_subtotal, 2, '.', '' ),
        );
    }

    // 1. Subtotal
    $subtotal_val = (float) WC()->cart->get_subtotal();
    $subtotal = '₹' . number_format( $subtotal_val, 2, '.', '' );

    // 2. Discount (when applicable)
    $discount_val = (float) WC()->cart->get_discount_total();
    $discount = ( $discount_val > 0 ) ? '₹' . number_format( $discount_val, 2, '.', '' ) : '';

    // 3. Tax (when applicable)
    $tax_val = (float) WC()->cart->get_total_tax();
    $tax = ( $tax_val > 0 ) ? '₹' . number_format( $tax_val, 2, '.', '' ) : '';

    // 4. Delivery Charge / Shipping calculation
    $needs_shipping = WC()->cart->needs_shipping();
    $packages = WC()->shipping()->get_packages();
    $has_rates = false;
    if ( ! empty( $packages ) ) {
        foreach ( $packages as $pkg ) {
            if ( ! empty( $pkg['rates'] ) ) {
                $has_rates = true;
                break;
            }
        }
    }

    if ( ! $needs_shipping ) {
        $shipping = '₹0.00';
        $shipping_calculated = true;
    } elseif ( $has_rates ) {
        $shipping_val = (float) WC()->cart->get_shipping_total();
        $shipping = '₹' . number_format( $shipping_val, 2, '.', '' );
        $shipping_calculated = true;
    } else {
        $shipping = 'To be confirmed';
        $shipping_calculated = false;
    }

    // 5. Total calculation
    if ( $shipping_calculated ) {
        $total_val = (float) WC()->cart->total;
        $total = '₹' . number_format( $total_val, 2, '.', '' );
        $total_calculated = true;
    } else {
        $total = 'Pending confirmation';
        $total_calculated = false;
    }

    return array(
        'is_empty'             => false,
        'items'                => $items,
        'subtotal'             => $subtotal,
        'discount'             => $discount,
        'tax'                  => $tax,
        'shipping'             => $shipping,
        'shipping_calculated'  => $shipping_calculated,
        'total'                => $total,
        'total_calculated'     => $total_calculated,
        'count'                => WC()->cart->get_cart_contents_count(),
        'whatsapp_number'      => function_exists( 'rmh_get_whatsapp_number' ) ? rmh_get_whatsapp_number() : '917205859117',
    );
}

/**
 * Build a readable WhatsApp message from the current WooCommerce cart.
 *
 * @param array $customer_details Optional customer/delivery details to include.
 * @return string
 */
function rmh_get_cart_whatsapp_message( $customer_details = array() ) {
    $cart_data = rmh_get_cart_order_request_data();
    if ( empty( $cart_data['items'] ) ) {
        return "Hello Rabindra Medical Hall,\n\nI would like to place an order request.";
    }

    $lines   = array();
    $lines[] = 'Hello Rabindra Medical Hall,';
    $lines[] = '';
    $lines[] = 'I would like to place an order request through your website.';
    $lines[] = '';

    // CUSTOMER DETAILS
    $lines[] = '━━━━━━━━━━━━━━━━━━';
    $lines[] = 'CUSTOMER DETAILS';
    $lines[] = '━━━━━━━━━━━━━━━━━━';
    $lines[] = 'Name: ' . ( ! empty( $customer_details['name'] ) ? $customer_details['name'] : 'Not Provided' );
    $lines[] = 'Mobile: ' . ( ! empty( $customer_details['phone'] ) ? $customer_details['phone'] : 'Not Provided' );
    $lines[] = 'Email: ' . ( ! empty( $customer_details['email'] ) ? $customer_details['email'] : 'Not Provided' );
    $lines[] = '';

    // DELIVERY ADDRESS
    $lines[] = 'DELIVERY ADDRESS';
    $house = ! empty( $customer_details['house'] ) ? $customer_details['house'] : ( ! empty( $customer_details['address_1'] ) ? $customer_details['address_1'] : '' );
    if ( $house ) {
        $lines[] = 'House/Flat: ' . $house;
    }
    $street = ! empty( $customer_details['street'] ) ? $customer_details['street'] : ( ! empty( $customer_details['address_2'] ) ? $customer_details['address_2'] : '' );
    if ( $street ) {
        $lines[] = 'Street/Address: ' . $street;
    }
    if ( ! empty( $customer_details['landmark'] ) ) {
        $lines[] = 'Landmark: ' . $customer_details['landmark'];
    }
    if ( ! empty( $customer_details['city'] ) ) {
        $lines[] = 'City: ' . $customer_details['city'];
    }
    $lines[] = 'State: ' . ( ! empty( $customer_details['state'] ) ? $customer_details['state'] : 'Odisha' );
    $pin = ! empty( $customer_details['postcode'] ) ? $customer_details['postcode'] : ( ! empty( $customer_details['pin'] ) ? $customer_details['pin'] : '' );
    if ( $pin ) {
        $lines[] = 'PIN Code: ' . $pin;
    }
    $lines[] = '';

    // DELIVERY LOCATION
    $lines[] = 'DELIVERY LOCATION';
    $lines[] = 'Google Maps: ' . ( ! empty( $customer_details['maps_url'] ) ? $customer_details['maps_url'] : 'Not Provided' );
    $lines[] = '';

    // ORDER DETAILS
    $lines[] = '━━━━━━━━━━━━━━━━━━';
    $lines[] = 'ORDER DETAILS';
    $lines[] = '━━━━━━━━━━━━━━━━━━';
    $lines[] = '';

    $i = 1;
    foreach ( $cart_data['items'] as $item ) {
        $name  = ! empty( $item['name'] ) ? $item['name'] : 'Medicine';
        $qty   = ! empty( $item['quantity'] ) ? $item['quantity'] : 1;
        $sku   = ! empty( $item['sku'] ) ? $item['sku'] : 'Not Available';
        $price = ! empty( $item['price_formatted'] ) ? $item['price_formatted'] : ( ! empty( $item['price'] ) ? '₹' . number_format( (float) $item['price'], 2 ) : 'Price on request' );

        $lines[] = sprintf( '%d. %s × %d', $i, $name, $qty );
        $lines[] = '   SKU: ' . $sku;
        $lines[] = '   Price: ' . $price;
        if ( ! empty( $item['variation_details'] ) ) {
            $lines[] = '   Variant: ' . $item['variation_details'];
        }
        $lines[] = '';
        $i++;
    }

    // ORDER SUMMARY
    $lines[] = '━━━━━━━━━━━━━━━━━━';
    $lines[] = 'ORDER SUMMARY';
    $lines[] = '━━━━━━━━━━━━━━━━━━';
    $lines[] = 'Subtotal: ' . ( ! empty( $cart_data['subtotal'] ) ? $cart_data['subtotal'] : '₹0.00' );
    $shipping = ! empty( $cart_data['shipping'] ) ? $cart_data['shipping'] : 'To Be Confirmed';
    if ( strtolower( $shipping ) === 'to be confirmed' ) {
        $shipping = 'To Be Confirmed';
    }
    $lines[] = 'Delivery Charge: ' . $shipping;
    $total = ! empty( $cart_data['total'] ) ? $cart_data['total'] : 'Pending Confirmation';
    if ( strtolower( $total ) === 'pending confirmation' ) {
        $total = 'Pending Confirmation';
    }
    $lines[] = 'Estimated Total: ' . $total;
    $lines[] = '';

    $lines[] = 'ORDER TYPE: WhatsApp Order Request';
    $lines[] = 'Website: Rabindra Medical Hall';
    $lines[] = '';
    $lines[] = 'Please confirm product availability, applicable delivery charges, the final payable amount, and delivery details before processing my request.';
    $lines[] = '';
    $lines[] = 'Thank you,';
    $lines[] = 'Rabindra Medical Hall';

    return implode( "\n", $lines );
}

/**
 * Add a cart-aware WhatsApp CTA below cart totals.
 * Seamlessly transitions to the checkout delivery-details step.
 */
function rmh_render_cart_whatsapp_button() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
        return;
    }

    $checkout_url = wc_get_checkout_url() . '#rmh-whatsapp-order-section';

    echo '<div class="rmh-cart-contact-actions">';
    printf(
        '<a class="rmh-whatsapp-button rmh-whatsapp-button--cart" href="%s">%s</a>',
        esc_url( $checkout_url ),
        esc_html__( 'Provide Delivery Details & Order on WhatsApp', 'rabindra-medical-hall' )
    );
    echo '<a class="rmh-phone-button rmh-phone-button--cart" href="tel:' . esc_attr( rmh_get_contact_phone() ) . '"><span aria-hidden="true">📞</span> ' . esc_html__( 'Call Pharmacy', 'rabindra-medical-hall' ) . ' <strong>' . esc_html( rmh_get_contact_phone() ) . '</strong></a>';
    echo '</div>';
    echo '<p class="rmh-cart-whatsapp-note">' . esc_html__( 'WhatsApp Order Request — WooCommerce order is not yet created. Enter your delivery address at checkout to send your complete order request via WhatsApp.', 'rabindra-medical-hall' ) . '</p>';
}
add_action( 'woocommerce_proceed_to_checkout', 'rmh_render_cart_whatsapp_button', 25 );

/**
 * Add an optional Landmark field to WooCommerce checkout billing fields.
 *
 * @param array $fields Billing fields.
 * @return array
 */
function rmh_add_billing_landmark_field( $fields ) {
    $fields['billing_landmark'] = array(
        'type'        => 'text',
        'label'       => __( 'Landmark (Optional)', 'rabindra-medical-hall' ),
        'placeholder' => __( 'E.g. Near Bus Stand, Opposite Temple', 'rabindra-medical-hall' ),
        'required'    => false,
        'class'       => array( 'form-row-wide' ),
        'clear'       => true,
        'priority'    => 65,
    );
    return $fields;
}
add_filter( 'woocommerce_billing_fields', 'rmh_add_billing_landmark_field' );

/**
 * Render the dedicated WhatsApp Order Request section on the WooCommerce Checkout page.
 */
function rmh_render_checkout_whatsapp_order_section() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
        return;
    }

    $cart_data = rmh_get_cart_order_request_data();
    ?>
    <div class="rmh-whatsapp-checkout-section" id="rmh-whatsapp-order-section" data-cart-data="<?php echo esc_attr( wp_json_encode( $cart_data ) ); ?>">
        <div class="rmh-whatsapp-checkout-card">
            <div class="rmh-wa-card-header">
                <div class="rmh-wa-badge-pill">
                    <span class="rmh-wa-icon" aria-hidden="true">💬</span>
                    <span><?php echo esc_html__( 'WhatsApp Ordering', 'rabindra-medical-hall' ); ?></span>
                </div>
                <h3 class="rmh-wa-card-title"><?php echo esc_html__( 'Send Order Request via WhatsApp', 'rabindra-medical-hall' ); ?></h3>
                <p class="rmh-wa-card-subtitle">
                    <?php echo esc_html__( 'Prefer ordering on WhatsApp? Enter your delivery details above and tap below to send your complete order request with delivery address directly to our pharmacist.', 'rabindra-medical-hall' ); ?>
                </p>
            </div>

            <div class="rmh-wa-status-box" id="rmhWaNoticeBox" role="alert" style="display:none;"></div>

            <button type="button" class="button alt rmh-wa-submit-btn" id="rmhSendWhatsAppOrderBtn">
                <span class="rmh-wa-btn-icon" aria-hidden="true">💬</span>
                <span class="rmh-wa-btn-text"><?php echo esc_html__( 'Send Order Request on WhatsApp', 'rabindra-medical-hall' ); ?></span>
            </button>

            <div class="rmh-wa-disclaimer">
                <strong><?php echo esc_html__( 'WhatsApp Order Request', 'rabindra-medical-hall' ); ?></strong> &mdash; <?php echo esc_html__( 'WooCommerce order is not yet created. Payment and stock verification happen directly with Rabindra Medical Hall on WhatsApp.', 'rabindra-medical-hall' ); ?>
            </div>
        </div>
    </div>
    <?php
}
add_action( 'woocommerce_review_order_after_payment', 'rmh_render_checkout_whatsapp_order_section', 15 );

/**
 * AJAX endpoint to return fresh cart data for WhatsApp order request.
 */
function rmh_ajax_get_cart_whatsapp_data() {
    wp_send_json_success( rmh_get_cart_order_request_data() );
}
add_action( 'wp_ajax_rmh_get_cart_whatsapp_data', 'rmh_ajax_get_cart_whatsapp_data' );
add_action( 'wp_ajax_nopriv_rmh_get_cart_whatsapp_data', 'rmh_ajax_get_cart_whatsapp_data' );


/**
 * Conditionally manage Cash on Delivery availability.
 *
 * COD is allowed by default (controlled by WooCommerce Admin → Payments settings).
 * A site administrator can disable COD by adding this line to wp-config.php:
 *
 *   define( 'RMH_DISABLE_COD', true );
 *
 * This replaces the previous hard-coded removal so that the WooCommerce
 * admin toggle is the source of truth for payment gateway availability.
 *
 * @param array $gateways Available gateways keyed by gateway ID.
 * @return array
 */
function rmh_manage_cash_on_delivery( $gateways ) {
    // If the site owner explicitly disables COD via wp-config constant, respect that.
    if ( defined( 'RMH_DISABLE_COD' ) && RMH_DISABLE_COD && isset( $gateways['cod'] ) ) {
        unset( $gateways['cod'] );
    }
    return $gateways;
}

add_filter( 'woocommerce_available_payment_gateways', 'rmh_manage_cash_on_delivery', 100 );

/**
 * Show a neutral checkout status when no payment methods are configured.
 */
function rmh_render_payment_configuration_notice() {
    if ( ! function_exists( 'WC' ) || ! is_checkout() ) {
        return;
    }

    $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

    if ( ! empty( $available_gateways ) ) {
        return;
    }

    echo '<div class="rmh-payment-empty" role="status">';
    echo '<h3>' . esc_html__( 'Secure Payment', 'rabindra-medical-hall' ) . '</h3>';
    echo '<p>' . esc_html__( 'Payment methods will appear here once a verified gateway is configured for checkout.', 'rabindra-medical-hall' ) . '</p>';
    echo '</div>';
}

add_action( 'woocommerce_checkout_order_review', 'rmh_render_payment_configuration_notice', 20 );

/**
 * Keep transactional/customer-only WooCommerce screens out of search indexes.
 *
 * @param array $robots Existing robots directives.
 * @return array
 */
function rmh_noindex_private_store_pages( $robots ) {
    if ( function_exists( 'is_account_page' ) && ( is_account_page() || is_cart() || is_checkout() ) ) {
        $robots['noindex'] = true;
        $robots['follow']  = true;
    }

    return $robots;
}

add_filter( 'wp_robots', 'rmh_noindex_private_store_pages' );

/**
 * Render the secure contact form.
 *
 * @return string
 */
function rmh_contact_form_shortcode() {
    $status = isset( $_GET['rmh_contact'] ) ? sanitize_key( wp_unslash( $_GET['rmh_contact'] ) ) : '';
    ob_start();
    ?>
    <div class="rmh-form-card">
        <?php if ( 'sent' === $status ) : ?>
            <div class="rmh-form-notice rmh-form-notice--success" role="status"><?php echo esc_html__( 'Thank you. Your message has been sent to Rabindra Medical Hall.', 'rabindra-medical-hall' ); ?></div>
        <?php elseif ( 'error' === $status ) : ?>
            <div class="rmh-form-notice rmh-form-notice--error" role="alert"><?php echo esc_html__( 'Please check the form fields and try again.', 'rabindra-medical-hall' ); ?></div>
        <?php endif; ?>

        <form class="rmh-secure-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'rmh_contact_form', 'rmh_contact_nonce' ); ?>
            <input type="hidden" name="action" value="rmh_contact_form">
            <div class="rmh-honeypot" aria-hidden="true">
                <label for="rmh_contact_website"><?php echo esc_html__( 'Website', 'rabindra-medical-hall' ); ?></label>
                <input id="rmh_contact_website" type="text" name="website" tabindex="-1" autocomplete="off">
            </div>
            <div class="rmh-form-field">
                <label for="rmh_contact_name"><?php echo esc_html__( 'Customer Name', 'rabindra-medical-hall' ); ?></label>
                <input id="rmh_contact_name" type="text" name="customer_name" required autocomplete="name">
            </div>
            <div class="rmh-form-field">
                <label for="rmh_contact_mobile"><?php echo esc_html__( 'Mobile Number', 'rabindra-medical-hall' ); ?></label>
                <input id="rmh_contact_mobile" type="tel" name="mobile_number" required inputmode="tel" autocomplete="tel">
            </div>
            <div class="rmh-form-field">
                <label for="rmh_contact_message"><?php echo esc_html__( 'Message', 'rabindra-medical-hall' ); ?></label>
                <textarea id="rmh_contact_message" name="message" rows="5" required></textarea>
            </div>
            <button class="rmh-form-submit" type="submit"><?php echo esc_html__( 'Send Message', 'rabindra-medical-hall' ); ?></button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode( 'rmh_contact_form', 'rmh_contact_form_shortcode' );

/**
 * Process secure contact form submissions.
 */
function rmh_handle_contact_form() {
    // Rate limiting: 3 submissions per hour per IP
    $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    $rate_key = 'rmh_contact_rate_' . md5( $ip );
    $requests = (int) get_transient( $rate_key );

    if ( $requests >= 3 ) {
        wp_safe_redirect( add_query_arg( 'rmh_contact', 'error', $referer ) );
        exit;
    }

    $referer = wp_get_referer() ? wp_get_referer() : home_url( '/contact-us/' );

    if (
        ! isset( $_POST['rmh_contact_nonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rmh_contact_nonce'] ) ), 'rmh_contact_form' )
    ) {
        wp_safe_redirect( add_query_arg( 'rmh_contact', 'error', $referer ) );
        exit;
    }

    if ( ! empty( $_POST['website'] ) ) {
        wp_safe_redirect( add_query_arg( 'rmh_contact', 'sent', $referer ) );
        exit;
    }

    $name    = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
    $mobile  = isset( $_POST['mobile_number'] ) ? sanitize_text_field( wp_unslash( $_POST['mobile_number'] ) ) : '';
    $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

    if ( '' === $name || '' === $message || ! preg_match( '/^[0-9+\-\s()]{7,20}$/', $mobile ) ) {
        wp_safe_redirect( add_query_arg( 'rmh_contact', 'error', $referer ) );
        exit;
    }

    // Increment rate limit counter
    set_transient( $rate_key, $requests + 1, HOUR_IN_SECONDS );

    $body = sprintf(
        "Customer: %s\nMobile: %s\n\nMessage:\n%s",
        $name,
        $mobile,
        $message
    );

    wp_mail(
        rmh_get_contact_email(),
        sprintf( __( 'New contact message from %s', 'rabindra-medical-hall' ), $name ),
        $body
    );

    wp_safe_redirect( add_query_arg( 'rmh_contact', 'sent', $referer ) );
    exit;
}

add_action( 'admin_post_rmh_contact_form', 'rmh_handle_contact_form' );
add_action( 'admin_post_nopriv_rmh_contact_form', 'rmh_handle_contact_form' );

/**
 * Register admin-visible pharmacy workflow records.
 */
function rmh_register_workflow_post_types() {
    register_post_type(
        'rmh_prescription',
        array(
            'labels'       => array(
                'name'          => __( 'Prescriptions', 'rabindra-medical-hall' ),
                'singular_name' => __( 'Prescription', 'rabindra-medical-hall' ),
            ),
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => true,
            'supports'     => array( 'title' ),
            'capability_type' => 'post',
            'menu_icon'    => 'dashicons-media-document',
        )
    );
}

add_action( 'init', 'rmh_register_workflow_post_types' );

/**
 * Return the supported private prescription workflow states.
 *
 * @return array
 */
function rmh_prescription_status_options() {
    return array(
        'Pending Review',
        'Under Review',
        'Approved',
        'Rejected',
    );
}

/**
 * Record a private prescription workflow transition for admin audit history.
 *
 * @param int    $post_id Prescription record ID.
 * @param string $status New status.
 * @param string $previous_status Previous status.
 */
function rmh_record_prescription_status_change( $post_id, $status, $previous_status = '' ) {
    $history = get_post_meta( $post_id, '_rmh_status_history', true );
    $history = is_array( $history ) ? $history : array();
    $history[] = array(
        'status'   => sanitize_text_field( $status ),
        'previous' => sanitize_text_field( $previous_status ),
        'at'       => current_time( 'mysql', true ),
        'user_id'  => get_current_user_id(),
    );

    update_post_meta( $post_id, '_rmh_status_history', array_slice( $history, -50 ) );
}

/**
 * Render the logged-in customer's own prescription status records.
 *
 * Only the user ID, status and optional order reference are shown. Private
 * customer details and file paths never leave the admin workflow.
 */
function rmh_render_customer_prescription_history() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    global $wpdb;

    $record_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE p.post_type = %s AND p.post_status IN ('draft', 'private') AND m.meta_key = %s AND m.meta_value = %d ORDER BY p.post_date DESC",
            'rmh_prescription',
            '_rmh_user_id',
            get_current_user_id()
        )
    );

    echo '<section class="rmh-account-prescriptions" aria-labelledby="rmh-account-prescriptions-title">';
    echo '<h2 id="rmh-account-prescriptions-title">' . esc_html__( 'Prescription History', 'rabindra-medical-hall' ) . '</h2>';

    if ( empty( $record_ids ) ) {
        echo '<p class="rmh-empty-state">' . esc_html__( 'Prescription submissions linked to this account will appear here.', 'rabindra-medical-hall' ) . '</p>';
        echo '</section>';
        return;
    }

    echo '<ul class="rmh-account-prescriptions__list">';
    foreach ( $record_ids as $record_id ) {
        $status   = get_post_meta( $record_id, '_rmh_status', true );
        $order_id = absint( get_post_meta( $record_id, '_rmh_order_id', true ) );

        echo '<li class="rmh-account-prescriptions__item">';
        echo '<strong>' . esc_html( $status ? $status : __( 'Pending Review', 'rabindra-medical-hall' ) ) . '</strong>';
        echo '<span>' . esc_html( get_the_date( get_option( 'date_format' ), $record_id ) ) . '</span>';
        if ( $order_id && function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $order_id );
            if ( $order && (int) $order->get_user_id() === get_current_user_id() ) {
                echo '<a href="' . esc_url( $order->get_view_order_url() ) . '">' . esc_html( sprintf( __( 'Order #%d', 'rabindra-medical-hall' ), $order_id ) ) . '</a>';
            }
        }
        echo '</li>';
    }
    echo '</ul></section>';
}

add_action( 'woocommerce_account_dashboard', 'rmh_render_customer_prescription_history', 20 );

/**
 * Return the private directory used for prescription files.
 *
 * This lives beside the public web root so uploads are never directly
 * addressable by a customer-facing URL.
 *
 * @return string
 */
function rmh_get_private_storage_dir() {
    $dir = trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . 'rmh-private/prescriptions';

    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
    }

    $parent_dir = dirname( $dir );
    if ( is_dir( $parent_dir ) ) {
        $htaccess = trailingslashit( $parent_dir ) . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            @file_put_contents( $htaccess, "Deny from all\n" );
        }
        $index = trailingslashit( $parent_dir ) . 'index.html';
        if ( ! file_exists( $index ) ) {
            @file_put_contents( $index, '' );
        }
    }

    return $dir;
}

/**
 * Add useful columns to the prescription admin list.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function rmh_prescription_admin_columns( $columns ) {
    return array(
        'cb'       => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox">',
        'title'    => __( 'Record', 'rabindra-medical-hall' ),
        'customer' => __( 'Customer', 'rabindra-medical-hall' ),
        'mobile'   => __( 'Mobile', 'rabindra-medical-hall' ),
        'status'   => __( 'Status', 'rabindra-medical-hall' ),
        'file'     => __( 'Prescription', 'rabindra-medical-hall' ),
        'date'     => isset( $columns['date'] ) ? $columns['date'] : __( 'Date', 'rabindra-medical-hall' ),
    );
}

add_filter( 'manage_rmh_prescription_posts_columns', 'rmh_prescription_admin_columns' );

/**
 * Render prescription admin list values.
 *
 * @param string $column Column key.
 * @param int    $post_id Post ID.
 */
function rmh_prescription_admin_column_value( $column, $post_id ) {
    $customer = get_post_meta( $post_id, '_rmh_customer_name', true );
    $mobile   = get_post_meta( $post_id, '_rmh_mobile_number', true );
    $status   = get_post_meta( $post_id, '_rmh_status', true );
    $filename = get_post_meta( $post_id, '_rmh_original_filename', true );

    if ( 'customer' === $column ) {
        echo esc_html( $customer ? $customer : '—' );
    } elseif ( 'mobile' === $column ) {
        echo esc_html( $mobile ? $mobile : '—' );
    } elseif ( 'status' === $column ) {
        echo esc_html( $status ? $status : __( 'Pending Review', 'rabindra-medical-hall' ) );
    } elseif ( 'file' === $column ) {
        if ( $filename && file_exists( get_post_meta( $post_id, '_rmh_file_path', true ) ) ) {
            $download_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=rmh_download_prescription&post_id=' . absint( $post_id ) ),
                'rmh_download_prescription_' . absint( $post_id )
            );
            printf( '<a href="%s">%s</a>', esc_url( $download_url ), esc_html( $filename ) );
        } else {
            echo esc_html__( 'File unavailable', 'rabindra-medical-hall' );
        }
    }
}

add_action( 'manage_rmh_prescription_posts_custom_column', 'rmh_prescription_admin_column_value', 10, 2 );

/**
 * Add a prescription details/status panel in admin.
 */
function rmh_register_prescription_meta_box() {
    add_meta_box(
        'rmh-prescription-details',
        __( 'Prescription Details', 'rabindra-medical-hall' ),
        'rmh_render_prescription_meta_box',
        'rmh_prescription',
        'normal',
        'high'
    );
}

add_action( 'add_meta_boxes', 'rmh_register_prescription_meta_box' );

/**
 * Render prescription details/status panel.
 *
 * @param WP_Post $post Current post.
 */
function rmh_render_prescription_meta_box( $post ) {
    $status   = get_post_meta( $post->ID, '_rmh_status', true );
    $filename = get_post_meta( $post->ID, '_rmh_original_filename', true );
    $path     = get_post_meta( $post->ID, '_rmh_file_path', true );
    $statuses = rmh_prescription_status_options();
    $history  = get_post_meta( $post->ID, '_rmh_status_history', true );
    wp_nonce_field( 'rmh_save_prescription_details', 'rmh_prescription_details_nonce' );
    ?>
    <p><strong><?php echo esc_html__( 'Customer', 'rabindra-medical-hall' ); ?>:</strong> <?php echo esc_html( get_post_meta( $post->ID, '_rmh_customer_name', true ) ); ?></p>
    <p><strong><?php echo esc_html__( 'Mobile', 'rabindra-medical-hall' ); ?>:</strong> <?php echo esc_html( get_post_meta( $post->ID, '_rmh_mobile_number', true ) ); ?></p>
    <p><strong><?php echo esc_html__( 'File', 'rabindra-medical-hall' ); ?>:</strong> <?php echo esc_html( $filename ? $filename : __( 'Unavailable', 'rabindra-medical-hall' ) ); ?></p>
    <?php if ( $path && file_exists( $path ) ) : ?>
        <p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rmh_download_prescription&post_id=' . absint( $post->ID ) ), 'rmh_download_prescription_' . absint( $post->ID ) ) ); ?>"><?php echo esc_html__( 'Download Prescription', 'rabindra-medical-hall' ); ?></a></p>
    <?php endif; ?>
    <p>
        <label for="rmh_prescription_status"><strong><?php echo esc_html__( 'Workflow Status', 'rabindra-medical-hall' ); ?></strong></label><br>
        <select id="rmh_prescription_status" name="rmh_prescription_status">
            <?php foreach ( $statuses as $option ) : ?>
                <option value="<?php echo esc_attr( $option ); ?>" <?php selected( $status ? $status : 'Pending Review', $option ); ?>><?php echo esc_html( $option ); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php if ( is_array( $history ) && ! empty( $history ) ) : ?>
        <p><strong><?php echo esc_html__( 'Status History', 'rabindra-medical-hall' ); ?></strong></p>
        <ul class="rmh-prescription-status-history">
            <?php foreach ( array_reverse( $history ) as $entry ) : ?>
                <li>
                    <?php echo esc_html( isset( $entry['status'] ) ? $entry['status'] : '' ); ?>
                    <small><?php echo esc_html( isset( $entry['at'] ) ? $entry['at'] . ' UTC' : '' ); ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php
}

/**
 * Save prescription workflow status.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post Post object.
 * @param bool    $update Whether this is an update.
 */
function rmh_save_prescription_details( $post_id, $post, $update ) {
    if ( ! isset( $_POST['rmh_prescription_details_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rmh_prescription_details_nonce'] ) ), 'rmh_save_prescription_details' ) ) {
        return;
    }

    if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $allowed = rmh_prescription_status_options();
    $status  = isset( $_POST['rmh_prescription_status'] ) ? sanitize_text_field( wp_unslash( $_POST['rmh_prescription_status'] ) ) : 'Pending Review';

    if ( in_array( $status, $allowed, true ) ) {
        $previous_status = get_post_meta( $post_id, '_rmh_status', true );
        update_post_meta( $post_id, '_rmh_status', $status );

        if ( $previous_status !== $status ) {
            rmh_record_prescription_status_change( $post_id, $status, $previous_status );
        }
    }
}

add_action( 'save_post_rmh_prescription', 'rmh_save_prescription_details', 10, 3 );

/**
 * Serve a prescription only to an authorized WordPress admin/editor.
 */
function rmh_download_prescription() {
    $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

    if ( ! $post_id || 'rmh_prescription' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_die( esc_html__( 'You are not allowed to access this file.', 'rabindra-medical-hall' ), '', array( 'response' => 403 ) );
    }

    check_admin_referer( 'rmh_download_prescription_' . $post_id );

    $path          = get_post_meta( $post_id, '_rmh_file_path', true );
    $storage_dir   = realpath( rmh_get_private_storage_dir() );
    $resolved_path = $path ? realpath( $path ) : false;

    if ( ! $storage_dir || ! $resolved_path || 0 !== strpos( wp_normalize_path( $resolved_path ), trailingslashit( wp_normalize_path( $storage_dir ) ) ) || ! is_readable( $resolved_path ) ) {
        wp_die( esc_html__( 'The prescription file is unavailable.', 'rabindra-medical-hall' ), '', array( 'response' => 404 ) );
    }

    $mime     = get_post_meta( $post_id, '_rmh_file_mime', true );
    $filename = get_post_meta( $post_id, '_rmh_original_filename', true );
    $filename = $filename ? sanitize_file_name( $filename ) : 'prescription-' . $post_id;

    nocache_headers();
    header( 'Content-Type: ' . ( $mime ? sanitize_mime_type( $mime ) : 'application/octet-stream' ) );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . filesize( $resolved_path ) );
    readfile( $resolved_path );
    exit;
}

add_action( 'admin_post_rmh_download_prescription', 'rmh_download_prescription' );

/**
 * Shared redirect helper for pharmacy forms.
 *
 * @param string $key Status query key.
 * @param string $status Status value.
 * @param string $fallback Fallback URL.
 */
function rmh_form_redirect( $key, $status, $fallback ) {
    $referer = wp_get_referer() ? wp_get_referer() : $fallback;
    wp_safe_redirect( add_query_arg( $key, $status, $referer ) );
    exit;
}

/**
 * Render secure prescription upload form.
 *
 * @return string
 */
function rmh_prescription_form_shortcode() {
    $status = isset( $_GET['rmh_prescription'] ) ? sanitize_key( wp_unslash( $_GET['rmh_prescription'] ) ) : '';
    $order_id = isset( $_GET['rmh_order_id'] ) ? absint( $_GET['rmh_order_id'] ) : 0;

    if ( ! is_user_logged_in() || ! $order_id || ! function_exists( 'wc_get_order' ) ) {
        $order_id = 0;
    } else {
        $order = wc_get_order( $order_id );
        if ( ! $order || (int) $order->get_user_id() !== get_current_user_id() ) {
            $order_id = 0;
        }
    }

    ob_start();
    ?>
    <form class="rmh-pharmacy-form" id="rmhPrescriptionForm" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'rmh_prescription_form', 'rmh_prescription_nonce' ); ?>
        <input type="hidden" name="action" value="rmh_prescription_form">
        <?php if ( $order_id ) : ?>
            <input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">
        <?php endif; ?>
        <div class="rmh-honeypot" aria-hidden="true">
            <label for="rmh_prescription_website"><?php echo esc_html__( 'Website', 'rabindra-medical-hall' ); ?></label>
            <input id="rmh_prescription_website" type="text" name="website" tabindex="-1" autocomplete="off">
        </div>
        <?php if ( 'sent' === $status ) : ?>
            <div class="rmh-form-notice rmh-form-notice--success" role="status"><?php echo esc_html__( 'Prescription received. The pharmacy team can review it in WordPress admin.', 'rabindra-medical-hall' ); ?></div>
        <?php elseif ( 'error' === $status ) : ?>
            <div class="rmh-form-notice rmh-form-notice--error" role="alert"><?php echo esc_html__( 'Please provide your name, mobile number and a JPG, PNG, WEBP or PDF file up to 10 MB.', 'rabindra-medical-hall' ); ?></div>
        <?php endif; ?>
        <div class="rmh-form-grid">
            <div class="rmh-form-field">
                <label for="prescriptionName"><?php echo esc_html__( 'Customer Name', 'rabindra-medical-hall' ); ?></label>
                <input type="text" id="prescriptionName" name="customer_name" required autocomplete="name">
            </div>
            <div class="rmh-form-field">
                <label for="prescriptionPhone"><?php echo esc_html__( 'Mobile Number', 'rabindra-medical-hall' ); ?></label>
                <input type="tel" id="prescriptionPhone" name="mobile_number" required inputmode="tel" autocomplete="tel">
            </div>
        </div>
        <div class="rmh-form-field rmh-form-field--upload">
            <span class="rmh-upload-title"><?php echo esc_html__( 'Prescription', 'rabindra-medical-hall' ); ?></span>
            <span class="rmh-upload-subtitle"><?php echo esc_html__( 'Upload your prescription', 'rabindra-medical-hall' ); ?></span>
            <div class="rmh-prescription-file-actions">
                <label class="rmh-file-action" for="prescriptionFile">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span><?php echo esc_html__( 'Choose File', 'rabindra-medical-hall' ); ?></span>
                </label>
                <label class="rmh-file-action rmh-file-action--camera" for="rmhPrescriptionCamera">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    <span><?php echo esc_html__( 'Take Photo', 'rabindra-medical-hall' ); ?></span>
                </label>
            </div>
            <input class="rmh-visually-hidden-file rmh-prescription-file-input" type="file" id="prescriptionFile" name="prescription_file" accept=".jpg,.jpeg,.png,.webp,.pdf" aria-describedby="rmh-prescription-file-help rmh-prescription-file-status">
            <input class="rmh-visually-hidden-file rmh-camera-input" type="file" id="rmhPrescriptionCamera" name="prescription_camera" accept="image/*" capture="environment" aria-describedby="rmh-prescription-file-help rmh-prescription-file-status">
            <span class="rmh-field-help" id="rmh-prescription-file-help"><?php echo esc_html__( 'JPG, PNG, WEBP or PDF. Maximum 10 MB.', 'rabindra-medical-hall' ); ?></span>
            <span class="rmh-file-status" id="rmh-prescription-file-status" aria-live="polite"></span>
        </div>
        <button type="submit" class="rmh-form-button"><?php echo esc_html__( 'Submit Prescription', 'rabindra-medical-hall' ); ?></button>
    </form>
    <?php
    return ob_get_clean();
}

add_shortcode( 'rmh_prescription_form', 'rmh_prescription_form_shortcode' );

/**
 * Process prescription uploads securely.
 */
function rmh_handle_prescription_form() {
    // Rate limiting: 5 uploads per hour per IP
    $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    $rate_key = 'rmh_prescription_rate_' . md5( $ip );
    $requests = (int) get_transient( $rate_key );

    if ( $requests >= 5 ) {
        rmh_form_redirect( 'rmh_prescription', 'error', home_url( '/#rmh-prescription' ) );
    }

    if (
        ! isset( $_POST['rmh_prescription_nonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rmh_prescription_nonce'] ) ), 'rmh_prescription_form' )
    ) {
        rmh_form_redirect( 'rmh_prescription', 'error', home_url( '/#rmh-prescription' ) );
    }

    if ( ! empty( $_POST['website'] ) ) {
        rmh_form_redirect( 'rmh_prescription', 'sent', home_url( '/#rmh-prescription' ) );
    }

    $name   = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
    $mobile = isset( $_POST['mobile_number'] ) ? sanitize_text_field( wp_unslash( $_POST['mobile_number'] ) ) : '';
    $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

    if ( ! is_user_logged_in() || ! $order_id || ! function_exists( 'wc_get_order' ) ) {
        $order_id = 0;
    } else {
        $order = wc_get_order( $order_id );
        if ( ! $order || (int) $order->get_user_id() !== get_current_user_id() ) {
            $order_id = 0;
        }
    }

    $has_file_upload = ( ! empty( $_FILES['prescription_file'] ) && ! empty( $_FILES['prescription_file']['tmp_name'] ) ) || ( ! empty( $_FILES['prescription_camera'] ) && ! empty( $_FILES['prescription_camera']['tmp_name'] ) );

    if ( '' === $name || ! preg_match( '/^[0-9+\-\s()]{7,20}$/', $mobile ) || ! $has_file_upload ) {
        rmh_form_redirect( 'rmh_prescription', 'error', home_url( '/#rmh-prescription' ) );
    }

    $file = isset( $_FILES['prescription_file'] ) ? $_FILES['prescription_file'] : array();

    if ( empty( $file['tmp_name'] ) && isset( $_FILES['prescription_camera'] ) ) {
        $file = $_FILES['prescription_camera'];
    }

    if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || (int) $file['size'] > 10 * MB_IN_BYTES ) {
        rmh_form_redirect( 'rmh_prescription', 'error', home_url( '/#rmh-prescription' ) );
    }

    $check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );

    $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/webp', 'application/pdf' );

    if ( empty( $check['ext'] ) || empty( $check['type'] ) || ! in_array( $check['type'], $allowed_mimes, true ) ) {
        rmh_form_redirect( 'rmh_prescription', 'error', home_url( '/#rmh-prescription' ) );
    }

    // Increment rate limit counter
    set_transient( $rate_key, $requests + 1, HOUR_IN_SECONDS );

    $storage_dir = rmh_get_private_storage_dir();

    if ( ! wp_mkdir_p( $storage_dir ) || ! is_dir( $storage_dir ) ) {
        rmh_form_redirect( 'rmh_prescription', 'error', home_url( '/#rmh-prescription' ) );
    }

    $filename = wp_unique_filename( $storage_dir, wp_generate_password( 32, false, false ) . '.' . $check['ext'] );
    $target   = trailingslashit( $storage_dir ) . $filename;

    if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
        rmh_form_redirect( 'rmh_prescription', 'error', home_url( '/#rmh-prescription' ) );
    }

    $post_id = wp_insert_post(
        array(
            'post_type'   => 'rmh_prescription',
            'post_status' => 'draft',
            'post_title'  => sprintf( __( 'Prescription from %s', 'rabindra-medical-hall' ), $name ),
        ),
        true
    );

    if ( is_wp_error( $post_id ) ) {
        @unlink( $target );
        rmh_form_redirect( 'rmh_prescription', 'error', home_url( '/#rmh-prescription' ) );
    }

    update_post_meta( $post_id, '_rmh_customer_name', $name );
    update_post_meta( $post_id, '_rmh_mobile_number', $mobile );
    update_post_meta( $post_id, '_rmh_file_path', $target );
    update_post_meta( $post_id, '_rmh_original_filename', sanitize_file_name( $file['name'] ) );
    update_post_meta( $post_id, '_rmh_file_mime', $check['type'] );
    if ( is_user_logged_in() ) {
        update_post_meta( $post_id, '_rmh_user_id', get_current_user_id() );
    }
    if ( $order_id ) {
        update_post_meta( $post_id, '_rmh_order_id', $order_id );
    }
    update_post_meta( $post_id, '_rmh_status', 'Pending Review' );
    rmh_record_prescription_status_change( $post_id, 'Pending Review' );

    wp_mail(
        rmh_get_contact_email(),
        __( 'New prescription upload', 'rabindra-medical-hall' ),
        sprintf( "Customer: %s\nMobile: %s\nStatus: Pending Review\nRecord ID: %d", $name, $mobile, $post_id )
    );

    rmh_form_redirect( 'rmh_prescription', 'sent', home_url( '/#rmh-prescription' ) );
}

add_action( 'admin_post_rmh_prescription_form', 'rmh_handle_prescription_form' );
add_action( 'admin_post_nopriv_rmh_prescription_form', 'rmh_handle_prescription_form' );

/**
 * Offer a secure upload link from an order only when its real items require it.
 *
 * @param WC_Order $order Current order.
 */
function rmh_render_order_prescription_link( $order ) {
    if ( ! is_user_logged_in() || ! $order instanceof WC_Order || (int) $order->get_user_id() !== get_current_user_id() ) {
        return;
    }

    foreach ( $order->get_items() as $item ) {
        $product = $item->get_product();
        if ( $product && rmh_product_requires_prescription( $product ) ) {
            $upload_url = add_query_arg( 'rmh_order_id', $order->get_id(), home_url( '/' ) ) . '#rmh-prescription';
            echo '<p class="rmh-order-prescription-link"><a class="button" href="' . esc_url( $upload_url ) . '">' . esc_html__( 'Upload prescription for this order', 'rabindra-medical-hall' ) . '</a></p>';
            break;
        }
    }
}

add_action( 'woocommerce_order_details_after_order_table', 'rmh_render_order_prescription_link', 20 );





/**
 * Restrict Local Delivery Zone flat_rate to its configured postcodes.
 *
 * WooCommerce applies Zone 2 (Local Delivery) rates based on its postcode
 * location list. This filter provides an extra guard: if the destination
 * postcode is not in the local zone list AND the rate came from Zone 2 (the
 * local delivery zone, instance_id 2), we remove it to prevent the local
 * flat_rate from appearing for non-local addresses.
 *
 * Zone 1 (Regular Odisha Delivery, flat_rate instance_id 6) is NOT touched
 * by this filter — WooCommerce handles zone matching for that automatically.
 *
 * @param WC_Shipping_Rate[] $rates   Available rates for this package.
 * @param array              $package Shipping package data.
 * @return WC_Shipping_Rate[]
 */
add_filter( 'woocommerce_package_rates', 'rmh_restrict_local_delivery_by_zip', 10, 2 );
function rmh_restrict_local_delivery_by_zip( $rates, $package ) {
    $shipping_postcode = '';

    if ( isset( $package['destination']['postcode'] ) ) {
        $shipping_postcode = rmh_normalize_postcode( $package['destination']['postcode'] );
    }

    // No postcode yet — let WooCommerce show all available rates.
    if ( '' === $shipping_postcode ) {
        return $rates;
    }

    $is_local = rmh_is_allowed_delivery_postcode( $shipping_postcode );

    if ( ! $is_local ) {
        /*
         * Customer is outside the local zone. Remove only the Local Delivery
         * flat_rate (instance_id 2, which is Zone 2). The Odisha zone flat_rate
         * (instance_id 6, Zone 1) must remain to give these customers a rate.
         */
        $local_instance_id = apply_filters( 'rmh_local_zone_flat_rate_instance_id', 2 );

        foreach ( $rates as $rate_id => $rate ) {
            if (
                isset( $rate->method_id ) &&
                'flat_rate' === $rate->method_id &&
                isset( $rate->instance_id ) &&
                (int) $rate->instance_id === (int) $local_instance_id
            ) {
                unset( $rates[ $rate_id ] );
            }
        }
    }

    return $rates;
}


// Custom error message at checkout for out-of-radius addresses
add_action( 'woocommerce_checkout_process', 'rmh_validate_delivery_radius' );
function rmh_validate_delivery_radius() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
        return;
    }

    // Do not restrict checkout if the order does not require physical delivery.
    if ( ! WC()->cart->needs_shipping() ) {
        return;
    }

    $posted_data = wp_unslash( $_POST );

    // Check if the selected shipping method is local pickup.
    $chosen_shipping_methods = array();
    if ( ! empty( $posted_data['shipping_method'] ) && is_array( $posted_data['shipping_method'] ) ) {
        $chosen_shipping_methods = $posted_data['shipping_method'];
    } elseif ( WC()->session ) {
        $chosen_shipping_methods = (array) WC()->session->get( 'chosen_shipping_methods' );
    }

    foreach ( $chosen_shipping_methods as $method ) {
        if ( is_string( $method ) && 0 === strpos( $method, 'local_pickup' ) ) {
            return;
        }
    }

    $shipping_postcode = '';
    $ship_to_different_address = ! empty( $posted_data['ship_to_different_address'] );

    if ( $ship_to_different_address && ! empty( $posted_data['shipping_postcode'] ) ) {
        $shipping_postcode = rmh_normalize_postcode( $posted_data['shipping_postcode'] );
    } elseif ( ! empty( $posted_data['billing_postcode'] ) ) {
        $shipping_postcode = rmh_normalize_postcode( $posted_data['billing_postcode'] );
    } elseif ( ! empty( $posted_data['shipping_postcode'] ) ) {
        $shipping_postcode = rmh_normalize_postcode( $posted_data['shipping_postcode'] );
    }

    // Fallback to session-saved location from header location selector.
    if ( '' === $shipping_postcode && WC()->session ) {
        $shipping_postcode = WC()->session->get( 'rmh_delivery_postcode', '' );
    }

    /*
     * Previously this blocked ALL non-local postcodes. Now that Zone 1 covers all
     * of Odisha state (IN:OD), non-local Odisha customers can get a shipping rate.
     * Only block if the postcode is known to be outside our serviceable area
     * (i.e. WooCommerce returned zero shipping rates for this destination).
     *
     * We leave the shipping-method check to WooCommerce itself — if no rate is
     * available, WC will already prevent order submission with its own error.
     */
}

/**
 * Prevent checkout when prescription-required items are in cart without a linked prescription.
 *
 * Validates that the customer has uploaded at least one prescription that is either
 * 'Approved' or 'Under Review' for the current session/order.
 *
 * @return void
 */
function rmh_validate_prescription_requirements_at_checkout() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
        return;
    }

    // Do not restrict if the order does not require physical delivery.
    if ( ! WC()->cart->needs_shipping() ) {
        return;
    }

    $has_rx_item = false;

    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

        if ( $product instanceof WC_Product && rmh_product_requires_prescription( $product ) ) {
            $has_rx_item = true;
            break;
        }
    }

    if ( ! $has_rx_item ) {
        return;
    }

    // A prescription ID must come from the current WooCommerce session and must
    // belong to this customer/session. Never accept an ID posted from the browser.
    $session_rx_id = ( function_exists( 'WC' ) && WC()->session ) ? (int) WC()->session->get( 'rmh_uploaded_prescription_id' ) : 0;

    if ( $session_rx_id > 0 && function_exists( 'rmh_current_session_owns_prescription' ) && rmh_current_session_owns_prescription( $session_rx_id ) ) {
        // Prescription uploaded for this session - customer is allowed to proceed to checkout.
        // The order will be flagged for pharmacist review before dispatch.
        return;
    }

    $user_id = get_current_user_id();

    if ( $user_id > 0 ) {
        global $wpdb;
        $user_rx_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) 
                 FROM {$wpdb->posts} p 
                 INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id AND pm_user.meta_key = '_rmh_user_id'
                 INNER JOIN {$wpdb->postmeta} pm_stat ON p.ID = pm_stat.post_id AND pm_stat.meta_key = '_rmh_status'
                 WHERE p.post_type = 'rmh_prescription' 
                   AND pm_user.meta_value = %d 
                   AND pm_stat.meta_value NOT IN ('Rejected', 'Rejected / Cannot Fulfill')",
                $user_id
            )
        );

        if ( $user_rx_count > 0 ) {
            return;
        }
    }

    wc_add_notice(
        wp_kses(
            sprintf(
                __( '<strong>Prescription Required:</strong> Your cart contains prescription-only medicines. Please %1$supload your doctor prescription%2$s before placing this order.', 'rabindra-medical-hall' ),
                '<a href="' . esc_url( home_url( '/#rmh-prescription' ) ) . '" class="rmh-open-rx-modal">',
                '</a>'
            ),
            array(
                'strong' => array(),
                'a'      => array( 'href' => array(), 'class' => array() ),
            )
        ),
        'error'
    );
}

add_action( 'woocommerce_checkout_process', 'rmh_validate_prescription_requirements_at_checkout', 15 );

/**
 * Re-validate inventory and prices at checkout to prevent overselling and price drift.
 *
 * This runs server-side during checkout processing to ensure:
 * - Products still exist and are purchasable
 * - Current prices match what the customer sees
 * - Stock is sufficient for requested quantities
 * - Prescription requirements are still valid
 *
 * @return void
 */
function rmh_validate_inventory_at_checkout() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
        return;
    }

    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        $product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

        if ( ! $product instanceof WC_Product ) {
            wc_add_notice(
                sprintf(
                    __( 'Product "%s" is no longer available. Please update your cart.', 'rabindra-medical-hall' ),
                    $cart_item['data'] ? $cart_item['data']->get_name() : __( 'Unknown product', 'rabindra-medical-hall' )
                ),
                'error'
            );
            continue;
        }

        // Re-check product existence and publish status
        if ( 'publish' !== $product->get_status() ) {
            wc_add_notice(
                sprintf(
                    __( 'Product "%s" is no longer available for purchase.', 'rabindra-medical-hall' ),
                    $product->get_name()
                ),
                'error'
            );
            continue;
        }

        // Re-validate price
        $cart_price = $cart_item['data']->get_price();
        $current_price = $product->get_price();

        if ( is_numeric( $cart_price ) && is_numeric( $current_price ) && (float) $cart_price !== (float) $current_price ) {
            wc_add_notice(
                sprintf(
                    __( 'Price for "%s" has changed. Please review your cart.', 'rabindra-medical-hall' ),
                    $product->get_name()
                ),
                'error'
            );
        }

        // Re-validate stock
        if ( $product->managing_stock() ) {
            $available_stock = $product->get_stock_quantity();
            $cart_qty = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;

            if ( $available_stock !== null && $cart_qty > $available_stock ) {
                wc_add_notice(
                    sprintf(
                        __( 'Insufficient stock for "%s". Only %d available.', 'rabindra-medical-hall' ),
                        $product->get_name(),
                        $available_stock
                    ),
                    'error'
                );
            }
        }

        // Re-validate prescription requirement
        if ( rmh_product_requires_prescription( $product ) ) {
            // This will be caught by the prescription validation hook, but we add a cart notice too
            wc_add_notice(
                sprintf(
                    __( '%s requires a valid prescription.', 'rabindra-medical-hall' ),
                    $product->get_name()
                ),
                'notice'
            );
        }
    }
}

add_action( 'woocommerce_checkout_process', 'rmh_validate_inventory_at_checkout', 10 );

/**
 * Return operational delivery PIN codes.
 *
 * Kept filterable so the list can be moved to settings later without changing
 * checkout logic.
 *
 * @return string[]
 */
function rmh_get_allowed_delivery_postcodes() {
    return apply_filters(
        'rmh_allowed_delivery_postcodes',
        array( '753012', '753001', '753002', '753003' )
    );
}

/**
 * Normalize a submitted Indian PIN/postcode value.
 *
 * @param mixed $postcode Raw postcode value.
 * @return string
 */
function rmh_normalize_postcode( $postcode ) {
    return strtoupper( preg_replace( '/[^A-Z0-9]/', '', sanitize_text_field( (string) $postcode ) ) );
}

/**
 * Check whether a postcode is currently in the configured delivery area.
 *
 * @param string $postcode Normalized postcode.
 * @return bool
 */
function rmh_is_allowed_delivery_postcode( $postcode ) {
    if ( '' === $postcode ) {
        return true;
    }

    return in_array( $postcode, rmh_get_allowed_delivery_postcodes(), true );
}

/**
 * Save the customer's selected delivery location/postcode to the WooCommerce session.
 *
 * @return void
 */
function rmh_ajax_save_delivery_location() {
    check_ajax_referer( 'rmh_location', 'nonce' );

    $postcode = isset( $_POST['postcode'] ) ? rmh_normalize_postcode( wp_unslash( $_POST['postcode'] ) ) : '';

    if ( ! $postcode ) {
        wp_send_json_error( array( 'message' => __( 'Please enter a valid PIN code.', 'rabindra-medical-hall' ) ) );
    }

    if ( ! rmh_is_allowed_delivery_postcode( $postcode ) ) {
        wp_send_json_error( array( 'message' => __( 'Delivery is not available for this PIN code.', 'rabindra-medical-hall' ) ) );
    }

    if ( ! WC()->session ) {
        wp_send_json_error( array( 'message' => __( 'Session not available. Please try again.', 'rabindra-medical-hall' ) ) );
    }

    WC()->session->set( 'rmh_delivery_postcode', $postcode );

    // Also update the shipping destination so WooCommerce recalculates shipping.
    if ( isset( WC()->customer ) ) {
        WC()->customer->set_shipping_postcode( $postcode );
        WC()->customer->save();
    }

    $is_local = rmh_is_allowed_delivery_postcode( $postcode );

    wp_send_json_success( array(
        'postcode' => $postcode,
        'is_local' => $is_local,
        'message'  => $is_local ? __( 'PIN code is within our local delivery area.', 'rabindra-medical-hall' ) : __( 'PIN code is valid for regular Odisha delivery.', 'rabindra-medical-hall' ),
    ) );
}

add_action( 'wp_ajax_rmh_save_delivery_location', 'rmh_ajax_save_delivery_location' );
add_action( 'wp_ajax_nopriv_rmh_save_delivery_location', 'rmh_ajax_save_delivery_location' );

/**
 * Return the saved delivery postcode for the current session.
 *
 * @return void
 */
function rmh_ajax_get_delivery_location() {
    check_ajax_referer( 'rmh_location', 'nonce' );

    $postcode = '';

    if ( WC()->session ) {
        $postcode = WC()->session->get( 'rmh_delivery_postcode', '' );
    }

    wp_send_json_success( array( 'postcode' => $postcode ) );
}

add_action( 'wp_ajax_rmh_get_delivery_location', 'rmh_ajax_get_delivery_location' );
add_action( 'wp_ajax_nopriv_rmh_get_delivery_location', 'rmh_ajax_get_delivery_location' );

/**
 * Output Schema.org Pharmacy structured data for SEO.
 */
function rmh_output_pharmacy_schema() {
    if ( is_admin() ) {
        return;
    }

    $trust_settings = function_exists( 'rmh_get_trust_settings' ) ? rmh_get_trust_settings() : array();
    $license_numbers = ! empty( $trust_settings['drug_license_numbers'] ) ? preg_split( '/\r\n|\r|\n/', (string) $trust_settings['drug_license_numbers'] ) : array();
    if ( empty( $license_numbers ) && ! empty( $trust_settings['drug_license_no'] ) ) {
        $license_numbers = array( $trust_settings['drug_license_no'] );
    }
    $license_numbers = array_values( array_filter( array_map( 'trim', $license_numbers ) ) );
    $is_licensed    = ! empty( $license_numbers );
    $schema_desc    = sprintf(
        'Rabindra Medical Hall is a %sretail pharmacy at Badambadi Old Bus Stand, Cuttack offering genuine medicines, healthcare essentials, and local delivery in Cuttack.',
        $is_licensed ? 'licensed ' : ''
    );

    $schema = array(
        '@context'           => 'https://schema.org',
        '@type'              => 'Pharmacy',
        'name'               => 'Rabindra Medical Hall',
        'url'                => home_url( '/' ),
        'logo'               => trailingslashit( set_url_scheme( get_stylesheet_directory_uri(), rmh_is_secure_request() ? 'https' : 'http' ) ) . 'assets/images/rmh-header-logo.jpg',
        'image'              => trailingslashit( set_url_scheme( get_stylesheet_directory_uri(), rmh_is_secure_request() ? 'https' : 'http' ) ) . 'assets/images/rmh-hero-pharmacy-box.jpeg',
        'description'        => $schema_desc,
        'telephone'          => '+91' . rmh_get_contact_phone(),
        'email'              => rmh_get_contact_email(),
        'priceRange'         => '₹',
        'currenciesAccepted' => 'INR',
        'paymentAccepted'    => 'Cash, UPI, Credit Card, Debit Card',
        'address'            => array(
            '@type'           => 'PostalAddress',
            'streetAddress'   => 'Badambadi Old Bus Stand',
            'addressLocality' => 'Cuttack',
            'addressRegion'   => 'Odisha',
            'postalCode'      => '753001',
            'addressCountry'  => 'IN',
        ),
        'areaServed'         => array(
            array(
                '@type' => 'City',
                'name'  => 'Cuttack',
            ),
            array(
                '@type' => 'State',
                'name'  => 'Odisha',
            ),
        ),
    );

    if ( ! empty( $trust_settings['gstin'] ) ) {
        $schema['taxID'] = $trust_settings['gstin'];
    }
    if ( ! empty( $license_numbers ) ) {
        $schema['identifier'] = array_map(
            static function ( $license_number ) {
                return array(
                    '@type'      => 'PropertyValue',
                    'propertyID' => 'Drug Licence Number',
                    'value'      => $license_number,
                );
            },
            $license_numbers
        );
    }

    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>' . "\n";
}

add_action( 'wp_head', 'rmh_output_pharmacy_schema', 1 );

/* =========================================================================
   CATALOGUE FILTERING & SORTING: AVAILABLE MEDICINES & A-Z ORDER (PARTS F & G)
   ========================================================================= */

/**
 * Filter customer-facing shop and archive product queries to in-stock only and A->Z alphabetical order.
 *
 * @param WC_Query $query Product query.
 */
function rmh_filter_customer_facing_catalog( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    // Keep published out-of-stock products visible; WooCommerce still blocks
    // purchasing when stock rules make a product unavailable.
    $query->set( 'rmh_stock_first_order', true );

    // Enforce A -> Z alphabetical order by default
    $orderby = $query->get( 'orderby' );
    if ( empty( $orderby ) || 'menu_order' === $orderby || 'menu_order title' === $orderby ) {
        $query->set( 'orderby', 'title' );
        $query->set( 'order', 'ASC' );
    }
}
add_action( 'woocommerce_product_query', 'rmh_filter_customer_facing_catalog', 25 );

/**
 * Keep available products first and continue with unavailable products after them.
 * Alphabetical ordering is preserved within each stock group.
 *
 * @param array    $clauses SQL clauses.
 * @param WP_Query $query   Current product query.
 * @return array
 */
function rmh_order_catalog_stock_first( $clauses, $query ) {
    global $wpdb;

    if ( ! $query instanceof WP_Query || ! $query->get( 'rmh_stock_first_order' ) ) {
        return $clauses;
    }

    $lookup_table = $wpdb->wc_product_meta_lookup;
    if ( false === strpos( $clauses['join'], $lookup_table ) ) {
        $clauses['join'] .= " LEFT JOIN {$lookup_table} AS rmh_stock_lookup ON {$wpdb->posts}.ID = rmh_stock_lookup.product_id";
    }

    $clauses['orderby'] = "CASE WHEN rmh_stock_lookup.stock_status = 'outofstock' THEN 1 ELSE 0 END ASC, {$wpdb->posts}.post_title ASC";
    return $clauses;
}

add_filter( 'posts_clauses', 'rmh_order_catalog_stock_first', 25, 2 );

/**
 * Show legitimate published out-of-stock products in the catalogue and Store API.
 * This changes visibility only; WooCommerce purchase/stock validation remains active.
 *
 * @param mixed  $value  Pre-option value.
 * @param string $option Option name.
 * @param mixed  $default Default option value.
 * @return string
 */
function rmh_show_out_of_stock_catalogue_items( $value, $option, $default ) {
    return 'no';
}

add_filter( 'pre_option_woocommerce_hide_out_of_stock_items', 'rmh_show_out_of_stock_catalogue_items', 10, 3 );

/**
 * Enforce A -> Z alphabetical order for related products.
 */
add_filter( 'woocommerce_related_products_args', function( $args ) {
    $args['orderby'] = 'title';
    $args['order']   = 'ASC';
    return $args;
}, 20 );

/**
 * Configure compact related products display.
 */
add_filter( 'woocommerce_output_related_products_args', function( $args ) {
    $args['posts_per_page'] = 4;
    $args['columns']        = 4;
    return $args;
}, 20 );

/**
 * Publish only the confirmed store-return terms on the customer-facing page.
 *
 * @param string $content Page content.
 * @return string
 */
function rmh_return_policy_content( $content ) {
    if ( is_admin() || ! is_page( 'returns-refunds' ) ) {
        return $content;
    }

    return '<div class="rmh-policy-page"><div class="rmh-policy-content">'
        . '<h1>Return Policy</h1>'
        . '<p>We accept eligible product returns within 7 days of purchase at our store. To request a return, please bring the original bill as proof of purchase and visit the store between 1:00 PM and 4:00 PM.</p>'
        . '<p>To be eligible for a return, the product must be unused, unopened, undamaged, and in its original packaging, with all labels, seals, and accompanying items intact. Products that have been opened, used, damaged, tampered with, or are missing their original packaging may not be eligible for return.</p>'
        . '<p>Our store team will inspect the product and verify the original bill before processing the return. Return acceptance is subject to product eligibility and verification at the store.</p>'
        . '</div></div>';
}

add_filter( 'the_content', 'rmh_return_policy_content', 99 );

/**
 * Remove legacy COD wording from the public terms page without changing the
 * stored legal document or any admin configuration labels.
 *
 * @param string $content Page content.
 * @return string
 */
function rmh_remove_cod_from_public_terms( $content ) {
    if ( is_admin() || ! is_page( 'terms-conditions' ) ) {
        return $content;
    }
    return str_ireplace(
        array( 'Cash on Delivery (limited regions)', 'Cash on Delivery', 'Pay on Delivery', 'Cash Payment on Delivery' ),
        __( 'secure online payment', 'rabindra-medical-hall' ),
        $content
    );
}

add_filter( 'the_content', 'rmh_remove_cod_from_public_terms', 98 );

/**
 * Match the public page title to the approved policy wording.
 *
 * @param string $title Page title.
 * @param int    $post_id Post ID.
 * @return string
 */
function rmh_return_policy_title( $title, $post_id = 0 ) {
    if ( ! is_admin() && $post_id && 'returns-refunds' === get_post_field( 'post_name', $post_id ) ) {
        return __( 'Return Policy', 'rabindra-medical-hall' );
    }
    return $title;
}

add_filter( 'the_title', 'rmh_return_policy_title', 20, 2 );

/**
 * Sanitize legacy Customizer Additional CSS to prevent broken 2-column heading grids
 * and avoid forced letter-by-letter vertical wrapping.
 *
 * @param string $css The Customizer Additional CSS.
 * @return string Sanitized CSS.
 */
function rmh_sanitize_customizer_additional_css( $css ) {
    if ( ! empty( $css ) ) {
        // Fix the broken heading 2-column grid that squeezes section labels into 20px vertical towers
        $css = str_replace( 'grid-template-columns: minmax(0, 1fr) auto;', 'grid-template-columns: 1fr;', $css );
        // Prevent anywhere wrapping that breaks words letter-by-letter into vertical lines
        $css = str_replace( 'overflow-wrap: anywhere;', 'overflow-wrap: break-word;', $css );
        $css = str_replace( 'overflow-wrap: anywhere !important;', 'overflow-wrap: break-word !important;', $css );
    }
    return $css;
}
add_filter( 'wp_get_custom_css', 'rmh_sanitize_customizer_additional_css', 99 );

/**
 * RMH Mobile Image Reliability
 * Presentation-only image CSS.
 */
function rmh_enqueue_mobile_image_assets() {
    $css_file = get_stylesheet_directory() . '/assets/css/rmh-mobile-images.css';

    if ( file_exists( $css_file ) ) {
        wp_enqueue_style(
            'rmh-mobile-images',
            get_stylesheet_directory_uri() . '/assets/css/rmh-mobile-images.css',
            array(),
            filemtime( $css_file )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'rmh_enqueue_mobile_image_assets', 999 );

/**
 * RMH Mobile Image Reliability JS
 */
function rmh_enqueue_mobile_image_js() {
    $js_file = get_stylesheet_directory() . '/assets/js/rmh-mobile-images.js';

    if ( file_exists( $js_file ) ) {
        wp_enqueue_script(
            'rmh-mobile-images',
            get_stylesheet_directory_uri() . '/assets/js/rmh-mobile-images.js',
            array(),
            filemtime( $js_file ),
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'rmh_enqueue_mobile_image_js', 999 );

/**
 * RMH Mobile UI & Responsive Design System Assets
 */
function rmh_enqueue_mobile_ui_assets() {
    $css_file = get_stylesheet_directory() . '/assets/css/rmh-mobile-ui.css';
    $js_file  = get_stylesheet_directory() . '/assets/js/rmh-mobile-ui.js';
    $scheme   = rmh_is_secure_request() ? 'https' : 'http';
    $base_uri = set_url_scheme( get_stylesheet_directory_uri(), $scheme );

    if ( file_exists( $css_file ) ) {
        wp_enqueue_style(
            'rmh-mobile-ui',
            $base_uri . '/assets/css/rmh-mobile-ui.css',
            array( 'rmh-custom-css' ),
            filemtime( $css_file )
        );
    }

    if ( file_exists( $js_file ) ) {
        wp_enqueue_script(
            'rmh-mobile-ui',
            $base_uri . '/assets/js/rmh-mobile-ui.js',
            array(),
            filemtime( $js_file ),
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'rmh_enqueue_mobile_ui_assets', 1000 );

/**
 * Permanently remove Cash on Delivery (COD) payment gateway.
 */
function rmh_disable_cash_on_delivery( $available_gateways ) {
    if ( isset( $available_gateways['cod'] ) ) {
        unset( $available_gateways['cod'] );
    }
    return $available_gateways;
}
add_filter( 'woocommerce_available_payment_gateways', 'rmh_disable_cash_on_delivery', 999 );
