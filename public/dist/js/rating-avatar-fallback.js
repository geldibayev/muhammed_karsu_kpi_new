(function () {
    'use strict';

    function showFallback(image) {
        const fallback = image.nextElementSibling;

        image.classList.add('d-none');

        if (!fallback || !fallback.hasAttribute('data-rating-avatar-fallback')) {
            return;
        }

        fallback.classList.remove('d-none');
        fallback.classList.add('d-inline-flex');
    }

    document.addEventListener('error', function (event) {
        const image = event.target;

        if (!(image instanceof HTMLImageElement)
            || !image.hasAttribute('data-rating-avatar-image')) {
            return;
        }

        showFallback(image);
    }, true);

    document.querySelectorAll('[data-rating-avatar-image]').forEach(function (image) {
        if (image.complete && image.naturalWidth === 0) {
            showFallback(image);
        }
    });
}());
