document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('header');
    if (!header) return;

    let lastY = window.scrollY;
    let ticking = false;

    function update() {
        const y = window.scrollY;
        const delta = y - lastY;

        if (y > 120) {
            if (delta > 4) {
                header.classList.add('nav-hidden');
            } else if (delta < -4) {
                header.classList.remove('nav-hidden');
            }
        } else {
            header.classList.remove('nav-hidden');
        }

        lastY = y;
        ticking = false;
    }

    window.addEventListener('scroll', () => {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(update);
    }, { passive: true });

    update();
});
