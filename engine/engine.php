<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
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
$dbDir = dirname(__DIR__) . '/database';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}
$dbFile = $dbDir . '/database.sqlite';
$action = $_GET['action'] ?? $_POST['action'] ?? 'send';
if ($action === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            http_response_code(403);
            die('CSRF token validation failed.');
        }
    }
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
    header("Location: engine.php?action=auth");
    exit;
}
if ($action === 'auth' || $action === 'login' || $action === 'register') {
    $error = '';
    $success = '';
    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS chats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            chat_id INTEGER NOT NULL,
            sender TEXT NOT NULL,
            text TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (PDOException $e) {
        die("Database connection error.");
    }
    if (isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit;
    }
    $mode = $_GET['mode'] ?? (($action === 'register') ? 'register' : 'login');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            http_response_code(403);
            die('CSRF token validation failed.');
        }
        $subAction = $_POST['auth_action'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($username) || empty($password)) {
            $error = "Please fill in all fields.";
        } else {
            if ($subAction === 'register') {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
                $stmt->execute([':username' => $username]);
                if ($stmt->fetch()) {
                    $error = "Username already exists.";
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
                    $stmt->execute([
                        ':username' => $username,
                        ':password' => $hashedPassword
                    ]);
                    $success = "Account created successfully! You can now log in.";
                    $mode = 'login';
                }
            } elseif ($subAction === 'login') {
                $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = :username");
                $stmt->execute([':username' => $username]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $username;
                    session_write_close();
                    header("Location: ../index.php");
                    exit;
                } else {
                    $error = "Invalid username or password.";
                }
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENTRY AI - Authentication</title>
    <link rel="stylesheet" href="../style/style.css">
    </head>
    <body class="auth-body">
    <div class="auth-card">
        <h1 class="auth-title">SENTRY AI</h1>
        <?php if ($error): ?>
            <div class="msg-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="msg-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($mode === 'register'): ?>
            <form action="engine.php?action=auth&mode=register" method="POST">
                <input type="hidden" name="auth_action" value="register">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="auth-input" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="auth-input" required>
                </div>
                <button type="submit" class="btn-submit">Register</button>
            </form>
            <div class="auth-toggle">
                Already have an account? <a href="engine.php?action=auth&mode=login">Log In</a>
            </div>
        <?php else: ?>
            <form action="engine.php?action=auth&mode=login" method="POST">
                <input type="hidden" name="auth_action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="auth-input" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="auth-input" required>
                </div>
                <button type="submit" class="btn-submit">Log In</button>
            </form>
            <div class="auth-toggle">
                Don't have an account? <a href="engine.php?action=auth&mode=register">Register</a>
            </div>
        <?php endif; ?>
    </div>
    </body>
    </html>
    <?php
    exit;
}
if (!isset($_SESSION['user_id'])) {
    header("Location: engine.php?action=auth");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed.');
    }
    $userMessageText = trim($_POST['message']);
    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (empty($_SESSION['active_chat_id'])) {
            $stmt = $pdo->prepare("INSERT INTO chats (user_id, title) VALUES (:user_id, :title)");
            $title = (strlen($userMessageText) > 30) ? substr($userMessageText, 0, 30) . "..." : $userMessageText;
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':title'   => $title
            ]);
            $_SESSION['active_chat_id'] = $pdo->lastInsertId();
        }
        $activeChatId = $_SESSION['active_chat_id'];
        $stmt = $pdo->prepare("INSERT INTO messages (chat_id, sender, text) VALUES (:chat_id, 'user', :text)");
        $stmt->execute([
            ':chat_id' => $activeChatId,
            ':text'    => $userMessageText
        ]);
    } catch (Exception $e) {
    }
    header("Location: ../index.php");
    exit;
}
if ($action === 'process_ai') {
    $activeChatId = $_SESSION['active_chat_id'] ?? 0;
    if ($activeChatId > 0 && file_exists($dbFile)) {
        try {
            $pdo = new PDO('sqlite:' . $dbFile);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->prepare("
                SELECT sender, text FROM (
                    SELECT id, sender, text FROM messages
                    WHERE chat_id = :chat_id
                    ORDER BY id DESC LIMIT 6
                ) ORDER BY id ASC
            ");
            $stmt->execute([':chat_id' => $activeChatId]);
            $dbMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($dbMessages) && end($dbMessages)['sender'] === 'user') {
                $api_messages = [
                    ["role" => "system", "content" => "You are SENTRY AI, a helpful assistant."]
                ];
                foreach ($dbMessages as $msg) {
                    $api_messages[] = [
                        "role"    => ($msg['sender'] === 'user') ? 'user' : 'assistant',
                        "content" => $msg['text']
                    ];
                }
                $url = 'http://127.0.0.1:8082/v1/chat/completions';
                $payload = json_encode([
                    "model"       => "llama-3.2",
                    "messages"    => $api_messages,
                    "temperature" => 0.7,
                    "max_tokens"  => 4096
                ]);
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                $response  = curl_exec($ch);
                $curl_error = curl_error($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $ai_text = "";
                if ($response !== false && $http_code === 200) {
                    $data = json_decode($response, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                        if (isset($data['choices'][0]['message']['content'])) {
                            $ai_text = $data['choices'][0]['message']['content'];
                        } elseif (isset($data['choices'][0]['text'])) {
                            $ai_text = $data['choices'][0]['text'];
                        }
                    }
                }
                if (empty($ai_text)) {
                    $ai_text = "Error communicating with local AI service.";
                }
                $stmt = $pdo->prepare("INSERT INTO messages (chat_id, sender, text) VALUES (:chat_id, 'ai', :text)");
                $stmt->execute([
                    ':chat_id' => $activeChatId,
                    ':text'    => $ai_text
                ]);
            }
        } catch (Exception $e) {
        }
    }
    header("Location: ../chat.php#end");
    exit;
}
header("Location: ../chat.php#end");
exit;
