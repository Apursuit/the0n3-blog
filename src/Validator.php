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

    public function validate(array $posts): void
    {
        $this->posts = $posts;
        $this->errors = [];

        $this->validateRequiredFields();
        $this->validateContentTypes();
        $this->validatePermalinks();
        $this->validateNoConflicts();

        if (!empty($this->errors)) {
            throw new \Exception(
                "发现 " . count($this->errors) . " 个错误:\n" .
                implode("\n", $this->errors)
            );
        }
    }

    private function validateRequiredFields(): void
    {
        foreach ($this->posts as $post) {
            $source = $post['sourcePath'];
            $fm = $post['frontMatter'] ?? [];

            if (empty($fm['title'])) {
                $this->errors[] = "{$source}: 缺少 title";
            }
            if (empty($fm['date'])) {
                $this->errors[] = "{$source}: 缺少 date";
            }
            if (empty($fm['permalink']) || (is_string($fm['permalink']) && trim($fm['permalink']) === '')) {
                $this->errors[] = "{$source}: 缺少 permalink";
            }
            if (empty($post['content'])) {
                $this->errors[] = "{$source}: 缺少正文内容";
            }
        }
    }

    private function validateContentTypes(): void
    {
        foreach ($this->posts as $post) {
            $source = $post['sourcePath'];
            $fm = $post['frontMatter'] ?? [];

            if (isset($fm['tags']) && !is_array($fm['tags'])) {
                $this->errors[] = "{$source}: tags 必须为数组";
            }
            if (isset($fm['categories']) && !is_array($fm['categories'])) {
                $this->errors[] = "{$source}: categories 必须为数组";
            }

            $pin = $fm['pin'] ?? 0;
            if (!is_int($pin) || $pin < 0 || $pin > 3) {
                $this->errors[] = "{$source}: pin 必须是 0-3 之间的整数，当前为 " . var_export($pin, true);
            }

            $permalink = $fm['permalink'] ?? '';
            if ($permalink !== '' && (!is_string($permalink) || trim($permalink) === '')) {
                $this->errors[] = "{$source}: permalink 必须为非空字符串";
            }
        }
    }

    private function validatePermalinks(): void
    {
        $permalinkMap = [];
        foreach ($this->posts as $post) {
            $pl = $post['frontMatter']['permalink'] ?? '';
            if ($pl !== '' && is_string($pl)) {
                $permalinkMap[$pl][] = $post['sourcePath'];
            }
        }

        foreach ($permalinkMap as $pl => $sources) {
            if (count($sources) > 1) {
                $this->errors[] = "重复 permalink '{$pl}': " . implode(', ', $sources);
            }
        }

        foreach ($this->posts as $post) {
            $permalink = $post['frontMatter']['permalink'] ?? '';
            $source = $post['sourcePath'];

            if ($permalink === '' || !is_string($permalink)) {
                continue;
            }

            if (strpos($permalink, '/../') !== false || strpos($permalink, '//') !== false || $permalink[0] !== '/' || substr($permalink, -1) !== '/') {
                $this->errors[] = "{$source}: permalink 格式无效 '{$permalink}'";
            }
        }
    }

    private function validateNoConflicts(): void
    {
        foreach ($this->posts as $post) {
            $pl = rtrim($post['frontMatter']['permalink'] ?? '', '/');
            $source = $post['sourcePath'];

            if ($pl === '') {
                continue;
            }

            foreach ($this->systemPages as $sysPage) {
                if ($pl === $sysPage) {
                    $this->errors[] = "{$source}: permalink '{$pl}/' 与系统页面 '{$sysPage}' 冲突";
                    break;
                }
            }

            foreach ($this->publicPaths as $publicPath) {
                if (rtrim($publicPath, '/') === $pl) {
                    $this->errors[] = "{$source}: permalink '{$pl}/' 与 public 资源 '{$publicPath}' 冲突";
                    break;
                }
            }
        }
    }
}
