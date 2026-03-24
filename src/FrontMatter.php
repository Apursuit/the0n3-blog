<?php

namespace App;

use Symfony\Component\Yaml\Yaml;
use App\Utils;

class FrontMatter
{
    /**
    * 解析 Front Matter，补齐默认值并返回内容。
     * @param string $content
     * @return array
     * @throws \Exception
     */
    public static function parse(string $content): array
    {
        $pattern = '/^---[\r\n]+(.*?)[\r\n]+---[\r\n]+(.*)/s';
        if (!preg_match($pattern, $content, $matches)) {
            throw new \Exception("Front matter not found or invalid format.");
        }

        $frontMatter = Yaml::parse($matches[1]);
        $markdownContent = $matches[2];

        if (!is_array($frontMatter)) {
            throw new \Exception('Front matter YAML must be a mapping/object.');
        }

        // Set default values
        $frontMatter['tags'] = $frontMatter['tags'] ?? [];
        $frontMatter['categories'] = $frontMatter['categories'] ?? [];
        if (is_string($frontMatter['categories'])) {
            $frontMatter['categories'] = [$frontMatter['categories']];
        }
        $frontMatter['pin'] = $frontMatter['pin'] ?? 0;
        if (is_string($frontMatter['pin']) && ctype_digit($frontMatter['pin'])) {
            $frontMatter['pin'] = (int) $frontMatter['pin'];
        }
        $frontMatter['draft'] = $frontMatter['draft'] ?? false;
        $frontMatter['sidebar'] = $frontMatter['sidebar'] ?? true;

        return [
            'frontMatter' => $frontMatter,
            'content' => $markdownContent,
        ];
    }
}
