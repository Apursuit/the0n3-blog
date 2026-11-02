<?php
/*
 * 分类 / 标签独立落地页，单个模板服务全部条目。
 * 数据由 PageGenerator::generateTaxonomyChildren() 注入：
 * $type('category'|'tag')、$name(原始名称)、$posts(已按日期倒序)。
 * $pageTitle / $pageDescription / $pageCanonical 由 layout.php 消费。
 *
 * 页面不设面包屑：h1 就是条目名，再加一行「标签 / sudo」属于重复信息。
 * 回到索引页的入口在导航栏（标签 / 分类）与文章页底部的链接上。
 */

// 按年份分节：Web安全 这类条目有 49 篇，单页全列会很长，
// 用年份分组复用归档页的视觉语言，比翻页更符合「看某条目全部文章」的意图。
// date 由 Validator 保证非空，这里不做兜底，出错时异常信息能定位到文件。
$byYear = [];
foreach ($posts as $item) {
    $timestamp = \App\Utils::dateToTimestamp(
        $item['frontMatter']['date'],
        $item['sourcePath'] ?? ''
    );
    $byYear[date('Y', $timestamp)][] = $item;
}
krsort($byYear, SORT_NUMERIC);

ob_start();
?>

<h1><?= htmlspecialchars((string) $name) ?></h1>
<p class="meta">共 <?= count($posts) ?> 篇文章</p>

<?php if (empty($posts)): ?>
    <p>暂无文章。</p>
<?php else: ?>
    <?php foreach ($byYear as $year => $items): ?>
        <h2><?= htmlspecialchars((string) $year) ?></h2>
        <ul class="post-list">
            <?php foreach ($items as $item): ?>
                <li class="post-item">
                    <a href="<?= htmlspecialchars($item['frontMatter']['permalink'] ?? '#') ?>"><?= htmlspecialchars($item['frontMatter']['title'] ?? '') ?></a>
                    <span class="meta"><?= \App\Utils::formatDate($item['frontMatter']['date'] ?? '', 'm-d') ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
