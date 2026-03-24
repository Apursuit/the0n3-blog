<?php
$pageTitle = 'Archives';
ob_start();
?>

<h1>归档</h1>
<?php
krsort($archives);
foreach ($archives as $year => $posts): ?>
    <h2><?= $year ?></h2>
    <ul class="post-list">
        <?php foreach ($posts as $post): ?>
            <li class="post-item">
                <a href="<?= htmlspecialchars($post['frontMatter']['permalink']) ?>"><?= htmlspecialchars($post['frontMatter']['title']) ?></a>
                <span class="meta"><?= \App\Utils::formatDate($post['frontMatter']['date'], 'm-d') ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endforeach; ?>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
