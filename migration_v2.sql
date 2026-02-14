-- =====================================================
-- Migração V2: Gastos + Perfis de Usuário
-- =====================================================

USE financeiro_pessoal;

-- 1. Modificar tabela users: remover senha, adicionar avatar
ALTER TABLE users 
    ADD COLUMN avatar VARCHAR(10) DEFAULT '👤' AFTER name,
    MODIFY COLUMN password_hash VARCHAR(255) DEFAULT NULL;

-- 2. Tabela de categorias de gasto
CREATE TABLE IF NOT EXISTS expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50) DEFAULT '💸',
    color VARCHAR(20) DEFAULT '#ef4444',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Tabela de gastos
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    date DATE NOT NULL,
    category_id INT DEFAULT NULL,
    category_name VARCHAR(100) DEFAULT NULL,
    observation TEXT DEFAULT NULL,
    tags VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL,
    INDEX idx_exp_user_date (user_id, date),
    INDEX idx_exp_user_cat (user_id, category_id)
) ENGINE=InnoDB;

-- 4. Atualizar avatar do usuário existente
UPDATE users SET avatar = '😎' WHERE username = 'alisson';
UPDATE users SET avatar = '👤' WHERE avatar IS NULL;

-- 5. Inserir categorias de gasto padrão para usuários existentes
INSERT INTO expense_categories (user_id, name, icon, color)
SELECT id, 'Mercado', '🛒', '#ef4444' FROM users
WHERE id NOT IN (SELECT DISTINCT user_id FROM expense_categories WHERE name = 'Mercado');

INSERT INTO expense_categories (user_id, name, icon, color)
SELECT id, 'Gasolina', '⛽', '#f97316' FROM users
WHERE id NOT IN (SELECT DISTINCT user_id FROM expense_categories WHERE name = 'Gasolina');

INSERT INTO expense_categories (user_id, name, icon, color)
SELECT id, 'Alimentação', '🍔', '#eab308' FROM users
WHERE id NOT IN (SELECT DISTINCT user_id FROM expense_categories WHERE name = 'Alimentação');

INSERT INTO expense_categories (user_id, name, icon, color)
SELECT id, 'Contas', '📄', '#8b5cf6' FROM users
WHERE id NOT IN (SELECT DISTINCT user_id FROM expense_categories WHERE name = 'Contas');

INSERT INTO expense_categories (user_id, name, icon, color)
SELECT id, 'Transporte', '🚌', '#3b82f6' FROM users
WHERE id NOT IN (SELECT DISTINCT user_id FROM expense_categories WHERE name = 'Transporte');

INSERT INTO expense_categories (user_id, name, icon, color)
SELECT id, 'Lazer', '🎮', '#ec4899' FROM users
WHERE id NOT IN (SELECT DISTINCT user_id FROM expense_categories WHERE name = 'Lazer');

INSERT INTO expense_categories (user_id, name, icon, color)
SELECT id, 'Saúde', '💊', '#14b8a6' FROM users
WHERE id NOT IN (SELECT DISTINCT user_id FROM expense_categories WHERE name = 'Saúde');

INSERT INTO expense_categories (user_id, name, icon, color)
SELECT id, 'Outros', '💸', '#6b7280' FROM users
WHERE id NOT IN (SELECT DISTINCT user_id FROM expense_categories WHERE name = 'Outros');
