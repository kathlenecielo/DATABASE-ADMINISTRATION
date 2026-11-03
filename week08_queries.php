
<?php
session_start();
require_once "../includes/db.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$view = $_GET["view"] ?? "views";

$allowedViews = ["views", "case", "transactions"];

if (!in_array($view, $allowedViews, true)) {
    $view = "views";
}

$records = [];
$columns = [];
$pageTitle = "";
$pageDescription = "";
$errorMessage = "";
$transactionDemo = null;

try {

    // Statistics from the Week 8 datasets.
    $stats = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM week08_balance.v_student_balance)
                AS total_accounts,

            (SELECT COUNT(*) FROM week08_balance.payments)
                AS total_payments,

            (SELECT COUNT(*) FROM week08_transactions.students)
                AS total_students,

            (SELECT COUNT(*) FROM week08_transactions.payments)
                AS transaction_payments
    ")->fetch();


    switch ($view) {

        // ==========================================
        // VIEW: STUDENT BALANCE
        // ==========================================

        case "views":

            $pageTitle = "Student Balance View";

            $pageDescription = "Retrieve student account information from the existing v_student_balance database view.";

            $columns = [
                "Student Number",
                "Student Name",
                "Assessment",
                "Discount",
                "Net Assessment",
                "Total Paid",
                "Balance",
                "Payment Status"
            ];

            $sql = "
                SELECT
                    student_no,
                    student_name,
                    assessed_amount,
                    discount,
                    net_assessment,
                    total_paid,
                    balance,
                    payment_status

                FROM week08_balance.v_student_balance

                ORDER BY student_name ASC
            ";

            $records = $pdo->query($sql)->fetchAll();

            break;


        // ==========================================
        // CASE: CLASSIFY STUDENT PAYMENT STATUS
        // ==========================================

        case "case":

            $pageTitle = "CASE Statement Analysis";

            $pageDescription = "Classify student accounts based on their remaining balance and payment activity.";

            $columns = [
                "Student Number",
                "Student Name",
                "Net Assessment",
                "Total Paid",
                "Remaining Balance",
                "CASE Classification"
            ];

            $sql = "
                SELECT
                    student_no,
                    student_name,
                    net_assessment,
                    total_paid,
                    balance,

                    CASE
                        WHEN balance <= 0 THEN 'Fully Paid'
                        WHEN total_paid = 0 THEN 'Unpaid'
                        ELSE 'Partially Paid'
                    END AS account_classification

                FROM week08_balance.v_student_balance

                ORDER BY student_name ASC
            ";

            $records = $pdo->query($sql)->fetchAll();

            break;


        // ==========================================
        // TRANSACTION: COMMIT AND ROLLBACK DEMO
        // ==========================================

        case "transactions":

            $pageTitle = "Transaction Management";

            $pageDescription = "Demonstrate transaction control using BEGIN, COMMIT, and ROLLBACK without modifying permanent database records.";

            $columns = [];

            // Temporary table exists only for this database session.
            // It does not modify permanent Week 8 tables.

            $pdo->exec("
                CREATE TEMP TABLE week08_transaction_demo (
                    demo_id SERIAL PRIMARY KEY,
                    description VARCHAR(100)
                )
            ");

            // DEMONSTRATION 1: COMMIT
            $pdo->beginTransaction();

            $pdo->exec("
                INSERT INTO week08_transaction_demo (description)
                VALUES ('Committed test record')
            ");

            $countBeforeCommit = (int)$pdo->query("
                SELECT COUNT(*)
                FROM week08_transaction_demo
            ")->fetchColumn();

            $pdo->commit();

            $countAfterCommit = (int)$pdo->query("
                SELECT COUNT(*)
                FROM week08_transaction_demo
            ")->fetchColumn();


            // DEMONSTRATION 2: ROLLBACK
            $pdo->beginTransaction();

            $pdo->exec("
                INSERT INTO week08_transaction_demo (description)
                VALUES ('Rolled back test record')
            ");

            $countBeforeRollback = (int)$pdo->query("
                SELECT COUNT(*)
                FROM week08_transaction_demo
            ")->fetchColumn();

            $pdo->rollBack();

            $countAfterRollback = (int)$pdo->query("
                SELECT COUNT(*)
                FROM week08_transaction_demo
            ")->fetchColumn();


            $transactionDemo = [
                "before_commit" => $countBeforeCommit,
                "after_commit" => $countAfterCommit,
                "before_rollback" => $countBeforeRollback,
                "after_rollback" => $countAfterRollback
            ];

            break;
    }

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    $errorMessage = "Unable to complete this module. Please check your Week 8 schema, table permissions, and database connection.";

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Week 8 | Views, CASE & Transactions</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        .stat-card,
        .table-card,
        .concept-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 22px;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .stat-number {
            font-size: 27px;
            font-weight: 800;
            margin-top: 12px;
        }

        .stat-label {
            font-size: 11px;
            color: var(--muted);
            font-weight: 700;
        }

        .module-nav {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .module-nav a {
            text-decoration: none;
            border: 1px solid var(--border);
            padding: 9px 15px;
            border-radius: 9px;
            color: var(--text);
            font-size: 13px;
            font-weight: 600;
            background: white;
        }

        .module-nav a.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .concept-label {
            font-size: 11px;
            color: var(--muted);
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        .concept-description {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.6;
        }

        .sql-badge {
            font-family: monospace;
            font-size: 12px;
            font-weight: 700;
            background: #f1f0ff;
            color: #6246c7;
            padding: 5px 9px;
            border-radius: 6px;
            display: inline-block;
        }

        .transaction-result {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            height: 100%;
        }

        .transaction-result .result-number {
            font-size: 30px;
            font-weight: 800;
        }

        .empty-state {
            text-align: center;
            padding: 45px 20px;
        }

        .empty-state i {
            font-size: 40px;
            color: #adb5bd;
        }

        @media (max-width: 768px) {

            .stat-card,
            .table-card,
            .concept-card {
                padding: 16px;
            }

            .module-nav {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
            }

            .module-nav a {
                text-align: center;
            }

        }

    </style>

</head>

<body>

<div class="app-layout">

    <?php
    $basePath = "../";
    require "../includes/sidebar.php";
    ?>

    <main class="main-content">

        <header class="topbar">

            <div>
                <div class="breadcrumb-text">
                    PART 4 / WEEK 8
                </div>

                <h4>Views, CASE & Transactions</h4>
            </div>

        </header>


        <div class="content-area">

            <!-- PAGE HEADING -->

            <div class="welcome-section">

                <div>
                    <h2>Week 8 SQL Operations</h2>

                    <p class="text-muted small">
                        Explore database views, conditional expressions, and transaction control.
                    </p>
                </div>

                <span class="badge bg-primary-subtle text-primary px-3 py-2">
                    WEEK 8
                </span>

            </div>


            <!-- STATISTICS -->

            <div class="row g-3 mb-4">

                <div class="col-6 col-xl-3">
                    <div class="stat-card">

                        <div class="stat-icon bg-primary-subtle text-primary">
                            <i class="bi bi-person-vcard"></i>
                        </div>

                        <div class="stat-number">
                            <?= number_format((int)$stats["total_accounts"]) ?>
                        </div>

                        <div class="stat-label">
                            STUDENT ACCOUNTS
                        </div>

                    </div>
                </div>


                <div class="col-6 col-xl-3">
                    <div class="stat-card">

                        <div class="stat-icon bg-success-subtle text-success">
                            <i class="bi bi-wallet2"></i>
                        </div>

                        <div class="stat-number">
                            <?= number_format((int)$stats["total_payments"]) ?>
                        </div>

                        <div class="stat-label">
                            BALANCE PAYMENTS
                        </div>

                    </div>
                </div>


                <div class="col-6 col-xl-3">
                    <div class="stat-card">

                        <div class="stat-icon bg-info-subtle text-info">
                            <i class="bi bi-people"></i>
                        </div>

                        <div class="stat-number">
                            <?= number_format((int)$stats["total_students"]) ?>
                        </div>

                        <div class="stat-label">
                            TRANSACTION STUDENTS
                        </div>

                    </div>
                </div>


                <div class="col-6 col-xl-3">
                    <div class="stat-card">

                        <div class="stat-icon bg-warning-subtle text-warning">
                            <i class="bi bi-receipt"></i>
                        </div>

                        <div class="stat-number">
                            <?= number_format((int)$stats["transaction_payments"]) ?>
                        </div>

                        <div class="stat-label">
                            TRANSACTION PAYMENTS
                        </div>

                    </div>
                </div>

            </div>


            <!-- CONCEPTS -->

            <div class="row g-3 mb-4">

                <div class="col-md-4">
                    <div class="concept-card h-100">

                        <div class="concept-label">01 · Database Views</div>

                        <h6 class="fw-bold mt-2">
                            V_STUDENT_BALANCE
                        </h6>

                        <p class="concept-description mb-2">
                            Retrieve combined account information from a saved database view.
                        </p>

                        <span class="sql-badge">CREATE VIEW / SELECT</span>

                    </div>
                </div>


                <div class="col-md-4">
                    <div class="concept-card h-100">

                        <div class="concept-label">02 · Conditional Logic</div>

                        <h6 class="fw-bold mt-2">
                            CASE Statements
                        </h6>

                        <p class="concept-description mb-2">
                            Categorize account records based on specified conditions.
                        </p>

                        <span class="sql-badge">CASE WHEN</span>

                    </div>
                </div>


                <div class="col-md-4">
                    <div class="concept-card h-100">

                        <div class="concept-label">03 · Transaction Control</div>

                        <h6 class="fw-bold mt-2">
                            COMMIT / ROLLBACK
                        </h6>

                        <p class="concept-description mb-2">
                            Understand how database changes can be saved or reversed.
                        </p>

                        <span class="sql-badge">BEGIN / COMMIT / ROLLBACK</span>

                    </div>
                </div>

            </div>


            <!-- MODULE NAVIGATION -->

            <section class="table-card mb-4">

                <h5 class="fw-bold mb-3">
                    Practice Modules
                </h5>

                <div class="module-nav">

                    <a href="?view=views"
                       class="<?= $view === "views" ? "active" : "" ?>">
                        <i class="bi bi-eye me-1"></i>
                        Database Views
                    </a>

                    <a href="?view=case"
                       class="<?= $view === "case" ? "active" : "" ?>">
                        <i class="bi bi-diagram-2 me-1"></i>
                        CASE Statements
                    </a>

                    <a href="?view=transactions"
                       class="<?= $view === "transactions" ? "active" : "" ?>">
                        <i class="bi bi-arrow-left-right me-1"></i>
                        Transactions
                    </a>

                </div>

            </section>


            <!-- RESULTS -->

            <section class="table-card">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

                    <div>

                        <h5 class="fw-bold mb-1">
                            <?= e($pageTitle) ?>
                        </h5>

                        <p class="text-muted small mb-0">
                            <?= e($pageDescription) ?>
                        </p>

                    </div>

                    <?php if ($view !== "transactions"): ?>

                        <span class="badge bg-primary-subtle text-primary px-3 py-2">
                            <?= count($records) ?> records
                        </span>

                    <?php endif; ?>

                </div>


                <?php if ($errorMessage !== ""): ?>

                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= e($errorMessage) ?>
                    </div>


                <?php elseif ($view === "transactions" && $transactionDemo !== null): ?>

                    <div class="alert alert-success">
                        <i class="bi bi-shield-check me-2"></i>
                        Transaction demonstration completed successfully. No permanent database records were changed.
                    </div>

                    <div class="row g-3">

                        <div class="col-md-6">

                            <div class="transaction-result">

                                <span class="badge bg-success-subtle text-success mb-3">
                                    COMMIT
                                </span>

                                <h6 class="fw-bold">
                                    Save Transaction
                                </h6>

                                <p class="text-muted small">
                                    COMMIT permanently saves the changes made within a transaction.
                                </p>

                                <div class="small text-muted">
                                    Records before COMMIT
                                </div>

                                <div class="result-number">
                                    <?= $transactionDemo["before_commit"] ?>
                                </div>

                                <div class="small text-muted mt-2">
                                    Records after COMMIT
                                </div>

                                <div class="result-number text-success">
                                    <?= $transactionDemo["after_commit"] ?>
                                </div>

                                <div class="mt-3">
                                    <span class="badge bg-success">
                                        Test record saved
                                    </span>
                                </div>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <div class="transaction-result">

                                <span class="badge bg-danger-subtle text-danger mb-3">
                                    ROLLBACK
                                </span>

                                <h6 class="fw-bold">
                                    Reverse Transaction
                                </h6>

                                <p class="text-muted small">
                                    ROLLBACK cancels changes made after the transaction begins.
                                </p>

                                <div class="small text-muted">
                                    Records before ROLLBACK
                                </div>

                                <div class="result-number">
                                    <?= $transactionDemo["before_rollback"] ?>
                                </div>

                                <div class="small text-muted mt-2">
                                    Records after ROLLBACK
                                </div>

                                <div class="result-number text-danger">
                                    <?= $transactionDemo["after_rollback"] ?>
                                </div>

                                <div class="mt-3">
                                    <span class="badge bg-danger">
                                        Test record reversed
                                    </span>
                                </div>

                            </div>

                        </div>

                    </div>


                    <div class="alert alert-info mt-4 mb-0">

                        <strong>Important:</strong>

                        This demonstration uses a temporary table that exists only during the database connection. It does not insert, update, or delete records in your permanent Week 8 tables.

                    </div>


                <?php elseif (empty($records)): ?>

                    <div class="empty-state">

                        <i class="bi bi-database"></i>

                        <h6 class="fw-bold mt-3">
                            No Records Found
                        </h6>

                        <p class="text-muted small">
                            No matching records are currently available.
                        </p>

                    </div>


                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table table-hover align-middle">

                            <thead>

                                <tr>

                                    <?php foreach ($columns as $column): ?>

                                        <th>
                                            <?= e($column) ?>
                                        </th>

                                    <?php endforeach; ?>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($records as $record): ?>

                                    <tr>

                                        <?php if ($view === "views"): ?>

                                            <td>
                                                <strong>
                                                    <?= e($record["student_no"]) ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <?= e($record["student_name"]) ?>
                                            </td>

                                            <td>
                                                <?= number_format((float)$record["assessed_amount"], 2) ?>
                                            </td>

                                            <td>
                                                <?= number_format((float)$record["discount"], 2) ?>
                                            </td>

                                            <td>
                                                <?= number_format((float)$record["net_assessment"], 2) ?>
                                            </td>

                                            <td>
                                                <?= number_format((float)$record["total_paid"], 2) ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= number_format((float)$record["balance"], 2) ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <span class="badge bg-primary-subtle text-primary">
                                                    <?= e($record["payment_status"]) ?>
                                                </span>
                                            </td>


                                        <?php elseif ($view === "case"): ?>

                                            <td>
                                                <strong>
                                                    <?= e($record["student_no"]) ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <?= e($record["student_name"]) ?>
                                            </td>

                                            <td>
                                                <?= number_format((float)$record["net_assessment"], 2) ?>
                                            </td>

                                            <td>
                                                <?= number_format((float)$record["total_paid"], 2) ?>
                                            </td>

                                            <td>
                                                <?= number_format((float)$record["balance"], 2) ?>
                                            </td>

                                            <td>

                                                <?php
                                                    $classification = $record["account_classification"];

                                                    $badgeClass = match ($classification) {
                                                        "Fully Paid" => "bg-success-subtle text-success",
                                                        "Unpaid" => "bg-danger-subtle text-danger",
                                                        default => "bg-warning-subtle text-warning"
                                                    };
                                                ?>

                                                <span class="badge <?= $badgeClass ?>">
                                                    <?= e($classification) ?>
                                                </span>

                                            </td>

                                        <?php endif; ?>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </section>


            <!-- FOOTER -->

            <footer class="footer">

                <span>
                    Database Administration Practical System
                </span>

                <span>
                    Views, CASE & Transactions · Week 8
                </span>

            </footer>

        </div>

    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
