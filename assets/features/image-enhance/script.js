document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('article.post-content');
    if (!container) return;

    const images = container.querySelectorAll('img');
    if (!images.length) return;

    function ensureOverlay() {
        let overlay = document.querySelector('.image-lightbox');
        if (overlay) return overlay;

        overlay = document.createElement('div');
        overlay.className = 'image-lightbox';
        overlay.innerHTML = '<img class="image-lightbox__img" alt="" />';
        document.body.appendChild(overlay);

        overlay.addEventListener('click', () => {
            overlay.classList.remove('is-open');
            document.body.classList.remove('is-lightbox-open');
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                overlay.classList.remove('is-open');
                document.body.classList.remove('is-lightbox-open');
            }
        });

        return overlay;
    }

    images.forEach((img) => {
        if (!img.hasAttribute('loading')) {
            img.setAttribute('loading', 'lazy');
        }
        if (!img.hasAttribute('decoding')) {
            img.setAttribute('decoding', 'async');
        }

        const block = img.closest('p') || img.parentElement;
        if (block) {
            block.classList.add('image-block');
        }

        if (img.dataset.noLightbox === 'true') return;

        img.addEventListener('click', () => {
            const overlay = ensureOverlay();
            const lightboxImg = overlay.querySelector('.image-lightbox__img');
            if (!lightboxImg) return;

            lightboxImg.src = img.currentSrc || img.src;
            lightboxImg.alt = img.alt || '';

            overlay.classList.add('is-open');
            document.body.classList.add('is-lightbox-open');
        });
    });
});
