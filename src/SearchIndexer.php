<?php

namespace App;

class SearchIndexer
{
    public function build(array $posts, string $distPath): void
    {
        Utils::log("生成搜索索引...");

        $indexData = [
            'version' => '2.0',
            'buildTime' => date('c'),
            'posts' => [],
        ];

        foreach ($posts as $post) {
            $headings = $this->extractHeadings($post['html']);

            $plainText = strip_tags($post['html']);
            $plainText = preg_replace('/\s+/', ' ', $plainText);
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

        $indexFile = $distPath . '/search-index.json';
        $json = json_encode($indexData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($indexFile, $json);

        $fileSize = round(filesize($indexFile) / 1024, 2);
        Utils::log("搜索索引已生成：{$fileSize}KB");
    }

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

        return array_unique($headings);
    }
}
