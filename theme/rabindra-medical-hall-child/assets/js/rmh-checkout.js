/**
 * RMH Checkout JavaScript — Loaded ONLY on checkout page.
 *
 * Handles:
 * 1. WooCommerce checkout AJAX events (updated_checkout, checkout_error)
 * 2. Prescription modal upload success → trigger update_checkout
 * 3. Prescription notice state management
 * 4. Location badge click on checkout (delegates to existing rmhData handlers)
 * 5. Select2 field width enforcement after AJAX refresh
 *
 * Dependencies: jQuery, wc-checkout, rmhData (localized by functions.php)
 *
 * @version 1.0.0
 */

/* global jQuery, rmhData, wc_checkout_params */
(function ($) {
    'use strict';

    /**
     * Initialise prescription notice state on the checkout page.
     * State is determined server-side and rendered in the DOM.
     * This JS layer handles post-upload updates without a full page reload.
     */
    function rmhCheckoutInitPrescriptionNotice() {
        var $notice = $('.rmh-prescription-checkout-notice');
        if (!$notice.length) {
            return; // No Rx items in cart.
        }

        // Listen for prescription upload success broadcast from the existing modal JS
        $(document).on('rmh:prescriptionUploaded', function (e, data) {
            if (data && data.prescriptionId) {
                $notice.addClass('state-uploaded').removeClass('state-missing state-blocked');
                $notice.find('strong').text('Prescription received!');
                $notice.find('.rmh-rx-status-text').text(
                    'Our pharmacist will review it shortly. You may now proceed to place your order.'
                );
                // Remove the upload link; it's no longer needed
                $notice.find('a[href*="rmh-prescription"]').remove();

                // Trigger WooCommerce to re-validate (updates totals, re-checks Rx block)
                $('body').trigger('update_checkout');
            }
        });

        // Also handle legacy custom event name
        $(document).on('rmhPrescriptionUploaded', function (e, prescriptionId) {
            $(document).trigger('rmh:prescriptionUploaded', [{prescriptionId: prescriptionId}]);
        });
    }

    /**
     * After every WooCommerce checkout AJAX refresh, re-apply our overrides.
     * WooCommerce replaces DOM fragments with $.load(), which can reset Select2
     * widths and lose our custom styles.
     */
    function rmhCheckoutAfterRefresh() {
        // Force Select2 dropdowns to full width
        $('.woocommerce-checkout .select2-container').each(function () {
            $(this).css({width: '100%', maxWidth: '100%'});
        });

        // Re-apply checkout notice state if prescriptionId is in sessionStorage
        var rxId = sessionStorage.getItem('rmh_checkout_rx_id');
        if (rxId) {
            var $notice = $('.rmh-prescription-checkout-notice');
            if ($notice.length && !$notice.hasClass('state-uploaded')) {
                $notice.addClass('state-uploaded');
                $notice.find('strong').text('Prescription received!');
                $notice.find('a[href*="rmh-prescription"]').remove();
            }
        }
    }

    /**
     * Scroll to first error notice after checkout error.
     */
    function rmhScrollToError() {
        var $errors = $('.woocommerce-error, .woocommerce-NoticeGroup-checkout');
        if ($errors.length) {
            $('html, body').animate({
                scrollTop: $errors.first().offset().top - 80
            }, 300);
        }
    }

    /**
     * Enforce mobile-safe layout on checkout form.
     * WooCommerce does not always remove float-based layout classes correctly.
     */
    function rmhCheckoutEnforceMobileLayout() {
        if (window.innerWidth > 768) {
            return;
        }
        // Form row floats
        $('.form-row-first, .form-row-last').css({
            'float': 'none',
            'width': '100%',
            'margin-left': '0',
            'margin-right': '0',
            'clear': 'both'
        });
        // Select2 widths
        $('.woocommerce-checkout .select2-container').css({width: '100%'});
    }

    /**
     * Handle location badge click on checkout to open location modal.
     * The existing location modal JS handles the actual flow; this ensures
     * the click event is forwarded when the badge is visible on checkout.
     */
    function rmhCheckoutLocationBadgeInit() {
        // The button#rmhHeaderLocationBadge click is already handled in custom.js
        // via rmhData nonces. We just need to make sure the badge doesn't have its
        // click suppressed on checkout.
        $(document).on('click', '#rmhHeaderLocationBadge', function () {
            // After location modal is dismissed + location saved, refresh checkout
            // so shipping methods update. We listen for the same custom event the
            // location modal fires on save.
        });

        $(document).on('rmh:locationSaved', function (e, data) {
            if (data && data.postcode) {
                // Update WooCommerce shipping postcode field if present
                var $postcode = $('#billing_postcode, #shipping_postcode');
                if ($postcode.length && $postcode.val() === '') {
                    $postcode.first().val(data.postcode).trigger('change');
                }
                // Trigger checkout update to recalculate shipping
                $('body').trigger('update_checkout');
            }
        });
    }

    /**
     * Persist prescription ID to sessionStorage when uploaded so that
     * the checkout page can show correct state after a reload.
     */
    function rmhCheckoutPersistPrescription() {
        $(document).on('rmh:prescriptionUploaded', function (e, data) {
            if (data && data.prescriptionId) {
                try {
                    sessionStorage.setItem('rmh_checkout_rx_id', data.prescriptionId);
                } catch (err) {
                    // sessionStorage may not be available in all browsers
                }
            }
        });
    }

    /**
     * Initialise WhatsApp Order Request flow on WooCommerce Checkout.
     * Reuses native WooCommerce billing and shipping fields, validates inputs,
     * builds a formatted store message with cart items, and opens WhatsApp.
     */
    function rmhCheckoutInitWhatsAppOrderRequest() {
        var $section = $('#rmh-whatsapp-order-section');
        if (!$section.length) {
            return;
        }

        var isSubmitting = false;
        var originalBtnHtml = '';

        // Read cart data from DOM data-attribute or localized object
        function getActiveCartData() {
            var domData = $('#rmh-whatsapp-order-section').attr('data-cart-data');
            if (domData) {
                try {
                    return JSON.parse(domData);
                } catch (e) {}
            }
            if (window.rmhCheckoutData && window.rmhCheckoutData.cartData) {
                return window.rmhCheckoutData.cartData;
            }
            return { is_empty: false, items: [], subtotal: '', total: '' };
        }

        function cleanRupeeText(str) {
            if (!str) return '';
            var clean = $.trim(String(str).replace(/&#8377;|&amp;#8377;/g, '₹'));
            clean = clean.replace(/\(includes.*?\)/gi, '').trim();
            clean = clean.replace(/&nbsp;|\u00A0/g, ' ');
            var match = clean.match(/₹\s*[\d,]+(?:\.\d+)?/);
            if (match) {
                return match[0].replace(/\s+/, '');
            }
            var numMatch = clean.match(/[\d,]+(?:\.\d+)?/);
            if (numMatch) {
                return '₹' + numMatch[0];
            }
            return '';
        }

        function getFieldText(selector) {
            var $el = $(selector);
            if (!$el.length) return '';
            if ($el.is('select')) {
                var val = $el.val();
                if (!val) return '';
                var text = $el.find('option:selected').text() || $el.find('option[value="' + val + '"]').text() || val;
                return /select/i.test(text) ? '' : $.trim(text);
            }
            return $.trim($el.val() || '');
        }

        function showNotice(type, message, $focusTarget) {
            var $box = $('#rmhWaNoticeBox');
            $box.removeClass('rmh-wa-status-box--error rmh-wa-status-box--success');
            $box.addClass('rmh-wa-status-box--' + type);
            $box.html(message).show();

            if ($focusTarget && $focusTarget.length) {
                $focusTarget.addClass('rmh-input-error');
                $focusTarget.one('input change', function () {
                    $(this).removeClass('rmh-input-error');
                });
                $('html, body').animate({
                    scrollTop: $focusTarget.offset().top - 100
                }, 250);
                $focusTarget.focus();
            } else {
                $('html, body').animate({
                    scrollTop: $box.offset().top - 100
                }, 250);
            }
        }

        function clearNotice() {
            var $box = $('#rmhWaNoticeBox');
            $box.hide().empty();
            $('.rmh-input-error').removeClass('rmh-input-error');
        }

        $(document).on('click', '#rmhSendWhatsAppOrderBtn', function (e) {
            e.preventDefault();

            if (isSubmitting) {
                return false;
            }

            var $btn = $(this);
            if ($btn.prop('disabled')) {
                return false;
            }

            isSubmitting = true;
            originalBtnHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="rmh-wa-btn-spinner" aria-hidden="true">⏳</span> ' + 'Preparing WhatsApp Request...');
            clearNotice();

            // 1. Validate Cart
            var cartData = getActiveCartData();
            if (cartData.is_empty || !cartData.items || cartData.items.length === 0) {
                showNotice('error', 'Your cart is empty. Please add products before sending an order request.');
                $btn.prop('disabled', false).html(originalBtnHtml);
                isSubmitting = false;
                return;
            }

            // 2. Collect & Validate Customer Name
            var firstName = getFieldText('#billing_first_name');
            var lastName  = getFieldText('#billing_last_name');
            var fullName  = $.trim(firstName + ' ' + lastName);
            if (!fullName) {
                showNotice('error', 'Please enter your full name.', $('#billing_first_name'));
                $btn.prop('disabled', false).html(originalBtnHtml);
                isSubmitting = false;
                return;
            }

            // 3. Collect & Validate Mobile Number
            var rawPhone = getFieldText('#billing_phone');
            var cleanPhone = rawPhone.replace(/\D/g, '');
            if (cleanPhone.length > 10 && cleanPhone.indexOf('91') === 0) {
                cleanPhone = cleanPhone.substring(2);
            }
            if (cleanPhone.length === 11 && cleanPhone.indexOf('0') === 0) {
                cleanPhone = cleanPhone.substring(1);
            }

            if (!cleanPhone) {
                showNotice('error', 'Please enter your mobile number.', $('#billing_phone'));
                $btn.prop('disabled', false).html(originalBtnHtml);
                isSubmitting = false;
                return;
            }
            if (cleanPhone.length !== 10) {
                showNotice('error', 'Please enter a valid 10-digit mobile number.', $('#billing_phone'));
                $btn.prop('disabled', false).html(originalBtnHtml);
                isSubmitting = false;
                return;
            }

            // 4. Collect & Validate Email (if provided or required)
            var email = getFieldText('#billing_email');
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showNotice('error', 'Please enter a valid email address.', $('#billing_email'));
                $btn.prop('disabled', false).html(originalBtnHtml);
                isSubmitting = false;
                return;
            }

            // 5. Collect & Validate Delivery Address
            var isShippingDiff = $('#ship-to-different-address-checkbox').is(':checked');
            var address1 = isShippingDiff ? getFieldText('#shipping_address_1') : getFieldText('#billing_address_1');
            var address2 = isShippingDiff ? getFieldText('#shipping_address_2') : getFieldText('#billing_address_2');
            var city     = isShippingDiff ? getFieldText('#shipping_city') : getFieldText('#billing_city');
            var state    = isShippingDiff ? getFieldText('#shipping_state') : getFieldText('#billing_state');
            var rawPin   = isShippingDiff ? getFieldText('#shipping_postcode') : getFieldText('#billing_postcode');
            var cleanPin = rawPin.replace(/\D/g, '');
            var landmark = getFieldText('#billing_landmark');

            if (!address1) {
                showNotice('error', 'Please enter your delivery address (House / Flat / Building).', isShippingDiff ? $('#shipping_address_1') : $('#billing_address_1'));
                $btn.prop('disabled', false).html(originalBtnHtml);
                isSubmitting = false;
                return;
            }

            if (!city) {
                showNotice('error', 'Please enter your city.', isShippingDiff ? $('#shipping_city') : $('#billing_city'));
                $btn.prop('disabled', false).html(originalBtnHtml);
                isSubmitting = false;
                return;
            }

            if (!state) {
                showNotice('error', 'Please select your state.', isShippingDiff ? $('#shipping_state') : $('#billing_state'));
                $btn.prop('disabled', false).html(originalBtnHtml);
                isSubmitting = false;
                return;
            }

            if (!cleanPin) {
                showNotice('error', 'Please enter your PIN code.', isShippingDiff ? $('#shipping_postcode') : $('#billing_postcode'));
                $btn.prop('disabled', false).html(originalBtnHtml);
                isSubmitting = false;
                return;
            }
            if (cleanPin.length !== 6) {
                showNotice('error', 'Please enter a valid 6-digit PIN code.', isShippingDiff ? $('#shipping_postcode') : $('#billing_postcode'));
                $btn.prop('disabled', false).html(originalBtnHtml);
                isSubmitting = false;
                return;
            }

            // 6. Build the Structured WhatsApp Message
            var lines = [];
            lines.push('Hello Rabindra Medical Hall,');
            lines.push('');
            lines.push('I would like to place an order request through your website.');
            lines.push('');

            // CUSTOMER DETAILS
            lines.push('━━━━━━━━━━━━━━━━━━');
            lines.push('CUSTOMER DETAILS');
            lines.push('━━━━━━━━━━━━━━━━━━');
            lines.push('Name: ' + (fullName || 'Not Provided'));
            lines.push('Mobile: ' + (cleanPhone || 'Not Provided'));
            lines.push('Email: ' + (email || 'Not Provided'));
            lines.push('');

            // DELIVERY ADDRESS
            lines.push('DELIVERY ADDRESS');
            if (address1) {
                lines.push('House/Flat: ' + address1);
            }
            if (address2) {
                lines.push('Street/Address: ' + address2);
            }
            if (landmark) {
                lines.push('Landmark: ' + landmark);
            }
            lines.push('City: ' + (city || 'Cuttack'));
            lines.push('State: ' + (state || 'Odisha'));
            if (cleanPin) {
                lines.push('PIN Code: ' + cleanPin);
            }
            lines.push('');

            // 6b. Include GPS/Maps location if available — NEVER show raw coordinates!
            var deliveryLat  = $.trim($('#rmh_delivery_lat').val() || '');
            var deliveryLng  = $.trim($('#rmh_delivery_lng').val() || '');
            var deliveryMaps = $.trim($('#rmh_delivery_maps_url').val() || '');
            var finalMapsUrl = '';

            if (deliveryLat && deliveryLng && !isNaN(parseFloat(deliveryLat)) && !isNaN(parseFloat(deliveryLng))) {
                var latNum = parseFloat(deliveryLat);
                var lngNum = parseFloat(deliveryLng);
                if (latNum >= -90 && latNum <= 90 && lngNum >= -180 && lngNum <= 180) {
                    finalMapsUrl = 'https://www.google.com/maps?q=' + encodeURIComponent(deliveryLat + ',' + deliveryLng);
                }
            }
            if (!finalMapsUrl && deliveryMaps && /^(https?:\/\/)/i.test(deliveryMaps)) {
                finalMapsUrl = deliveryMaps;
            }

            lines.push('DELIVERY LOCATION');
            lines.push('Google Maps: ' + (finalMapsUrl || 'Not Provided'));
            lines.push('');

            // ORDER DETAILS
            lines.push('━━━━━━━━━━━━━━━━━━');
            lines.push('ORDER DETAILS');
            lines.push('━━━━━━━━━━━━━━━━━━');
            lines.push('');

            if (cartData && cartData.items && cartData.items.length) {
                cartData.items.forEach(function (item, idx) {
                    lines.push((idx + 1) + '. ' + item.name + ' × ' + item.quantity);
                    lines.push('   SKU: ' + (item.sku || 'Not Available'));
                    var itemPrice = item.price_formatted || (item.price ? '₹' + parseFloat(item.price).toFixed(2) : 'Price on request');
                    lines.push('   Price: ' + itemPrice);
                    if (item.variation_details) {
                        lines.push('   Variant: ' + item.variation_details);
                    }
                    lines.push('');
                });
            }

            // ORDER SUMMARY
            lines.push('━━━━━━━━━━━━━━━━━━');
            lines.push('ORDER SUMMARY');
            lines.push('━━━━━━━━━━━━━━━━━━');

            // Reconcile with live DOM review table if available
            var domSubtotal = cleanRupeeText($('.woocommerce-checkout-review-order-table .cart-subtotal td').text());
            var domDiscount = cleanRupeeText($('.woocommerce-checkout-review-order-table .cart-discount td').text());
            var domTax      = cleanRupeeText($('.woocommerce-checkout-review-order-table .tax-rate td').text());
            var domTotal    = cleanRupeeText($('.woocommerce-checkout-review-order-table .order-total td').text());

            var domShipping = '';
            var $shippingRow = $('.woocommerce-checkout-review-order-table tr.shipping, .woocommerce-checkout-review-order-table tr.woocommerce-shipping-totals');
            if ($shippingRow.length) {
                var shippingText = $shippingRow.find('td').text().trim();
                if (/no shipping options|no shipping method|not available/i.test(shippingText)) {
                    domShipping = 'To Be Confirmed';
                } else {
                    var parsedShipping = cleanRupeeText(shippingText);
                    if (parsedShipping) {
                        domShipping = parsedShipping;
                    } else if (/free/i.test(shippingText)) {
                        domShipping = '₹0.00';
                    }
                }
            }

            var subtotalOut = domSubtotal || cartData.subtotal || '₹0.00';
            var shippingOut = domShipping || cartData.shipping || 'To Be Confirmed';
            var totalOut    = domTotal || cartData.total || 'Pending Confirmation';

            // If shipping is uncalculated or to be confirmed, total must be "Pending Confirmation"
            if (!shippingOut || /to be confirmed/i.test(shippingOut)) {
                shippingOut = 'To Be Confirmed';
                totalOut = 'Pending Confirmation';
            }

            lines.push('Subtotal: ' + subtotalOut);
            lines.push('Delivery Charge: ' + shippingOut);
            lines.push('Estimated Total: ' + totalOut);
            lines.push('');

            lines.push('ORDER TYPE: WhatsApp Order Request');
            lines.push('Website: Rabindra Medical Hall');
            lines.push('');
            lines.push('Please confirm product availability, applicable delivery charges, the final payable amount, and delivery details before processing my request.');
            lines.push('');
            lines.push('Thank you,');
            lines.push('Rabindra Medical Hall');

            var fullMessage = lines.join('\n');
            var waNumber = (window.rmhCheckoutData && window.rmhCheckoutData.whatsappNumber) || '917205859117';
            var waUrl = 'https://wa.me/' + encodeURIComponent(waNumber) + '?text=' + encodeURIComponent(fullMessage);

            $btn.html('✓ Opening WhatsApp...');

            var successMsg = '✓ WhatsApp opened! Your order request and delivery details have been prepared. Please click <strong>Send</strong> in WhatsApp to complete sending to Rabindra Medical Hall.<br><small>If WhatsApp did not open automatically, <a href="' + waUrl + '" target="_blank" rel="noopener">tap here to open WhatsApp</a>.</small>';
            showNotice('success', successMsg);

            // Open WhatsApp in a new tab/window
            try {
                window.open(waUrl, '_blank');
            } catch (err) {
                window.location.href = waUrl;
            }

            // Restore button state after 3.5 seconds so user is never trapped
            setTimeout(function () {
                $btn.prop('disabled', false).html(originalBtnHtml);
                isSubmitting = false;
            }, 3500);
        });

        // Re-sync cart data whenever WooCommerce updates checkout fragments via AJAX
        $(document.body).on('updated_checkout', function () {
            var ajaxUrl = window.rmhCheckoutData && window.rmhCheckoutData.ajaxUrl ? window.rmhCheckoutData.ajaxUrl : '/wp-admin/admin-ajax.php';
            $.post(ajaxUrl, { action: 'rmh_get_cart_whatsapp_data' }, function (res) {
                if (res && res.success && res.data) {
                    $('#rmh-whatsapp-order-section').attr('data-cart-data', JSON.stringify(res.data));
                    if (window.rmhCheckoutData) {
                        window.rmhCheckoutData.cartData = res.data;
                    }
                }
            });
        });
    }

    /**
     * Bootstrap: run when DOM is ready.
     */
    $(function () {
        // Only run on checkout
        if (!$('body').hasClass('woocommerce-checkout')) {
            return;
        }

        rmhCheckoutInitPrescriptionNotice();
        rmhCheckoutEnforceMobileLayout();
        rmhCheckoutLocationBadgeInit();
        rmhCheckoutPersistPrescription();
        rmhCheckoutInitWhatsAppOrderRequest();

        // WooCommerce AJAX checkout events
        $('body').on('updated_checkout', function () {
            rmhCheckoutAfterRefresh();
            rmhCheckoutEnforceMobileLayout();
        });

        $('body').on('checkout_error', function () {
            rmhScrollToError();
        });

        // Resize handler — re-enforce mobile layout if user rotates device
        var resizeTimeout;
        $(window).on('resize', function () {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(rmhCheckoutEnforceMobileLayout, 150);
        });
    });

}(jQuery));

