<?php
// =====================================================
// API de Metas Financeiras
// =====================================================
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$userId = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$db = getDB();

switch ($action) {
    case 'list':
        $status = $_GET['status'] ?? '';
        $where = 'user_id = ?';
        $params = [$userId];

        if ($status && in_array($status, ['active', 'completed', 'paused'])) {
            $where .= ' AND status = ?';
            $params[] = $status;
        }

        $stmt = $db->prepare("SELECT * FROM goals WHERE $where ORDER BY status ASC, created_at DESC");
        $stmt->execute($params);
        $goals = $stmt->fetchAll();

        // Calculate percentage
        foreach ($goals as &$g) {
            $g['percentage'] = $g['target_amount'] > 0
                ? round(($g['current_amount'] / $g['target_amount']) * 100, 1)
                : 0;
            $g['remaining'] = max(0, $g['target_amount'] - $g['current_amount']);
        }

        jsonResponse(['goals' => $goals]);
        break;

    case 'create':
        if ($method !== 'POST')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);

        $title = trim($data['title'] ?? '');
        $targetAmount = floatval($data['target_amount'] ?? 0);
        $currentAmount = floatval($data['current_amount'] ?? 0);
        $deadline = $data['deadline'] ?? null;
        $icon = trim($data['icon'] ?? '🎯');
        $color = trim($data['color'] ?? '#6366f1');

        if (!$title)
            jsonError('Título obrigatório');
        if ($targetAmount <= 0)
            jsonError('Valor da meta deve ser maior que zero');

        $stmt = $db->prepare('INSERT INTO goals (user_id, title, target_amount, current_amount, deadline, icon, color) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $title, $targetAmount, $currentAmount, $deadline ?: null, $icon, $color]);

        jsonResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
        break;

    case 'update':
        if ($method !== 'POST' && $method !== 'PUT')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        if (!$id)
            jsonError('ID obrigatório');

        $fields = [];
        $params = [];

        if (isset($data['title'])) {
            $fields[] = 'title=?';
            $params[] = trim($data['title']);
        }
        if (isset($data['target_amount'])) {
            $fields[] = 'target_amount=?';
            $params[] = floatval($data['target_amount']);
        }
        if (isset($data['current_amount'])) {
            $fields[] = 'current_amount=?';
            $params[] = floatval($data['current_amount']);
        }
        if (isset($data['deadline'])) {
            $fields[] = 'deadline=?';
            $params[] = $data['deadline'] ?: null;
        }
        if (isset($data['icon'])) {
            $fields[] = 'icon=?';
            $params[] = trim($data['icon']);
        }
        if (isset($data['color'])) {
            $fields[] = 'color=?';
            $params[] = trim($data['color']);
        }
        if (isset($data['status'])) {
            $fields[] = 'status=?';
            $params[] = $data['status'];
        }

        if (empty($fields))
            jsonError('Nenhum campo para atualizar');

        $params[] = $id;
        $params[] = $userId;

        $stmt = $db->prepare("UPDATE goals SET " . implode(',', $fields) . " WHERE id=? AND user_id=?");
        $stmt->execute($params);

        // Auto-complete if current >= target
        if (isset($data['current_amount'])) {
            $stmt = $db->prepare("UPDATE goals SET status='completed' WHERE id=? AND user_id=? AND current_amount >= target_amount AND status='active'");
            $stmt->execute([$id, $userId]);
        }

        jsonResponse(['success' => true]);
        break;

    case 'deposit':
        if ($method !== 'POST')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        $amount = floatval($data['amount'] ?? 0);

        if (!$id)
            jsonError('ID obrigatório');
        if ($amount == 0)
            jsonError('Valor inválido');

        // Check balance if withdrawing
        if ($amount < 0) {
            $stmt = $db->prepare('SELECT current_amount FROM goals WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);
            $goal = $stmt->fetch();
            if (!$goal || ($goal['current_amount'] + $amount < 0))
                jsonError('Saldo insuficiente na meta');
        }

        $stmt = $db->prepare('UPDATE goals SET current_amount = current_amount + ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$amount, $id, $userId]);

        // Auto-complete check
        $stmt = $db->prepare("UPDATE goals SET status='completed' WHERE id=? AND user_id=? AND current_amount >= target_amount AND status='active'");
        $stmt->execute([$id, $userId]);

        // Revert status if amount drops below target (e.g. was completed, now active again)
        $stmt = $db->prepare("UPDATE goals SET status='active' WHERE id=? AND user_id=? AND current_amount < target_amount AND status='completed'");
        $stmt->execute([$id, $userId]);

        jsonResponse(['success' => true]);
        break;

    case 'delete':
        if ($method !== 'POST' && $method !== 'DELETE')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        if (!$id)
            jsonError('ID obrigatório');

        $stmt = $db->prepare('DELETE FROM goals WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        jsonResponse(['success' => true]);
        break;

    default:
        jsonError('Ação inválida', 404);
}
