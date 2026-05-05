<?php
require_once __DIR__ . '/includes/auth.php';

session_start_safe();

$page = $_GET['page'] ?? 'dashboard';

// Handle logout
if ($page === 'logout') logout();

// Handle API calls
if ($page === 'api') {
    require_once __DIR__ . '/api/router.php';
    exit;
}

// Login page — no auth needed
if ($page === 'login') {
    require_once __DIR__ . '/pages/login.php';
    exit;
}

// Everything else needs auth
$user = require_login();

// Page routing
$pages = ['dashboard','invoices','invoice-new','invoice-edit','expenses','expense-new','expense-edit',
          'transactions','contacts','reports','settings','users','profile'];

if (!in_array($page, $pages)) $page = 'dashboard';

// Load contacts for dropdowns
$businesses = db_query("SELECT * FROM contacts WHERE user_id=? AND contact_type='business' ORDER BY name", [$user['id']]);
$clients    = db_query("SELECT * FROM contacts WHERE user_id=? AND contact_type='client'   ORDER BY name", [$user['id']]);
$employees  = db_query("SELECT * FROM contacts WHERE user_id=? AND contact_type IN ('employee','editor','freelance','vendor') ORDER BY name", [$user['id']]);

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>FinFlow — <?= safe(ucfirst(str_replace('-',' ',$page))) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:ital,wght@0,300;0,400;0,500;1,400&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet"/>
<style>
:root {
  --bg:       #0a0a0f;
  --bg2:      #111118;
  --bg3:      #1a1a24;
  --border:   rgba(255,255,255,0.07);
  --border2:  rgba(255,255,255,0.12);
  --text:     #f0f0f8;
  --text2:    #9090a8;
  --text3:    #5a5a72;
  --accent:   #6c63ff;
  --accent2:  #9d96ff;
  --green:    #22c55e;
  --red:      #ef4444;
  --yellow:   #f59e0b;
  --blue:     #3b82f6;
  --orange:   #f97316;
  --sidebar-w: 240px;
  --header-h:  60px;
  --radius:   12px;
  --radius-sm: 7px;
  --shadow:   0 4px 24px rgba(0,0,0,.4);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); font-size: 14px; line-height: 1.5; overflow-x: hidden; }

/* ── LAYOUT ── */
.app { display: flex; height: 100vh; overflow: hidden; }

/* ── SIDEBAR ── */
.sidebar {
  width: var(--sidebar-w); flex-shrink: 0;
  background: var(--bg2); border-right: 1px solid var(--border);
  display: flex; flex-direction: column; overflow-y: auto; overflow-x: hidden;
  transition: transform .2s;
}
.sidebar-logo { padding: 20px 20px 12px; border-bottom: 1px solid var(--border); }
.sidebar-logo .logo-mark { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 20px; color: var(--text); letter-spacing: -.5px; display: flex; align-items: center; gap: 8px; }
.sidebar-logo .logo-mark span { background: var(--accent); color: #fff; width: 28px; height: 28px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.sidebar-logo .biz-name { font-size: 11px; color: var(--text3); margin-top: 2px; font-family: 'DM Mono', monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.nav-section { padding: 16px 12px 4px; }
.nav-section-label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .1em; color: var(--text3); padding: 0 8px; margin-bottom: 4px; }
.nav-item { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 8px; cursor: pointer; color: var(--text2); font-size: 13px; font-weight: 500; text-decoration: none; transition: all .15s; white-space: nowrap; }
.nav-item:hover { background: var(--bg3); color: var(--text); }
.nav-item.active { background: rgba(108,99,255,.15); color: var(--accent2); }
.nav-item .icon { width: 18px; height: 18px; opacity: .8; flex-shrink: 0; }
.nav-item.active .icon { opacity: 1; }
.nav-badge { margin-left: auto; background: var(--accent); color: #fff; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 20px; }

.sidebar-footer { margin-top: auto; padding: 12px; border-top: 1px solid var(--border); }
.user-pill { display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: var(--radius-sm); cursor: pointer; }
.user-pill:hover { background: var(--bg3); }
.avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--accent); display: flex; align-items: center; justify-content: center; font-family: 'Syne',sans-serif; font-weight: 700; font-size: 13px; color: #fff; flex-shrink: 0; }
.user-info { overflow: hidden; }
.user-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-role { font-size: 11px; color: var(--text3); text-transform: capitalize; }

/* ── MAIN ── */
.main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.topbar { height: var(--header-h); border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 0 24px; gap: 16px; background: var(--bg2); flex-shrink: 0; }
.topbar-title { font-family: 'Syne',sans-serif; font-weight: 700; font-size: 17px; flex: 1; }
.topbar-actions { display: flex; align-items: center; gap: 10px; }
.content { flex: 1; overflow-y: auto; padding: 24px; }

/* ── BUTTONS ── */
.btn { display: inline-flex; align-items: center; gap: 7px; padding: 8px 16px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 500; font-family: inherit; cursor: pointer; border: none; transition: all .15s; text-decoration: none; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover { background: #5a52e0; }
.btn-secondary { background: var(--bg3); color: var(--text); border: 1px solid var(--border2); }
.btn-secondary:hover { background: var(--bg3); border-color: var(--border2); filter: brightness(1.2); }
.btn-danger { background: rgba(239,68,68,.15); color: var(--red); border: 1px solid rgba(239,68,68,.2); }
.btn-danger:hover { background: rgba(239,68,68,.25); }
.btn-success { background: rgba(34,197,94,.15); color: var(--green); border: 1px solid rgba(34,197,94,.2); }
.btn-sm { padding: 5px 12px; font-size: 12px; }
.btn-icon { padding: 7px; border-radius: var(--radius-sm); }

/* ── CARDS ── */
.card { background: var(--bg2); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; }
.card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.card-title { font-family: 'Syne',sans-serif; font-weight: 600; font-size: 14px; }

/* ── STAT CARDS ── */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 14px; margin-bottom: 20px; }
.stat-card { background: var(--bg2); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 20px; position: relative; overflow: hidden; }
.stat-card::before { content:''; position:absolute; inset:0; opacity:.05; background: radial-gradient(circle at 100% 0%, var(--c) 0%, transparent 60%); }
.stat-card[data-color="green"]  { --c: var(--green); }
.stat-card[data-color="red"]    { --c: var(--red); }
.stat-card[data-color="blue"]   { --c: var(--blue); }
.stat-card[data-color="purple"] { --c: var(--accent); }
.stat-card[data-color="yellow"] { --c: var(--yellow); }
.stat-card[data-color="orange"] { --c: var(--orange); }
.stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--text3); font-weight: 600; margin-bottom: 8px; }
.stat-value { font-family: 'Syne',sans-serif; font-size: 24px; font-weight: 700; margin-bottom: 4px; }
.stat-sub { font-size: 12px; color: var(--text2); }
.stat-icon { position: absolute; right: 16px; top: 16px; opacity: .15; font-size: 28px; }

/* ── TABLE ── */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
thead th { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text3); padding: 10px 14px; border-bottom: 1px solid var(--border); text-align: left; white-space: nowrap; }
tbody td { padding: 12px 14px; border-bottom: 1px solid var(--border); font-size: 13px; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: rgba(255,255,255,.02); }
.mono { font-family: 'DM Mono', monospace; font-size: 12px; }

/* ── BADGES ── */
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-paid    { background: rgba(34,197,94,.15);  color: var(--green); }
.badge-unpaid  { background: rgba(239,68,68,.15);  color: var(--red); }
.badge-partial { background: rgba(245,158,11,.15); color: var(--yellow); }
.badge-pending { background: rgba(245,158,11,.15); color: var(--yellow); }
.badge-income  { background: rgba(34,197,94,.12);  color: var(--green); }
.badge-expense { background: rgba(239,68,68,.12);  color: var(--red); }
.badge-salary  { background: rgba(59,130,246,.12); color: var(--blue); }
.badge-freelance{ background: rgba(249,115,22,.12); color: var(--orange); }
.badge-transfer{ background: rgba(108,99,255,.12); color: var(--accent2); }
.badge-other   { background: rgba(144,144,168,.1); color: var(--text2); }

/* ── FORMS ── */
.form-grid { display: grid; gap: 14px; }
.form-grid-2 { grid-template-columns: 1fr 1fr; }
.form-grid-3 { grid-template-columns: 1fr 1fr 1fr; }
.form-grid-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text3); }
.field input, .field select, .field textarea {
  background: var(--bg3); border: 1px solid var(--border2); border-radius: var(--radius-sm);
  color: var(--text); font-family: inherit; font-size: 13px; padding: 9px 12px; outline: none;
  transition: border-color .15s;
}
.field input:focus, .field select:focus, .field textarea:focus { border-color: var(--accent); }
.field input::placeholder, .field textarea::placeholder { color: var(--text3); }
.field textarea { resize: vertical; min-height: 64px; }
.field select option { background: var(--bg2); }
.field.span-2 { grid-column: span 2; }
.field.span-3 { grid-column: span 3; }
.field.span-4 { grid-column: span 4; }

/* ── ITEMS TABLE in forms ── */
.items-table { width: 100%; border-collapse: collapse; }
.items-table th { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text3); padding: 6px 8px; border-bottom: 1px solid var(--border); text-align: left; }
.items-table td { padding: 5px 4px; vertical-align: middle; }
.items-table input, .items-table select { background: var(--bg3); border: 1px solid var(--border); border-radius: 5px; color: var(--text); font-size: 13px; padding: 6px 8px; width: 100%; font-family: inherit; }
.items-table input:focus, .items-table select:focus { border-color: var(--accent); outline: none; }
.add-row-btn { background: none; border: 1px dashed var(--border2); border-radius: var(--radius-sm); padding: 6px 14px; color: var(--text3); font-size: 12px; cursor: pointer; font-family: inherit; margin-top: 8px; }
.add-row-btn:hover { border-color: var(--accent); color: var(--accent2); }
.del-row-btn { background: none; border: none; color: var(--text3); cursor: pointer; font-size: 16px; padding: 4px 6px; border-radius: 4px; }
.del-row-btn:hover { color: var(--red); background: rgba(239,68,68,.1); }

/* ── MODAL ── */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.7); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; backdrop-filter: blur(4px); }
.modal-overlay.hidden { display: none; }
.modal { background: var(--bg2); border: 1px solid var(--border2); border-radius: 16px; max-width: 720px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.5); }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid var(--border); position: sticky; top: 0; background: var(--bg2); z-index: 1; }
.modal-title { font-family: 'Syne',sans-serif; font-weight: 700; font-size: 16px; }
.modal-close { background: none; border: none; color: var(--text2); cursor: pointer; font-size: 20px; border-radius: 6px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; }
.modal-close:hover { background: var(--bg3); color: var(--text); }
.modal-body { padding: 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; gap: 10px; justify-content: flex-end; }

/* ── NOTIFICATIONS ── */
.notif { position: fixed; top: 20px; right: 20px; z-index: 2000; display: flex; flex-direction: column; gap: 8px; }
.notif-item { background: var(--bg2); border: 1px solid var(--border2); border-radius: 10px; padding: 12px 16px; font-size: 13px; font-weight: 500; max-width: 320px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 10px; animation: slideIn .2s ease; }
.notif-item.success { border-color: rgba(34,197,94,.3); }
.notif-item.error   { border-color: rgba(239,68,68,.3); color: var(--red); }
@keyframes slideIn { from { transform: translateX(20px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

/* ── SEARCH BAR ── */
.search-bar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
.search-bar input, .search-bar select { background: var(--bg2); border: 1px solid var(--border2); border-radius: var(--radius-sm); color: var(--text); font-family: inherit; font-size: 13px; padding: 8px 12px; outline: none; }
.search-bar input:focus, .search-bar select:focus { border-color: var(--accent); }
.search-bar input { flex: 1; min-width: 200px; }

/* ── CHART PLACEHOLDER ── */
.chart-container { position: relative; height: 220px; }

/* ── P&L SECTION ── */
.pl-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border); }
.pl-row:last-child { border-bottom: none; }
.pl-row.total { font-weight: 700; font-size: 15px; border-top: 1px solid var(--border2); padding-top: 14px; margin-top: 4px; }
.pl-row.profit { color: var(--green); }
.pl-row.loss   { color: var(--red); }

/* ── EMPTY STATE ── */
.empty-state { text-align: center; padding: 60px 20px; }
.empty-state .icon { font-size: 48px; margin-bottom: 16px; opacity: .3; }
.empty-state h3 { font-family: 'Syne',sans-serif; font-size: 16px; font-weight: 600; margin-bottom: 8px; color: var(--text2); }
.empty-state p { font-size: 13px; color: var(--text3); }

/* ── DIVIDER ── */
.divider { height: 1px; background: var(--border); margin: 16px 0; }
.section-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--text3); margin-bottom: 12px; }

/* ── TABS ── */
.tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: 20px; }
.tab { padding: 10px 16px; font-size: 13px; font-weight: 500; color: var(--text2); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: all .15s; }
.tab:hover { color: var(--text); }
.tab.active { color: var(--accent2); border-bottom-color: var(--accent); }

/* ── PAGE HEADER ── */
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.page-header h1 { font-family: 'Syne',sans-serif; font-weight: 700; font-size: 22px; }
.page-header-sub { color: var(--text3); font-size: 13px; margin-top: 2px; }

/* ── QUICK STATS ROW ── */
.qs-row { display: flex; gap: 10px; flex-wrap: wrap; }
.qs-item { display: flex; align-items: center; gap: 8px; background: var(--bg3); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 8px 14px; }
.qs-dot { width: 8px; height: 8px; border-radius: 50%; }
.qs-label { font-size: 12px; color: var(--text2); }
.qs-val { font-family: 'DM Mono',monospace; font-size: 13px; font-weight: 500; }

/* ── RESPONSIVE ── */
@media(max-width:900px){
  .sidebar { position: fixed; left: 0; top: 0; bottom: 0; z-index: 100; transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); }
  .content { padding: 16px; }
  .form-grid-2,.form-grid-3,.form-grid-4 { grid-template-columns: 1fr; }
  .field.span-2,.field.span-3,.field.span-4 { grid-column: span 1; }
}
</style>
</head>
<body>
<div class="app">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark"><span>F</span>FinFlow</div>
    <div class="biz-name"><?= safe($user['business_name'] ?: $user['full_name']) ?></div>
  </div>

  <div class="nav-section">
    <div class="nav-section-label">Overview</div>
    <a href="?page=dashboard" class="nav-item <?= $page==='dashboard'?'active':'' ?>">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Dashboard
    </a>
    <a href="?page=reports" class="nav-item <?= $page==='reports'?'active':'' ?>">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 4-4"/></svg>
      P&L Reports
    </a>
  </div>

  <div class="nav-section">
    <div class="nav-section-label">Finance</div>
    <a href="?page=invoices" class="nav-item <?= in_array($page,['invoices','invoice-new','invoice-edit'])?'active':'' ?>">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Invoices
    </a>
    <a href="?page=expenses" class="nav-item <?= in_array($page,['expenses','expense-new','expense-edit'])?'active':'' ?>">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18v-2m0-8V6"/></svg>
      Expenses & Salary
    </a>
    <a href="?page=transactions" class="nav-item <?= $page==='transactions'?'active':'' ?>">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 16V4m0 0L3 8m4-4l4 4"/><path d="M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
      Transactions
    </a>
  </div>

  <div class="nav-section">
    <div class="nav-section-label">Management</div>
    <a href="?page=contacts" class="nav-item <?= $page==='contacts'?'active':'' ?>">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Contacts
    </a>
    <?php if($user['role']==='admin'): ?>
    <a href="?page=users" class="nav-item <?= $page==='users'?'active':'' ?>">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      User Management
    </a>
    <?php endif; ?>
    <a href="?page=settings" class="nav-item <?= $page==='settings'?'active':'' ?>">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      Settings
    </a>
  </div>

  <div class="sidebar-footer">
    <a href="?page=profile" class="user-pill" style="text-decoration:none;color:inherit">
      <div class="avatar" style="background:<?= safe($user['avatar_color'] ?? '#6c63ff') ?>"><?= strtoupper(substr($user['full_name']?:$user['username'],0,1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= safe($user['full_name'] ?: $user['username']) ?></div>
        <div class="user-role"><?= safe($user['role']) ?></div>
      </div>
    </a>
    <a href="?page=logout" style="display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:var(--radius-sm);color:var(--text3);font-size:12px;text-decoration:none;margin-top:4px">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Sign Out
    </a>
  </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main">
  <div class="topbar">
    <button onclick="document.getElementById('sidebar').classList.toggle('open')" style="display:none;background:none;border:none;color:var(--text);cursor:pointer;padding:4px" id="menu-btn">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="topbar-title" id="page-title"><?= safe(ucwords(str_replace('-',' ',$page))) ?></div>
    <div class="topbar-actions">
      <span style="font-size:12px;color:var(--text3);font-family:'DM Mono',monospace"><?= safe($user['currency'] ?? '₹') ?></span>
      <a href="?page=invoice-new" class="btn btn-primary btn-sm">+ New Invoice</a>
    </div>
  </div>
  <div class="content" id="main-content">
    <?php
    $page_file = __DIR__ . '/pages/' . $page . '.php';
    if (file_exists($page_file)) {
        include $page_file;
    } else {
        echo '<div class="empty-state"><div class="icon">🚧</div><h3>Page coming soon</h3><p>This section is under construction.</p></div>';
    }
    ?>
  </div>
</main>
</div>

<!-- NOTIFICATIONS CONTAINER -->
<div class="notif" id="notif-container"></div>

<script>
const CSRF = <?= json_encode($csrf) ?>;
const USER = <?= json_encode(['id'=>$user['id'],'role'=>$user['role'],'currency'=>$user['currency']??'₹']) ?>;
const API  = '?page=api';

// ── API helper ──
async function api(action, data={}) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('csrf', CSRF);
    for (const [k,v] of Object.entries(data)) fd.append(k, v ?? '');
    const res = await fetch(API, {method:'POST', body:fd});
    return res.json();
}

// ── Notifications ──
function notify(msg, type='success', duration=3500) {
    const el = document.createElement('div');
    el.className = `notif-item ${type}`;
    el.innerHTML = `<span>${type==='success'?'✓':'✗'}</span> ${msg}`;
    document.getElementById('notif-container').appendChild(el);
    setTimeout(() => el.remove(), duration);
}

// ── Confirm delete ──
async function confirmDelete(msg='Delete this record?') {
    return new Promise(resolve => {
        if (confirm(msg)) resolve(true); else resolve(false);
    });
}

// ── Format money ──
function fmtMoney(n, sym='₹') { return sym + parseFloat(n||0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,','); }
function fmtDate(d) { if(!d) return '—'; const dt=new Date(d); return dt.toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'}); }

// ── Modal helpers ──
function openModal(id) { document.getElementById(id)?.classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

// ── Mobile menu ──
if (window.innerWidth < 900) {
    document.getElementById('menu-btn').style.display = 'flex';
}
window.addEventListener('resize', () => {
    document.getElementById('menu-btn').style.display = window.innerWidth < 900 ? 'flex' : 'none';
});

// Close sidebar on outside click
document.addEventListener('click', e => {
    const sb = document.getElementById('sidebar');
    if (window.innerWidth < 900 && sb.classList.contains('open') && !sb.contains(e.target) && e.target.id !== 'menu-btn') {
        sb.classList.remove('open');
    }
});
</script>
<?php include __DIR__ . '/pages/_scripts.php'; ?>
</body>
</html>
