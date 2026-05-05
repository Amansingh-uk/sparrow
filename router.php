<?php
// api/router.php — handles all POST/AJAX calls

require_once __DIR__ . '/../includes/auth.php';

session_start_safe();
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Public actions
if ($action === 'login') {
    if (login($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        json_response(['ok' => true]);
    } else {
        json_response(['error' => 'Invalid username or password'], 401);
    }
}

if ($action === 'logout') { logout(); }

// Protected from here
$user = current_user();
if (!$user) json_response(['error' => 'Not authenticated'], 401);
$uid = $user['id'];

verify_csrf();

switch ($action) {

// ── INVOICES ──────────────────────────────────────────
case 'save_invoice':
    $id = (int)($_POST['id'] ?? 0);
    $num = trim($_POST['inv_number'] ?? '');
    if (!$num) json_response(['error' => 'Invoice number required'], 400);
    
    // Duplicate check
    $dup = db_get("SELECT id FROM invoices WHERE user_id=? AND inv_number=? AND id!=?", [$uid, $num, $id]);
    if ($dup) json_response(['error' => "Invoice # '$num' already exists"], 409);

    $items = json_decode($_POST['items_json'] ?? '[]', true) ?: [];
    $pays  = json_decode($_POST['pays_json']  ?? '[]', true) ?: [];
    $sub   = (float)($_POST['subtotal']    ?? 0);
    $tax   = (float)($_POST['tax_amt']     ?? 0);
    $disc  = (float)($_POST['disc_amt']    ?? 0);
    $total = (float)($_POST['total_amt']   ?? 0);
    $paid  = (float)($_POST['paid_amt']    ?? 0);
    $bal   = (float)($_POST['balance_due'] ?? 0);
    $status = $total > 0 && $bal <= 0 ? 'paid' : ($paid > 0 && $bal > 0 ? 'partial' : 'unpaid');

    $fields = [
        'inv_number'  => $num,
        'inv_title'   => $_POST['inv_title']   ?? 'INVOICE',
        'inv_date'    => $_POST['inv_date']    ?? date('Y-m-d'),
        'due_date'    => $_POST['due_date']    ?? null,
        'inv_terms'   => $_POST['inv_terms']   ?? 'On Receipt',
        'from_name'   => $_POST['from_name']   ?? '',
        'from_biz'    => $_POST['from_biz']    ?? '',
        'from_addr'   => $_POST['from_addr']   ?? '',
        'from_email'  => $_POST['from_email']  ?? '',
        'to_name'     => $_POST['to_name']     ?? '',
        'to_street'   => $_POST['to_street']   ?? '',
        'to_city'     => $_POST['to_city']     ?? '',
        'to_phone'    => $_POST['to_phone']    ?? '',
        'to_email'    => $_POST['to_email']    ?? '',
        'currency'    => $_POST['currency']    ?? '₹',
        'tax_pct'     => (float)($_POST['tax_pct']  ?? 0),
        'disc_pct'    => (float)($_POST['disc_pct'] ?? 0),
        'items_json'  => json_encode($items),
        'pays_json'   => json_encode($pays),
        'notes'       => $_POST['notes']       ?? '',
        'logo_data'   => $_POST['logo_data']   ?? '',
        'sign_data'   => $_POST['sign_data']   ?? '',
        'sign_date'   => $_POST['sign_date']   ?? '',
        'subtotal'    => $sub, 'tax_amt' => $tax, 'disc_amt' => $disc,
        'total_amt'   => $total, 'paid_amt' => $paid, 'balance_due' => $bal,
        'status'      => $status,
    ];

    if ($id > 0) {
        $sets = implode(',', array_map(fn($k) => "$k=:$k", array_keys($fields)));
        db_exec("UPDATE invoices SET $sets WHERE id=:id AND user_id=:uid",
            array_merge($fields, ['id' => $id, 'uid' => $uid]));
        json_response(['ok' => true, 'id' => $id, 'msg' => 'Invoice updated!']);
    } else {
        $fields['user_id'] = $uid;
        $cols = implode(',', array_keys($fields));
        $vals = ':' . implode(',:', array_keys($fields));
        $id = db_run("INSERT INTO invoices ($cols) VALUES ($vals)", $fields);
        json_response(['ok' => true, 'id' => $id, 'msg' => 'Invoice saved!']);
    }

case 'delete_invoice':
    db_exec("DELETE FROM invoices WHERE id=? AND user_id=?", [(int)$_POST['id'], $uid]);
    json_response(['ok' => true]);

case 'get_invoice':
    $row = db_get("SELECT * FROM invoices WHERE id=? AND user_id=?", [(int)($_POST['id'] ?? 0), $uid]);
    if (!$row) json_response(['error' => 'Not found'], 404);
    $row['items_decoded']    = json_decode($row['items_json'], true) ?: [];
    $row['pays_decoded']     = json_decode($row['pays_json'],  true) ?: [];
    json_response(['ok' => true, 'data' => $row]);

case 'check_invoice_number':
    $num = trim($_POST['inv_number'] ?? '');
    $id  = (int)($_POST['id'] ?? 0);
    $dup = db_get("SELECT id FROM invoices WHERE user_id=? AND inv_number=? AND id!=?", [$uid, $num, $id]);
    json_response(['taken' => (bool)$dup]);

// ── EXPENSES ──────────────────────────────────────────
case 'save_expense':
    $id = (int)($_POST['id'] ?? 0);
    $items  = json_decode($_POST['items_json']      ?? '[]', true) ?: [];
    $deducts = json_decode($_POST['deductions_json'] ?? '[]', true) ?: [];
    $fields = [
        'exp_type'        => $_POST['exp_type']      ?? 'expense',
        'exp_number'      => $_POST['exp_number']    ?? '',
        'exp_date'        => $_POST['exp_date']      ?? date('Y-m-d'),
        'period_from'     => $_POST['period_from']   ?? null,
        'period_to'       => $_POST['period_to']     ?? null,
        'payee_name'      => $_POST['payee_name']    ?? '',
        'payee_role'      => $_POST['payee_role']    ?? '',
        'payee_address'   => $_POST['payee_address'] ?? '',
        'company_name'    => $_POST['company_name']  ?? '',
        'category'        => $_POST['category']      ?? 'General',
        'currency'        => $_POST['currency']      ?? '₹',
        'items_json'      => json_encode($items),
        'deductions_json' => json_encode($deducts),
        'gross_amt'       => (float)($_POST['gross_amt']     ?? 0),
        'deduction_amt'   => (float)($_POST['deduction_amt'] ?? 0),
        'net_amt'         => (float)($_POST['net_amt']       ?? 0),
        'pay_method'      => $_POST['pay_method']    ?? '',
        'pay_ref'         => $_POST['pay_ref']       ?? '',
        'notes'           => $_POST['notes']         ?? '',
        'status'          => $_POST['status']        ?? 'paid',
        'bank_name'       => $_POST['bank_name']     ?? '',
        'account_no'      => $_POST['account_no']    ?? '',
        'ifsc'            => $_POST['ifsc']           ?? '',
        'pan'             => $_POST['pan']            ?? '',
    ];

    if ($id > 0) {
        $sets = implode(',', array_map(fn($k) => "$k=:$k", array_keys($fields)));
        db_exec("UPDATE expenses SET $sets WHERE id=:id AND user_id=:uid",
            array_merge($fields, ['id' => $id, 'uid' => $uid]));
        json_response(['ok' => true, 'id' => $id, 'msg' => 'Expense updated!']);
    } else {
        $fields['user_id'] = $uid;
        $cols = implode(',', array_keys($fields));
        $vals = ':' . implode(',:', array_keys($fields));
        $id = db_run("INSERT INTO expenses ($cols) VALUES ($vals)", $fields);
        json_response(['ok' => true, 'id' => $id, 'msg' => 'Expense saved!']);
    }

case 'delete_expense':
    db_exec("DELETE FROM expenses WHERE id=? AND user_id=?", [(int)$_POST['id'], $uid]);
    json_response(['ok' => true]);

case 'get_expense':
    $row = db_get("SELECT * FROM expenses WHERE id=? AND user_id=?", [(int)($_POST['id'] ?? 0), $uid]);
    if (!$row) json_response(['error' => 'Not found'], 404);
    $row['items_decoded']      = json_decode($row['items_json'],      true) ?: [];
    $row['deductions_decoded'] = json_decode($row['deductions_json'], true) ?: [];
    json_response(['ok' => true, 'data' => $row]);

// ── TRANSACTIONS ──────────────────────────────────────
case 'save_transaction':
    $id = (int)($_POST['id'] ?? 0);
    $fields = [
        'txn_date'    => $_POST['txn_date']    ?? date('Y-m-d'),
        'txn_type'    => $_POST['txn_type']    ?? 'income',
        'category'    => $_POST['category']    ?? 'Other',
        'description' => $_POST['description'] ?? '',
        'amount'      => (float)($_POST['amount'] ?? 0),
        'currency'    => $_POST['currency']    ?? '₹',
        'pay_method'  => $_POST['pay_method']  ?? '',
        'reference'   => $_POST['reference']   ?? '',
        'notes'       => $_POST['notes']       ?? '',
    ];
    if (!$fields['description']) json_response(['error' => 'Description required'], 400);

    if ($id > 0) {
        $sets = implode(',', array_map(fn($k) => "$k=:$k", array_keys($fields)));
        db_exec("UPDATE transactions SET $sets WHERE id=:id AND user_id=:uid",
            array_merge($fields, ['id' => $id, 'uid' => $uid]));
        json_response(['ok' => true, 'id' => $id, 'msg' => 'Transaction updated!']);
    } else {
        $fields['user_id'] = $uid;
        $cols = implode(',', array_keys($fields));
        $vals = ':' . implode(',:', array_keys($fields));
        $id = db_run("INSERT INTO transactions ($cols) VALUES ($vals)", $fields);
        json_response(['ok' => true, 'id' => $id, 'msg' => 'Transaction saved!']);
    }

case 'delete_transaction':
    db_exec("DELETE FROM transactions WHERE id=? AND user_id=?", [(int)$_POST['id'], $uid]);
    json_response(['ok' => true]);

// ── CONTACTS ──────────────────────────────────────────
case 'save_contact':
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    if (!$name) json_response(['error' => 'Name required'], 400);
    $fields = [
        'contact_type' => $_POST['contact_type'] ?? 'client',
        'name'         => $name,
        'role'         => $_POST['role']         ?? '',
        'email'        => $_POST['email']        ?? '',
        'phone'        => $_POST['phone']        ?? '',
        'address'      => $_POST['address']      ?? '',
        'company'      => $_POST['company']      ?? '',
        'bank_name'    => $_POST['bank_name']    ?? '',
        'account_no'   => $_POST['account_no']   ?? '',
        'ifsc'         => $_POST['ifsc']         ?? '',
        'pan'          => $_POST['pan']          ?? '',
        'base_rate'    => (float)($_POST['base_rate'] ?? 0),
        'currency'     => $_POST['currency']     ?? '₹',
        'notes'        => $_POST['notes']        ?? '',
        'logo_data'    => $_POST['logo_data']    ?? '',
    ];
    if ($id > 0) {
        $sets = implode(',', array_map(fn($k) => "$k=:$k", array_keys($fields)));
        db_exec("UPDATE contacts SET $sets WHERE id=:id AND user_id=:uid",
            array_merge($fields, ['id' => $id, 'uid' => $uid]));
    } else {
        $fields['user_id'] = $uid;
        $cols = implode(',', array_keys($fields));
        $vals = ':' . implode(',:', array_keys($fields));
        $id = db_run("INSERT INTO contacts ($cols) VALUES ($vals)", $fields);
    }
    $all = db_query("SELECT * FROM contacts WHERE user_id=? AND contact_type=? ORDER BY name",
        [$uid, $fields['contact_type']]);
    json_response(['ok' => true, 'id' => $id, 'contacts' => $all]);

case 'delete_contact':
    db_exec("DELETE FROM contacts WHERE id=? AND user_id=?", [(int)$_POST['id'], $uid]);
    json_response(['ok' => true]);

// ── USERS (admin only) ────────────────────────────────
case 'save_user':
    if ($user['role'] !== 'admin') json_response(['error' => 'No permission'], 403);
    $id = (int)($_POST['id'] ?? 0);
    $uname = trim($_POST['username'] ?? '');
    $fname = trim($_POST['full_name'] ?? '');
    if (!$uname || !$fname) json_response(['error' => 'Username and name required'], 400);

    if ($id > 0) {
        $fields = ['full_name' => $fname, 'email' => $_POST['email'] ?? '',
                   'role' => $_POST['role'] ?? 'user', 'business_name' => $_POST['business_name'] ?? ''];
        if (!empty($_POST['password'])) {
            $fields['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
        }
        $sets = implode(',', array_map(fn($k) => "$k=:$k", array_keys($fields)));
        db_exec("UPDATE users SET $sets WHERE id=:id", array_merge($fields, ['id' => $id]));
        json_response(['ok' => true, 'msg' => 'User updated!']);
    } else {
        $pass = $_POST['password'] ?? '';
        if (!$pass) json_response(['error' => 'Password required for new user'], 400);
        $dup = db_get("SELECT id FROM users WHERE username=?", [$uname]);
        if ($dup) json_response(['error' => "Username '$uname' already taken"], 409);
        $id = db_run("INSERT INTO users (username,password,full_name,email,role,business_name) VALUES (?,?,?,?,?,?)",
            [$uname, password_hash($pass, PASSWORD_BCRYPT), $fname,
             $_POST['email'] ?? '', $_POST['role'] ?? 'user', $_POST['business_name'] ?? '']);
        json_response(['ok' => true, 'id' => $id, 'msg' => 'User created!']);
    }

case 'delete_user':
    if ($user['role'] !== 'admin') json_response(['error' => 'No permission'], 403);
    $del_id = (int)$_POST['id'];
    if ($del_id === $uid) json_response(['error' => "Can't delete yourself"], 400);
    db_exec("DELETE FROM users WHERE id=?", [$del_id]);
    json_response(['ok' => true]);

case 'update_profile':
    $fields = ['full_name' => trim($_POST['full_name'] ?? $user['full_name']),
               'email' => $_POST['email'] ?? '', 'business_name' => $_POST['business_name'] ?? '',
               'currency' => $_POST['currency'] ?? '₹'];
    if (!empty($_POST['password'])) {
        $fields['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
    }
    $sets = implode(',', array_map(fn($k) => "$k=:$k", array_keys($fields)));
    db_exec("UPDATE users SET $sets WHERE id=:id", array_merge($fields, ['id' => $uid]));
    json_response(['ok' => true, 'msg' => 'Profile saved!']);

// ── ANALYTICS DATA ────────────────────────────────────
case 'get_dashboard_stats':
    $year  = (int)($_POST['year']  ?? date('Y'));
    $month = (int)($_POST['month'] ?? 0);

    $date_filter = $month > 0
        ? "AND strftime('%Y-%m', inv_date) = '" . sprintf('%04d-%02d', $year, $month) . "'"
        : "AND strftime('%Y', inv_date) = '$year'";

    $inv_stats = db_get("SELECT
        COUNT(*) as total,
        COALESCE(SUM(total_amt),0) as revenue,
        COALESCE(SUM(paid_amt),0) as collected,
        COALESCE(SUM(balance_due),0) as outstanding,
        COUNT(CASE WHEN status='paid' THEN 1 END) as paid_count,
        COUNT(CASE WHEN status='unpaid' THEN 1 END) as unpaid_count,
        COUNT(CASE WHEN status='partial' THEN 1 END) as partial_count
        FROM invoices WHERE user_id=? $date_filter", [$uid]);

    $exp_date_filter = $month > 0
        ? "AND strftime('%Y-%m', exp_date) = '" . sprintf('%04d-%02d', $year, $month) . "'"
        : "AND strftime('%Y', exp_date) = '$year'";

    $exp_stats = db_get("SELECT
        COUNT(*) as total,
        COALESCE(SUM(net_amt),0) as total_expenses,
        COALESCE(SUM(CASE WHEN exp_type='salary' THEN net_amt ELSE 0 END),0) as salary_total,
        COALESCE(SUM(CASE WHEN exp_type='expense' THEN net_amt ELSE 0 END),0) as other_total
        FROM expenses WHERE user_id=? $exp_date_filter", [$uid]);

    $txn_date_filter = $month > 0
        ? "AND strftime('%Y-%m', txn_date) = '" . sprintf('%04d-%02d', $year, $month) . "'"
        : "AND strftime('%Y', txn_date) = '$year'";

    $txn_stats = db_get("SELECT
        COALESCE(SUM(CASE WHEN txn_type='income' THEN amount ELSE 0 END),0) as total_income,
        COALESCE(SUM(CASE WHEN txn_type='expense' THEN amount ELSE 0 END),0) as total_expense
        FROM transactions WHERE user_id=? $txn_date_filter", [$uid]);

    // Monthly chart data (last 12 months)
    $monthly = db_query("SELECT
        strftime('%Y-%m', inv_date) as month,
        COALESCE(SUM(total_amt),0) as revenue,
        COALESCE(SUM(paid_amt),0) as collected
        FROM invoices WHERE user_id=? AND inv_date >= date('now','-12 months')
        GROUP BY month ORDER BY month", [$uid]);

    $monthly_exp = db_query("SELECT
        strftime('%Y-%m', exp_date) as month,
        COALESCE(SUM(net_amt),0) as expenses
        FROM expenses WHERE user_id=? AND exp_date >= date('now','-12 months')
        GROUP BY month ORDER BY month", [$uid]);

    // P&L calculation
    $revenue   = (float)($inv_stats['collected'] ?? 0);
    $expenses  = (float)($exp_stats['total_expenses'] ?? 0);
    $profit    = $revenue - $expenses;

    // Top clients
    $top_clients = db_query("SELECT to_name as name, COUNT(*) as invoices,
        SUM(total_amt) as total, SUM(paid_amt) as paid
        FROM invoices WHERE user_id=? AND to_name!='' $date_filter
        GROUP BY to_name ORDER BY total DESC LIMIT 5", [$uid]);

    // Recent invoices
    $recent_invoices = db_query("SELECT id,inv_number,to_name,inv_date,total_amt,balance_due,status,currency
        FROM invoices WHERE user_id=? ORDER BY created_at DESC LIMIT 8", [$uid]);

    // Recent expenses
    $recent_expenses = db_query("SELECT id,exp_type,exp_number,payee_name,exp_date,net_amt,status,currency
        FROM expenses WHERE user_id=? ORDER BY created_at DESC LIMIT 8", [$uid]);

    json_response(['ok' => true, 'data' => compact(
        'inv_stats','exp_stats','txn_stats','monthly','monthly_exp',
        'profit','revenue','expenses','top_clients','recent_invoices','recent_expenses'
    )]);

default:
    json_response(['error' => "Unknown action: $action"], 400);
}
