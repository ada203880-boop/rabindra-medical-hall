(function () {
    'use strict';

    document.documentElement.classList.add('rmh-js');

    // Respect reduced motion preference
    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            var targetId = link.getAttribute('href').slice(1);
            var target = document.getElementById(targetId);

            if (!target) {
                return;
            }

            event.preventDefault();
            target.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
            target.focus({ preventScroll: true });
        });
    });

    document.querySelectorAll('.rmh-home-hero a[href$="#rmh-prescription"]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            var target = document.getElementById('rmh-prescription');

            if (!target) {
                return;
            }

            event.preventDefault();
            target.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
            target.focus({ preventScroll: true });
            window.history.replaceState(null, '', link.href);
        });
    });

    // Intersection Observer for scroll animations
    function initScrollAnimations() {
        if (!('IntersectionObserver' in window) || prefersReducedMotion) {
            return;
        }

        // Ensure elements are never hidden as blank white spaces
        var animatedSections = document.querySelectorAll(
            '.rmh-section-heading, .rmh-trust-item, .rmh-faq-list details, .rmh-promotion-banner, .rmh-category, .rmh-product-card, .rmh-tools-card'
        );

        animatedSections.forEach(function (el) {
            el.style.opacity = '1';
            el.style.transform = 'none';
        });
    }

    // Modern search experience
    function initModernSearch() {
        var searchForm = document.querySelector('.rmh-site-search');
        var searchInput = document.querySelector('.rmh-site-search input[type="search"]');
        var searchResults = document.getElementById('rmh-search-results');

        if (!searchForm || !searchInput || !searchResults) {
            return;
        }

        var debounceTimer = null;
        var currentFocus = -1;

        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>\"']/g, function (character) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '\"': '&quot;',
                    "'": '&#039;'
                }[character];
            });
        }

        searchInput.addEventListener('input', function () {
            var query = searchInput.value.trim();

            clearTimeout(debounceTimer);

            if (query.length < 2) {
                searchResults.innerHTML = '';
                searchResults.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(function () {
                fetchProducts(query);
            }, 250);
        });

        searchInput.addEventListener('keydown', function (event) {
            var items = searchResults.querySelectorAll('.rmh-search-item');

            if (event.key === 'ArrowDown') {
                currentFocus++;
                highlightItem(items, currentFocus);
                event.preventDefault();
            } else if (event.key === 'ArrowUp') {
                currentFocus--;
                highlightItem(items, currentFocus);
                event.preventDefault();
            } else if (event.key === 'Enter' && currentFocus >= 0 && items[currentFocus]) {
                items[currentFocus].click();
                event.preventDefault();
            } else if (event.key === 'Escape') {
                searchResults.innerHTML = '';
                searchResults.style.display = 'none';
                searchInput.blur();
            }
        });

        document.addEventListener('click', function (event) {
            if (!searchForm.contains(event.target)) {
                searchResults.innerHTML = '';
                searchResults.style.display = 'none';
                searchResults.setAttribute('aria-hidden', 'true');
            }
        });

        function fetchProducts(query) {
            var searchSpinner = searchForm.querySelector('.rmh-search-spinner');
            if (searchSpinner) {
                searchSpinner.style.display = 'block';
            }

            searchResults.setAttribute('aria-hidden', 'false');
            searchResults.setAttribute('aria-live', 'polite');
            searchResults.setAttribute('aria-busy', 'true');

            var xhr = new XMLHttpRequest();
            var ajaxUrl = (typeof window.rmhData !== 'undefined' && window.rmhData.ajaxUrl) ? window.rmhData.ajaxUrl : '/wp-admin/admin-ajax.php';
            var nonce = (typeof window.rmhData !== 'undefined' && window.rmhData.nonces && window.rmhData.nonces.search) ? window.rmhData.nonces.search : '';

            xhr.open('GET', ajaxUrl + '?action=rmh_search_products&q=' + encodeURIComponent(query) + '&nonce=' + encodeURIComponent(nonce), true);

            xhr.onload = function () {
                if (searchSpinner) {
                    searchSpinner.style.display = 'none';
                }

                searchResults.removeAttribute('aria-busy');

                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);

                        if (response.success && response.data && response.data.results) {
                            var products = response.data.results;

                            if (products.length === 0) {
                                var emptyMsg = (typeof window.rmhData !== 'undefined' && window.rmhData.strings && window.rmhData.strings.searchEmpty) ? window.rmhData.strings.searchEmpty : 'No medicines found.';
                                searchResults.innerHTML = '<div class="rmh-search-item rmh-search-empty">' + emptyMsg + '</div>';
                                searchResults.style.display = 'block';
                                return;
                            }

                            var html = '';
                            products.forEach(function (product) {
                                html += '<a class="rmh-search-item" href="' + escapeHtml(product.permalink) + '" role="option">';
                                if (product.image) {
                                    var fallbackSrc = (typeof window.rmhData !== 'undefined' && window.rmhData.fallbackImage) ? window.rmhData.fallbackImage : '';
                                    html += '<img class="rmh-search-thumb" src="' + escapeHtml(product.image) + '" alt="" loading="lazy"' + (fallbackSrc ? ' onerror="this.onerror=null;this.src=\'' + escapeHtml(fallbackSrc) + '\';"' : '') + '>';
                                }
                                html += '<div class="rmh-search-info">';
                                html += '<div class="rmh-search-title">' + escapeHtml(product.name) + '</div>';
                                var meta = [];
                                if (product.composition) {
                                    meta.push(escapeHtml(product.composition));
                                }
                                if (product.brand) {
                                    meta.push(escapeHtml(product.brand));
                                }
                                if (product.pack) {
                                    meta.push(escapeHtml(product.pack));
                                }
                                if (meta.length) {
                                    html += '<div class="rmh-search-brand">' + meta.join(' &bull; ') + '</div>';
                                }
                                if (product.price) {
                                    html += '<div class="rmh-search-price">' + escapeHtml(product.price) + '</div>';
                                }
                                if (!product.in_stock) {
                                    html += '<span class="rmh-search-stock">Out of Stock</span>';
                                }
                                html += '</div>';
                                html += '</a>';
                            });

                            searchResults.innerHTML = html;
                            searchResults.style.display = 'block';
                            currentFocus = -1;
                        } else {
                            throw new Error('Invalid response');
                        }
                    } catch (e) {
                        searchResults.innerHTML = '<div class="rmh-search-item rmh-search-error">Search error. Please try again.</div>';
                        searchResults.style.display = 'block';
                    }
                } else {
                    searchResults.innerHTML = '<div class="rmh-search-item rmh-search-error">Search error. Please try again.</div>';
                    searchResults.style.display = 'block';
                }
            };

            xhr.onerror = function () {
                if (searchSpinner) {
                    searchSpinner.style.display = 'none';
                }
                searchResults.removeAttribute('aria-busy');
                searchResults.innerHTML = '<div class="rmh-search-item rmh-search-error">Network error. Please check your connection.</div>';
                searchResults.style.display = 'block';
            };

            xhr.send();
        }

        function highlightItem(items, index) {
            if (index >= items.length) {
                currentFocus = -1;
            } else if (index < -1) {
                currentFocus = items.length - 1;
            }

            items.forEach(function (item, i) {
                if (i === currentFocus) {
                    item.style.background = '#f5fbf7';
                    item.setAttribute('aria-selected', 'true');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.style.background = '';
                    item.setAttribute('aria-selected', 'false');
                }
            });
        }
    }

    // Add to cart feedback
    function initCartFeedback() {
        document.addEventListener('click', function (event) {
            var addToCartBtn = event.target.closest('.add_to_cart_button');

            if (!addToCartBtn) {
                return;
            }

            var originalText = addToCartBtn.textContent;
            addToCartBtn.textContent = 'Added!';
            addToCartBtn.style.background = '#08754f';
            addToCartBtn.disabled = true;

            setTimeout(function () {
                addToCartBtn.textContent = originalText;
                addToCartBtn.style.background = '';
                addToCartBtn.disabled = false;
            }, 1500);
        });
    }

    // Mobile bottom navigation — cart badge sync only.
    // The PHP wp_footer hook already renders .rmh-mobile-nav.
    // This function ONLY handles dynamic badge updates and search icon focus.
    function initMobileNav() {
        var phpNav = document.querySelector('.rmh-mobile-nav');

        if (!phpNav) {
            return; // PHP nav not present — nothing to do
        }

        // Wire up search icon to focus the header search input instead of scrolling
        var searchLink = phpNav.querySelector('.rmh-mobile-nav__search, a[href*="#rmh-popular-categories"]');
        if (searchLink) {
            searchLink.addEventListener('click', function (event) {
                var searchInput = document.getElementById('rmh-site-search-field');
                if (searchInput) {
                    event.preventDefault();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    setTimeout(function () {
                        searchInput.focus();
                    }, 300);
                }
            });
        }

        // Sync cart badge dynamically when WooCommerce fires cart events
        function syncCartBadge() {
            var headerCount = document.querySelector('.rmh-cart-count');
            var badge = phpNav.querySelector('.rmh-mobile-nav__cart-badge');
            if (headerCount && badge) {
                badge.textContent = headerCount.textContent.trim();
            }
        }

        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).on('added_to_cart removed_from_cart wc_fragments_refreshed wc_fragments_loaded', syncCartBadge);
        }

        // MutationObserver on header cart count
        var headerCount = document.querySelector('.rmh-cart-count');
        if (headerCount) {
            var observer = new MutationObserver(syncCartBadge);
            observer.observe(headerCount, { childList: true, characterData: true, subtree: true });
        }
    }

    function closeMenu(header, toggle) {
        header.classList.remove('is-menu-open');
        toggle.setAttribute('aria-expanded', 'false');
    }

    function initStoreView(showcase) {
        var controls = showcase.querySelectorAll('[data-rmh-direction]');
        var visual = showcase.querySelector('.rmh-360-showcase__visual');
        var badge = showcase.querySelector('.rmh-360-badge');
        var view = 0;

        if (!controls.length || !visual || !badge) {
            return;
        }

        controls.forEach(function (control) {
            control.addEventListener('click', function () {
                view = control.getAttribute('data-rmh-direction') === 'next' ? view + 1 : view - 1;
                view = (view + 8) % 8;
                showcase.setAttribute('data-rmh-view', String(view));
                visual.style.setProperty('--rmh-view-rotation', String(view * 45) + 'deg');
                badge.textContent = '360° View ' + String(view + 1);
            });
        });
    }

    function initPrescriptionCapture() {
        var form = document.getElementById('rmhPrescriptionForm');
        var fileInput = document.getElementById('prescriptionFile');
        var cameraInput = document.getElementById('rmhPrescriptionCamera');
        var status = document.getElementById('rmh-prescription-file-status');

        if (!form || !fileInput || !cameraInput || !status) {
            return;
        }

        function syncInputs(source, other) {
            if (!source.files.length) {
                return;
            }

            other.value = '';
            status.textContent = source.files[0].name;
        }

        fileInput.addEventListener('change', function () {
            syncInputs(fileInput, cameraInput);
        });

        cameraInput.addEventListener('change', function () {
            syncInputs(cameraInput, fileInput);
        });

        form.addEventListener('submit', function () {
            var submit = form.querySelector('button[type="submit"]');

            if (!form.checkValidity() || (!fileInput.files.length && !cameraInput.files.length)) {
                return;
            }

            if (submit) {
                submit.disabled = true;
                submit.setAttribute('aria-busy', 'true');
            }

            status.textContent = 'Uploading prescription...';
        });
    }

    // Delivery location modal logic.
    function initDeliveryLocationModal() {
        var modalBackdrop = document.getElementById('rmhLocationModalBackdrop');
        var modal = document.getElementById('rmhLocationModal');
        var closeBtn = document.getElementById('rmhLocationModalClose');
        var tabGps = document.getElementById('rmhTabGps');
        var tabManual = document.getElementById('rmhTabManual');
        var panelGps = document.getElementById('rmhPanelGps');
        var panelManual = document.getElementById('rmhPanelManual');
        var checkGpsBtn = document.getElementById('rmhCheckGpsBtn');
        var switchToManualBtn = document.getElementById('rmhSwitchToManualBtn');
        var errorManualBtn = document.getElementById('rmhErrorManualBtn');
        var gpsRetryBtn = document.getElementById('rmhGpsRetryBtn');
        var gpsChangeBtn = document.getElementById('rmhGpsChangeBtn');
        var manualForm = document.getElementById('rmhManualAddressForm');
        var manualFeedback = document.getElementById('rmhManualFeedback');
        var triggerButtons = [
            document.getElementById('rmhHeaderLocationBadge'),
            document.getElementById('rmhTriggerDeliveryModal')
        ].filter(Boolean);
        ['rmhStepLocationBtnOrder', 'rmhStepLocationBtnDeliveryPromo', 'rmhStepLocationBtnDelivery', 'rmhStepLocationBtnHowto'].forEach(function (id) {
            var trigger = document.getElementById(id);
            if (trigger) {
                triggerButtons.push(trigger);
            }
        });

        var lastFocusedElement = null;

        if (!modalBackdrop || !modal) {
            return;
        }

        var storeLat = 20.4625;
        var storeLng = 85.8828;
        var maxRadiusKm = 10;
        var allowedPostcodes = ['753012', '753001', '753002', '753003'];

        if (typeof window.rmhData !== 'undefined') {
            if (window.rmhData.storeLat) storeLat = parseFloat(window.rmhData.storeLat);
            if (window.rmhData.storeLng) storeLng = parseFloat(window.rmhData.storeLng);
            if (window.rmhData.maxRadiusKm) maxRadiusKm = parseFloat(window.rmhData.maxRadiusKm);
            if (window.rmhData.allowedPostcodes && Array.isArray(window.rmhData.allowedPostcodes)) {
                allowedPostcodes = window.rmhData.allowedPostcodes;
            }
        }

        function openModal() {
            lastFocusedElement = document.activeElement;
            modalBackdrop.hidden = false;
            modalBackdrop.classList.add('is-visible');
            document.body.style.overflow = 'hidden';
            modal.setAttribute('tabindex', '-1');
            modal.focus();
            resetStates();
            trapFocus(modal);
        }

        function closeModal() {
            modalBackdrop.classList.remove('is-visible');
            document.body.style.overflow = '';
            setTimeout(function () {
                modalBackdrop.hidden = true;
            }, 300);
            if (lastFocusedElement) {
                lastFocusedElement.focus();
            }
        }

        function trapFocus(element) {
            var focusableElements = element.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            var firstElement = focusableElements[0];
            var lastElement = focusableElements[focusableElements.length - 1];

            element.addEventListener('keydown', function handleTab(e) {
                if (e.key !== 'Tab') return;

                if (e.shiftKey) {
                    if (document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            });
        }

        function resetStates() {
            var states = modal.querySelectorAll('.rmh-loc-state');
            states.forEach(function (s) { s.classList.remove('is-active'); });
            var initial = document.getElementById('rmhGpsInitialState');
            if (initial) initial.classList.add('is-active');

            var panels = modal.querySelectorAll('.rmh-tab-panel');
            panels.forEach(function (p) { p.hidden = true; p.classList.remove('is-active'); });
            if (panelGps) { panelGps.hidden = false; panelGps.classList.add('is-active'); }
            if (panelManual) { panelManual.hidden = true; panelManual.classList.remove('is-active'); }
            if (tabGps) { tabGps.classList.add('is-active'); tabGps.setAttribute('aria-selected', 'true'); }
            if (tabManual) { tabManual.classList.remove('is-active'); tabManual.setAttribute('aria-selected', 'false'); }
            if (manualFeedback) { manualFeedback.hidden = true; manualFeedback.classList.remove('is-success', 'is-error'); }
            if (manualForm) manualForm.reset();
        }

        function haversineDistance(lat1, lng1, lat2, lng2) {
            var R = 6371;
            var dLat = (lat2 - lat1) * Math.PI / 180;
            var dLng = (lng2 - lng1) * Math.PI / 180;
            var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLng / 2) * Math.sin(dLng / 2);
            var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        function showState(stateId) {
            var states = modal.querySelectorAll('.rmh-loc-state');
            states.forEach(function (s) { s.classList.remove('is-active'); });
            var state = document.getElementById(stateId);
            if (state) state.classList.add('is-active');
        }

        function setManualFeedback(message, type) {
            if (!manualFeedback) return;
            manualFeedback.hidden = false;
            manualFeedback.textContent = message;
            manualFeedback.classList.remove('is-success', 'is-error');
            manualFeedback.classList.add(type === 'success' ? 'is-success' : 'is-error');
        }

        function checkGpsEligibility(lat, lng) {
            var distance = haversineDistance(lat, lng, storeLat, storeLng);
            var distanceText = document.getElementById('rmhGpsDistanceText');
            if (distanceText) {
                distanceText.textContent = '\uD83D\uDCCD ' + distance.toFixed(1) + ' km from pharmacy';
            }

            if (distance <= maxRadiusKm) {
                showState('rmhGpsEligibleState');
            } else {
                var outsideDistanceText = document.getElementById('rmhGpsOutsideDistanceText');
                if (outsideDistanceText) {
                    outsideDistanceText.textContent = '\uD83D\uDCCD ' + distance.toFixed(1) + ' km from pharmacy';
                }
                showState('rmhGpsOutsideState');
            }
        }

        function handleGpsSuccess(position) {
            var lat = position.coords.latitude;
            var lng = position.coords.longitude;
            checkGpsEligibility(lat, lng);
        }

        function handleGpsError(error) {
            var errorTitle = document.getElementById('rmhGpsErrorTitle');
            var errorDesc = document.getElementById('rmhGpsErrorDesc');
            if (errorTitle) errorTitle.textContent = 'Location Permission Not Granted';
            if (errorDesc) errorDesc.textContent = 'No problem at all! You can manually enter your delivery address or PIN code below.';
            showState('rmhGpsErrorState');
        }

        if (checkGpsBtn) {
            checkGpsBtn.addEventListener('click', function () {
                showState('rmhGpsLoadingState');
                if (!navigator.geolocation) {
                    handleGpsError({ code: 0, message: 'Geolocation not supported' });
                    return;
                }
                navigator.geolocation.getCurrentPosition(handleGpsSuccess, handleGpsError, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            });
        }

        if (switchToManualBtn) {
            switchToManualBtn.addEventListener('click', function () {
                if (panelManual) { panelManual.hidden = false; panelManual.classList.add('is-active'); }
                if (panelGps) { panelGps.hidden = true; panelGps.classList.remove('is-active'); }
                if (tabManual) { tabManual.classList.add('is-active'); tabManual.setAttribute('aria-selected', 'true'); }
                if (tabGps) { tabGps.classList.remove('is-active'); tabGps.setAttribute('aria-selected', 'false'); }
            });
        }

        if (errorManualBtn) {
            errorManualBtn.addEventListener('click', function () {
                if (panelManual) { panelManual.hidden = false; panelManual.classList.add('is-active'); }
                if (panelGps) { panelGps.hidden = true; panelGps.classList.remove('is-active'); }
                if (tabManual) { tabManual.classList.add('is-active'); tabManual.setAttribute('aria-selected', 'true'); }
                if (tabGps) { tabGps.classList.remove('is-active'); tabGps.setAttribute('aria-selected', 'false'); }
            });
        }

        if (gpsRetryBtn) {
            gpsRetryBtn.addEventListener('click', function () {
                showState('rmhGpsInitialState');
            });
        }

        if (gpsChangeBtn) {
            gpsChangeBtn.addEventListener('click', function () {
                showState('rmhGpsInitialState');
            });
        }

        if (tabGps) {
            tabGps.addEventListener('click', function () {
                if (panelGps) { panelGps.hidden = false; panelGps.classList.add('is-active'); }
                if (panelManual) { panelManual.hidden = true; panelManual.classList.remove('is-active'); }
                tabGps.classList.add('is-active');
                tabGps.setAttribute('aria-selected', 'true');
                if (tabManual) {
                    tabManual.classList.remove('is-active');
                    tabManual.setAttribute('aria-selected', 'false');
                }
                showState('rmhGpsInitialState');
            });
        }

        if (tabManual) {
            tabManual.addEventListener('click', function () {
                if (panelManual) { panelManual.hidden = false; panelManual.classList.add('is-active'); }
                if (panelGps) { panelGps.hidden = true; panelGps.classList.remove('is-active'); }
                tabManual.classList.add('is-active');
                tabManual.setAttribute('aria-selected', 'true');
                if (tabGps) {
                    tabGps.classList.remove('is-active');
                    tabGps.setAttribute('aria-selected', 'false');
                }
            });
        }

        if (manualForm) {
            manualForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var postcodeEl = document.getElementById('rmhManualPostcode');
                var pin = postcodeEl ? postcodeEl.value.trim().toUpperCase() : '';
                var normalizedPin = pin.replace(/[^A-Z0-9]/g, '');

                if (!normalizedPin) {
                    setManualFeedback('Please enter a valid PIN code.', 'error');
                    return;
                }

                if (allowedPostcodes.indexOf(normalizedPin) === -1) {
                    setManualFeedback('Delivery is not available for PIN code ' + normalizedPin + '. Please use a supported address or choose regular delivery at checkout.', 'error');
                    return;
                }

                var xhr = new XMLHttpRequest();
                var ajaxUrl = (typeof window.rmhData !== 'undefined' && window.rmhData.ajaxUrl) ? window.rmhData.ajaxUrl : '/wp-admin/admin-ajax.php';
                var nonce = (typeof window.rmhData !== 'undefined' && window.rmhData.nonces && window.rmhData.nonces.location) ? window.rmhData.nonces.location : '';

                xhr.open('POST', ajaxUrl + '?action=rmh_save_delivery_location', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

                xhr.onload = function () {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                setManualFeedback('Location saved: ' + response.data.postcode + '. ' + response.data.message, 'success');
                                updateHeaderLocation(response.data.postcode, response.data.is_local);
                            } else {
                                setManualFeedback(response.data.message || 'Could not save location.', 'error');
                            }
                        } catch (err) {
                            setManualFeedback('Could not save location. Please try again.', 'error');
                        }
                    } else {
                        setManualFeedback('Could not save location. Please try again.', 'error');
                    }
                };

                xhr.onerror = function () {
                    setManualFeedback('Network error. Please check your connection.', 'error');
                };

                xhr.send('nonce=' + encodeURIComponent(nonce) + '&postcode=' + encodeURIComponent(normalizedPin));
            });
        }

        triggerButtons.forEach(function (btn) {
            if (!btn) return;
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                openModal();
            });
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }

        if (modalBackdrop) {
            modalBackdrop.addEventListener('click', function (e) {
                if (e.target === modalBackdrop) {
                    closeModal();
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modalBackdrop.classList.contains('is-visible')) {
                closeModal();
            }
        });
    }

    function updateHeaderLocation(postcode, isLocal) {
        var headerLocName = document.getElementById('rmhHeaderLocName');
        if (headerLocName) {
            headerLocName.textContent = postcode + (isLocal ? ' (Local)' : ' (Odisha)');
        }
    }

    function loadSavedLocation() {
        var allowedPostcodes = ['753012', '753001', '753002', '753003'];
        if (typeof window.rmhData !== 'undefined' && window.rmhData.allowedPostcodes && Array.isArray(window.rmhData.allowedPostcodes)) {
            allowedPostcodes = window.rmhData.allowedPostcodes;
        }

        var xhr = new XMLHttpRequest();
        var ajaxUrl = (typeof window.rmhData !== 'undefined' && window.rmhData.ajaxUrl) ? window.rmhData.ajaxUrl : '/wp-admin/admin-ajax.php';
        var nonce = (typeof window.rmhData !== 'undefined' && window.rmhData.nonces && window.rmhData.nonces.location) ? window.rmhData.nonces.location : '';

        xhr.open('GET', ajaxUrl + '?action=rmh_get_delivery_location&nonce=' + encodeURIComponent(nonce), true);

        xhr.onload = function () {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success && response.data && response.data.postcode) {
                        var isLocal = allowedPostcodes.indexOf(response.data.postcode) !== -1;
                        updateHeaderLocation(response.data.postcode, isLocal);
                    }
                } catch (err) {
                    // Ignore parse errors on load
                }
            }
        };

        xhr.send();
    }

    function initTickerTouchPause() {
        var ribbons = document.querySelectorAll('.rmh-announcement-ribbon, .rmh-marquee-section');
        ribbons.forEach(function (ribbon) {
            var track = ribbon.querySelector('.rmh-announcement-ribbon__track, .rmh-marquee-track');
            if (!track) return;
            ribbon.addEventListener('touchstart', function () {
                track.style.animationPlayState = 'paused';
            }, { passive: true });
            ribbon.addEventListener('touchend', function () {
                track.style.animationPlayState = 'running';
            }, { passive: true });
        });
    }

    function initEPharmacyRxModal() {
        var backdrop = document.getElementById('rmhPrescriptionModalBackdrop');
        var modal = document.getElementById('rmhPrescriptionModal');
        var closeBtn = document.getElementById('rmhPrescriptionModalClose');
        var form = document.getElementById('rmhModalRxForm');
        var fileInput = document.getElementById('modalRxFile');
        var cameraInput = document.getElementById('modalRxCamera');
        var fileStatus = document.getElementById('modalRxFileStatus');
        var feedback = document.getElementById('modalRxFeedback');
        var submitBtn = document.getElementById('modalRxSubmitBtn');

        if (!backdrop || !modal) return;

        function openRxModal(e) {
            if (e && e.preventDefault) e.preventDefault();
            backdrop.removeAttribute('hidden');
            backdrop.classList.add('is-visible');
            document.body.style.overflow = 'hidden';
            var nameField = document.getElementById('modalRxName');
            if (nameField) nameField.focus();
        }

        function closeRxModal() {
            backdrop.setAttribute('hidden', '');
            backdrop.classList.remove('is-visible');
            document.body.style.overflow = '';
        }

        document.addEventListener('click', function(e) {
            var target = e.target.closest('.rmh-open-rx-modal, #rmhTriggerRxModal');
            if (target) {
                openRxModal(e);
            }
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', closeRxModal);
        }
        backdrop.addEventListener('click', function(e) {
            if (e.target === backdrop) {
                closeRxModal();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !backdrop.hasAttribute('hidden')) {
                closeRxModal();
            }
        });

        function updateFileLabel(input) {
            if (input && input.files && input.files.length > 0) {
                var file = input.files[0];
                if (fileStatus) {
                    fileStatus.textContent = 'Selected: ' + file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
                    fileStatus.style.color = '#007A53';
                    fileStatus.style.fontWeight = 'bold';
                }
            }
        }
        if (fileInput) fileInput.addEventListener('change', function() { updateFileLabel(fileInput); });
        if (cameraInput) cameraInput.addEventListener('change', function() { updateFileLabel(cameraInput); });

        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var nameEl = document.getElementById('modalRxName');
                var phoneEl = document.getElementById('modalRxPhone');
                var name = nameEl ? nameEl.value.trim() : '';
                var phone = phoneEl ? phoneEl.value.trim() : '';
                var hasFile = (fileInput && fileInput.files.length > 0) || (cameraInput && cameraInput.files.length > 0);

                if (!name || !phone) {
                    if (feedback) {
                        feedback.style.display = 'block';
                        feedback.className = 'rmh-form-feedback rmh-form-feedback--error';
                        feedback.textContent = 'Please provide patient name and mobile number.';
                    }
                    return;
                }
                if (!hasFile) {
                    if (feedback) {
                        feedback.style.display = 'block';
                        feedback.className = 'rmh-form-feedback rmh-form-feedback--error';
                        feedback.textContent = 'Please choose a prescription document or capture a photo.';
                    }
                    return;
                }

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Uploading Prescription...';
                }

                var formData = new FormData(form);
                var ajaxUrl = (typeof window.rmhQuickAdd !== 'undefined') ? window.rmhQuickAdd.ajaxUrl : '/wp-admin/admin-ajax.php';

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Submit Prescription for Review';
                    }
                    if (feedback) {
                        feedback.style.display = 'block';
                        if (data && data.success) {
                            feedback.className = 'rmh-form-feedback rmh-form-feedback--success';
                            feedback.textContent = data.data && data.data.message ? data.data.message : 'Prescription uploaded successfully!';
                            form.reset();
                            if (fileStatus) fileStatus.textContent = 'Accepted formats: JPG, PNG, WEBP, PDF up to 10 MB.';
                            setTimeout(function() {
                                closeRxModal();
                            }, 2500);
                        } else {
                            feedback.className = 'rmh-form-feedback rmh-form-feedback--error';
                            feedback.textContent = data.data && data.data.message ? data.data.message : 'Upload failed. Please try again.';
                        }
                    }
                })
                .catch(function() {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Submit Prescription for Review';
                    }
                    if (feedback) {
                        feedback.style.display = 'block';
                        feedback.className = 'rmh-form-feedback rmh-form-feedback--error';
                        feedback.textContent = 'Network error while uploading prescription. Please try again.';
                    }
                });
            });
        }
    }

    function initEPharmacyQuickAdd() {
        document.addEventListener('click', function(e) {
            var addBtn = e.target.closest('.rmh-quick-add-btn');
            if (addBtn) {
                e.preventDefault();
                var wrap = addBtn.closest('.rmh-quick-add-wrap');
                var productId = addBtn.getAttribute('data-product-id');
                if (!productId) return;

                addBtn.disabled = true;
                addBtn.innerHTML = '<span>Adding...</span>';

                var ajaxUrl = (typeof window.rmhQuickAdd !== 'undefined') ? window.rmhQuickAdd.ajaxUrl : '/wp-admin/admin-ajax.php';
                var nonce = (typeof window.rmhQuickAdd !== 'undefined') ? window.rmhQuickAdd.nonce : '';

                var bodyData = new URLSearchParams();
                bodyData.append('action', 'rmh_quick_add');
                bodyData.append('product_id', productId);
                bodyData.append('quantity', '1');
                bodyData.append('nonce', nonce);

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: bodyData.toString()
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    addBtn.disabled = false;
                    if (data && data.success) {
                        if (data.data && data.data.rx_required) {
                            addBtn.innerHTML = '<span>Order with Rx</span>';
                            var rxTrigger = document.querySelector('.rmh-open-rx-modal');
                            if (rxTrigger) rxTrigger.click();
                            return;
                        }
                        if (wrap) {
                            addBtn.style.display = 'none';
                            var stepper = wrap.querySelector('.rmh-qty-stepper');
                            var qtyVal = wrap.querySelector('.rmh-stepper-qty');
                            if (stepper && qtyVal) {
                                qtyVal.textContent = '1';
                                stepper.style.display = 'inline-flex';
                            }
                        }
                        if (typeof jQuery !== 'undefined') {
                            jQuery(document.body).trigger('wc_fragment_refresh');
                        }
                    } else {
                        addBtn.innerHTML = '<span style="color:#c62828;">Error</span>';
                        setTimeout(function() {
                            addBtn.innerHTML = '<span class="rmh-quick-add-icon">+</span><span>Add</span>';
                        }, 2000);
                    }
                })
                .catch(function() {
                    addBtn.disabled = false;
                    addBtn.innerHTML = '<span class="rmh-quick-add-icon">+</span><span>Add</span>';
                });
                return;
            }

            var stepPlus = e.target.closest('.rmh-stepper-plus');
            if (stepPlus) {
                e.preventDefault();
                var wrap = stepPlus.closest('.rmh-quick-add-wrap');
                var productId = wrap ? wrap.getAttribute('data-product-id') : null;
                var qtyEl = wrap ? wrap.querySelector('.rmh-stepper-qty') : null;
                if (!productId || !qtyEl) return;

                var curQty = parseInt(qtyEl.textContent, 10) || 1;
                var newQty = curQty + 1;
                qtyEl.textContent = newQty;

                var ajaxUrl = (typeof window.rmhQuickAdd !== 'undefined') ? window.rmhQuickAdd.ajaxUrl : '/wp-admin/admin-ajax.php';
                var nonce = (typeof window.rmhQuickAdd !== 'undefined') ? window.rmhQuickAdd.nonce : '';

                var bodyData = new URLSearchParams();
                bodyData.append('action', 'rmh_quick_add');
                bodyData.append('product_id', productId);
                bodyData.append('quantity', '1');
                bodyData.append('nonce', nonce);

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: bodyData.toString()
                }).then(function() {
                    if (typeof jQuery !== 'undefined') {
                        jQuery(document.body).trigger('wc_fragment_refresh');
                    }
                });
                return;
            }

            var stepMinus = e.target.closest('.rmh-stepper-minus');
            if (stepMinus) {
                e.preventDefault();
                var wrap = stepMinus.closest('.rmh-quick-add-wrap');
                var productId = wrap ? wrap.getAttribute('data-product-id') : null;
                var qtyEl = wrap ? wrap.querySelector('.rmh-stepper-qty') : null;
                var addBtn = wrap ? wrap.querySelector('.rmh-quick-add-btn') : null;
                var stepper = wrap ? wrap.querySelector('.rmh-qty-stepper') : null;
                if (!qtyEl) return;

                var curQty = parseInt(qtyEl.textContent, 10) || 1;
                var newQty = Math.max(0, curQty - 1);
                var ajaxUrl = (typeof window.rmhQuickAdd !== 'undefined') ? window.rmhQuickAdd.ajaxUrl : '/wp-admin/admin-ajax.php';
                var nonce = (typeof window.rmhQuickAdd !== 'undefined') ? window.rmhQuickAdd.nonce : '';
                var bodyData = new URLSearchParams();
                bodyData.append('action', 'rmh_update_cart_product');
                bodyData.append('product_id', productId || '');
                bodyData.append('quantity', String(newQty));
                bodyData.append('nonce', nonce);
                fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: bodyData.toString() })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (!data || !data.success) return;
                    if (newQty > 0) {
                        qtyEl.textContent = String(newQty);
                    } else {
                        if (stepper) stepper.style.display = 'none';
                        if (addBtn) {
                            addBtn.style.display = 'inline-flex';
                            addBtn.innerHTML = '<span class="rmh-quick-add-icon">+</span><span>Add</span>';
                        }
                    }
                    if (typeof jQuery !== 'undefined') jQuery(document.body).trigger('wc_fragment_refresh');
                });
                return;
            }
        });
    }

    function initDeliveryFeedback() {
        var form = document.getElementById('rmhDeliveryFeedbackForm');
        if (!form) return;

        var starBtns = form.querySelectorAll('.rmh-star-btn');
        var ratingInput = document.getElementById('rmhFeedbackRatingInput');
        var responseEl = document.getElementById('rmhFeedbackResponse');
        var submitBtn = document.getElementById('rmhSubmitFeedbackBtn');

        starBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var val = parseInt(this.getAttribute('data-star'), 10) || 5;
                if (ratingInput) ratingInput.value = val;
                starBtns.forEach(function (b) {
                    var s = parseInt(b.getAttribute('data-star'), 10) || 1;
                    b.classList.toggle('is-active', s <= val);
                });
            });
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting...';
            }
            if (responseEl) {
                responseEl.style.display = 'none';
                responseEl.className = 'rmh-form-feedback';
            }

            var formData = new FormData(form);
            var ajaxUrl = (window.rmhQuickAdd && window.rmhQuickAdd.ajaxUrl) ? window.rmhQuickAdd.ajaxUrl : '/wp-admin/admin-ajax.php';

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                        submitBtn.textContent = 'Submit Feedback';
                }
                if (responseEl) {
                    responseEl.style.display = 'block';
                    if (data && data.success) {
                        responseEl.className = 'rmh-form-feedback rmh-form-feedback--success';
                        responseEl.textContent = (data.data && data.data.message) ? data.data.message : 'Thank you for your feedback!';
                        form.reset();
                        if (ratingInput) ratingInput.value = '5';
                        starBtns.forEach(function (b) { b.classList.add('is-active'); });
                    } else {
                        responseEl.className = 'rmh-form-feedback rmh-form-feedback--error';
                        responseEl.textContent = (data && data.data && data.data.message) ? data.data.message : 'Submission failed. Please try again.';
                    }
                }
            })
            .catch(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Delivery Feedback';
                }
                if (responseEl) {
                    responseEl.style.display = 'block';
                    responseEl.className = 'rmh-form-feedback rmh-form-feedback--error';
                    responseEl.textContent = 'Network error. Please try again.';
                }
            });
        });
    }

    function initOrderTracking() {
        var form = document.getElementById('rmhOrderTrackingForm');
        var resultContainer = document.getElementById('rmhTrackResultContainer');
        var feedback = document.getElementById('rmhTrackFeedback');
        var submitBtn = document.getElementById('rmhTrackSubmitBtn');

        if (!form || !resultContainer) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var orderNumberInput = document.getElementById('rmhTrackOrderNumber');
            var contactInput = document.getElementById('rmhTrackContact');
            var orderNumber = orderNumberInput ? orderNumberInput.value.trim() : '';
            var contact = contactInput ? contactInput.value.trim() : '';
            var nonceInput = document.getElementById('rmhTrackOrderNonce');
            var nonce = nonceInput ? nonceInput.value : (window.rmhQuickAdd ? window.rmhQuickAdd.trackNonce : '');
            var ajaxUrl = (typeof window.rmhData !== 'undefined' && window.rmhData.ajaxUrl) ? window.rmhData.ajaxUrl : '/wp-admin/admin-ajax.php';

            if (!orderNumber || !contact) {
                if (feedback) {
                    feedback.style.display = 'block';
                    feedback.className = 'rmh-form-feedback rmh-form-feedback--error';
                    feedback.textContent = 'Please enter both your Order Number and Mobile/Email.';
                }
                return;
            }

            if (feedback) {
                feedback.style.display = 'none';
                feedback.textContent = '';
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Verifying with pharmacy...';
            }

            var formData = new FormData();
            formData.append('action', 'rmh_track_order');
            formData.append('nonce', nonce);
            formData.append('order_number', orderNumber);
            formData.append('order_contact', contact);

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function (res) { return res.json(); })
            .then(function (res) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Track Order';
                }

                if (res.success && res.data) {
                    var data = res.data;
                    form.style.display = 'none';
                    resultContainer.style.display = 'block';

                    var itemsHtml = '';
                    if (data.items && data.items.length) {
                        itemsHtml = '<div class="rmh-track-items"><h4>' + (data.items.length === 1 ? '1 Medicine Item' : data.items.length + ' Medicine Items') + '</h4><ul class="rmh-track-items-list">';
                        data.items.forEach(function (it) {
                            itemsHtml += '<li><span class="rmh-track-item-name">' + it.name + ' × ' + it.quantity + '</span><span class="rmh-track-item-price">' + it.subtotal + '</span></li>';
                        });
                        itemsHtml += '</ul></div>';
                    }

                    var timelineHtml = '';
                    if (data.milestones && data.milestones.length) {
                        timelineHtml = '<div class="rmh-track-timeline"><h4>Live Status</h4><div class="rmh-track-steps">';
                        data.milestones.forEach(function (step) {
                            var statusClass = step.completed ? 'is-completed' : (step.current ? 'is-current' : (step.is_error ? 'is-error' : 'is-pending'));
                            timelineHtml += '<div class="rmh-track-step ' + statusClass + '">';
                            timelineHtml += '<div class="rmh-track-step__icon"></div>';
                            timelineHtml += '<div class="rmh-track-step__content"><strong>' + step.title + '</strong><p>' + step.desc + '</p></div>';
                            timelineHtml += '</div>';
                        });
                        timelineHtml += '</div></div>';
                    }

                    resultContainer.innerHTML = [
                        '<div class="rmh-track-card-header">',
                            '<span class="rmh-track-order-num">Order #' + data.order_number + '</span>',
                            '<span class="rmh-track-status-pill rmh-status--' + data.status_key + '">' + data.status_label + '</span>',
                        '</div>',
                        '<div class="rmh-track-meta">',
                            '<p><strong>Order Date:</strong> ' + data.order_date + '</p>',
                            '<p><strong>Delivery Method:</strong> ' + data.shipping_method + '</p>',
                            '<p><strong>Total Amount:</strong> <span class="rmh-track-total">' + data.total_formatted + '</span></p>',
                        '</div>',
                        itemsHtml,
                        timelineHtml,
                        '<div style="margin-top:18px;">',
                            '<button type="button" class="rmh-btn rmh-btn-secondary" id="rmhTrackAnotherBtn">← Track Another Order</button>',
                        '</div>'
                    ].join('');

                    var resetBtn = document.getElementById('rmhTrackAnotherBtn');
                    if (resetBtn) {
                        resetBtn.addEventListener('click', function () {
                            resultContainer.style.display = 'none';
                            resultContainer.innerHTML = '';
                            form.style.display = 'block';
                            if (orderNumberInput) orderNumberInput.value = '';
                            if (contactInput) contactInput.value = '';
                        });
                    }
                } else {
                    if (feedback) {
                        feedback.style.display = 'block';
                        feedback.className = 'rmh-form-feedback rmh-form-feedback--error';
                        feedback.textContent = (res.data && res.data.message) ? res.data.message : 'We could not find a matching order. Please check your order number and phone/email and try again.';
                    }
                }
            })
            .catch(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Track Order';
                }
                if (feedback) {
                    feedback.style.display = 'block';
                    feedback.className = 'rmh-form-feedback rmh-form-feedback--error';
                    feedback.textContent = 'Network or server error while tracking order. Please try again.';
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var header = document.querySelector('.rmh-site-header');

        initScrollAnimations();
        initModernSearch();
        initCartFeedback();
        initMobileNav();
        initDeliveryLocationModal();
        initPrescriptionCapture();
        loadSavedLocation();
        initTickerTouchPause();
        initEPharmacyRxModal();
        initEPharmacyQuickAdd();
        initDeliveryFeedback();
        initOrderTracking();

        document.querySelectorAll('.rmh-360-showcase').forEach(initStoreView);

        if (!header) {
            return;
        }

        var toggle = header.querySelector('.rmh-menu-toggle');
        var nav = header.querySelector('#rmh-primary-navigation');

        if (!toggle || !nav) {
            return;
        }

        // Ensure correct initial ARIA state (button may lack the attribute on first render)
        if (!toggle.hasAttribute('aria-expanded')) {
            toggle.setAttribute('aria-expanded', 'false');
        }

        // Create a backdrop so (a) clicking outside closes the drawer and
        // (b) the fixed drawer cannot intercept pointer events on the toggle
        // (the backdrop sits between the page and the drawer, z-index 45)
        var backdrop = document.createElement('div');
        backdrop.id = 'rmh-nav-backdrop';
        backdrop.setAttribute('aria-hidden', 'true');
        document.body.appendChild(backdrop);

        function openMenuState() {
            header.classList.add('is-menu-open');
            toggle.setAttribute('aria-expanded', 'true');
            backdrop.classList.add('is-active');
            document.body.classList.add('rmh-nav-open');
        }

        function closeMenuState() {
            header.classList.remove('is-menu-open');
            toggle.setAttribute('aria-expanded', 'false');
            backdrop.classList.remove('is-active');
            document.body.classList.remove('rmh-nav-open');
        }

        toggle.addEventListener('click', function () {
            if (header.classList.contains('is-menu-open')) {
                closeMenuState();
            } else {
                openMenuState();
            }
        });

        // Backdrop click closes menu
        backdrop.addEventListener('click', function () {
            closeMenuState();
            toggle.focus();
        });

        // Clicking a nav link also closes the menu
        nav.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                closeMenuState();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && header.classList.contains('is-menu-open')) {
                closeMenuState();
                toggle.focus();
            }
        });
    });
}());
