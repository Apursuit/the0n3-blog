document.addEventListener('DOMContentLoaded', () => {
    const backToTop = document.getElementById('backToTop');
    if (!backToTop) return;

    const isPostPage = !!document.querySelector('main.markdown-body article');
    if (!isPostPage) {
        backToTop.classList.remove('is-visible');
        return;
    }

    const tocContainer = document.querySelector('.post-toc .toc-list');
    const tocRoot = document.querySelector('.post-toc');
    const article = document.querySelector('article.post-content');
    if (tocContainer && tocRoot && article) {
        const headings = article.querySelectorAll('h2, h3');
        const used = new Map();

        const slugify = (text) => {
            return text
                .trim()
                .toLowerCase()
                .replace(/[^\w\u4e00-\u9fa5]+/g, '-')
                .replace(/^-+|-+$/g, '');
        };

        const items = [];
        headings.forEach((heading) => {
            if (!heading.id) {
                const baseText = heading.textContent || 'section';
                const base = slugify(baseText) || 'section';
                const count = (used.get(base) || 0) + 1;
                used.set(base, count);
                heading.id = count === 1 ? base : `${base}-${count}`;
            }

            const link = document.createElement('a');
            link.href = `#${heading.id}`;
            link.textContent = heading.textContent || '';
            link.className = heading.tagName.toLowerCase() === 'h3' ? 'toc-link toc-link-sub' : 'toc-link';
            items.push(link);
        });

        tocContainer.textContent = '';
        if (items.length === 0) {
            tocRoot.classList.add('is-empty');
        } else {
            items.forEach((link) => tocContainer.appendChild(link));
        }

        tocContainer.addEventListener('click', (event) => {
            const target = event.target;
            if (target && target.tagName === 'A' && target.hash) {
                const el = document.getElementById(target.hash.slice(1));
                if (el) {
                    event.preventDefault();
                    try {
                        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } catch (e) {
                        el.scrollIntoView(true);
                    }
                    history.pushState(null, '', target.hash);
                }
            }
        });
    }

    const threshold = 360;
    let ticking = false;
    let lastVisible = false;

    function update() {
        ticking = false;
        const shouldShow = window.scrollY > threshold;
        if (shouldShow !== lastVisible) {
            backToTop.classList.toggle('is-visible', shouldShow);
            lastVisible = shouldShow;
        }
    }

    window.addEventListener('scroll', () => {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(update);
    }, { passive: true });

    update();

    backToTop.addEventListener('click', () => {
        try {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (e) {
            window.scrollTo(0, 0);
        }
    });
});
