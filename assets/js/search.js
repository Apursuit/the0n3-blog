(async () => {
    // 等待 Fuse.js 可用。搜索组件依赖它做前端模糊匹配。
    let retries = 0;
    while (typeof Fuse === 'undefined' && retries < 50) {
        await new Promise((resolve) => setTimeout(resolve, 100));
        retries++;
    }

    if (typeof Fuse === 'undefined') {
        console.error('Fuse.js failed to load');
        return;
    }

    const modal = document.getElementById('searchModal');
    const trigger = document.getElementById('searchTrigger');
    const closeButton = document.getElementById('searchClose');
    const input = document.getElementById('searchInput');
    const results = document.getElementById('searchResults');
    const state = document.getElementById('searchState');

    if (!modal || !trigger || !closeButton || !input || !results || !state) {
        console.error('Search modal elements not found');
        return;
    }

    let fuse = null;
    let loadPromise = null;
    let indexReady = false;
    let isOpen = false;
    let query = '';
    let activeIndex = -1;
    let currentResults = [];
    let searchTimeout = null;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function setState(message, tone = 'default') {
        state.textContent = message;
        state.dataset.tone = tone;
    }

    function openSearch() {
        if (isOpen) {
            input.focus();
            return;
        }

        isOpen = true;
        modal.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        document.body.classList.add('search-open');
        window.setTimeout(() => input.focus(), 0);

        if (!query) {
            setState('输入关键词开始搜索');
        }
    }

    function closeSearch() {
        if (!isOpen) {
            return;
        }

        isOpen = false;
        modal.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('search-open');
        activeIndex = -1;
        results.innerHTML = '';
        setState(query ? '搜索已关闭，再次打开可继续输入' : '输入关键词开始搜索');
        trigger.focus();
    }

    function renderResults(items) {
        results.innerHTML = '';

        if (items.length === 0) {
            setState(`没有找到与 “${query}” 相关的结果`, 'empty');
            return;
        }

        const fragment = document.createDocumentFragment();

        items.forEach((item, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'search-result-item';
            button.setAttribute('role', 'option');
            button.dataset.index = String(index);
            button.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');

            if (index === activeIndex) {
                button.classList.add('is-active');
            }

            const safeTitle = escapeHtml(item.title || '');
            const safeDate = escapeHtml(item.date || '');
            const rawUrl = item.url || '/';
            const safeUrl = escapeHtml(rawUrl);
            const tags = Array.isArray(item.tags) ? item.tags.slice(0, 3) : [];

            button.innerHTML = `
                <span class="search-result-item__main">
                    <span class="search-result-item__title">${safeTitle}</span>
                    <span class="search-result-item__meta">
                        ${safeDate ? `<span class="search-result-item__date">${safeDate}</span>` : ''}
                        ${tags.length ? `
                            <span class="search-result-item__tags">
                                ${tags.map((tag) => `<span class="search-tag">#${escapeHtml(tag)}</span>`).join('')}
                            </span>
                        ` : ''}
                    </span>
                </span>
                <span class="search-result-item__arrow" aria-hidden="true">↗</span>
            `;

            button.addEventListener('click', () => {
                window.location.href = rawUrl;
            });

            button.addEventListener('mousemove', () => {
                setActiveIndex(index);
            });

            fragment.appendChild(button);
        });

        results.appendChild(fragment);
        setState(`找到 ${items.length} 条结果`, 'success');
    }

    function setActiveIndex(nextIndex) {
        if (nextIndex < 0 || nextIndex >= currentResults.length) {
            return;
        }

        activeIndex = nextIndex;

        const items = results.querySelectorAll('.search-result-item');
        items.forEach((item, index) => {
            const isActive = index === activeIndex;
            item.classList.toggle('is-active', isActive);
            item.setAttribute('aria-selected', isActive ? 'true' : 'false');

            if (isActive) {
                item.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    function performSearch() {
        if (!fuse) {
            return;
        }

        if (!query) {
            currentResults = [];
            activeIndex = -1;
            results.innerHTML = '';
            setState('输入关键词开始搜索');
            return;
        }

        try {
            currentResults = fuse.search(query).slice(0, 8).map((entry) => entry.item);
            activeIndex = currentResults.length > 0 ? 0 : -1;
            renderResults(currentResults);
        } catch (error) {
            console.error('Search failed:', error);
            setState('搜索时发生错误，请稍后重试', 'error');
        }
    }

    function loadIndex() {
        if (loadPromise) {
            return loadPromise;
        }

        setState('正在加载搜索索引...', 'loading');

        loadPromise = fetch('/search-index.json')
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then((data) => {
                fuse = new Fuse(data.posts, {
                    keys: [
                        { name: 'title', weight: 10 },
                        { name: 'headings', weight: 6 },
                        { name: 'tags', weight: 7 },
                        { name: 'content', weight: 1 },
                    ],
                    threshold: 0.32,
                    minMatchCharLength: 2,
                    ignoreLocation: true,
                    useExtendedSearch: true,
                });

                indexReady = true;
                setState('输入关键词开始搜索');
            })
            .catch((error) => {
                console.error('Failed to load search index:', error.message);
                setState('搜索索引加载失败，请先执行构建', 'error');
                loadPromise = null;
            });

        return loadPromise;
    }

    async function ensureIndexReady() {
        if (indexReady) {
            return true;
        }

        await loadIndex();
        return indexReady;
    }

    trigger.addEventListener('click', () => {
        openSearch();
        void ensureIndexReady();
    });

    closeButton.addEventListener('click', closeSearch);

    modal.addEventListener('click', (event) => {
        const target = event.target;
        if (target instanceof HTMLElement && target.hasAttribute('data-search-close')) {
            closeSearch();
        }
    });

    input.addEventListener('input', async (event) => {
        query = event.target.value.trim();
        clearTimeout(searchTimeout);

        if (!(await ensureIndexReady())) {
            return;
        }

        searchTimeout = window.setTimeout(() => {
            performSearch();
        }, 120);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (currentResults.length > 0) {
                const next = activeIndex < currentResults.length - 1 ? activeIndex + 1 : 0;
                setActiveIndex(next);
            }
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (currentResults.length > 0) {
                const next = activeIndex > 0 ? activeIndex - 1 : currentResults.length - 1;
                setActiveIndex(next);
            }
            return;
        }

        if (event.key === 'Enter') {
            if (activeIndex >= 0 && currentResults[activeIndex]) {
                event.preventDefault();
                window.location.href = currentResults[activeIndex].url;
            }
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeSearch();
        }
    });

    document.addEventListener('keydown', (event) => {
        const activeElement = document.activeElement;
        const isTypingTarget = activeElement instanceof HTMLInputElement || activeElement instanceof HTMLTextAreaElement;

        if (event.key === '/' && !isTypingTarget && !isOpen) {
            event.preventDefault();
            openSearch();
            void ensureIndexReady();
            return;
        }

        if (event.key === 'Escape' && isOpen) {
            event.preventDefault();
            closeSearch();
        }
    });
})();
