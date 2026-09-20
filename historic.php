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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ./engine/engine.php?action=auth");
    exit;
}

$dbDir = __DIR__ . '/database';
$dbFile = $dbDir . '/database.sqlite';
$pdo = null;

if (file_exists($dbFile)) {
    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pdo = null;
    }
}

$_SESSION['last_activity'] = time();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_chat_id'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed.');
    }

    $deleteId = (int)$_POST['delete_chat_id'];
    if ($pdo && $deleteId > 0) {
        $stmt = $pdo->prepare("DELETE FROM chats WHERE id = :id AND user_id = :user_id");
        $stmt->execute([
            ':id'      => $deleteId,
            ':user_id' => $_SESSION['user_id']
        ]);

        $stmtMsg = $pdo->prepare("DELETE FROM messages WHERE chat_id = :chat_id");
        $stmtMsg->execute([':chat_id' => $deleteId]);
        
        if (($_SESSION['active_chat_id'] ?? 0) === $deleteId) {
            unset($_SESSION['active_chat_id']);
        }
    }
    header("Location: index.php");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'new') {
    unset($_SESSION['active_chat_id']);
    header("Location: index.php");
    exit;
}

if (isset($_GET['select'])) {
    $_SESSION['active_chat_id'] = (int)$_GET['select'];
    header("Location: index.php");
    exit;
}

$chats = [];
$searchTerm = trim($_GET['search'] ?? '');

if ($pdo) {
    try {
        if ($searchTerm !== '') {
            $stmt = $pdo->prepare("SELECT id, title FROM chats WHERE user_id = :user_id AND title LIKE :search ORDER BY id DESC");
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':search'  => '%' . $searchTerm . '%'
            ]);
        } else {
            $stmt = $pdo->prepare("SELECT id, title FROM chats WHERE user_id = :user_id ORDER BY id DESC");
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
        }
        if ($stmt) {
            $chats = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $chats = [];
    }
}

$activeChatId = $_SESSION['active_chat_id'] ?? 0;
$username = $_SESSION['username'] ?? 'User';
$initial = strtoupper(substr($username, 0, 1));

$isHidden = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'sidebar=hidden') !== false;
$toggleLink = $isHidden ? 'index.php?sidebar=shown' : 'index.php?sidebar=hidden';
$toggleTitle = $isHidden ? 'Show sidebar' : 'Hide sidebar';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historic</title>
    <link rel="stylesheet" href="style/style.css">
</head>
<body class="sidebar-body">

    <div class="sidebar-top">
        <a href="index.php" target="_top" class="brand">SENTRY AI</a>
        <a href="historic.php?action=new" target="_top" class="icon-btn" title="New chat">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="M15 5l4 4"/></svg>
        </a>
        <a href="<?php echo $toggleLink; ?>" target="_top" class="toggle-btn" title="<?php echo $toggleTitle; ?>">
            <?php if ($isHidden): ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            <?php endif; ?>
        </a>
    </div>

    <form action="historic.php" method="GET" target="_self" class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" name="search" placeholder="Search chats" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
    </form>

    <nav class="conv-list">
    <?php if (empty($chats)): ?>
        <div style="font-size: 12px; color: var(--text-faint); padding: 8px;">No chats found</div>
    <?php else: ?>
        <?php foreach ($chats as $chat): ?>
            <div class="conv-item <?php echo ($chat['id'] == $activeChatId) ? 'active' : ''; ?>">
                <a href="historic.php?select=<?php echo $chat['id']; ?>" target="_top" class="title">
                    <?php echo htmlspecialchars($chat['title'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <details class="chat-menu">
                    <summary>⋮</summary>
                    <div class="menu-dropdown">
                        <form action="historic.php" method="POST" target="_top">
                            <input type="hidden" name="delete_chat_id" value="<?php echo $chat['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn-delete">Delete</button>
                        </form>
                    </div>
                </details>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </nav>

    <details class="profile-menu-container">
        <summary class="profile-summary">
            <div class="profile-avatar"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></div>
            <span class="profile-name"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span>
        </summary>
        <div class="profile-dropdown">
            <form action="./engine/engine.php?action=logout" method="POST" target="_top" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn-logout-pop">
                    🚪 Logout
                </button>
            </form>
        </div>
    </details>

</body>
</html>
