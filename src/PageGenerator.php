<?php

namespace App;

class PageGenerator
{
    private Renderer $renderer;
    private array $config;

    public function __construct(Renderer $renderer, array $config)
    {
        $this->renderer = $renderer;
        $this->config = $config;
    }

    public function generate(array $posts, array $pages, array $tags, array $categories, array $archives): void
    {
        $siteData = $this->config['site'] ?? [];
        $siteUrl = rtrim($siteData['url'] ?? '', '/');
        if ($siteUrl === '') {
            Utils::log('站点 url 未配置，已跳过 sitemap 生成。', 'warning');
        }

        $navItems = $this->buildNavItems($siteData, $pages);
        $buildTimestamp = date('c');
        $sitemapEntries = [];

        foreach ($posts as $post) {
            $path = $this->config['dist_path'] . $post['frontMatter']['permalink'];
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
            $html = $this->renderer->render('post', [
                'post' => $post,
                'site' => $siteData,
                'navItems' => $navItems,
            ]);
            file_put_contents($path . 'index.html', $html);
        }

        foreach ($pages as $page) {
            $this->generateStandalonePage($page, $siteData, $navItems);
            if ($siteUrl !== '') {
                // 独立页同样是 md 文件，取自身最后一次提交时间
                $pageGitTs = GitLastmod::resolve($page['sourcePath'] ?? '');
                $sitemapEntries[$page['frontMatter']['permalink']] = $pageGitTs !== null
                    ? date('c', $pageGitTs)
                    : $buildTimestamp;
            }
        }

        $this->generateIndexPages($posts, $siteData, $navItems);
        $this->generateTaxonomyPages($tags, $categories, $archives, $siteData, $navItems, $siteUrl);

        $notFoundHtml = $this->renderer->render('404', [
            'site' => $siteData,
            'navItems' => $navItems,
        ]);
        file_put_contents($this->config['dist_path'] . '/404.html', $notFoundHtml);

        if ($siteUrl !== '') {
            $postTimestamps = [];
            foreach ($posts as $post) {
                $permalink = $post['frontMatter']['permalink'] ?? '';
                if ($permalink === '') {
                    continue;
                }

                // 复用 PostProcessor 已解析的更新时间；未提交的新文件回退到 date
                $gitTs = $post['updatedAt'] ?? null;
                $lastmod = $gitTs !== null
                    ? date('c', $gitTs)
                    : Utils::formatDate($post['frontMatter']['date'] ?? '', 'c');
                if ($lastmod === '') {
                    $lastmod = $buildTimestamp;
                }

                $sitemapEntries[$permalink] = $lastmod;

                $ts = strtotime($lastmod);
                if ($ts !== false) {
                    $postTimestamps[] = $ts;
                }
            }

            // 首页没有独立源文件，其内容即最新文章列表，取全站文章的最新时间
            $sitemapEntries['/'] = $postTimestamps
                ? date('c', max($postTimestamps))
                : $buildTimestamp;

            $sitemapXml = $this->buildSitemapXml($siteUrl, $sitemapEntries);
            file_put_contents($this->config['dist_path'] . '/sitemap.xml', $sitemapXml);
        }
    }

    private function generateStandalonePage(array $page, array $siteData, array $navItems): void
    {
        $path = $this->config['dist_path'] . $page['frontMatter']['permalink'];
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $template = $page['frontMatter']['template'] ?? 'page';
        $html = $this->renderer->render($template, [
            'page' => $page,
            'site' => $siteData,
            'navItems' => $navItems,
        ]);
        file_put_contents($path . 'index.html', $html);
    }

    private function generateIndexPages(array $posts, array $siteData, array $navItems): void
    {
        # 每页显示的文章数量
        $pageSize = 30;
        $pinnedPosts = array_filter($posts, function ($post) {
            return ($post['frontMatter']['pin'] ?? 0) > 0;
        });
        usort($pinnedPosts, function ($a, $b) {
            $aPin = $a['frontMatter']['pin'] ?? 0;
            $bPin = $b['frontMatter']['pin'] ?? 0;
            if ($aPin !== $bPin) {
                return $aPin <=> $bPin;
            }
            $aTs = Utils::dateToTimestamp($a['frontMatter']['date'], $a['sourcePath']);
            $bTs = Utils::dateToTimestamp($b['frontMatter']['date'], $b['sourcePath']);
            return $bTs <=> $aTs;
        });
        $pinnedPosts = array_slice($pinnedPosts, 0, 3);
        $pinnedPermalinks = array_flip(array_map(function ($post) {
            return $post['frontMatter']['permalink'] ?? '';
        }, $pinnedPosts));
        $remainingPosts = array_values(array_filter($posts, function ($post) use ($pinnedPermalinks) {
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

            $indexHtml = $this->renderer->render('index', [
                'posts' => $pagePosts,
                'pagination' => [
                    'current' => $page,
                    'total' => $totalPages,
                ],
                'site' => $siteData,
                'navItems' => $navItems,
            ]);

            if ($page === 1) {
                file_put_contents($this->config['dist_path'] . '/index.html', $indexHtml);
                continue;
            }

            $pagePath = $this->config['dist_path'] . '/page/' . $page . '/';
            if (!is_dir($pagePath)) {
                mkdir($pagePath, 0755, true);
            }
            file_put_contents($pagePath . 'index.html', $indexHtml);
        }
    }

    private function generateTaxonomyPages(array $tags, array $categories, array $archives, array $siteData, array $navItems, string $siteUrl): void
    {
        $tagsPath = $this->config['dist_path'] . '/tags/';
        mkdir($tagsPath, 0755, true);
        $tagsHtml = $this->renderer->render('tags', [
            'tags' => $tags,
            'site' => $siteData,
            'navItems' => $navItems,
        ]);
        file_put_contents($tagsPath . 'index.html', $tagsHtml);

        $categoriesPath = $this->config['dist_path'] . '/categories/';
        mkdir($categoriesPath, 0755, true);
        $categoriesHtml = $this->renderer->render('categories', [
            'categories' => $categories,
            'site' => $siteData,
            'navItems' => $navItems,
        ]);
        file_put_contents($categoriesPath . 'index.html', $categoriesHtml);

        $archivePath = $this->config['dist_path'] . '/archives/';
        mkdir($archivePath, 0755, true);
        $archiveHtml = $this->renderer->render('archive', [
            'archives' => $archives,
            'site' => $siteData,
            'navItems' => $navItems,
        ]);
        file_put_contents($archivePath . 'index.html', $archiveHtml);

        // 索引页与下方分类/标签落地页均不进 sitemap：它们是站点导航结构而非内容页，
        // 提交只会稀释 sitemap 中真实内容的占比。页面本身照常生成，读者访问不受影响。

        // 索引页之后生成各条目的独立落地页，顺序与索引页一致（数据层已按文章数降序）
        $this->generateTaxonomyChildren($categories, 'category', '分类', '/categories/', $siteData, $navItems, $siteUrl);
        $this->generateTaxonomyChildren($tags, 'tag', '标签', '/tags/', $siteData, $navItems, $siteUrl);
    }

    /**
     * 生成分类 / 标签的独立落地页，形如 /categories/{原始名称}/。
     *
     * 目录名保留原始名称，URL 由 Taxonomy::urlPath() 做一次 percent-encoding。
     * 不分页：单页列出该条目下全部文章，模板内按年份分节。
     *
     * @param array  $items           形如 [名称 => post[]]
     * @param string $kind            category | tag
     * @param string $label           报错与标题用的中文标签
     * @param string $basePath        形如 /categories/
     * @param array  $siteData
     * @param array  $navItems
     * @param string $siteUrl
     */
    private function generateTaxonomyChildren(
        array $items,
        string $kind,
        string $label,
        string $basePath,
        array $siteData,
        array $navItems,
        string $siteUrl
    ): void {
        // NTFS 大小写不敏感：本地 Windows 预览时，大小写不同的两个名称会写进同一个目录并
        // 静默覆盖（线上 Linux 则是两个独立页面）。只告警不自动改名，是否合并由作者决定。
        foreach (Taxonomy::findCaseConflicts(array_keys($items)) as $group) {
            Utils::log(
                "{$label}目录名大小写冲突：" . implode(' / ', $group)
                . '，本地 Windows 预览会互相覆盖',
                'warning'
            );
        }

        foreach ($items as $name => $posts) {
            $name = (string) $name;
            $urlPath = $basePath . Taxonomy::urlPath($name) . '/';
            $targetPath = $this->config['dist_path'] . $basePath . Taxonomy::dirName($name, $label);

            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }

            $html = $this->renderer->render('taxonomy', [
                'type' => $kind,
                'name' => $name,
                'posts' => $posts,
                'pageTitle' => "{$label}：{$name}",
                'pageDescription' => "{$label}「{$name}」下的全部文章，共 " . count($posts) . ' 篇。',
                'pageCanonical' => $siteUrl . $urlPath,
                'site' => $siteData,
                'navItems' => $navItems,
            ]);
            file_put_contents($targetPath . '/index.html', $html);
        }
    }

    private function buildNavItems(array $siteData, array $pages): array
    {
        $navItems = $siteData['nav'] ?? [];

        foreach ($pages as $page) {
            $frontMatter = $page['frontMatter'] ?? [];
            if (($frontMatter['nav'] ?? false) !== true) {
                continue;
            }

            $navItems[] = [
                'label' => $frontMatter['nav_title'] ?? ($frontMatter['title'] ?? ''),
                'url' => $frontMatter['permalink'] ?? '#',
                'order' => $frontMatter['nav_order'] ?? 100,
            ];
        }

        usort($navItems, function ($a, $b) {
            $aOrder = (int) ($a['order'] ?? 100);
            $bOrder = (int) ($b['order'] ?? 100);
            if ($aOrder !== $bOrder) {
                return $aOrder <=> $bOrder;
            }

            return strcmp($a['label'] ?? '', $b['label'] ?? '');
        });

        return $navItems;
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
}
