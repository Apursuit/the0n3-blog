<?php

namespace App;

/**
 * 首页列表摘要：优先取 front matter 的 summary，其次 description，最后正文首段。
 *
 * 只做结构判断（元素类型、段落长度）和一道安全上限：
 * 显示几行由 CSS 的 line-clamp 决定，构建期不猜测断行位置。
 */
class Summary
{
    /** 最多向前考察的段落数，避免为了凑长度一路扫到正文深处 */
    private const MAX_CANDIDATES = 3;

    /** 段落少于该长度视为无效（"直接上代码："这类过场话） */
    public const MIN_LENGTH = 20;

    /** 安全上限：远大于两行容量，正常不会成为显示截断点，只防止极端长段撑大 HTML */
    public const MAX_LENGTH = 240;

    public static function fromHtml(string $html, int $maxLength = self::MAX_LENGTH): ?string
    {
        if (trim($html) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML(
            '<?xml encoding="UTF-8" ?><div class="summary-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($doc);
        $root = $xpath->query('//div[contains(@class, "summary-root")]')->item(0);
        if ($root === null) {
            return null;
        }

        $candidates = 0;
        foreach ($root->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $text = self::normalize((string) $node->nodeValue);
                if (mb_strlen($text, 'UTF-8') >= self::MIN_LENGTH) {
                    return self::limit($text, $maxLength);
                }
                continue;
            }

            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            // 只取顶层段落。标题、引用、代码、表格、图片段落都不是 p，
            // 提示块是 div（整棵子树一并跳过），因此不会漏出块内文字。
            if (strtolower($node->nodeName) !== 'p') {
                continue;
            }

            $text = self::normalize((string) $node->textContent);
            // 纯图片段落不计入候选，否则几张截图就会耗尽考察额度
            if ($text === '') {
                continue;
            }

            if ($candidates >= self::MAX_CANDIDATES) {
                break;
            }
            $candidates++;

            if (mb_strlen($text, 'UTF-8') < self::MIN_LENGTH) {
                continue;
            }

            return self::limit($text, $maxLength);
        }

        return null;
    }

    /**
     * 安全上限：超出才截断并补省略号。两行的实际裁切交给 CSS，
     * 这里不做标点回退，否则会把完整句子提前砍短。
     */
    public static function limit(string $text, int $maxLength = self::MAX_LENGTH): string
    {
        $text = self::normalize($text);
        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLength, 'UTF-8')) . '…';
    }

    /** 折叠换行、制表、&nbsp; 与全角空格，去掉首尾空白 */
    private static function normalize(string $text): string
    {
        $text = preg_replace('/[\s\x{00A0}\x{3000}]+/u', ' ', $text);

        return trim((string) $text);
    }
}
