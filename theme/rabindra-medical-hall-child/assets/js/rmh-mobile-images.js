(function () {
    'use strict';

    /*
     * RMH image reliability layer.
     *
     * Does not replace valid WooCommerce image URLs.
     * Does not invent fallback medicine images.
     * Only prevents failed image requests from leaving broken
     * layout and retries using the existing src when appropriate.
     */

    function markImageError(img) {
        if (!img || img.dataset.rmhImageErrorHandled === '1') {
            return;
        }

        img.dataset.rmhImageErrorHandled = '1';
        img.classList.add('rmh-image-error');

        /*
         * Do not replace medicine/product images with fake images.
         * Preserve the original source for debugging.
         */
        img.setAttribute('aria-broken', 'true');
    }

    function prepareImages() {
        var lazyImgs = [];

        document.querySelectorAll('img').forEach(function (img) {

            if (!img.hasAttribute('decoding')) {
                img.setAttribute('decoding', 'async');
            }

            img.addEventListener('error', function () {
                markImageError(img);
            }, { once: true });

            if (img.loading === 'lazy' && !img.complete) {
                lazyImgs.push(img);
            }
        });

        // Ensure below-the-fold lazy images load progressively without stalling
        if (lazyImgs.length > 0) {
            lazyImgs.forEach(function (img) {
                img.loading = 'eager';
                img.addEventListener('load', function () {
                    img.loading = 'lazy';
                }, { once: true });
            });

            setTimeout(function () {
                lazyImgs.forEach(function (img) {
                    img.loading = 'lazy';
                });
            }, 1200);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', prepareImages, {
            once: true
        });
    } else {
        prepareImages();
    }

    /*
     * WooCommerce can replace product images during AJAX updates.
     */
    if (window.MutationObserver) {
        var observer = new MutationObserver(function () {
            prepareImages();
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true
        });
    }

})();
