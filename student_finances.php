
<?php
session_start();

require_once __DIR__ . '/../includes/db.php';

function escape($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? '');

$students = [];

$totalStudents = 0;
$totalAssessed = 0;
$totalDiscounts = 0;
$totalNetAssessment = 0;
$totalPaid = 0;
$totalBalance = 0;

try {

    // FETCH FINANCIAL RECORDS AND CALCULATE BALANCES
    $sql = "
        SELECT
            s.student_id,
            s.student_no,
            s.student_name,
            s.program,

            a.account_id,
            a.assessed_amount,
            a.discount_amount,
            a.account_status,

            COALESCE(
                week10_lab.fn_net_assessment(s.student_id),
                0
            ) AS net_assessment,

            COALESCE(
                week10_lab.fn_total_paid(s.student_id),
                0
            ) AS total_paid,

            COALESCE(
                week10_lab.fn_student_balance(s.student_id),
                0
            ) AS outstanding_balance

        FROM week10_lab.students s

        LEFT JOIN week10_lab.student_accounts a
            ON s.student_id = a.student_id
    ";

    $params = [];

    if ($search !== '') {

        $sql .= "
            WHERE
                s.student_no ILIKE :student_no
                OR s.student_name ILIKE :student_name
                OR s.program ILIKE :program
                OR a.account_status ILIKE :account_status
        ";

        $term = '%' . $search . '%';

        $params = [
            'student_no' => $term,
            'student_name' => $term,
            'program' => $term,
            'account_status' => $term
        ];
    }

    $sql .= "
        ORDER BY s.student_name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // CALCULATE DASHBOARD TOTALS
    foreach ($students as $student) {

        $totalStudents++;

        $totalAssessed += (float)($student['assessed_amount'] ?? 0);
        $totalDiscounts += (float)($student['discount_amount'] ?? 0);
        $totalNetAssessment += (float)$student['net_assessment'];
        $totalPaid += (float)$student['total_paid'];
        $totalBalance += (float)$student['outstanding_balance'];
    }

} catch (PDOException $e) {

    error_log($e->getMessage());

    die(
        'Unable to load student financial records. ' .
        'Please check your Week 10 tables, functions, and app_user permissions.'
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Finances | CampusCore</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        body {
            background: #f4f6fb;
            font-family: Arial, sans-serif;
            color: #202b45;
        }

        .main-content {
            margin-left: 260px;
            padding: 35px;
            min-height: 100vh;
        }

        .page-title {
            color: #101b3d;
            font-weight: 700;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #e9edf5;
            border-radius: 14px;
            padding: 22px;
            height: 100%;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #edf2ff;
            color: #263e83;
            font-size: 21px;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #172858;
        }

        .stat-label {
            color: #69758d;
            font-size: 13px;
        }

        .content-card {
            background: #fff;
            border: 1px solid #e9edf5;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(16, 27, 61, .03);
        }

        .table thead th {
            background: #f7f8fc;
            color: #65708a;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: 15px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #34415e;
        }

        .student-name {
            font-weight: 600;
            color: #172858;
        }

        .money {
            white-space: nowrap;
            font-weight: 600;
        }

        .paid {
            color: #16845b;
        }

        .balance {
            color: #c47a19;
        }

        .search-input {
            min-width: 230px;
            border-radius: 9px;
            padding: 10px 13px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-paid {
            background: #e5f7ee;
            color: #16845b;
        }

        .status-partial {
            background: #fff3dc;
            color: #a76b13;
        }

        .status-unpaid {
            background: #feecec;
            color: #c03939;
        }

        .status-unknown {
            background: #eef0f5;
            color: #69758d;
        }

        .empty-state {
            padding: 55px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 45px;
            color: #aab6d2;
        }

        .financial-summary {
            background: #f0f4ff;
            border: 1px solid #dfe7ff;
            border-radius: 12px;
            padding: 17px;
        }

        @media (max-width: 768px) {

            .main-content {
                margin-left: 0;
                padding: 20px 12px;
            }

            .content-card {
                padding: 15px;
            }

            .stat-number {
                font-size: 21px;
            }

            .search-input {
                min-width: 0;
                width: 100%;
            }

        }

    </style>

</head>

<body>

    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">

        <!-- HEADER -->

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">

            <div>

                <h2 class="page-title mb-1">
                    Student Finances
                </h2>

                <p class="text-secondary mb-0">
                    Monitor student assessments, payments, discounts, and outstanding balances.
                </p>

            </div>

        </div>

        <!-- FINANCIAL SUMMARY -->

        <div class="row g-3 mb-4">

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>

                            <div class="stat-label mb-1">
                                Total Assessed
                            </div>

                            <div class="stat-number">
                                ₱<?= number_format($totalAssessed, 2) ?>
                            </div>

                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>

                    </div>

                    <small class="text-secondary">
                        Total assessed amounts
                    </small>

                </div>

            </div>

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>

                            <div class="stat-label mb-1">
                                Total Discounts
                            </div>

                            <div class="stat-number">
                                ₱<?= number_format($totalDiscounts, 2) ?>
                            </div>

                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-percent"></i>
                        </div>

                    </div>

                    <small class="text-secondary">
                        Discounts applied to accounts
                    </small>

                </div>

            </div>

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>

                            <div class="stat-label mb-1">
                                Total Collected
                            </div>

                            <div class="stat-number paid">
                                ₱<?= number_format($totalPaid, 2) ?>
                            </div>

                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-cash-stack"></i>
                        </div>

                    </div>

                    <small class="text-secondary">
                        Payments received from students
                    </small>

                </div>

            </div>

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>

                            <div class="stat-label mb-1">
                                Outstanding Balance
                            </div>

                            <div class="stat-number balance">
                                ₱<?= number_format($totalBalance, 2) ?>
                            </div>

                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-wallet2"></i>
                        </div>

                    </div>

                    <small class="text-secondary">
                        Remaining student balances
                    </small>

                </div>

            </div>

        </div>

        <!-- FINANCIAL SUMMARY INFORMATION -->

        <div class="financial-summary mb-4">

            <div class="d-flex align-items-start gap-3">

                <i class="bi bi-info-circle fs-4 text-primary"></i>

                <div>

                    <strong class="text-dark">
                        Financial Overview
                    </strong>

                    <p class="small text-secondary mb-1 mt-1">
                        Net assessment represents the assessed amount after discounts.
                        Outstanding balance represents the remaining amount after payments.
                    </p>

                    <div class="fw-semibold text-primary">
                        Total Net Assessment:
                        ₱<?= number_format($totalNetAssessment, 2) ?>
                    </div>

                </div>

            </div>

        </div>

        <!-- STUDENT FINANCIAL DIRECTORY -->

        <div class="content-card">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

                <div>

                    <h5 class="fw-bold mb-1">
                        Student Financial Records
                    </h5>

                    <p class="text-secondary small mb-0">
                        Individual student accounts and payment summaries.
                    </p>

                </div>

                <form method="GET" class="d-flex gap-2">

                    <input
                        type="search"
                        name="search"
                        class="form-control search-input"
                        placeholder="Search student or status..."
                        value="<?= escape($search) ?>"
                    >

                    <button
                        type="submit"
                        class="btn btn-outline-primary"
                        title="Search"
                    >
                        <i class="bi bi-search"></i>
                    </button>

                    <?php if ($search !== ''): ?>

                        <a
                            href="student_finances.php"
                            class="btn btn-outline-secondary"
                            title="Clear search"
                        >
                            <i class="bi bi-x-lg"></i>
                        </a>

                    <?php endif; ?>

                </form>

            </div>

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>Student</th>
                            <th>Program</th>
                            <th>Assessed</th>
                            <th>Discount</th>
                            <th>Net Assessment</th>
                            <th>Total Paid</th>
                            <th>Balance</th>
                            <th>Account Status</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (count($students) > 0): ?>

                            <?php foreach ($students as $student): ?>

                                <?php

                                $accountStatus = trim(
                                    (string)($student['account_status'] ?? '')
                                );

                                $normalizedStatus = strtolower($accountStatus);

                                if ($accountStatus === '') {
                                    $statusClass = 'status-unknown';
                                    $displayStatus = 'No Account';
                                } elseif (in_array($normalizedStatus, ['paid', 'fully paid', 'completed'], true)) {
                                    $statusClass = 'status-paid';
                                    $displayStatus = $accountStatus;
                                } elseif (in_array($normalizedStatus, ['partial', 'partially paid'], true)) {
                                    $statusClass = 'status-partial';
                                    $displayStatus = $accountStatus;
                                } elseif (in_array($normalizedStatus, ['unpaid', 'pending', 'outstanding'], true)) {
                                    $statusClass = 'status-unpaid';
                                    $displayStatus = $accountStatus;
                                } else {
                                    $statusClass = 'status-unknown';
                                    $displayStatus = $accountStatus;
                                }

                                ?>

                                <tr>

                                    <td>

                                        <div class="student-name">
                                            <?= escape($student['student_name']) ?>
                                        </div>

                                        <small class="text-secondary">
                                            <?= escape($student['student_no']) ?>
                                        </small>

                                    </td>

                                    <td>
                                        <?= escape($student['program']) ?>
                                    </td>

                                    <td class="money">
                                        ₱<?= number_format(
                                            (float)($student['assessed_amount'] ?? 0),
                                            2
                                        ) ?>
                                    </td>

                                    <td class="money">
                                        ₱<?= number_format(
                                            (float)($student['discount_amount'] ?? 0),
                                            2
                                        ) ?>
                                    </td>

                                    <td class="money">
                                        ₱<?= number_format(
                                            (float)$student['net_assessment'],
                                            2
                                        ) ?>
                                    </td>

                                    <td class="money paid">
                                        ₱<?= number_format(
                                            (float)$student['total_paid'],
                                            2
                                        ) ?>
                                    </td>

                                    <td class="money balance">
                                        ₱<?= number_format(
                                            (float)$student['outstanding_balance'],
                                            2
                                        ) ?>
                                    </td>

                                    <td>

                                        <span class="status-badge <?= escape($statusClass) ?>">
                                            <?= escape($displayStatus) ?>
                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="8">

                                    <div class="empty-state">

                                        <div class="empty-icon mb-3">
                                            <i class="bi bi-wallet2"></i>
                                        </div>

                                        <h5 class="fw-bold">
                                            No Financial Records Found
                                        </h5>

                                        <p class="text-secondary mb-0">

                                            <?= $search !== ''
                                                ? 'No student financial records matched your search.'
                                                : 'There are currently no financial student records.' ?>

                                        </p>

                                    </div>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">

                <small class="text-secondary">
                    Showing <?= count($students) ?> student record(s)
                </small>

                <small class="text-secondary">
                    Financial data from Week 10 database
                </small>

            </div>

        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
