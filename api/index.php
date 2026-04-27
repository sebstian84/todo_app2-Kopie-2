<?php
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$data_dir = __DIR__ . '/data/';
$encryption_key = "todo_secret_key_32_chars_long_!!!";

// Crypto Helpers
function encrypt($data, $key) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
}

function decrypt($data, $key) {
    $data = base64_decode($data);
    $iv_len = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $iv_len);
    $encrypted = substr($data, $iv_len);
    return openssl_decrypt($encrypted, 'aes-256-cbc', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
}

function read_secure_file($filename) {
    global $data_dir, $encryption_key;
    $path = $data_dir . $filename;
    if (!file_exists($path)) return null;
    $encrypted = file_get_contents($path);
    $json = decrypt($encrypted, $encryption_key);
    return json_decode($json, true);
}

function log_debug($msg) {
    $path = 'data/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[$timestamp] $msg\n";
    file_put_contents($path, $formatted, FILE_APPEND);
}

function write_secure_file($filename, $data) {
    global $data_dir, $encryption_key;
    $json = json_encode($data, JSON_PRETTY_PRINT);
    $encrypted = encrypt($json, $encryption_key);
    file_put_contents($data_dir . $filename, $encrypted);
}

// User Management
function get_user_credentials() {
    $users = read_secure_file('users.json');
    if (!$users) {
        $users = ['username' => 'frost0xx', 'password' => '381984'];
        write_secure_file('users.json', $users);
    }
    return $users;
}

function get_auth_token() {
    if (isset($_COOKIE['todo_auth_token'])) {
        return $_COOKIE['todo_auth_token'];
    }
    // Also check Authorization header
    $headers = [];
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
    }
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    // Alternative way to get headers if not using Apache or if header is missing in $headers
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            return $matches[1];
        }
    }
    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

function is_authorized() {
    $creds = get_user_credentials();
    $providedToken = get_auth_token();
    if (!$providedToken || !isset($creds['token'])) {
        return false;
    }
    // Use hash_equals to prevent timing attacks
    return hash_equals($creds['token'], $providedToken);
}

// Logging
function log_change($todoId, $action, $oldData, $newData, $description = null) {
    $changes = read_secure_file('changelog.json') ?: ['changes' => []];
    if (!isset($changes['changes'][$todoId])) $changes['changes'][$todoId] = [];
    $changes['changes'][$todoId][] = [
        'id' => (string)(time() * 1000),
        'timestamp' => date('c'),
        'action' => $action,
        'oldData' => $oldData,
        'newData' => $newData,
        'description' => $description
    ];
    if (count($changes['changes'][$todoId]) > 100) {
        $changes['changes'][$todoId] = array_slice($changes['changes'][$todoId], -100);
    }
    write_secure_file('changelog.json', $changes);
}

// Routing
// Remove query string
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Strip /api and /index.php for routing
$path = str_replace(['/api', '/index.php'], '', $path);
$method = $_SERVER['REQUEST_METHOD'];

// Auth Endpoint
if ($path === '/auth/login' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $creds = get_user_credentials();

    $password_matches = false;
    $should_migrate = false;

    // Check password (handle both plaintext migration and secure hash)
    if ($input['username'] === $creds['username']) {
        if (password_verify($input['password'], $creds['password'])) {
            $password_matches = true;
        } elseif ($input['password'] === $creds['password']) {
            // Legacy plaintext match - trigger migration
            $password_matches = true;
            $should_migrate = true;
        }
    }

    if ($password_matches) {
        // Generate secure random token
        $token = bin2hex(random_bytes(32));
        $creds['token'] = $token;

        // Upgrade plaintext password to hash if needed
        if ($should_migrate) {
            $creds['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }

        write_secure_file('users.json', $creds);

        setcookie('todo_auth_token', $token, [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'samesite' => 'Lax',
            'httponly' => true
        ]);
        echo json_encode(['token' => $token, 'username' => $creds['username']]);
    } else {
        http_response_code(401);
        echo json_encode(['message' => 'Ungültige Zugangsdaten']);
    }
    exit;
}
elseif ($path === '/auth/logout' && $method === 'POST') {
    setcookie('todo_auth_token', '', time() - 3600, '/');
    echo json_encode(['success' => true]);
    exit;
}

// All other endpoints require auth
if (!is_authorized()) {
    http_response_code(401);
    echo json_encode(['message' => 'Unauthorized']);
    exit;
}

if ($path === '/auth/update' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!empty($input['username']) && !empty($input['password'])) {
        // Generate secure random token and hash password
        $token = bin2hex(random_bytes(32));
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);

        write_secure_file('users.json', [
            'username' => $input['username'],
            'password' => $hashedPassword,
            'token' => $token
        ]);

        // Update cookie with new token
        setcookie('todo_auth_token', $token, [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'samesite' => 'Lax',
            'httponly' => true
        ]);
        echo json_encode(['success' => true, 'token' => $token]);
    } else {
        http_response_code(400);
        echo json_encode(['message' => 'Username und Passwort erforderlich']);
    }
}
elseif ($path === '/data' && $method === 'GET') {
    echo json_encode(read_secure_file('db.json') ?: ['todos' => []]);
}
elseif ($path === '/todos' && $method === 'POST') {
    $newData = json_decode(file_get_contents('php://input'), true);
    $oldData = read_secure_file('db.json') ?: ['todos' => []];
    $oldTodos = isset($oldData['todos']) && is_array($oldData['todos']) ? $oldData['todos'] : (is_array($oldData) ? $oldData : []);
    $newTodos = isset($newData['todos']) && is_array($newData['todos']) ? $newData['todos'] : (is_array($newData) ? $newData : []);
    
    foreach ($newTodos as $newTodo) {
        $existed = null;
        foreach ($oldTodos as $old) { if (isset($old['id']) && isset($newTodo['id']) && $old['id'] === $newTodo['id']) { $existed = $old; break; } }
        if (isset($newTodo['id'])) {
            if (!$existed) log_change($newTodo['id'], 'created', null, $newTodo);
            elseif (json_encode($existed) !== json_encode($newTodo)) log_change($newTodo['id'], 'updated', $existed, $newTodo);
        }
    }
    foreach ($oldTodos as $oldTodo) {
        $exists = false;
        foreach ($newTodos as $new) { if (isset($oldTodo['id']) && isset($new['id']) && $new['id'] === $oldTodo['id']) { $exists = true; break; } }
        if (!$exists && isset($oldTodo['id'])) log_change($oldTodo['id'], 'deleted', $oldTodo, null);
    }
    write_secure_file('db.json', $newData);
    echo json_encode(['success' => true]);
}
elseif ($path === '/settings' && $method === 'GET') {
    echo json_encode(read_secure_file('settings.json') ?: []);
}
elseif ($path === '/settings' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    write_secure_file('settings.json', $data);
    echo json_encode(['success' => true]);
}
elseif ($path === '/archive' && $method === 'GET') {
    echo json_encode(read_secure_file('archive.json') ?: ['archivedTodos' => []]);
}
elseif ($path === '/archive' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    write_secure_file('archive.json', $data);
    echo json_encode(['success' => true]);
}
elseif ($path === '/backup/export' && $method === 'GET') {
    $data = [
        'timestamp' => date('c'),
        'version' => '1.0',
        'data' => [
            'todos' => (read_secure_file('db.json') ?: ['todos' => []])['todos'],
            'archivedTodos' => (read_secure_file('archive.json') ?: ['archivedTodos' => []])['archivedTodos'],
            'settings' => read_secure_file('settings.json') ?: []
        ]
    ];
    echo json_encode($data);
}
elseif ($path === '/backup/import' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['data'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Ungültiges Format']);
        exit;
    }
    
    $data = $input['data'];
    $currentTodos = (read_secure_file('db.json') ?: ['todos' => []])['todos'];
    $currentArchived = (read_secure_file('archive.json') ?: ['archivedTodos' => []])['archivedTodos'];
    
    $skippedCount = 0;
    
    // Import Todos
    if (isset($data['todos'])) {
        foreach ($data['todos'] as $imported) {
            $duplicate = false;
            foreach ($currentTodos as $existing) {
                if (json_encode($existing) === json_encode($imported)) { $duplicate = true; break; }
            }
            if (!$duplicate) {
                $currentTodos[] = $imported;
                log_change($imported['id'], 'imported', null, $imported, 'Aus Backup importiert');
            } else {
                $skippedCount++;
            }
        }
        write_secure_file('db.json', ['todos' => $currentTodos]);
    }
    
    // Import Archive
    if (isset($data['archivedTodos'])) {
        foreach ($data['archivedTodos'] as $imported) {
            $duplicate = false;
            foreach ($currentArchived as $existing) {
                if (json_encode($existing) === json_encode($imported)) { $duplicate = true; break; }
            }
            if (!$duplicate) {
                $currentArchived[] = $imported;
                log_change($imported['id'], 'imported', null, $imported, 'Aus Backup importiert (archiviert)');
            } else {
                $skippedCount++;
            }
        }
        write_secure_file('archive.json', ['archivedTodos' => $currentArchived]);
    }
    
    // Import Settings (Overwrite)
    if (isset($data['settings'])) {
        write_secure_file('settings.json', $data['settings']);
    }
    
    echo json_encode(['success' => true, 'skippedCount' => $skippedCount]);
}
elseif ($path === '/changelog' && $method === 'GET') {
    $changes = read_secure_file('changelog.json') ?: ['changes' => []];
    $todos = (read_secure_file('db.json') ?: ['todos' => []])['todos'];
    $archived = (read_secure_file('archive.json') ?: ['archivedTodos' => []])['archivedTodos'];
    $allChanges = [];
    foreach ($changes['changes'] as $todoId => $list) {
        $todoName = "Unbekannt";
        foreach ($todos as $t) { if ($t['id'] === $todoId) { $todoName = $t['name']; break; } }
        if ($todoName === "Unbekannt") foreach ($archived as $t) { if ($t['id'] === $todoId) { $todoName = $t['name']; break; } }
        foreach ($list as $c) {
            if (!isset($c['undone']) || !$c['undone']) {
                $c['todoId'] = $todoId;
                $c['todoName'] = $todoName;
                $allChanges[] = $c;
            }
        }
    }
    usort($allChanges, function($a, $b) { return strcmp($b['timestamp'], $a['timestamp']); });
    echo json_encode(['changes' => $allChanges]);
}
elseif ($path === '/changelog/undo' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['todoId']) || !isset($input['changeId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing todoId or changeId']);
        exit;
    }
    
    $changesData = read_secure_file('changelog.json') ?: ['changes' => []];
    $todoId = $input['todoId'];
    $changeId = $input['changeId'];
    
    if (!isset($changesData['changes'][$todoId])) {
        echo json_encode(['success' => false, 'message' => 'Change not found']);
        exit;
    }
    
    $targetChange = null;
    foreach ($changesData['changes'][$todoId] as $c) {
        if ($c['id'] === $changeId) {
            $targetChange = $c;
            break;
        }
    }
    
    if (!$targetChange) {
        echo json_encode(['success' => false, 'message' => 'Change not found']);
        exit;
    }
    
    $db = read_secure_file('db.json') ?: ['todos' => []];
    $todos = isset($db['todos']) && is_array($db['todos']) ? $db['todos'] : [];
    
    $todoIndex = -1;
    foreach ($todos as $i => $t) {
        if (isset($t['id']) && (string)$t['id'] === (string)$todoId) {
            $todoIndex = $i;
            break;
        }
    }
    
    $oldData = isset($targetChange['oldData']) ? $targetChange['oldData'] : null;
    $newData = isset($targetChange['newData']) ? $targetChange['newData'] : null;
    $action = $targetChange['action'];
    
    $newDbState = $todos;
    $currentTodoState = $todoIndex !== -1 ? $todos[$todoIndex] : null;
    
    $undoDesc = "Wiederherstellung von Aktion: " . ($targetChange['description'] ?: $action);
    $undoAction = '';
    $finalNewData = null;
    
    if ($action === 'created' || ($action === 'imported' && !$oldData)) {
        if ($todoIndex !== -1) {
            array_splice($newDbState, $todoIndex, 1);
            $undoAction = 'deleted';
        } else {
            echo json_encode(['success' => false, 'message' => 'Todo existiert bereits nicht mehr.']);
            exit;
        }
    } elseif ($action === 'deleted') {
        if ($oldData) {
            if ($todoIndex !== -1) {
                $newDbState[$todoIndex] = $oldData;
            } else {
                $newDbState[] = $oldData;
            }
            $undoAction = 'created';
            $finalNewData = $oldData;
        }
    } elseif ($action === 'updated') {
        if ($oldData) {
            if ($todoIndex !== -1) {
                $newDbState[$todoIndex] = $oldData;
            } else {
                $newDbState[] = $oldData;
            }
            $undoAction = 'updated';
            $finalNewData = $oldData;
        }
    }
    
    if ($undoAction) {
        $db['todos'] = $newDbState;
        write_secure_file('db.json', $db);
        log_change($todoId, $undoAction, $currentTodoState, $finalNewData, $undoDesc);
        echo json_encode(['success' => true, 'message' => 'Wiederherstellung erfolgreich']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Keine Änderung möglich']);
    }
}
elseif ($path === '/changelog/delete' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['todoId']) || !isset($input['changeId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing todoId or changeId']);
        exit;
    }
    $changes = read_secure_file('changelog.json') ?: ['changes' => []];
    $todoId = $input['todoId'];
    $changeId = $input['changeId'];
    if (isset($changes['changes'][$todoId])) {
        $filtered = array_filter($changes['changes'][$todoId], function($c) use ($changeId) {
            return $c['id'] !== $changeId;
        });
        $changes['changes'][$todoId] = array_values($filtered);
        write_secure_file('changelog.json', $changes);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Change not found']);
    }
}
elseif ($path === '/changelog/clear' && $method === 'POST') {
    write_secure_file('changelog.json', ['changes' => []]);
    echo json_encode(['success' => true]);
}
elseif ($path === '/notes' && $method === 'GET') {
    echo json_encode(read_secure_file('notes.json') ?: []);
}
elseif ($path === '/notes' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    write_secure_file('notes.json', $data);
    echo json_encode(['success' => true]);
}
elseif ($path === '/notes/archive' && $method === 'GET') {
    echo json_encode(read_secure_file('notes_archive.json') ?: new stdClass());
}
elseif ($path === '/notes/archive' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    write_secure_file('notes_archive.json', $data);
    echo json_encode(['success' => true]);
}
elseif ($path === '/time' && $method === 'GET') {
    $data = read_secure_file('time_tracking.json') ?: new stdClass();
    log_debug("GET /time: " . json_encode($data));
    echo json_encode($data);
}
elseif ($path === '/time' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    log_debug("POST /time: " . json_encode($data));
    write_secure_file('time_tracking.json', $data);
    echo json_encode(['success' => true]);
}
else {
    http_response_code(404);
}
