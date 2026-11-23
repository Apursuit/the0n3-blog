<?php

namespace App;

class Validator
{
    private $posts;
    private $publicPaths;
    private $systemPages;
    private $errors = [];

    public function __construct(array $publicPaths, array $systemPages)
    {
        $this->publicPaths = $publicPaths;
        $this->systemPages = $systemPages;
    }

    public function validate(array $posts): array
    {
        $this->posts = $posts;
        $this->errors = [];
        $invalidSources = [];

        $this->validateRequiredFields($invalidSources);
        $this->validateContentTypes($invalidSources);
        $this->validatePermalinks($invalidSources);
        $this->validateNoConflicts($invalidSources);
        $this->validateCoverPaths();

        foreach ($this->errors as $error) {
            Utils::log($error, 'warning');
        }

        $validPosts = [];
        foreach ($this->posts as $post) {
            if (!isset($invalidSources[$post['sourcePath']])) {
                $validPosts[] = $post;
            }
        }

        return $validPosts;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function validateRequiredFields(array &$invalidSources): void
    {
        foreach ($this->posts as $post) {
            $source = $post['sourcePath'];
            $fm = $post['frontMatter'] ?? [];

            if (empty($fm['title'])) {
                $this->errors[] = "{$source}: 缺少 title";
                $invalidSources[$source] = true;
            }
            if (empty($fm['date'])) {
                $this->errors[] = "{$source}: 缺少 date";
                $invalidSources[$source] = true;
            }
            if (empty($fm['permalink']) || (is_string($fm['permalink']) && trim($fm['permalink']) === '')) {
                $this->errors[] = "{$source}: 缺少 permalink";
                $invalidSources[$source] = true;
            }
            if (empty($post['content'])) {
                $this->errors[] = "{$source}: 缺少正文内容";
                $invalidSources[$source] = true;
            }
        }
    }

    private function validateContentTypes(array &$invalidSources): void
    {
        foreach ($this->posts as $post) {
            $source = $post['sourcePath'];
            $fm = $post['frontMatter'] ?? [];

            if (isset($fm['tags']) && !is_array($fm['tags'])) {
                $this->errors[] = "{$source}: tags 必须为数组";
                $invalidSources[$source] = true;
            }
            if (isset($fm['categories']) && !is_array($fm['categories'])) {
                $this->errors[] = "{$source}: categories 必须为数组";
                $invalidSources[$source] = true;
            }

            $pin = $fm['pin'] ?? 0;
            if (!is_int($pin) || $pin < 0 || $pin > 3) {
                $this->errors[] = "{$source}: pin 必须是 0-3 之间的整数，当前为 " . var_export($pin, true);
                $invalidSources[$source] = true;
            }

            $permalink = $fm['permalink'] ?? '';
            if ($permalink !== '' && (!is_string($permalink) || trim($permalink) === '')) {
                $this->errors[] = "{$source}: permalink 必须为非空字符串";
                $invalidSources[$source] = true;
            }
        }
    }

    private function validatePermalinks(array &$invalidSources): void
    {
        $permalinkMap = [];
        foreach ($this->posts as $post) {
            $source = $post['sourcePath'];
            if (isset($invalidSources[$source])) {
                continue;
            }
            $pl = $post['frontMatter']['permalink'] ?? '';
            if ($pl !== '' && is_string($pl)) {
                $permalinkMap[$pl][] = $source;
            }
        }

        foreach ($permalinkMap as $pl => $sources) {
            if (count($sources) > 1) {
                $first = array_shift($sources);
                $this->errors[] = "重复 permalink '{$pl}': " . implode(', ', array_merge([$first], $sources)) . "（保留 {$first}）";
                foreach ($sources as $dup) {
                    $invalidSources[$dup] = true;
                }
            }
        }

        foreach ($this->posts as $post) {
            $source = $post['sourcePath'];
            if (isset($invalidSources[$source])) {
                continue;
            }
            $permalink = $post['frontMatter']['permalink'] ?? '';

            if ($permalink === '' || !is_string($permalink)) {
                continue;
            }

            if (strpos($permalink, '/../') !== false || strpos($permalink, '//') !== false || $permalink[0] !== '/' || substr($permalink, -1) !== '/') {
                $this->errors[] = "{$source}: permalink 格式无效 '{$permalink}'";
                $invalidSources[$source] = true;
            }
        }
    }

    /**
     * cover 指向不存在的文件只告警，不剔除文章：图片缺失不影响正文可读，
     * 但静默 404 在静态站里很难察觉。
     */
    private function validateCoverPaths(): void
    {
        foreach ($this->posts as $post) {
            $cover = trim((string) ($post['frontMatter']['cover'] ?? ''));
            if ($cover === '') {
                continue;
            }

            if ($cover[0] !== '/' || !in_array($cover, $this->publicPaths, true)) {
                Utils::log("{$post['sourcePath']}: cover '{$cover}' 不是站内路径或文件不存在", 'warning');
            }
        }
    }

    private function validateNoConflicts(array &$invalidSources): void
    {
        foreach ($this->posts as $post) {
            $source = $post['sourcePath'];
            if (isset($invalidSources[$source])) {
                continue;
            }
            $pl = rtrim($post['frontMatter']['permalink'] ?? '', '/');

            if ($pl === '') {
                continue;
            }

            foreach ($this->systemPages as $sysPage) {
                if ($pl === $sysPage) {
                    $this->errors[] = "{$source}: permalink '{$pl}/' 与系统页面 '{$sysPage}' 冲突";
                    $invalidSources[$source] = true;
                    break;
                }
            }

            if (isset($invalidSources[$source])) {
                continue;
            }

            foreach ($this->publicPaths as $publicPath) {
                if (rtrim($publicPath, '/') === $pl) {
                    $this->errors[] = "{$source}: permalink '{$pl}/' 与 public 资源 '{$publicPath}' 冲突";
                    $invalidSources[$source] = true;
                    break;
                }
            }
        }
    }
}
