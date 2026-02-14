<?php
// =====================================================
// API de Relatórios (Renda + Gastos)
// =====================================================
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$userId = requireAuth();
$db = getDB();
$action = $_GET['action'] ?? 'monthly';

switch ($action) {
    case 'monthly':
        $month = intval($_GET['month'] ?? date('n'));
        $year = intval($_GET['year'] ?? date('Y'));
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        $daysInMonth = intval(date('t', strtotime($startDate)));

        // Income stats
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as total, COUNT(*) as count FROM incomes WHERE user_id=? AND date >= ? AND date <= ?");
        $stmt->execute([$userId, $startDate, $endDate]);
        $incStats = $stmt->fetch();
        $incTotal = floatval($incStats['total']);

        // Expense stats
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as total, COUNT(*) as count FROM expenses WHERE user_id=? AND date >= ? AND date <= ?");
        $stmt->execute([$userId, $startDate, $endDate]);
        $expStats = $stmt->fetch();
        $expTotal = floatval($expStats['total']);

        // Daily income
        $stmt = $db->prepare("SELECT date, SUM(amount) as total FROM incomes WHERE user_id=? AND date >= ? AND date <= ? GROUP BY date ORDER BY date");
        $stmt->execute([$userId, $startDate, $endDate]);
        $dailyIncome = $stmt->fetchAll();

        // Daily expenses
        $stmt = $db->prepare("SELECT date, SUM(amount) as total FROM expenses WHERE user_id=? AND date >= ? AND date <= ? GROUP BY date ORDER BY date");
        $stmt->execute([$userId, $startDate, $endDate]);
        $dailyExpenses = $stmt->fetchAll();

        // Income by source
        $stmt = $db->prepare("SELECT COALESCE(source_name,'Outros') as name, SUM(amount) as total FROM incomes WHERE user_id=? AND date >= ? AND date <= ? GROUP BY name ORDER BY total DESC");
        $stmt->execute([$userId, $startDate, $endDate]);
        $bySource = $stmt->fetchAll();

        // Expenses by category
        $stmt = $db->prepare("SELECT COALESCE(category_name,'Outros') as name, SUM(amount) as total FROM expenses WHERE user_id=? AND date >= ? AND date <= ? GROUP BY name ORDER BY total DESC");
        $stmt->execute([$userId, $startDate, $endDate]);
        $byCategory = $stmt->fetchAll();

        // Best day (income)
        $stmt = $db->prepare("SELECT date, SUM(amount) as total FROM incomes WHERE user_id=? AND date >= ? AND date <= ? GROUP BY date ORDER BY total DESC LIMIT 1");
        $stmt->execute([$userId, $startDate, $endDate]);
        $bestDay = $stmt->fetch() ?: null;

        // Previous month comparison (income)
        $prevStart = date('Y-m-01', strtotime("$startDate -1 month"));
        $prevEnd = date('Y-m-t', strtotime($prevStart));
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as total FROM incomes WHERE user_id=? AND date >= ? AND date <= ?");
        $stmt->execute([$userId, $prevStart, $prevEnd]);
        $prevInc = floatval($stmt->fetch()['total']);
        $incGrowth = $prevInc > 0 ? round(($incTotal - $prevInc) / $prevInc * 100, 1) : 0;

        jsonResponse([
            'income_total' => $incTotal,
            'income_count' => intval($incStats['count']),
            'expense_total' => $expTotal,
            'expense_count' => intval($expStats['count']),
            'balance' => $incTotal - $expTotal,
            'avg_daily_income' => round($incTotal / $daysInMonth, 2),
            'avg_daily_expense' => round($expTotal / $daysInMonth, 2),
            'best_day' => $bestDay,
            'income_growth' => $incGrowth,
            'daily_income' => $dailyIncome,
            'daily_expenses' => $dailyExpenses,
            'by_source' => $bySource,
            'by_category' => $byCategory
        ]);
        break;

    case 'annual':
        $year = intval($_GET['year'] ?? date('Y'));
        $startDate = "$year-01-01";
        $endDate = "$year-12-31";

        // Income
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as total, COUNT(*) as count FROM incomes WHERE user_id=? AND date >= ? AND date <= ?");
        $stmt->execute([$userId, $startDate, $endDate]);
        $incStats = $stmt->fetch();
        $incTotal = floatval($incStats['total']);

        // Expenses
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as total, COUNT(*) as count FROM expenses WHERE user_id=? AND date >= ? AND date <= ?");
        $stmt->execute([$userId, $startDate, $endDate]);
        $expStats = $stmt->fetch();
        $expTotal = floatval($expStats['total']);

        // Monthly income
        $stmt = $db->prepare("SELECT CAST(strftime('%m', date) AS INTEGER) as month, SUM(amount) as total FROM incomes WHERE user_id=? AND date >= ? AND date <= ? GROUP BY month ORDER BY month");
        $stmt->execute([$userId, $startDate, $endDate]);
        $monthlyIncome = $stmt->fetchAll();

        // Monthly expenses
        $stmt = $db->prepare("SELECT CAST(strftime('%m', date) AS INTEGER) as month, SUM(amount) as total FROM expenses WHERE user_id=? AND date >= ? AND date <= ? GROUP BY month ORDER BY month");
        $stmt->execute([$userId, $startDate, $endDate]);
        $monthlyExpenses = $stmt->fetchAll();

        // By source
        $stmt = $db->prepare("SELECT COALESCE(source_name,'Outros') as name, SUM(amount) as total FROM incomes WHERE user_id=? AND date >= ? AND date <= ? GROUP BY name ORDER BY total DESC");
        $stmt->execute([$userId, $startDate, $endDate]);
        $bySource = $stmt->fetchAll();

        // By category
        $stmt = $db->prepare("SELECT COALESCE(category_name,'Outros') as name, SUM(amount) as total FROM expenses WHERE user_id=? AND date >= ? AND date <= ? GROUP BY name ORDER BY total DESC");
        $stmt->execute([$userId, $startDate, $endDate]);
        $byCategory = $stmt->fetchAll();

        // Best month
        $stmt = $db->prepare("SELECT CAST(strftime('%m', date) AS INTEGER) as month, SUM(amount) as total FROM incomes WHERE user_id=? AND date >= ? AND date <= ? GROUP BY month ORDER BY total DESC LIMIT 1");
        $stmt->execute([$userId, $startDate, $endDate]);
        $bestMonth = $stmt->fetch() ?: null;

        // Previous year growth
        $prevStart = ($year - 1) . '-01-01';
        $prevEnd = ($year - 1) . '-12-31';
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as total FROM incomes WHERE user_id=? AND date >= ? AND date <= ?");
        $stmt->execute([$userId, $prevStart, $prevEnd]);
        $prevInc = floatval($stmt->fetch()['total']);
        $growth = $prevInc > 0 ? round(($incTotal - $prevInc) / $prevInc * 100, 1) : 0;

        jsonResponse([
            'income_total' => $incTotal,
            'income_count' => intval($incStats['count']),
            'expense_total' => $expTotal,
            'expense_count' => intval($expStats['count']),
            'balance' => $incTotal - $expTotal,
            'avg_monthly_income' => round($incTotal / 12, 2),
            'avg_monthly_expense' => round($expTotal / 12, 2),
            'best_month' => $bestMonth,
            'growth' => $growth,
            'monthly_income' => $monthlyIncome,
            'monthly_expenses' => $monthlyExpenses,
            'by_source' => $bySource,
            'by_category' => $byCategory
        ]);
        break;

    default:
        jsonError('Ação inválida', 404);
}
