<?php
$pageTitle = 'Home';
ob_start();
?>

<h1>最近</h1>
<ul class="post-list">
    <?php foreach ($posts as $post): ?>
        <li class="post-item">
            <a href="<?= htmlspecialchars($post['frontMatter']['permalink']) ?>"><?= htmlspecialchars($post['frontMatter']['title']) ?></a>
            <span class="meta"><?= \App\Utils::formatDate($post['frontMatter']['date']) ?></span>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (!empty($pagination) && ($pagination['total'] ?? 1) > 1): ?>
    <?php
    $current = (int) ($pagination['current'] ?? 1);
    $total = (int) ($pagination['total'] ?? 1);
    $pageUrl = function (int $page): string {
        return $page <= 1 ? '/' : '/page/' . $page . '/';
    };
    ?>
    <nav class="pagination" aria-label="Pagination">
        <?php if ($current > 1): ?>
            <a class="pagination-link" href="<?= htmlspecialchars($pageUrl($current - 1)) ?>" rel="prev">上一页</a>
        <?php else: ?>
            <span class="pagination-link is-disabled">上一页</span>
        <?php endif; ?>

        <span class="pagination-link is-current" aria-current="page"><?= $current ?> / <?= $total ?></span>

        <?php if ($current < $total): ?>
            <a class="pagination-link" href="<?= htmlspecialchars($pageUrl($current + 1)) ?>" rel="next">下一页</a>
        <?php else: ?>
            <span class="pagination-link is-disabled">下一页</span>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
