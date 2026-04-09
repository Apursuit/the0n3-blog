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
    
    console.log('✓ Fuse.js 加载成功');
    
    // 1️⃣ 加载搜索索引
    let indexData = null;
    try {
        const response = await fetch('/search-index.json');
        if (!response.ok) {
            console.warn(`⚠️ search-index.json not found (HTTP ${response.status})`);
            console.warn('💡 需要运行构建命令: php main.php build');
            return;
        }
        indexData = await response.json();
        console.log('✓ 搜索索引加载成功（文章数：' + indexData.posts.length + '）');
    } catch (error) {
        console.error('❌ 加载搜索索引失败:', error.message);
        console.warn('💡 提示: 运行 php main.php build 来生成 search-index.json');
        return;
    }
    
    // 2️⃣ 初始化 Fuse.js
    const fuse = new Fuse(indexData.posts, {
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
    
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    
    if (!searchInput || !searchResults) {
        console.error('❌ 找不到搜索元素');
        return;
    }
    
    // 💾 存储最后的搜索查询
    let lastQuery = '';
    
    // 3️⃣ 监听输入
    let searchTimeout = null;
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        lastQuery = query; // 保存查询
        
        if (!query) {
            searchResults.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });
    
    // 8️⃣ 焦点恢复 - 重新获得焦点时显示之前的搜索结果
    searchInput.addEventListener('focus', () => {
        if (lastQuery) {
            performSearch(lastQuery);
        }
    });
    
    // 4️⃣ 执行搜索
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
    
    // 5️⃣ 防 XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // 6️⃣ 点击外部关闭
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-box')) {
            searchResults.style.display = 'none';
        }
    });
    
    // 7️⃣ ESC 关闭
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            searchResults.style.display = 'none';
            searchInput.blur();
        }
    });
    
    // 9️⃣ 全局快捷键 - 按 "/" 聚焦搜索框
    document.addEventListener('keydown', (e) => {
        // 检查是否在输入框或文本区域中
        const activeElement = document.activeElement;
        const isInputElement = activeElement instanceof HTMLInputElement || activeElement instanceof HTMLTextAreaElement;
        
        if (e.key === '/' && !isInputElement) {
            e.preventDefault();
            searchInput.focus();
            console.log('⌨️ 快捷键触发: / 聚焦搜索框');
        }
    });
    
    console.log('✅ 搜索功能初始化完毕', {
        版本: indexData.version,
        文章数: indexData.posts.length,
        构建时间: indexData.buildTime
    });
})();