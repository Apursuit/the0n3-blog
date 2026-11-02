<?php
/*
 * 标签索引：标签云。
 *
 * 字号编码文章数（四档阈值 15 / 10 / 5，见 Taxonomy::weightTier），
 * 数量不占视觉空间：鼠标悬停看 title，读屏由 aria-label 提供。
 * 排序沿用数据层的文章数降序，视觉上形成由大到小的瀑布。
 */
$pageTitle = '标签';
$pageDescription = '本站全部标签，按标签浏览文章。';
ob_start();
?>

<h1>标签</h1>
<div class="tag-cloud">
    <?php foreach ($tags as $tag => $posts): ?>
        <?php
        $count = count($posts);
        $tier = \App\Taxonomy::weightTier($count);
        $tagName = (string) $tag;
        ?>
        <a class="tag-cloud__item tag-cloud__item--<?= $tier ?>"
           href="/tags/<?= htmlspecialchars(\App\Taxonomy::urlPath($tagName)) ?>/"
           title="<?= htmlspecialchars($tagName) ?> · <?= $count ?> 篇"
           aria-label="<?= htmlspecialchars($tagName) ?>，<?= $count ?> 篇文章"><?= htmlspecialchars($tagName) ?></a>
    <?php endforeach; ?>
</div>

<?php
$content = ob_get_clean();

$pageCanonical = rtrim($site['url'] ?? '', '/') . '/tags/';

include 'layout.php';
?>
