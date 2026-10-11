<?php
/**
 * Rabindra Medical Hall — WhatsApp Cloud API Order Notification
 *
 * Architecture
 * ------------
 * Sensitive credentials   wp-config.php constants take priority over DB.
 * Non-sensitive settings  WooCommerce Settings -> "WhatsApp Notify" tab.
 * Trigger                 woocommerce_order_status_changed to processing|completed
 *                         + woocommerce_payment_complete (safety net).
 * Deduplication           Transient lock (60 s) + persistent notification_status meta.
 * Notification meta keys  _rmh_wa_notification_status   (pending|sent|failed)
 *                         _rmh_wa_notification_message_id
 *                         _rmh_wa_notification_sent_at
 *                         _rmh_wa_notification_error
 * Template variables      {{1}}-{{11}}: see rmh_wa_build_template_params().
 * Prescription detection  uses existing rmh_product_requires_prescription().
 * Token safety            never exposed in frontend HTML, JS, REST, or logs.
 *
 * @package RabindraMedicalHall
 * @since   2.1.0
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
   1. CONSTANTS / OPTION PREFIX
   ========================================================================= */

if ( ! defined( 'RMH_WA_OPTION_PREFIX' ) ) {
    define( 'RMH_WA_OPTION_PREFIX', 'rmh_wa_' );
}
if ( ! defined( 'RMH_WA_STATUS_PENDING' ) ) {
    define( 'RMH_WA_STATUS_PENDING', 'pending' );
    define( 'RMH_WA_STATUS_SENT',    'sent'    );
    define( 'RMH_WA_STATUS_FAILED',  'failed'  );
}

/* =========================================================================
   2. CREDENTIAL RESOLUTION
   Sensitive values come from wp-config.php constants. Non-sensitive settings
   (recipient, template name, template language, enabled) come from the DB.
   The access token is NEVER written to any log, output, or REST response.
   ========================================================================= */

function rmh_wa_get_access_token() {
    if ( defined( 'RMH_WHATSAPP_ACCESS_TOKEN' ) && '' !== RMH_WHATSAPP_ACCESS_TOKEN ) {
        return (string) RMH_WHATSAPP_ACCESS_TOKEN;
    }
    return trim( (string) get_option( RMH_WA_OPTION_PREFIX . 'access_token', '' ) );
}

function rmh_wa_get_phone_number_id() {
    if ( defined( 'RMH_WHATSAPP_PHONE_NUMBER_ID' ) && '' !== RMH_WHATSAPP_PHONE_NUMBER_ID ) {
        return (string) RMH_WHATSAPP_PHONE_NUMBER_ID;
    }
    return trim( (string) get_option( RMH_WA_OPTION_PREFIX . 'phone_number_id', '' ) );
}

function rmh_wa_get_api_version() {
    if ( defined( 'RMH_WHATSAPP_API_VERSION' ) && '' !== RMH_WHATSAPP_API_VERSION ) {
        return (string) RMH_WHATSAPP_API_VERSION;
    }
    return trim( (string) get_option( RMH_WA_OPTION_PREFIX . 'api_version', 'v20.0' ) );
}

function rmh_wa_opt( $key, $default = '' ) {
    return trim( (string) get_option( RMH_WA_OPTION_PREFIX . $key, $default ) );
}

function rmh_wa_is_configured() {
    return (
        'yes' === rmh_wa_opt( 'enabled', 'no' )
        && '' !== rmh_wa_get_api_version()
        && '' !== rmh_wa_get_phone_number_id()
        && '' !== rmh_wa_get_access_token()
        && '' !== rmh_wa_opt( 'recipient_number' )
        && '' !== rmh_wa_opt( 'template_name', 'rmh_new_order' )
    );
}

/* =========================================================================
   3. ADMIN SETTINGS — WooCommerce Settings -> "WhatsApp Notify" tab
   Non-sensitive settings only. Sensitive credentials belong in wp-config.php.
   ========================================================================= */

function rmh_wa_add_settings_tab( $settings_tabs ) {
    $settings_tabs['rmh_whatsapp_notify'] = __( 'WhatsApp Notify', 'rabindra-medical-hall' );
    return $settings_tabs;
}
add_filter( 'woocommerce_settings_tabs_array', 'rmh_wa_add_settings_tab', 80 );

function rmh_wa_settings_tab_output() {
    woocommerce_admin_fields( rmh_wa_get_settings() );
}
add_action( 'woocommerce_settings_tabs_rmh_whatsapp_notify', 'rmh_wa_settings_tab_output' );

function rmh_wa_settings_tab_save() {
    woocommerce_update_options( rmh_wa_get_settings() );
}
add_action( 'woocommerce_update_options_rmh_whatsapp_notify', 'rmh_wa_settings_tab_save' );

function rmh_wa_get_settings() {
    $token_src   = ( defined( 'RMH_WHATSAPP_ACCESS_TOKEN' )    && '' !== RMH_WHATSAPP_ACCESS_TOKEN )    ? 'wp-config.php constant OK' : 'NOT set in wp-config.php';
    $phone_src   = ( defined( 'RMH_WHATSAPP_PHONE_NUMBER_ID' ) && '' !== RMH_WHATSAPP_PHONE_NUMBER_ID ) ? 'wp-config.php constant OK' : 'NOT set in wp-config.php';
    $version_src = ( defined( 'RMH_WHATSAPP_API_VERSION' )     && '' !== RMH_WHATSAPP_API_VERSION )     ? 'wp-config.php constant OK' : 'NOT set in wp-config.php';

    $guide  = 'Add to wp-config.php (above "That\'s all" line):' . "\n";
    $guide .= "define( 'RMH_WHATSAPP_ACCESS_TOKEN',    'YOUR_TOKEN'   );\n";
    $guide .= "define( 'RMH_WHATSAPP_PHONE_NUMBER_ID', 'YOUR_PHONE_ID' );\n";
    $guide .= "define( 'RMH_WHATSAPP_API_VERSION',     'v20.0'         );";

    return array(
        array(
            'title' => __( 'WhatsApp Cloud API — Order Notification', 'rabindra-medical-hall' ),
            'type'  => 'title',
            'desc'  => '<pre style="background:#f1f1f1;padding:8px;font-size:12px;">'
                       . esc_html( $guide ) . '</pre>'
                       . '<a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started" target="_blank" rel="noopener">'
                       . esc_html__( 'Meta Cloud API setup guide', 'rabindra-medical-hall' ) . '</a>',
            'id'    => RMH_WA_OPTION_PREFIX . 'section_start',
        ),
        array(
            'title'   => __( 'Enable Notifications', 'rabindra-medical-hall' ),
            'type'    => 'checkbox',
            'desc'    => __( 'Send WhatsApp template message to staff on every paid order (processing / completed).', 'rabindra-medical-hall' ),
            'id'      => RMH_WA_OPTION_PREFIX . 'enabled',
            'default' => 'no',
        ),
        array(
            'title' => __( 'Credential Status', 'rabindra-medical-hall' ),
            'type'  => 'title',
            'desc'  => '<p><strong>Access Token:</strong> ' . esc_html( $token_src ) . '<br>'
                       . '<strong>Phone Number ID:</strong> ' . esc_html( $phone_src ) . '<br>'
                       . '<strong>API Version:</strong> ' . esc_html( $version_src ) . '</p>',
            'id'    => RMH_WA_OPTION_PREFIX . 'cred_status',
        ),
        array(
            'title'       => __( 'Staff Recipient Number', 'rabindra-medical-hall' ),
            'type'        => 'text',
            'desc'        => __( 'International format, no + or spaces. e.g. 917205859117', 'rabindra-medical-hall' ),
            'id'          => RMH_WA_OPTION_PREFIX . 'recipient_number',
            'default'     => '',
            'placeholder' => '917205859117',
            'css'         => 'width:220px;',
        ),
        array(
            'title'       => __( 'Message Template Name', 'rabindra-medical-hall' ),
            'type'        => 'text',
            'desc'        => __( 'Must match an approved Meta Business template. Default: rmh_new_order', 'rabindra-medical-hall' ),
            'id'          => RMH_WA_OPTION_PREFIX . 'template_name',
            'default'     => 'rmh_new_order',
            'placeholder' => 'rmh_new_order',
            'css'         => 'width:280px;',
        ),
        array(
            'title'       => __( 'Template Language Code', 'rabindra-medical-hall' ),
            'type'        => 'text',
            'desc'        => __( 'e.g. en or en_US', 'rabindra-medical-hall' ),
            'id'          => RMH_WA_OPTION_PREFIX . 'template_language',
            'default'     => 'en',
            'placeholder' => 'en',
            'css'         => 'width:120px;',
        ),
        array(
            'type' => 'sectionend',
            'id'   => RMH_WA_OPTION_PREFIX . 'section_end',
        ),
    );
}

/* =========================================================================
   4. ORDER HOOKS
   Fire on processing|completed status transitions and on payment_complete.
   Explicitly do NOT fire for pending, failed, cancelled, on-hold, refunded.
   ========================================================================= */

function rmh_wa_on_order_status_change( $order_id, $old_status, $new_status, $order ) {
    if ( ! in_array( $new_status, array( 'processing', 'completed' ), true ) ) {
        return;
    }
    // HPOS-compatible: use $order->get_meta().
    $existing = (string) $order->get_meta( '_rmh_wa_notification_status', true );
    if ( RMH_WA_STATUS_SENT === $existing || RMH_WA_STATUS_PENDING === $existing ) {
        return;
    }
    if ( ! rmh_wa_is_configured() ) {
        return;
    }
    rmh_wa_dispatch( $order_id );
}
add_action( 'woocommerce_order_status_changed', 'rmh_wa_on_order_status_change', 10, 4 );

function rmh_wa_on_payment_complete( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }
    $existing = (string) $order->get_meta( '_rmh_wa_notification_status', true );
    if ( RMH_WA_STATUS_SENT === $existing || RMH_WA_STATUS_PENDING === $existing ) {
        return;
    }
    if ( ! rmh_wa_is_configured() ) {
        return;
    }
    rmh_wa_dispatch( $order_id );
}
add_action( 'woocommerce_payment_complete', 'rmh_wa_on_payment_complete', 10 );

function rmh_wa_dispatch( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }
    // HPOS-compatible: use $order->update_meta_data() + $order->save().
    $order->update_meta_data( '_rmh_wa_notification_status', RMH_WA_STATUS_PENDING );
    $order->save();
    if ( function_exists( 'as_enqueue_async_action' ) ) {
        as_enqueue_async_action(
            'rmh_wa_process_notification',
            array( 'order_id' => (int) $order_id ),
            'rmh-whatsapp'
        );
    } else {
        rmh_wa_send_notification( $order_id );
    }
}

function rmh_wa_process_notification( $order_id ) {
    rmh_wa_send_notification( (int) $order_id );
}
add_action( 'rmh_wa_process_notification', 'rmh_wa_process_notification' );

/* =========================================================================
   5. PAYLOAD BUILDERS
   ========================================================================= */

function rmh_wa_build_order_summary( WC_Order $order ) {
    $lines = array();
    foreach ( $order->get_items() as $item ) {
        $lines[] = $item->get_name() . ' x ' . (int) $item->get_quantity();
    }
    return $lines ? implode( ', ', $lines ) : 'No items';
}

function rmh_wa_build_delivery_address( WC_Order $order ) {
    $parts = array_filter( array(
        $order->get_shipping_address_1(),
        $order->get_shipping_address_2(),
        $order->get_shipping_city(),
        $order->get_shipping_state(),
        $order->get_shipping_postcode(),
    ) );
    if ( empty( $parts ) ) {
        $parts = array_filter( array(
            $order->get_billing_address_1(),
            $order->get_billing_address_2(),
            $order->get_billing_city(),
            $order->get_billing_state(),
            $order->get_billing_postcode(),
        ) );
    }
    return implode( ', ', $parts );
}

/**
 * Return a Maps URL ONLY from valid coordinates or a stored Maps link.
 * NEVER fabricates coordinates from a text address.
 *
 * @param  WC_Order $order
 * @return string  URL or empty string.
 */
function rmh_wa_build_maps_url( WC_Order $order ) {
    // HPOS-compatible: use $order->get_meta() instead of get_post_meta().
    $lat = (string) $order->get_meta( '_rmh_delivery_lat', true );
    $lng = (string) $order->get_meta( '_rmh_delivery_lng', true );

    if (
        '' !== $lat && '' !== $lng
        && is_numeric( $lat ) && is_numeric( $lng )
        && (float) $lat >= -90.0  && (float) $lat <= 90.0
        && (float) $lng >= -180.0 && (float) $lng <= 180.0
    ) {
        return 'https://www.google.com/maps?q=' . rawurlencode( (float) $lat . ',' . (float) $lng );
    }

    $stored = (string) $order->get_meta( '_rmh_delivery_maps_url', true );
    if ( '' !== $stored && filter_var( $stored, FILTER_VALIDATE_URL ) ) {
        return $stored;
    }

    return ''; // No valid location — return empty, never invent one.
}

/**
 * Return true if any item in the order is marked prescription-required.
 * Uses existing rmh_product_requires_prescription() without modification.
 *
 * @param  WC_Order $order
 * @return bool
 */
function rmh_wa_order_has_prescription_items( WC_Order $order ) {
    if ( ! function_exists( 'rmh_product_requires_prescription' ) ) {
        return false;
    }
    foreach ( $order->get_items() as $item ) {
        $product = $item->get_product();
        if ( $product instanceof WC_Product && rmh_product_requires_prescription( $product ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Build the 11-element template parameter array.
 *
 * Variable mapping (must match your approved Meta template body exactly):
 *   {{1}}  Order number
 *   {{2}}  Order date and time
 *   {{3}}  Customer name
 *   {{4}}  Customer phone
 *   {{5}}  Items list
 *   {{6}}  Order total
 *   {{7}}  Payment method (and order status)
 *   {{8}}  Delivery address
 *   {{9}}  Google Maps URL (or "Not provided")
 *   {{10}} Prescription required (Yes/No)
 *   {{11}} WooCommerce admin order URL
 *
 * @param  WC_Order $order
 * @return array
 */
function rmh_wa_build_template_params( WC_Order $order ) {
    $order_num = (string) $order->get_order_number();
    $date      = $order->get_date_created()
                 ? $order->get_date_created()->date_i18n( 'd M Y, h:i A' )
                 : current_time( 'd M Y, h:i A' );
    $name      = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    $phone     = $order->get_billing_phone();
    $items     = rmh_wa_build_order_summary( $order );
    $total     = wp_strip_all_tags( html_entity_decode( $order->get_formatted_order_total(), ENT_QUOTES, 'UTF-8' ) );
    $payment   = $order->get_payment_method_title() . ' (' . wc_get_order_status_name( $order->get_status() ) . ')';
    $address   = rmh_wa_build_delivery_address( $order );
    $maps_url  = rmh_wa_build_maps_url( $order );
    $has_rx    = rmh_wa_order_has_prescription_items( $order ) ? 'Yes - Prescription required' : 'No';
    $admin_url = admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' );

    return array(
        array( 'type' => 'text', 'text' => $order_num ),
        array( 'type' => 'text', 'text' => $date ),
        array( 'type' => 'text', 'text' => ( '' !== $name ) ? $name : 'N/A' ),
        array( 'type' => 'text', 'text' => ( '' !== $phone ) ? $phone : 'N/A' ),
        array( 'type' => 'text', 'text' => $items ),
        array( 'type' => 'text', 'text' => $total ),
        array( 'type' => 'text', 'text' => $payment ),
        array( 'type' => 'text', 'text' => ( '' !== $address ) ? $address : 'Not provided' ),
        array( 'type' => 'text', 'text' => ( '' !== $maps_url ) ? $maps_url : 'Not provided' ),
        array( 'type' => 'text', 'text' => $has_rx ),
        array( 'type' => 'text', 'text' => $admin_url ),
    );
}

/* =========================================================================
   6. API DISPATCH
   ========================================================================= */

/**
 * Send the WhatsApp template notification for the given order.
 *
 * On success  : writes notification_status=sent, message_id, sent_at.
 * On failure  : writes notification_status=failed + sanitised error (no token).
 * Never crashes WooCommerce.
 *
 * @param int $order_id Order ID.
 */
function rmh_wa_send_notification( $order_id ) {
    $order_id = (int) $order_id;

    if ( ! rmh_wa_is_configured() ) {
        rmh_wa_log( 'ORDER #' . $order_id . ' - Skipped: module not fully configured.' );
        return;
    }

    // Load order early so we can use HPOS-compatible meta reads.
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        rmh_wa_log( 'ORDER #' . $order_id . ' - Skipped: order not found.' );
        return;
    }

    // Already sent - hard stop.
    if ( RMH_WA_STATUS_SENT === (string) $order->get_meta( '_rmh_wa_notification_status', true ) ) {
        return;
    }

    // 60-second process lock to prevent concurrent sends.
    $lock = 'rmh_wa_lock_' . $order_id;
    if ( get_transient( $lock ) ) {
        rmh_wa_log( 'ORDER #' . $order_id . ' - Skipped: process lock active.' );
        return;
    }
    set_transient( $lock, 1, 60 );

    // Double-check after acquiring lock.
    if ( RMH_WA_STATUS_SENT === (string) $order->get_meta( '_rmh_wa_notification_status', true ) ) {
        delete_transient( $lock );
        return;
    }

    // Mark pending before the HTTP call (HPOS-compatible).
    $order->update_meta_data( '_rmh_wa_notification_status', RMH_WA_STATUS_PENDING );
    $order->save();

    // Build request.
    $api_version     = rmh_wa_get_api_version();
    $phone_number_id = rmh_wa_get_phone_number_id();
    $access_token    = rmh_wa_get_access_token(); // NEVER logged or echoed.
    $recipient       = preg_replace( '/\D/', '', rmh_wa_opt( 'recipient_number' ) );
    $template_name   = rmh_wa_opt( 'template_name', 'rmh_new_order' );
    $template_lang   = rmh_wa_opt( 'template_language', 'en' );

    $endpoint = sprintf(
        'https://graph.facebook.com/%s/%s/messages',
        rawurlencode( $api_version ),
        rawurlencode( $phone_number_id )
    );

    $payload = array(
        'messaging_product' => 'whatsapp',
        'to'                => $recipient,
        'type'              => 'template',
        'template'          => array(
            'name'       => $template_name,
            'language'   => array( 'code' => $template_lang ),
            'components' => array(
                array(
                    'type'       => 'body',
                    'parameters' => rmh_wa_build_template_params( $order ),
                ),
            ),
        ),
    );

    $response = wp_remote_post( $endpoint, array(
        'timeout'     => 15,
        'redirection' => 3,
        'headers'     => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json',
        ),
        'body'        => wp_json_encode( $payload ),
    ) );

    if ( is_wp_error( $response ) ) {
        rmh_wa_write_failure( $order, 'WP_HTTP: ' . $response->get_error_message() );
    } else {
        $http_code = (int) wp_remote_retrieve_response_code( $response );
        $raw_body  = wp_remote_retrieve_body( $response );
        $decoded   = json_decode( $raw_body, true );

        if ( 200 === $http_code && isset( $decoded['messages'][0]['id'] ) ) {
            rmh_wa_write_success( $order, sanitize_text_field( $decoded['messages'][0]['id'] ) );
        } else {
            // Scrub token from body before storing or logging.
            $safe = rmh_wa_scrub_token( $raw_body, $access_token );
            rmh_wa_write_failure( $order, 'HTTP ' . $http_code . ': ' . $safe );
        }
    }

    delete_transient( $lock );
}

function rmh_wa_write_success( WC_Order $order, $message_id ) {
    $now = current_time( 'mysql' );
    $order->update_meta_data( '_rmh_wa_notification_status',     RMH_WA_STATUS_SENT );
    $order->update_meta_data( '_rmh_wa_notification_message_id', $message_id );
    $order->update_meta_data( '_rmh_wa_notification_sent_at',    $now );
    $order->delete_meta_data( '_rmh_wa_notification_error' );
    $order->save();
    $order->add_order_note(
        'WhatsApp notification sent to staff. Message ID: ' . $message_id . ' at ' . $now,
        false, false
    );
    rmh_wa_log( 'ORDER #' . $order->get_id() . ' - Sent OK. ID: ' . $message_id );
}

function rmh_wa_write_failure( WC_Order $order, $error_msg ) {
    $short = mb_substr( sanitize_text_field( $error_msg ), 0, 500 );
    $order->update_meta_data( '_rmh_wa_notification_status', RMH_WA_STATUS_FAILED );
    $order->update_meta_data( '_rmh_wa_notification_error',  $short );
    $order->save();
    $order->add_order_note(
        'WhatsApp notification FAILED. See WooCommerce > Status > Logs (rmh-whatsapp).',
        false, false
    );
    rmh_wa_log( 'ORDER #' . $order->get_id() . ' - Failed: ' . $short );
}

function rmh_wa_scrub_token( $text, $token ) {
    if ( '' === $token || '' === $text ) {
        return $text;
    }
    return str_replace( $token, '[REDACTED]', $text );
}

/* =========================================================================
   7. LOGGING — WooCommerce > Status > Logs (source: rmh-whatsapp)
   ========================================================================= */

function rmh_wa_log( $message ) {
    if ( function_exists( 'wc_get_logger' ) ) {
        wc_get_logger()->info( $message, array( 'source' => 'rmh-whatsapp' ) );
    }
}

/* =========================================================================
   8. ADMIN PANEL — notification status in order detail
   Displays: Sent (timestamp + message ID), Failed (error), or Pending.
   Only visible to manage_woocommerce capability.
   ========================================================================= */

function rmh_wa_display_order_meta( $order ) {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $status     = (string) $order->get_meta( '_rmh_wa_notification_status', true );
    $message_id = (string) $order->get_meta( '_rmh_wa_notification_message_id', true );
    $sent_at    = (string) $order->get_meta( '_rmh_wa_notification_sent_at', true );
    $error      = (string) $order->get_meta( '_rmh_wa_notification_error', true );

    if ( '' !== $status ) {
        echo '<div class="form-field" style="padding:.5rem 0;border-top:1px solid #f0f0f0;margin-top:.5rem;">';
        echo '<strong>' . esc_html__( 'WhatsApp Notification:', 'rabindra-medical-hall' ) . '</strong> ';

        if ( RMH_WA_STATUS_SENT === $status ) {
            echo '<span style="color:#2e7d32;">&#10003; Sent</span>';
            if ( $sent_at ) {
                echo ' &mdash; ' . esc_html( $sent_at );
            }
            if ( $message_id ) {
                echo '<br><small style="color:#555;">Message ID: ' . esc_html( $message_id ) . '</small>';
            }
        } elseif ( RMH_WA_STATUS_FAILED === $status ) {
            echo '<span style="color:#c62828;">&#10007; Failed</span>';
            if ( $error ) {
                echo '<br><small style="color:#888;">' . esc_html( $error ) . '</small>';
            }
        } elseif ( RMH_WA_STATUS_PENDING === $status ) {
            echo '<span style="color:#e65100;">&#8987; Pending</span>';
        } else {
            echo esc_html( $status );
        }

        echo '</div>';
    }

    // Delivery Location section (BUG 4) — always shown when any data is saved.
    $lat      = (string) $order->get_meta( '_rmh_delivery_lat', true );
    $lng      = (string) $order->get_meta( '_rmh_delivery_lng', true );
    $maps_url = (string) $order->get_meta( '_rmh_delivery_maps_url', true );
    $landmark = (string) $order->get_meta( '_rmh_delivery_landmark', true );

    $has_location = ( '' !== $lat || '' !== $maps_url || '' !== $landmark );
    if ( ! $has_location ) {
        return;
    }

    echo '<div class="form-field" style="padding:.5rem 0;border-top:1px solid #f0f0f0;margin-top:.5rem;">';
    echo '<strong>&#128205; ' . esc_html__( 'Delivery Location:', 'rabindra-medical-hall' ) . '</strong>';

    if ( '' !== $lat && '' !== $lng
        && is_numeric( $lat ) && is_numeric( $lng )
        && (float) $lat >= -90.0 && (float) $lat <= 90.0
        && (float) $lng >= -180.0 && (float) $lng <= 180.0
    ) {
        $coord_url = 'https://www.google.com/maps?q=' . rawurlencode( $lat . ',' . $lng );
        echo '<br><small>Latitude: ' . esc_html( $lat ) . ' &nbsp;|&nbsp; Longitude: ' . esc_html( $lng ) . '</small>';
        echo '<br><small><a href="' . esc_url( $coord_url ) . '" target="_blank" rel="noopener">'
             . esc_html__( 'View on Google Maps (GPS coordinates)', 'rabindra-medical-hall' )
             . '</a></small>';
    }

    if ( '' !== $maps_url && filter_var( $maps_url, FILTER_VALIDATE_URL ) ) {
        echo '<br><small><a href="' . esc_url( $maps_url ) . '" target="_blank" rel="noopener">'
             . esc_html__( 'Customer-provided Maps link', 'rabindra-medical-hall' )
             . '</a></small>';
    }

    if ( '' !== $landmark ) {
        echo '<br><small>' . esc_html__( 'Landmark:', 'rabindra-medical-hall' ) . ' ' . esc_html( $landmark ) . '</small>';
    }

    echo '</div>';
}
add_action( 'woocommerce_admin_order_data_after_order_details', 'rmh_wa_display_order_meta' );

/* =========================================================================
   9. ADMIN RESEND ACTION
   ========================================================================= */

function rmh_wa_add_order_action( $actions ) {
    if ( current_user_can( 'manage_woocommerce' ) ) {
        $actions['rmh_wa_resend'] = __( 'Re-send WhatsApp notification', 'rabindra-medical-hall' );
    }
    return $actions;
}
add_filter( 'woocommerce_order_actions', 'rmh_wa_add_order_action' );

function rmh_wa_handle_resend_action( WC_Order $order ) {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }
    // HPOS-compatible: use order CRUD instead of delete_post_meta.
    $order->delete_meta_data( '_rmh_wa_notification_status'     );
    $order->delete_meta_data( '_rmh_wa_notification_message_id' );
    $order->delete_meta_data( '_rmh_wa_notification_sent_at'    );
    $order->delete_meta_data( '_rmh_wa_notification_error'      );
    $order->save();
    delete_transient( 'rmh_wa_lock_' . $order->get_id() );
    rmh_wa_send_notification( $order->get_id() );
}
add_action( 'woocommerce_order_action_rmh_wa_resend', 'rmh_wa_handle_resend_action' );

/* =========================================================================
   10. CHECKOUT FIELD — optional GPS / Maps URL capture
   Reuses meta keys: _rmh_delivery_lat, _rmh_delivery_lng, _rmh_delivery_maps_url
   Strict coordinate validation before saving. Token is unrelated to this section.
   ========================================================================= */

function rmh_wa_add_checkout_location_field() {
    if ( ! is_checkout() ) {
        return;
    }
    $site_url  = get_site_url();
    $is_secure = ( 0 === strpos( $site_url, 'https://' ) ) || ( false !== strpos( $site_url, '.local' ) );
    if ( ! $is_secure ) {
        return;
    }
    ?>
    <div class="rmh-delivery-location-block" id="rmhDeliveryLocationBlock"
         style="margin-top:1.5rem;padding:1rem;background:#f8fafb;border-radius:6px;border:1px solid #d9e2ec;">
        <h3 style="font-size:1rem;margin:0 0 .4rem;color:#1a1a2e;">
            &#128205; <?php esc_html_e( 'Share Your Delivery Location (Optional)', 'rabindra-medical-hall' ); ?>
        </h3>
        <p style="font-size:.82rem;color:#5a6a7a;margin:0 0 .75rem;">
            <?php esc_html_e( 'Help our delivery team find you faster. Entirely optional.', 'rabindra-medical-hall' ); ?>
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin-bottom:.75rem;">
            <button type="button" id="rmhShareLocationBtn"
                    style="padding:.45rem .9rem;font-size:.82rem;background:#1565c0;color:#fff;border:none;border-radius:4px;cursor:pointer;">
                <?php esc_html_e( 'Use My GPS Location', 'rabindra-medical-hall' ); ?>
            </button>
            <span id="rmhLocationStatus" style="font-size:.8rem;color:#555;"></span>
        </div>
        <label for="rmh_delivery_maps_url" style="font-size:.82rem;display:block;margin-bottom:.3rem;">
            <?php esc_html_e( 'Or paste a Google Maps link:', 'rabindra-medical-hall' ); ?>
        </label>
        <input type="url" id="rmh_delivery_maps_url" name="rmh_delivery_maps_url"
               placeholder="https://maps.app.goo.gl/..."
               style="width:100%;max-width:400px;padding:.4rem .6rem;border:1px solid #ccc;border-radius:4px;font-size:.82rem;" />
        <input type="hidden" id="rmh_delivery_lat" name="rmh_delivery_lat" value="" />
        <input type="hidden" id="rmh_delivery_lng" name="rmh_delivery_lng" value="" />
    </div>
    <script>
    (function () {
        'use strict';
        var btn   = document.getElementById('rmhShareLocationBtn');
        var stat  = document.getElementById('rmhLocationStatus');
        var latIn = document.getElementById('rmh_delivery_lat');
        var lngIn = document.getElementById('rmh_delivery_lng');
        var mapIn = document.getElementById('rmh_delivery_maps_url');
        if (!btn || !window.navigator || !navigator.geolocation) {
            if (btn) { btn.style.display = 'none'; }
            return;
        }
        btn.addEventListener('click', function () {
            stat.textContent = '<?php echo esc_js( 'Detecting location\u2026' ); ?>';
            btn.disabled = true;
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    var lat = pos.coords.latitude.toFixed(7);
                    var lng = pos.coords.longitude.toFixed(7);
                    latIn.value = lat;
                    lngIn.value = lng;
                    if (!mapIn.value) {
                        mapIn.value = 'https://www.google.com/maps?q=' + encodeURIComponent(lat + ',' + lng);
                    }
                    stat.textContent = '\u2705 <?php echo esc_js( 'Location captured!' ); ?> (' + lat + ', ' + lng + ')';
                    stat.style.color = '#2e7d32';
                    btn.disabled = false;
                },
                function () {
                    stat.textContent = '\u26a0\ufe0f <?php echo esc_js( 'Could not get GPS. Paste a Maps link instead.' ); ?>';
                    stat.style.color = '#c62828';
                    btn.disabled = false;
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        });
    }());
    </script>
    <?php
}
add_action( 'woocommerce_after_checkout_billing_form', 'rmh_wa_add_checkout_location_field' );

/**
 * Save GPS/Maps/Landmark data to the WooCommerce order using the HPOS-compatible
 * Order CRUD API. Hooked on woocommerce_checkout_create_order so $order object
 * is available; $order->save() is called by WooCommerce after this hook returns.
 *
 * Meta keys:
 *   _rmh_delivery_lat       Latitude  (float string, valid range -90..90)
 *   _rmh_delivery_lng       Longitude (float string, valid range -180..180)
 *   _rmh_delivery_maps_url  Google Maps URL (validated URL)
 *   _rmh_delivery_landmark  Landmark text (optional, from billing_landmark field)
 *
 * @param WC_Order $order   The order object being created.
 * @param array    $data    Posted checkout data.
 */
function rmh_wa_save_checkout_location( WC_Order $order, $data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
    // --- Latitude + Longitude ---
    $lat = isset( $_POST['rmh_delivery_lat'] ) ? sanitize_text_field( wp_unslash( $_POST['rmh_delivery_lat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $lng = isset( $_POST['rmh_delivery_lng'] ) ? sanitize_text_field( wp_unslash( $_POST['rmh_delivery_lng'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

    if (
        '' !== $lat && '' !== $lng
        && is_numeric( $lat ) && is_numeric( $lng )
        && (float) $lat >= -90.0  && (float) $lat <= 90.0
        && (float) $lng >= -180.0 && (float) $lng <= 180.0
    ) {
        $order->update_meta_data( '_rmh_delivery_lat', (string) (float) $lat );
        $order->update_meta_data( '_rmh_delivery_lng', (string) (float) $lng );
    }

    // --- Maps URL ---
    $maps_url = isset( $_POST['rmh_delivery_maps_url'] ) ? esc_url_raw( wp_unslash( $_POST['rmh_delivery_maps_url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( '' !== $maps_url && filter_var( $maps_url, FILTER_VALIDATE_URL ) ) {
        $order->update_meta_data( '_rmh_delivery_maps_url', $maps_url );
    }

    // --- Landmark (from billing_landmark WooCommerce field) ---
    $landmark = isset( $_POST['billing_landmark'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_landmark'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( '' !== $landmark ) {
        $order->update_meta_data( '_rmh_delivery_landmark', $landmark );
    }
    // Note: $order->save() is called by WooCommerce core after this hook.
}
// woocommerce_checkout_create_order provides $order object — HPOS-compatible.
add_action( 'woocommerce_checkout_create_order', 'rmh_wa_save_checkout_location', 20, 2 );
