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
