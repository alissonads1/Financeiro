<?php
// =====================================================
// API do Dashboard (Renda + Gastos)
// =====================================================
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$userId = requireAuth();
$db = getDB();

$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$monthStart = date('Y-m-01');
$yearStart = date('Y-01-01');

// ---- INCOME SUMMARY ----
$incSummary = function ($dateCondition, $params) use ($db, $userId) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as total FROM incomes WHERE user_id = ? AND $dateCondition");
    $stmt->execute(array_merge([$userId], $params));
    return floatval($stmt->fetch()['total']);
};

$incToday = $incSummary('date = ?', [$today]);
$incWeek = $incSummary('date >= ?', [$weekStart]);
$incMonth = $incSummary('date >= ?', [$monthStart]);
$incYear = $incSummary('date >= ?', [$yearStart]);

// ---- EXPENSE SUMMARY ----
$expSummary = function ($dateCondition, $params) use ($db, $userId) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE user_id = ? AND $dateCondition");
    $stmt->execute(array_merge([$userId], $params));
    return floatval($stmt->fetch()['total']);
};

$expToday = $expSummary('date = ?', [$today]);
$expWeek = $expSummary('date >= ?', [$weekStart]);
$expMonth = $expSummary('date >= ?', [$monthStart]);
$expYear = $expSummary('date >= ?', [$yearStart]);

// Total accumulated
$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as total FROM incomes WHERE user_id = ?");
$stmt->execute([$userId]);
$incTotal = floatval($stmt->fetch()['total']);

$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE user_id = ?");
$stmt->execute([$userId]);
$expTotal = floatval($stmt->fetch()['total']);

// Month growth (income)
$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as total FROM incomes WHERE user_id = ? AND date >= ? AND date <= ?");
$stmt->execute([$userId, $lastMonthStart, $lastMonthEnd]);
$lastMonth = floatval($stmt->fetch()['total']);
$monthGrowth = $lastMonth > 0 ? round((($incMonth - $lastMonth) / $lastMonth) * 100, 1) : 0;

// Monthly evolution (income vs expenses by month)
$twelveMonthsAgo = date('Y-m-d', strtotime('-12 months'));

$stmt = $db->prepare("
    SELECT strftime('%Y-%m', date) as month, SUM(amount) as total
    FROM incomes WHERE user_id = ? AND date >= ?
    GROUP BY month ORDER BY month
");
$stmt->execute([$userId, $twelveMonthsAgo]);
$monthlyIncome = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT strftime('%Y-%m', date) as month, SUM(amount) as total
    FROM expenses WHERE user_id = ? AND date >= ?
    GROUP BY month ORDER BY month
");
$stmt->execute([$userId, $twelveMonthsAgo]);
$monthlyExpenses = $stmt->fetchAll();

// Source distribution (income)
$stmt = $db->prepare("
    SELECT COALESCE(source_name, 'Outros') as source, SUM(amount) as total, COUNT(*) as count
    FROM incomes WHERE user_id = ? AND date >= ?
    GROUP BY source ORDER BY total DESC LIMIT 8
");
$stmt->execute([$userId, $monthStart]);
$sourceDist = $stmt->fetchAll();

// Category distribution (expenses)
$stmt = $db->prepare("
    SELECT COALESCE(category_name, 'Outros') as category, SUM(amount) as total, COUNT(*) as count
    FROM expenses WHERE user_id = ? AND date >= ?
    GROUP BY category ORDER BY total DESC LIMIT 8
");
$stmt->execute([$userId, $monthStart]);
$categoryDist = $stmt->fetchAll();

// Recent transactions (income)
$stmt = $db->prepare("
    SELECT i.*, s.icon as source_icon, s.color as source_color
    FROM incomes i LEFT JOIN income_sources s ON i.source_id = s.id
    WHERE i.user_id = ? ORDER BY i.date DESC, i.created_at DESC LIMIT 5
");
$stmt->execute([$userId]);
$recentIncome = $stmt->fetchAll();

// Recent expenses
$stmt = $db->prepare("
    SELECT e.*, c.icon as cat_icon, c.color as cat_color
    FROM expenses e LEFT JOIN expense_categories c ON e.category_id = c.id
    WHERE e.user_id = ? ORDER BY e.date DESC, e.created_at DESC LIMIT 5
");
$stmt->execute([$userId]);
$recentExpenses = $stmt->fetchAll();

// Active goals
$stmt = $db->prepare("SELECT * FROM goals WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 4");
$stmt->execute([$userId]);
$goals = $stmt->fetchAll();
foreach ($goals as &$g) {
    $g['percentage'] = $g['target_amount'] > 0
        ? round(($g['current_amount'] / $g['target_amount']) * 100, 1)
        : 0;
}

jsonResponse([
    'income' => [
        'today' => $incToday,
        'week' => $incWeek,
        'month' => $incMonth,
        'year' => $incYear,
        'total' => $incTotal,
        'month_growth' => $monthGrowth,
        'last_month' => $lastMonth
    ],
    'expenses' => [
        'today' => $expToday,
        'week' => $expWeek,
        'month' => $expMonth,
        'year' => $expYear,
        'total' => $expTotal
    ],
    'balance' => [
        'month' => $incMonth - $expMonth,
        'year' => $incYear - $expYear,
        'total' => $incTotal - $expTotal
    ],
    'monthly_income' => $monthlyIncome,
    'monthly_expenses' => $monthlyExpenses,
    'source_distribution' => $sourceDist,
    'category_distribution' => $categoryDist,
    'recent_income' => $recentIncome,
    'recent_expenses' => $recentExpenses,
    'active_goals' => $goals
]);
