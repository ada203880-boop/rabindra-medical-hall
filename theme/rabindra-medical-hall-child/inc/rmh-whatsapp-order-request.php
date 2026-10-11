<?php
/**
 * Cart-based WhatsApp medicine order requests.
 *
 * Creates a real WooCommerce order from the customer's cart without charging
 * them, then holds it for store stock/price verification.
 *
 * @package RabindraMedicalHall
 */

defined( 'ABSPATH' ) || exit;

const RMH_WA_ORDER_META_ORIGINAL_SUBTOTAL = '_rmh_wa_original_subtotal';
const RMH_WA_ORDER_META_FINAL_TOTAL       = '_rmh_wa_final_total';
const RMH_WA_ORDER_META_DISCOUNT_TYPE     = '_rmh_wa_discount_type';
const RMH_WA_ORDER_META_DISCOUNT_VALUE    = '_rmh_wa_discount_value';
const RMH_WA_ORDER_META_DISCOUNT_AMOUNT   = '_rmh_wa_discount_amount';
const RMH_WA_ORDER_META_PAYMENT_STATUS    = '_rmh_wa_payment_status';
const RMH_WA_ORDER_META_PAYMENT_REF       = '_rmh_wa_payment_reference';
const RMH_WA_ORDER_META_NOTIFY_STATUS     = '_rmh_wa_request_notify_status';
const RMH_WA_ORDER_META_NOTIFY_ERROR      = '_rmh_wa_request_notify_error';

/**
 * Register manual verification statuses used by the WhatsApp request flow.
 */
function rmh_wa_order_register_statuses() {
    $statuses = array(
        'wc-store-verification' => _x( 'Pending store verification', 'Order status', 'rabindra-medical-hall' ),
        'wc-stock-confirmed'    => _x( 'Stock confirmed', 'Order status', 'rabindra-medical-hall' ),
        'wc-payment-pending'    => _x( 'Payment pending', 'Order status', 'rabindra-medical-hall' ),
        'wc-payment-confirmed'  => _x( 'Payment confirmed', 'Order status', 'rabindra-medical-hall' ),
        'wc-out-for-delivery'   => _x( 'Out for delivery', 'Order status', 'rabindra-medical-hall' ),
        'wc-out-of-stock'       => _x( 'Out of stock', 'Order status', 'rabindra-medical-hall' ),
    );

    foreach ( $statuses as $status => $label ) {
        register_post_status(
            $status,
            array(
                'label'                     => $label,
                'public'                    => false,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>', 'rabindra-medical-hall' ),
            )
        );
    }
}
add_action( 'init', 'rmh_wa_order_register_statuses' );

/**
 * Add the statuses to WooCommerce's status list.
 *
 * @param array $statuses Statuses.
 * @return array
 */
function rmh_wa_order_add_statuses( $statuses ) {
    $insert = array(
        'wc-store-verification' => __( 'Pending store verification', 'rabindra-medical-hall' ),
        'wc-stock-confirmed'    => __( 'Stock confirmed', 'rabindra-medical-hall' ),
        'wc-payment-pending'    => __( 'Payment pending', 'rabindra-medical-hall' ),
        'wc-payment-confirmed'  => __( 'Payment confirmed', 'rabindra-medical-hall' ),
        'wc-out-for-delivery'   => __( 'Out for delivery', 'rabindra-medical-hall' ),
        'wc-out-of-stock'       => __( 'Out of stock', 'rabindra-medical-hall' ),
    );

    $result = array();
    foreach ( $statuses as $key => $label ) {
        $result[ $key ] = $label;
        if ( 'wc-on-hold' === $key ) {
            $result = array_merge( $result, $insert );
        }
    }

    return $result;
}
add_filter( 'wc_order_statuses', 'rmh_wa_order_add_statuses' );

/**
 * Render a structured cart request form.
 */
function rmh_render_cart_whatsapp_order_form() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
        return;
    }

    $customer = WC()->customer;
    $name     = $customer ? trim( $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name() ) : '';
    $phone    = $customer ? $customer->get_billing_phone() : '';
    $address  = $customer ? trim( $customer->get_shipping_address_1() ?: $customer->get_billing_address_1() ) : '';
    $city     = $customer ? trim( $customer->get_shipping_city() ?: $customer->get_billing_city() ) : '';
    ?>
    <div class="rmh-whatsapp-order-card" id="rmh-whatsapp-order">
        <h3><?php echo esc_html__( 'Order through WhatsApp', 'rabindra-medical-hall' ); ?></h3>
        <p><?php echo esc_html__( 'Send this cart to the pharmacy for stock verification. Payment happens only after the store confirms availability and final amount.', 'rabindra-medical-hall' ); ?></p>
        <form class="rmh-whatsapp-order-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'rmh_whatsapp_order_request', 'rmh_whatsapp_order_nonce' ); ?>
            <input type="hidden" name="action" value="rmh_whatsapp_order_request">
            <p class="rmh-form-field">
                <label for="rmh_wa_customer_name"><?php echo esc_html__( 'Customer Name', 'rabindra-medical-hall' ); ?> <span class="required">*</span></label>
                <input id="rmh_wa_customer_name" name="customer_name" type="text" required autocomplete="name" value="<?php echo esc_attr( $name ); ?>">
            </p>
            <p class="rmh-form-field">
                <label for="rmh_wa_customer_phone"><?php echo esc_html__( 'Mobile Number', 'rabindra-medical-hall' ); ?> <span class="required">*</span></label>
                <input id="rmh_wa_customer_phone" name="customer_phone" type="tel" required inputmode="tel" autocomplete="tel" value="<?php echo esc_attr( $phone ); ?>">
            </p>
            <p class="rmh-form-field">
                <label for="rmh_wa_delivery_address"><?php echo esc_html__( 'Delivery Address / Location', 'rabindra-medical-hall' ); ?> <span class="required">*</span></label>
                <textarea id="rmh_wa_delivery_address" name="delivery_address" required autocomplete="street-address"><?php echo esc_textarea( $address ); ?></textarea>
            </p>
            <p class="rmh-form-field">
                <label for="rmh_wa_area_city"><?php echo esc_html__( 'Area / City', 'rabindra-medical-hall' ); ?> <span class="required">*</span></label>
                <input id="rmh_wa_area_city" name="area_city" type="text" required autocomplete="address-level2" value="<?php echo esc_attr( $city ); ?>">
            </p>
            <p class="rmh-form-field">
                <label for="rmh_wa_landmark"><?php echo esc_html__( 'Landmark', 'rabindra-medical-hall' ); ?></label>
                <input id="rmh_wa_landmark" name="landmark" type="text" autocomplete="off">
            </p>
            <p class="rmh-form-field">
                <label for="rmh_wa_delivery_instructions"><?php echo esc_html__( 'Delivery Instructions', 'rabindra-medical-hall' ); ?></label>
                <textarea id="rmh_wa_delivery_instructions" name="delivery_instructions"></textarea>
            </p>
            <button type="submit" class="button alt rmh-whatsapp-order-submit" aria-label="<?php echo esc_attr__( 'Send order to Rabindra Medical Hall on WhatsApp', 'rabindra-medical-hall' ); ?>">
                <?php echo esc_html__( 'Order Through WhatsApp', 'rabindra-medical-hall' ); ?>
            </button>
        </form>
    </div>
    <?php
}
// Note: Hook removed to prevent duplicate cart form and fake WooCommerce order creation.
// WhatsApp order requests now seamlessly transition to checkout for delivery details.
// add_action( 'woocommerce_proceed_to_checkout', 'rmh_render_cart_whatsapp_order_form', 24 );

/**
 * Validate phone in a conservative India-friendly way.
 *
 * @param string $phone Raw phone.
 * @return string
 */
function rmh_wa_order_clean_phone( $phone ) {
    $digits = preg_replace( '/\D+/', '', (string) $phone );
    if ( strlen( $digits ) > 10 && '91' === substr( $digits, 0, 2 ) ) {
        $digits = substr( $digits, 2 );
    }
    return $digits;
}

/**
 * Build the store-facing readable message.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function rmh_wa_order_build_store_message( WC_Order $order ) {
    $lines   = array();
    $lines[] = 'NEW MEDICINE ORDER REQUEST';
    $lines[] = '';
    $lines[] = 'Order ID: RMH-' . $order->get_order_number();
    $lines[] = 'Time: ' . current_time( 'd M Y, h:i A' );
    $lines[] = '';
    $lines[] = 'Customer:';
    $lines[] = 'Name: ' . trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    $lines[] = 'Mobile: ' . $order->get_billing_phone();
    $lines[] = '';
    $lines[] = 'Delivery:';
    $lines[] = $order->get_shipping_address_1();
    $lines[] = $order->get_shipping_city();

    $landmark = (string) $order->get_meta( '_rmh_delivery_landmark', true );
    $notes    = (string) $order->get_meta( '_rmh_delivery_instructions', true );
    if ( $landmark ) {
        $lines[] = 'Landmark: ' . $landmark;
    }
    if ( $notes ) {
        $lines[] = 'Instructions: ' . $notes;
    }

    $lines[] = '';
    $lines[] = 'MEDICINES:';
    $index   = 1;
    foreach ( $order->get_items() as $item ) {
        $product = $item->get_product();
        $sku     = $product ? $product->get_sku() : '';
        $parts   = array( $index . '. ' . $item->get_name() . ' x ' . $item->get_quantity() );
        $parts[] = 'Price: ' . wp_strip_all_tags( wc_price( (float) $item->get_subtotal() ) );
        if ( $sku ) {
            $parts[] = 'SKU: ' . $sku;
        }
        if ( $product ) {
            $parts[] = 'Product ID: ' . $product->get_id();
        }
        $lines[] = implode( ' | ', $parts );
        $index++;
    }

    $lines[] = '';
    $lines[] = 'Subtotal: ' . wp_strip_all_tags( wc_price( (float) $order->get_meta( RMH_WA_ORDER_META_ORIGINAL_SUBTOTAL, true ) ) );
    $lines[] = 'STATUS: Pending stock verification';
    $lines[] = 'Prescription: ' . ( 'yes' === $order->get_meta( '_rmh_prescription_required', true ) ? 'Required / verify before dispensing' : 'Not required by cart items' );
    $lines[] = '';
    $lines[] = 'Please verify availability, adjust quantities/prices if needed, apply discount, and confirm the final amount.';

    return implode( "\n", array_filter( $lines, static function ( $line ) { return null !== $line; } ) );
}

/**
 * Handle customer cart request submission.
 */
function rmh_handle_whatsapp_order_request() {
    if ( ! isset( $_POST['rmh_whatsapp_order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rmh_whatsapp_order_nonce'] ) ), 'rmh_whatsapp_order_request' ) ) {
        wp_die( esc_html__( 'Security check failed. Please try again.', 'rabindra-medical-hall' ), '', array( 'response' => 403 ) );
    }
    if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }

    $name         = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
    $phone_raw    = isset( $_POST['customer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_phone'] ) ) : '';
    $phone        = rmh_wa_order_clean_phone( $phone_raw );
    $address      = isset( $_POST['delivery_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['delivery_address'] ) ) : '';
    $area_city    = isset( $_POST['area_city'] ) ? sanitize_text_field( wp_unslash( $_POST['area_city'] ) ) : '';
    $landmark     = isset( $_POST['landmark'] ) ? sanitize_text_field( wp_unslash( $_POST['landmark'] ) ) : '';
    $instructions = isset( $_POST['delivery_instructions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['delivery_instructions'] ) ) : '';

    if ( '' === $name || strlen( $phone ) < 10 || '' === $address || '' === $area_city ) {
        wc_add_notice( __( 'Please enter your name, valid mobile number, delivery address, and area/city before sending a WhatsApp order.', 'rabindra-medical-hall' ), 'error' );
        wp_safe_redirect( wc_get_cart_url() . '#rmh-whatsapp-order' );
        exit;
    }

    WC()->cart->calculate_totals();

    $order = wc_create_order(
        array(
            'customer_id' => get_current_user_id(),
            'created_via' => 'rmh-whatsapp-order',
        )
    );

    if ( is_wp_error( $order ) ) {
        wc_add_notice( __( 'Could not create the order request. Please call the pharmacy.', 'rabindra-medical-hall' ), 'error' );
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }

    $name_parts = preg_split( '/\s+/', $name, 2 );
    $first_name = $name_parts[0] ?? $name;
    $last_name  = $name_parts[1] ?? '';

    $address_data = array(
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'phone'      => $phone,
        'address_1'  => $address,
        'city'       => $area_city,
        'country'    => 'IN',
    );

    $order->set_address( $address_data, 'billing' );
    $order->set_address( $address_data, 'shipping' );

    $has_rx = false;
    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        $product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
        if ( ! $product instanceof WC_Product ) {
            continue;
        }

        $item_id = $order->add_product( $product, absint( $cart_item['quantity'] ), array() );
        if ( $item_id ) {
            wc_add_order_item_meta( $item_id, '_rmh_original_product_id', $product->get_id() );
            wc_add_order_item_meta( $item_id, '_rmh_original_unit_price', wc_format_decimal( (float) $product->get_price(), wc_get_price_decimals() ) );
            wc_add_order_item_meta( $item_id, '_rmh_stock_verification', 'pending' );
        }
        if ( function_exists( 'rmh_product_requires_prescription' ) && rmh_product_requires_prescription( $product ) ) {
            $has_rx = true;
        }
    }

    $original_subtotal = (float) WC()->cart->get_subtotal();
    $order->update_meta_data( RMH_WA_ORDER_META_ORIGINAL_SUBTOTAL, wc_format_decimal( $original_subtotal, wc_get_price_decimals() ) );
    $order->update_meta_data( RMH_WA_ORDER_META_PAYMENT_STATUS, 'pending_store_verification' );
    $order->update_meta_data( '_rmh_delivery_landmark', $landmark );
    $order->update_meta_data( '_rmh_delivery_instructions', $instructions );
    $order->update_meta_data( '_rmh_prescription_required', $has_rx ? 'yes' : 'no' );

    $session_rx_id = ( WC()->session ) ? (int) WC()->session->get( 'rmh_uploaded_prescription_id' ) : 0;
    if ( $session_rx_id > 0 && ( ! function_exists( 'rmh_current_session_owns_prescription' ) || rmh_current_session_owns_prescription( $session_rx_id ) ) ) {
        $order->update_meta_data( '_rmh_prescription_id', $session_rx_id );
    }

    $order->set_payment_method( 'rmh_whatsapp_manual' );
    $order->set_payment_method_title( __( 'WhatsApp order request - payment after store confirmation', 'rabindra-medical-hall' ) );
    $order->calculate_totals( true );
    $order->update_status( 'store-verification', __( 'WhatsApp order request received. Awaiting manual stock and price verification.', 'rabindra-medical-hall' ), false );
    $order->save();

    $message = rmh_wa_order_build_store_message( $order );
    $order->add_order_note( __( 'Store WhatsApp request message prepared from cart details.', 'rabindra-medical-hall' ), false, false );
    $order->update_meta_data( '_rmh_wa_store_message', $message );
    $order->save_meta_data();

    WC()->cart->empty_cart();

    $redirect = add_query_arg(
        array(
            'rmh_wa_order' => $order->get_id(),
            'key'          => $order->get_order_key(),
        ),
        $order->get_checkout_order_received_url()
    );

    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_rmh_whatsapp_order_request', 'rmh_handle_whatsapp_order_request' );
add_action( 'admin_post_nopriv_rmh_whatsapp_order_request', 'rmh_handle_whatsapp_order_request' );

/**
 * Replace the order received copy for WhatsApp request orders.
 *
 * @param string $text Current text.
 * @param WC_Order|null $order Order.
 * @return string
 */
function rmh_wa_order_received_text( $text, $order ) {
    if ( $order instanceof WC_Order && 'rmh-whatsapp-order' === $order->get_created_via() ) {
        return sprintf(
            /* translators: %s order number */
            __( 'Order request received. Order ID: RMH-%s. Our store will check medicine availability and contact you on WhatsApp with the final amount and payment instructions.', 'rabindra-medical-hall' ),
            esc_html( $order->get_order_number() )
        );
    }
    return $text;
}
add_filter( 'woocommerce_thankyou_order_received_text', 'rmh_wa_order_received_text', 10, 2 );

/**
 * Admin panel with verification/discount/payment controls.
 *
 * @param WC_Order $order Order.
 */
function rmh_wa_order_admin_panel( $order ) {
    if ( ! $order instanceof WC_Order || 'rmh-whatsapp-order' !== $order->get_created_via() || ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $discount_type  = $order->get_meta( RMH_WA_ORDER_META_DISCOUNT_TYPE, true );
    $discount_value = $order->get_meta( RMH_WA_ORDER_META_DISCOUNT_VALUE, true );
    $payment_status = $order->get_meta( RMH_WA_ORDER_META_PAYMENT_STATUS, true );
    $payment_ref    = $order->get_meta( RMH_WA_ORDER_META_PAYMENT_REF, true );
    $store_message  = $order->get_meta( '_rmh_wa_store_message', true );
    ?>
    <div class="order_data_column" style="clear:both;width:100%;padding-top:12px;">
        <h3><?php echo esc_html__( 'RMH WhatsApp Verification', 'rabindra-medical-hall' ); ?></h3>
        <p><strong><?php echo esc_html__( 'Payment status:', 'rabindra-medical-hall' ); ?></strong> <?php echo esc_html( $payment_status ? $payment_status : 'pending' ); ?></p>
        <?php if ( $store_message ) : ?>
            <p><a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( rmh_get_whatsapp_url( $store_message ) ); ?>"><?php echo esc_html__( 'Open store WhatsApp request', 'rabindra-medical-hall' ); ?></a></p>
        <?php endif; ?>
        <p class="form-field form-field-wide">
            <label for="rmh_wa_discount_type"><?php echo esc_html__( 'Discount type', 'rabindra-medical-hall' ); ?></label>
            <select id="rmh_wa_discount_type" name="rmh_wa_discount_type">
                <option value="" <?php selected( $discount_type, '' ); ?>><?php echo esc_html__( 'No discount', 'rabindra-medical-hall' ); ?></option>
                <option value="percent" <?php selected( $discount_type, 'percent' ); ?>><?php echo esc_html__( 'Percentage', 'rabindra-medical-hall' ); ?></option>
                <option value="fixed" <?php selected( $discount_type, 'fixed' ); ?>><?php echo esc_html__( 'Fixed amount', 'rabindra-medical-hall' ); ?></option>
            </select>
        </p>
        <p class="form-field form-field-wide">
            <label for="rmh_wa_discount_value"><?php echo esc_html__( 'Discount value', 'rabindra-medical-hall' ); ?></label>
            <input id="rmh_wa_discount_value" name="rmh_wa_discount_value" type="number" min="0" step="0.01" value="<?php echo esc_attr( $discount_value ); ?>">
        </p>
        <p class="form-field form-field-wide">
            <label for="rmh_wa_payment_reference"><?php echo esc_html__( 'Payment reference / transaction ID', 'rabindra-medical-hall' ); ?></label>
            <input id="rmh_wa_payment_reference" name="rmh_wa_payment_reference" type="text" value="<?php echo esc_attr( $payment_ref ); ?>">
        </p>
        <?php wp_nonce_field( 'rmh_save_whatsapp_order_' . $order->get_id(), 'rmh_whatsapp_order_admin_nonce' ); ?>
    </div>
    <?php
}
add_action( 'woocommerce_admin_order_data_after_order_details', 'rmh_wa_order_admin_panel', 30 );

/**
 * Save admin verification values and recalculate final totals server-side.
 *
 * @param int $order_id Order ID.
 */
function rmh_wa_order_save_admin_panel( $order_id ) {
    if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['rmh_whatsapp_order_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rmh_whatsapp_order_admin_nonce'] ) ), 'rmh_save_whatsapp_order_' . $order_id ) ) {
        return;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order || 'rmh-whatsapp-order' !== $order->get_created_via() ) {
        return;
    }

    $type  = isset( $_POST['rmh_wa_discount_type'] ) ? sanitize_key( wp_unslash( $_POST['rmh_wa_discount_type'] ) ) : '';
    $value = isset( $_POST['rmh_wa_discount_value'] ) ? wc_format_decimal( wp_unslash( $_POST['rmh_wa_discount_value'] ), wc_get_price_decimals() ) : '0';
    $ref   = isset( $_POST['rmh_wa_payment_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['rmh_wa_payment_reference'] ) ) : '';

    if ( ! in_array( $type, array( '', 'percent', 'fixed' ), true ) ) {
        $type = '';
    }

    $subtotal        = (float) $order->get_subtotal();
    $discount_amount = 0.0;
    if ( 'percent' === $type ) {
        $discount_amount = min( $subtotal, $subtotal * min( 100, (float) $value ) / 100 );
    } elseif ( 'fixed' === $type ) {
        $discount_amount = min( $subtotal, (float) $value );
    }

    foreach ( $order->get_fees() as $fee_id => $fee ) {
        if ( '_rmh_manual_discount' === $fee->get_meta( '_rmh_fee_type', true ) ) {
            $order->remove_item( $fee_id );
        }
    }

    if ( $discount_amount > 0 ) {
        $fee = new WC_Order_Item_Fee();
        $fee->set_name( __( 'Store confirmed discount', 'rabindra-medical-hall' ) );
        $fee->set_amount( -1 * $discount_amount );
        $fee->set_total( -1 * $discount_amount );
        $fee->add_meta_data( '_rmh_fee_type', '_rmh_manual_discount', true );
        $order->add_item( $fee );
    }

    $order->update_meta_data( RMH_WA_ORDER_META_DISCOUNT_TYPE, $type );
    $order->update_meta_data( RMH_WA_ORDER_META_DISCOUNT_VALUE, $value );
    $order->update_meta_data( RMH_WA_ORDER_META_DISCOUNT_AMOUNT, wc_format_decimal( $discount_amount, wc_get_price_decimals() ) );
    $order->update_meta_data( RMH_WA_ORDER_META_PAYMENT_REF, $ref );
    $order->calculate_totals( true );
    $order->update_meta_data( RMH_WA_ORDER_META_FINAL_TOTAL, wc_format_decimal( (float) $order->get_total(), wc_get_price_decimals() ) );
    $order->save();
}
add_action( 'woocommerce_process_shop_order_meta', 'rmh_wa_order_save_admin_panel', 20 );

/**
 * Add admin order actions for the manual flow.
 *
 * @param array $actions Actions.
 * @return array
 */
function rmh_wa_order_actions( $actions ) {
    if ( current_user_can( 'manage_woocommerce' ) ) {
        $actions['rmh_wa_mark_stock_confirmed']   = __( 'RMH: Stock confirmed, request payment', 'rabindra-medical-hall' );
        $actions['rmh_wa_confirm_payment']        = __( 'RMH: Confirm manual payment', 'rabindra-medical-hall' );
        $actions['rmh_wa_mark_out_for_delivery']  = __( 'RMH: Mark out for delivery', 'rabindra-medical-hall' );
    }
    return $actions;
}
add_filter( 'woocommerce_order_actions', 'rmh_wa_order_actions' );

/**
 * Build customer payment request text.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function rmh_wa_order_build_payment_message( WC_Order $order ) {
    $lines   = array();
    $lines[] = 'Rabindra Medical Hall - Payment Request';
    $lines[] = 'Order ID: RMH-' . $order->get_order_number();
    $lines[] = '';
    $lines[] = 'Available medicines:';
    foreach ( $order->get_items() as $item ) {
        $lines[] = '- ' . $item->get_name() . ' x ' . $item->get_quantity() . ': ' . wp_strip_all_tags( wc_price( (float) $item->get_total() ) );
    }
    $lines[] = '';
    $lines[] = 'Original amount: ' . wp_strip_all_tags( wc_price( (float) $order->get_meta( RMH_WA_ORDER_META_ORIGINAL_SUBTOTAL, true ) ) );
    $lines[] = 'Discount: ' . wp_strip_all_tags( wc_price( (float) $order->get_meta( RMH_WA_ORDER_META_DISCOUNT_AMOUNT, true ) ) );
    $lines[] = 'Final payable amount: ' . wp_strip_all_tags( $order->get_formatted_order_total() );
    $lines[] = '';
    $lines[] = 'Please pay using the payment method/instructions shared by the store. Your order will be processed after payment confirmation.';
    return implode( "\n", $lines );
}

function rmh_wa_order_mark_stock_confirmed( WC_Order $order ) {
    if ( 'rmh-whatsapp-order' !== $order->get_created_via() ) {
        return;
    }
    $message = rmh_wa_order_build_payment_message( $order );
    $order->update_meta_data( '_rmh_wa_customer_payment_message', $message );
    $order->update_meta_data( RMH_WA_ORDER_META_PAYMENT_STATUS, 'payment_pending' );
    $order->update_status( 'payment-pending', __( 'Stock verified. Final amount calculated and payment request prepared for WhatsApp.', 'rabindra-medical-hall' ), false );
    $order->save();
}
add_action( 'woocommerce_order_action_rmh_wa_mark_stock_confirmed', 'rmh_wa_order_mark_stock_confirmed' );

function rmh_wa_order_confirm_payment( WC_Order $order ) {
    if ( 'rmh-whatsapp-order' !== $order->get_created_via() || 'payment_confirmed' === $order->get_meta( RMH_WA_ORDER_META_PAYMENT_STATUS, true ) ) {
        return;
    }
    $order->payment_complete();
    $order->update_meta_data( RMH_WA_ORDER_META_PAYMENT_STATUS, 'payment_confirmed' );
    $order->update_status( 'processing', __( 'Manual payment confirmed by store/admin. Invoice can now be issued and delivery processed.', 'rabindra-medical-hall' ), false );
    $order->save();
}
add_action( 'woocommerce_order_action_rmh_wa_confirm_payment', 'rmh_wa_order_confirm_payment' );

function rmh_wa_order_mark_out_for_delivery( WC_Order $order ) {
    if ( 'rmh-whatsapp-order' !== $order->get_created_via() ) {
        return;
    }
    $order->update_status( 'out-for-delivery', __( 'Order marked out for delivery by store/admin.', 'rabindra-medical-hall' ), false );
}
add_action( 'woocommerce_order_action_rmh_wa_mark_out_for_delivery', 'rmh_wa_order_mark_out_for_delivery' );

/**
 * Display WhatsApp helper links in admin after stock/payment actions.
 *
 * @param WC_Order $order Order.
 */
function rmh_wa_order_admin_message_links( $order ) {
    if ( ! $order instanceof WC_Order || 'rmh-whatsapp-order' !== $order->get_created_via() || ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }
    $customer_message = $order->get_meta( '_rmh_wa_customer_payment_message', true );
    if ( $customer_message ) {
        $phone = preg_replace( '/\D+/', '', $order->get_billing_phone() );
        if ( strlen( $phone ) === 10 ) {
            $phone = '91' . $phone;
        }
        $url = 'https://wa.me/' . rawurlencode( $phone ) . '?text=' . rawurlencode( $customer_message );
        echo '<p><a class="button" target="_blank" rel="noopener" href="' . esc_url( $url ) . '">' . esc_html__( 'Send final amount to customer on WhatsApp', 'rabindra-medical-hall' ) . '</a></p>';
    }
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'rmh_wa_order_admin_message_links', 20 );

