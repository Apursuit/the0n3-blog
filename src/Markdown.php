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
        $html = self::enhanceImages($html, $imageBases);

        // 图标必须放在最后一次 DOM 序列化之后回填，否则会被 libxml 小写成 viewbox。
        return self::restoreIcons($html);
    }

    // 解析并转换 [!NOTE] 等标记为 GitHub 原版 alert 结构。
    // 输出：<div class="markdown-alert markdown-alert-note">
    //         <p class="markdown-alert-title"><svg class="octicon …">…</svg>Note</p>
    //         原 blockquote 内容
    //       </div>
    // 外观（图标 + 英文词 + 无底色 + 左侧竖线）完全由 github-markdown.css
    // 的 .markdown-alert 规则提供，站点样式只做一处间距微调。
    private static function transformCallouts(string $html): string
    {
        if (strpos($html, '[!') === false) {
            return $html;
        }

        // 类型 → [类名后缀, 标题词（GitHub 原版首字母大写）, 图标名]
        $types = [
            'NOTE'      => ['note', 'Note', 'info'],
            'TIP'       => ['tip', 'Tip', 'light-bulb'],
            'IMPORTANT' => ['important', 'Important', 'report'],
            'WARNING'   => ['warning', 'Warning', 'alert'],
            'CAUTION'   => ['caution', 'Caution', 'stop'],
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

            $type = $types[$matches[1]] ?? ['note', 'Note', 'info'];
            [$typeClass, $word, $icon] = $type;

            // 只裁掉首段开头的标记，保留段内既有的行内标记（加粗、行内代码等）
            $markerNode = self::firstTextNode($firstParagraph);
            if ($markerNode !== null) {
                $markerNode->nodeValue = preg_replace($pattern, '', $markerNode->nodeValue, 1);
            }

            if (trim($firstParagraph->textContent) === '') {
                $blockquote->removeChild($firstParagraph);
            }

            $alert = $doc->createElement('div');
            $alert->setAttribute('class', 'markdown-alert markdown-alert-' . $typeClass);
            $alert->setAttribute('dir', 'auto');

            $title = $doc->createElement('p');
            $title->setAttribute('class', 'markdown-alert-title');
            $title->setAttribute('dir', 'auto');
            $title->appendChild(self::iconSlot($doc, $icon));
            $title->appendChild($doc->createTextNode($word));
            $alert->appendChild($title);

            while ($blockquote->firstChild) {
                $alert->appendChild($blockquote->firstChild);
            }

            $blockquote->parentNode->replaceChild($alert, $blockquote);
        }

        $container = $doc->getElementsByTagName('div')->item(0);
        $output = '';
        if ($container) {
            foreach ($container->childNodes as $child) {
                $output .= $doc->saveHTML($child);
            }
        }

        // 图标先留占位 <span>，序列化之后由 restoreIcons() 回填纯 HTML 字符串：
        // libxml 的 HTML 序列化器会往 SVG 命名空间节点上加 xmlns:default 前缀、
        // 并把 viewBox 小写成 viewbox，浏览器会当成未知标签不渲染。
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $output ?: $html;
    }

    // 取节点内第一个文本节点：用于只裁剪开头的 [!XXX] 标记，
    // 不再用 nodeValue 整段覆盖（那会抹掉段内的加粗/行内代码）。
    private static function firstTextNode(\DOMNode $node): ?\DOMNode
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                return $child;
            }
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $found = self::firstTextNode($child);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    // 把占位 <span> 换成 GitHub Octicons 图标（最后一次序列化之后执行）。
    private static function restoreIcons(string $html): string
    {
        if (strpos($html, 'alert-icon-slot') === false) {
            return $html;
        }

        foreach (array_keys(self::ICON_PATHS) as $iconName) {
            $html = str_replace(
                '<span class="alert-icon-slot alert-icon-slot-' . $iconName . '"></span>',
                self::iconHtml($iconName),
                $html
            );
        }

        return $html;
    }

    // 图标占位元素：真正的 SVG 在最后一次序列化后回填，见 restoreIcons()。
    private static function iconSlot(\DOMDocument $doc, string $name): \DOMElement
    {
        $slot = $doc->createElement('span');
        $slot->setAttribute('class', 'alert-icon-slot alert-icon-slot-' . $name);

        return $slot;
    }

    // GitHub Octicons 16px 图标标记（与 GitHub alert 标题行完全一致）
    private static function iconHtml(string $name): string
    {
        $path = self::ICON_PATHS[$name] ?? self::ICON_PATHS['info'];

        return '<svg class="octicon octicon-' . $name . ' mr-2" viewBox="0 0 16 16"'
            . ' width="16" height="16" aria-hidden="true">'
            . '<path d="' . $path . '"></path></svg>';
    }

    // GitHub Octicons 16px 图标路径（MIT License，取自 github/octicons）
    private const ICON_PATHS = [
        'info' => 'M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8Zm8-6.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13ZM6.5 7.75A.75.75 0 0 1 7.25 7h1a.75.75 0 0 1 .75.75v2.75h.25a.75.75 0 0 1 0 1.5h-2a.75.75 0 0 1 0-1.5h.25v-2h-.25a.75.75 0 0 1-.75-.75ZM8 6a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z',
        'light-bulb' => 'M8 1.5c-2.363 0-4 1.69-4 3.75 0 .984.424 1.625.984 2.304l.214.253c.223.264.47.556.673.848.284.411.537.896.621 1.49a.75.75 0 0 1-1.484.211c-.04-.282-.163-.547-.37-.847a8.456 8.456 0 0 0-.542-.68c-.084-.1-.173-.205-.268-.32C3.201 7.75 2.5 6.766 2.5 5.25 2.5 2.31 4.863 0 8 0s5.5 2.31 5.5 5.25c0 1.516-.701 2.5-1.328 3.259-.095.115-.184.22-.268.319-.207.245-.383.453-.541.681-.208.3-.33.565-.37.847a.751.751 0 0 1-1.485-.212c.084-.593.337-1.078.621-1.489.203-.292.45-.584.673-.848.075-.088.147-.173.213-.253.561-.679.985-1.32.985-2.304 0-2.06-1.637-3.75-4-3.75ZM5.75 12h4.5a.75.75 0 0 1 0 1.5h-4.5a.75.75 0 0 1 0-1.5ZM6 15.25a.75.75 0 0 1 .75-.75h2.5a.75.75 0 0 1 0 1.5h-2.5a.75.75 0 0 1-.75-.75Z',
        'report' => 'M0 1.75C0 .784.784 0 1.75 0h12.5C15.216 0 16 .784 16 1.75v9.5A1.75 1.75 0 0 1 14.25 13H8.06l-2.573 2.573A1.458 1.458 0 0 1 3 14.543V13H1.75A1.75 1.75 0 0 1 0 11.25Zm1.75-.25a.25.25 0 0 0-.25.25v9.5c0 .138.112.25.25.25h2a.75.75 0 0 1 .75.75v2.19l2.72-2.72a.749.749 0 0 1 .53-.22h6.5a.25.25 0 0 0 .25-.25v-9.5a.25.25 0 0 0-.25-.25Zm7 2.25v2.5a.75.75 0 0 1-1.5 0v-2.5a.75.75 0 0 1 1.5 0ZM9 9a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z',
        'alert' => 'M6.457 1.047c.659-1.234 2.427-1.234 3.086 0l6.082 11.378A1.75 1.75 0 0 1 14.082 15H1.918a1.75 1.75 0 0 1-1.543-2.575Zm1.763.707a.25.25 0 0 0-.44 0L1.698 13.132a.25.25 0 0 0 .22.368h12.164a.25.25 0 0 0 .22-.368Zm.53 3.996v2.5a.75.75 0 0 1-1.5 0v-2.5a.75.75 0 0 1 1.5 0ZM9 11a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z',
        'stop' => 'M4.47.22A.749.749 0 0 1 5 0h6c.199 0 .389.079.53.22l4.25 4.25c.141.14.22.331.22.53v6a.749.749 0 0 1-.22.53l-4.25 4.25A.749.749 0 0 1 11 16H5a.749.749 0 0 1-.53-.22L.22 11.53A.749.749 0 0 1 0 11V5c0-.199.079-.389.22-.53Zm.84 1.28L1.5 5.31v5.38l3.81 3.81h5.38l3.81-3.81V5.31L10.69 1.5ZM8 4a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z',
    ];

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
