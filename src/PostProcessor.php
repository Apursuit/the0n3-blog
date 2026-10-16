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
                Utils::log("跳过无法解析的文章：{$sourcePath}，原因：{$e->getMessage()}", 'warning');
            }
        }

        if ($draftCount > 0) {
            Utils::log("跳过草稿文章：{$draftCount} 篇");
        }
        if ($errorCount > 0) {
            Utils::log("跳过解析失败的文章：{$errorCount} 篇", 'warning');
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
        foreach ($posts as &$post) {
            $post['html'] = Markdown::toHtml($post['content'], $this->config);
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

        // 分类 / 标签索引按文章数降序排列（数量相同按名称字节序兜底）。
        // 与上方 ksort($archives) 一致，排序在数据聚合层完成，模板不再持有排序逻辑。
        $tags = $this->sortTaxonomyByCount($tags);
        $categories = $this->sortTaxonomyByCount($categories);

        return [
            'posts' => $posts,
            'tags' => $tags,
            'categories' => $categories,
            'archives' => $archives,
        ];
    }

    /**
     * 按「文章数降序」重排分类 / 标签索引，保留 name => posts[] 的映射结构。
     *
     * - 数量多的排在前面，让热点分类 / 标签更靠前，便于浏览。
     * - 数量相同时按名称做字节序升序兜底，避免大量 count=1 的长尾在每次
     *   构建后顺序漂移，保证输出稳定可复现。
     * - 使用 PHP 5 兼容写法：不使用返回类型声明、类型化属性、<=> 与 ??。
     *
     * @param array $items 形如 ['分类名' => [post, post, ...], ...]
     * @return array       按数量降序（同数量按名称升序）重排后的索引
     */
    private function sortTaxonomyByCount($items)
    {
        uksort($items, function ($a, $b) use ($items) {
            $countA = count($items[$a]);
            $countB = count($items[$b]);

            if ($countA !== $countB) {
                return $countB - $countA;
            }

            // 字节序比较，中文按 UTF-8 码点排序，结果稳定即可。
            return strcmp($a, $b);
        });

        return $items;
    }
}
