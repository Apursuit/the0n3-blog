document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.tax-accordion');
    if (!root) return;

    const closeAll = () => {
        root.querySelectorAll('.tax-toggle').forEach((btn) => {
            btn.setAttribute('aria-expanded', 'false');
        });
        root.querySelectorAll('.tax-item').forEach((item) => {
            item.classList.remove('is-active');
        });
        root.querySelectorAll('.post-list').forEach((list) => {
            list.classList.remove('is-open');
            list.setAttribute('aria-hidden', 'true');
        });
    };

    root.addEventListener('click', (event) => {
        const button = event.target.closest('.tax-toggle');
        if (!button) return;

        const item = button.closest('.tax-item');
        const list = item ? item.querySelector('.post-list') : null;
        if (!item || !list) return;

        const isExpanded = button.getAttribute('aria-expanded') === 'true';
        closeAll();

        if (!isExpanded) {
            button.setAttribute('aria-expanded', 'true');
            item.classList.add('is-active');
            list.classList.add('is-open');
            list.setAttribute('aria-hidden', 'false');

            if (!list.dataset.rendered) {
                try {
                    const postsJson = item.getAttribute('data-posts') || '[]';
                    const posts = JSON.parse(postsJson);
                    const fragment = document.createDocumentFragment();

                    posts.forEach((post) => {
                        const li = document.createElement('li');
                        li.className = 'post-item';

                        const link = document.createElement('a');
                        link.href = post.permalink;
                        link.textContent = post.title;
                        link.target = '_blank';
                        link.rel = 'noopener';

                        const meta = document.createElement('span');
                        meta.className = 'meta';
                        meta.textContent = post.date || '';

                        li.appendChild(link);
                        li.appendChild(meta);
                        fragment.appendChild(li);
                    });

                    list.textContent = '';
                    list.appendChild(fragment);
                } catch (e) {
                    list.textContent = '';
                }

                list.dataset.rendered = 'true';
            }
        }
    });
});
