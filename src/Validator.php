<?php

namespace App;

class Validator
{
    private $posts;
    private $publicPaths;
    private $systemPages;

    // 收集公共资源与系统页面路径用于冲突校验。
    public function __construct(array $publicPaths, array $systemPages)
    {
        $this->publicPaths = $publicPaths;
        $this->systemPages = $systemPages;
    }

    // 执行文章的完整校验流程。
    public function validate(array $posts): void
    {
        $this->posts = $posts;
        $this->validateRequiredFields();
        $this->validateContentTypes();
        $this->validatePermalinks();
        $this->validateNoConflicts();
    }

    // 校验必须字段（标题、日期、链接与正文）。
    private function validateRequiredFields(): void
    {
        foreach ($this->posts as $post) {
            if (empty($post['frontMatter']['title'])) {
                throw new \Exception("Post '{$post['sourcePath']}' is missing title.");
            }
            if (empty($post['frontMatter']['date'])) {
                throw new \Exception("Post '{$post['sourcePath']}' is missing date.");
            }
            if (!array_key_exists('permalink', $post['frontMatter']) || (is_string($post['frontMatter']['permalink']) && trim($post['frontMatter']['permalink']) === '')) {
                throw new \Exception("Post '{$post['sourcePath']}' is missing permalink.");
            }
            if (empty($post['content'])) {
                throw new \Exception("Post '{$post['sourcePath']}' has no content.");
            }
        }
    }

    // 校验字段类型（tags/categories/pin/permalink）。
    private function validateContentTypes(): void
    {
        foreach ($this->posts as $post) {
            if (!is_array($post['frontMatter']['tags'])) {
                throw new \Exception("Post '{$post['sourcePath']}' tags must be an array.");
            }
            if (!is_array($post['frontMatter']['categories'])) {
                throw new \Exception("Post '{$post['sourcePath']}' categories must be an array.");
            }
            $pin = $post['frontMatter']['pin'] ?? 0;
            if (!is_int($pin) || $pin < 0 || $pin > 3) {
                throw new \Exception("Post '{$post['sourcePath']}' pin must be an integer between 0 and 3.");
            }
            if (!is_string($post['frontMatter']['permalink']) || trim($post['frontMatter']['permalink']) === '') {
                throw new \Exception("Post '{$post['sourcePath']}' permalink must be a non-empty string.");
            }
        }
    }

    // 校验 permalink 唯一性与格式。
    private function validatePermalinks(): void
    {
        $permalinks = array_map(fn($p) => $p['frontMatter']['permalink'], $this->posts);
        if (count($permalinks) !== count(array_unique($permalinks))) {
            throw new \Exception("Duplicate permalinks found.");
        }

        foreach ($this->posts as $post) {
            $permalink = $post['frontMatter']['permalink'];
            if (!is_string($permalink) || trim($permalink) === '') {
                throw new \Exception('Missing required field: permalink');
            }
            if (
                !preg_match('/^\/posts\/[a-zA-Z0-9_-]+\/$/', $permalink) &&
                !preg_match('/^\/posts\/[a-f0-9]{6}\/$/', $permalink)
            ) {
                 // This is a simplified check. A more robust one might be needed.
                 if (strpos($permalink, '/../') !== false || strpos($permalink, '//') !== false || $permalink[0] !== '/' || substr($permalink, -1) !== '/') {
                    throw new \Exception("Invalid permalink '{$permalink}' in '{$post['sourcePath']}'.");
                 }
            }
        }
    }

    // 校验 permalink 不与系统页或 public 资源冲突。
    private function validateNoConflicts(): void
    {
        $postPermalinks = array_map(fn($p) => rtrim($p['frontMatter']['permalink'], '/'), $this->posts);

        foreach ($postPermalinks as $permalink) {
            if (in_array($permalink, $this->systemPages)) {
                throw new \Exception("Permalink '{$permalink}/' conflicts with a system page.");
            }
            if (in_array($permalink, $this->publicPaths)) {
                throw new \Exception("Permalink '{$permalink}/' conflicts with a public path.");
            }
        }
    }
}
