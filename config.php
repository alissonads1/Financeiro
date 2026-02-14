<?php
// =====================================================
// Configuração do Sistema Financeiro Pessoal
// =====================================================

// Capturar erros PHP e retornar como JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});

session_start();

// Definir caminho do banco com fallback inteligente
$defaultPath = __DIR__ . '/data/financeiro.db';
$dbPath = getenv('DB_PATH') ?: $defaultPath;
$dir = dirname($dbPath);

// Tenta garantir que o diretório exista e seja gravável
if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
    @chmod($dir, 0777);
}

// Se não conseguir escrever no diretório padrão, usa /tmp (funciona no Render)
if (!is_writable($dir) && !file_exists($dbPath)) {
    $tmpDir = sys_get_temp_dir();
    if (is_writable($tmpDir)) {
        $dbPath = $tmpDir . '/financeiro.db';
    }
}

define('DB_PATH', $dbPath);

// Conexão PDO SQLite
function getDB()
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dir = dirname(DB_PATH);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            $pdo = new PDO(
                "sqlite:" . DB_PATH,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            // Otimizações SQLite
            $pdo->exec("PRAGMA journal_mode=WAL");
            $pdo->exec("PRAGMA foreign_keys=ON");

            // Auto-migrate: criar tabelas se não existirem
            autoMigrate($pdo);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Erro de conexão: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    return $pdo;
}

function autoMigrate($pdo)
{
    // Verificar se tabela users existe
    $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
    
    if (!$check) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                name TEXT NOT NULL,
                avatar TEXT DEFAULT '👤',
                pin TEXT DEFAULT NULL,
                password_hash TEXT DEFAULT NULL,
                created_at TEXT DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS income_sources (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                icon TEXT DEFAULT '💰',
                color TEXT DEFAULT '#6366f1',
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS incomes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                amount REAL NOT NULL,
                date TEXT NOT NULL,
                source_id INTEGER DEFAULT NULL,
                source_name TEXT DEFAULT NULL,
                type TEXT DEFAULT 'entrada',
                observation TEXT DEFAULT NULL,
                tags TEXT DEFAULT NULL,
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (source_id) REFERENCES income_sources(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS expense_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                icon TEXT DEFAULT '💸',
                color TEXT DEFAULT '#ef4444',
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS expenses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                amount REAL NOT NULL,
                date TEXT NOT NULL,
                category_id INTEGER DEFAULT NULL,
                category_name TEXT DEFAULT NULL,
                observation TEXT DEFAULT NULL,
                tags TEXT DEFAULT NULL,
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS goals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT DEFAULT NULL,
                name TEXT DEFAULT NULL,
                icon TEXT DEFAULT '🎯',
                color TEXT DEFAULT '#6366f1',
                target_amount REAL NOT NULL,
                current_amount REAL DEFAULT 0,
                deposit_value REAL DEFAULT 0,
                deadline TEXT DEFAULT NULL,
                status TEXT DEFAULT 'active',
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ");
    }

    // Verificar se coluna PIN existe (para migração de bases antigas)
    $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('pin', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN pin TEXT DEFAULT NULL");
    }
}

// Verificar autenticação
function requireAuth()
{
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Não autorizado']);
        exit;
    }
    return $_SESSION['user_id'];
}

// Response helpers
function jsonResponse($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($message, $code = 400)
{
    jsonResponse(['error' => $message], $code);
}
