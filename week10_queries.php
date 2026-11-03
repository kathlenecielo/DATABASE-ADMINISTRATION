
<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

require_once "../includes/db.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// CSRF protection.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// Available sections.
$view = $_GET["view"] ?? "overview";

$allowedViews = [
    "overview",
    "functions",
    "payments",
    "audit"
];

if (!in_array($view, $allowedViews, true)) {
    $view = "overview";
}

$stats = [
    "students" => 0,
    "payments" => 0,
    "accounts" => 0,
    "audit_logs" => 0
];

$students = [];
$payments = [];
$auditLogs = [];

$errorMessage = "";
$successMessage = "";

// Read notification messages.
if (isset($_GET["success"])) {
    $successMessage = "Payment amount updated successfully. The database trigger recorded the change.";
}

// Process payment update.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_payment"])) {

    try {

        if (
            !isset($_POST["csrf_token"]) ||
            !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
        ) {
            throw new RuntimeException("Invalid security token. Please refresh the page.");
        }

        $paymentId = filter_var(
            $_POST["payment_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        $newAmount = $_POST["amount"] ?? "";

        if (!$paymentId || !is_numeric($newAmount)) {
            throw new RuntimeException("Please provide a valid payment and amount.");
        }

        $newAmount = (float)$newAmount;

        if (!is_finite($newAmount) || $newAmount < 0) {
            throw new RuntimeException("The payment amount must be a valid non-negative number.");
        }

        // Confirm the payment exists.
        $checkPayment = $pdo->prepare("
            SELECT payment_id
            FROM week10_lab.payments
            WHERE payment_id = :payment_id
        ");

        $checkPayment->execute([
            ":payment_id" => $paymentId
        ]);

        if (!$checkPayment->fetch()) {
            throw new RuntimeException("The selected payment record does not exist.");
        }

        // Update payment amount.
        // PostgreSQL automatically fires trg_payment_audit.
        $updatePayment = $pdo->prepare("
            UPDATE week10_lab.payments
            SET amount = :amount
            WHERE payment_id = :payment_id
        ");

        $updatePayment->execute([
            ":amount" => $newAmount,
            ":payment_id" => $paymentId
        ]);

        header("Location: week10_queries.php?view=payments&success=1");
        exit;

    } catch (Throwable $error) {

        error_log("Week 10 Update Error: " . $error->getMessage());

        $errorMessage = $error->getMessage();

    }
}


// Retrieve Week 10 records.
try {

    // Dashboard statistics.
    $statsResult = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM week10_lab.students) AS students,
            (SELECT COUNT(*) FROM week10_lab.payments) AS payments,
            (SELECT COUNT(*) FROM week10_lab.student_accounts) AS accounts,
            (SELECT COUNT(*) FROM week10_lab.payment_audit) AS audit_logs
    ")->fetch(PDO::FETCH_ASSOC);

    if ($statsResult) {
        $stats = array_merge($stats, $statsResult);
    }


    // Function results.
    $students = $pdo->query("
        SELECT
            s.student_id,
            s.student_no,
            s.student_name,
            s.program,
            week10_lab.fn_total_paid(s.student_id) AS total_paid,
            week10_lab.fn_net_assessment(s.student_id) AS net_assessment,
            (
                week10_lab.fn_net_assessment(s.student_id)
                - week10_lab.fn_total_paid(s.student_id)
            ) AS balance
        FROM week10_lab.students s
        ORDER BY s.student_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);


    // Payment records.
    $payments = $pdo->query("
        SELECT
            p.payment_id,
            p.student_id,
            s.student_no,
            s.student_name,
            p.amount,
            p.payment_date,
            p.payment_method
        FROM week10_lab.payments p
        LEFT JOIN week10_lab.students s
            ON s.student_id = p.student_id
        ORDER BY p.payment_date DESC, p.payment_id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);


    // Audit history.
    $auditLogs = $pdo->query("
        SELECT
            a.audit_id,
            a.payment_id,
            a.action_type,
            a.old_amount,
            a.new_amount,
            a.changed_at,
            a.note,
            s.student_name
        FROM week10_lab.payment_audit a
        LEFT JOIN week10_lab.payments p
            ON p.payment_id = a.payment_id
        LEFT JOIN week10_lab.students s
            ON s.student_id = p.student_id
        ORDER BY a.changed_at DESC, a.audit_id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $error) {

    error_log("Week 10 Database Error: " . $error->getMessage());

    if ($errorMessage === "") {
        $errorMessage = $error->getMessage();
    }

}


// Page information.
$pageTitles = [
    "overview" => "Functions & Triggers Overview",
    "functions" => "SQL Functions",
    "payments" => "Payment Records",
    "audit" => "Payment Audit Trail"
];

$pageDescriptions = [
    "overview" => "Monitor payment records, student assessments, and automatic database auditing.",
    "functions" => "Calculate total payments, net assessments, and outstanding balances using PostgreSQL functions.",
    "payments" => "View payment records and update payment amounts to demonstrate database triggers.",
    "audit" => "Review payment changes automatically recorded by the PostgreSQL audit trigger."
];

$pageTitle = $pageTitles[$view];
$pageDescription = $pageDescriptions[$view];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Week 10 | Functions & Triggers</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        .week10-content {
            padding: 25px;
        }

        .week10-header {
            margin-bottom: 25px;
        }

        .week10-header h2 {
            font-weight: 750;
            color: #172033;
            margin-bottom: 8px;
        }

        .week10-header p {
            color: #778196;
            margin-bottom: 0;
        }

        .week10-stat {
            background: #fff;
            border: 1px solid #e9edf4;
            border-radius: 14px;
            padding: 21px;
            height: 100%;
            box-shadow: 0 3px 12px rgba(20, 35, 60, 0.04);
        }

        .week10-stat-label {
            color: #778196;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .week10-stat-value {
            color: #172033;
            font-size: 27px;
            font-weight: 750;
        }

        .week10-stat-icon {
            width: 43px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #edf3ff;
            color: #3157a4;
            font-size: 19px;
        }

        .week10-card {
            background: #fff;
            border: 1px solid #e9edf4;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(20, 35, 60, 0.04);
            overflow: hidden;
        }

        .week10-card-header {
            padding: 21px;
            border-bottom: 1px solid #edf0f5;
        }

        .week10-card-header h5 {
            color: #172033;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .week10-card-header p {
            color: #778196;
            font-size: 13px;
            margin: 0;
        }

        .week10-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            padding: 17px 21px;
            border-bottom: 1px solid #edf0f5;
        }

        .week10-tab {
            text-decoration: none;
            padding: 9px 15px;
            border-radius: 9px;
            background: #f5f7fb;
            color: #647086;
            font-size: 13px;
            font-weight: 650;
        }

        .week10-tab:hover {
            background: #eaf0fc;
            color: #3157a4;
        }

        .week10-tab.active {
            background: #172d5d;
            color: #fff;
        }

        .week10-table-wrapper {
            overflow-x: auto;
        }

        .week10-table {
            min-width: 750px;
            margin: 0;
        }

        .week10-table thead th {
            background: #f7f9fc;
            color: #647086;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .35px;
            padding: 15px;
            white-space: nowrap;
        }

        .week10-table tbody td {
            padding: 15px;
            font-size: 13px;
            color: #344054;
            vertical-align: middle;
        }

        .week10-empty {
            padding: 40px 20px;
            text-align: center;
            color: #778196;
        }

        .week10-empty i {
            display: block;
            font-size: 35px;
            margin-bottom: 10px;
        }

        .week10-function-card {
            padding: 20px;
            border: 1px solid #e9edf4;
            border-radius: 12px;
            height: 100%;
            background: #fff;
        }

        .week10-function-card h6 {
            font-weight: 700;
            color: #172033;
        }

        .week10-function-card code {
            display: inline-block;
            padding: 7px 10px;
            margin: 7px 0;
            border-radius: 7px;
            background: #f1f4fa;
            color: #3157a4;
        }

        .week10-action-form {
            display: flex;
            gap: 8px;
            align-items: center;
            min-width: 230px;
        }

        .week10-action-form input {
            max-width: 110px;
        }

        .week10-error {
            margin: 20px;
            padding: 16px;
            border-radius: 10px;
            background: #fff1f1;
            border: 1px solid #ffcaca;
            color: #a12626;
            overflow-wrap: anywhere;
        }

        @media(max-width: 768px) {

            .week10-content {
                padding: 16px;
            }

            .week10-stat {
                padding: 16px;
            }

            .week10-stat-value {
                font-size: 23px;
            }

        }

    </style>

</head>

<body>

<div class="app-layout">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="week10-content">

            <!-- HEADER -->

            <div class="week10-header">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                    <div>

                        <span class="badge text-bg-primary mb-2">
                            PART 5 · WEEK 10
                        </span>

                        <h2>Functions & Triggers</h2>

                        <p><?= e($pageDescription) ?></p>

                    </div>

                    <button
                        class="btn btn-outline-secondary btn-sm"
                        onclick="window.location.reload()"
                    >
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Refresh Data
                    </button>

                </div>

            </div>


            <!-- STATISTICS -->

            <div class="row g-3 mb-4">

                <div class="col-6 col-xl-3">

                    <div class="week10-stat">

                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <span class="week10-stat-label">Students</span>

                            <span class="week10-stat-icon">
                                <i class="bi bi-people"></i>
                            </span>

                        </div>

                        <div class="week10-stat-value">
                            <?= e($stats["students"]) ?>
                        </div>

                    </div>

                </div>


                <div class="col-6 col-xl-3">

                    <div class="week10-stat">

                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <span class="week10-stat-label">Payments</span>

                            <span class="week10-stat-icon">
                                <i class="bi bi-wallet2"></i>
                            </span>

                        </div>

                        <div class="week10-stat-value">
                            <?= e($stats["payments"]) ?>
                        </div>

                    </div>

                </div>


                <div class="col-6 col-xl-3">

                    <div class="week10-stat">

                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <span class="week10-stat-label">Accounts</span>

                            <span class="week10-stat-icon">
                                <i class="bi bi-person-vcard"></i>
                            </span>

                        </div>

                        <div class="week10-stat-value">
                            <?= e($stats["accounts"]) ?>
                        </div>

                    </div>

                </div>


                <div class="col-6 col-xl-3">

                    <div class="week10-stat">

                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <span class="week10-stat-label">Audit Logs</span>

                            <span class="week10-stat-icon">
                                <i class="bi bi-shield-check"></i>
                            </span>

                        </div>

                        <div class="week10-stat-value">
                            <?= e($stats["audit_logs"]) ?>
                        </div>

                    </div>

                </div>

            </div>


            <!-- MAIN CARD -->

            <div class="week10-card">

                <!-- NAVIGATION -->

                <div class="week10-tabs">

                    <a
                        href="?view=overview"
                        class="week10-tab <?= $view === 'overview' ? 'active' : '' ?>"
                    >
                        <i class="bi bi-grid me-1"></i>
                        Overview
                    </a>

                    <a
                        href="?view=functions"
                        class="week10-tab <?= $view === 'functions' ? 'active' : '' ?>"
                    >
                        <i class="bi bi-calculator me-1"></i>
                        SQL Functions
                    </a>

                    <a
                        href="?view=payments"
                        class="week10-tab <?= $view === 'payments' ? 'active' : '' ?>"
                    >
                        <i class="bi bi-wallet me-1"></i>
                        Payments
                    </a>

                    <a
                        href="?view=audit"
                        class="week10-tab <?= $view === 'audit' ? 'active' : '' ?>"
                    >
                        <i class="bi bi-journal-text me-1"></i>
                        Audit Trail
                    </a>

                </div>


                <div class="week10-card-header">

                    <h5><?= e($pageTitle) ?></h5>

                    <p><?= e($pageDescription) ?></p>

                </div>


                <!-- NOTIFICATIONS -->

                <?php if ($successMessage !== ""): ?>

                    <div class="alert alert-success m-3">
                        <i class="bi bi-check-circle me-2"></i>
                        <?= e($successMessage) ?>
                    </div>

                <?php endif; ?>


                <?php if ($errorMessage !== ""): ?>

                    <div class="week10-error">

                        <strong>
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Week 10 Error
                        </strong>

                        <p class="mb-0 mt-2">
                            <?= e($errorMessage) ?>
                        </p>

                    </div>

                <?php else: ?>


                    <!-- OVERVIEW -->

                    <?php if ($view === "overview"): ?>

                        <div class="p-4">

                            <div class="row g-3">

                                <div class="col-md-6">

                                    <div class="week10-function-card">

                                        <i class="bi bi-calculator fs-3 text-primary"></i>

                                        <h6 class="mt-3">
                                            Total Paid Function
                                        </h6>

                                        <code>fn_total_paid(student_id)</code>

                                        <p class="text-muted small mb-0">
                                            Calculates the total amount paid by a student
                                            using the SUM aggregate function.
                                        </p>

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <div class="week10-function-card">

                                        <i class="bi bi-cash-stack fs-3 text-primary"></i>

                                        <h6 class="mt-3">
                                            Net Assessment Function
                                        </h6>

                                        <code>fn_net_assessment(student_id)</code>

                                        <p class="text-muted small mb-0">
                                            Calculates the student's net assessment
                                            after deducting applicable discounts.
                                        </p>

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <div class="week10-function-card">

                                        <i class="bi bi-arrow-repeat fs-3 text-primary"></i>

                                        <h6 class="mt-3">
                                            Automatic Payment Trigger
                                        </h6>

                                        <code>trg_payment_audit</code>

                                        <p class="text-muted small mb-0">
                                            Automatically records payment insertions,
                                            updates, and deletions in the audit table.
                                        </p>

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <div class="week10-function-card">

                                        <i class="bi bi-shield-check fs-3 text-primary"></i>

                                        <h6 class="mt-3">
                                            Payment Audit Trail
                                        </h6>

                                        <code>payment_audit</code>

                                        <p class="text-muted small mb-0">
                                            Stores payment changes, old amounts,
                                            new amounts, timestamps, and notes.
                                        </p>

                                    </div>

                                </div>

                            </div>


                            <div class="alert alert-light border mt-4 mb-0">

                                <i class="bi bi-info-circle me-2"></i>

                                Use the Payments tab to update a payment amount.
                                PostgreSQL will automatically generate an audit
                                record through the trigger.

                            </div>

                        </div>


                    <!-- FUNCTIONS -->

                    <?php elseif ($view === "functions"): ?>

                        <div class="week10-table-wrapper">

                            <table class="table table-hover week10-table">

                                <thead>

                                    <tr>
                                        <th>#</th>
                                        <th>Student Number</th>
                                        <th>Student Name</th>
                                        <th>Program</th>
                                        <th>Net Assessment</th>
                                        <th>Total Paid</th>
                                        <th>Balance</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php if (empty($students)): ?>

                                        <tr>
                                            <td colspan="7">
                                                <div class="week10-empty">
                                                    <i class="bi bi-inbox"></i>
                                                    No student records found.
                                                </div>
                                            </td>
                                        </tr>

                                    <?php else: ?>

                                        <?php foreach ($students as $index => $student): ?>

                                            <tr>

                                                <td><?= $index + 1 ?></td>

                                                <td><?= e($student["student_no"]) ?></td>

                                                <td class="fw-semibold">
                                                    <?= e($student["student_name"]) ?>
                                                </td>

                                                <td><?= e($student["program"]) ?></td>

                                                <td>
                                                    ₱<?= number_format((float)$student["net_assessment"], 2) ?>
                                                </td>

                                                <td>
                                                    ₱<?= number_format((float)$student["total_paid"], 2) ?>
                                                </td>

                                                <td class="<?= (float)$student["balance"] > 0 ? 'text-danger' : 'text-success' ?> fw-semibold">
                                                    ₱<?= number_format((float)$student["balance"], 2) ?>
                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </tbody>

                            </table>

                        </div>


                    <!-- PAYMENTS -->

                    <?php elseif ($view === "payments"): ?>

                        <div class="week10-table-wrapper">

                            <table class="table table-hover week10-table">

                                <thead>

                                    <tr>
                                        <th>Payment ID</th>
                                        <th>Student Number</th>
                                        <th>Student Name</th>
                                        <th>Payment Date</th>
                                        <th>Payment Method</th>
                                        <th>Current Amount</th>
                                        <th>Update Amount</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php if (empty($payments)): ?>

                                        <tr>
                                            <td colspan="7">
                                                <div class="week10-empty">
                                                    <i class="bi bi-wallet2"></i>
                                                    No payment records found.
                                                </div>
                                            </td>
                                        </tr>

                                    <?php else: ?>

                                        <?php foreach ($payments as $payment): ?>

                                            <tr>

                                                <td>
                                                    #<?= e($payment["payment_id"]) ?>
                                                </td>

                                                <td>
                                                    <?= e($payment["student_no"]) ?>
                                                </td>

                                                <td class="fw-semibold">
                                                    <?= e($payment["student_name"]) ?>
                                                </td>

                                                <td>
                                                    <?= e($payment["payment_date"]) ?>
                                                </td>

                                                <td>
                                                    <?= e($payment["payment_method"]) ?>
                                                </td>

                                                <td class="fw-semibold">
                                                    ₱<?= number_format((float)$payment["amount"], 2) ?>
                                                </td>

                                                <td>

                                                    <form
                                                        method="POST"
                                                        class="week10-action-form"
                                                        onsubmit="return confirm('Update this payment amount? The database trigger will record the change.');"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= e($csrfToken) ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="payment_id"
                                                            value="<?= e($payment["payment_id"]) ?>"
                                                        >

                                                        <input
                                                            type="number"
                                                            name="amount"
                                                            class="form-control form-control-sm"
                                                            min="0"
                                                            step="0.01"
                                                            value="<?= e($payment["amount"]) ?>"
                                                            required
                                                        >

                                                        <button
                                                            type="submit"
                                                            name="update_payment"
                                                            value="1"
                                                            class="btn btn-primary btn-sm"
                                                        >
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>

                                                    </form>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </tbody>

                            </table>

                        </div>


                    <!-- AUDIT TRAIL -->

                    <?php elseif ($view === "audit"): ?>

                        <div class="week10-table-wrapper">

                            <table class="table table-hover week10-table">

                                <thead>

                                    <tr>
                                        <th>Audit ID</th>
                                        <th>Payment ID</th>
                                        <th>Student</th>
                                        <th>Action</th>
                                        <th>Old Amount</th>
                                        <th>New Amount</th>
                                        <th>Date Changed</th>
                                        <th>Note</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php if (empty($auditLogs)): ?>

                                        <tr>
                                            <td colspan="8">

                                                <div class="week10-empty">

                                                    <i class="bi bi-journal-x"></i>

                                                    <h6 class="fw-bold">
                                                        No Audit Records Yet
                                                    </h6>

                                                    <p class="mb-0">
                                                        Payment changes will automatically
                                                        appear here after the trigger runs.
                                                    </p>

                                                </div>

                                            </td>
                                        </tr>

                                    <?php else: ?>

                                        <?php foreach ($auditLogs as $log): ?>

                                            <tr>

                                                <td>
                                                    #<?= e($log["audit_id"]) ?>
                                                </td>

                                                <td>
                                                    #<?= e($log["payment_id"]) ?>
                                                </td>

                                                <td>
                                                    <?= e($log["student_name"] ?? "Student record unavailable") ?>
                                                </td>

                                                <td>

                                                    <?php
                                                        $action = strtoupper((string)$log["action_type"]);

                                                        $badgeClass = match ($action) {
                                                            "INSERT" => "success",
                                                            "UPDATE" => "primary",
                                                            "DELETE" => "danger",
                                                            default => "secondary"
                                                        };
                                                    ?>

                                                    <span class="badge text-bg-<?= e($badgeClass) ?>">
                                                        <?= e($action) ?>
                                                    </span>

                                                </td>

                                                <td>
                                                    <?= $log["old_amount"] === null
                                                        ? "—"
                                                        : "₱" . number_format((float)$log["old_amount"], 2) ?>
                                                </td>

                                                <td>
                                                    <?= $log["new_amount"] === null
                                                        ? "—"
                                                        : "₱" . number_format((float)$log["new_amount"], 2) ?>
                                                </td>

                                                <td>
                                                    <?= e($log["changed_at"]) ?>
                                                </td>

                                                <td>
                                                    <?= e($log["note"]) ?>
                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                <?php endif; ?>


                <!-- FOOTER -->

                <div class="border-top px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">

                    <span class="text-muted small">
                        Week 10 Database Administration Laboratory
                    </span>

                    <span class="text-muted small">
                        PostgreSQL · Functions & Triggers
                    </span>

                </div>

            </div>


            <div class="text-muted small mt-4">

                <i class="bi bi-info-circle me-1"></i>

                Records are retrieved directly from the week10_lab schema.
                Changes made through the Payments tab are automatically
                recorded by the PostgreSQL trigger.

            </div>

        </div>

    </main>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
