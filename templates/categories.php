<?php
$pageTitle = 'All Categories';
$enableTaxAccordion = true;
ob_start();
?>

<h1>分类</h1>
<ul class="tax-accordion">
    <?php
    ksort($categories);
    foreach ($categories as $category => $posts):
        $postItems = [];
        foreach ($posts as $post) {
            $postItems[] = [
                'title' => $post['frontMatter']['title'] ?? '',
                'permalink' => $post['frontMatter']['permalink'] ?? '#',
                'date' => \App\Utils::formatDate($post['frontMatter']['date'] ?? ''),
            ];
        }
        $postsJson = htmlspecialchars(json_encode($postItems, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        ?>
        <li class="tax-item" data-posts="<?= $postsJson ?>">
            <button class="tax-toggle" type="button" aria-expanded="false">
                <span class="tax-name"><?= htmlspecialchars($category) ?></span>
                <span class="tax-count"><?= count($posts) ?></span>
                <span class="tax-chevron" aria-hidden="true"></span>
            </button>
            <ul class="post-list" aria-hidden="true"></ul>
        </li>
    <?php endforeach; ?>
</ul>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
