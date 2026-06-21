<?php
/**
 * FinanceAI — REST API Endpoints
 * Handles CRUD, filtering, export, and live updates.
 */
header('X-Content-Type-Options: nosniff');

require 'config.php';

$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── Helper: JSON response ───────────────────────────────────────────────────
function json_ok($data = null, $msg = 'OK') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok', 'message' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function json_err($msg, $code = 400) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Build WHERE clause from filters ─────────────────────────────────────────
function build_filters($db) {
    $where  = [];
    $params = [];
    $types  = '';

    $type = $_GET['type'] ?? 'all';
    if (in_array($type, ['income', 'expense'])) {
        $where[] = 'type=?';
        $params[] = $type;
        $types .= 's';
    }

    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $where[] = '(description LIKE ? OR category LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= 'ss';
    }

    $date_from = trim($_GET['date_from'] ?? '');
    $date_to   = trim($_GET['date_to'] ?? '');
    if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $where[] = 'DATE(created_at) >= ?';
        $params[] = $date_from;
        $types .= 's';
    }
    if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $where[] = 'DATE(created_at) <= ?';
        $params[] = $date_to;
        $types .= 's';
    }

    // Quick presets: today, week, month, year
    $preset = $_GET['preset'] ?? '';
    if ($preset !== '') {
        switch ($preset) {
            case 'today':
                $where[] = 'DATE(created_at) = CURDATE()';
                break;
            case 'week':
                $where[] = 'YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)';
                break;
            case 'month':
                $where[] = 'MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())';
                break;
            case 'year':
                $where[] = 'YEAR(created_at) = YEAR(CURDATE())';
                break;
        }
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    return [$where_sql, $params, $types];
}

// ── ROUTER ──────────────────────────────────────────────────────────────────
switch ($action) {

    // ── ADD transaction ─────────────────────────────────────────────────────
    case 'add':
        if ($method !== 'POST') json_err('Method not allowed', 405);

        $desc     = trim($_POST['description'] ?? '');
        $amount   = floatval($_POST['amount'] ?? 0);
        $type     = $_POST['type'] ?? '';
        $category = trim($_POST['category'] ?? '');

        if ($desc === '' || $amount <= 0) json_err('Deskripsi dan nominal wajib diisi.');
        if (!in_array($type, ['income', 'expense'])) json_err('Tipe harus income atau expense.');

        $allowed = ['makan','minum','transport','hiburan','income','tagihan','belanja','lainnya'];
        if (!in_array($category, $allowed)) json_err('Kategori tidak valid.');

        $stmt = $db->prepare("INSERT INTO transactions (description, amount, type, category) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdss", $desc, $amount, $type, $category);
        $stmt->execute();
        $new_id = $stmt->insert_id;
        $stmt->close();

        // Return the new row + updated summary
        $row = $db->prepare("SELECT * FROM transactions WHERE id=?");
        $row->bind_param("i", $new_id);
        $row->execute();
        $new_row = $row->get_result()->fetch_assoc();
        $row->close();

        $summary = get_summary_data($db);
        json_ok(['transaction' => $new_row, 'summary' => $summary], 'Transaksi berhasil ditambahkan!');
        break;

    // ── EDIT transaction ────────────────────────────────────────────────────
    case 'edit':
        if ($method !== 'POST') json_err('Method not allowed', 405);

        $id       = intval($_POST['id'] ?? 0);
        $desc     = trim($_POST['description'] ?? '');
        $amount   = floatval($_POST['amount'] ?? 0);
        $type     = $_POST['type'] ?? '';
        $category = trim($_POST['category'] ?? '');

        if ($id <= 0) json_err('ID tidak valid.');
        if ($desc === '' || $amount <= 0) json_err('Deskripsi dan nominal wajib diisi.');
        if (!in_array($type, ['income', 'expense'])) json_err('Tipe harus income atau expense.');

        $allowed = ['makan','minum','transport','hiburan','income','tagihan','belanja','lainnya'];
        if (!in_array($category, $allowed)) json_err('Kategori tidak valid.');

        $stmt = $db->prepare("UPDATE transactions SET description=?, amount=?, type=?, category=? WHERE id=?");
        $stmt->bind_param("sdssi", $desc, $amount, $type, $category, $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) json_err('Transaksi tidak ditemukan.', 404);

        $row = $db->prepare("SELECT * FROM transactions WHERE id=?");
        $row->bind_param("i", $id);
        $row->execute();
        $updated = $row->get_result()->fetch_assoc();
        $row->close();

        $summary = get_summary_data($db);
        json_ok(['transaction' => $updated, 'summary' => $summary], 'Transaksi berhasil diperbarui!');
        break;

    // ── DELETE transaction ──────────────────────────────────────────────────
    case 'delete':
        if ($method !== 'POST') json_err('Method not allowed', 405);

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) json_err('ID tidak valid.');

        $stmt = $db->prepare("DELETE FROM transactions WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) json_err('Transaksi tidak ditemukan.', 404);

        $summary = get_summary_data($db);
        json_ok(['id' => $id, 'summary' => $summary], 'Transaksi berhasil dihapus!');
        break;

    // ── GET single transaction ──────────────────────────────────────────────
    case 'get':
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) json_err('ID tidak valid.');

        $stmt = $db->prepare("SELECT * FROM transactions WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) json_err('Transaksi tidak ditemukan.', 404);
        json_ok($row);
        break;

    // ── GET summary stats (with filters) ────────────────────────────────────
    case 'summary':
        list($where_sql, $params, $types) = build_filters($db);

        $sql = "SELECT
            SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS total_income,
            SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS total_expense,
            COUNT(*) AS total_tx,
            COUNT(DISTINCT category) AS total_cat
        FROM transactions $where_sql";

        $stmt = $db->prepare($sql);
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $balance = ($stats['total_income'] ?? 0) - ($stats['total_expense'] ?? 0);
        json_ok([
            'balance'       => $balance,
            'total_income'  => floatval($stats['total_income'] ?? 0),
            'total_expense' => floatval($stats['total_expense'] ?? 0),
            'total_tx'      => intval($stats['total_tx'] ?? 0),
            'total_cat'     => intval($stats['total_cat'] ?? 0),
        ]);
        break;

    // ── LIST transactions (with pagination, filters, date range) ────────────
    case 'list':
        $page     = max(1, intval($_GET['page'] ?? 1));
        $per_page = min(100, max(1, intval($_GET['per_page'] ?? 50)));
        $offset   = ($page - 1) * $per_page;

        list($where_sql, $params, $types) = build_filters($db);

        // Count
        $count_sql = "SELECT COUNT(*) as cnt FROM transactions $where_sql";
        $stmt = $db->prepare($count_sql);
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Data
        $data_sql = "SELECT * FROM transactions $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $stmt = $db->prepare($data_sql);
        $all_types = $types . 'ii';
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->bind_param($all_types, ...$all_params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        json_ok([
            'data'       => $rows,
            'total'      => intval($total),
            'page'       => $page,
            'per_page'   => $per_page,
            'total_pages' => ceil($total / $per_page),
        ]);
        break;

    // ── CATEGORY breakdown (with filters) ───────────────────────────────────
    case 'categories':
        list($where_sql, $params, $types) = build_filters($db);

        // Add type=expense filter
        $extra = $where_sql ? "$where_sql AND type='expense'" : "WHERE type='expense'";
        $sql = "SELECT category, COUNT(*) as cnt, SUM(amount) as total
                FROM transactions $extra
                GROUP BY category ORDER BY total DESC";

        $stmt = $db->prepare($sql);
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $cats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        json_ok($cats);
        break;

    // ── MONTHLY chart data (with filters) ───────────────────────────────────
    case 'monthly':
        list($where_sql, $params, $types) = build_filters($db);

        $sql = "SELECT
            DATE_FORMAT(created_at, '%b') as month,
            DATE_FORMAT(created_at, '%Y%m') as ym,
            SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS inc,
            SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS exp
        FROM transactions $where_sql
        GROUP BY ym, month ORDER BY ym DESC LIMIT 6";

        $stmt = $db->prepare($sql);
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $months = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        json_ok(array_reverse($months));
        break;

    // ── EXPORT CSV ──────────────────────────────────────────────────────────
    case 'export':
        list($where_sql, $params, $types) = build_filters($db);

        $sql = "SELECT id, description, amount, type, category, created_at
                FROM transactions $where_sql ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // CSV output
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="finance_export_' . date('Y-m-d_His') . '.csv"');

        $output = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['ID', 'Deskripsi', 'Nominal', 'Tipe', 'Kategori', 'Waktu']);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['description'],
                $row['amount'],
                $row['type'],
                $row['category'],
                $row['created_at'],
            ]);
        }
        fclose($output);
        exit;

    default:
        json_err('Action tidak dikenali.', 404);
}

// ── Helper: get summary data (unfiltered, for live updates) ─────────────────
function get_summary_data($db) {
    $stats = $db->query("
        SELECT
            SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS total_income,
            SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS total_expense,
            COUNT(*) AS total_tx,
            COUNT(DISTINCT category) AS total_cat
        FROM transactions
    ")->fetch_assoc();

    $balance = ($stats['total_income'] ?? 0) - ($stats['total_expense'] ?? 0);
    return [
        'balance'       => $balance,
        'total_income'  => floatval($stats['total_income'] ?? 0),
        'total_expense' => floatval($stats['total_expense'] ?? 0),
        'total_tx'      => intval($stats['total_tx'] ?? 0),
        'total_cat'     => intval($stats['total_cat'] ?? 0),
    ];
}
