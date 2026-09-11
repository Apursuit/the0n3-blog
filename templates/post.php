<?php
$pageTitle = $post['frontMatter']['title'];
ob_start();
?>

<article class="post-content">
    <h1><?= htmlspecialchars($post['frontMatter']['title']) ?></h1>
    <p>
        <span class="meta">发布于: <?= \App\Utils::formatDate($post['frontMatter']['date'], 'Y-m-d H:i') ?></span>
    </p>
    <div class="post-body">
        <?= $post['html'] ?>
    </div>
    <?php if (!empty($post['frontMatter']['categories']) || !empty($post['frontMatter']['tags'])): ?>
    <div class="post-meta-footer">
        <?php if (!empty($post['frontMatter']['categories'])): ?>
            <span class="post-meta-item">分类:
                <?php foreach ($post['frontMatter']['categories'] as $category): ?>
                    <span><?= htmlspecialchars($category) ?></span>
                <?php endforeach; ?>
            </span>
        <?php endif; ?>
        <?php if (!empty($post['frontMatter']['tags'])): ?>
            <span class="post-meta-item">标签:
                <?php foreach ($post['frontMatter']['tags'] as $tag): ?>
                    <span>#<?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
            </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</article>

<?php
$giscus = $site['giscus'] ?? null;
$giscusEnabled = is_array($giscus) && !empty($giscus['enabled']);
?>

<?php if ($giscusEnabled): ?>
<?php
/*
 * 评论区只输出容器与配置，不直接输出 giscus 的 script 标签。
 * giscus 的主题是在创建 iframe 时由 client.js 读取 data-theme 拼进 widget URL 决定的，
 * 而站点主题只有前端才知道（存在 localStorage 里），构建期无法确定。
 * 因此真正的 script 由 assets/features/giscus-theme/script.js 按访客当前主题注入，
 * 保证评论区第一帧就与正文一致，不依赖事后 postMessage 纠正。
 */
?>
<section class="post-comments" aria-label="Comments"
    data-giscus
    data-theme-mode="<?= htmlspecialchars($giscus['theme'] ?? 'auto') ?>"
    data-theme-light="<?= htmlspecialchars($giscus['theme_light'] ?? 'light') ?>"
    data-theme-dark="<?= htmlspecialchars($giscus['theme_dark'] ?? 'dark_dimmed') ?>"
    data-repo="<?= htmlspecialchars($giscus['repo'] ?? '') ?>"
    data-repo-id="<?= htmlspecialchars($giscus['repo_id'] ?? '') ?>"
    data-category="<?= htmlspecialchars($giscus['category'] ?? '') ?>"
    data-category-id="<?= htmlspecialchars($giscus['category_id'] ?? '') ?>"
    data-mapping="<?= htmlspecialchars($giscus['mapping'] ?? 'pathname') ?>"
    data-strict="<?= htmlspecialchars($giscus['strict'] ?? '0') ?>"
    data-reactions-enabled="<?= htmlspecialchars($giscus['reactions_enabled'] ?? '1') ?>"
    data-emit-metadata="<?= htmlspecialchars($giscus['emit_metadata'] ?? '0') ?>"
    data-input-position="<?= htmlspecialchars($giscus['input_position'] ?? 'bottom') ?>"
    data-lang="<?= htmlspecialchars($giscus['lang'] ?? 'zh-CN') ?>">
    <div class="giscus"></div>
</section>
<?php endif; ?>

<?php
$content = ob_get_clean();

$siteUrl = rtrim($site['url'] ?? '', '/');
$pageCanonical = $siteUrl . $post['frontMatter']['permalink'];

$plain = trim(strip_tags($post['html']));
$plain = preg_replace('/\s+/', ' ', $plain);
$pageDescription = mb_substr($plain, 0, 160);
if (mb_strlen($plain) > 160) {
    $pageDescription .= '…';
}

$ogType = 'article';
$ogTitle = $post['frontMatter']['title'];

if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $post['html'], $m)) {
    $src = $m[1];
    if ($src !== '' && $src[0] === '/') {
        $ogImage = $siteUrl . $src;
    } elseif ($src !== '' && !preg_match('/^https?:\/\//', $src)) {
        $ogImage = $siteUrl . '/' . $src;
    } elseif ($src !== '') {
        $ogImage = $src;
    }
}

$showSidebar = $post['frontMatter']['sidebar'] ?? true;
if ($showSidebar) {
    ob_start();
    ?>
    <aside class="post-toc" aria-label="Table of contents">
        <div class="toc-title">目录</div>
        <nav class="toc-list"></nav>
    </aside>
    <?php
    $sidebar = ob_get_clean();
}
include 'layout.php';
?>
