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
<section class="post-comments" aria-label="Comments">
    <script src="https://giscus.app/client.js"
        data-repo="<?= htmlspecialchars($giscus['repo'] ?? '') ?>"
        data-repo-id="<?= htmlspecialchars($giscus['repo_id'] ?? '') ?>"
        data-category="<?= htmlspecialchars($giscus['category'] ?? '') ?>"
        data-category-id="<?= htmlspecialchars($giscus['category_id'] ?? '') ?>"
        data-mapping="<?= htmlspecialchars($giscus['mapping'] ?? 'pathname') ?>"
        data-strict="<?= htmlspecialchars($giscus['strict'] ?? '0') ?>"
        data-reactions-enabled="<?= htmlspecialchars($giscus['reactions_enabled'] ?? '1') ?>"
        data-emit-metadata="<?= htmlspecialchars($giscus['emit_metadata'] ?? '0') ?>"
        data-input-position="<?= htmlspecialchars($giscus['input_position'] ?? 'bottom') ?>"
        data-theme="<?= htmlspecialchars($giscus['theme'] ?? 'preferred_color_scheme') ?>"
        data-lang="<?= htmlspecialchars($giscus['lang'] ?? 'zh-CN') ?>"
        crossorigin="anonymous"
        async>
    </script>
</section>
<?php endif; ?>

<?php
$content = ob_get_clean();
$enableReadingProgress = true;
$enableImageEnhance = true;
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
