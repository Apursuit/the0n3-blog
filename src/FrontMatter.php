<?php

namespace App;

use Symfony\Component\Yaml\Yaml;

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

        $frontMatterRaw = $matches[1];
        $frontMatter = Yaml::parse($frontMatterRaw);
        $markdownContent = $matches[2];

        if (!is_array($frontMatter)) {
            throw new \Exception('Front matter YAML must be a mapping/object.');
        }

        $rawDate = self::extractRawScalarField($frontMatterRaw, 'date');
        if ($rawDate !== null) {
            $frontMatter['date'] = self::normalizeRawScalarValue($rawDate);
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

    private static function extractRawScalarField(string $frontMatterRaw, string $field): ?string
    {
        $pattern = '/^' . preg_quote($field, '/') . ':\s*(.+)\s*$/m';
        if (!preg_match($pattern, $frontMatterRaw, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    private static function normalizeRawScalarValue(string $value): string
    {
        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }
}
