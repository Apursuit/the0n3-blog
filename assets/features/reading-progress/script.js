document.addEventListener('DOMContentLoaded', () => {
    const progressBar = document.querySelector('.reading-progress__bar');
    const article = document.querySelector('article.post-content');
    if (!progressBar || !article) return;

    const headings = Array.from(article.querySelectorAll('h2, h3'));
    const linkMap = new Map();

    function refreshLinkMap() {
        linkMap.clear();
        const links = document.querySelectorAll('.post-toc .toc-link');
        links.forEach((link) => {
            const hash = link.getAttribute('href') || '';
            if (hash.startsWith('#')) {
                linkMap.set(hash.slice(1), link);
            }
        });
    }

    refreshLinkMap();

    let ticking = false;
    let lastActiveId = null;

    function updateProgress() {
        const doc = document.documentElement;
        const max = doc.scrollHeight - window.innerHeight;
        const ratio = max > 0 ? window.scrollY / max : 0;
        progressBar.style.transform = `scaleX(${Math.min(Math.max(ratio, 0), 1)})`;
    }

    function updateActiveHeading() {
        if (!headings.length) return;

        let active = headings[0];
        const offset = 120;
        for (const heading of headings) {
            const rect = heading.getBoundingClientRect();
            if (rect.top <= offset) {
                active = heading;
            } else {
                break;
            }
        }

        const activeId = active.id || null;
        if (activeId === lastActiveId) return;

        if (!linkMap.size) {
            refreshLinkMap();
        }

        linkMap.forEach((link) => link.classList.remove('is-active'));
        if (activeId && linkMap.has(activeId)) {
            linkMap.get(activeId).classList.add('is-active');
        }
        lastActiveId = activeId;
    }

    function onScroll() {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(() => {
            updateProgress();
            updateActiveHeading();
            ticking = false;
        });
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);

    updateProgress();
    updateActiveHeading();
});
