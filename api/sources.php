<?php
// =====================================================
// API de Fontes de Renda
// =====================================================
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$userId = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$db = getDB();

switch ($action) {
    case 'list':
        $stmt = $db->prepare('SELECT * FROM income_sources WHERE user_id = ? ORDER BY name ASC');
        $stmt->execute([$userId]);
        jsonResponse(['sources' => $stmt->fetchAll()]);
        break;

    case 'create':
        if ($method !== 'POST')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? '');
        $icon = trim($data['icon'] ?? '💰');
        $color = trim($data['color'] ?? '#10b981');

        if (!$name)
            jsonError('Nome obrigatório');

        $stmt = $db->prepare('INSERT INTO income_sources (user_id, name, icon, color) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $name, $icon, $color]);

        jsonResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
        break;

    case 'delete':
        if ($method !== 'POST' && $method !== 'DELETE')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        if (!$id)
            jsonError('ID obrigatório');

        $stmt = $db->prepare('DELETE FROM income_sources WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        jsonResponse(['success' => true]);
        break;

    case 'update':
        if ($method !== 'POST')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $icon = trim($data['icon'] ?? '');
        if (!$id)
            jsonError('ID obrigatório');
        if (!$name)
            jsonError('Nome obrigatório');

        $fields = ['name = ?'];
        $params = [$name];
        if ($icon) {
            $fields[] = 'icon = ?';
            $params[] = $icon;
        }
        $params[] = $id;
        $params[] = $userId;

        $stmt = $db->prepare("UPDATE income_sources SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?");
        $stmt->execute($params);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonError('Ação inválida', 404);
}
