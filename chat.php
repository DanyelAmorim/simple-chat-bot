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
    header("Location: engine/engine.php?action=auth");
    exit;
}

$dbDir = __DIR__ . '/database';
$dbFile = $dbDir . '/database.sqlite';
$messages = [];
$activeChatId = $_SESSION['active_chat_id'] ?? 0;

if ($activeChatId > 0 && file_exists($dbFile)) {
    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT sender, text FROM messages WHERE chat_id = :chat_id ORDER BY id ASC");
        $stmt->execute([':chat_id' => $activeChatId]);
        $messages = $stmt->fetchAll();
    } catch (PDOException $e) {
        $messages = [];
    }
}

$isGenerating = false;
if (!empty($messages)) {
    $lastMessage = end($messages);
    if ($lastMessage['sender'] === 'user') {
        $isGenerating = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Llama</title>
    <link rel="stylesheet" href="style/style.css">
    <?php if ($isGenerating): ?>
        <meta http-equiv="refresh" content="0;url=./engine/engine.php?action=process_ai">
    <?php endif; ?>
</head>
<body>

<main class="main">
    <header class="topbar">
        <span>llama-3.2</span>
    </header>

    <section class="chat-area">
        <div class="messages-container" id="messages-container">
            <?php foreach ($messages as $msg): ?>
                <div class="msg-row <?php echo ($msg['sender'] === 'user') ? 'user' : 'ai'; ?>">
                    <div class="msg-bubble"><?php echo htmlspecialchars($msg['text'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            <?php endforeach; ?>

            <?php if ($isGenerating): ?>
                <div class="msg-row ai">
                    <div class="msg-bubble loading-dots">
                        <span>.</span><span>.</span><span>.</span>
                    </div>
                </div>
            <?php endif; ?>

            <div id="end"></div>
        </div>
    </section>

    <div class="input-wrap">
        <form action="./engine/engine.php" method="POST" target="_top" class="input-box">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <textarea name="message" id="message-text" rows="2" placeholder="Type a message..." required></textarea>
            <div class="input-toolbar">
                <button type="submit" class="send-btn" title="Send" aria-label="Send">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="#000" stroke-width="2">
                        <path d="M12 19V5"/>
                        <path d="m5 12 7-7 7 7"/>
                    </svg>
                </button>
            </div>
        </form>
    </div>
</main>

</body>
</html>
