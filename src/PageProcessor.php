<?php

namespace App;

class PageProcessor
{
    private array $config;
    private array $publicPaths;
    private array $systemPages;
    private array $errors = [];

    public function __construct(array $config, array $publicPaths, array $systemPages)
    {
        $this->config = $config;
        $this->publicPaths = $publicPaths;
        $this->systemPages = $systemPages;
    }

    public function load(): array
    {
        $pagesPath = $this->config['pages_path'] ?? '';
        $this->errors = [];

        if ($pagesPath === '' || !is_dir($pagesPath)) {
            return [];
        }

        $loader = new Loader($pagesPath);
        $rawPages = $loader->loadPosts();
        $pages = [];
        $permalinkMap = [];

        foreach ($rawPages as $rawPage) {
            $source = $rawPage['sourcePath'];

            try {
                $parsed = FrontMatter::parse($rawPage['rawContent'], $source);
            } catch (\Exception $e) {
                $this->errors[] = "{$source}: {$e->getMessage()}";
                continue;
            }

            $frontMatter = $parsed['frontMatter'];
            if (($frontMatter['draft'] ?? false) === true) {
                continue;
            }

            $page = array_merge($rawPage, $parsed);
            $page['frontMatter'] = $this->normalizeFrontMatter($frontMatter);

            if (!$this->validatePage($page)) {
                continue;
            }

            $permalink = $page['frontMatter']['permalink'];
            $permalinkMap[$permalink][] = $source;
            $page['html'] = Markdown::toHtml($page['content']);
            $pages[] = $page;
        }

        $duplicateSources = $this->findDuplicatePermalinks($permalinkMap);
        if (!empty($duplicateSources)) {
            $pages = array_values(array_filter($pages, function ($page) use ($duplicateSources) {
                return !isset($duplicateSources[$page['sourcePath']]);
            }));
        }

        foreach ($this->errors as $error) {
            Utils::log($error, 'warning');
        }

        usort($pages, function ($a, $b) {
            $aOrder = (int) ($a['frontMatter']['nav_order'] ?? 100);
            $bOrder = (int) ($b['frontMatter']['nav_order'] ?? 100);
            if ($aOrder !== $bOrder) {
                return $aOrder <=> $bOrder;
            }

            return strcmp($a['frontMatter']['title'] ?? '', $b['frontMatter']['title'] ?? '');
        });

        return $pages;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function normalizeFrontMatter(array $frontMatter): array
    {
        $frontMatter['template'] = $frontMatter['template'] ?? 'page';
        $frontMatter['nav'] = $frontMatter['nav'] ?? false;
        $frontMatter['nav_title'] = $frontMatter['nav_title'] ?? ($frontMatter['title'] ?? '');
        $frontMatter['nav_order'] = $frontMatter['nav_order'] ?? 100;

        if (is_string($frontMatter['nav_order']) && ctype_digit($frontMatter['nav_order'])) {
            $frontMatter['nav_order'] = (int) $frontMatter['nav_order'];
        }

        return $frontMatter;
    }

    private function validatePage(array $page): bool
    {
        $source = $page['sourcePath'];
        $fm = $page['frontMatter'] ?? [];
        $valid = true;

        if (empty($fm['title'])) {
            $this->errors[] = "{$source}: missing title";
            $valid = false;
        }

        if (empty($fm['permalink']) || !is_string($fm['permalink']) || trim($fm['permalink']) === '') {
            $this->errors[] = "{$source}: missing permalink";
            $valid = false;
        }

        if (empty($page['content'])) {
            $this->errors[] = "{$source}: missing content";
            $valid = false;
        }

        if (!$valid) {
            return false;
        }

        $permalink = $fm['permalink'];
        if (strpos($permalink, '/../') !== false || strpos($permalink, '//') !== false || $permalink[0] !== '/' || substr($permalink, -1) !== '/') {
            $this->errors[] = "{$source}: invalid permalink '{$permalink}'";
            return false;
        }

        $trimmedPermalink = rtrim($permalink, '/');
        foreach ($this->systemPages as $systemPage) {
            if ($trimmedPermalink === rtrim($systemPage, '/')) {
                $this->errors[] = "{$source}: permalink '{$permalink}' conflicts with system page '{$systemPage}'";
                return false;
            }
        }

        foreach ($this->publicPaths as $publicPath) {
            if ($trimmedPermalink === rtrim($publicPath, '/')) {
                $this->errors[] = "{$source}: permalink '{$permalink}' conflicts with public asset '{$publicPath}'";
                return false;
            }
        }

        return true;
    }

    private function findDuplicatePermalinks(array $permalinkMap): array
    {
        $duplicateSources = [];

        foreach ($permalinkMap as $permalink => $sources) {
            if (count($sources) <= 1) {
                continue;
            }

            $this->errors[] = "duplicate page permalink '{$permalink}': " . implode(', ', $sources);
            foreach ($sources as $source) {
                $duplicateSources[$source] = true;
            }
        }

        return $duplicateSources;
    }
}
