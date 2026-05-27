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

        if ($siteUrl !== '') {
            $sitemapEntries['/'] = $buildTimestamp;
        }

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
                $sitemapEntries[$page['frontMatter']['permalink']] = $buildTimestamp;
            }
        }

        $this->generateIndexPages($posts, $siteData, $navItems, $sitemapEntries, $buildTimestamp, $siteUrl);
        $this->generateTaxonomyPages($tags, $categories, $archives, $siteData, $navItems, $sitemapEntries, $buildTimestamp, $siteUrl);

        $notFoundHtml = $this->renderer->render('404', [
            'site' => $siteData,
            'navItems' => $navItems,
        ]);
        file_put_contents($this->config['dist_path'] . '/404.html', $notFoundHtml);

        if ($siteUrl !== '') {
            foreach ($posts as $post) {
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

    private function generateIndexPages(array $posts, array $siteData, array $navItems, array &$sitemapEntries, string $buildTimestamp, string $siteUrl): void
    {
        $pageSize = 15;
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
    }

    private function generateTaxonomyPages(array $tags, array $categories, array $archives, array $siteData, array $navItems, array &$sitemapEntries, string $buildTimestamp, string $siteUrl): void
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

        if ($siteUrl !== '') {
            $sitemapEntries['/tags/'] = $buildTimestamp;
            $sitemapEntries['/categories/'] = $buildTimestamp;
            $sitemapEntries['/archives/'] = $buildTimestamp;
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
