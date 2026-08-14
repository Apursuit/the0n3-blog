<?php

namespace App;

use Parsedown;

class Markdown
{
    private static $instance;

    // 获取全局的 Parsedown 实例，避免重复初始化。
    public static function getInstance(): Parsedown
    {
        if (self::$instance === null) {
            self::$instance = new Parsedown();
            // Allow raw HTML
            self::$instance->setSafeMode(false);
        }
        return self::$instance;
    }

    // 将 Markdown 转为 HTML，并进行自定义后处理。
    // $imageBases 可传入 config（含 images_path / public_path），用于给本地图片注入真实宽高。
    public static function toHtml(string $markdown, ?array $imageBases = null): string
    {
        $html = self::getInstance()->text($markdown);
        $html = self::transformCallouts($html);
        return self::enhanceImages($html, $imageBases);
    }

    // 解析并转换 [!NOTE] 等标记为 callout 结构。
    private static function transformCallouts(string $html): string
    {
        if (strpos($html, '[!') === false) {
            return $html;
        }

        $types = [
            'NOTE' => 'note',
            'TIP' => 'tip',
            'WARNING' => 'warning',
            'IMPORTANT' => 'important',
            'CAUTION' => 'caution',
        ];

        $pattern = '/^\[!(NOTE|TIP|WARNING|IMPORTANT|CAUTION)\]\s*/';

        $previous = libxml_use_internal_errors(true);

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $wrapper = '<div>' . $html . '</div>';
        $doc->loadHTML('<?xml encoding="UTF-8" ?>' . $wrapper, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new \DOMXPath($doc);
        $blockquotes = $xpath->query('//blockquote');

        foreach ($blockquotes as $blockquote) {
            $firstParagraph = null;
            foreach ($blockquote->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE && $child->nodeName === 'p') {
                    $firstParagraph = $child;
                    break;
                }
            }

            if (!$firstParagraph) {
                continue;
            }

            $text = trim($firstParagraph->textContent ?? '');
            if (!preg_match($pattern, $text, $matches)) {
                continue;
            }

            $typeKey = $matches[1];
            $typeClass = $types[$typeKey] ?? 'note';

            if ($firstParagraph->firstChild && $firstParagraph->firstChild->nodeType === XML_TEXT_NODE) {
                $firstParagraph->firstChild->nodeValue = preg_replace($pattern, '', $firstParagraph->firstChild->nodeValue, 1);
            } else {
                $firstParagraph->nodeValue = preg_replace($pattern, '', $firstParagraph->textContent, 1);
            }

            if (trim($firstParagraph->textContent) === '') {
                $blockquote->removeChild($firstParagraph);
            }

            $callout = $doc->createElement('div');
            $callout->setAttribute('class', 'callout callout-' . $typeClass);

            while ($blockquote->firstChild) {
                $callout->appendChild($blockquote->firstChild);
            }

            $blockquote->parentNode->replaceChild($callout, $blockquote);
        }

        $container = $doc->getElementsByTagName('div')->item(0);
        $output = '';
        if ($container) {
            foreach ($container->childNodes as $child) {
                $output .= $doc->saveHTML($child);
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $output ?: $html;
    }

    private static function enhanceImages(string $html, ?array $imageBases = null): string
    {
        if (stripos($html, '<img') === false) {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $wrapper = '<div>' . $html . '</div>';
        $doc->loadHTML('<?xml encoding="UTF-8" ?>' . $wrapper, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $images = $doc->getElementsByTagName('img');
        $imageIndex = 0;

        foreach ($images as $img) {
            if (!$img->hasAttribute('decoding')) {
                $img->setAttribute('decoding', 'async');
            }

            if ($imageIndex > 0 && !$img->hasAttribute('loading')) {
                $img->setAttribute('loading', 'lazy');
            }

            // 注入本地图片真实宽高，浏览器据 width/height 预留 aspect-ratio 空间，
            // 避免懒加载图片撑开内容导致目录跳转/阅读进度偏移（CLS）。
            if (!$img->hasAttribute('width') && !$img->hasAttribute('height')) {
                $size = self::resolveLocalImageSize($img->getAttribute('src'), $imageBases);
                if ($size !== null) {
                    $img->setAttribute('width', (string) $size[0]);
                    $img->setAttribute('height', (string) $size[1]);
                }
            }

            $imageIndex++;
        }

        $container = $doc->getElementsByTagName('div')->item(0);
        $output = '';
        if ($container) {
            foreach ($container->childNodes as $child) {
                $output .= $doc->saveHTML($child);
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $output ?: $html;
    }

    // 将图片 src 解析为本地文件并读取真实尺寸；外部图/缺失/失败一律返回 null。
    private static function resolveLocalImageSize(string $src, ?array $bases): ?array
    {
        if ($bases === null || $src === '' || strpos($src, 'data:') === 0 || preg_match('#^(https?:)?//#', $src)) {
            return null;
        }

        if (strpos($src, '?') !== false) {
            $src = strtok($src, '?');
        }

        $path = null;
        if (strpos($src, '/images/') === 0 && !empty($bases['images_path'])) {
            $path = rtrim($bases['images_path'], '/\\') . '/' . ltrim(rawurldecode(substr($src, 8)), '/');
        } elseif ($src[0] === '/' && !empty($bases['public_path'])) {
            $path = rtrim($bases['public_path'], '/\\') . '/' . ltrim(rawurldecode(substr($src, 1)), '/');
        }

        if ($path === null || !is_file($path)) {
            return null;
        }

        $size = @getimagesize($path);
        if (is_array($size) && ($size[0] ?? 0) > 0 && ($size[1] ?? 0) > 0) {
            return [$size[0], $size[1]];
        }

        return null;
    }
}
