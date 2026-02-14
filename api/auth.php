<?php
// =====================================================
// API de Perfis de Usuário (sem senha)
// =====================================================
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'profiles';
$db = getDB();

switch ($action) {
    case 'profiles':
        $stmt = $db->query("SELECT id, name, avatar, created_at FROM users ORDER BY name ASC");
        $users = $stmt->fetchAll();
        jsonResponse(['profiles' => $users]);
        break;

    case 'select':
        if ($method !== 'POST')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        if (!$id)
            jsonError('ID do perfil obrigatório');

        $stmt = $db->prepare('SELECT id, name, avatar, pin FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user)
            jsonError('Perfil não encontrado', 404);

        // Se tiver PIN, não loga direto
        if (!empty($user['pin'])) {
            jsonResponse(['require_pin' => true, 'id' => $user['id'], 'name' => $user['name'], 'avatar' => $user['avatar']]);
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        jsonResponse(['success' => true, 'user' => $user]);
        break;

    case 'verify':
        if ($method !== 'POST')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        $pin = trim($data['pin'] ?? '');

        if (!$id)
            jsonError('ID obrigatório');
        if (!$pin)
            jsonError('PIN obrigatório');

        $stmt = $db->prepare('SELECT id, name, avatar, pin FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user || $user['pin'] !== $pin) {
            jsonError('PIN incorreto', 401);
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        jsonResponse(['success' => true, 'user' => $user]);
        break;

    case 'create':
        if ($method !== 'POST')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? '');
        $avatar = trim($data['avatar'] ?? '👤');
        $pin = trim($data['pin'] ?? null);

        if (!$name)
            jsonError('Nome é obrigatório');
        if (strlen($name) < 2)
            jsonError('Nome deve ter pelo menos 2 caracteres');

        // Create user
        $stmt = $db->prepare('INSERT INTO users (username, name, avatar, pin) VALUES (?, ?, ?, ?)');
        $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)) . '_' . time();
        $stmt->execute([$username, $name, $avatar, $pin ?: null]);
        $userId = $db->lastInsertId();

        // Default income sources
        $sources = [
            ['iFood', '🛵', '#ff6b35'],
            ['Renda Online', '💻', '#6366f1'],
            ['Serviços', '🔧', '#10b981'],
            ['Vendas', '🛒', '#f59e0b'],
            ['Outros', '💰', '#8b5cf6']
        ];
        $stmt = $db->prepare('INSERT INTO income_sources (user_id, name, icon, color) VALUES (?, ?, ?, ?)');
        foreach ($sources as $s) {
            $stmt->execute([$userId, $s[0], $s[1], $s[2]]);
        }

        // Default expense categories
        $cats = [
            ['Mercado', '🛒', '#ef4444'],
            ['Gasolina', '⛽', '#f97316'],
            ['Alimentação', '🍔', '#eab308'],
            ['Contas', '📄', '#8b5cf6'],
            ['Transporte', '🚌', '#3b82f6'],
            ['Lazer', '🎮', '#ec4899'],
            ['Saúde', '💊', '#14b8a6'],
            ['Outros', '💸', '#6b7280']
        ];
        $stmt = $db->prepare('INSERT INTO expense_categories (user_id, name, icon, color) VALUES (?, ?, ?, ?)');
        foreach ($cats as $c) {
            $stmt->execute([$userId, $c[0], $c[1], $c[2]]);
        }

        // Auto-select new profile
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;

        jsonResponse(['success' => true, 'user' => ['id' => $userId, 'name' => $name, 'avatar' => $avatar]], 201);
        break;

    case 'delete':
        if ($method !== 'POST')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        if (!$id)
            jsonError('ID obrigatório');

        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);

        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
            session_destroy();
        }

        jsonResponse(['success' => true]);
        break;

    case 'check':
        if (isset($_SESSION['user_id'])) {
            $stmt = $db->prepare('SELECT id, name, avatar FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user) {
                jsonResponse(['authenticated' => true, 'user' => $user]);
            }
        }
        jsonResponse(['authenticated' => false]);
        break;

    case 'logout':
        session_destroy();
        jsonResponse(['success' => true]);
        break;

    default:
        jsonError('Ação inválida', 404);
}
