document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('main.markdown-body');
    if (!container) return;

    const images = container.querySelectorAll('img');
    if (!images.length) return;

    let overlay = null;
    let lightboxImg = null;
    let lightboxSpring = null;
    let lastTransform = { dx: 0, dy: 0, scale: 1 };

    function prefersReducedMotion() {
        return typeof Spring !== 'undefined' && Spring.prefersReducedMotion();
    }

    function ensureOverlay() {
        if (overlay) return overlay;

        overlay = document.createElement('div');
        overlay.className = 'image-lightbox';
        overlay.innerHTML = '<img class="image-lightbox__img" alt="" />';
        document.body.appendChild(overlay);
        lightboxImg = overlay.querySelector('.image-lightbox__img');

        overlay.addEventListener('click', closeLightbox);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeLightbox();
            }
        });

        return overlay;
    }

    // 计算从缩略图到全尺寸的映射：dx/dy 为位移、scale 为缩放（Apple Design §7）
    function computeTarget(img, box) {
        const rect = img.getBoundingClientRect();
        const natW = box.naturalWidth || 0;
        const natH = box.naturalHeight || 0;
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        let finalW;
        if (natW && natH) {
            const k = Math.min((0.92 * vw) / natW, (0.92 * vh) / natH);
            finalW = natW * k;
        } else {
            finalW = box.offsetWidth || rect.width;
        }

        return {
            dx: (rect.left + rect.width / 2) - vw / 2,
            dy: (rect.top + rect.height / 2) - vh / 2,
            scale: finalW > 0 ? rect.width / finalW : 0.5,
        };
    }

    function render(box, t, v) {
        box.style.transform =
            'translate(' + (t.dx * (1 - v)).toFixed(2) + 'px, ' + (t.dy * (1 - v)).toFixed(2) + 'px) ' +
            'scale(' + (t.scale + (1 - t.scale) * v).toFixed(4) + ')';
        box.style.opacity = String(v);
    }

    function openLightbox(img) {
        const box = ensureOverlay();
        box.classList.add('is-open');
        document.body.classList.add('is-lightbox-open');

        lightboxImg.src = img.currentSrc || img.src;
        lightboxImg.alt = img.alt || '';

        const finish = () => {
            lastTransform = computeTarget(img, lightboxImg);
            lightboxImg.style.transformOrigin = 'center center';

            if (prefersReducedMotion()) {
                if (lightboxSpring) {
                    lightboxSpring.stop();
                    lightboxSpring = null;
                }
                lightboxImg.style.transform = '';
                lightboxImg.style.opacity = '1';
                return;
            }

            render(lightboxImg, lastTransform, 0);
            lightboxImg.getBoundingClientRect();

            if (lightboxSpring) lightboxSpring.stop();
            lightboxSpring = Spring.createSpring({
                from: 0,
                to: 1,
                damping: 0.85,
                response: 0.35,
                onUpdate: (v) => render(lightboxImg, lastTransform, v),
                onComplete: () => { lightboxSpring = null; },
            });
            lightboxSpring.start();
        };

        if (lightboxImg.complete && lightboxImg.naturalWidth) {
            finish();
        } else {
            lightboxImg.onload = finish;
            lightboxImg.onerror = finish;
        }
    }

    function closeLightbox() {
        if (!overlay || !overlay.classList.contains('is-open')) return;

        if (prefersReducedMotion()) {
            if (lightboxSpring) {
                lightboxSpring.stop();
                lightboxSpring = null;
            }
            overlay.classList.remove('is-open');
            document.body.classList.remove('is-lightbox-open');
            return;
        }

        if (lightboxSpring) lightboxSpring.stop();
        lightboxSpring = Spring.createSpring({
            from: 1,
            to: 0,
            damping: 1.0,
            response: 0.3,
            onUpdate: (v) => render(lightboxImg, lastTransform, v),
            onComplete: () => {
                lightboxSpring = null;
                overlay.classList.remove('is-open');
                document.body.classList.remove('is-lightbox-open');
            },
        });
        lightboxSpring.start();
    }

    images.forEach((img, index) => {
        if (index > 0 && !img.hasAttribute('loading')) {
            img.setAttribute('loading', 'lazy');
        }
        if (!img.hasAttribute('decoding')) {
            img.setAttribute('decoding', 'async');
        }

        if (img.dataset.noLightbox === 'true') return;

        const block = img.closest('p') || img.parentElement;
        if (block) {
            block.classList.add('image-block');
        }

        img.addEventListener('click', () => {
            openLightbox(img);
        });
    });
});
