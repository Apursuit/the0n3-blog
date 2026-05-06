<?php

namespace App;

class PostProcessor
{
    private array $config;
    private array $parseErrors = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function load(): array
    {
        Utils::log("加载并处理文章...");
        $loader = new Loader($this->config['posts_path']);
        $rawPosts = $loader->loadPosts();
        $processedPosts = [];
        $this->parseErrors = [];

        $draftCount = 0;
        $errorCount = 0;
        foreach ($rawPosts as $rawPost) {
            try {
                $parsed = FrontMatter::parse($rawPost['rawContent'], $rawPost['sourcePath']);
                if ($parsed['frontMatter']['draft'] === true) {
                    $draftCount++;
                    continue;
                }
                $processedPosts[] = array_merge(
                    $rawPost,
                    $parsed
                );
            } catch (\Exception $e) {
                $errorCount++;
                $sourcePath = $rawPost['sourcePath'];
                $this->parseErrors[] = [
                    'file' => $sourcePath,
                    'error' => $e->getMessage(),
                ];
                Utils::log("跳过无法解析的文章: {$sourcePath} — {$e->getMessage()}", 'warning');
            }
        }

        if ($draftCount > 0) {
            Utils::log("跳过草稿：{$draftCount} 篇。");
        }
        if ($errorCount > 0) {
            Utils::log("跳过解析失败的文章：{$errorCount} 篇。", 'warning');
        }

        return $processedPosts;
    }

    public function hasParseErrors(): bool
    {
        return !empty($this->parseErrors);
    }

    public function getParseErrors(): array
    {
        return $this->parseErrors;
    }

    public function prepare(array $posts): array
    {
        Utils::log("准备数据...");

        foreach ($posts as &$post) {
            $post['html'] = Markdown::toHtml($post['content']);
        }
        unset($post);

        usort($posts, function ($a, $b) {
            $aTs = Utils::dateToTimestamp($a['frontMatter']['date'], $a['sourcePath']);
            $bTs = Utils::dateToTimestamp($b['frontMatter']['date'], $b['sourcePath']);
            return $bTs <=> $aTs;
        });

        $tags = [];
        $categories = [];
        $archives = [];

        foreach ($posts as $post) {
            foreach ($post['frontMatter']['tags'] as $tagName) {
                if (!isset($tags[$tagName])) {
                    $tags[$tagName] = [];
                }
                $tags[$tagName][] = $post;
            }

            foreach ($post['frontMatter']['categories'] as $categoryName) {
                if (!isset($categories[$categoryName])) {
                    $categories[$categoryName] = [];
                }
                $categories[$categoryName][] = $post;
            }

            $timestamp = Utils::dateToTimestamp($post['frontMatter']['date'], $post['sourcePath']);
            $year = date('Y', $timestamp);
            if (!isset($archives[$year])) {
                $archives[$year] = [];
            }
            $archives[$year][] = $post;
        }
        ksort($archives, SORT_NUMERIC);
        array_walk($archives, function (&$posts) {
            usort($posts, function ($a, $b) {
                $aTs = Utils::dateToTimestamp($a['frontMatter']['date'], $a['sourcePath']);
                $bTs = Utils::dateToTimestamp($b['frontMatter']['date'], $b['sourcePath']);
                return $bTs <=> $aTs;
            });
        });

        return [
            'posts' => $posts,
            'tags' => $tags,
            'categories' => $categories,
            'archives' => $archives,
        ];
    }
}
