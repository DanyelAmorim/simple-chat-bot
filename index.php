<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: engine/engine.php?action=auth");
    exit;
}

$sidebarParam = $_GET['sidebar'] ?? '';
$containerClass = 'app-container';
if ($sidebarParam === 'hidden') {
    $containerClass .= ' sidebar-hidden';
} elseif ($sidebarParam === 'shown') {
    $containerClass .= ' sidebar-showing';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SENTRY AI</title>
<link rel="stylesheet" href="style/style.css">
</head>
<body>
<div class="<?php echo $containerClass; ?>">
    <iframe src="historic.php" name="sidebar" class="sidebar-frame"></iframe>
    
    <iframe src="chat.php#end" name="chat_window" class="chat-frame"></iframe>
</div>
</body>
</html>
