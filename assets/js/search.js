(async () => {
    // ⏳ 等待 Fuse.js 库加载（最多等待 5 秒）
    let retries = 0;
    while (typeof Fuse === 'undefined' && retries < 50) {
        await new Promise(resolve => setTimeout(resolve, 100));
        retries++;
    }

    if (typeof Fuse === 'undefined') {
        console.error('❌ Fuse.js failed to load');
        return;
    }

    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');

    if (!searchInput || !searchResults) {
        console.error('❌ 找不到搜索元素');
        return;
    }

    // 搜索核心状态
    let fuse = null;
    let indexReady = false;
    let loadPromise = null;
    let lastQuery = '';
    let searchTimeout = null;
    let readyCallId = 0;

    // 1️⃣ 按需加载搜索索引（首次 focus 或 input 时触发）
    function loadIndex() {
        if (loadPromise) return loadPromise;

        loadPromise = fetch('/search-index.json')
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then(data => {
                fuse = new Fuse(data.posts, {
                    keys: [
                        { name: 'title', weight: 10 },
                        { name: 'headings', weight: 5 },
                        { name: 'tags', weight: 7 },
                        { name: 'content', weight: 1 },
                    ],
                    threshold: 0.3,
                    minMatchCharLength: 2,
                    ignoreLocation: true,
                    useExtendedSearch: true,
                });
                indexReady = true;
                console.log('✓ 搜索索引加载成功（文章数：' + data.posts.length + '）', {
                    版本: data.version,
                    构建时间: data.buildTime
                });
            })
            .catch(error => {
                console.error('❌ 加载搜索索引失败:', error.message);
                console.warn('💡 提示: 运行 php main.php build 来生成 search-index.json');
                loadPromise = null; // 允许下次交互重试
            });

        return loadPromise;
    }

    // 2️⃣ 确保索引就绪后执行回调（带竞态保护）
    async function whenReady(callback) {
        if (indexReady) {
            callback();
            return;
        }
        const callId = ++readyCallId;
        await loadIndex();
        if (indexReady && callId === readyCallId) {
            callback();
        }
    }

    // 3️⃣ 监听输入（立即注册，不等待索引加载）
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        lastQuery = query;

        if (!query) {
            searchResults.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            whenReady(() => performSearch(query));
        }, 300);
    });

    // 4️⃣ 焦点恢复 - 重新获得焦点时显示之前的搜索结果
    searchInput.addEventListener('focus', () => {
        if (lastQuery) {
            whenReady(() => performSearch(lastQuery));
        }
    });

    // 5️⃣ 执行搜索
    function performSearch(query) {
        try {
            const results = fuse.search(query).slice(0, 10);
            const ul = searchResults.querySelector('ul');

            if (results.length === 0) {
                ul.innerHTML = '<li class="no-result">未找到相关内容</li>';
            } else {
                ul.innerHTML = results.map(({ item }) => {
                    return `
                        <li class="search-result-item">
                            <a href="${escapeHtml(item.url)}">
                                <strong>${escapeHtml(item.title)}</strong>
                                ${item.tags.length ? `
                                    <div class="tags">
                                        ${item.tags.slice(0, 3).map(tag =>
                                            `<span class="tag">#${escapeHtml(tag)}</span>`
                                        ).join('')}
                                    </div>
                                ` : ''}
                            </a>
                        </li>
                    `;
                }).join('');
            }

            searchResults.style.display = 'block';
        } catch (error) {
            console.error('❌ 搜索错误:', error);
        }
    }

    // 6️⃣ 防 XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // 7️⃣ 点击外部关闭
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-box')) {
            searchResults.style.display = 'none';
        }
    });

    // 8️⃣ ESC 关闭
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            searchResults.style.display = 'none';
            searchInput.blur();
        }
    });

    // 9️⃣ 全局快捷键 - 按 "/" 聚焦搜索框
    document.addEventListener('keydown', (e) => {
        const activeElement = document.activeElement;
        const isInputElement = activeElement instanceof HTMLInputElement || activeElement instanceof HTMLTextAreaElement;

        if (e.key === '/' && !isInputElement) {
            e.preventDefault();
            searchInput.focus();
        }
    });
})();
