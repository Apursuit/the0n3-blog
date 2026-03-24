<?php
http_response_code(404);
$pageTitle = '404 Not Found';
ob_start();
?>

<h1>404 - Page Not Found</h1>
<p>来到了没有知识的荒原。。。。</p>
<p><a href="/">Return to Home</a></p>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
