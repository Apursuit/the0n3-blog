<?php

namespace App;

class Generator
{
    private $config;
    private $renderer;
    private $validator;

    private $posts = [];
    private $tags = [];
    private $categories = [];
    private $archives = [];

    // 初始化生成器与校验器，收集 public 路径用于冲突检查。
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->renderer = new Renderer($config['templates_path']);

        $publicPaths = [];
        if (is_dir($this->config['public_path'])) {
            $publicDirIterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->config['public_path'], \RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($publicDirIterator as $file) {
                if ($file->isFile()) {
                    $publicPaths[] = '/' . str_replace('\\', '/', $publicDirIterator->getSubPathName());
                }
            }
        }

        if (is_dir($this->config['images_path'])) {
            $imagesDirIterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->config['images_path'], \RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($imagesDirIterator as $file) {
                if ($file->isFile()) {
                    $publicPaths[] = '/images/' . str_replace('\\', '/', $imagesDirIterator->getSubPathName());
                }
            }
        }

        $systemPages = ['/tags', '/categories', '/archives'];
        $this->validator = new Validator($publicPaths, $systemPages);
    }

    // 执行完整构建流程。
    public function run(): void
    {
        $startTime = microtime(true);

        Utils::log("开始构建...");

        $this->timeStep('1. 清空 dist/', function () {
            $this->cleanup();
        });
        $this->timeStep('2. 资源映射', function () {
            $this->copyStaticAssets();
        });
        $this->timeStep('3. 解析 Markdown', function () {
            $this->loadAndProcessPosts();
        });
        $this->timeStep('4. 校验数据', function () {
            $this->validatePosts();
        });
        $this->timeStep('5. 构建数据索引', function () {
            $this->prepareData();
        });
        $this->timeStep('6. 渲染页面', function () {
            $this->generatePages();
        });
        $this->timeStep('7. 生成搜索索引', function () {
            $this->buildSearchIndex();
        });

        $endTime = microtime(true);
        $buildTime = round($endTime - $startTime, 2);

        Utils::log("构建完成，用时 {$buildTime} 秒。");
        Utils::log(" - 生成文章数：" . count($this->posts));
        Utils::log(" - 标签数：" . count($this->tags));
        Utils::log(" - 分类数：" . count($this->categories));
    }

    // 输出阶段耗时，便于定位构建瓶颈。
    private function timeStep(string $label, callable $task): void
    {
        $start = microtime(true);
        $task();
        $elapsed = round(microtime(true) - $start, 3);
        Utils::log("{$label} 用时 {$elapsed} 秒。");
    }

    // 清理 dist 目录并重新创建。
    private function cleanup(): void
    {
        Utils::log("清理 dist 目录...");
        if (is_dir($this->config['dist_path'])) {
            Utils::deleteDirectory($this->config['dist_path']);
        }
        mkdir($this->config['dist_path'], 0755, true);
    }

    // 复制 assets 与 public 资源到 dist。
    private function copyStaticAssets(): void
    {
        Utils::log("复制静态资源...");
        if (is_dir($this->config['assets_path'])) {
            Utils::copyDirectory($this->config['assets_path'], $this->config['dist_path'] . '/assets');
        }
        if (is_dir($this->config['public_path'])) {
            Utils::copyDirectory($this->config['public_path'], $this->config['dist_path']);
        }
        if (is_dir($this->config['images_path'])) {
            Utils::copyDirectory($this->config['images_path'], $this->config['dist_path'] . '/images');
        }
    }

    // 读取文章并解析 Front Matter，过滤草稿。
    private function loadAndProcessPosts(): void
    {
        Utils::log("加载并处理文章...");
        $loader = new Loader($this->config['posts_path']);
        $rawPosts = $loader->loadPosts();
        $processedPosts = [];

        $draftCount = 0;
        foreach ($rawPosts as $rawPost) {
            try {
                $parsed = FrontMatter::parse($rawPost['rawContent']);
                if ($parsed['frontMatter']['draft'] === true) {
                    $draftCount++;
                    continue;
                }
                $processedPosts[] = array_merge(
                    $rawPost,
                    $parsed
                );
            } catch (\Exception $e) {
                Utils::log("Error parsing '{$rawPost['sourcePath']}': " . $e->getMessage(), 'error');
                exit(1);
            }
        }
        if ($draftCount > 0) {
            Utils::log("跳过草稿：{$draftCount} 篇。");
        }
        $this->posts = $processedPosts;
    }

    // 校验文章数据完整性与链接合法性。
    private function validatePosts(): void
    {
        Utils::log("校验文章数据...");
        try {
            $this->validator->validate($this->posts);
        } catch (\Exception $e) {
            Utils::log($e->getMessage(), 'error');
            exit(1);
        }
    }

    // 生成 HTML、排序、聚合标签/分类/归档数据。
    private function prepareData(): void
    {
        Utils::log("准备数据...");

        // Convert markdown to HTML
        foreach ($this->posts as &$post) {
            $post['html'] = Markdown::toHtml($post['content']);
        }
        unset($post);

        // Sort posts by date desc using normalized timestamps
        usort($this->posts, function ($a, $b) {
            $aTs = $this->normalizePostDateToTimestamp($a);
            $bTs = $this->normalizePostDateToTimestamp($b);

            return $bTs <=> $aTs;
        });

        // Aggregate tags, categories, and archives
        foreach ($this->posts as $post) {
            // Tags
            foreach ($post['frontMatter']['tags'] as $tagName) {
                if (!isset($this->tags[$tagName])) {
                    $this->tags[$tagName] = [];
                }
                $this->tags[$tagName][] = $post;
            }

            // Categories
            foreach ($post['frontMatter']['categories'] as $categoryName) {
                if (!isset($this->categories[$categoryName])) {
                    $this->categories[$categoryName] = [];
                }
                $this->categories[$categoryName][] = $post;
            }

            // Archives
            $timestamp = $this->normalizePostDateToTimestamp($post);
            $year = date('Y', $timestamp);
            if (!isset($this->archives[$year])) {
                $this->archives[$year] = [];
            }
            $this->archives[$year][] = $post;
        }
        ksort($this->archives, SORT_NUMERIC);
        array_walk($this->archives, function (&$posts) {
            usort($posts, function ($a, $b) {
                $aTs = $this->normalizePostDateToTimestamp($a);
                $bTs = $this->normalizePostDateToTimestamp($b);

                return $bTs <=> $aTs;
            });
        });
    }

    // 统一将各种日期格式转为时间戳。
    private function normalizePostDateToTimestamp(array $post): int
    {
        $value = $post['frontMatter']['date'] ?? null;

        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                throw new \Exception("Post '{$post['sourcePath']}' has empty date.");
            }

            if (ctype_digit($trimmed)) {
                return (int) $trimmed;
            }

            $timestamp = strtotime($trimmed);
            if ($timestamp !== false) {
                return $timestamp;
            }

            throw new \Exception("Post '{$post['sourcePath']}' has invalid date '{$trimmed}'. Expected a parseable date string (e.g. YYYY-MM-DD or YYYY-MM-DD HH:MM:SS).");
        }

        $type = gettype($value);
        throw new \Exception("Post '{$post['sourcePath']}' has invalid date type '{$type}'.");
    }

    // 渲染页面并写入到 dist。
    private function generatePages(): void
    {
        Utils::log("生成页面...");
        $siteData = $this->config['site'] ?? [];
        $siteUrl = $siteData['url'] ?? '';
        $siteUrl = rtrim($siteUrl, '/');
        if ($siteUrl === '') {
            Utils::log('未配置站点 url，跳过 sitemap 生成。', 'warning');
        }
        $buildTimestamp = date('c');
        $sitemapEntries = [];

        if ($siteUrl !== '') {
            $sitemapEntries['/'] = $buildTimestamp;
        }

        // Post pages
        foreach ($this->posts as $post) {
            $path = $this->config['dist_path'] . $post['frontMatter']['permalink'];
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
            $html = $this->renderer->render('post', ['post' => $post, 'site' => $siteData]);
            file_put_contents($path . 'index.html', $html);
        }

        // Index pages (pagination)
        $pageSize = 15;
        $pinnedPosts = array_filter($this->posts, function ($post) {
            return ($post['frontMatter']['pin'] ?? 0) > 0;
        });
        usort($pinnedPosts, function ($a, $b) {
            $aPin = $a['frontMatter']['pin'] ?? 0;
            $bPin = $b['frontMatter']['pin'] ?? 0;
            if ($aPin !== $bPin) {
                return $aPin <=> $bPin;
            }
            $aTs = $this->normalizePostDateToTimestamp($a);
            $bTs = $this->normalizePostDateToTimestamp($b);
            return $bTs <=> $aTs;
        });
        $pinnedPosts = array_slice($pinnedPosts, 0, 3);
        $pinnedPermalinks = array_flip(array_map(function ($post) {
            return $post['frontMatter']['permalink'] ?? '';
        }, $pinnedPosts));
        $remainingPosts = array_values(array_filter($this->posts, function ($post) use ($pinnedPermalinks) {
            $permalink = $post['frontMatter']['permalink'] ?? '';
            return $permalink === '' || !isset($pinnedPermalinks[$permalink]);
        }));

        $pinnedCount = count($pinnedPosts);
        $totalItems = count($remainingPosts) + $pinnedCount;
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $firstPageRemainingCount = max(0, $pageSize - $pinnedCount);
        for ($page = 1; $page <= $totalPages; $page++) {
            if ($page === 1) {
                $pagePosts = array_merge(
                    $pinnedPosts,
                    array_slice($remainingPosts, 0, $firstPageRemainingCount)
                );
            } else {
                $offset = $firstPageRemainingCount + ($page - 2) * $pageSize;
                $pagePosts = array_slice($remainingPosts, $offset, $pageSize);
            }
            $pagination = [
                'current' => $page,
                'total' => $totalPages,
            ];
            $indexHtml = $this->renderer->render('index', [
                'posts' => $pagePosts,
                'pagination' => $pagination,
                'site' => $siteData,
            ]);

            if ($page === 1) {
                file_put_contents($this->config['dist_path'] . '/index.html', $indexHtml);
                if ($siteUrl !== '') {
                    $sitemapEntries['/'] = $buildTimestamp;
                }
                continue;
            }

            $pagePath = $this->config['dist_path'] . '/page/' . $page . '/';
            if (!is_dir($pagePath)) {
                mkdir($pagePath, 0755, true);
            }
            file_put_contents($pagePath . 'index.html', $indexHtml);

            if ($siteUrl !== '') {
                $sitemapEntries['/page/' . $page . '/'] = $buildTimestamp;
            }
        }

        // Tags pages
        $tagsPath = $this->config['dist_path'] . '/tags/';
        mkdir($tagsPath, 0755, true);
        $tagsHtml = $this->renderer->render('tags', ['tags' => $this->tags, 'site' => $siteData]);
        file_put_contents($tagsPath . 'index.html', $tagsHtml);

        if ($siteUrl !== '') {
            $sitemapEntries['/tags/'] = $buildTimestamp;
        }

        // Categories pages
        $categoriesPath = $this->config['dist_path'] . '/categories/';
        mkdir($categoriesPath, 0755, true);
        $categoriesHtml = $this->renderer->render('categories', ['categories' => $this->categories, 'site' => $siteData]);
        file_put_contents($categoriesPath . 'index.html', $categoriesHtml);

        if ($siteUrl !== '') {
            $sitemapEntries['/categories/'] = $buildTimestamp;
        }

        // Archive page
        $archivePath = $this->config['dist_path'] . '/archives/';
        mkdir($archivePath, 0755, true);
        $archiveHtml = $this->renderer->render('archive', ['archives' => $this->archives, 'site' => $siteData]);
        file_put_contents($archivePath . 'index.html', $archiveHtml);

        if ($siteUrl !== '') {
            $sitemapEntries['/archives/'] = $buildTimestamp;
        }

        // 404 page
        $notFoundHtml = $this->renderer->render('404', ['site' => $siteData]);
        file_put_contents($this->config['dist_path'] . '/404.html', $notFoundHtml);

        if ($siteUrl !== '') {
            foreach ($this->posts as $post) {
                $permalink = $post['frontMatter']['permalink'] ?? '';
                if ($permalink === '') {
                    continue;
                }
                $lastmod = Utils::formatDate($post['frontMatter']['date'] ?? '', 'c');
                $sitemapEntries[$permalink] = $lastmod !== '' ? $lastmod : $buildTimestamp;
            }

            $sitemapXml = $this->buildSitemapXml($siteUrl, $sitemapEntries);
            file_put_contents($this->config['dist_path'] . '/sitemap.xml', $sitemapXml);
        }
    }

    private function buildSitemapXml(string $siteUrl, array $entries): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($entries as $path => $lastmod) {
            $loc = rtrim($siteUrl, '/') . $path;
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') . "</loc>\n";
            if ($lastmod !== '') {
                $xml .= '    <lastmod>' . htmlspecialchars($lastmod, ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
            }
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";
        return $xml;
    }

    // 生成搜索索引 JSON 文件
    private function buildSearchIndex(): void
    {
        Utils::log("生成搜索索引...");
        
        $indexData = [
            'version' => '2.0',
            'buildTime' => date('c'),
            'posts' => [],
        ];
        
        foreach ($this->posts as $post) {
            // 从 HTML 提取 h1-h3 标题
            $headings = $this->extractHeadings($post['html']);
            
            // 获取纯文本内容（去除 HTML 标签）
            $plainText = strip_tags($post['html']);
            $plainText = preg_replace('/\s+/', ' ', $plainText); // 规范化空白
            // 限制大小以减少 JSON 文件体积
            $plainText = mb_substr($plainText, 0, 5000, 'UTF-8');
            
            $indexData['posts'][] = [
                'id' => md5($post['frontMatter']['permalink']),
                'title' => $post['frontMatter']['title'],
                'url' => $post['frontMatter']['permalink'],
                'date' => $post['frontMatter']['date'],
                'content' => $plainText,
                'tags' => array_values($post['frontMatter']['tags']),
                'headings' => $headings,
            ];
        }
        
        // 写入 JSON 文件
        $indexFile = $this->config['dist_path'] . '/search-index.json';
        $json = json_encode($indexData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($indexFile, $json);
        
        $fileSize = round(filesize($indexFile) / 1024, 2);
        Utils::log("搜索索引已生成：{$fileSize}KB");
    }
    
    // 从 HTML 中提取 h1-h3 标题
    private function extractHeadings(string $html): array
    {
        $headings = [];
        $pattern = '/<h[1-3][^>]*>(.*?)<\/h[1-3]>/i';
        
        if (preg_match_all($pattern, $html, $matches)) {
            foreach ($matches[1] as $heading) {
                $text = strip_tags($heading);
                $text = trim($text);
                
                if (!empty($text)) {
                    $headings[] = $text;
                }
            }
        }
        
        return array_unique($headings); // 去重
    }
}
