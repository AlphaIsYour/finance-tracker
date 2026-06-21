<?php
require 'config.php';
$db = get_db();

// ── Summary stats (prepared — no user input) ────────────────────────────────
$stats = $db->query("
    SELECT
        SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS total_income,
        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS total_expense,
        COUNT(*) AS total_tx,
        COUNT(DISTINCT category) AS total_cat
    FROM transactions
")->fetch_assoc();

$balance       = ($stats['total_income'] ?? 0) - ($stats['total_expense'] ?? 0);
$total_income  = $stats['total_income']  ?? 0;
$total_expense = $stats['total_expense'] ?? 0;
$total_tx      = $stats['total_tx']      ?? 0;

// ── Category breakdown ──────────────────────────────────────────────────────
$cat_rows = $db->query("
    SELECT category, COUNT(*) as cnt, SUM(amount) as total
    FROM transactions WHERE type='expense'
    GROUP BY category ORDER BY total DESC
");
$cats = [];
$max_cat_total = 0;
while ($c = $cat_rows->fetch_assoc()) {
    $cats[] = $c;
    if ($c['total'] > $max_cat_total) $max_cat_total = $c['total'];
}

// ── Monthly chart data ──────────────────────────────────────────────────────
$monthly = $db->query("
    SELECT
        DATE_FORMAT(created_at, '%b') as month,
        DATE_FORMAT(created_at, '%Y%m') as ym,
        SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS inc,
        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS exp
    FROM transactions
    GROUP BY ym, month ORDER BY ym DESC LIMIT 6
");
$months = [];
while ($m = $monthly->fetch_assoc()) $months[] = $m;
$months = array_reverse($months);
$max_monthly = 1;
foreach ($months as $m) {
    if ($m['inc'] > $max_monthly) $max_monthly = $m['inc'];
    if ($m['exp'] > $max_monthly) $max_monthly = $m['exp'];
}

// ── Transactions list (safe filter) ─────────────────────────────────────────
$filter_type = $_GET['type'] ?? 'all';
$allowed_filters = ['all', 'income', 'expense'];
if (!in_array($filter_type, $allowed_filters)) $filter_type = 'all';

if ($filter_type !== 'all') {
    $stmt = $db->prepare("SELECT * FROM transactions WHERE type=? ORDER BY created_at DESC LIMIT 50");
    $stmt->bind_param("s", $filter_type);
    $stmt->execute();
    $transactions = $stmt->get_result();
} else {
    $transactions = $db->query("SELECT * FROM transactions ORDER BY created_at DESC LIMIT 50");
}

// ── Icon & color maps ───────────────────────────────────────────────────────
$icons = [
    'makan'=>'utensils','minum'=>'coffee','transport'=>'car','hiburan'=>'film',
    'income'=>'trending-up','tagihan'=>'receipt','belanja'=>'shopping-bag','lainnya'=>'package',
];
$cat_colors = [
    'makan'=>'#D12828','minum'=>'#0E2A5C','transport'=>'#d97706','hiburan'=>'#7c3aed',
    'income'=>'#16a34a','tagihan'=>'#dc2626','belanja'=>'#0891b2','lainnya'=>'#333333',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FinanceAI — Dashboard</title>
  <meta name="description" content="AI Finance Tracker — Catat keuanganmu lewat WhatsApp, dashboard sketch-note style.">
  <meta property="og:title" content="FinanceAI Dashboard">
  <meta property="og:description" content="AI-powered finance tracker with hand-drawn notebook aesthetic.">
  <meta property="og:type" content="website">

  <!-- Google Fonts: Patrick Hand + Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Patrick+Hand&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
  /* ═══════════════════════════════════════════════════════════════════════════
     FinanceAI — Hand-Drawn Sketch-Note Theme
     ═══════════════════════════════════════════════════════════════════════════ */

  /* ── Design Tokens ──────────────────────────────────────────────────────── */
  :root {
    --paper-note: #F4F1EA;
    --paper-cream: #FAF8F3;
    --paper-line: #C8D3E0;
    --ink-blue: #0E2A5C;
    --pencil-grey: #333333;
    --alert-red: #D12828;
    --highlight-yellow: #FFFFA5;
    --ballpoint-blue: #0000FF;
    --font-hand: 'Patrick Hand', cursive;
    --font-ui: 'Inter', system-ui, sans-serif;
    --shadow-sketch: 2px 3px 0px rgba(14,42,92,0.12);
    --shadow-tape: 0 1px 3px rgba(0,0,0,0.15), inset 0 0 0 1px rgba(255,255,255,0.3);
    --radius-sketch: 3px;
    --transition: all 0.2s ease;
  }

  /* ── Reset ──────────────────────────────────────────────────────────────── */
  *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
  html { font-size: 15px; scroll-behavior: smooth; }
  body {
    font-family: var(--font-hand);
    background-color: var(--paper-note);
    color: var(--ink-blue);
    background-image:
      linear-gradient(var(--paper-line) 1px, transparent 1px);
    background-size: 100% 1.5em;
    background-attachment: local;
    min-height: 100vh;
    line-height: 1.5em;
    -webkit-font-smoothing: antialiased;
  }
  a { text-decoration: none; color: inherit; }
  button { font-family: inherit; cursor: pointer; border: none; background: none; }
  ul, ol { list-style: none; }

  /* ── Paper Grain Overlay ────────────────────────────────────────────────── */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.03'/%3E%3C/svg%3E");
    pointer-events: none;
    z-index: 9999;
  }

  /* ── Spiral Binding (left edge) ─────────────────────────────────────────── */
  body::after {
    content: '';
    position: fixed;
    left: 0;
    top: 0;
    width: 40px;
    height: 100%;
    background: repeating-linear-gradient(
      to bottom,
      transparent 0px,
      transparent 22px,
      #888 22px,
      #888 24px,
      transparent 24px,
      transparent 26px,
      #aaa 26px,
      #aaa 27px,
      transparent 27px,
      transparent 48px
    );
    z-index: 100;
    pointer-events: none;
    opacity: 0.5;
  }

  /* ── Coffee Ring Stain (decorative) ─────────────────────────────────────── */
  .coffee-ring {
    position: absolute;
    width: 90px;
    height: 90px;
    border-radius: 50%;
    border: 3px solid rgba(139, 90, 43, 0.08);
    pointer-events: none;
    z-index: 1;
  }
  .coffee-ring::before {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 2px solid rgba(139, 90, 43, 0.04);
  }

  /* ══ LAYOUT ═══════════════════════════════════════════════════════════════ */
  .app-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px 24px 20px 60px;
    position: relative;
  }

  /* ══ NAVBAR ════════════════════════════════════════════════════════════════ */
  .navbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    margin-bottom: 24px;
    background: var(--paper-cream);
    border: 2px solid var(--ink-blue);
    border-radius: var(--radius-sketch);
    box-shadow: var(--shadow-sketch);
    position: relative;
  }
  .navbar::before {
    content: '';
    position: absolute;
    top: -6px;
    left: 30px;
    width: 60px;
    height: 18px;
    background: rgba(255,255,165,0.7);
    transform: rotate(-2deg);
    box-shadow: var(--shadow-tape);
    border-radius: 2px;
  }
  .nav-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--ink-blue);
  }
  .nav-logo svg { width: 28px; height: 28px; }
  .nav-links {
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .nav-link {
    padding: 6px 14px;
    font-size: 1rem;
    border-radius: var(--radius-sketch);
    transition: var(--transition);
    cursor: pointer;
    position: relative;
  }
  .nav-link:hover, .nav-link.active {
    background: rgba(14,42,92,0.08);
    color: var(--alert-red);
  }
  .nav-link.active::after {
    content: '';
    position: absolute;
    bottom: 2px;
    left: 10%;
    width: 80%;
    height: 2px;
    background: var(--alert-red);
    border-radius: 1px;
  }
  .nav-cta {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    background: var(--alert-red);
    color: #fff;
    border: 2px solid var(--ink-blue);
    border-radius: var(--radius-sketch);
    font-size: 1rem;
    font-family: var(--font-hand);
    box-shadow: var(--shadow-sketch);
    cursor: pointer;
    transition: var(--transition);
  }
  .nav-cta:hover {
    transform: translate(-1px, -1px);
    box-shadow: 3px 4px 0px rgba(14,42,92,0.2);
  }
  .nav-cta:active {
    transform: translate(1px, 1px);
    box-shadow: 1px 1px 0px rgba(14,42,92,0.15);
  }
  .nav-cta svg { width: 16px; height: 16px; }

  /* ── Mobile hamburger ───────────────────────────────────────────────────── */
  .nav-hamburger {
    display: none;
    flex-direction: column;
    gap: 4px;
    cursor: pointer;
    padding: 4px;
  }
  .nav-hamburger span {
    display: block;
    width: 22px;
    height: 2px;
    background: var(--ink-blue);
    border-radius: 1px;
    transition: var(--transition);
  }

  /* ══ PAGE HEADER ═══════════════════════════════════════════════════════════ */
  .page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 20px;
    gap: 16px;
    flex-wrap: wrap;
  }
  .page-title {
    font-size: 2rem;
    color: var(--ink-blue);
    line-height: 1.2;
    position: relative;
  }
  .page-title .underline-sketch {
    display: block;
    height: 6px;
    background: linear-gradient(90deg, transparent, var(--highlight-yellow), transparent);
    margin-top: 2px;
    border-radius: 3px;
    opacity: 0.7;
  }
  .page-subtitle {
    font-size: 1.1rem;
    color: var(--pencil-grey);
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .page-subtitle svg { width: 16px; height: 16px; opacity: 0.6; }

  /* ── Filter tabs ────────────────────────────────────────────────────────── */
  .filter-tabs {
    display: flex;
    gap: 4px;
    background: var(--paper-cream);
    border: 2px solid var(--pencil-grey);
    border-radius: var(--radius-sketch);
    padding: 3px;
    box-shadow: var(--shadow-sketch);
  }
  .filter-tab {
    padding: 6px 16px;
    border-radius: var(--radius-sketch);
    font-size: 1rem;
    font-family: var(--font-hand);
    color: var(--pencil-grey);
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
  }
  .filter-tab:hover { background: rgba(14,42,92,0.06); }
  .filter-tab.active {
    background: var(--ink-blue);
    color: #fff;
    box-shadow: var(--shadow-sketch);
  }

  /* ══ KPI CARDS ═════════════════════════════════════════════════════════════ */
  .kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
  }
  .kpi-card {
    background: var(--paper-cream);
    border: 2px solid var(--ink-blue);
    border-radius: var(--radius-sketch);
    padding: 18px 20px;
    position: relative;
    box-shadow: var(--shadow-sketch);
    transition: var(--transition);
    cursor: default;
    opacity: 0;
    transform: translateY(12px);
  }
  .kpi-card.kpi-enter {
    animation: kpi-reveal 0.4s ease forwards;
  }
  @keyframes kpi-reveal {
    to { opacity: 1; transform: translateY(0); }
  }
  .kpi-card:hover {
    transform: translateY(-3px) rotate(-0.5deg);
    box-shadow: 4px 5px 0px rgba(14,42,92,0.15);
  }
  /* Tape effect on each card */
  .kpi-card::before {
    content: '';
    position: absolute;
    top: -8px;
    right: 20px;
    width: 50px;
    height: 16px;
    background: rgba(255,255,165,0.65);
    transform: rotate(2deg);
    border-radius: 2px;
    box-shadow: var(--shadow-tape);
  }
  .kpi-card:nth-child(2)::before { background: rgba(209,40,40,0.15); transform: rotate(-1.5deg); right: auto; left: 15px; }
  .kpi-card:nth-child(3)::before { background: rgba(14,42,92,0.12); transform: rotate(3deg); }
  .kpi-card:nth-child(4)::before { background: rgba(0,0,255,0.1); transform: rotate(-2deg); right: auto; left: 20px; }

  .kpi-icon-wrap {
    width: 36px;
    height: 36px;
    border: 2px solid currentColor;
    border-radius: var(--radius-sketch);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
  }
  .kpi-icon-wrap svg { width: 18px; height: 18px; }
  .kpi-icon-wrap.emerald { color: #16a34a; background: rgba(22,163,74,0.08); }
  .kpi-icon-wrap.rose { color: var(--alert-red); background: rgba(209,40,40,0.08); }
  .kpi-icon-wrap.blue { color: var(--ink-blue); background: rgba(14,42,92,0.08); }

  .kpi-label {
    font-size: 0.85rem;
    color: var(--pencil-grey);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }
  .kpi-value {
    font-family: var(--font-ui);
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--ink-blue);
    letter-spacing: -0.02em;
  }
  .kpi-sub {
    font-size: 0.85rem;
    color: var(--pencil-grey);
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
  }
  .kpi-sub svg { width: 13px; height: 13px; }

  /* ══ DASHBOARD GRID ═══════════════════════════════════════════════════════ */
  .dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 18px;
    margin-bottom: 24px;
  }

  /* ══ PANELS ════════════════════════════════════════════════════════════════ */
  .panel {
    background: var(--paper-cream);
    border: 2px solid var(--ink-blue);
    border-radius: var(--radius-sketch);
    box-shadow: var(--shadow-sketch);
    overflow: hidden;
    transition: var(--transition);
    position: relative;
  }
  .panel:hover {
    box-shadow: 4px 5px 0px rgba(14,42,92,0.15);
  }
  .panel-header {
    padding: 14px 18px;
    border-bottom: 2px dashed rgba(14,42,92,0.15);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }
  .panel-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--ink-blue);
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .panel-title svg { width: 18px; height: 18px; opacity: 0.7; }
  .panel-title-count {
    font-size: 0.8rem;
    color: var(--pencil-grey);
    background: rgba(14,42,92,0.06);
    padding: 1px 8px;
    border-radius: 10px;
  }
  .panel-body { padding: 18px; }
  .panel-body--flush { padding: 0; }

  /* ══ CHART ═════════════════════════════════════════════════════════════════ */
  .chart-wrap { position: relative; }
  .chart-legend {
    display: flex;
    gap: 16px;
    margin-bottom: 14px;
  }
  .legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.9rem;
    color: var(--pencil-grey);
  }
  .legend-swatch {
    width: 12px;
    height: 12px;
    border: 2px solid var(--ink-blue);
    border-radius: 2px;
  }
  .legend-swatch.emerald { background: #16a34a; }
  .legend-swatch.rose { background: var(--alert-red); }

  .chart-area {
    height: 150px;
    display: flex;
    align-items: flex-end;
    gap: 10px;
    padding: 0 4px;
  }
  .chart-column {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    height: 100%;
  }
  .chart-bar-group {
    flex: 1;
    width: 100%;
    display: flex;
    gap: 4px;
    align-items: flex-end;
  }
  .chart-bar {
    flex: 1;
    border: 2px solid var(--ink-blue);
    border-radius: 3px 3px 0 0;
    cursor: pointer;
    min-height: 4px;
    transition: var(--transition);
    position: relative;
  }
  .chart-bar:hover {
    filter: brightness(1.1);
    transform: scaleY(1.05);
    transform-origin: bottom;
  }
  .chart-bar.income-bar { background: #16a34a; }
  .chart-bar.expense-bar { background: var(--alert-red); }
  .chart-label {
    font-size: 0.8rem;
    color: var(--pencil-grey);
    margin-top: 6px;
    font-weight: 600;
  }

  /* ══ CATEGORY LIST ════════════════════════════════════════════════════════ */
  .cat-list { display: flex; flex-direction: column; gap: 4px; }
  .cat-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: var(--radius-sketch);
    transition: var(--transition);
    cursor: default;
  }
  .cat-row:hover {
    background: rgba(14,42,92,0.04);
    transform: translateX(3px);
  }
  .cat-icon-wrap {
    width: 34px;
    height: 34px;
    border: 2px solid currentColor;
    border-radius: var(--radius-sketch);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .cat-icon-wrap svg { width: 16px; height: 16px; }
  .cat-info { flex: 1; min-width: 0; }
  .cat-name-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 5px;
  }
  .cat-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--ink-blue);
    text-transform: capitalize;
  }
  .cat-amount {
    font-family: var(--font-ui);
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--pencil-grey);
  }
  .cat-bar-track {
    height: 6px;
    background: rgba(14,42,92,0.08);
    border-radius: 3px;
    overflow: hidden;
    border: 1px solid rgba(14,42,92,0.1);
  }
  .cat-bar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.8s ease;
  }
  .cat-count {
    font-family: var(--font-ui);
    font-size: 0.75rem;
    color: var(--pencil-grey);
    background: rgba(14,42,92,0.06);
    padding: 2px 8px;
    border-radius: 10px;
    flex-shrink: 0;
  }

  /* ══ DONUT CHART ═══════════════════════════════════════════════════════════ */
  .donut-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 18px;
    position: relative;
  }
  .donut-svg { transform: rotate(-90deg); }
  .donut-segment {
    transition: all 0.6s ease;
    cursor: pointer;
    filter: drop-shadow(1px 1px 0px rgba(14,42,92,0.15));
  }
  .donut-segment:hover {
    filter: brightness(1.15) drop-shadow(2px 2px 0px rgba(14,42,92,0.2));
    transform-origin: center;
  }
  .donut-center {
    position: absolute;
    text-align: center;
    pointer-events: none;
  }
  .donut-center-label {
    font-size: 0.75rem;
    color: var(--pencil-grey);
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }
  .donut-center-value {
    font-family: var(--font-ui);
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--ink-blue);
  }

  /* ══ SEARCH BAR ═══════════════════════════════════════════════════════════ */
  .search-bar {
    position: relative;
    margin-bottom: 0;
  }
  .search-bar svg {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    color: var(--pencil-grey);
    opacity: 0.5;
    pointer-events: none;
  }
  .search-input {
    width: 100%;
    padding: 10px 14px 10px 36px;
    font-family: var(--font-hand);
    font-size: 1rem;
    color: var(--ink-blue);
    background: var(--paper-note);
    border: 2px solid rgba(14,42,92,0.15);
    border-radius: var(--radius-sketch);
    outline: none;
    transition: var(--transition);
  }
  .search-input:focus {
    border-color: var(--ink-blue);
    box-shadow: 0 0 0 3px rgba(14,42,92,0.1);
  }
  .search-input::placeholder { color: rgba(51,51,51,0.35); }
  .search-clear {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 24px;
    height: 24px;
    display: none;
    align-items: center;
    justify-content: center;
    border: none;
    background: rgba(14,42,92,0.08);
    border-radius: 50%;
    cursor: pointer;
    color: var(--pencil-grey);
    transition: var(--transition);
  }
  .search-clear:hover { background: rgba(209,40,40,0.15); color: var(--alert-red); }
  .search-clear svg { width: 12px; height: 12px; position: static; transform: none; opacity: 1; }
  .search-clear.visible { display: flex; }
  .search-count {
    font-size: 0.8rem;
    color: var(--pencil-grey);
    margin-left: 8px;
    white-space: nowrap;
  }

  /* ══ SPENDING INSIGHTS ════════════════════════════════════════════════════ */
  .insight-box {
    background: rgba(255,255,165,0.2);
    border: 2px dashed rgba(14,42,92,0.15);
    border-radius: var(--radius-sketch);
    padding: 14px 16px;
    margin-top: 14px;
    position: relative;
  }
  .insight-box::before {
    content: '';
    position: absolute;
    top: -8px;
    left: 16px;
    width: 45px;
    height: 14px;
    background: rgba(255,255,165,0.6);
    transform: rotate(-1.5deg);
    border-radius: 2px;
    box-shadow: var(--shadow-tape);
  }
  .insight-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--ink-blue);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .insight-title svg { width: 14px; height: 14px; }
  .insight-text {
    font-size: 0.95rem;
    color: var(--pencil-grey);
    line-height: 1.5;
  }
  .insight-text strong { color: var(--ink-blue); }

  /* ══ ROW STAGGER ══════════════════════════════════════════════════════════ */
  tbody tr {
    opacity: 0;
    animation: row-slide 0.3s ease forwards;
  }
  @keyframes row-slide {
    from { opacity: 0; transform: translateX(-8px); }
    to { opacity: 1; transform: translateX(0); }
  }
  /* Hidden rows for search filtering */
  tbody tr.row-hidden {
    display: none;
  }

  /* ══ TABLE ═════════════════════════════════════════════════════════════════ */
  .table-wrapper { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
  thead tr {
    background: rgba(14,42,92,0.05);
    border-bottom: 2px solid var(--ink-blue);
  }
  th {
    padding: 10px 16px;
    text-align: left;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--pencil-grey);
    font-weight: 700;
    white-space: nowrap;
  }
  tbody tr {
    border-bottom: 1px dashed rgba(14,42,92,0.12);
    transition: var(--transition);
  }
  tbody tr:hover { background: rgba(255,255,165,0.25); }
  td {
    padding: 12px 16px;
    color: var(--pencil-grey);
    vertical-align: middle;
  }
  .col-num {
    font-family: var(--font-ui);
    font-size: 0.75rem;
    color: rgba(51,51,51,0.5);
    width: 36px;
  }
  .col-desc {
    font-weight: 600;
    color: var(--ink-blue);
    max-width: 200px;
  }
  .col-desc span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .col-amount {
    font-family: var(--font-ui);
    font-weight: 600;
    font-size: 0.9rem;
  }
  .amount-income { color: #16a34a; }
  .amount-expense { color: var(--alert-red); }
  .col-time {
    font-family: var(--font-ui);
    font-size: 0.75rem;
    color: rgba(51,51,51,0.6);
    white-space: nowrap;
  }
  .col-actions {
    display: flex;
    gap: 4px;
  }
  .btn-icon {
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--pencil-grey);
    border-radius: var(--radius-sketch);
    cursor: pointer;
    transition: var(--transition);
    color: var(--pencil-grey);
    background: transparent;
  }
  .btn-icon svg { width: 14px; height: 14px; }
  .btn-icon:hover {
    background: var(--ink-blue);
    color: #fff;
    border-color: var(--ink-blue);
    transform: translate(-1px, -1px);
    box-shadow: 2px 2px 0px rgba(14,42,92,0.2);
  }
  .btn-icon.danger:hover {
    background: var(--alert-red);
    border-color: var(--alert-red);
  }

  /* ── Badges ─────────────────────────────────────────────────────────────── */
  .badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border: 2px solid currentColor;
    border-radius: var(--radius-sketch);
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
  }
  .badge svg { width: 12px; height: 12px; }
  .badge-income { color: #16a34a; background: rgba(22,163,74,0.08); }
  .badge-expense { color: var(--alert-red); background: rgba(209,40,40,0.08); }

  /* ══ BUTTONS ═══════════════════════════════════════════════════════════════ */
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: 2px solid var(--ink-blue);
    border-radius: var(--radius-sketch);
    font-size: 0.95rem;
    font-family: var(--font-hand);
    cursor: pointer;
    transition: var(--transition);
    white-space: nowrap;
    text-decoration: none;
    background: var(--paper-cream);
    color: var(--ink-blue);
    box-shadow: var(--shadow-sketch);
  }
  .btn:hover {
    transform: translate(-1px, -1px);
    box-shadow: 3px 4px 0px rgba(14,42,92,0.2);
  }
  .btn:active {
    transform: translate(1px, 1px);
    box-shadow: 1px 1px 0px rgba(14,42,92,0.15);
  }
  .btn svg { width: 16px; height: 16px; }
  .btn-primary {
    background: var(--alert-red);
    color: #fff;
    border-color: var(--ink-blue);
  }
  .btn-primary:hover { background: #b82222; }
  .btn-ghost {
    background: transparent;
    border-color: var(--pencil-grey);
  }
  .btn-ghost.active {
    background: var(--ink-blue);
    color: #fff;
    border-color: var(--ink-blue);
  }
  .btn-sm { padding: 5px 12px; font-size: 0.85rem; }

  /* ══ MODALS ════════════════════════════════════════════════════════════════ */
  .modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(14,42,92,0.3);
    backdrop-filter: blur(3px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  .modal-overlay.open { display: flex; }
  .modal {
    background: var(--paper-cream);
    border: 3px solid var(--ink-blue);
    border-radius: var(--radius-sketch);
    box-shadow: 6px 7px 0px rgba(14,42,92,0.2);
    width: 100%;
    max-width: 460px;
    position: relative;
    animation: modal-pop 0.25s ease;
  }
  @keyframes modal-pop {
    from { transform: scale(0.92) rotate(-1deg); opacity: 0; }
    to { transform: scale(1) rotate(0deg); opacity: 1; }
  }
  /* Tape on modal */
  .modal::before {
    content: '';
    position: absolute;
    top: -10px;
    left: 50%;
    transform: translateX(-50%) rotate(-1.5deg);
    width: 70px;
    height: 18px;
    background: rgba(255,255,165,0.7);
    border-radius: 2px;
    box-shadow: var(--shadow-tape);
    z-index: 1;
  }
  .modal-header {
    padding: 18px 22px 14px;
    border-bottom: 2px dashed rgba(14,42,92,0.15);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .modal-title {
    font-size: 1.3rem;
    color: var(--ink-blue);
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .modal-title svg { width: 20px; height: 20px; }
  .modal-close {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--pencil-grey);
    border-radius: var(--radius-sketch);
    cursor: pointer;
    transition: var(--transition);
    color: var(--pencil-grey);
  }
  .modal-close svg { width: 16px; height: 16px; }
  .modal-close:hover {
    background: var(--alert-red);
    color: #fff;
    border-color: var(--alert-red);
  }
  .modal-body { padding: 18px 22px; }
  .modal-footer {
    padding: 14px 22px 18px;
    display: flex;
    gap: 8px;
    justify-content: flex-end;
  }

  /* ── Form fields ────────────────────────────────────────────────────────── */
  .form-group {
    margin-bottom: 14px;
  }
  .form-label {
    display: block;
    font-size: 0.95rem;
    color: var(--ink-blue);
    margin-bottom: 4px;
    font-weight: 600;
  }
  .form-input, .form-select {
    width: 100%;
    padding: 10px 14px;
    font-family: var(--font-hand);
    font-size: 1.05rem;
    color: var(--ink-blue);
    background: var(--paper-note);
    border: 2px solid var(--pencil-grey);
    border-radius: var(--radius-sketch);
    outline: none;
    transition: var(--transition);
    background-image: linear-gradient(var(--paper-line) 1px, transparent 1px);
    background-size: 100% 1.5em;
    background-position: 0 8px;
  }
  .form-input:focus, .form-select:focus {
    border-color: var(--ink-blue);
    box-shadow: 0 0 0 3px rgba(14,42,92,0.12);
  }
  .form-input::placeholder { color: rgba(51,51,51,0.4); }
  .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }
  .form-error {
    font-size: 0.85rem;
    color: var(--alert-red);
    margin-top: 4px;
    display: none;
  }

  /* ── Type toggle ────────────────────────────────────────────────────────── */
  .type-toggle {
    display: flex;
    border: 2px solid var(--pencil-grey);
    border-radius: var(--radius-sketch);
    overflow: hidden;
  }
  .type-toggle-btn {
    flex: 1;
    padding: 8px 14px;
    font-family: var(--font-hand);
    font-size: 1rem;
    cursor: pointer;
    transition: var(--transition);
    text-align: center;
    background: transparent;
    color: var(--pencil-grey);
    border: none;
  }
  .type-toggle-btn:first-child { border-right: 2px solid var(--pencil-grey); }
  .type-toggle-btn.active-income {
    background: #16a34a;
    color: #fff;
  }
  .type-toggle-btn.active-expense {
    background: var(--alert-red);
    color: #fff;
  }

  /* ══ TOAST NOTIFICATIONS ══════════════════════════════════════════════════ */
  .toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 2000;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .toast {
    background: var(--paper-cream);
    border: 2px solid var(--ink-blue);
    border-radius: var(--radius-sketch);
    padding: 12px 18px;
    box-shadow: var(--shadow-sketch);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.95rem;
    color: var(--ink-blue);
    animation: toast-in 0.3s ease;
    min-width: 260px;
    max-width: 380px;
    position: relative;
  }
  .toast::before {
    content: '';
    position: absolute;
    top: -6px;
    right: 15px;
    width: 40px;
    height: 14px;
    background: rgba(255,255,165,0.6);
    transform: rotate(1.5deg);
    border-radius: 2px;
    box-shadow: var(--shadow-tape);
  }
  .toast svg { width: 18px; height: 18px; flex-shrink: 0; }
  .toast.success { border-left: 4px solid #16a34a; }
  .toast.success svg { color: #16a34a; }
  .toast.error { border-left: 4px solid var(--alert-red); }
  .toast.error svg { color: var(--alert-red); }
  .toast.info { border-left: 4px solid var(--ballpoint-blue); }
  .toast.info svg { color: var(--ballpoint-blue); }
  @keyframes toast-in {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
  @keyframes toast-out {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
  }

  /* ══ EMPTY STATE ══════════════════════════════════════════════════════════ */
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    color: var(--pencil-grey);
    gap: 8px;
  }
  .empty-state svg { width: 40px; height: 40px; opacity: 0.4; }
  .empty-state p { font-size: 1rem; }

  /* ══ FOOTER ════════════════════════════════════════════════════════════════ */
  .page-footer {
    padding: 18px 24px;
    border-top: 2px dashed rgba(14,42,92,0.15);
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.85rem;
    color: var(--pencil-grey);
    gap: 12px;
    flex-wrap: wrap;
    background: var(--paper-cream);
    border: 2px solid var(--ink-blue);
    border-radius: var(--radius-sketch);
    box-shadow: var(--shadow-sketch);
    margin-top: 8px;
  }
  .page-footer span {
    display: flex;
    align-items: center;
    gap: 5px;
  }
  .page-footer svg { width: 14px; height: 14px; opacity: 0.6; }
  .footer-links {
    display: flex;
    gap: 12px;
  }
  .footer-link {
    color: var(--pencil-grey);
    transition: var(--transition);
    cursor: pointer;
    font-size: 0.85rem;
  }
  .footer-link:hover { color: var(--alert-red); }

  /* ══ DOODLE DECORATIONS ═══════════════════════════════════════════════════ */
  .doodle-arrow {
    position: absolute;
    pointer-events: none;
    opacity: 0.15;
  }

  /* ══ DATE RANGE FILTER ════════════════════════════════════════════════════ */
  .date-filter-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }
  .date-input-wrap {
    position: relative;
  }
  .date-input-wrap label {
    font-size: 0.75rem;
    color: var(--pencil-grey);
    position: absolute;
    top: -14px;
    left: 4px;
    background: var(--paper-cream);
    padding: 0 3px;
    pointer-events: none;
  }
  .date-input {
    padding: 6px 10px;
    font-family: var(--font-hand);
    font-size: 0.9rem;
    color: var(--ink-blue);
    background: var(--paper-note);
    border: 2px solid rgba(14,42,92,0.2);
    border-radius: var(--radius-sketch);
    outline: none;
    transition: var(--transition);
    width: 130px;
    cursor: pointer;
  }
  .date-input:focus {
    border-color: var(--ink-blue);
    box-shadow: 0 0 0 2px rgba(14,42,92,0.1);
  }
  .date-input::-webkit-calendar-picker-indicator {
    cursor: pointer;
    opacity: 0.6;
  }
  .preset-btns {
    display: flex;
    gap: 3px;
    background: rgba(14,42,92,0.04);
    border-radius: var(--radius-sketch);
    padding: 2px;
    border: 1px solid rgba(14,42,92,0.1);
  }
  .preset-btn {
    padding: 4px 10px;
    font-family: var(--font-hand);
    font-size: 0.8rem;
    color: var(--pencil-grey);
    border: none;
    background: transparent;
    border-radius: var(--radius-sketch);
    cursor: pointer;
    transition: var(--transition);
  }
  .preset-btn:hover { background: rgba(14,42,92,0.06); }
  .preset-btn.active {
    background: var(--ink-blue);
    color: #fff;
  }
  .date-clear {
    width: 26px;
    height: 26px;
    display: none;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(14,42,92,0.2);
    border-radius: var(--radius-sketch);
    cursor: pointer;
    color: var(--pencil-grey);
    background: transparent;
    transition: var(--transition);
  }
  .date-clear:hover { background: rgba(209,40,40,0.1); color: var(--alert-red); border-color: var(--alert-red); }
  .date-clear svg { width: 12px; height: 12px; }
  .date-clear.visible { display: flex; }

  /* ══ EXPORT BUTTON ════════════════════════════════════════════════════════ */
  .btn-export {
    background: transparent;
    border-color: var(--pencil-grey);
    color: var(--pencil-grey);
  }
  .btn-export:hover {
    background: #16a34a;
    color: #fff;
    border-color: #16a34a;
  }

  /* ══ RESPONSIVE ═══════════════════════════════════════════════════════════ */
  @media (max-width: 1100px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .dashboard-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 768px) {
    body::after { display: none; } /* hide spiral on mobile */
    .app-wrapper { padding: 12px 14px; }
    .nav-links { display: none; }
    .nav-hamburger { display: flex; }
    .nav-links.mobile-open {
      display: flex;
      flex-direction: column;
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: var(--paper-cream);
      border: 2px solid var(--ink-blue);
      border-top: none;
      border-radius: 0 0 var(--radius-sketch) var(--radius-sketch);
      padding: 10px;
      z-index: 50;
      box-shadow: var(--shadow-sketch);
    }
    .page-header { flex-direction: column; align-items: stretch; }
    .filter-tabs { align-self: flex-start; }
    .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .kpi-card { padding: 14px 16px; }
    .kpi-value { font-size: 1.2rem; }
    .col-time, .col-num { display: none; }
    .form-row { grid-template-columns: 1fr; }
    .page-footer { flex-direction: column; align-items: flex-start; gap: 6px; }
  }
  @media (max-width: 480px) {
    .kpi-grid { grid-template-columns: 1fr; }
    .page-title { font-size: 1.5rem; }
    .filter-tabs { width: 100%; }
    .filter-tab { flex: 1; text-align: center; }
  }

  /* ── Scrollbar ──────────────────────────────────────────────────────────── */
  ::-webkit-scrollbar { width: 6px; height: 6px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: rgba(14,42,92,0.2); border-radius: 3px; }
  ::-webkit-scrollbar-thumb:hover { background: rgba(14,42,92,0.35); }
  </style>
</head>
<body>

<div class="app-wrapper">

  <!-- ══ NAVBAR ══════════════════════════════════════════════════════════════ -->
  <nav class="navbar">
    <a href="?" class="nav-logo">
      <!-- Chart-pie icon (Lucide style) -->
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
      FinanceAI
    </a>

    <div class="nav-links" id="navLinks">
      <a href="?type=all" class="nav-link <?= $filter_type==='all' ? 'active' : '' ?>">Dashboard</a>
      <a href="?type=income" class="nav-link <?= $filter_type==='income' ? 'active' : '' ?>">Income</a>
      <a href="?type=expense" class="nav-link <?= $filter_type==='expense' ? 'active' : '' ?>">Expense</a>
      <a href="#categories" class="nav-link">Kategori</a>
    </div>

    <div style="display:flex;align-items:center;gap:8px;">
      <a href="#" class="btn btn-sm btn-export" id="btnExport" title="Export CSV">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <span>CSV</span>
      </a>
      <button class="nav-cta" id="btnAddTx" type="button">
        <!-- Plus icon -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span>Transaksi</span>
      </button>
      <div class="nav-hamburger" id="navHamburger">
        <span></span><span></span><span></span>
      </div>
    </div>
  </nav>

  <!-- ══ PAGE HEADER ════════════════════════════════════════════════════════ -->
  <div class="page-header">
    <div>
      <h1 class="page-title">
        Overview Keuangan
        <span class="underline-sketch"></span>
      </h1>
      <p class="page-subtitle">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <?php
          $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
          $bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
          echo $hari[date('w')] . ', ' . date('d') . ' ' . $bulan[date('n')] . ' ' . date('Y');
        ?> &middot; <?= $total_tx ?> transaksi tercatat
      </p>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
      <div class="filter-tabs" role="tablist">
        <a href="?type=all"     class="filter-tab <?= $filter_type==='all'     ? 'active' : '' ?>" role="tab">Semua</a>
        <a href="?type=income"  class="filter-tab <?= $filter_type==='income'  ? 'active' : '' ?>" role="tab">Income</a>
        <a href="?type=expense" class="filter-tab <?= $filter_type==='expense' ? 'active' : '' ?>" role="tab">Expense</a>
      </div>
      <div class="date-filter-bar">
        <div class="preset-btns">
          <button class="preset-btn" data-preset="today" type="button">Hari ini</button>
          <button class="preset-btn" data-preset="week" type="button">Minggu</button>
          <button class="preset-btn active" data-preset="month" type="button">Bulan</button>
          <button class="preset-btn" data-preset="year" type="button">Tahun</button>
          <button class="preset-btn" data-preset="" type="button">Semua</button>
        </div>
        <div class="date-input-wrap">
          <label>Dari</label>
          <input type="date" class="date-input" id="dateFrom" value="<?= date('Y-m-01') ?>">
        </div>
        <div class="date-input-wrap">
          <label>Sampai</label>
          <input type="date" class="date-input" id="dateTo" value="<?= date('Y-m-d') ?>">
        </div>
        <button class="date-clear visible" id="dateClear" type="button" title="Reset filter">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
    </div>
  </div>

  <!-- ══ KPI CARDS ══════════════════════════════════════════════════════════ -->
  <section class="kpi-grid" id="kpiGrid">

    <article class="kpi-card">
      <div class="kpi-icon-wrap <?= $balance >= 0 ? 'emerald' : 'rose' ?>">
        <?php if ($balance >= 0): ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="17 11 12 6 7 11"/><line x1="12" y1="6" x2="12" y2="18"/></svg>
        <?php else: ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="7 13 12 18 17 13"/><line x1="12" y1="18" x2="12" y2="6"/></svg>
        <?php endif; ?>
      </div>
      <div class="kpi-label">Net Balance</div>
      <div class="kpi-value" id="kpiBalance"><?= ($balance>=0?'+':'') ?>Rp<?= number_format($balance/1000,0,',','.') ?>k</div>
      <div class="kpi-sub">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/></svg>
        <?= $balance >= 0 ? 'Surplus' : 'Defisit' ?> &middot; saldo bersih
      </div>
    </article>

    <article class="kpi-card">
      <div class="kpi-icon-wrap emerald">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
      </div>
      <div class="kpi-label">Total Income</div>
      <div class="kpi-value" id="kpiIncome">Rp<?= number_format($total_income/1000,0,',','.') ?>k</div>
      <div class="kpi-sub">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/></svg>
        Total pemasukan
      </div>
    </article>

    <article class="kpi-card">
      <div class="kpi-icon-wrap rose">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      </div>
      <div class="kpi-label">Total Expense</div>
      <div class="kpi-value" id="kpiExpense">Rp<?= number_format($total_expense/1000,0,',','.') ?>k</div>
      <div class="kpi-sub">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 12H8"/><path d="M12 8v8"/></svg>
        <?= $total_income > 0 ? number_format(($total_expense/$total_income)*100,1) : 0 ?>% dari income
      </div>
    </article>

    <article class="kpi-card">
      <div class="kpi-icon-wrap blue">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
      </div>
      <div class="kpi-label">Transaksi</div>
      <div class="kpi-value" id="kpiTx"><?= $total_tx ?></div>
      <div class="kpi-sub">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
        <?= count($cats) ?> kategori aktif
      </div>
    </article>

  </section>

  <!-- ══ DASHBOARD GRID ═════════════════════════════════════════════════════ -->
  <div class="dashboard-grid">

    <!-- Chart Panel -->
    <section class="panel" id="chart">
      <div class="panel-header">
        <div class="panel-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/><rect x="7" y="10" width="3" height="7"/><rect x="14" y="7" width="3" height="10"/></svg>
          Tren Bulanan
        </div>
        <div style="display:flex;gap:4px;">
          <span class="btn btn-ghost btn-sm active">6 Bulan</span>
        </div>
      </div>
      <div class="panel-body">
        <div class="chart-wrap">
          <div class="chart-legend">
            <div class="legend-item"><span class="legend-swatch emerald"></span> Income</div>
            <div class="legend-item"><span class="legend-swatch rose"></span> Expense</div>
          </div>
          <?php if (count($months) > 0): ?>
          <div class="chart-area">
            <?php foreach ($months as $m):
              $inc_h = $max_monthly > 0 ? round(($m['inc'] / $max_monthly) * 130) : 0;
              $exp_h = $max_monthly > 0 ? round(($m['exp'] / $max_monthly) * 130) : 0;
            ?>
            <div class="chart-column">
              <div class="chart-bar-group">
                <div class="chart-bar income-bar" style="height:<?= max(4,$inc_h) ?>px" data-tooltip="Income: Rp<?= number_format($m['inc'],0,',','.') ?>"></div>
                <div class="chart-bar expense-bar" style="height:<?= max(4,$exp_h) ?>px" data-tooltip="Expense: Rp<?= number_format($m['exp'],0,',','.') ?>"></div>
              </div>
              <span class="chart-label"><?= $m['month'] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 17V11M12 17V7M17 17V13"/></svg>
            <p>Belum ada data bulanan</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- Category Panel -->
    <section class="panel" id="categories">
      <div class="panel-header">
        <div class="panel-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
          Pengeluaran per Kategori
        </div>
      </div>
      <div class="panel-body">
        <?php if (count($cats) > 0):
          // Build donut chart segments
          $total_all_cats = array_sum(array_column($cats, 'total'));
          $donut_colors = [];
          $donut_data = [];
          foreach ($cats as $c) {
            $pct = $total_all_cats > 0 ? ($c['total'] / $total_all_cats) * 100 : 0;
            $donut_data[] = ['label' => $cat_colors[$c['category']] ?? '#333333', 'pct' => $pct];
          }
        ?>
        <!-- Donut Chart -->
        <div class="donut-wrap">
          <svg class="donut-svg" width="140" height="140" viewBox="0 0 140 140">
            <?php
            $cumulative = 0;
            $radius = 54;
            $circumference = 2 * M_PI * $radius;
            foreach ($donut_data as $i => $seg):
              $dash = ($seg['pct'] / 100) * $circumference;
              $offset = ($cumulative / 100) * $circumference;
              $cumulative += $seg['pct'];
            ?>
            <circle class="donut-segment" cx="70" cy="70" r="<?= $radius ?>"
              fill="none" stroke="<?= $seg['label'] ?>" stroke-width="22"
              stroke-dasharray="<?= $dash ?> <?= $circumference - $dash ?>"
              stroke-dashoffset="<?= -$offset ?>"
              data-tooltip="<?= round($seg['pct'], 1) ?>%"
            />
            <?php endforeach; ?>
          </svg>
          <div class="donut-center">
            <div class="donut-center-label">Total</div>
            <div class="donut-center-value">Rp<?= number_format($total_all_cats/1000,0,',','.') ?>k</div>
          </div>
        </div>
        <ul class="cat-list">
          <?php foreach ($cats as $cat):
            $icon_name = $icons[$cat['category']] ?? 'package';
            $color     = $cat_colors[$cat['category']] ?? '#333333';
            $pct       = $max_cat_total > 0 ? round(($cat['total'] / $max_cat_total) * 100) : 0;
          ?>
          <li class="cat-row">
            <div class="cat-icon-wrap" style="color:<?= $color ?>; background:<?= $color ?>12">
              <?php include __DIR__ . '/icons/' . $icon_name . '.php'; ?>
            </div>
            <div class="cat-info">
              <div class="cat-name-row">
                <span class="cat-name"><?= htmlspecialchars($cat['category']) ?></span>
                <span class="cat-amount">Rp<?= number_format($cat['total']/1000,0,',','.') ?>k</span>
              </div>
              <div class="cat-bar-track">
                <div class="cat-bar-fill" style="width:<?= $pct ?>%; background:<?= $color ?>"></div>
              </div>
            </div>
            <span class="cat-count"><?= $cat['cnt'] ?>x</span>
          </li>
          <?php endforeach; ?>
        </ul>

        <!-- Spending Insight -->
        <div class="insight-box">
          <div class="insight-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 1 1 7.072 0l-.548.547A3.374 3.374 0 0 0 14 18.469V19a2 2 0 1 1-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            Insight
          </div>
          <?php
            $top_cat = $cats[0] ?? null;
            $avg_per_tx = $total_tx > 0 ? $total_expense / $total_tx : 0;
          ?>
          <p class="insight-text">
            <?php if ($top_cat): ?>
            Pengeluaran terbesar: <strong><?= htmlspecialchars($top_cat['category']) ?></strong> (Rp<?= number_format($top_cat['total']/1000,0,',','.') ?>k).<br>
            <?php endif; ?>
            Rata-rata per transaksi: <strong>Rp<?= number_format($avg_per_tx,0,',','.') ?></strong>.
          </p>
        </div>
        <?php else: ?>
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
          <p>Belum ada data kategori</p>
        </div>
        <?php endif; ?>
      </div>
    </section>

  </div>

  <!-- ══ TRANSACTIONS TABLE ═════════════════════════════════════════════════ -->
  <section class="panel">
    <div class="panel-header">
      <div class="panel-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
        Riwayat Transaksi
        <span class="panel-title-count" id="txCount"><?= $total_tx ?></span>
        <span class="search-count" id="searchCount"></span>
      </div>
      <div style="display:flex;gap:4px;align-items:center;">
        <div class="search-bar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" class="search-input" id="searchInput" placeholder="Cari transaksi...">
          <button class="search-clear" id="searchClear" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <a href="?type=all"     class="btn btn-ghost btn-sm <?= $filter_type==='all'     ? 'active' : '' ?>">Semua</a>
        <a href="?type=income"  class="btn btn-ghost btn-sm <?= $filter_type==='income'  ? 'active' : '' ?>">Income</a>
        <a href="?type=expense" class="btn btn-ghost btn-sm <?= $filter_type==='expense' ? 'active' : '' ?>">Expense</a>
      </div>
    </div>
    <div class="panel-body panel-body--flush">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th scope="col">#</th>
              <th scope="col">Deskripsi</th>
              <th scope="col">Kategori</th>
              <th scope="col">Tipe</th>
              <th scope="col">Nominal</th>
              <th scope="col">Waktu</th>
              <th scope="col">Aksi</th>
            </tr>
          </thead>
          <tbody id="txTableBody">
          <?php
          $no = 1;
          $found = false;
          while ($tx = $transactions->fetch_assoc()):
            $found = true;
            $icon_name = $icons[$tx['category']] ?? 'package';
            $color     = $cat_colors[$tx['category']] ?? '#333333';
          ?>
            <tr data-id="<?= $tx['id'] ?>" style="animation-delay:<?= ($no-1)*40 ?>ms" data-search="<?= mb_strtolower(htmlspecialchars($tx['description']) . ' ' . $tx['category'] . ' ' . $tx['type']) ?>">
              <td class="col-num"><?= $no++ ?></td>
              <td class="col-desc"><span><?= htmlspecialchars($tx['description']) ?></span></td>
              <td>
                <span class="badge" style="color:<?= $color ?>; background:<?= $color ?>12; border-color:<?= $color ?>;">
                  <?= htmlspecialchars($tx['category']) ?>
                </span>
              </td>
              <td>
                <span class="badge <?= $tx['type']==='income' ? 'badge-income' : 'badge-expense' ?>">
                  <?= $tx['type'] ?>
                </span>
              </td>
              <td class="col-amount <?= $tx['type']==='income' ? 'amount-income' : 'amount-expense' ?>">
                <?= $tx['type']==='income' ? '+' : '−' ?>Rp <?= number_format($tx['amount'], 0, ',', '.') ?>
              </td>
              <td class="col-time"><?= $tx['created_at'] ?></td>
              <td>
                <div class="col-actions">
                  <button class="btn-icon btn-edit" data-id="<?= $tx['id'] ?>" title="Edit" type="button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </button>
                  <button class="btn-icon btn-delete danger" data-id="<?= $tx['id'] ?>" title="Hapus" type="button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
          <?php if (!$found): ?>
            <tr>
              <td colspan="7">
                <div class="empty-state">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="2" width="20" height="20" rx="3"/><path d="M9 9h6M9 13h4"/></svg>
                  <p>Belum ada transaksi. Klik tombol "+ Transaksi" untuk menambah!</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- ══ FOOTER ═════════════════════════════════════════════════════════════ -->
  <footer class="page-footer">
    <span>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><circle cx="12" cy="17" r=".5"/></svg>
      AI Finance Tracker &middot; Naive Bayes v1.0
    </span>
    <div class="footer-links">
      <span class="footer-link">Privacy</span>
      <span class="footer-link">Terms</span>
      <span class="footer-link">Contact</span>
      <span class="footer-link" onclick="location.reload()">Refresh</span>
    </div>
    <span>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Auto-refresh 60s
    </span>
  </footer>

</div><!-- /app-wrapper -->

<!-- ══ ADD / EDIT MODAL ═════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="txModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modalTitle">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span>Tambah Transaksi</span>
      </div>
      <button class="modal-close" id="modalClose" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="txForm" autocomplete="off">
      <div class="modal-body">
        <input type="hidden" name="id" id="formId" value="">
        <div class="form-group">
          <label class="form-label" for="formDesc">Deskripsi</label>
          <input class="form-input" type="text" name="description" id="formDesc" placeholder="beli nasi goreng" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="formAmount">Nominal (Rp)</label>
            <input class="form-input" type="number" name="amount" id="formAmount" placeholder="15000" min="1" required>
          </div>
          <div class="form-group">
            <label class="form-label">Tipe</label>
            <div class="type-toggle" id="typeToggle">
              <button type="button" class="type-toggle-btn active-expense" data-type="expense">Expense</button>
              <button type="button" class="type-toggle-btn" data-type="income">Income</button>
            </div>
            <input type="hidden" name="type" id="formType" value="expense">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="formCategory">Kategori</label>
          <select class="form-select" name="category" id="formCategory" required>
            <option value="makan">Makan</option>
            <option value="minum">Minum</option>
            <option value="transport">Transport</option>
            <option value="hiburan">Hiburan</option>
            <option value="tagihan">Tagihan</option>
            <option value="belanja">Belanja</option>
            <option value="lainnya">Lainnya</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" id="btnCancel">Batal</button>
        <button type="submit" class="btn btn-primary" id="btnSubmit">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
          Simpan
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══ DELETE CONFIRM MODAL ═════════════════════════════════════════════════ -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal" style="max-width:380px;">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        <span>Hapus Transaksi?</span>
      </div>
      <button class="modal-close" id="deleteModalClose" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:1.1rem;color:var(--pencil-grey);">Yakin mau hapus transaksi ini? Tindakan ini tidak bisa dibatalkan.</p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" id="deleteCancel">Batal</button>
      <button type="button" class="btn btn-primary" id="deleteConfirm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        Hapus
      </button>
    </div>
  </div>
</div>

<!-- ══ TOAST CONTAINER ══════════════════════════════════════════════════════ -->
<div class="toast-container" id="toastContainer"></div>

<script>
// ── SVG Icons for JS-generated content ──────────────────────────────────────
const ICONS = {
  check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
  alert: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
  info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
  edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
  trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
};

// ── Toast ───────────────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
  const container = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  toast.className = 'toast ' + type;
  const icon = type === 'success' ? ICONS.check : type === 'error' ? ICONS.alert : ICONS.info;
  toast.innerHTML = icon + '<span>' + msg + '</span>';
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'toast-out 0.3s ease forwards';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// ── Category color map ──────────────────────────────────────────────────────
const CAT_COLORS = {
  makan:'#D12828',minum:'#0E2A5C',transport:'#d97706',hiburan:'#7c3aed',
  income:'#16a34a',tagihan:'#dc2626',belanja:'#0891b2',lainnya:'#333333'
};

function formatRp(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }
function escapeHTML(str) { const d=document.createElement('div'); d.textContent=str; return d.innerHTML; }

// ── Update KPI cards live ───────────────────────────────────────────────────
function updateKPIs(s) {
  const bEl=document.getElementById('kpiBalance'), iEl=document.getElementById('kpiIncome'),
        eEl=document.getElementById('kpiExpense'), tEl=document.getElementById('kpiTx');
  if(bEl) bEl.textContent=(s.balance>=0?'+':'')+'Rp'+Math.round(s.balance/1000).toLocaleString('id-ID')+'k';
  if(iEl) iEl.textContent='Rp'+Math.round(s.total_income/1000).toLocaleString('id-ID')+'k';
  if(eEl) eEl.textContent='Rp'+Math.round(s.total_expense/1000).toLocaleString('id-ID')+'k';
  if(tEl) tEl.textContent=s.total_tx;
}

// ── Build table row HTML ────────────────────────────────────────────────────
function buildRowHTML(tx, idx) {
  const color=CAT_COLORS[tx.category]||'#333333', sign=tx.type==='income'?'+':'−',
        aCls=tx.type==='income'?'amount-income':'amount-expense',
        bCls=tx.type==='income'?'badge-income':'badge-expense',
        search=(tx.description+' '+tx.category+' '+tx.type).toLowerCase();
  return `<tr data-id="${tx.id}" style="animation-delay:${idx*40}ms" data-search="${search}">
    <td class="col-num">${idx+1}</td>
    <td class="col-desc"><span>${escapeHTML(tx.description)}</span></td>
    <td><span class="badge" style="color:${color};background:${color}12;border-color:${color}">${escapeHTML(tx.category)}</span></td>
    <td><span class="badge ${bCls}">${tx.type}</span></td>
    <td class="col-amount ${aCls}">${sign}${formatRp(tx.amount)}</td>
    <td class="col-time">${tx.created_at}</td>
    <td><div class="col-actions">
      <button class="btn-icon btn-edit" data-id="${tx.id}" title="Edit" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
      <button class="btn-icon btn-delete danger" data-id="${tx.id}" title="Hapus" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
    </div></td>
  </tr>`;
}

// ── Bind edit/delete on a row ───────────────────────────────────────────────
function bindRowButtons(row) {
  row.querySelector('.btn-edit')?.addEventListener('click', async function() {
    try { const r=await fetch('api.php?action=get&id='+this.dataset.id), j=await r.json();
      if(j.status==='ok') openModal('edit',j.data); else showToast('Transaksi tidak ditemukan.','error');
    } catch { showToast('Gagal mengambil data.','error'); }
  });
  row.querySelector('.btn-delete')?.addEventListener('click', function() {
    deleteTargetId=this.dataset.id; deleteModal.classList.add('open');
  });
}

// ── Get current filter params ───────────────────────────────────────────────
function getCurrentFilters() {
  const p=new URLSearchParams(), u=new URLSearchParams(window.location.search);
  const type=u.get('type')||'all';
  if(type!=='all') p.set('type',type);
  const preset=document.querySelector('.preset-btn.active')?.dataset?.preset||'';
  if(preset) { p.set('preset',preset); }
  else { const f=document.getElementById('dateFrom').value, t=document.getElementById('dateTo').value;
    if(f) p.set('date_from',f); if(t) p.set('date_to',t);
  }
  return p.toString();
}

// ── Modal Logic ─────────────────────────────────────────────────────────────
const txModal      = document.getElementById('txModal');
const deleteModal  = document.getElementById('deleteModal');
const txForm       = document.getElementById('txForm');
const modalTitle   = document.getElementById('modalTitle');
const formId       = document.getElementById('formId');
const formDesc     = document.getElementById('formDesc');
const formAmount   = document.getElementById('formAmount');
const formType     = document.getElementById('formType');
const formCategory = document.getElementById('formCategory');
const typeToggle   = document.getElementById('typeToggle');
let deleteTargetId = null;

function openModal(mode, data) {
  txForm.reset();
  formId.value = '';
  setTypeToggle('expense');

  if (mode === 'edit' && data) {
    modalTitle.querySelector('span').textContent = 'Edit Transaksi';
    modalTitle.querySelector('svg').innerHTML = '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>';
    formId.value       = data.id;
    formDesc.value     = data.description;
    formAmount.value   = data.amount;
    formCategory.value = data.category;
    setTypeToggle(data.type);
  } else {
    modalTitle.querySelector('span').textContent = 'Tambah Transaksi';
    modalTitle.querySelector('svg').innerHTML = '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>';
  }

  txModal.classList.add('open');
  setTimeout(() => formDesc.focus(), 100);
}

function closeModal() {
  txModal.classList.remove('open');
}

function setTypeToggle(type) {
  formType.value = type;
  typeToggle.querySelectorAll('.type-toggle-btn').forEach(btn => {
    btn.className = 'type-toggle-btn';
    if (btn.dataset.type === type) {
      btn.classList.add(type === 'income' ? 'active-income' : 'active-expense');
    }
  });
}

// Type toggle clicks
typeToggle.querySelectorAll('.type-toggle-btn').forEach(btn => {
  btn.addEventListener('click', () => setTypeToggle(btn.dataset.type));
});

// Open add modal
document.getElementById('btnAddTx').addEventListener('click', () => openModal('add'));
document.getElementById('modalClose').addEventListener('click', closeModal);
document.getElementById('btnCancel').addEventListener('click', closeModal);
txModal.addEventListener('click', e => { if (e.target === txModal) closeModal(); });

// ── Form Submit (Add / Edit) ────────────────────────────────────────────────
txForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const id = formId.value;
  const action = id ? 'edit' : 'add';
  const formData = new FormData(txForm);

  try {
    const res = await fetch('api.php?action=' + action, {
      method: 'POST',
      body: formData,
    });
    const json = await res.json();

    if (json.status === 'ok') {
      showToast(json.message, 'success');
      closeModal();
      // Live update KPIs
      if (json.data?.summary) updateKPIs(json.data.summary);
      // Live update table
      if (action === 'add' && json.data?.transaction) {
        const tbody = document.getElementById('txTableBody');
        const emptyRow = tbody.querySelector('.empty-state');
        if (emptyRow) tbody.innerHTML = '';
        const newHTML = buildRowHTML(json.data.transaction, 0);
        tbody.insertAdjacentHTML('afterbegin', newHTML);
        const tr = tbody.firstElementChild;
        bindRowButtons(tr);
        document.getElementById('txCount').textContent = parseInt(document.getElementById('txCount').textContent||0) + 1;
      } else if (action === 'edit' && json.data?.transaction) {
        const oldRow = document.querySelector('tr[data-id="'+json.data.transaction.id+'"]');
        if (oldRow) {
          const idx = Array.from(oldRow.parentNode.children).indexOf(oldRow);
          const newHTML = buildRowHTML(json.data.transaction, idx);
          oldRow.insertAdjacentHTML('afterend', newHTML);
          const tr = oldRow.nextElementSibling;
          oldRow.remove();
          bindRowButtons(tr);
          tr.style.background = 'rgba(255,255,165,0.4)';
          setTimeout(() => { tr.style.transition = 'background 1s'; tr.style.background = ''; }, 500);
        }
      }
    } else {
      showToast(json.message, 'error');
    }
  } catch (err) {
    showToast('Gagal menghubungi server.', 'error');
  }
});

// ── Edit button clicks ──────────────────────────────────────────────────────
document.querySelectorAll('.btn-edit').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id = btn.dataset.id;
    try {
      const res = await fetch('api.php?action=get&id=' + id);
      const json = await res.json();
      if (json.status === 'ok') {
        openModal('edit', json.data);
      } else {
        showToast('Transaksi tidak ditemukan.', 'error');
      }
    } catch (err) {
      showToast('Gagal mengambil data.', 'error');
    }
  });
});

// ── Delete button clicks ────────────────────────────────────────────────────
document.querySelectorAll('.btn-delete').forEach(btn => {
  btn.addEventListener('click', () => {
    deleteTargetId = btn.dataset.id;
    deleteModal.classList.add('open');
  });
});

document.getElementById('deleteModalClose').addEventListener('click', () => { deleteModal.classList.remove('open'); });
document.getElementById('deleteCancel').addEventListener('click', () => { deleteModal.classList.remove('open'); });
deleteModal.addEventListener('click', e => { if (e.target === deleteModal) deleteModal.classList.remove('open'); });

document.getElementById('deleteConfirm').addEventListener('click', async () => {
  if (!deleteTargetId) return;
  const formData = new FormData();
  formData.append('id', deleteTargetId);

  try {
    const res = await fetch('api.php?action=delete', {
      method: 'POST',
      body: formData,
    });
    const json = await res.json();

    if (json.status === 'ok') {
      showToast(json.message, 'success');
      deleteModal.classList.remove('open');
      // Live update KPIs
      if (json.data?.summary) updateKPIs(json.data.summary);
      // Remove row with animation
      const row = document.querySelector('tr[data-id="' + deleteTargetId + '"]');
      if (row) {
        row.style.transition = 'opacity 0.3s, transform 0.3s';
        row.style.opacity = '0';
        row.style.transform = 'translateX(20px)';
        setTimeout(() => row.remove(), 300);
      }
      deleteTargetId = null;
    } else {
      showToast(json.message, 'error');
    }
  } catch (err) {
    showToast('Gagal menghapus transaksi.', 'error');
  }
});

// ── Mobile hamburger ────────────────────────────────────────────────────────
document.getElementById('navHamburger').addEventListener('click', () => {
  document.getElementById('navLinks').classList.toggle('mobile-open');
});

// ── KPI entrance animation + animated counters ─────────────────────────────
function animateCounter(el, target, prefix = '', suffix = '') {
  const duration = 800;
  const start = performance.now();
  const initial = 0;
  function tick(now) {
    const elapsed = now - start;
    const progress = Math.min(elapsed / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
    const current = Math.round(initial + (target - initial) * eased);
    el.textContent = prefix + current.toLocaleString('id-ID') + suffix;
    if (progress < 1) requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
}

window.addEventListener('load', () => {
  document.querySelectorAll('.kpi-card').forEach((el, i) => {
    el.style.animationDelay = (i * 80) + 'ms';
    el.classList.add('kpi-enter');
  });

  // Animate KPI counters
  setTimeout(() => {
    const balanceEl = document.getElementById('kpiBalance');
    const incomeEl  = document.getElementById('kpiIncome');
    const expenseEl = document.getElementById('kpiExpense');
    const txEl      = document.getElementById('kpiTx');

    const bVal = <?= $balance ?>;
    const iVal = <?= $total_income ?>;
    const eVal = <?= $total_expense ?>;
    const tVal = <?= $total_tx ?>;

    if (balanceEl) animateCounter(balanceEl, Math.round(bVal/1000), bVal>=0?'+':'', 'k');
    if (incomeEl)  animateCounter(incomeEl, Math.round(iVal/1000), 'Rp', 'k');
    if (expenseEl) animateCounter(expenseEl, Math.round(eVal/1000), 'Rp', 'k');
    if (txEl)      animateCounter(txEl, tVal);
  }, 300);
});

// ── Search functionality ────────────────────────────────────────────────────
const searchInput = document.getElementById('searchInput');
const searchClear = document.getElementById('searchClear');
const searchCount = document.getElementById('searchCount');
const txCount     = document.getElementById('txCount');
const allRows     = document.querySelectorAll('#txTableBody tr[data-search]');

function doSearch() {
  const q = searchInput.value.trim().toLowerCase();
  let visible = 0;

  allRows.forEach(row => {
    const haystack = row.dataset.search || '';
    const match = q === '' || haystack.includes(q);
    row.classList.toggle('row-hidden', !match);
    if (match) {
      visible++;
      row.style.animationDelay = (visible * 30) + 'ms';
    }
  });

  searchClear.classList.toggle('visible', q.length > 0);

  if (q.length > 0) {
    searchCount.textContent = visible + ' ditemukan';
    txCount.style.display = 'none';
  } else {
    searchCount.textContent = '';
    txCount.style.display = '';
  }
}

searchInput.addEventListener('input', doSearch);
searchClear.addEventListener('click', () => {
  searchInput.value = '';
  doSearch();
  searchInput.focus();
});

// ── Chart tooltip ───────────────────────────────────────────────────────────
document.querySelectorAll('[data-tooltip]').forEach(el => {
  el.addEventListener('mouseenter', () => {
    const tip = document.createElement('div');
    tip.className = 'tooltip-popup';
    tip.textContent = el.dataset.tooltip;
    tip.style.cssText = 'position:absolute;background:var(--ink-blue);color:#fff;font-family:var(--font-hand);font-size:0.85rem;padding:5px 10px;border-radius:3px;pointer-events:none;z-index:9999;white-space:nowrap;box-shadow:var(--shadow-sketch);';
    document.body.appendChild(tip);
    const rect = el.getBoundingClientRect();
    tip.style.left = rect.left + rect.width/2 - tip.offsetWidth/2 + 'px';
    tip.style.top  = rect.top - tip.offsetHeight - 8 + window.scrollY + 'px';
    el._tooltip = tip;
  });
  el.addEventListener('mouseleave', () => { el._tooltip?.remove(); });
});

// ── Keyboard shortcuts ──────────────────────────────────────────────────────
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeModal();
    deleteModal.classList.remove('open');
    searchInput.blur();
  }
  // Ctrl+N = new transaction
  if (e.ctrlKey && e.key === 'n') {
    e.preventDefault();
    openModal('add');
  }
  // Ctrl+K / / = focus search
  if ((e.ctrlKey && e.key === 'k') || (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA')) {
    e.preventDefault();
    searchInput.focus();
  }
});

// ── Donut chart tooltips ────────────────────────────────────────────────────
document.querySelectorAll('.donut-segment').forEach(seg => {
  seg.addEventListener('mouseenter', (e) => {
    const tip = document.createElement('div');
    tip.className = 'tooltip-popup';
    tip.textContent = seg.dataset.tooltip;
    tip.style.cssText = 'position:absolute;background:var(--ink-blue);color:#fff;font-family:var(--font-hand);font-size:0.85rem;padding:5px 10px;border-radius:3px;pointer-events:none;z-index:9999;white-space:nowrap;box-shadow:var(--shadow-sketch);';
    document.body.appendChild(tip);
    const rect = seg.getBoundingClientRect();
    tip.style.left = rect.left + rect.width/2 - tip.offsetWidth/2 + 'px';
    tip.style.top  = rect.top - tip.offsetHeight - 8 + window.scrollY + 'px';
    seg._tooltip = tip;
  });
  seg.addEventListener('mouseleave', () => { seg._tooltip?.remove(); });
});

// ── Date Range Filter ───────────────────────────────────────────────────────
const dateFrom   = document.getElementById('dateFrom');
const dateTo     = document.getElementById('dateTo');
const dateClear  = document.getElementById('dateClear');
const presetBtns = document.querySelectorAll('.preset-btn');

async function refreshWithFilters() {
  try {
    const params = getCurrentFilters();
    const [listRes, sumRes] = await Promise.all([
      fetch('api.php?action=list&'+params),
      fetch('api.php?action=summary&'+params)
    ]);
    const listJson = await listRes.json();
    const sumJson  = await sumRes.json();

    if (listJson.status === 'ok') {
      const tbody = document.getElementById('txTableBody');
      const data = listJson.data;
      if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="2" width="20" height="20" rx="3"/><path d="M9 9h6M9 13h4"/></svg><p>Tidak ada transaksi ditemukan.</p></div></td></tr>';
      } else {
        tbody.innerHTML = data.map((tx,i) => buildRowHTML(tx,i)).join('');
        tbody.querySelectorAll('tr').forEach(r => bindRowButtons(r));
      }
      document.getElementById('txCount').textContent = listJson.total;
    }
    if (sumJson.status === 'ok') updateKPIs(sumJson.data);
  } catch(err) { console.error('Filter refresh failed:', err); }
}

presetBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    presetBtns.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    refreshWithFilters();
    dateClear.classList.add('visible');
  });
});

dateFrom.addEventListener('change', () => {
  presetBtns.forEach(b => b.classList.remove('active'));
  refreshWithFilters();
  dateClear.classList.add('visible');
});
dateTo.addEventListener('change', () => {
  presetBtns.forEach(b => b.classList.remove('active'));
  refreshWithFilters();
  dateClear.classList.add('visible');
});

dateClear.addEventListener('click', () => {
  dateFrom.value = ''; dateTo.value = '';
  presetBtns.forEach(b => b.classList.remove('active'));
  dateClear.classList.remove('visible');
  refreshWithFilters();
});

// ── Export CSV ──────────────────────────────────────────────────────────────
document.getElementById('btnExport').addEventListener('click', (e) => {
  e.preventDefault();
  const params = getCurrentFilters();
  window.location.href = 'api.php?action=export&' + params;
});
</script>

</body>
</html>
