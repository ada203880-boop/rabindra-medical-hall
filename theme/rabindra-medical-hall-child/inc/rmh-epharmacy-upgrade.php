<?php
/**
 * Rabindra Medical Hall — Premium Authentic E-Pharmacy Upgrade
 *
 * Implements:
 * 1. Product Card System (Medical hierarchy, composition, Rx/OTC badges, pack normalization, savings, AJAX stepper)
 * 2. Product Detail Page (Medical specs, prescription warning, pharmacist WhatsApp ask, cold chain)
 * 3. Header & Secondary Health Navigation (12 verified categories)
 * 4. Pharmacy Trust & Regulatory Admin Settings (Drug License, GSTIN, Pharmacist details - hidden when empty)
 * 5. Pharmacist Review Workflow (5 exact statuses, order meta, checkout validation fix)
 * 6. Quick Prescription Upload Modal (desktop & mobile camera capture, private storage)
 * 7. Homepage 13-Section Flow (Real catalog products, verified brands, health conditions)
 * 8. Cart & Checkout Rx Badges & Associations
 *
 * @package RabindraMedicalHall
 * @version 3.0.0
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
   1. CORE PHARMACEUTICAL HELPERS
   ========================================================================= */

/**
 * Normalize raw packaging codes into professional customer-friendly text.
 * Never invents quantities when not present in source data.
 *
 * @param string $raw_pack Raw pack size string from ERP or meta.
 * @return string Normalized pack description.
 */
function rmh_format_pack_size( $raw_pack ) {
    if ( empty( $raw_pack ) ) {
        return '';
    }
    $pack = trim( (string) $raw_pack );
    if ( in_array( strtoupper( $pack ), array( 'BLANK', '-BLANK-', '-', 'N/A', '' ), true ) ) {
        return '';
    }

    // Pattern matches with explicit quantities (e.g. "10 TAB", "10TAB", "100 ML")
    if ( preg_match( '/^(\d+)\s*(?:TAB|TABS|TAB\.)(?:\s*\(.*\))?$/i', $pack, $m ) ) {
        return sprintf( __( 'Strip of %s Tablets', 'rabindra-medical-hall' ), $m[1] );
    }
    if ( preg_match( '/^(\d+)\s*(?:CAP|CAPS|CAP\.)(?:\s*\(.*\))?$/i', $pack, $m ) ) {
        return sprintf( __( 'Strip of %s Capsules', 'rabindra-medical-hall' ), $m[1] );
    }
    if ( preg_match( '/^(\d+)\s*(?:ML)(?:\s*\(.*\))?$/i', $pack, $m ) ) {
        return sprintf( __( 'Bottle of %s ml', 'rabindra-medical-hall' ), $m[1] );
    }
    if ( preg_match( '/^(\d+)\s*(?:GM|GMS|G)(?:\s*\(.*\))?$/i', $pack, $m ) ) {
        return sprintf( __( 'Tube of %s g', 'rabindra-medical-hall' ), $m[1] );
    }
    if ( preg_match( '/^(\d+)\s*(?:VIAL|VIALS)(?:\s*\(.*\))?$/i', $pack, $m ) ) {
        return sprintf( __( 'Pack of %s Vials', 'rabindra-medical-hall' ), $m[1] );
    }
    if ( preg_match( '/^(\d+)\s*(?:AMP|AMPS)(?:\s*\(.*\))?$/i', $pack, $m ) ) {
        return sprintf( __( 'Pack of %s Ampoules', 'rabindra-medical-hall' ), $m[1] );
    }
    if ( preg_match( '/^(\d+)\s*(?:PCS|PC)(?:\s*\(.*\))?$/i', $pack, $m ) ) {
        return sprintf( __( 'Pack of %s Units', 'rabindra-medical-hall' ), $m[1] );
    }

    // Individual word replacements
    $replacements = array(
        '/\bTAB\b|\bTABS\b|\bTAB\./i'   => __( 'Tablets', 'rabindra-medical-hall' ),
        '/\bCAP\b|\bCAPS\b|\bCAP\./i'   => __( 'Capsules', 'rabindra-medical-hall' ),
        '/\bSYR\b|\bSYR\./i'            => __( 'Syrup', 'rabindra-medical-hall' ),
        '/\bSUSP\b|\bSUSP\./i'          => __( 'Suspension', 'rabindra-medical-hall' ),
        '/\bOINT\b|\bOINT\./i'          => __( 'Ointment', 'rabindra-medical-hall' ),
        '/\bCREAM\b|\bCRM\b/i'          => __( 'Cream', 'rabindra-medical-hall' ),
        '/\bDROP\b|\bDROPS\b|\bDRP\b/i' => __( 'Drops', 'rabindra-medical-hall' ),
        '/\bINJ\b|\bINJ\./i'            => __( 'Injection', 'rabindra-medical-hall' ),
        '/\bGEL\b/i'                    => __( 'Gel', 'rabindra-medical-hall' ),
        '/\bLOT\b|\bLOTN\b|\bLOT\./i'   => __( 'Lotion', 'rabindra-medical-hall' ),
        '/\bPCS\b|\bPC\b/i'             => __( 'Pieces', 'rabindra-medical-hall' ),
        '/\bVIAL\b|\bVIALS\b/i'         => __( 'Vial', 'rabindra-medical-hall' ),
        '/\bAMP\b|\bAMPS\b/i'           => __( 'Ampoule', 'rabindra-medical-hall' ),
        '/\bRESP\b|\bRESPULES\b/i'      => __( 'Respules', 'rabindra-medical-hall' ),
        '/\bSACHET\b|\bSACHETS\b/i'     => __( 'Sachets', 'rabindra-medical-hall' ),
        '/\bML\b/i'                     => 'ml',
        '/\bGM\b|\bGMS\b/i'             => 'g',
        '/\bMG\b/i'                     => 'mg',
    );

    foreach ( $replacements as $pattern => $replacement ) {
        $pack = preg_replace( $pattern, $replacement, $pack );
    }

    return rtrim( trim( $pack ), '.' );
}

/**
 * Retrieve active ingredient/composition safely from product metadata.
 * Never invents composition. Returns empty string if unavailable.
 *
 * @param int $product_id Product ID.
 * @return string Composition or empty.
 */
function rmh_get_product_composition( $product_id ) {
    $keys = array( '_rmh_composition', '_rmh_generic_name', '_generic_name', '_active_ingredient', 'composition' );
    foreach ( $keys as $key ) {
        $val = get_post_meta( $product_id, $key, true );
        if ( ! empty( $val ) && ! in_array( strtoupper( trim( $val ) ), array( 'BLANK', '-BLANK-', '-', 'N/A', 'NONE', '' ), true ) ) {
            return trim( (string) $val );
        }
    }
    return '';
}

/**
 * Determine prescription/OTC classification for a product.
 * Returns 'rx' for prescription required, 'otc' for confirmed OTC, or '' for unknown.
 *
 * @param WC_Product $product WooCommerce product.
 * @return string 'rx', 'otc', or ''
 */
function rmh_get_product_classification( $product ) {
    if ( ! $product instanceof WC_Product ) {
        return '';
    }
    if ( rmh_product_requires_prescription( $product ) ) {
        return 'rx';
    }
    $is_otc = get_post_meta( $product->get_id(), '_rmh_otc', true );
    if ( 'yes' === $is_otc || has_term( 'otc-medicines', 'product_cat', $product->get_id() ) ) {
        return 'otc';
    }
    return '';
}

/**
 * Calculate genuine percentage savings between MRP / Regular Price and Sale Price.
 *
 * @param WC_Product $product Product to inspect.
 * @return int Discount percentage or 0.
 */
function rmh_get_product_savings( $product ) {
    if ( ! $product instanceof WC_Product ) {
        return 0;
    }
    $mrp = (float) get_post_meta( $product->get_id(), '_rmh_mrp', true );
    if ( ! $mrp ) {
        $mrp = (float) get_post_meta( $product->get_id(), '_mrp', true );
    }
    $regular = (float) $product->get_regular_price();
    $sale    = (float) $product->get_price();

    $benchmark = $mrp > 0 ? $mrp : ( $regular > 0 ? $regular : 0 );

    if ( $benchmark > 0 && $sale > 0 && $sale < $benchmark ) {
        return (int) round( ( ( $benchmark - $sale ) / $benchmark ) * 100 );
    }
    return 0;
}

/**
 * Retrieve MRP value safely.
 *
 * @param WC_Product $product Product to inspect.
 * @return float MRP or 0.
 */
function rmh_get_product_mrp( $product ) {
    if ( ! $product instanceof WC_Product ) {
        return 0.0;
    }
    $mrp = (float) get_post_meta( $product->get_id(), '_rmh_mrp', true );
    if ( ! $mrp ) {
        $mrp = (float) get_post_meta( $product->get_id(), '_mrp', true );
    }
    if ( ! $mrp ) {
        $mrp = (float) $product->get_regular_price();
    }
    return $mrp;
}

/**
 * Render the customer-facing MRP without exposing sale-price comparisons.
 * The stored WooCommerce regular/sale values remain unchanged for checkout.
 *
 * @param WC_Product $product Product to inspect.
 * @return string
 */
function rmh_get_product_mrp_html( $product ) {
    if ( ! $product instanceof WC_Product ) {
        return '';
    }
    $mrp = rmh_get_product_mrp( $product );
    if ( $mrp <= 0 ) {
        return wp_kses_post( wc_price( (float) $product->get_price() ) );
    }
    return '<span class="rmh-mrp-label">' . esc_html__( 'MRP', 'rabindra-medical-hall' ) . ' ' . wp_kses_post( wc_price( $mrp ) ) . '</span>';
}

/**
 * Keep product/shop/category price presentation limited to MRP.
 * Cart, checkout and order totals continue using the real WooCommerce price.
 *
 * @param string     $price_html Existing price HTML.
 * @param WC_Product $product Product being rendered.
 * @return string
 */
function rmh_mrp_only_price_html( $price_html, $product ) {
    if ( ! $product instanceof WC_Product || is_admin() ) {
        return $price_html;
    }
    if ( function_exists( 'is_cart' ) && is_cart() ) {
        return $price_html;
    }
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        return $price_html;
    }
    if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
        return $price_html;
    }
    return rmh_get_product_mrp_html( $product );
}

add_filter( 'woocommerce_get_price_html', 'rmh_mrp_only_price_html', 20, 2 );

/**
 * Check if cold-chain is verified for this product.
 * Never claims cold-chain unless explicitly configured.
 *
 * @param WC_Product $product Product.
 * @return bool
 */
function rmh_product_is_cold_chain( $product ) {
    if ( ! $product instanceof WC_Product ) {
        return false;
    }
    return 'yes' === get_post_meta( $product->get_id(), '_rmh_cold_chain_required', true );
}

/* =========================================================================
   2. PHARMACY TRUST & REGULATORY SETTINGS (WP Admin)
   ========================================================================= */

/**
 * Return verified pharmacy trust configuration.
 * All regulatory fields default to empty string so nothing fake ever appears.
 *
 * @return array
 */
function rmh_get_trust_settings() {
    $defaults = array(
        'store_name'            => 'Rabindra Medical Hall',
        'physical_address'      => 'Rabindra Medical Hall, Badambadi Old Bus Stand, Cuttack, Odisha',
        'drug_license_no'       => '',
        'drug_license_numbers'  => "CU-28841/R\nCU-28842/RC\nCU-11433/RX",
        'gstin'                 => '21AARFR5599J1ZH',
        'pharmacist_name'       => '',
        'pharmacist_reg_no'     => '',
        'established_year'      => '',
        'google_maps_url'       => 'https://maps.google.com/?q=Badambadi+Cuttack+Odisha',
        'whatsapp_number'       => '917205859117',
        'support_phone'         => '+91 72058 59117',
        'delivery_radius_km'    => '10',
        'delivery_promise_mins' => '60',
        'rx_workflow_enabled'   => 'yes',
    );
    $saved = get_option( 'rmh_pharmacy_trust_settings', array() );
    return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

/**
 * Migrate the stored delivery-promise setting from the retired short-window
 * value to the current one-hour promise without touching unrelated settings.
 */
function rmh_migrate_delivery_promise_setting() {
    $settings = get_option( 'rmh_pharmacy_trust_settings', array() );
    if ( ! is_array( $settings ) ) {
        return;
    }

    $changed = false;
    if ( ! isset( $settings['gstin'] ) || '' === trim( (string) $settings['gstin'] ) || '21AAFRF5599J1ZH' === (string) $settings['gstin'] ) {
        $settings['gstin'] = '21AARFR5599J1ZH';
        $changed = true;
    }
    if ( ! isset( $settings['drug_license_numbers'] ) || '' === trim( (string) $settings['drug_license_numbers'] ) ) {
        $settings['drug_license_numbers'] = "CU-28841/R\nCU-28842/RC\nCU-11433/RX";
        $changed = true;
    }

    if ( isset( $settings['delivery_promise_mins'] ) && '30' === (string) $settings['delivery_promise_mins'] ) {
        $settings['delivery_promise_mins'] = '60';
        $changed = true;
    }

    if ( $changed ) {
        update_option( 'rmh_pharmacy_trust_settings', $settings, false );
    }
}

add_action( 'init', 'rmh_migrate_delivery_promise_setting', 8 );

/**
 * Register the trust settings with WordPress.
 */
function rmh_register_trust_settings() {
    register_setting(
        'rmh_trust_settings_group',
        'rmh_pharmacy_trust_settings',
        array(
            'type'              => 'array',
            'sanitize_callback' => function( $input ) {
                $clean = array();
                $text_fields = array(
                    'store_name', 'physical_address', 'drug_license_no', 'gstin',
                    'pharmacist_name', 'pharmacist_reg_no', 'established_year',
                    'whatsapp_number', 'support_phone', 'delivery_radius_km',
                    'delivery_promise_mins', 'rx_workflow_enabled',
                );
                foreach ( $text_fields as $f ) {
                    if ( isset( $input[ $f ] ) ) {
                        $clean[ $f ] = sanitize_text_field( wp_unslash( $input[ $f ] ) );
                    }
                }
                if ( isset( $input['drug_license_numbers'] ) ) {
                    $clean['drug_license_numbers'] = sanitize_textarea_field( wp_unslash( $input['drug_license_numbers'] ) );
                }
                if ( isset( $input['google_maps_url'] ) ) {
                    $clean['google_maps_url'] = esc_url_raw( wp_unslash( $input['google_maps_url'] ) );
                }
                return $clean;
            },
        )
    );
}
add_action( 'admin_init', 'rmh_register_trust_settings' );

/**
 * Add Pharmacy Trust settings menu page in WP Admin.
 */
function rmh_add_trust_settings_menu() {
    add_submenu_page(
        'woocommerce',
        __( 'Pharmacy Trust & Regulatory Info', 'rabindra-medical-hall' ),
        __( 'Pharmacy Trust Info', 'rabindra-medical-hall' ),
        'manage_options',
        'rmh-trust-settings',
        'rmh_render_trust_settings_page'
    );
}
add_action( 'admin_menu', 'rmh_add_trust_settings_menu', 50 );

/**
 * Render Pharmacy Trust Settings page.
 */
function rmh_render_trust_settings_page() {
    $settings = rmh_get_trust_settings();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Rabindra Medical Hall — Pharmacy Trust & Regulatory Settings', 'rabindra-medical-hall' ); ?></h1>
        <p style="max-width:700px; color:#555;">
            <?php echo esc_html__( 'Enter only verified credentials. If any field (e.g. Drug License No. or Pharmacist Registration) is left blank, the corresponding public trust badge will remain completely hidden on the storefront to protect regulatory compliance.', 'rabindra-medical-hall' ); ?>
        </p>

        <?php if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Pharmacy trust settings updated successfully.', 'rabindra-medical-hall' ); ?></p></div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php
            settings_fields( 'rmh_trust_settings_group' );
            do_settings_sections( 'rmh_trust_settings_group' );
            ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="store_name"><?php echo esc_html__( 'Store Name', 'rabindra-medical-hall' ); ?></label></th>
                    <td><input type="text" id="store_name" name="rmh_pharmacy_trust_settings[store_name]" value="<?php echo esc_attr( $settings['store_name'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="physical_address"><?php echo esc_html__( 'Physical Address', 'rabindra-medical-hall' ); ?></label></th>
                    <td><input type="text" id="physical_address" name="rmh_pharmacy_trust_settings[physical_address]" value="<?php echo esc_attr( $settings['physical_address'] ); ?>" class="large-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="drug_license_no"><?php echo esc_html__( 'Drug License No. (Form 20B/21B)', 'rabindra-medical-hall' ); ?></label></th>
                    <td>
                        <input type="text" id="drug_license_no" name="rmh_pharmacy_trust_settings[drug_license_no]" value="<?php echo esc_attr( $settings['drug_license_no'] ); ?>" class="regular-text" placeholder="e.g. 20B/21B/CTK/...">
                        <p class="description"><?php echo esc_html__( 'Leave blank if not yet registered. Badge is hidden until entered.', 'rabindra-medical-hall' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="drug_license_numbers"><?php echo esc_html__( 'Drug Licence Numbers (unclassified)', 'rabindra-medical-hall' ); ?></label></th>
                    <td>
                        <textarea id="drug_license_numbers" name="rmh_pharmacy_trust_settings[drug_license_numbers]" rows="3" class="large-text"><?php echo esc_textarea( $settings['drug_license_numbers'] ); ?></textarea>
                        <p class="description"><?php echo esc_html__( 'Enter only owner-verified licence numbers. Do not assign Form 20, Form 21, Schedule X, or other classifications unless independently verified.', 'rabindra-medical-hall' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gstin"><?php echo esc_html__( 'GSTIN', 'rabindra-medical-hall' ); ?></label></th>
                    <td>
                        <input type="text" id="gstin" name="rmh_pharmacy_trust_settings[gstin]" value="<?php echo esc_attr( $settings['gstin'] ); ?>" class="regular-text" placeholder="21XXXXXXXXX...">
                        <p class="description"><?php echo esc_html__( 'Shown in footer and receipts only when provided.', 'rabindra-medical-hall' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pharmacist_name"><?php echo esc_html__( 'Supervising Pharmacist Name', 'rabindra-medical-hall' ); ?></label></th>
                    <td><input type="text" id="pharmacist_name" name="rmh_pharmacy_trust_settings[pharmacist_name]" value="<?php echo esc_attr( $settings['pharmacist_name'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="pharmacist_reg_no"><?php echo esc_html__( 'Pharmacist Registration No.', 'rabindra-medical-hall' ); ?></label></th>
                    <td><input type="text" id="pharmacist_reg_no" name="rmh_pharmacy_trust_settings[pharmacist_reg_no]" value="<?php echo esc_attr( $settings['pharmacist_reg_no'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="established_year"><?php echo esc_html__( 'Year Established', 'rabindra-medical-hall' ); ?></label></th>
                    <td><input type="text" id="established_year" name="rmh_pharmacy_trust_settings[established_year]" value="<?php echo esc_attr( $settings['established_year'] ); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="whatsapp_number"><?php echo esc_html__( 'WhatsApp Business Number', 'rabindra-medical-hall' ); ?></label></th>
                    <td><input type="text" id="whatsapp_number" name="rmh_pharmacy_trust_settings[whatsapp_number]" value="<?php echo esc_attr( $settings['whatsapp_number'] ); ?>" class="regular-text" placeholder="e.g. 917205859117"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="support_phone"><?php echo esc_html__( 'Customer Support Phone', 'rabindra-medical-hall' ); ?></label></th>
                    <td><input type="text" id="support_phone" name="rmh_pharmacy_trust_settings[support_phone]" value="<?php echo esc_attr( $settings['support_phone'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="google_maps_url"><?php echo esc_html__( 'Google Business / Maps Link', 'rabindra-medical-hall' ); ?></label></th>
                    <td><input type="url" id="google_maps_url" name="rmh_pharmacy_trust_settings[google_maps_url]" value="<?php echo esc_attr( $settings['google_maps_url'] ); ?>" class="large-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="delivery_promise_mins"><?php echo esc_html__( 'Local Delivery Promise (Minutes)', 'rabindra-medical-hall' ); ?></label></th>
                    <td><input type="text" id="delivery_promise_mins" name="rmh_pharmacy_trust_settings[delivery_promise_mins]" value="<?php echo esc_attr( $settings['delivery_promise_mins'] ); ?>" class="small-text"></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/* =========================================================================
   3. PHARMACIST WORKFLOW: 5 EXACT STATUSES & PRESCRIPTION TRACKING
   ========================================================================= */

/**
 * Filter supported prescription workflow states to the 5 exact industry-standard statuses.
 */
add_filter( 'rmh_prescription_status_options_list', function() {
    return array(
        'Pending Prescription Review',
        'Prescription Under Review',
        'Approved for Fulfillment',
        'Needs Clarification',
        'Rejected / Cannot Fulfill',
    );
});

/**
 * Add custom column to WooCommerce Orders Admin table showing prescription verification status.
 */
function rmh_add_order_prescription_column( $columns ) {
    $new_cols = array();
    foreach ( $columns as $key => $title ) {
        $new_cols[ $key ] = $title;
        if ( 'order_status' === $key ) {
            $new_cols['rmh_rx_status'] = __( 'Rx Review', 'rabindra-medical-hall' );
        }
    }
    return $new_cols;
}
add_filter( 'manage_edit-shop_order_columns', 'rmh_add_order_prescription_column', 20 );

function rmh_render_order_prescription_column_content( $column, $post_id ) {
    if ( 'rmh_rx_status' !== $column ) {
        return;
    }
    $rx_status = get_post_meta( $post_id, '_rmh_prescription_status', true );
    $rx_id     = get_post_meta( $post_id, '_rmh_prescription_id', true );

    if ( ! $rx_status && ! $rx_id ) {
        echo '<span style="color:#999;">—</span>';
        return;
    }

    $color = '#666';
    if ( 'Approved for Fulfillment' === $rx_status || 'Approved' === $rx_status ) {
        $color = '#1b5e20; font-weight:bold;';
    } elseif ( 'Pending Prescription Review' === $rx_status || 'Pending Review' === $rx_status ) {
        $color = '#e65100; font-weight:bold;';
    } elseif ( 'Needs Clarification' === $rx_status ) {
        $color = '#0277bd; font-weight:bold;';
    } elseif ( 'Rejected / Cannot Fulfill' === $rx_status || 'Rejected' === $rx_status ) {
        $color = '#c62828; font-weight:bold;';
    }

    echo '<span style="color:' . esc_attr( $color ) . '">';
    echo esc_html( $rx_status ? $rx_status : 'Pending Review' );
    echo '</span>';

    if ( $rx_id ) {
        $edit_url = admin_url( 'post.php?post=' . absint( $rx_id ) . '&action=edit' );
        echo '<br><a href="' . esc_url( $edit_url ) . '" style="font-size:11px;">' . esc_html__( 'View Rx #', 'rabindra-medical-hall' ) . absint( $rx_id ) . '</a>';
    }
}
add_action( 'manage_shop_order_custom_column', 'rmh_render_order_prescription_column_content', 20, 2 );

/**
 * Attach session prescription to newly placed order.
 */
function rmh_attach_prescription_to_new_order( $order, $data ) {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) {
        return;
    }

    $rx_id = (int) WC()->session->get( 'rmh_uploaded_prescription_id' );
    $order_id = $order->get_id();

    // Check if order contains prescription items
    $has_rx = false;
    foreach ( $order->get_items() as $item ) {
        $product = $item->get_product();
        if ( $product && rmh_product_requires_prescription( $product ) ) {
            $has_rx = true;
            break;
        }
    }

    if ( $has_rx ) {
        if ( $rx_id && rmh_current_session_owns_prescription( $rx_id ) ) {
            update_post_meta( $order_id, '_rmh_prescription_id', $rx_id );
            update_post_meta( $order_id, '_rmh_prescription_status', 'Pending Prescription Review' );
            update_post_meta( $rx_id, '_rmh_order_id', $order_id );
            // Clear session key after attaching
            WC()->session->__unset( 'rmh_uploaded_prescription_id' );
        } else {
            update_post_meta( $order_id, '_rmh_prescription_status', 'Needs Prescription Upload' );
        }
    }
}
add_action( 'woocommerce_checkout_create_order', 'rmh_attach_prescription_to_new_order', 20, 2 );

/**
 * Confirm that a private prescription record belongs to the current customer
 * or, for a guest, to the active WooCommerce session.
 *
 * @param int $prescription_id Prescription post ID.
 * @return bool
 */
function rmh_current_session_owns_prescription( $prescription_id ) {
    if ( $prescription_id < 1 || 'rmh_prescription' !== get_post_type( $prescription_id ) || ! function_exists( 'WC' ) || ! WC()->session ) {
        return false;
    }

    $user_id = get_current_user_id();
    if ( $user_id > 0 && $user_id === (int) get_post_meta( $prescription_id, '_rmh_user_id', true ) ) {
        return true;
    }

    $session_key = (string) WC()->session->get_customer_id();
    return '' !== $session_key && hash_equals( (string) get_post_meta( $prescription_id, '_rmh_wc_session_key', true ), $session_key );
}

/* =========================================================================
   4. AJAX QUICK ADD STEPPER [-] [1] [+] & PRESCRIPTION MODAL
   ========================================================================= */

/**
 * Handle AJAX quick add to cart with stock and prescription checks.
 */
function rmh_ajax_quick_add() {
    check_ajax_referer( 'rmh_quick_add', 'nonce' );

    $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    $quantity   = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

    if ( ! $product_id ) {
        wp_send_json_error( array( 'message' => __( 'Invalid product.', 'rabindra-medical-hall' ) ) );
    }

    $product = wc_get_product( $product_id );
    if ( ! $product || ! $product->is_purchasable() ) {
        wp_send_json_error( array( 'message' => __( 'Product cannot be purchased.', 'rabindra-medical-hall' ) ) );
    }

    if ( ! $product->is_in_stock() ) {
        wp_send_json_error( array( 'message' => __( 'Product is out of stock.', 'rabindra-medical-hall' ) ) );
    }

    // Prescription check: routing
    if ( rmh_product_requires_prescription( $product ) ) {
        wp_send_json_success( array(
            'rx_required'  => true,
            'product_id'   => $product_id,
            'product_name' => $product->get_name(),
            'message'      => __( 'This medicine requires a doctor prescription.', 'rabindra-medical-hall' ),
        ) );
    }

    // Stock check
    if ( $product->managing_stock() ) {
        $stock = $product->get_stock_quantity();
        if ( null !== $stock && $quantity > $stock ) {
            $quantity = max( 1, $stock );
        }
    }

    $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );

    if ( $passed_validation ) {
        $cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity );
        if ( $cart_item_key ) {
            WC()->cart->calculate_totals();
            wp_send_json_success( array(
                'cart_item_key' => $cart_item_key,
                'cart_url'      => wc_get_cart_url(),
                'cart_count'    => WC()->cart->get_cart_contents_count(),
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Could not add to cart.', 'rabindra-medical-hall' ) ) );
        }
    } else {
        wp_send_json_error( array( 'message' => __( 'Could not add to cart.', 'rabindra-medical-hall' ) ) );
    }
}
add_action( 'wp_ajax_rmh_quick_add', 'rmh_ajax_quick_add' );
add_action( 'wp_ajax_nopriv_rmh_quick_add', 'rmh_ajax_quick_add' );

/** Update the quantity of a product already in the current cart. */
function rmh_ajax_update_cart_product() {
    check_ajax_referer( 'rmh_quick_add', 'nonce' );

    $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    $quantity   = isset( $_POST['quantity'] ) ? max( 0, absint( $_POST['quantity'] ) ) : 0;
    if ( ! $product_id || ! function_exists( 'WC' ) || ! WC()->cart ) {
        wp_send_json_error( array( 'message' => __( 'Your cart is unavailable. Please refresh and try again.', 'rabindra-medical-hall' ) ) );
    }

    foreach ( WC()->cart->get_cart() as $key => $item ) {
        if ( isset( $item['product_id'] ) && $product_id === (int) $item['product_id'] ) {
            if ( 0 === $quantity ) {
                WC()->cart->remove_cart_item( $key );
            } else {
                WC()->cart->set_quantity( $key, $quantity, false );
            }
            WC()->cart->calculate_totals();
            wp_send_json_success( array( 'quantity' => $quantity ) );
        }
    }

    wp_send_json_error( array( 'message' => __( 'This product is not in your cart.', 'rabindra-medical-hall' ) ) );
}
add_action( 'wp_ajax_rmh_update_cart_product', 'rmh_ajax_update_cart_product' );
add_action( 'wp_ajax_nopriv_rmh_update_cart_product', 'rmh_ajax_update_cart_product' );

/**
 * Handle AJAX quick prescription upload from modal.
 */
function rmh_ajax_upload_prescription() {
    check_ajax_referer( 'rmh_prescription_form', 'nonce' );

    $name   = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
    $mobile = isset( $_POST['mobile_number'] ) ? sanitize_text_field( wp_unslash( $_POST['mobile_number'] ) ) : '';

    if ( empty( $name ) || ! preg_match( '/^[0-9+\-\s()]{7,20}$/', $mobile ) ) {
        wp_send_json_error( array( 'message' => __( 'Please enter a valid customer name and mobile number.', 'rabindra-medical-hall' ) ) );
    }

    $has_file = ( ! empty( $_FILES['prescription_file']['tmp_name'] ) ) || ( ! empty( $_FILES['prescription_camera']['tmp_name'] ) );
    if ( ! $has_file ) {
        wp_send_json_error( array( 'message' => __( 'Please select a prescription document or capture a photo.', 'rabindra-medical-hall' ) ) );
    }

    $file = ! empty( $_FILES['prescription_file']['tmp_name'] ) ? $_FILES['prescription_file'] : $_FILES['prescription_camera'];

    // Validate PHP upload state before inspecting temporary file contents.
    if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
        wp_send_json_error( array( 'message' => __( 'The uploaded file could not be verified. Please try again.', 'rabindra-medical-hall' ) ) );
    }

    // File validation: Size <= 10 MB
    if ( (int) $file['size'] > 10 * MB_IN_BYTES ) {
        wp_send_json_error( array( 'message' => __( 'File is too large. Maximum size is 10 MB.', 'rabindra-medical-hall' ) ) );
    }

    $allowed_mimes = array(
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
    );

    // Step 1: Extension check (fast, uses allowed_mimes whitelist)
    $file_info = wp_check_filetype( $file['name'], $allowed_mimes );
    if ( ! in_array( $file_info['type'], $allowed_mimes, true ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid file format. Only JPG, PNG, and PDF are accepted.', 'rabindra-medical-hall' ) ) );
    }

    // Step 2: Real MIME check via file magic bytes (prevents renamed malicious files)
    if ( function_exists( 'finfo_open' ) ) {
        $finfo     = finfo_open( FILEINFO_MIME_TYPE );
        $real_mime = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );
        $allowed_real_mimes = array( 'image/jpeg', 'image/png', 'image/webp', 'application/pdf' );
        if ( ! in_array( $real_mime, $allowed_real_mimes, true ) ) {
            wp_send_json_error( array( 'message' => __( 'File content does not match the allowed formats (JPG, PNG, PDF).', 'rabindra-medical-hall' ) ) );
        }
    }

    // Secure private upload directory
    $upload_dir = rmh_get_private_storage_dir();
    if ( ! is_dir( $upload_dir ) || ! wp_is_writable( $upload_dir ) ) {
        wp_send_json_error( array( 'message' => __( 'Secure storage directory is not writable. Contact support.', 'rabindra-medical-hall' ) ) );
    }

    $safe_filename = wp_unique_filename( $upload_dir, wp_generate_password( 32, false, false ) . '.' . $file_info['ext'] );
    $target_path   = trailingslashit( $upload_dir ) . $safe_filename;

    if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) {
        wp_send_json_error( array( 'message' => __( 'Upload failed. Please try again.', 'rabindra-medical-hall' ) ) );
    }

    // Create prescription record
    $post_id = wp_insert_post( array(
        'post_type'   => 'rmh_prescription',
        'post_status' => 'private',
        'post_title'  => sprintf( __( 'Rx from %1$s (%2$s)', 'rabindra-medical-hall' ), $name, date_i18n( 'M j, Y H:i' ) ),
    ) );

    if ( is_wp_error( $post_id ) ) {
        @unlink( $target_path );
        wp_send_json_error( array( 'message' => __( 'Could not create prescription record.', 'rabindra-medical-hall' ) ) );
    }

    update_post_meta( $post_id, '_rmh_customer_name', $name );
    update_post_meta( $post_id, '_rmh_mobile_number', $mobile );
    update_post_meta( $post_id, '_rmh_file_path', $target_path );
    update_post_meta( $post_id, '_rmh_file_mime', $file_info['type'] );
    update_post_meta( $post_id, '_rmh_file_size', (int) $file['size'] );
    update_post_meta( $post_id, '_rmh_uploaded_at', current_time( 'mysql' ) );
    update_post_meta( $post_id, '_rmh_status', 'Pending Prescription Review' );

    if ( is_user_logged_in() ) {
        update_post_meta( $post_id, '_rmh_user_id', get_current_user_id() );
    }

    // Link to active WooCommerce session
    if ( function_exists( 'WC' ) && WC()->session ) {
        update_post_meta( $post_id, '_rmh_wc_session_key', WC()->session->get_customer_id() );
        WC()->session->set( 'rmh_uploaded_prescription_id', $post_id );
    }

    wp_send_json_success( array(
        'prescription_id' => $post_id,
        'message'         => __( 'Prescription received! Our pharmacy team will review it before dispatching your order.', 'rabindra-medical-hall' ),
    ) );
}
add_action( 'wp_ajax_rmh_ajax_upload_prescription', 'rmh_ajax_upload_prescription' );
add_action( 'wp_ajax_nopriv_rmh_ajax_upload_prescription', 'rmh_ajax_upload_prescription' );

/**
 * Render the dedicated Quick Prescription Upload Modal in footer.
 */
function rmh_render_prescription_modal() {
    ?>
    <div id="rmhPrescriptionModalBackdrop" class="rmh-modal-backdrop" hidden>
        <div id="rmhPrescriptionModal" class="rmh-location-modal rmh-rx-modal" role="dialog" aria-modal="true" aria-labelledby="rmhRxModalTitle">
            <div class="rmh-modal-header">
                <div>
                    <span class="rmh-modal-eyebrow"><?php echo esc_html__( 'Rabindra Medical Hall • Pharmacy', 'rabindra-medical-hall' ); ?></span>
                    <h2 id="rmhRxModalTitle"><?php echo esc_html__( 'Upload Doctor\'s Prescription', 'rabindra-medical-hall' ); ?></h2>
                </div>
                <button type="button" id="rmhPrescriptionModalClose" class="rmh-modal-close" aria-label="<?php echo esc_attr__( 'Close prescription modal', 'rabindra-medical-hall' ); ?>">✕</button>
            </div>
            <div class="rmh-tab-panel">
                <div class="rmh-rx-privacy-notice">
                    <span aria-hidden="true">🔒</span>
                    <div>
                        <strong><?php echo esc_html__( 'Confidential &amp; Verified', 'rabindra-medical-hall' ); ?></strong>
                        <p><?php echo esc_html__( 'Your prescription is strictly confidential and reviewed only by licensed pharmacy staff prior to medicine dispensing.', 'rabindra-medical-hall' ); ?></p>
                    </div>
                </div>

                <form id="rmhModalRxForm" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="rmh_ajax_upload_prescription">
                    <input type="hidden" id="rmhModalRxNonce" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'rmh_prescription_form' ) ); ?>">

                    <div class="rmh-form-row rmh-form-row--2col">
                        <div class="rmh-form-group">
                            <label for="modalRxName"><?php echo esc_html__( 'Patient / Customer Name', 'rabindra-medical-hall' ); ?> <span class="rmh-req">*</span></label>
                            <input type="text" id="modalRxName" name="customer_name" required placeholder="<?php echo esc_attr__( 'Full Name', 'rabindra-medical-hall' ); ?>" autocomplete="name">
                        </div>
                        <div class="rmh-form-group">
                            <label for="modalRxPhone"><?php echo esc_html__( 'Mobile Number', 'rabindra-medical-hall' ); ?> <span class="rmh-req">*</span></label>
                            <input type="tel" id="modalRxPhone" name="mobile_number" required placeholder="<?php echo esc_attr__( '10-digit Mobile', 'rabindra-medical-hall' ); ?>" inputmode="tel" autocomplete="tel">
                        </div>
                    </div>

                    <div class="rmh-form-group" style="margin-top:14px;">
                        <label for="modalRxFile"><?php echo esc_html__( 'Prescription Document / Photo', 'rabindra-medical-hall' ); ?> <span class="rmh-req">*</span></label>
                        <div class="rmh-prescription-file-actions">
                            <label class="rmh-file-action" for="modalRxFile">
                                <span>📁 <?php echo esc_html__( 'Choose Document / File', 'rabindra-medical-hall' ); ?></span>
                            </label>
                            <label class="rmh-file-action rmh-file-action--camera" for="modalRxCamera">
                                <span>📷 <?php echo esc_html__( 'Take Photo from Camera', 'rabindra-medical-hall' ); ?></span>
                            </label>
                        </div>
                        <input type="file" id="modalRxFile" name="prescription_file" accept=".jpg,.jpeg,.png,.webp,.pdf" style="display:none;">
                        <input type="file" id="modalRxCamera" name="prescription_camera" accept="image/*" capture="environment" style="display:none;">
                        <span id="modalRxFileStatus" class="rmh-field-help"><?php echo esc_html__( 'Accepted formats: JPG, PNG, WEBP, PDF up to 10 MB.', 'rabindra-medical-hall' ); ?></span>
                    </div>

                    <div id="modalRxFeedback" class="rmh-form-feedback" style="display:none;" role="status"></div>

                    <div style="margin-top:18px;">
                        <button type="submit" id="modalRxSubmitBtn" class="rmh-btn rmh-btn-primary rmh-btn-block">
                            <?php echo esc_html__( 'Submit Prescription for Review', 'rabindra-medical-hall' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}
add_action( 'wp_footer', 'rmh_render_prescription_modal', 30 );

/* =========================================================================
   5. VERIFIED BRAND SHOWCASE & QUICK SERVICES SHORTCODES
   ========================================================================= */

/**
 * Render the Trusted Manufacturers showcase using real imported product data from DB.
 * Sorted A-Z with accurate product counts and genuine brand logos.
 */
function rmh_trusted_manufacturers_shortcode() {
    // -----------------------------------------------------------------------
    // Verified manufacturer data.
    // 'db_key'  — substring used in LIKE query against _rmh_manufacturer / _brand.
    //             Must match the raw DB value (case-insensitive LIKE).
    // 'label'   — Pretty display name shown on the card and used in the filter URL.
    // 'logo'    — Filename in assets/images/manufacturers/ (verified local file).
    //
    // Only entries with a real verified local logo file are listed.
    // Manufacturers without logos are intentionally excluded from the homepage.
    // -----------------------------------------------------------------------
    $verified_manufacturers = array(
        array( 'db_key' => 'Abbott Laboratories',              'label' => 'Abbott Laboratories',              'logo' => 'abbott.svg' ),
        array( 'db_key' => 'Ajanta Pharma',                    'label' => 'Ajanta Pharma',                    'logo' => 'ajanta-pharma.png' ),
        array( 'db_key' => 'Alembic Pharmaceuticals',          'label' => 'Alembic Pharmaceuticals',          'logo' => 'alembic.png' ),
        array( 'db_key' => 'Alkem Laboratories',               'label' => 'Alkem Laboratories',               'logo' => 'alkem.svg' ),
        array( 'db_key' => 'Aristo Pharmaceuticals',           'label' => 'Aristo Pharmaceuticals',           'logo' => 'aristo-pharmaceuticals.png' ),
        array( 'db_key' => 'Cipla Limited',                    'label' => 'Cipla Limited',                    'logo' => 'cipla.svg' ),
        array( 'db_key' => 'Dr. Reddy\'s Laboratories',        'label' => 'Dr. Reddy\'s Laboratories',        'logo' => 'dr-reddys.svg' ),
        array( 'db_key' => 'Fourrts Laboratories',             'label' => 'Fourrts Laboratories',             'logo' => 'fourrts-laboratories.webp' ),
        array( 'db_key' => 'Franco',                           'label' => 'Franco Indian Pharmaceuticals',    'logo' => 'franco-indian-pharmaceuticals.png' ),
        array( 'db_key' => 'Glenmark Pharmaceuticals',         'label' => 'Glenmark Pharmaceuticals',         'logo' => 'glenmark.png' ),
        array( 'db_key' => 'Gsk',                              'label' => 'GSK',                              'logo' => 'gsk.png' ),
        array( 'db_key' => 'Indoco',                           'label' => 'Indoco Remedies',                  'logo' => 'indoco-remedies.png' ),
        array( 'db_key' => 'Intas Pharmaceuticals',            'label' => 'Intas Pharmaceuticals',            'logo' => 'intas.png' ),
        array( 'db_key' => 'Ipca Laboratories',                'label' => 'Ipca Laboratories',                'logo' => 'ipca-laboratories.svg' ),
        array( 'db_key' => 'J.b.chemical',                     'label' => 'J.B. Chemicals & Pharma',          'logo' => 'jb-chemicals.svg' ),
        array( 'db_key' => 'Lilly',                            'label' => 'Eli Lilly',                        'logo' => 'eli-lilly.svg' ),
        array( 'db_key' => 'Lupin Limited',                    'label' => 'Lupin Limited',                    'logo' => 'lupin.svg' ),
        array( 'db_key' => 'Macleods Pharmaceuticals',         'label' => 'Macleods Pharmaceuticals',         'logo' => 'macleods-pharmaceuticals.svg' ),
        array( 'db_key' => 'Mankind Pharma',                   'label' => 'Mankind Pharma',                   'logo' => 'mankind.png' ),
        array( 'db_key' => 'Medley Pharmaceuticals',           'label' => 'Medley Pharmaceuticals',           'logo' => 'medley-pharmaceuticals.jpg' ),
        array( 'db_key' => 'MSN Laboratories',                 'label' => 'MSN Laboratories',                 'logo' => 'msn-laboratories.png' ),
        array( 'db_key' => 'Natco Pharma',                     'label' => 'Natco Pharma',                     'logo' => 'natco-pharma.svg' ),
        array( 'db_key' => 'Pfizer India',                     'label' => 'Pfizer India',                     'logo' => 'pfizer-india.svg' ),
        array( 'db_key' => 'Ranbaxy Laboratories (Sun Pharma)', 'label' => 'Ranbaxy Laboratories',            'logo' => 'ranbaxy-laboratories.svg' ),
        array( 'db_key' => 'RPG Life Sciences',                'label' => 'RPG Life Sciences',                'logo' => 'rpg-life-sciences.png' ),
        array( 'db_key' => 'Sun Pharma Laboratories',          'label' => 'Sun Pharma Laboratories',          'logo' => 'sun-pharma.svg' ),
        array( 'db_key' => 'Systopic Laboratories',            'label' => 'Systopic Laboratories',            'logo' => 'systopic-laboratories.png' ),
        array( 'db_key' => 'Torrent Pharmaceuticals',          'label' => 'Torrent Pharmaceuticals',          'logo' => 'torrent.svg' ),
        array( 'db_key' => 'USV Private Limited',              'label' => 'USV Private Limited',              'logo' => 'usv-private-limited.svg' ),
        array( 'db_key' => 'Wockhardt',                        'label' => 'Wockhardt',                        'logo' => 'wockhardt.png' ),
        array( 'db_key' => 'Zydus Lifesciences',               'label' => 'Zydus Lifesciences',               'logo' => 'zydus.svg' ),
        // Micro Labs: logo (micro-labs.jpg) is 1.2 MB — excluded for homepage performance.
    );

    // -----------------------------------------------------------------------
    // Manufacturer directory paths
    // -----------------------------------------------------------------------
    $mfg_dir_uri  = trailingslashit( set_url_scheme( get_stylesheet_directory_uri(), function_exists( 'rmh_is_secure_request' ) && rmh_is_secure_request() ? 'https' : 'http' ) ) . 'assets/images/manufacturers/';
    $mfg_dir_path = trailingslashit( get_stylesheet_directory() )     . 'assets/images/manufacturers/';

    // -----------------------------------------------------------------------
    // Try transient cache first (6 h). Falls back to live DB query.
    // -----------------------------------------------------------------------
    $transient_key = 'rmh_trusted_mfg_v4';
    $brands        = get_transient( $transient_key );

    if ( false === $brands ) {
        global $wpdb;
        $brands = array();

        foreach ( $verified_manufacturers as $mfg ) {
            $logo_file    = $mfg['logo'];
            $display_name = $mfg['label'];
            $db_key       = $mfg['db_key'];

            // Skip if logo file does not exist on disk (safety net).
            if ( ! file_exists( $mfg_dir_path . $logo_file ) ) {
                continue;
            }

            // Query live product count.
            // Match on _rmh_manufacturer (primary) or _brand (fallback) using LIKE,
            // so partial keys like "Franco" also match "Franco India", "Franco-indian".
            $like = '%' . $wpdb->esc_like( $db_key ) . '%';

            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type   = 'product'
                   AND p.post_status = 'publish'
                   AND pm.meta_key   IN ('_rmh_manufacturer', '_brand')
                   AND pm.meta_value LIKE %s",
                $like
            ) );

            // Skip manufacturers with no published products.
            if ( $count < 1 ) {
                continue;
            }

            $brands[] = array(
                'name'  => $display_name,
                'count' => $count,
                'logo'  => esc_url( $mfg_dir_uri . $logo_file ),
                // Filter URL uses db_key so existing catalog filter (LIKE) still works.
                'filter_key' => $db_key,
            );
        }

        // Sort A-Z by display label.
        usort( $brands, function( $a, $b ) {
            return strcmp( $a['name'], $b['name'] );
        } );

        // Enforce maximum of 24 on homepage.
        $brands = array_slice( $brands, 0, 24 );

        // Cache for 6 hours.
        set_transient( $transient_key, $brands, 6 * HOUR_IN_SECONDS );
    }

    ob_start();
    ?>
    <section class="rmh-section rmh-brands-section" aria-labelledby="rmh-brands-heading">
        <div class="rmh-section-heading">
            <span class="rmh-section-label"><?php echo esc_html__( 'TRUSTED MANUFACTURERS', 'rabindra-medical-hall' ); ?></span>
            <h2 id="rmh-brands-heading"><?php echo esc_html__( 'Explore Medicines by Manufacturer', 'rabindra-medical-hall' ); ?></h2>
            <p><?php echo esc_html__( 'Explore medicines from trusted pharmaceutical manufacturers.', 'rabindra-medical-hall' ); ?></p>
        </div>
        <div class="rmh-brands-grid">
            <?php foreach ( $brands as $brand ) : ?>
                <a class="rmh-brand-card" href="<?php echo esc_url( add_query_arg( 'rmh_manufacturer', rawurlencode( $brand['filter_key'] ), home_url( '/shop/' ) ) ); ?>" title="<?php echo esc_attr( sprintf( __( 'Browse medicines by %s', 'rabindra-medical-hall' ), $brand['name'] ) ); ?>">
                    <div class="rmh-brand-card__logo-wrap<?php echo empty( $brand['logo'] ) ? ' rmh-brand-card__logo-wrap--text' : ''; ?>">
                        <?php if ( ! empty( $brand['logo'] ) ) : ?>
                            <img
                                class="rmh-brand-card__logo"
                                src="<?php echo esc_url( $brand['logo'] ); ?>"
                                alt="<?php echo esc_attr( sprintf( __( '%s logo', 'rabindra-medical-hall' ), $brand['name'] ) ); ?>"
                                loading="lazy"
                                decoding="async"
                                width="140"
                                height="48"
                                style="aspect-ratio: 140 / 48;"
                            >
                        <?php else : ?>
                            <span class="rmh-brand-card__emblem" aria-hidden="true"><?php echo esc_html( mb_substr( $brand['name'], 0, 2 ) ); ?></span>
                        <?php endif; ?>
                    </div>
                    <strong class="rmh-brand-card__name"><?php echo esc_html( $brand['name'] ); ?></strong>
                    <span class="rmh-brand-card__count"><?php echo esc_html( sprintf( _n( '%s Medicine', '%s Medicines', $brand['count'], 'rabindra-medical-hall' ), number_format_i18n( $brand['count'] ) ) ); ?></span>
                    <span class="rmh-brand-card__link"><?php echo esc_html__( 'View Medicines →', 'rabindra-medical-hall' ); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode( 'rmh_trusted_manufacturers', 'rmh_trusted_manufacturers_shortcode' );
add_shortcode( 'rmh_pharma_brands', 'rmh_trusted_manufacturers_shortcode' );

/**
 * Render the 4 Quick Services cards under the hero.
 */
function rmh_quick_services_shortcode() {
    $trust = rmh_get_trust_settings();
    ob_start();
    ?>
    <section class="rmh-section rmh-quick-services-section" aria-label="<?php echo esc_attr__( 'Quick pharmacy services', 'rabindra-medical-hall' ); ?>">
        <div class="rmh-quick-services-grid">
            <div class="rmh-qs-card rmh-qs-card--rx" id="rmhTriggerRxModal">
                <span class="rmh-qs-card__icon" aria-hidden="true">📄</span>
                <div class="rmh-qs-card__content">
                    <span class="rmh-qs-card__kicker"><?php echo esc_html__( 'Fast &amp; Easy', 'rabindra-medical-hall' ); ?></span>
                    <h3><?php echo esc_html__( 'Order with Prescription', 'rabindra-medical-hall' ); ?></h3>
                    <p><?php echo esc_html__( 'Upload photo from camera or file. Our pharmacist reviews and fulfills your order.', 'rabindra-medical-hall' ); ?></p>
                    <button type="button" class="rmh-qs-btn rmh-qs-btn--rx"><?php echo esc_html__( 'Upload Now →', 'rabindra-medical-hall' ); ?></button>
                </div>
            </div>

            <div class="rmh-qs-card rmh-qs-card--delivery" id="rmhTriggerDeliveryModal">
                <span class="rmh-qs-card__icon" aria-hidden="true">⚡</span>
                <div class="rmh-qs-card__content">
                    <span class="rmh-qs-card__kicker"><?php echo esc_html__( 'Local Cuttack', 'rabindra-medical-hall' ); ?></span>
                    <h3><?php echo esc_html__( 'Fast Local Delivery', 'rabindra-medical-hall' ); ?></h3>
                    <p><?php echo esc_html__( 'Fast delivery within 10 km of our Badambadi Old Bus Stand pharmacy. Delivery availability depends on location, medicine availability and pharmacy operating hours.', 'rabindra-medical-hall' ); ?></p>
                    <button type="button" class="rmh-qs-btn"><?php echo esc_html__( 'Check PIN Code →', 'rabindra-medical-hall' ); ?></button>
                </div>
            </div>

            <div class="rmh-qs-card rmh-qs-card--wa">
                <span class="rmh-qs-card__icon" aria-hidden="true">💬</span>
                <div class="rmh-qs-card__content">
                    <span class="rmh-qs-card__kicker"><?php echo esc_html__( 'Direct Support', 'rabindra-medical-hall' ); ?></span>
                    <h3><?php echo esc_html__( 'WhatsApp Pharmacist', 'rabindra-medical-hall' ); ?></h3>
                    <p><?php echo esc_html__( 'Need help with dosage, substitutes, or ordering? Message our pharmacy team.', 'rabindra-medical-hall' ); ?></p>
                    <a class="rmh-qs-btn rmh-qs-btn--wa" href="<?php echo esc_url( rmh_get_whatsapp_url( 'Hello Rabindra Medical Hall, I need help ordering medicines.' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Chat on WhatsApp →', 'rabindra-medical-hall' ); ?></a>
                </div>
            </div>

            <div class="rmh-qs-card rmh-qs-card--trust">
                <span class="rmh-qs-card__icon" aria-hidden="true">🛡️</span>
                <div class="rmh-qs-card__content">
                    <span class="rmh-qs-card__kicker"><?php echo esc_html( ! empty( $trust['drug_license_no'] ) ? __( 'Licensed & Trusted', 'rabindra-medical-hall' ) : __( 'Community Pharmacy', 'rabindra-medical-hall' ) ); ?></span>
                    <h3><?php echo esc_html__( 'Local Pharmacy Store', 'rabindra-medical-hall' ); ?></h3>
                    <p>
                        <?php
                        $heritage_parts = array();
                        if ( ! empty( $trust['established_year'] ) ) {
                            $heritage_parts[] = sprintf( __( 'Serving Cuttack since %s.', 'rabindra-medical-hall' ), esc_html( $trust['established_year'] ) );
                        } else {
                            $heritage_parts[] = __( 'Physical pharmacy store at Badambadi Old Bus Stand, Cuttack.', 'rabindra-medical-hall' );
                        }
                        if ( ! empty( $trust['pharmacist_reg_no'] ) ) {
                            $heritage_parts[] = __( 'Supervised by registered pharmacist.', 'rabindra-medical-hall' );
                        } else {
                            $heritage_parts[] = __( 'Genuine healthcare essentials and verified medicines.', 'rabindra-medical-hall' );
                        }
                        echo esc_html( implode( ' ', $heritage_parts ) );
                        ?>
                    </p>
                    <a class="rmh-qs-btn" href="<?php echo esc_url( $trust['google_maps_url'] ? $trust['google_maps_url'] : '#rmh-store-location' ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Find Our Store →', 'rabindra-medical-hall' ); ?></a>
                </div>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode( 'rmh_quick_services', 'rmh_quick_services_shortcode' );

/**
 * Render verified Pharmacy Trust & Regulatory Section.
 * Strictly hides unsupplied credentials to maintain regulatory integrity.
 */
function rmh_trust_regulatory_shortcode() {
    $trust = rmh_get_trust_settings();
    $license_numbers = ! empty( $trust['drug_license_numbers'] ) ? preg_split( '/\r\n|\r|\n/', (string) $trust['drug_license_numbers'] ) : array();
    if ( empty( $license_numbers ) && ! empty( $trust['drug_license_no'] ) ) {
        $license_numbers = array( $trust['drug_license_no'] );
    }
    $license_numbers = array_values( array_filter( array_map( 'trim', $license_numbers ) ) );
    $has_license    = ! empty( $license_numbers );
    $has_gstin      = ! empty( $trust['gstin'] );
    $has_pharmacist = ! empty( $trust['pharmacist_reg_no'] ) || ! empty( $trust['pharmacist_name'] );

    ob_start();
    ?>
    <section class="rmh-section rmh-trust-regulatory-section" id="rmh-trust-section" aria-labelledby="rmh-trust-heading">
        <div class="rmh-section-heading">
            <span class="rmh-section-label"><?php echo esc_html( $has_license ? __( 'LICENSED PHARMACY', 'rabindra-medical-hall' ) : __( 'PHARMACY COMPLIANCE', 'rabindra-medical-hall' ) ); ?></span>
            <h2 id="rmh-trust-heading"><?php echo esc_html__( 'Trust, Safety & Standards', 'rabindra-medical-hall' ); ?></h2>
            <p><?php echo esc_html__( 'Patient safety is our primary responsibility. Genuine medicines and compliant dispensing.', 'rabindra-medical-hall' ); ?></p>
        </div>
        <div class="rmh-trust-cards-grid">
            <div class="rmh-trust-card">
                <div class="rmh-trust-card__icon" aria-hidden="true">📜</div>
                <h3><?php echo esc_html( $has_license ? __( 'Licensed Retail Pharmacy', 'rabindra-medical-hall' ) : __( 'Retail Pharmacy Compliance', 'rabindra-medical-hall' ) ); ?></h3>
                <p><?php echo esc_html__( 'Operated by Rabindra Medical Hall under applicable Drugs & Cosmetics regulations.', 'rabindra-medical-hall' ); ?></p>
                <?php if ( $has_license ) : ?>
                    <div class="rmh-trust-card__meta">
                        <strong><?php echo esc_html__( 'Drug Licence Numbers:', 'rabindra-medical-hall' ); ?></strong>
                        <?php foreach ( $license_numbers as $license_number ) : ?>
                            <code><?php echo esc_html( $license_number ); ?></code>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ( $has_gstin ) : ?>
                    <div class="rmh-trust-card__meta">
                        <strong><?php echo esc_html__( 'GSTIN:', 'rabindra-medical-hall' ); ?></strong>
                        <code><?php echo esc_html( $trust['gstin'] ); ?></code>
                    </div>
                <?php endif; ?>
            </div>

            <div class="rmh-trust-card">
                <div class="rmh-trust-card__icon" aria-hidden="true">👨‍⚕️</div>
                <h3><?php echo esc_html( $has_pharmacist ? __( 'Registered Pharmacist Verification', 'rabindra-medical-hall' ) : __( 'Prescription Verification Workflow', 'rabindra-medical-hall' ) ); ?></h3>
                <p><?php echo esc_html__( 'All prescription medicines are reviewed prior to dispensing to ensure dosage accuracy and patient safety.', 'rabindra-medical-hall' ); ?></p>
                <?php if ( ! empty( $trust['pharmacist_name'] ) ) : ?>
                    <div class="rmh-trust-card__meta">
                        <strong><?php echo esc_html__( 'Pharmacist in Charge:', 'rabindra-medical-hall' ); ?></strong>
                        <span><?php echo esc_html( $trust['pharmacist_name'] ); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $trust['pharmacist_reg_no'] ) ) : ?>
                    <div class="rmh-trust-card__meta">
                        <strong><?php echo esc_html__( 'Registration No:', 'rabindra-medical-hall' ); ?></strong>
                        <code><?php echo esc_html( $trust['pharmacist_reg_no'] ); ?></code>
                    </div>
                <?php endif; ?>
            </div>

            <div class="rmh-trust-card">
                <div class="rmh-trust-card__icon" aria-hidden="true">📍</div>
                <h3><?php echo esc_html__( 'Physical Store', 'rabindra-medical-hall' ); ?></h3>
                <p>
                    <?php
                    if ( ! empty( $trust['established_year'] ) ) {
                        printf( esc_html__( 'Serving the community of Cuttack since %s with genuine medicines and healthcare advice.', 'rabindra-medical-hall' ), esc_html( $trust['established_year'] ) );
                    } else {
                        echo esc_html__( 'A neighborhood medical store at Badambadi Old Bus Stand, Cuttack offering genuine medicines and personal healthcare advice.', 'rabindra-medical-hall' );
                    }
                    ?>
                </p>
                <?php if ( ! empty( $trust['physical_address'] ) ) : ?>
                    <div class="rmh-trust-card__meta">
                        <strong><?php echo esc_html__( 'Address:', 'rabindra-medical-hall' ); ?></strong>
                        <span><?php echo esc_html( $trust['physical_address'] ); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode( 'rmh_trust_regulatory', 'rmh_trust_regulatory_shortcode' );

/* =========================================================================
   6B. ONE-HOUR DELIVERY REVIEW & CUSTOMER FEEDBACK COMPONENT (PART L)
   ========================================================================= */

/**
 * Register rmh_delivery_review post type for genuine customer feedback.
 */
function rmh_register_delivery_review_cpt() {
    register_post_type( 'rmh_delivery_review', array(
        'labels' => array(
            'name'          => __( 'Delivery Feedback', 'rabindra-medical-hall' ),
            'singular_name' => __( 'Delivery Feedback', 'rabindra-medical-hall' ),
        ),
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => 'woocommerce',
        'capability_type' => 'post',
        'hierarchical'    => false,
        'supports'        => array( 'title', 'custom-fields' ),
    ) );
}
add_action( 'init', 'rmh_register_delivery_review_cpt' );

/**
 * Handle AJAX submission of customer delivery feedback.
 */
function rmh_ajax_delivery_feedback() {
    check_ajax_referer( 'rmh_delivery_feedback', 'nonce' );

    $rating  = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
    $speed   = isset( $_POST['speed'] ) ? sanitize_text_field( wp_unslash( $_POST['speed'] ) ) : '';
    $service = isset( $_POST['service'] ) ? sanitize_text_field( wp_unslash( $_POST['service'] ) ) : '';
    $comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';
    $order_ref = isset( $_POST['order_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['order_ref'] ) ) : '';

    if ( $rating < 0 || $rating > 5 ) {
        wp_send_json_error( array( 'message' => __( 'Invalid feedback value.', 'rabindra-medical-hall' ) ) );
    }

    $post_id = wp_insert_post( array(
        'post_type'   => 'rmh_delivery_review',
        'post_status' => 'pending', // Moderated: only genuine reviews approved by store staff
        'post_title'  => sprintf( __( 'Delivery Feedback (%s)', 'rabindra-medical-hall' ), date_i18n( 'M j, Y' ) ),
    ) );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Could not submit feedback. Please try again.', 'rabindra-medical-hall' ) ) );
    }

    if ( $rating > 0 ) {
        update_post_meta( $post_id, '_rmh_rating', $rating );
    }
    update_post_meta( $post_id, '_rmh_speed', $speed );
    update_post_meta( $post_id, '_rmh_service', $service );
    update_post_meta( $post_id, '_rmh_comment', $comment );
    update_post_meta( $post_id, '_rmh_order_ref', $order_ref );
    update_post_meta( $post_id, '_rmh_submitted_at', current_time( 'mysql' ) );

    wp_send_json_success( array(
        'message' => __( 'Thank you! Your delivery feedback has been received for team review.', 'rabindra-medical-hall' ),
    ) );
}
add_action( 'wp_ajax_rmh_delivery_feedback', 'rmh_ajax_delivery_feedback' );
add_action( 'wp_ajax_nopriv_rmh_delivery_feedback', 'rmh_ajax_delivery_feedback' );

/**
 * Render customer feedback form: "How was your delivery?"
 */
function rmh_delivery_feedback_shortcode() {
    ob_start();
    ?>
    <div class="rmh-delivery-feedback-card" id="rmh-delivery-feedback">
        <h3><?php echo esc_html__( 'Share feedback about your local delivery', 'rabindra-medical-hall' ); ?></h3>
        <p class="rmh-feedback-intro"><?php echo esc_html__( 'Help us serve Cuttack better. Share your experience with our local pharmacy delivery team.', 'rabindra-medical-hall' ); ?></p>

        <form id="rmhDeliveryFeedbackForm" method="post">
            <input type="hidden" name="action" value="rmh_delivery_feedback">
            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'rmh_delivery_feedback' ) ); ?>">
            <div class="rmh-feedback-grid">
                <div class="rmh-form-group">
                    <label for="rmhFeedbackSpeed"><?php echo esc_html__( 'Delivery Speed', 'rabindra-medical-hall' ); ?></label>
                    <select id="rmhFeedbackSpeed" name="speed" class="rmh-feedback-select">
                        <option value="Prompt and on time" selected><?php echo esc_html__( 'Prompt and on time', 'rabindra-medical-hall' ); ?></option>
                        <option value="Faster than expected"><?php echo esc_html__( 'Faster than expected', 'rabindra-medical-hall' ); ?></option>
                        <option value="Slight delay"><?php echo esc_html__( 'Slight delay', 'rabindra-medical-hall' ); ?></option>
                        <option value="Needs improvement"><?php echo esc_html__( 'Needs improvement', 'rabindra-medical-hall' ); ?></option>
                    </select>
                </div>
                <div class="rmh-form-group">
                    <label for="rmhFeedbackService"><?php echo esc_html__( 'Staff & Packaging', 'rabindra-medical-hall' ); ?></label>
                    <select id="rmhFeedbackService" name="service" class="rmh-feedback-select">
                        <option value="Excellent" selected><?php echo esc_html__( 'Excellent &amp; Secure', 'rabindra-medical-hall' ); ?></option>
                        <option value="Good"><?php echo esc_html__( 'Good', 'rabindra-medical-hall' ); ?></option>
                        <option value="Satisfactory"><?php echo esc_html__( 'Satisfactory', 'rabindra-medical-hall' ); ?></option>
                        <option value="Needs Improvement"><?php echo esc_html__( 'Needs Improvement', 'rabindra-medical-hall' ); ?></option>
                    </select>
                </div>
            </div>

            <div class="rmh-form-group">
                <label for="rmhFeedbackComment"><?php echo esc_html__( 'Comments or Suggestions (Optional)', 'rabindra-medical-hall' ); ?></label>
                <textarea id="rmhFeedbackComment" name="comment" class="rmh-feedback-textarea" placeholder="<?php echo esc_attr__( 'Tell us how we did...', 'rabindra-medical-hall' ); ?>"></textarea>
            </div>

            <button type="submit" class="rmh-btn rmh-btn-primary" id="rmhSubmitFeedbackBtn">
                <?php echo esc_html__( 'Submit Feedback', 'rabindra-medical-hall' ); ?>
            </button>
            <div id="rmhFeedbackResponse" class="rmh-form-feedback" style="display:none;margin-top:10px;"></div>

            <small class="rmh-feedback-disclaimer">
                <?php echo esc_html__( 'Notice: Fast local delivery applies to eligible local orders and serviceable locations in Cuttack (within 10 km). Delivery availability depends on location, medicine availability and pharmacy operating hours. Feedback is reviewed by our team and is not published as a verified review automatically.', 'rabindra-medical-hall' ); ?>
            </small>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'rmh_delivery_feedback', 'rmh_delivery_feedback_shortcode' );

/**
 * Handle secure AJAX lookup for Real WooCommerce Order Tracking.
 * Verifies Order Number + (10-digit Phone OR Customer Email) against real DB orders.
 */
function rmh_ajax_track_order() {
    check_ajax_referer( 'rmh_track_order_nonce', 'nonce' );

    $raw_order_id = isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : '';
    $raw_contact  = isset( $_POST['order_contact'] ) ? sanitize_text_field( wp_unslash( $_POST['order_contact'] ) ) : '';

    $clean_order_num = preg_replace( '/[^0-9]/', '', $raw_order_id );
    $order_id        = absint( $clean_order_num );

    if ( ! $order_id || empty( $raw_contact ) ) {
        wp_send_json_error( array(
            'message' => __( 'Please enter both your Order Number and verified Mobile Number or Email Address.', 'rabindra-medical-hall' ),
        ) );
    }

    if ( ! function_exists( 'wc_get_order' ) ) {
        wp_send_json_error( array(
            'message' => __( 'Order tracking service is currently unavailable. Please contact the pharmacy team.', 'rabindra-medical-hall' ),
        ) );
    }

    $order = wc_get_order( $order_id );

    // Generic error response to avoid leaking order existence
    $generic_error = __( 'We couldn\'t find a matching order. Please check your order number and phone/email and try again.', 'rabindra-medical-hall' );

    if ( ! $order || ! is_a( $order, 'WC_Order' ) || 'trash' === $order->get_status() ) {
        wp_send_json_error( array( 'message' => $generic_error ) );
    }

    // Normalize inputs for strict verification
    $input_digits = preg_replace( '/[^0-9]/', '', $raw_contact );
    $input_email  = strtolower( trim( sanitize_email( $raw_contact ) ) );

    $billing_phone = preg_replace( '/[^0-9]/', '', (string) $order->get_billing_phone() );
    $billing_email = strtolower( trim( (string) $order->get_billing_email() ) );

    // Check phone match (allowing matching with/without country code prefix)
    $phone_matched = false;
    if ( strlen( $input_digits ) >= 10 && strlen( $billing_phone ) >= 10 ) {
        $input_last10   = substr( $input_digits, -10 );
        $billing_last10 = substr( $billing_phone, -10 );
        if ( $input_last10 === $billing_last10 ) {
            $phone_matched = true;
        }
    }

    // Check email match
    $email_matched = false;
    if ( ! empty( $input_email ) && is_email( $input_email ) && ! empty( $billing_email ) ) {
        if ( $input_email === $billing_email ) {
            $email_matched = true;
        }
    }

    if ( ! $phone_matched && ! $email_matched ) {
        wp_send_json_error( array( 'message' => $generic_error ) );
    }

    // Verification succeeded! Extract real WooCommerce order data safely
    $status_key   = $order->get_status();
    $status_label = wc_get_order_status_name( $status_key );
    $date_created = $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) ) : '';

    $items = array();
    foreach ( $order->get_items() as $item ) {
        $items[] = array(
            'name'     => $item->get_name(),
            'quantity' => $item->get_quantity(),
            'subtotal' => wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) ),
        );
    }

    $shipping_method = $order->get_shipping_method();
    if ( empty( $shipping_method ) ) {
        $shipping_method = __( 'Standard Local Delivery', 'rabindra-medical-hall' );
    }

    // Build realistic milestone status history based strictly on actual order state
    $milestones = array();
    $milestones[] = array(
        'title'     => __( 'Order Received', 'rabindra-medical-hall' ),
        'desc'      => __( 'Your order was successfully received by Rabindra Medical Hall.', 'rabindra-medical-hall' ),
        'completed' => true,
    );

    if ( in_array( $status_key, array( 'processing', 'completed', 'driver-assigned', 'out-for-delivery' ), true ) ) {
        $milestones[] = array(
            'title'     => __( 'Prescription & Order Verified', 'rabindra-medical-hall' ),
            'desc'      => __( 'Medicines verified and packed by our pharmacy team.', 'rabindra-medical-hall' ),
            'completed' => true,
        );
    } else if ( 'pending' === $status_key || 'on-hold' === $status_key ) {
        $milestones[] = array(
            'title'     => __( 'Under Verification', 'rabindra-medical-hall' ),
            'desc'      => __( 'Pharmacist reviewing order details and prescription.', 'rabindra-medical-hall' ),
            'completed' => false,
            'current'   => true,
        );
    }

    if ( in_array( $status_key, array( 'out-for-delivery', 'completed' ), true ) ) {
        $milestones[] = array(
            'title'     => __( 'Dispatched for Delivery', 'rabindra-medical-hall' ),
            'desc'      => __( 'Out for local delivery / dispatched with delivery team.', 'rabindra-medical-hall' ),
            'completed' => true,
        );
    }

    if ( 'completed' === $status_key ) {
        $milestones[] = array(
            'title'     => __( 'Delivered / Completed', 'rabindra-medical-hall' ),
            'desc'      => __( 'Package successfully delivered to your address.', 'rabindra-medical-hall' ),
            'completed' => true,
        );
    } elseif ( in_array( $status_key, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
        $milestones[] = array(
            'title'     => $status_label,
            'desc'      => sprintf( __( 'Order status: %s.', 'rabindra-medical-hall' ), $status_label ),
            'completed' => false,
            'is_error'  => true,
        );
    }

    wp_send_json_success( array(
        'order_number'    => $order->get_order_number(),
        'order_date'      => $date_created,
        'status_key'      => $status_key,
        'status_label'    => $status_label,
        'total_formatted' => $order->get_formatted_order_total(),
        'shipping_method' => $shipping_method,
        'items'           => $items,
        'milestones'      => $milestones,
    ) );
}
add_action( 'wp_ajax_rmh_track_order', 'rmh_ajax_track_order' );
add_action( 'wp_ajax_nopriv_rmh_track_order', 'rmh_ajax_track_order' );

/**
 * Render combined 50/50 section: Delivery Rating + Live Order Tracking.
 */
function rmh_delivery_and_tracking_shortcode() {
    ob_start();
    ?>
    <section class="rmh-section rmh-delivery-tracking-section" aria-labelledby="rmh-dt-heading">
        <div class="rmh-section-heading">
            <span class="rmh-section-label"><?php echo esc_html__( 'CUSTOMER SERVICE & TRACKING', 'rabindra-medical-hall' ); ?></span>
        <h2 id="rmh-dt-heading"><?php echo esc_html__( 'Delivery Feedback &amp; Live Order Tracking', 'rabindra-medical-hall' ); ?></h2>
            <p><?php echo esc_html__( 'Rate your local delivery experience or track the live status of your medicine order with Rabindra Medical Hall.', 'rabindra-medical-hall' ); ?></p>
        </div>
        <div class="rmh-dt-grid">
            <div class="rmh-dt-col rmh-dt-col--feedback">
                <?php echo rmh_delivery_feedback_shortcode(); ?>
            </div>
            <div class="rmh-dt-col rmh-dt-col--tracking">
                <div class="rmh-delivery-feedback-card rmh-tracking-card" id="rmh-order-tracking">
                    <h3><?php echo esc_html__( 'Track Your Order', 'rabindra-medical-hall' ); ?></h3>
                    <p class="rmh-feedback-intro"><?php echo esc_html__( 'Enter your Order Number and the Mobile Number or Email Address used at checkout to check live fulfillment status.', 'rabindra-medical-hall' ); ?></p>

                    <form id="rmhOrderTrackingForm" method="post">
                        <input type="hidden" name="action" value="rmh_track_order">
                        <input type="hidden" id="rmhTrackOrderNonce" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'rmh_track_order_nonce' ) ); ?>">

                        <div class="rmh-form-group">
                            <label for="rmhTrackOrderNumber"><?php echo esc_html__( 'Order Number', 'rabindra-medical-hall' ); ?> <span class="rmh-req">*</span></label>
                            <input type="text" id="rmhTrackOrderNumber" name="order_number" required placeholder="<?php echo esc_attr__( 'e.g. 1024', 'rabindra-medical-hall' ); ?>" autocomplete="off" class="rmh-feedback-input">
                        </div>

                        <div class="rmh-form-group" style="margin-top:12px;">
                            <label for="rmhTrackContact"><?php echo esc_html__( 'Phone Number or Email Address', 'rabindra-medical-hall' ); ?> <span class="rmh-req">*</span></label>
                            <input type="text" id="rmhTrackContact" name="order_contact" required placeholder="<?php echo esc_attr__( '10-digit Mobile or Email Address', 'rabindra-medical-hall' ); ?>" autocomplete="email" class="rmh-feedback-input">
                        </div>

                        <div style="margin-top:18px;">
                            <button type="submit" class="rmh-btn rmh-btn-primary" id="rmhTrackSubmitBtn">
                                <?php echo esc_html__( 'Track Order', 'rabindra-medical-hall' ); ?>
                            </button>
                        </div>

                        <div id="rmhTrackFeedback" class="rmh-form-feedback" style="display:none;margin-top:12px;" role="status"></div>
                    </form>

                    <div id="rmhTrackResultContainer" class="rmh-track-result" style="display:none;" aria-live="polite"></div>
                </div>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode( 'rmh_delivery_and_tracking', 'rmh_delivery_and_tracking_shortcode' );

/* =========================================================================
   7. CART & CHECKOUT RX INTEGRATION
   ========================================================================= */

/**
 * Add red Rx pill indicator next to prescription medicines in cart and checkout review.
 */
function rmh_render_cart_item_rx_badge( $item_name, $cart_item, $cart_item_key ) {
    $product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
    if ( $product instanceof WC_Product && rmh_product_requires_prescription( $product ) ) {
        $badge = '<span class="rmh-rx-pill-inline" title="' . esc_attr__( 'Prescription required for this medicine', 'rabindra-medical-hall' ) . '">Rx</span>';
        return $badge . ' ' . $item_name;
    }
    return $item_name;
}
add_filter( 'woocommerce_cart_item_name', 'rmh_render_cart_item_rx_badge', 20, 3 );
add_filter( 'woocommerce_order_item_name', 'rmh_render_cart_item_rx_badge', 20, 3 );

/**
 * Pass quick add nonce to custom frontend script.
 */
function rmh_localize_quick_add_script() {
    $data = array(
        'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
        'nonce'      => wp_create_nonce( 'rmh_quick_add' ),
        'rxNonce'    => wp_create_nonce( 'rmh_prescription_form' ),
        'trackNonce' => wp_create_nonce( 'rmh_track_order_nonce' ),
        'viewCart'   => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
        'strings'    => array(
            'added'       => __( 'Added!', 'rabindra-medical-hall' ),
            'outOfStock'  => __( 'Out of stock', 'rabindra-medical-hall' ),
            'rxRequired'  => __( 'Doctor prescription required', 'rabindra-medical-hall' ),
        ),
    );
    wp_localize_script( 'rmh-custom-js', 'rmhQuickAdd', $data );
    wp_localize_script( 'rmh-custom-script', 'rmhQuickAdd', $data );
}
add_action( 'wp_enqueue_scripts', 'rmh_localize_quick_add_script', 30 );
