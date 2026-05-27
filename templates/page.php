<?php
$pageTitle = $page['frontMatter']['title'];
ob_start();
?>

<h1><?= htmlspecialchars($page['frontMatter']['title']) ?></h1>
<?= $page['html'] ?>

<?php
$content = ob_get_clean();

$siteUrl = rtrim($site['url'] ?? '', '/');
$pageCanonical = $siteUrl . $page['frontMatter']['permalink'];

$plain = trim(strip_tags($page['html']));
$plain = preg_replace('/\s+/', ' ', $plain);
$pageDescription = mb_substr($plain, 0, 160);
if (mb_strlen($plain) > 160) {
    $pageDescription .= '...';
}

$ogTitle = $page['frontMatter']['title'];

include 'layout.php';
?>
