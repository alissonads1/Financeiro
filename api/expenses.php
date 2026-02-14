<?php
// =====================================================
// API de Gastos (Despesas)
// =====================================================
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$userId = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$db = getDB();

switch ($action) {
    case 'list':
        $where = ['e.user_id = ?'];
        $params = [$userId];

        if (!empty($_GET['date_from'])) {
            $where[] = 'e.date >= ?';
            $params[] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $where[] = 'e.date <= ?';
            $params[] = $_GET['date_to'];
        }
        if (!empty($_GET['category_id'])) {
            $where[] = 'e.category_id = ?';
            $params[] = $_GET['category_id'];
        }
        if (!empty($_GET['month'])) {
            $where[] = "CAST(strftime('%m', e.date) AS INTEGER) = ?";
            $params[] = $_GET['month'];
        }
        if (!empty($_GET['year'])) {
            $where[] = "CAST(strftime('%Y', e.date) AS INTEGER) = ?";
            $params[] = $_GET['year'];
        }
        if (!empty($_GET['search'])) {
            $where[] = '(e.observation LIKE ? OR e.category_name LIKE ? OR e.tags LIKE ?)';
            $s = '%' . $_GET['search'] . '%';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $whereStr = implode(' AND ', $where);
        $order = in_array(strtoupper($_GET['order'] ?? 'DESC'), ['ASC', 'DESC']) ? strtoupper($_GET['order'] ?? 'DESC') : 'DESC';

        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        // Count
        $countStmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(e.amount),0) as sum_total FROM expenses e WHERE $whereStr");
        $countStmt->execute($params);
        $totals = $countStmt->fetch();

        // Records
        $stmt = $db->prepare("
            SELECT e.*, c.name as cat_label, c.icon as cat_icon, c.color as cat_color
            FROM expenses e
            LEFT JOIN expense_categories c ON e.category_id = c.id
            WHERE $whereStr
            ORDER BY e.date $order, e.created_at $order
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
        $categoryId = intval($data['category_id'] ?? 0) ?: null;
        $categoryName = trim($data['category_name'] ?? '');
        $observation = trim($data['observation'] ?? '');
        $tags = trim($data['tags'] ?? '');

        if ($amount <= 0)
            jsonError('Valor deve ser maior que zero');

        if ($categoryId) {
            $stmt = $db->prepare('SELECT name FROM expense_categories WHERE id = ? AND user_id = ?');
            $stmt->execute([$categoryId, $userId]);
            $cat = $stmt->fetch();
            if ($cat)
                $categoryName = $cat['name'];
        }

        $stmt = $db->prepare('INSERT INTO expenses (user_id, amount, date, category_id, category_name, observation, tags) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $amount, $date, $categoryId, $categoryName, $observation, $tags]);

        jsonResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
        break;

    case 'delete':
        if ($method !== 'POST' && $method !== 'DELETE')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        if (!$id)
            jsonError('ID obrigatório');

        $stmt = $db->prepare('DELETE FROM expenses WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        jsonResponse(['success' => true]);
        break;

    case 'categories':
        $stmt = $db->prepare('SELECT * FROM expense_categories WHERE user_id = ? ORDER BY name ASC');
        $stmt->execute([$userId]);
        jsonResponse(['categories' => $stmt->fetchAll()]);
        break;

    case 'add_category':
        if ($method !== 'POST')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? '');
        $icon = trim($data['icon'] ?? '💸');
        $color = trim($data['color'] ?? '#ef4444');
        if (!$name)
            jsonError('Nome obrigatório');

        $stmt = $db->prepare('INSERT INTO expense_categories (user_id, name, icon, color) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $name, $icon, $color]);
        jsonResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
        break;

    case 'delete_category':
        if ($method !== 'POST')
            jsonError('Método não permitido', 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        if (!$id)
            jsonError('ID obrigatório');

        $stmt = $db->prepare('DELETE FROM expense_categories WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        jsonResponse(['success' => true]);
        break;

    case 'summary':
        $monthNames = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

        $dateFrom = $_GET['date_from'] ?? date('Y-m-01', strtotime('-5 months'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-t');

        $stmt = $db->prepare("
            SELECT CAST(strftime('%Y', date) AS INTEGER) as y,
                   CAST(strftime('%m', date) AS INTEGER) as m,
                   SUM(amount) as total, COUNT(*) as count
            FROM expenses
            WHERE user_id = ? AND date >= ? AND date <= ?
            GROUP BY strftime('%Y', date), strftime('%m', date)
            ORDER BY y DESC, m DESC
        ");
        $stmt->execute([$userId, $dateFrom, $dateTo]);
        $rows = $stmt->fetchAll();

        // Total geral do período
        $stmtTotal = $db->prepare("SELECT COALESCE(SUM(amount),0) as total, COUNT(*) as count FROM expenses WHERE user_id = ? AND date >= ? AND date <= ?");
        $stmtTotal->execute([$userId, $dateFrom, $dateTo]);
        $totals = $stmtTotal->fetch();

        $months = [];
        foreach ($rows as $r) {
            $t = floatval($r['total']);
            // Calcular dias no mês via PHP
            $dim = intval(date('t', mktime(0, 0, 0, $r['m'], 1, $r['y'])));
            $months[] = [
                'month' => intval($r['m']),
                'year' => intval($r['y']),
                'label' => $monthNames[intval($r['m'])] . '/' . $r['y'],
                'total' => $t,
                'count' => intval($r['count']),
                'avg_day' => $dim > 0 ? round($t / $dim, 2) : 0
            ];
        }

        jsonResponse([
            'months' => $months,
            'period_total' => floatval($totals['total']),
            'period_count' => intval($totals['count']),
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
        break;

    case 'export':
        $where = ['e.user_id = ?'];
        $params = [$userId];

        if (!empty($_GET['date_from'])) {
            $where[] = 'e.date >= ?';
            $params[] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $where[] = 'e.date <= ?';
            $params[] = $_GET['date_to'];
        }
        if (!empty($_GET['category_id'])) {
            $where[] = 'e.category_id = ?';
            $params[] = $_GET['category_id'];
        }

        $whereStr = implode(' AND ', $where);
        $stmt = $db->prepare("SELECT e.date, e.amount, e.category_name, e.observation, e.tags FROM expenses e WHERE $whereStr ORDER BY e.date DESC");
        $stmt->execute($params);

        $filename = 'gastos_' . date('d-m-Y') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['DATA', 'VALOR (R$)', 'CATEGORIA', 'OBSERVAÇÃO', 'TAGS'], ';');

        while ($r = $stmt->fetch()) {
            fputcsv($out, [
                date('d/m/Y', strtotime($r['date'])),
                number_format($r['amount'], 2, ',', '.'),
                mb_convert_case($r['category_name'] ?: 'Outros', MB_CASE_TITLE, "UTF-8"),
                $r['observation'],
                $r['tags']
            ], ';');
        }
        fclose($out);
        exit;

    case 'update_category':
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

        $stmt = $db->prepare("UPDATE expense_categories SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?");
        $stmt->execute($params);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonError('Ação inválida', 404);
}
