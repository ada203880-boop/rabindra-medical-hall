/**
 * Rabindra Medical Hall — Mobile UI Interactions & Cart Drawer Integration
 *
 * Handles:
 *  1. Cart drawer open / close (body scroll-lock, aria, animation)
 *  2. All cart triggers: header icon, bottom nav, floating bubble
 *  3. Close: button, backdrop click, Escape key
 *  4. "Shop now" / empty-cart link → closes drawer, scrolls to #shop
 *  5. Mobile nav: search focus, WhatsApp shortcut
 *  6. Prescription upload form validation
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------
       1. CART DRAWER STATE MANAGER
       ------------------------------------------------------------------ */
    var cartModal   = null;
    var cartOpen    = false;

    function getCartModal() {
        if (!cartModal) {
            cartModal = document.getElementById('moderncart-slide-out-modal');
        }
        return cartModal;
    }

    function openCart(e) {
        if (e && e.preventDefault) {
            e.preventDefault();
        }
        var modal = getCartModal();
        if (!modal) { return; }

        cartOpen = true;

        /* Show modal via CSS class */
        modal.classList.add('moderncart-show');
        modal.setAttribute('aria-hidden', 'false');

        /* Scroll-lock body */
        document.body.classList.add('rmh-cart-open');

        /* Focus the close button for accessibility */
        var closeBtn = modal.querySelector('.moderncart-slide-out-header-close');
        if (closeBtn) {
            setTimeout(function () { closeBtn.focus(); }, 320);
        }

        /* Update floating button aria state */
        var floatBtn = document.querySelector('.moderncart-floating-cart-button');
        if (floatBtn) {
            floatBtn.setAttribute('aria-expanded', 'true');
        }
    }

    function closeCart(e) {
        if (e && e.preventDefault) {
            e.preventDefault();
        }
        var modal = getCartModal();
        if (!modal) { return; }

        cartOpen = false;

        modal.classList.remove('moderncart-show');
        modal.setAttribute('aria-hidden', 'true');

        document.body.classList.remove('rmh-cart-open');

        /* Update floating button aria state */
        var floatBtn = document.querySelector('.moderncart-floating-cart-button');
        if (floatBtn) {
            floatBtn.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleCart(e) {
        if (cartOpen) {
            closeCart(e);
        } else {
            openCart(e);
        }
    }

    /* ------------------------------------------------------------------
       2. WIRE CART TRIGGERS
       ------------------------------------------------------------------ */
    function initCart() {
        var modal = getCartModal();
        if (!modal) { return; }

        /* ── 2a. Floating bubble button ── */
        var floatingBtn = document.querySelector('.moderncart-floating-cart-button');
        if (floatingBtn) {
            floatingBtn.addEventListener('click', toggleCart);
        }

        /* ── 2b. Close button inside drawer ── */
        var closeBtn = modal.querySelector('.moderncart-slide-out-header-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeCart);
        }

        /* ── 2c. Backdrop click (clicking outside the drawer panel) ── */
        modal.addEventListener('click', function (e) {
            var panel = modal.querySelector('#moderncart-slide-out');
            if (panel && !panel.contains(e.target)) {
                closeCart();
            }
        });

        /* ── 2d. Escape key ── */
        document.addEventListener('keydown', function (e) {
            if (cartOpen && (e.key === 'Escape' || e.keyCode === 27)) {
                closeCart();
            }
        });

        /* ── 2e. "Shop now" / "Your cart is empty. Shop now →" link ── */
        var shopNowLinks = modal.querySelectorAll('a[href*="#shop"], .checkout-button');
        shopNowLinks.forEach(function (link) {
            link.addEventListener('click', function (ev) {
                closeCart();
                /* If it's an anchor to #shop, smooth-scroll */
                var href = link.getAttribute('href') || '';
                if (href === '#shop' || href.indexOf('#shop') === 0) {
                    ev.preventDefault();
                    var shopSection = document.getElementById('shop');
                    if (shopSection) {
                        setTimeout(function () {
                            shopSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }, 350);
                    }
                }
            });
        });

        /* ── 2f. Header cart icon (if present) ── */
        var headerCart = document.querySelector('.rmh-site-header__cart, .rmh-header-cart, [data-rmh-cart]');
        if (headerCart) {
            headerCart.addEventListener('click', toggleCart);
        }
    }

    /* ------------------------------------------------------------------
       3. BOTTOM NAV WIRING
       ------------------------------------------------------------------ */
    function initMobileNav() {
        /* Cart tab → open drawer */
        var navCart = document.querySelector('.rmh-mobile-nav__cart, [data-rmh-nav="cart"]');
        if (navCart) {
            navCart.addEventListener('click', function (e) {
                e.preventDefault();
                openCart();
            });
        }

        /* Search tab → focus header search */
        var navSearch = document.getElementById('rmhMobileNavSearch');
        if (navSearch) {
            navSearch.addEventListener('click', function (e) {
                var searchField = document.querySelector('.rmh-site-search input[type="search"], #rmh-site-search-field');
                if (searchField) {
                    e.preventDefault();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    setTimeout(function () { searchField.focus(); }, 300);
                }
            });
        }

        /* WhatsApp tab — let it navigate normally (href is set in HTML) */
    }

    /* ------------------------------------------------------------------
       4. FLOATING CART INLINE STYLE OVERRIDE
       The plugin sets: style="bottom: 66.5px !important;"
       Our CSS uses !important but some browsers give inline style priority.
       We override it programmatically after DOM ready.
       ------------------------------------------------------------------ */
    function fixFloatingCartPosition() {
        var floatCart = document.getElementById('moderncart-floating-cart');
        if (floatCart) {
            /* Remove inline style that conflicts with our CSS */
            floatCart.style.removeProperty('bottom');
            /* Re-apply via inline as fallback (CSS !important still wins in most browsers) */
            floatCart.style.setProperty('bottom', 'calc(74px + env(safe-area-inset-bottom, 0px))', 'important');
            floatCart.style.setProperty('position', 'fixed', 'important');
            floatCart.style.setProperty('right', '16px', 'important');
            floatCart.style.setProperty('left', 'auto', 'important');
            floatCart.style.setProperty('z-index', '9998', 'important');
        }
    }

    /* ------------------------------------------------------------------
       5. PRESCRIPTION UPLOAD FORM
       ------------------------------------------------------------------ */
    function initPrescriptionUpload() {
        /* Main section form */
        var form    = document.getElementById('rmhPrescriptionForm');
        var fileInp = document.getElementById('prescriptionFile');
        var camInp  = document.getElementById('rmhPrescriptionCamera');
        var status  = document.getElementById('rmh-prescription-file-status');

        function handleFileSelection(src, other) {
            if (!src || !src.files || !src.files.length) { return; }
            var file = src.files[0];
            var maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                if (status) {
                    status.textContent = 'File exceeds 10 MB. Please choose a smaller file.';
                    status.style.color = '#dc2626';
                }
                src.value = '';
                return;
            }
            if (other) { other.value = ''; }
            if (status) {
                status.textContent = 'Selected: ' + file.name;
                status.style.color = '#007a48';
            }
        }

        if (fileInp) { fileInp.addEventListener('change', function () { handleFileSelection(fileInp, camInp); }); }
        if (camInp)  { camInp.addEventListener('change',  function () { handleFileSelection(camInp,  fileInp); }); }

        if (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('button[type="submit"]');
                if (btn && form.checkValidity() &&
                    ((fileInp && fileInp.files.length) || (camInp && camInp.files.length))) {
                    btn.disabled = true;
                    btn.textContent = 'Submitting…';
                    btn.setAttribute('aria-busy', 'true');
                }
            });
        }

        /* Modal Rx form */
        var modalForm    = document.getElementById('rmhModalRxForm');
        var modalFile    = document.getElementById('modalRxFile');
        var modalCam     = document.getElementById('modalRxCamera');
        var modalStatus  = document.getElementById('modalRxFileStatus');
        var modalFb      = document.getElementById('modalRxFeedback');

        function handleModalFile(src, other) {
            if (!src || !src.files || !src.files.length) { return; }
            var file = src.files[0];
            if (file.size > 10 * 1024 * 1024) {
                if (modalStatus) {
                    modalStatus.textContent = 'File exceeds 10 MB.';
                    modalStatus.style.color = '#dc2626';
                }
                src.value = '';
                return;
            }
            if (other) { other.value = ''; }
            if (modalStatus) {
                modalStatus.textContent = 'Selected: ' + file.name;
                modalStatus.style.color = '#007a48';
            }
        }

        if (modalFile) { modalFile.addEventListener('change', function () { handleModalFile(modalFile, modalCam); }); }
        if (modalCam)  { modalCam.addEventListener('change',  function () { handleModalFile(modalCam,  modalFile); }); }

        if (modalForm) {
            modalForm.addEventListener('submit', function (ev) {
                ev.preventDefault();
                if (modalFb) {
                    modalFb.style.display = 'block';
                    modalFb.textContent   = 'Prescription received. Our pharmacist will contact you within 1 hour during business hours.';
                    modalFb.style.color   = '#007a48';
                }
                var submitBtn = modalForm.querySelector('#modalRxSubmitBtn');
                if (submitBtn) {
                    submitBtn.disabled     = true;
                    submitBtn.textContent  = 'Submitted ✓';
                }
            });
        }
    }

    /* ------------------------------------------------------------------
       6. INIT — run after DOM is ready
       ------------------------------------------------------------------ */
    function init() {
        initCart();
        initMobileNav();
        fixFloatingCartPosition();
        initPrescriptionUpload();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /* Also run fixFloatingCartPosition once page is fully loaded
       (plugin may re-set inline style on load event) */
    window.addEventListener('load', fixFloatingCartPosition);

})();
