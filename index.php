
<?php
require_once __DIR__ . '/includes/db.php';

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function number($value): string
{
    return number_format((int)$value);
}

try {
    // PART 1: LIVE DATABASE STATISTICS
    $totalBorrowers = (int)$pdo->query(
        "SELECT COUNT(*) FROM public.borrowers"
    )->fetchColumn();

    $totalEquipment = (int)$pdo->query(
        "SELECT COUNT(*) FROM public.equipment"
    )->fetchColumn();

    $totalTransactions = (int)$pdo->query(
        "SELECT COUNT(*) FROM public.borrow_transactions"
    )->fetchColumn();

    $totalBorrowItems = (int)$pdo->query(
        "SELECT COUNT(*) FROM public.borrow_items"
    )->fetchColumn();

    $availableEquipment = (int)$pdo->query(
        "SELECT COALESCE(SUM(quantity_available), 0)
         FROM public.equipment"
    )->fetchColumn();

    $activeTransactions = (int)$pdo->query(
        "SELECT COUNT(*)
         FROM public.borrow_transactions
         WHERE LOWER(status) NOT IN ('returned', 'completed', 'cancelled')"
    )->fetchColumn();

    // RECENT BORROWING TRANSACTIONS
    $recentTransactions = $pdo->query(
        "SELECT
            bt.borrow_id,
            b.borrower_no,
            b.full_name,
            bt.borrow_date,
            bt.due_date,
            bt.status
         FROM public.borrow_transactions bt
         LEFT JOIN public.borrowers b
            ON b.borrower_id = bt.borrower_id
         ORDER BY bt.borrow_date DESC, bt.borrow_id DESC
         LIMIT 6"
    )->fetchAll();

} catch (PDOException $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    http_response_code(500);
    die(
        '<div style="font-family:Arial;padding:30px;">
            <h2>Dashboard could not be loaded</h2>
            <p>Please check your database permissions and connection.</p>
        </div>'
    );
}

$today = date('l, F j, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Database Administration Practical</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        body {
            background: #f4f6fb;
            color: #202b43;
            font-family: 'DM Sans', Arial, sans-serif;
        }

        .app-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            min-width: 260px;
            min-height: 100vh;
            background: #101d3a;
            color: #fff;
            padding: 25px 17px;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 4px 7px 30px;
        }

        .brand-icon {
            width: 43px;
            height: 43px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: #3563e9;
            font-size: 20px;
        }

        .sidebar-brand h5 {
            font-size: 16px;
            font-weight: 800;
            margin: 0;
        }

        .sidebar-brand small {
            color: #a8b4cc;
            font-size: 9px;
            letter-spacing: .7px;
        }

        .sidebar-section-label {
            color: #8494b1;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 1px;
            padding: 16px 10px 8px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 11px;
            color: #bdc8db;
            text-decoration: none;
            padding: 11px 12px;
            border-radius: 9px;
            margin-bottom: 4px;
            font-size: 12px;
            font-weight: 600;
            transition: .2s;
        }

        .sidebar-link i {
            font-size: 16px;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background: #263b60;
            color: #fff;
        }

        .sidebar-link.active {
            box-shadow: inset 3px 0 #70a0ff;
        }

        .disabled-link {
            opacity: .55;
            cursor: not-allowed;
        }

        .soon-label {
            margin-left: auto;
            color: #aab9d4;
            font-size: 8px;
            letter-spacing: .5px;
        }

        .sidebar-footer {
            margin-top: auto;
            border-top: 1px solid #30415f;
            padding: 18px 5px 2px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-footer-icon {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            background: #1d3851;
            color: #61d6a1;
            border-radius: 9px;
        }

        .sidebar-footer strong,
        .sidebar-footer small {
            display: block;
        }

        .sidebar-footer strong {
            font-size: 10px;
        }

        .sidebar-footer small {
            font-size: 9px;
            color: #9caac1;
        }

        .main-content {
            flex: 1;
            min-width: 0;
        }

        .topbar {
            min-height: 76px;
            background: #fff;
            border-bottom: 1px solid #e9edf4;
            padding: 15px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .breadcrumb-text {
            font-size: 10px;
            color: #8b96aa;
            margin-bottom: 3px;
        }

        .topbar h4 {
            font-size: 17px;
            font-weight: 800;
            color: #17264a;
            margin: 0;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .system-status {
            color: #617089;
            font-size: 11px;
        }

        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #20b879;
            border-radius: 50%;
            margin-right: 5px;
        }

        .avatar {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #e9efff;
            color: #3158bd;
            font-size: 11px;
            font-weight: 800;
        }

        .content-area {
            padding: 30px 32px;
            max-width: 1600px;
            margin: auto;
        }

        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 28px;
        }

        .welcome-section h2 {
            font-size: 23px;
            font-weight: 800;
            color: #142348;
            margin-bottom: 5px;
        }

        .welcome-section p {
            font-size: 12px;
            color: #8490a5;
            margin: 0;
        }

        .date-display {
            padding: 10px 14px;
            background: #fff;
            border: 1px solid #e9edf4;
            border-radius: 9px;
            color: #617089;
            font-size: 11px;
            white-space: nowrap;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }

        .section-heading h5 {
            color: #172858;
            font-size: 14px;
            font-weight: 800;
            margin: 0;
        }

        .section-heading span {
            color: #8b96aa;
            font-size: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 27px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #e9edf4;
            border-radius: 13px;
            padding: 19px;
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 0;
            box-shadow: 0 3px 15px rgba(24, 42, 75, .025);
        }

        .stat-icon {
            width: 43px;
            height: 43px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 11px;
            font-size: 19px;
        }

        .icon-blue {
            background: #eaf0ff;
            color: #3563e9;
        }

        .icon-purple {
            background: #f1eaff;
            color: #8053c6;
        }

        .icon-cyan {
            background: #e5f8fc;
            color: #1595b1;
        }

        .icon-orange {
            background: #fff1e5;
            color: #e58a35;
        }

        .stat-label {
            color: #8490a5;
            font-size: 10px;
            margin-bottom: 4px;
        }

        .stat-value {
            color: #18294f;
            font-size: 23px;
            font-weight: 800;
            line-height: 1.2;
        }

        .stat-note {
            color: #9aa4b6;
            font-size: 9px;
            margin-top: 3px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(250px, .9fr);
            gap: 17px;
            align-items: start;
        }

        .panel {
            background: #fff;
            border: 1px solid #e9edf4;
            border-radius: 13px;
            padding: 20px;
            min-width: 0;
            box-shadow: 0 3px 15px rgba(24, 42, 75, .025);
        }

        .table {
            font-size: 11px;
            margin: 0;
        }

        .table thead th {
            background: #f7f9fc;
            color: #7f8ba1;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .4px;
            border-bottom: 1px solid #e9edf4;
            padding: 12px 10px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 13px 10px;
            color: #43516b;
            vertical-align: middle;
            border-color: #f0f2f7;
        }

        .borrower-name {
            color: #1b2b50;
            font-weight: 700;
        }

        .borrower-number {
            color: #929db0;
            font-size: 9px;
            margin-top: 3px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            background: #edf3ff;
            color: #3563c9;
            font-size: 9px;
            font-weight: 700;
            text-transform: capitalize;
        }

        .quick-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 0;
            text-decoration: none;
            color: #263655;
            border-bottom: 1px solid #f0f2f7;
        }

        .quick-link:last-child {
            border-bottom: 0;
        }

        .quick-link-icon {
            width: 37px;
            height: 37px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: #edf2ff;
            color: #3563e9;
            font-size: 16px;
        }

        .quick-link strong {
            display: block;
            font-size: 11px;
        }

        .quick-link small {
            display: block;
            color: #929db0;
            font-size: 9px;
            margin-top: 3px;
        }

        .quick-link .bi-chevron-right {
            margin-left: auto;
            color: #a5afbf;
            font-size: 11px;
        }

        .summary-box {
            background: #f5f8ff;
            border: 1px solid #e7edfc;
            border-radius: 11px;
            padding: 15px;
            margin-top: 17px;
        }

        .summary-box small {
            display: block;
            color: #7d8ba5;
            font-size: 10px;
        }

        .summary-box strong {
            display: block;
            color: #1c3265;
            font-size: 21px;
            margin-top: 4px;
        }

        .empty-state {
            text-align: center;
            color: #8b96aa;
            padding: 28px 10px !important;
        }

        @media (max-width: 1100px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .app-layout {
                display: block;
            }

            .sidebar {
                width: 100%;
                min-width: 0;
                min-height: auto;
                padding: 17px;
            }

            .sidebar-brand {
                padding-bottom: 10px;
            }

            .sidebar-navigation {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 2px 7px;
            }

            .sidebar-section-label {
                grid-column: 1 / -1;
            }

            .sidebar-footer {
                display: none;
            }

            .main-content {
                width: 100%;
            }

            .topbar {
                padding: 14px 18px;
            }

            .content-area {
                padding: 22px 17px;
            }

            .welcome-section {
                align-items: flex-start;
                flex-direction: column;
            }

            .date-display {
                white-space: normal;
            }
        }

        @media (max-width: 430px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .topbar-right .system-status {
                display: none;
            }

            .sidebar-navigation {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<div class="app-layout">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div>
                <div class="breadcrumb-text">OVERVIEW / DASHBOARD</div>
                <h4>Dashboard</h4>
            </div>

            <div class="topbar-right">
                <div class="system-status">
                    <span class="status-dot"></span>
                    PostgreSQL Connected
                </div>
                <div class="avatar">DB</div>
            </div>
        </header>

        <div class="content-area">

            <section class="welcome-section">
                <div>
                    <h2>Database Overview</h2>
                    <p>Monitor your borrowers, equipment, and borrowing activities.</p>
                </div>

                <div class="date-display">
                    <i class="bi bi-calendar3 me-2"></i>
                    <?= e($today) ?>
                </div>
            </section>

            <div class="section-heading">
                <h5>Part 1 · Weeks 4–5 Overview</h5>
                <span>Live PostgreSQL data</span>
            </div>

            <section class="stats-grid">

                <div class="stat-card">
                    <div class="stat-icon icon-blue">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <div class="stat-label">Total Borrowers</div>
                        <div class="stat-value"><?= number($totalBorrowers) ?></div>
                        <div class="stat-note">Registered borrowers</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon icon-purple">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div>
                        <div class="stat-label">Equipment Records</div>
                        <div class="stat-value"><?= number($totalEquipment) ?></div>
                        <div class="stat-note">Registered equipment types</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon icon-cyan">
                        <i class="bi bi-arrow-left-right"></i>
                    </div>
                    <div>
                        <div class="stat-label">Total Transactions</div>
                        <div class="stat-value"><?= number($totalTransactions) ?></div>
                        <div class="stat-note">Recorded borrow transactions</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon icon-orange">
                        <i class="bi bi-list-check"></i>
                    </div>
                    <div>
                        <div class="stat-label">Borrowed Item Records</div>
                        <div class="stat-value"><?= number($totalBorrowItems) ?></div>
                        <div class="stat-note">Items linked to transactions</div>
                    </div>
                </div>

            </section>

            <div class="dashboard-grid">

                <section class="panel">
                    <div class="section-heading">
                        <h5>Recent Borrowing Transactions</h5>
                        <span>Latest 6 records</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Borrower</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (empty($recentTransactions)): ?>
                                    <tr>
                                        <td colspan="4" class="empty-state">
                                            <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                            No borrowing transactions found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentTransactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <div class="borrower-name">
                                                    <?= e($transaction['full_name'] ?? 'Unknown borrower') ?>
                                                </div>
                                                <div class="borrower-number">
                                                    <?= e($transaction['borrower_no'] ?? 'No borrower number') ?>
                                                    · #<?= e($transaction['borrow_id']) ?>
                                                </div>
                                            </td>

                                            <td><?= e($transaction['borrow_date']) ?></td>
                                            <td><?= e($transaction['due_date']) ?></td>

                                            <td>
                                                <span class="status-badge">
                                                    <?= e($transaction['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel">
                    <div class="section-heading">
                        <h5>Quick Access</h5>
                        <span>Part 1 modules</span>
                    </div>

                    <a href="pages/borrowers.php" class="quick-link">
                        <div class="quick-link-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <strong>Borrowers Management</strong>
                            <small>Manage borrower records</small>
                        </div>
                        <i class="bi bi-chevron-right"></i>
                    </a>

                    <a href="pages/equipment.php" class="quick-link">
                        <div class="quick-link-icon">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div>
                            <strong>Equipment Inventory</strong>
                            <small>Manage equipment records</small>
                        </div>
                        <i class="bi bi-chevron-right"></i>
                    </a>

                    <a href="pages/transactions.php" class="quick-link">
                        <div class="quick-link-icon">
                            <i class="bi bi-arrow-left-right"></i>
                        </div>
                        <div>
                            <strong>Transactions & Items</strong>
                            <small>Manage borrowing activities</small>
                        </div>
                        <i class="bi bi-chevron-right"></i>
                    </a>

                    <div class="summary-box">
                        <small>
                            <i class="bi bi-box2 me-1"></i>
                            Total Available Equipment Quantity
                        </small>
                        <strong><?= number($availableEquipment) ?></strong>
                    </div>

                    <div class="summary-box">
                        <small>
                            <i class="bi bi-clock-history me-1"></i>
                            Transactions Not Yet Completed
                        </small>
                        <strong><?= number($activeTransactions) ?></strong>
                    </div>
                </section>

            </div>

            <div class="text-center mt-4">
                <small class="text-secondary">
                    Database Administration Practical System · PHP & PostgreSQL
                </small>
            </div>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
