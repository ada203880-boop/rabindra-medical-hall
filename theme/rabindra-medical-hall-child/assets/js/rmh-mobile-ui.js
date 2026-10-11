/**
 * Rabindra Medical Hall — Mobile UI Interactions & Cart Integration
 */
(function () {
    'use strict';

    function initMobileNav() {
        // Wire bottom nav Cart tab to trigger ModernCart slide-out drawer when available
        var navCart = document.querySelector('.rmh-mobile-nav__cart');
        if (navCart) {
            navCart.addEventListener('click', function (e) {
                var drawerBtn = document.querySelector('.moderncart-floating-cart-button, #moderncart-floating-cart button, .moderncart-toggle-slide-out button');
                if (drawerBtn) {
                    e.preventDefault();
                    drawerBtn.click();
                }
            });
        }

        // Wire bottom nav Search tab to focus header search
        var navSearch = document.getElementById('rmhMobileNavSearch');
        if (navSearch) {
            navSearch.addEventListener('click', function (e) {
                var searchField = document.querySelector('.rmh-site-search input[type="search"], #rmh-site-search-field');
                if (searchField) {
                    e.preventDefault();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    setTimeout(function () {
                        searchField.focus();
                    }, 300);
                }
            });
        }
    }

    function initPrescriptionUpload() {
        var form = document.getElementById('rmhPrescriptionForm');
        var fileInp = document.getElementById('prescriptionFile');
        var camInp = document.getElementById('rmhPrescriptionCamera');
        var status = document.getElementById('rmh-prescription-file-status');

        if (!form || !fileInp || !camInp || !status) {
            return;
        }

        function handleFileSelection(sourceInput, otherInput) {
            if (!sourceInput.files || !sourceInput.files.length) {
                return;
            }

            var file = sourceInput.files[0];
            var maxSize = 10 * 1024 * 1024; // 10 MB

            if (file.size > maxSize) {
                status.textContent = 'File exceeds maximum size of 10 MB. Please choose a smaller file.';
                status.style.color = '#dc2626';
                sourceInput.value = '';
                return;
            }

            // Clear other input to prevent confusion
            if (otherInput) {
                otherInput.value = '';
            }

            status.textContent = 'Selected: ' + file.name;
            status.style.color = '#007a48';
        }

        fileInp.addEventListener('change', function () {
            handleFileSelection(fileInp, camInp);
        });

        camInp.addEventListener('change', function () {
            handleFileSelection(camInp, fileInp);
        });

        // Prevent duplicate submissions
        form.addEventListener('submit', function () {
            var submitBtn = form.querySelector('button[type="submit"]');
            if (form.checkValidity() && (fileInp.files.length || camInp.files.length)) {
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Submitting...';
                    submitBtn.setAttribute('aria-busy', 'true');
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initMobileNav();
            initPrescriptionUpload();
        });
    } else {
        initMobileNav();
        initPrescriptionUpload();
    }
})();
