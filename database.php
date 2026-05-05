<?php
// includes/database.php — SQLite database layer

define('DB_PATH', __DIR__ . '/../data/finflow.db');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
    
    init_schema($pdo);
    return $pdo;
}

function init_schema(PDO $db): void {
    $db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        full_name TEXT NOT NULL DEFAULT '',
        email TEXT DEFAULT '',
        role TEXT NOT NULL DEFAULT 'user',
        avatar_color TEXT DEFAULT '#4f46e5',
        currency TEXT DEFAULT '₹',
        business_name TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME
    );

    CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        inv_number TEXT NOT NULL,
        inv_title TEXT DEFAULT 'INVOICE',
        inv_date DATE NOT NULL,
        due_date DATE,
        inv_terms TEXT DEFAULT 'On Receipt',
        from_name TEXT DEFAULT '',
        from_biz TEXT DEFAULT '',
        from_addr TEXT DEFAULT '',
        from_email TEXT DEFAULT '',
        to_name TEXT DEFAULT '',
        to_street TEXT DEFAULT '',
        to_city TEXT DEFAULT '',
        to_phone TEXT DEFAULT '',
        to_email TEXT DEFAULT '',
        currency TEXT DEFAULT '₹',
        tax_pct REAL DEFAULT 0,
        disc_pct REAL DEFAULT 0,
        items_json TEXT DEFAULT '[]',
        pays_json TEXT DEFAULT '[]',
        notes TEXT DEFAULT '',
        logo_data TEXT DEFAULT '',
        sign_data TEXT DEFAULT '',
        sign_date TEXT DEFAULT '',
        subtotal REAL DEFAULT 0,
        tax_amt REAL DEFAULT 0,
        disc_amt REAL DEFAULT 0,
        total_amt REAL DEFAULT 0,
        paid_amt REAL DEFAULT 0,
        balance_due REAL DEFAULT 0,
        status TEXT DEFAULT 'unpaid',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id),
        UNIQUE(user_id, inv_number)
    );

    CREATE TABLE IF NOT EXISTS expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        exp_type TEXT DEFAULT 'expense',
        exp_number TEXT DEFAULT '',
        exp_date DATE NOT NULL,
        period_from DATE,
        period_to DATE,
        payee_name TEXT DEFAULT '',
        payee_role TEXT DEFAULT '',
        payee_address TEXT DEFAULT '',
        company_name TEXT DEFAULT '',
        category TEXT DEFAULT 'General',
        currency TEXT DEFAULT '₹',
        items_json TEXT DEFAULT '[]',
        deductions_json TEXT DEFAULT '[]',
        gross_amt REAL DEFAULT 0,
        deduction_amt REAL DEFAULT 0,
        net_amt REAL DEFAULT 0,
        pay_method TEXT DEFAULT '',
        pay_ref TEXT DEFAULT '',
        notes TEXT DEFAULT '',
        status TEXT DEFAULT 'paid',
        bank_name TEXT DEFAULT '',
        account_no TEXT DEFAULT '',
        ifsc TEXT DEFAULT '',
        pan TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    );

    CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        txn_date DATE NOT NULL,
        txn_type TEXT NOT NULL,
        category TEXT DEFAULT 'Other',
        description TEXT NOT NULL,
        amount REAL NOT NULL,
        currency TEXT DEFAULT '₹',
        pay_method TEXT DEFAULT '',
        reference TEXT DEFAULT '',
        notes TEXT DEFAULT '',
        linked_invoice_id INTEGER,
        linked_expense_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    );

    CREATE TABLE IF NOT EXISTS contacts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        contact_type TEXT NOT NULL,
        name TEXT NOT NULL,
        role TEXT DEFAULT '',
        email TEXT DEFAULT '',
        phone TEXT DEFAULT '',
        address TEXT DEFAULT '',
        company TEXT DEFAULT '',
        bank_name TEXT DEFAULT '',
        account_no TEXT DEFAULT '',
        ifsc TEXT DEFAULT '',
        pan TEXT DEFAULT '',
        base_rate REAL DEFAULT 0,
        currency TEXT DEFAULT '₹',
        notes TEXT DEFAULT '',
        logo_data TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    );

    CREATE TABLE IF NOT EXISTS settings (
        user_id INTEGER NOT NULL,
        key TEXT NOT NULL,
        value TEXT DEFAULT '',
        PRIMARY KEY(user_id, key),
        FOREIGN KEY(user_id) REFERENCES users(id)
    );
    ");
    
    // Seed default admin if no users exist
    $count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count == 0) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $db->exec("INSERT INTO users (username,password,full_name,role,business_name) VALUES ('admin','$hash','Administrator','admin','My Business')");
    }
}

// ── Helpers ──
function db_query(string $sql, array $params = []): array {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_get(string $sql, array $params = []): ?array {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    $r = $stmt->fetch();
    return $r ?: null;
}

function db_run(string $sql, array $params = []): int {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return (int) get_db()->lastInsertId();
}

function db_exec(string $sql, array $params = []): int {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}
