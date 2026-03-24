<?php

namespace App;

class Loader
{
    private $postsPath;

    // 指定文章根目录。
    public function __construct(string $postsPath)
    {
        $this->postsPath = $postsPath;
    }

    /**
        * 扫描并读取所有 Markdown 文章。
     * @return array
     */
    public function loadPosts(): array
    {
        $posts = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->postsPath));
        $markdownFiles = new \RegexIterator($iterator, '/\.md$/');

        foreach ($markdownFiles as $file) {
            $content = file_get_contents($file->getPathname());
            $posts[] = [
                'sourcePath' => $file->getPathname(),
                'rawContent' => $content,
            ];
        }

        return $posts;
    }
}
