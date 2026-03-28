<?php
require 'config.php';
$db = get_db();

// ── Summary stats ──────────────────────────────────────────────────────────
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

// ── Category breakdown ─────────────────────────────────────────────────────
$cat_rows = $db->query("
    SELECT category, COUNT(*) as cnt, SUM(amount) as total
    FROM transactions
    WHERE type = 'expense'
    GROUP BY category
    ORDER BY total DESC
");
$cats = [];
$max_cat_total = 0;
while ($c = $cat_rows->fetch_assoc()) {
    $cats[] = $c;
    if ($c['total'] > $max_cat_total) $max_cat_total = $c['total'];
}

// ── Monthly chart data ─────────────────────────────────────────────────────
$monthly = $db->query("
    SELECT
        DATE_FORMAT(created_at, '%b') as month,
        DATE_FORMAT(created_at, '%Y%m') as ym,
        SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS inc,
        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS exp
    FROM transactions
    GROUP BY ym, month
    ORDER BY ym DESC
    LIMIT 6
");
$months = [];
while ($m = $monthly->fetch_assoc()) $months[] = $m;
$months = array_reverse($months);
$max_monthly = 1;
foreach ($months as $m) {
    if ($m['inc'] > $max_monthly) $max_monthly = $m['inc'];
    if ($m['exp'] > $max_monthly) $max_monthly = $m['exp'];
}

// ── Transactions list ──────────────────────────────────────────────────────
$filter_type = $_GET['type'] ?? 'all';
$where = ($filter_type !== 'all')
    ? "WHERE type = '" . $db->real_escape_string($filter_type) . "'"
    : "";

$transactions = $db->query("
    SELECT * FROM transactions $where ORDER BY created_at DESC LIMIT 50
");

// ── Category icon map (Font Awesome classes) ───────────────────────────────
$icons = [
    'makan'     => 'fa-utensils',
    'minum'     => 'fa-mug-hot',
    'transport' => 'fa-car',
    'hiburan'   => 'fa-film',
    'income'    => 'fa-coins',
    'tagihan'   => 'fa-file-invoice',
    'belanja'   => 'fa-bag-shopping',
    'lainnya'   => 'fa-box',
];

$cat_colors = [
    'makan'     => '#2563eb',
    'minum'     => '#7c3aed',
    'transport' => '#d97706',
    'hiburan'   => '#ea580c',
    'income'    => '#16a34a',
    'tagihan'   => '#dc2626',
    'belanja'   => '#0891b2',
    'lainnya'   => '#64748b',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FinanceAI — Dashboard</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">

  <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- ══ SIDEBAR ══════════════════════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

  <div class="sidebar-logo" id="sidebarToggle" role="button" tabindex="0" aria-label="Toggle sidebar">
    <div class="logo-mark">
      <i class="fa-solid fa-chart-pie"></i>
    </div>
    <span class="logo-text">FinanceAI</span>
  </div>

  <nav class="sidebar-nav" aria-label="Main navigation">

    <span class="nav-section-label">Workspace</span>

    <a href="?type=all" class="nav-item <?= $filter_type==='all' ? 'active' : '' ?>">
      <i class="nav-icon fa-solid fa-gauge-high"></i>
      <span class="nav-label">Dashboard</span>
    </a>

    <a href="?type=income" class="nav-item <?= $filter_type==='income' ? 'active' : '' ?>">
      <i class="nav-icon fa-solid fa-arrow-trend-up"></i>
      <span class="nav-label">Income</span>
    </a>

    <a href="?type=expense" class="nav-item <?= $filter_type==='expense' ? 'active' : '' ?>">
      <i class="nav-icon fa-solid fa-arrow-trend-down"></i>
      <span class="nav-label">Expenses</span>
    </a>

    <div class="nav-divider"></div>
    <span class="nav-section-label">Analitik</span>

    <a href="#categories" class="nav-item">
      <i class="nav-icon fa-solid fa-layer-group"></i>
      <span class="nav-label">Kategori</span>
    </a>

    <a href="#chart" class="nav-item">
      <i class="nav-icon fa-solid fa-chart-column"></i>
      <span class="nav-label">Tren Bulanan</span>
    </a>

    <div class="nav-divider"></div>
    <span class="nav-section-label">Sistem</span>

    <a href="#" class="nav-item">
      <i class="nav-icon fa-brands fa-whatsapp"></i>
      <span class="nav-label">WhatsApp Bot</span>
      <span class="nav-badge">ON</span>
    </a>

    <a href="#" class="nav-item">
      <i class="nav-icon fa-solid fa-database"></i>
      <span class="nav-label">Dataset</span>
    </a>

  </nav>

  <footer class="sidebar-footer">
    <div class="sidebar-footer-item">
      <i class="fa-solid fa-microchip"></i>
      <span class="nav-label">Naive Bayes v1.0</span>
    </div>
  </footer>

</aside>

<!-- ══ MAIN ══════════════════════════════════════════════════════════════════ -->
<div class="main-wrapper" id="mainWrapper">

  <!-- Topbar -->
  <header class="topbar">
    <div class="topbar-left">
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <span>FinanceAI</span>
        <i class="fa-solid fa-chevron-right breadcrumb-sep"></i>
        <span class="breadcrumb-current">Dashboard</span>
      </nav>
    </div>
    <div class="topbar-right">
      <div class="live-indicator">
        <span class="live-dot"></span>
        <span>Live</span>
      </div>
      <button class="btn btn-ghost" onclick="location.reload()" aria-label="Refresh">
        <i class="fa-solid fa-rotate-right"></i>
        <span>Refresh</span>
      </button>
      <button class="btn btn-primary" aria-label="New transaction">
        <i class="fa-solid fa-plus"></i>
        <span>Transaksi</span>
      </button>
    </div>
  </header>

  <!-- Page content -->
  <main class="page-content">

    <!-- Page header -->
    <div class="page-header">
      <div class="page-header-info">
        <h1 class="page-title">Overview Keuangan</h1>
        <p class="page-subtitle">
          <i class="fa-regular fa-calendar"></i>
          <?= date('l, d F Y') ?> &middot; <?= $total_tx ?> transaksi tercatat
        </p>
      </div>
      <div class="filter-tabs" role="tablist" aria-label="Filter transactions">
        <a href="?type=all"     class="filter-tab <?= $filter_type==='all'     ? 'active':'' ?>" role="tab">Semua</a>
        <a href="?type=income"  class="filter-tab <?= $filter_type==='income'  ? 'active':'' ?>" role="tab">Income</a>
        <a href="?type=expense" class="filter-tab <?= $filter_type==='expense' ? 'active':'' ?>" role="tab">Expense</a>
      </div>
    </div>

    <!-- KPI Cards -->
    <section class="kpi-grid" aria-label="Key performance indicators">

      <article class="kpi-card <?= $balance >= 0 ? 'accent-emerald' : 'accent-rose' ?>">
        <div class="kpi-top">
          <span class="kpi-label">Net Balance</span>
          <div class="kpi-icon-wrap <?= $balance >= 0 ? 'emerald' : 'rose' ?>">
            <i class="fa-solid <?= $balance >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
          </div>
        </div>
        <div class="kpi-value">
          <?= $balance >= 0 ? '+' : '' ?>Rp<?= number_format($balance/1000, 0, ',', '.') ?>k
        </div>
        <div class="kpi-sub">
          <i class="fa-solid <?= $balance >= 0 ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
          <?= $balance >= 0 ? 'Surplus' : 'Defisit' ?> · saldo bersih
        </div>
      </article>

      <article class="kpi-card accent-emerald">
        <div class="kpi-top">
          <span class="kpi-label">Total Income</span>
          <div class="kpi-icon-wrap emerald">
            <i class="fa-solid fa-coins"></i>
          </div>
        </div>
        <div class="kpi-value">Rp<?= number_format($total_income/1000, 0, ',', '.') ?>k</div>
        <div class="kpi-sub">
          <i class="fa-solid fa-chart-line"></i>
          Total pemasukan
        </div>
      </article>

      <article class="kpi-card accent-rose">
        <div class="kpi-top">
          <span class="kpi-label">Total Expense</span>
          <div class="kpi-icon-wrap rose">
            <i class="fa-solid fa-receipt"></i>
          </div>
        </div>
        <div class="kpi-value">Rp<?= number_format($total_expense/1000, 0, ',', '.') ?>k</div>
        <div class="kpi-sub">
          <i class="fa-solid fa-percent"></i>
          <?= $total_income > 0 ? number_format(($total_expense/$total_income)*100, 1) : 0 ?>% dari income
        </div>
      </article>

      <article class="kpi-card accent-blue">
        <div class="kpi-top">
          <span class="kpi-label">Transaksi</span>
          <div class="kpi-icon-wrap blue">
            <i class="fa-solid fa-list-ul"></i>
          </div>
        </div>
        <div class="kpi-value"><?= $total_tx ?></div>
        <div class="kpi-sub">
          <i class="fa-solid fa-tag"></i>
          <?= count($cats) ?> kategori aktif
        </div>
      </article>

    </section>

    <!-- Dashboard Grid -->
    <div class="dashboard-grid">

      <!-- Monthly Trend Chart -->
      <section class="panel" id="chart" aria-label="Monthly trend chart">
        <div class="panel-header">
          <div class="panel-title">
            <i class="fa-solid fa-chart-column panel-title-icon"></i>
            Tren Bulanan
          </div>
          <div class="panel-actions">
            <button class="btn btn-ghost btn-sm active">6 Bulan</button>
          </div>
        </div>
        <div class="panel-body">
          <div class="chart-wrap">
            <div class="chart-legend">
              <div class="legend-item">
                <span class="legend-swatch emerald"></span>
                Income
              </div>
              <div class="legend-item">
                <span class="legend-swatch rose"></span>
                Expense
              </div>
            </div>
            <?php if (count($months) > 0): ?>
            <div class="chart-area" role="img" aria-label="Bar chart of monthly income and expenses">
              <?php foreach ($months as $m):
                $inc_h = $max_monthly > 0 ? round(($m['inc'] / $max_monthly) * 130) : 0;
                $exp_h = $max_monthly > 0 ? round(($m['exp'] / $max_monthly) * 130) : 0;
              ?>
              <div class="chart-column">
                <div class="chart-bar-group">
                  <div class="chart-bar income-bar"
                       style="height:<?= max(4,$inc_h) ?>px"
                       data-tooltip="Income: Rp<?= number_format($m['inc'],0,',','.') ?>"></div>
                  <div class="chart-bar expense-bar"
                       style="height:<?= max(4,$exp_h) ?>px"
                       data-tooltip="Expense: Rp<?= number_format($m['exp'],0,',','.') ?>"></div>
                </div>
                <span class="chart-label"><?= $m['month'] ?></span>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
              <i class="fa-solid fa-chart-bar empty-icon"></i>
              <p>Belum ada data bulanan</p>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- Category breakdown -->
      <section class="panel" id="categories" aria-label="Expense by category">
        <div class="panel-header">
          <div class="panel-title">
            <i class="fa-solid fa-layer-group panel-title-icon"></i>
            Pengeluaran per Kategori
          </div>
        </div>
        <div class="panel-body">
          <?php if (count($cats) > 0): ?>
          <ul class="cat-list" role="list">
            <?php foreach ($cats as $cat):
              $icon_class = $icons[$cat['category']] ?? 'fa-box';
              $color      = $cat_colors[$cat['category']] ?? '#64748b';
              $pct        = $max_cat_total > 0 ? round(($cat['total'] / $max_cat_total) * 100) : 0;
            ?>
            <li class="cat-row">
              <div class="cat-icon-wrap" style="background:<?= $color ?>18; color:<?= $color ?>">
                <i class="fa-solid <?= $icon_class ?>"></i>
              </div>
              <div class="cat-info">
                <div class="cat-name-row">
                  <span class="cat-name"><?= htmlspecialchars($cat['category']) ?></span>
                  <span class="cat-amount">Rp<?= number_format($cat['total']/1000,0,',','.')?>k</span>
                </div>
                <div class="cat-bar-track">
                  <div class="cat-bar-fill" style="width:<?= $pct ?>%; background:<?= $color ?>"></div>
                </div>
              </div>
              <span class="cat-count"><?= $cat['cnt'] ?><small>×</small></span>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?>
          <div class="empty-state">
            <i class="fa-solid fa-folder-open empty-icon"></i>
            <p>Belum ada data kategori</p>
          </div>
          <?php endif; ?>
        </div>
      </section>

    </div>

    <!-- Transaction Table -->
    <section class="panel" aria-label="Transaction history">
      <div class="panel-header">
        <div class="panel-title">
          <i class="fa-solid fa-table-list panel-title-icon"></i>
          Riwayat Transaksi
          <span class="panel-title-count"><?= $total_tx ?></span>
        </div>
        <div class="panel-actions">
          <a href="?type=all"     class="btn btn-ghost btn-sm <?= $filter_type==='all'     ? 'active':'' ?>">Semua</a>
          <a href="?type=income"  class="btn btn-ghost btn-sm <?= $filter_type==='income'  ? 'active':'' ?>">Income</a>
          <a href="?type=expense" class="btn btn-ghost btn-sm <?= $filter_type==='expense' ? 'active':'' ?>">Expense</a>
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
              </tr>
            </thead>
            <tbody>
            <?php
            $no = 1;
            $found = false;
            while ($tx = $transactions->fetch_assoc()):
              $found = true;
              $icon_class = $icons[$tx['category']] ?? 'fa-box';
              $color      = $cat_colors[$tx['category']] ?? '#64748b';
            ?>
              <tr>
                <td class="col-num"><?= $no++ ?></td>
                <td class="col-desc"><span><?= htmlspecialchars($tx['description']) ?></span></td>
                <td>
                  <span class="badge badge-cat" style="--cat-color:<?= $color ?>">
                    <i class="fa-solid <?= $icon_class ?>"></i>
                    <?= htmlspecialchars($tx['category']) ?>
                  </span>
                </td>
                <td>
                  <span class="badge <?= $tx['type']==='income' ? 'badge-income' : 'badge-expense' ?>">
                    <i class="fa-solid <?= $tx['type']==='income' ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
                    <?= $tx['type'] ?>
                  </span>
                </td>
                <td class="col-amount <?= $tx['type']==='income' ? 'amount-income' : 'amount-expense' ?>">
                  <?= $tx['type']==='income' ? '+' : '−' ?>Rp <?= number_format($tx['amount'], 0, ',', '.') ?>
                </td>
                <td class="col-time"><?= $tx['created_at'] ?></td>
              </tr>
            <?php endwhile; ?>
            <?php if (!$found): ?>
              <tr>
                <td colspan="6">
                  <div class="empty-state">
                    <i class="fa-solid fa-inbox empty-icon"></i>
                    <p>Belum ada transaksi</p>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

  </main>

  <footer class="page-footer">
    <span>AI Finance Tracker &middot; Naive Bayes Classifier &middot; Local MVP</span>
    <span>
      <i class="fa-regular fa-clock"></i>
      Auto-refresh setiap 60 detik
    </span>
  </footer>

</div><!-- /main-wrapper -->

<!-- Mobile sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
// ── Sidebar toggle (logo click) ─────────────────────────────────────────────
const sidebar     = document.getElementById('sidebar');
const mainWrapper = document.getElementById('mainWrapper');
const toggleBtn   = document.getElementById('sidebarToggle');
const overlay     = document.getElementById('sidebarOverlay');
const STORAGE_KEY = 'sidebar_collapsed';
const isMobile    = () => window.innerWidth <= 768;

function setCollapsed(val) {
  sidebar.classList.toggle('collapsed', val);
  mainWrapper.classList.toggle('sidebar-collapsed', val);
  if (isMobile()) {
    overlay.classList.toggle('visible', !val);
    document.body.classList.toggle('no-scroll', !val);
  }
  if (!isMobile()) localStorage.setItem(STORAGE_KEY, val ? '1' : '0');
}

// Restore desktop preference
if (!isMobile() && localStorage.getItem(STORAGE_KEY) === '1') setCollapsed(true);
// On mobile, sidebar starts hidden (collapsed)
if (isMobile()) { sidebar.classList.add('mobile-hidden'); }

toggleBtn.addEventListener('click', () => {
  if (isMobile()) {
    const hidden = sidebar.classList.toggle('mobile-hidden');
    overlay.classList.toggle('visible', !hidden);
    document.body.classList.toggle('no-scroll', !hidden);
  } else {
    setCollapsed(!sidebar.classList.contains('collapsed'));
  }
});

// keyboard a11y
toggleBtn.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleBtn.click(); } });

overlay.addEventListener('click', () => {
  sidebar.classList.add('mobile-hidden');
  overlay.classList.remove('visible');
  document.body.classList.remove('no-scroll');
});

// ── Tooltip ─────────────────────────────────────────────────────────────────
document.querySelectorAll('[data-tooltip]').forEach(el => {
  el.addEventListener('mouseenter', () => {
    const tip = document.createElement('div');
    tip.className = 'tooltip-popup';
    tip.textContent = el.dataset.tooltip;
    document.body.appendChild(tip);

    const rect = el.getBoundingClientRect();
    tip.style.left = rect.left + rect.width / 2 - tip.offsetWidth / 2 + 'px';
    tip.style.top  = rect.top - tip.offsetHeight - 8 + window.scrollY + 'px';
    el._tooltip = tip;
  });
  el.addEventListener('mouseleave', () => {
    el._tooltip?.remove();
  });
});

// ── KPI entrance animation ───────────────────────────────────────────────────
window.addEventListener('load', () => {
  document.querySelectorAll('.kpi-card').forEach((el, i) => {
    el.style.animationDelay = `${i * 70}ms`;
    el.classList.add('kpi-enter');
  });
});

// ── Auto refresh ─────────────────────────────────────────────────────────────
setTimeout(() => location.reload(), 60000);
</script>

</body>
</html>