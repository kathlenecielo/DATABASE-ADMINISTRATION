
<?php
session_start();

require_once __DIR__ . '/../includes/db.php';

function escape($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

try {

    // FETCH PAYMENT RECORDS
    $sql = "
        SELECT
            p.payment_id,
            p.student_id,
            s.student_no,
            s.student_name,
            s.program,
            p.amount,
            p.payment_date,
            p.payment_method,
            COUNT(a.audit_id) AS audit_count
        FROM week10_lab.payments p
        INNER JOIN week10_lab.students s
            ON p.student_id = s.student_id
        LEFT JOIN week10_lab.payment_audit a
            ON p.payment_id = a.payment_id
    ";

    $params = [];

    if ($search !== '') {

        $sql .= "
            WHERE
                s.student_no ILIKE :student_no
                OR s.student_name ILIKE :student_name
                OR s.program ILIKE :program
                OR p.payment_method ILIKE :payment_method
                OR CAST(p.payment_id AS TEXT) ILIKE :payment_id
        ";

        $term = '%' . $search . '%';

        $params = [
            'student_no' => $term,
            'student_name' => $term,
            'program' => $term,
            'payment_method' => $term,
            'payment_id' => $term
        ];
    }

    $sql .= "
        GROUP BY
            p.payment_id,
            p.student_id,
            s.student_no,
            s.student_name,
            s.program,
            p.amount,
            p.payment_date,
            p.payment_method
        ORDER BY
            p.payment_date DESC,
            p.payment_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // PAYMENT STATISTICS
    $statsStmt = $pdo->query("
        SELECT
            COUNT(*) AS total_transactions,
            COALESCE(SUM(amount), 0) AS total_collected,
            COUNT(DISTINCT student_id) AS students_with_payments
        FROM week10_lab.payments
    ");

    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    $totalTransactions = (int)$stats['total_transactions'];
    $totalCollected = (float)$stats['total_collected'];
    $studentsWithPayments = (int)$stats['students_with_payments'];

    // AUDIT LOG COUNT
    $auditCount = (int)$pdo->query("
        SELECT COUNT(*)
        FROM week10_lab.payment_audit
    ")->fetchColumn();

} catch (PDOException $e) {

    error_log($e->getMessage());

    die('Unable to load payment records. Please check your Week 10 database tables and permissions.');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Payments Management | CampusCore</title>

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
            font-size: 27px;
            font-weight: 700;
            color: #172858;
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
            letter-spacing: .5px;
            padding: 15px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 16px 15px;
            vertical-align: middle;
            color: #34415e;
        }

        .search-input {
            min-width: 230px;
            border-radius: 9px;
            padding: 10px 13px;
        }

        .btn-primary {
            background: #172858;
            border-color: #172858;
            border-radius: 9px;
        }

        .btn-primary:hover {
            background: #263e83;
            border-color: #263e83;
        }

        .student-name {
            font-weight: 600;
            color: #172858;
        }

        .payment-amount {
            font-weight: 700;
            color: #16845b;
            white-space: nowrap;
        }

        .method-badge {
            background: #f0f4ff;
            color: #263e83;
            border: 1px solid #dfe7ff;
            padding: 7px 11px;
            border-radius: 20px;
            font-size: 12px;
            white-space: nowrap;
        }

        .empty-state {
            padding: 55px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 45px;
            color: #aab6d2;
        }

        .action-btn {
            width: 34px;
            height: 34px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            border-radius: 8px;
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
                font-size: 23px;
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
                    Payments Management
                </h2>

                <p class="text-secondary mb-0">
                    Monitor student payments and transaction history.
                </p>

            </div>

            <a href="add_payment.php" class="btn btn-primary px-4 py-2">

                <i class="bi bi-plus-lg me-2"></i>
                Record Payment

            </a>

        </div>

        <!-- STATUS MESSAGES -->

        <?php if ($status === 'added'): ?>

            <div class="alert alert-success alert-dismissible fade show">

                <i class="bi bi-check-circle me-2"></i>
                Payment successfully recorded.

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php elseif ($status === 'updated'): ?>

            <div class="alert alert-success alert-dismissible fade show">

                <i class="bi bi-check-circle me-2"></i>
                Payment successfully updated.

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>

        <!-- STATISTICS -->

        <div class="row g-3 mb-4">

            <div class="col-md-4">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>

                            <p class="text-secondary mb-1">
                                Total Collected
                            </p>

                            <div class="stat-number">
                                ₱<?= number_format($totalCollected, 2) ?>
                            </div>

                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-cash-stack"></i>
                        </div>

                    </div>

                    <small class="text-secondary">
                        Total recorded payment amounts
                    </small>

                </div>

            </div>

            <div class="col-md-4">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>

                            <p class="text-secondary mb-1">
                                Transactions
                            </p>

                            <div class="stat-number">
                                <?= number_format($totalTransactions) ?>
                            </div>

                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-receipt"></i>
                        </div>

                    </div>

                    <small class="text-secondary">
                        Total payment records
                    </small>

                </div>

            </div>

            <div class="col-md-4">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>

                            <p class="text-secondary mb-1">
                                Students With Payments
                            </p>

                            <div class="stat-number">
                                <?= number_format($studentsWithPayments) ?>
                            </div>

                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-people"></i>
                        </div>

                    </div>

                    <small class="text-secondary">
                        Unique students with recorded payments
                    </small>

                </div>

            </div>

        </div>

        <!-- PAYMENT DIRECTORY -->

        <div class="content-card">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

                <div>

                    <h5 class="fw-bold mb-1">
                        Payment Transactions
                    </h5>

                    <p class="text-secondary small mb-0">
                        Review recorded payments and their transaction details.
                    </p>

                </div>

                <form method="GET" class="d-flex gap-2">

                    <input
                        type="search"
                        name="search"
                        class="form-control search-input"
                        placeholder="Search payments..."
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
                            href="payments.php"
                            class="btn btn-outline-secondary"
                            title="Clear search"
                        >
                            <i class="bi bi-x-lg"></i>
                        </a>

                    <?php endif; ?>

                </form>

            </div>

            <!-- TABLE -->

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>Transaction ID</th>
                            <th>Student</th>
                            <th>Program</th>
                            <th>Amount</th>
                            <th>Payment Date</th>
                            <th>Payment Method</th>
                            <th>Audit Records</th>
                            <th class="text-center">Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (count($payments) > 0): ?>

                            <?php foreach ($payments as $payment): ?>

                                <tr>

                                    <td>
                                        <span class="fw-bold">
                                            #<?= (int)$payment['payment_id'] ?>
                                        </span>
                                    </td>

                                    <td>

                                        <div class="student-name">
                                            <?= escape($payment['student_name']) ?>
                                        </div>

                                        <small class="text-secondary">
                                            <?= escape($payment['student_no']) ?>
                                        </small>

                                    </td>

                                    <td>

                                        <span class="badge bg-light text-primary border">
                                            <?= escape($payment['program']) ?>
                                        </span>

                                    </td>

                                    <td>

                                        <span class="payment-amount">
                                            ₱<?= number_format((float)$payment['amount'], 2) ?>
                                        </span>

                                    </td>

                                    <td>
                                        <?= escape(date(
                                            'M d, Y',
                                            strtotime($payment['payment_date'])
                                        )) ?>
                                    </td>

                                    <td>

                                        <span class="method-badge">
                                            <?= escape($payment['payment_method']) ?>
                                        </span>

                                    </td>

                                    <td>

                                        <?php if ((int)$payment['audit_count'] > 0): ?>

                                            <span class="badge bg-success-subtle text-success">
                                                <?= (int)$payment['audit_count'] ?> record(s)
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-light text-secondary border">
                                                No changes
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <!-- ACTIONS -->

                                    <td class="text-center">

                                        <a
                                            href="edit_payment.php?id=<?= (int)$payment['payment_id'] ?>"
                                            class="btn btn-sm btn-outline-primary action-btn"
                                            title="Edit Payment"
                                        >
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="8">

                                    <div class="empty-state">

                                        <div class="empty-icon mb-3">
                                            <i class="bi bi-receipt-cutoff"></i>
                                        </div>

                                        <h5 class="fw-bold">
                                            No Payment Records Found
                                        </h5>

                                        <p class="text-secondary mb-3">

                                            <?= $search !== ''
                                                ? 'No transactions matched your search.'
                                                : 'There are no recorded payments yet.' ?>

                                        </p>

                                        <?php if ($search === ''): ?>

                                            <a
                                                href="add_payment.php"
                                                class="btn btn-primary"
                                            >

                                                <i class="bi bi-plus-lg me-2"></i>
                                                Record First Payment

                                            </a>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <!-- FOOTER -->

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">

                <small class="text-secondary">

                    Showing <?= count($payments) ?>
                    transaction(s)

                </small>

                <small class="text-secondary">

                    <?= number_format($auditCount) ?>
                    payment audit record(s)

                </small>

            </div>

        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
