<?php
/*
 * 分类索引：只做导航，不再内联文章 JSON。
 * 每个分类的完整文章列表在 /categories/{原始名称}/ 下，由 taxonomy.php 渲染。
 */
$pageTitle = '分类';
$pageDescription = '本站全部分类，按分类浏览文章。';
ob_start();
?>

<h1>分类</h1>
<ul class="tax-index">
    <?php foreach ($categories as $category => $posts): ?>
        <li class="tax-index__item">
            <a href="/categories/<?= htmlspecialchars(\App\Taxonomy::urlPath((string) $category)) ?>/"><?= htmlspecialchars((string) $category) ?></a>
            <span class="tax-index__count"><?= count($posts) ?></span>
        </li>
    <?php endforeach; ?>
</ul>

<?php
$content = ob_get_clean();

$pageCanonical = rtrim($site['url'] ?? '', '/') . '/categories/';

include 'layout.php';
?>
