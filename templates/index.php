<?php
$current = (int) ($pagination['current'] ?? 1);
$pageTitle = $current <= 1 ? '' : '第 ' . $current . ' 页';
$pageDescription = $site['description'] ?? '';
ob_start();
?>

<h1>最近</h1>
<ul class="post-list post-list--feed">
    <?php foreach ($posts as $post): ?>
        <?php
        $summary = $post['summary'] ?? null;
        $cover = trim((string) ($post['frontMatter']['cover'] ?? ''));
        $hasSummary = $summary !== null && $summary !== '';
        ?>
        <li class="post-item">
            <a class="post-item__link" href="<?= htmlspecialchars($post['frontMatter']['permalink']) ?>">
                <h2 class="post-item__title"><?= htmlspecialchars($post['frontMatter']['title']) ?></h2>
                <?php if ($cover !== ''): ?>
                    <div class="post-item__body">
                        <?php /* data-no-lightbox：避免点击封面被图片放大脚本接管，打断跳转 */ ?>
                        <img class="post-item__cover" src="<?= htmlspecialchars($cover) ?>" alt="" width="180" height="100" loading="lazy" decoding="async" data-no-lightbox="true">
                        <?php if ($hasSummary): ?>
                            <p class="post-item__desc"><?= htmlspecialchars($summary) ?></p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($hasSummary): ?>
                    <p class="post-item__desc"><?= htmlspecialchars($summary) ?></p>
                <?php endif; ?>
            </a>
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

$siteUrl = rtrim($site['url'] ?? '', '/');
$current = (int)($pagination['current'] ?? 1);
$pageCanonical = $current <= 1 ? $siteUrl . '/' : $siteUrl . '/page/' . $current . '/';

include 'layout.php';
?>
