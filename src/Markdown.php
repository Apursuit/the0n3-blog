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
    public static function toHtml(string $markdown): string
    {
        $html = self::getInstance()->text($markdown);
        return self::transformCallouts($html);
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
}
