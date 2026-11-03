
<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

require_once "../includes/db.php";

// Escape output safely.
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Available SQL activities.
$view = $_GET["view"] ?? "joins";

$allowedViews = [
    "joins",
    "union",
    "intersect",
    "except",
    "functions"
];

if (!in_array($view, $allowedViews, true)) {
    $view = "joins";
}

// Default values.
$records = [];
$columns = [];
$pageTitle = "";
$pageDescription = "";
$errorMessage = "";

$stats = [
    "total_students" => 0,
    "total_subjects" => 0,
    "total_enrollments" => 0,
    "total_payments" => 0
];

// Database operations.
try {

    // Dashboard statistics.
    $statsResult = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM week07_report.students) AS total_students,
            (SELECT COUNT(*) FROM week07_report.subjects) AS total_subjects,
            (SELECT COUNT(*) FROM week07_report.enrollments) AS total_enrollments,
            (SELECT COUNT(*) FROM week07_report.payments) AS total_payments
    ")->fetch(PDO::FETCH_ASSOC);

    if ($statsResult !== false) {
        $stats = array_merge($stats, $statsResult);
    }

    switch ($view) {

        // ==========================================
        // INNER JOIN AND LEFT JOIN
        // ==========================================

        case "joins":

            $pageTitle = "Student Enrollment and Payment Records";

            $pageDescription = "Combine student, subject, enrollment, and payment information using JOIN operations.";

            $columns = [
                "student_no" => "Student Number",
                "student_name" => "Student Name",
                "subject_code" => "Subject Code",
                "subject_name" => "Subject Name",
                "semester" => "Semester",
                "total_paid" => "Total Paid"
            ];

            $sql = "
                SELECT
                    s.student_no,
                    s.student_name,
                    sub.subject_code,
                    sub.subject_name,
                    en.semester,
                    COALESCE(payments.total_paid, 0) AS total_paid

                FROM week07_report.enrollments en

                INNER JOIN week07_report.students s
                    ON s.student_id = en.student_id

                INNER JOIN week07_report.subjects sub
                    ON sub.subject_id = en.subject_id

                LEFT JOIN (
                    SELECT
                        student_id,
                        SUM(amount) AS total_paid
                    FROM week07_report.payments
                    GROUP BY student_id
                ) payments
                    ON payments.student_id = s.student_id

                ORDER BY
                    s.student_name ASC,
                    sub.subject_code ASC
            ";

            $records = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            break;


        // ==========================================
        // UNION
        // ==========================================

        case "union":

            $pageTitle = "UNION Operation";

            $pageDescription = "Combine student records from enrollment and payment records.";

            $columns = [
                "student_no" => "Student Number",
                "student_name" => "Student Name",
                "record_source" => "Record Source"
            ];

            $sql = "
                SELECT
                    s.student_no,
                    s.student_name,
                    'Enrollment' AS record_source

                FROM week07_report.students s

                INNER JOIN week07_report.enrollments en
                    ON en.student_id = s.student_id

                UNION

                SELECT
                    s.student_no,
                    s.student_name,
                    'Payment' AS record_source

                FROM week07_report.students s

                INNER JOIN week07_report.payments p
                    ON p.student_id = s.student_id

                ORDER BY
                    student_name ASC,
                    record_source ASC
            ";

            $records = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            break;


        // ==========================================
        // INTERSECT
        // ==========================================

        case "intersect":

            $pageTitle = "INTERSECT Operation";

            $pageDescription = "Find students who have both enrollment records and payment records.";

            $columns = [
                "student_no" => "Student Number",
                "student_name" => "Student Name"
            ];

            $sql = "
                SELECT
                    s.student_no,
                    s.student_name

                FROM week07_report.students s

                INNER JOIN week07_report.enrollments en
                    ON en.student_id = s.student_id

                INTERSECT

                SELECT
                    s.student_no,
                    s.student_name

                FROM week07_report.students s

                INNER JOIN week07_report.payments p
                    ON p.student_id = s.student_id

                ORDER BY student_name ASC
            ";

            $records = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            break;


        // ==========================================
        // EXCEPT
        // ==========================================

        case "except":

            $pageTitle = "EXCEPT Operation";

            $pageDescription = "Find students with enrollment records who do not have payment records.";

            $columns = [
                "student_no" => "Student Number",
                "student_name" => "Student Name"
            ];

            $sql = "
                SELECT
                    s.student_no,
                    s.student_name

                FROM week07_report.students s

                INNER JOIN week07_report.enrollments en
                    ON en.student_id = s.student_id

                EXCEPT

                SELECT
                    s.student_no,
                    s.student_name

                FROM week07_report.students s

                INNER JOIN week07_report.payments p
                    ON p.student_id = s.student_id

                ORDER BY student_name ASC
            ";

            $records = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            break;


        // ==========================================
        // SQL FUNCTIONS AND AGGREGATES
        // ==========================================

        case "functions":

            $pageTitle = "SQL Functions and Aggregates";

            $pageDescription = "Use string functions, NULL handling, and aggregate functions to analyze student payment records.";

            $columns = [
                "student_no" => "Student Number",
                "student_name" => "Student Name",
                "uppercase_name" => "Uppercase Name",
                "payment_count" => "Payment Count",
                "total_paid" => "Total Paid",
                "average_payment" => "Average Payment"
            ];

            $sql = "
                SELECT
                    s.student_no,
                    s.student_name,
                    UPPER(s.student_name) AS uppercase_name,
                    COUNT(p.payment_id) AS payment_count,
                    COALESCE(SUM(p.amount), 0) AS total_paid,
                    COALESCE(ROUND(AVG(p.amount), 2), 0) AS average_payment

                FROM week07_report.students s

                LEFT JOIN week07_report.payments p
                    ON p.student_id = s.student_id

                GROUP BY
                    s.student_id,
                    s.student_no,
                    s.student_name

                ORDER BY s.student_name ASC
            ";

            $records = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            break;
    }

} catch (Throwable $error) {

    error_log("Week 7 Error: " . $error->getMessage());

    $errorMessage = $error->getMessage();

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Week 7 | Joins, Sets and Functions</title>

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

        .week7-content {
            padding: 25px;
        }

        .week7-header {
            margin-bottom: 25px;
        }

        .week7-header h2 {
            font-weight: 750;
            color: #172033;
            margin-bottom: 8px;
        }

        .week7-header p {
            color: #778196;
            margin-bottom: 0;
        }

        .week7-stat {
            background: #ffffff;
            border: 1px solid #e9edf4;
            border-radius: 14px;
            padding: 22px;
            height: 100%;
            box-shadow: 0 3px 12px rgba(20, 35, 60, 0.04);
        }

        .week7-stat-label {
            color: #778196;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .week7-stat-value {
            color: #172033;
            font-size: 27px;
            font-weight: 750;
        }

        .week7-stat-icon {
            width: 43px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 19px;
            background: #edf3ff;
            color: #3157a4;
        }

        .week7-card {
            background: #ffffff;
            border: 1px solid #e9edf4;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(20, 35, 60, 0.04);
            overflow: hidden;
        }

        .week7-card-header {
            padding: 22px;
            border-bottom: 1px solid #edf0f5;
        }

        .week7-card-header h5 {
            font-weight: 700;
            color: #172033;
            margin-bottom: 5px;
        }

        .week7-card-header p {
            color: #778196;
            font-size: 13px;
            margin-bottom: 0;
        }

        .week7-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            padding: 18px 22px;
            border-bottom: 1px solid #edf0f5;
        }

        .week7-tab {
            text-decoration: none;
            padding: 9px 15px;
            border-radius: 9px;
            color: #647086;
            font-size: 13px;
            font-weight: 650;
            background: #f5f7fb;
            transition: 0.2s;
        }

        .week7-tab:hover {
            background: #eaf0fc;
            color: #3157a4;
        }

        .week7-tab.active {
            background: #172d5d;
            color: #ffffff;
        }

        .week7-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .week7-table {
            margin-bottom: 0;
            min-width: 650px;
        }

        .week7-table thead th {
            background: #f7f9fc;
            color: #647086;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 15px 18px;
            white-space: nowrap;
        }

        .week7-table tbody td {
            padding: 15px 18px;
            font-size: 13px;
            color: #344054;
            vertical-align: middle;
        }

        .week7-table tbody tr:hover {
            background: #f8faff;
        }

        .week7-result-count {
            font-size: 13px;
            color: #778196;
        }

        .week7-empty {
            text-align: center;
            padding: 45px 20px;
            color: #778196;
        }

        .week7-empty i {
            font-size: 35px;
            display: block;
            margin-bottom: 12px;
            color: #a6b0c1;
        }

        .week7-error {
            margin: 22px;
            padding: 18px;
            border-radius: 10px;
            background: #fff1f1;
            border: 1px solid #ffcaca;
            color: #a12626;
            overflow-wrap: anywhere;
        }

        .week7-error code {
            color: #a12626;
            white-space: normal;
        }

        @media (max-width: 768px) {

            .week7-content {
                padding: 16px;
            }

            .week7-stat {
                padding: 17px;
            }

            .week7-stat-value {
                font-size: 23px;
            }

            .week7-tabs {
                padding: 14px;
            }

        }

    </style>

</head>

<body>

<div class="app-layout">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="week7-content">

            <!-- PAGE HEADER -->

            <div class="week7-header">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                    <div>

                        <span class="badge text-bg-primary mb-2">
                            PART 3 · WEEK 7
                        </span>

                        <h2>Joins, Sets & Functions</h2>

                        <p>
                            Explore SQL joins, set operations, and built-in functions
                            using your Week 7 laboratory database.
                        </p>

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

                    <div class="week7-stat">

                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <div class="week7-stat-label">
                                Total Students
                            </div>

                            <div class="week7-stat-icon">
                                <i class="bi bi-people"></i>
                            </div>

                        </div>

                        <div class="week7-stat-value">
                            <?= e($stats["total_students"]) ?>
                        </div>

                    </div>

                </div>


                <div class="col-6 col-xl-3">

                    <div class="week7-stat">

                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <div class="week7-stat-label">
                                Total Subjects
                            </div>

                            <div class="week7-stat-icon">
                                <i class="bi bi-book"></i>
                            </div>

                        </div>

                        <div class="week7-stat-value">
                            <?= e($stats["total_subjects"]) ?>
                        </div>

                    </div>

                </div>


                <div class="col-6 col-xl-3">

                    <div class="week7-stat">

                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <div class="week7-stat-label">
                                Enrollments
                            </div>

                            <div class="week7-stat-icon">
                                <i class="bi bi-journal-check"></i>
                            </div>

                        </div>

                        <div class="week7-stat-value">
                            <?= e($stats["total_enrollments"]) ?>
                        </div>

                    </div>

                </div>


                <div class="col-6 col-xl-3">

                    <div class="week7-stat">

                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <div class="week7-stat-label">
                                Payments
                            </div>

                            <div class="week7-stat-icon">
                                <i class="bi bi-wallet2"></i>
                            </div>

                        </div>

                        <div class="week7-stat-value">
                            <?= e($stats["total_payments"]) ?>
                        </div>

                    </div>

                </div>

            </div>


            <!-- SQL OPERATIONS -->

            <div class="week7-card">

                <div class="week7-tabs">

                    <a
                        href="?view=joins"
                        class="week7-tab <?= $view === 'joins' ? 'active' : '' ?>"
                    >
                        <i class="bi bi-diagram-3 me-1"></i>
                        Joins
                    </a>

                    <a
                        href="?view=union"
                        class="week7-tab <?= $view === 'union' ? 'active' : '' ?>"
                    >
                        <i class="bi bi-union me-1"></i>
                        UNION
                    </a>

                    <a
                        href="?view=intersect"
                        class="week7-tab <?= $view === 'intersect' ? 'active' : '' ?>"
                    >
                        <i class="bi bi-intersect me-1"></i>
                        INTERSECT
                    </a>

                    <a
                        href="?view=except"
                        class="week7-tab <?= $view === 'except' ? 'active' : '' ?>"
                    >
                        <i class="bi bi-dash-circle me-1"></i>
                        EXCEPT
                    </a>

                    <a
                        href="?view=functions"
                        class="week7-tab <?= $view === 'functions' ? 'active' : '' ?>"
                    >
                        <i class="bi bi-calculator me-1"></i>
                        Functions
                    </a>

                </div>


                <!-- TABLE HEADER -->

                <div class="week7-card-header">

                    <h5><?= e($pageTitle) ?></h5>

                    <p><?= e($pageDescription) ?></p>

                </div>


                <!-- DATABASE ERROR -->

                <?php if ($errorMessage !== ""): ?>

                    <div class="week7-error">

                        <h6 class="fw-bold">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Database Error
                        </h6>

                        <p class="mb-0">
                            <?= e($errorMessage) ?>
                        </p>

                    </div>

                <?php else: ?>


                    <!-- RESULTS -->

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 px-4 py-3">

                        <span class="week7-result-count">

                            <i class="bi bi-database me-1"></i>

                            <?= count($records) ?> record(s) found

                        </span>

                        <span class="badge text-bg-light border">
                            PostgreSQL · week07_report
                        </span>

                    </div>


                    <div class="week7-table-wrapper">

                        <table class="table table-hover week7-table">

                            <thead>

                                <tr>

                                    <th style="width: 55px;">#</th>

                                    <?php foreach ($columns as $label): ?>

                                        <th><?= e($label) ?></th>

                                    <?php endforeach; ?>

                                </tr>

                            </thead>


                            <tbody>

                                <?php if (empty($records)): ?>

                                    <tr>

                                        <td colspan="<?= count($columns) + 1 ?>">

                                            <div class="week7-empty">

                                                <i class="bi bi-inbox"></i>

                                                <h6 class="fw-bold">
                                                    No Records Found
                                                </h6>

                                                <p class="mb-0">
                                                    There are currently no matching records
                                                    in this SQL operation.
                                                </p>

                                            </div>

                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($records as $index => $record): ?>

                                        <tr>

                                            <td class="text-muted">
                                                <?= $index + 1 ?>
                                            </td>

                                            <?php foreach ($columns as $key => $label): ?>

                                                <td>

                                                    <?php if ($key === "total_paid" || $key === "average_payment"): ?>

                                                        ₱<?= number_format((float)($record[$key] ?? 0), 2) ?>

                                                    <?php else: ?>

                                                        <?= e($record[$key] ?? "") ?>

                                                    <?php endif; ?>

                                                </td>

                                            <?php endforeach; ?>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>


                <!-- FOOTER -->

                <div class="border-top px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">

                    <span class="text-muted small">
                        Week 7 Database Administration Laboratory
                    </span>

                    <span class="text-muted small">
                        SQL Operations and Analysis
                    </span>

                </div>

            </div>


            <div class="text-muted small mt-4">

                <i class="bi bi-info-circle me-1"></i>

                Data is retrieved directly from PostgreSQL.
                Refresh this page to load the latest database records.

            </div>

        </div>

    </main>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
