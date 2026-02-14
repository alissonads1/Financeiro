<?php
// =====================================================
// API de Registros de Renda
// =====================================================
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$userId = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$db = getDB();

switch ($action) {
    case 'list':
        $where = ['i.user_id = ?'];
        $params = [$userId];

        // Filters
        if (!empty($_GET['date_from'])) {
            $where[] = 'i.date >= ?';
            $params[] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $where[] = 'i.date <= ?';
            $params[] = $_GET['date_to'];
        }
        if (!empty($_GET['source_id'])) {
            $where[] = 'i.source_id = ?';
            $params[] = $_GET['source_id'];
        }
        if (!empty($_GET['month'])) {
            $where[] = "CAST(strftime('%m', i.date) AS INTEGER) = ?";
            $params[] = $_GET['month'];
        }
        if (!empty($_GET['year'])) {
            $where[] = "CAST(strftime('%Y', i.date) AS INTEGER) = ?";
            $params[] = $_GET['year'];
        }
        if (!empty($_GET['search'])) {
            $where[] = '(i.observation LIKE ? OR i.source_name LIKE ? OR i.tags LIKE ?)';
            $search = '%' . $_GET['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $whereStr = implode(' AND ', $where);
        $order = $_GET['order'] ?? 'DESC';
        $order = in_array(strtoupper($order), ['ASC', 'DESC']) ? strtoupper($order) : 'DESC';

        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        // Count total
        $countStmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(i.amount),0) as sum_total FROM incomes i WHERE $whereStr");
        $countStmt->execute($params);
        $totals = $countStmt->fetch();

        // Get records
        $stmt = $db->prepare("
            SELECT i.*, s.name as source_label, s.icon as source_icon, s.color as source_color
            FROM incomes i
            LEFT JOIN income_sources s ON i.source_id = s.id
            WHERE $whereStr
            ORDER BY i.date $order, i.created_at $order
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $records = $stmt->fetchAll();

        jsonResponse([
            'records' => $records,
            'total' => intval($totals['total']),
            'sum' => floatval($totals['sum_total']),
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($totals['total'] / $limit)
        ]);
        break;

    case 'create':
        if ($method !== 'POST')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);

        $amount = floatval($data['amount'] ?? 0);
        $date = $data['date'] ?? date('Y-m-d');
        $sourceId = intval($data['source_id'] ?? 0) ?: null;
        $sourceName = trim($data['source_name'] ?? '');
        $type = trim($data['type'] ?? 'entrada');
        $observation = trim($data['observation'] ?? '');
        $tags = trim($data['tags'] ?? '');

        if ($amount <= 0)
            jsonError('Valor deve ser maior que zero');

        // Get source name if source_id provided
        if ($sourceId) {
            $stmt = $db->prepare('SELECT name FROM income_sources WHERE id = ? AND user_id = ?');
            $stmt->execute([$sourceId, $userId]);
            $src = $stmt->fetch();
            if ($src)
                $sourceName = $src['name'];
        }

        $stmt = $db->prepare('INSERT INTO incomes (user_id, amount, date, source_id, source_name, type, observation, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $amount, $date, $sourceId, $sourceName, $type, $observation, $tags]);

        jsonResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
        break;

    case 'update':
        if ($method !== 'POST' && $method !== 'PUT')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        if (!$id)
            jsonError('ID obrigatório');

        $amount = floatval($data['amount'] ?? 0);
        $date = $data['date'] ?? date('Y-m-d');
        $sourceId = intval($data['source_id'] ?? 0) ?: null;
        $sourceName = trim($data['source_name'] ?? '');
        $type = trim($data['type'] ?? 'entrada');
        $observation = trim($data['observation'] ?? '');
        $tags = trim($data['tags'] ?? '');

        if ($amount <= 0)
            jsonError('Valor deve ser maior que zero');

        if ($sourceId) {
            $stmt = $db->prepare('SELECT name FROM income_sources WHERE id = ? AND user_id = ?');
            $stmt->execute([$sourceId, $userId]);
            $src = $stmt->fetch();
            if ($src)
                $sourceName = $src['name'];
        }

        $stmt = $db->prepare('UPDATE incomes SET amount=?, date=?, source_id=?, source_name=?, type=?, observation=?, tags=? WHERE id=? AND user_id=?');
        $stmt->execute([$amount, $date, $sourceId, $sourceName, $type, $observation, $tags, $id, $userId]);

        jsonResponse(['success' => true]);
        break;

    case 'delete':
        if ($method !== 'POST' && $method !== 'DELETE')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        if (!$id)
            jsonError('ID obrigatório');

        $stmt = $db->prepare('DELETE FROM incomes WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        jsonResponse(['success' => true]);
        break;

    case 'export':
        $where = ['i.user_id = ?'];
        $params = [$userId];

        if (!empty($_GET['date_from'])) {
            $where[] = 'i.date >= ?';
            $params[] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $where[] = 'i.date <= ?';
            $params[] = $_GET['date_to'];
        }
        if (!empty($_GET['source_id'])) {
            $where[] = 'i.source_id = ?';
            $params[] = $_GET['source_id'];
        }
        if (!empty($_GET['month'])) {
            $where[] = "CAST(strftime('%m', i.date) AS INTEGER) = ?";
            $params[] = $_GET['month'];
        }
        if (!empty($_GET['year'])) {
            $where[] = "CAST(strftime('%Y', i.date) AS INTEGER) = ?";
            $params[] = $_GET['year'];
        }

        $whereStr = implode(' AND ', $where);
        $stmt = $db->prepare("SELECT i.date, i.amount, i.source_name, i.type, i.observation, i.tags FROM incomes i WHERE $whereStr ORDER BY i.date DESC");
        $stmt->execute($params);

        $filename = 'relatorio_financeiro_' . date('d-m-Y') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM para Excel

        // Cabeçalho profissional
        fputcsv($out, ['DATA', 'VALOR (R$)', 'FONTE DE RENDA', 'TIPO', 'OBSERVAÇÃO', 'TAGS'], ';');

        while ($r = $stmt->fetch()) {
            fputcsv($out, [
                date('d/m/Y', strtotime($r['date'])),
                number_format($r['amount'], 2, ',', '.'),
                mb_convert_case($r['source_name'] ?: 'Outros', MB_CASE_TITLE, "UTF-8"),
                ucfirst($r['type']),
                $r['observation'],
                $r['tags']
            ], ';');
        }
        fclose($out);
        exit;

    default:
        jsonError('Ação inválida', 404);
}
