
<?php
session_start();

require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$errors = [];

$studentId = '';
$amount = '';
$paymentDate = date('Y-m-d');
$paymentMethod = '';

try {

    // FETCH FINANCIAL STUDENTS
    $studentsStmt = $pdo->query("
        SELECT
            student_id,
            student_no,
            student_name,
            program
        FROM week10_lab.students
        ORDER BY student_name ASC
    ");

    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log($e->getMessage());

    die('Unable to load student records. Please check your Week 10 database permissions.');
}

// PROCESS PAYMENT FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $studentId = trim($_POST['student_id'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $paymentDate = trim($_POST['payment_date'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? '');

    // CSRF VALIDATION
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errors[] = 'Invalid request. Please refresh the page and try again.';
    }

    // STUDENT VALIDATION
    if (
        $studentId === '' ||
        filter_var($studentId, FILTER_VALIDATE_INT) === false ||
        (int)$studentId < 1
    ) {
        $errors[] = 'Please select a valid student.';
    }

    // AMOUNT VALIDATION
    if (
        $amount === '' ||
        !is_numeric($amount) ||
        !is_finite((float)$amount) ||
        (float)$amount <= 0
    ) {
        $errors[] = 'Enter a valid payment amount greater than zero.';
    } elseif (strlen($amount) > 15 || !preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
        $errors[] = 'Payment amount must have no more than two decimal places.';
    }

    // DATE VALIDATION
    $dateObject = DateTime::createFromFormat('!Y-m-d', $paymentDate);
    $dateErrors = DateTime::getLastErrors();

    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !== $paymentDate ||
        ($dateErrors !== false && (
            $dateErrors['warning_count'] > 0 ||
            $dateErrors['error_count'] > 0
        ))
    ) {
        $errors[] = 'Please enter a valid payment date.';
    }

    // PAYMENT METHOD VALIDATION
    $allowedMethods = [
        'Cash',
        'GCash',
        'Bank Transfer',
        'Credit Card',
        'Debit Card',
        'Other'
    ];

    if (!in_array($paymentMethod, $allowedMethods, true)) {
        $errors[] = 'Please select a valid payment method.';
    }

    // CHECK STUDENT EXISTS
    if (empty($errors)) {

        try {

            $checkStudent = $pdo->prepare("
                SELECT COUNT(*)
                FROM week10_lab.students
                WHERE student_id = :student_id
            ");

            $checkStudent->execute([
                'student_id' => (int)$studentId
            ]);

            if ((int)$checkStudent->fetchColumn() === 0) {
                $errors[] = 'The selected student does not exist.';
            }

        } catch (PDOException $e) {

            error_log($e->getMessage());
            $errors[] = 'Unable to verify the selected student.';
        }
    }

    // SAVE PAYMENT
    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            $insert = $pdo->prepare("
                INSERT INTO week10_lab.payments (
                    student_id,
                    amount,
                    payment_date,
                    payment_method
                )
                VALUES (
                    :student_id,
                    :amount,
                    :payment_date,
                    :payment_method
                )
            ");

            $insert->execute([
                'student_id' => (int)$studentId,
                'amount' => number_format((float)$amount, 2, '.', ''),
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod
            ]);

            $pdo->commit();

            header('Location: payments.php?status=added');
            exit;

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            if ($e->getCode() === '23503') {
                $errors[] = 'The selected student is no longer available.';
            } else {
                $errors[] = 'Unable to save the payment. Please check the database error log.';
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Record Payment | CampusCore</title>

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

        .form-card {
            background: #fff;
            border: 1px solid #e9edf5;
            border-radius: 16px;
            padding: 30px;
            max-width: 850px;
            box-shadow: 0 5px 20px rgba(16, 27, 61, .03);
        }

        .form-label {
            color: #263653;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-control,
        .form-select {
            border: 1px solid #dfe4ef;
            border-radius: 9px;
            padding: 12px 14px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #526cae;
            box-shadow: 0 0 0 3px rgba(38, 62, 131, .10);
        }

        .form-section-title {
            color: #172858;
            font-weight: 700;
        }

        .btn-primary {
            background: #172858;
            border-color: #172858;
            border-radius: 9px;
            padding: 11px 22px;
        }

        .btn-primary:hover {
            background: #263e83;
            border-color: #263e83;
        }

        .info-box {
            background: #f0f4ff;
            border: 1px solid #dfe7ff;
            border-radius: 12px;
            padding: 16px;
            color: #344a7a;
        }

        .amount-input {
            font-size: 22px;
            font-weight: 700;
            color: #172858;
        }

        @media (max-width: 768px) {

            .main-content {
                margin-left: 0;
                padding: 20px 12px;
            }

            .form-card {
                padding: 20px;
            }
        }

    </style>

</head>

<body>

    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">

        <!-- HEADER -->

        <div class="mb-4">

            <a href="payments.php" class="text-decoration-none text-secondary small">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Payments
            </a>

            <h2 class="page-title mt-3 mb-1">Record a Payment</h2>

            <p class="text-secondary mb-0">
                Record and manage student payment transactions.
            </p>

        </div>

        <!-- ERRORS -->

        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger">

                <strong>
                    <i class="bi bi-exclamation-circle me-2"></i>
                    Please review the following:
                </strong>

                <ul class="mb-0 mt-2">

                    <?php foreach ($errors as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>

        <!-- PAYMENT FORM -->

        <div class="form-card">

            <div class="mb-4">

                <h5 class="form-section-title">
                    <i class="bi bi-cash-coin me-2"></i>
                    Payment Information
                </h5>

                <p class="text-secondary small">
                    Enter the payment details below. Fields marked with an asterisk are required.
                </p>

            </div>

            <?php if (empty($students)): ?>

                <div class="alert alert-warning">

                    <i class="bi bi-exclamation-triangle me-2"></i>

                    No financial student records were found in the Week 10 database.
                    Please register a student in the financial student table first.

                </div>

            <?php else: ?>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escape($_SESSION['csrf_token']) ?>"
                    >

                    <!-- STUDENT -->

                    <div class="mb-4">

                        <label class="form-label" for="student_id">
                            Select Student <span class="text-danger">*</span>
                        </label>

                        <select
                            class="form-select"
                            id="student_id"
                            name="student_id"
                            required
                        >

                            <option value="">Choose a student</option>

                            <?php foreach ($students as $student): ?>

                                <option
                                    value="<?= (int)$student['student_id'] ?>"
                                    <?= (string)$studentId === (string)$student['student_id'] ? 'selected' : '' ?>
                                >

                                    <?= escape($student['student_no']) ?>
                                    —
                                    <?= escape($student['student_name']) ?>
                                    (<?= escape($student['program']) ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <small class="text-secondary">
                            Select a student from the Week 10 financial records.
                        </small>

                    </div>

                    <hr class="my-4">

                    <h6 class="form-section-title mb-3">
                        Transaction Details
                    </h6>

                    <div class="row g-3">

                        <!-- AMOUNT -->

                        <div class="col-md-6">

                            <label class="form-label" for="amount">
                                Payment Amount <span class="text-danger">*</span>
                            </label>

                            <div class="input-group">

                                <span class="input-group-text">₱</span>

                                <input
                                    type="number"
                                    class="form-control amount-input"
                                    id="amount"
                                    name="amount"
                                    placeholder="0.00"
                                    min="0.01"
                                    step="0.01"
                                    value="<?= escape($amount) ?>"
                                    required
                                >

                            </div>

                        </div>

                        <!-- DATE -->

                        <div class="col-md-6">

                            <label class="form-label" for="payment_date">
                                Payment Date <span class="text-danger">*</span>
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                id="payment_date"
                                name="payment_date"
                                value="<?= escape($paymentDate) ?>"
                                required
                            >

                        </div>

                        <!-- METHOD -->

                        <div class="col-12">

                            <label class="form-label" for="payment_method">
                                Payment Method <span class="text-danger">*</span>
                            </label>

                            <select
                                class="form-select"
                                id="payment_method"
                                name="payment_method"
                                required
                            >

                                <option value="">Choose payment method</option>

                                <?php
                                $methods = [
                                    'Cash',
                                    'GCash',
                                    'Bank Transfer',
                                    'Credit Card',
                                    'Debit Card',
                                    'Other'
                                ];
                                ?>

                                <?php foreach ($methods as $method): ?>

                                    <option
                                        value="<?= escape($method) ?>"
                                        <?= $paymentMethod === $method ? 'selected' : '' ?>
                                    >
                                        <?= escape($method) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>

                    <!-- INFORMATION -->

                    <div class="info-box mt-4">

                        <div class="d-flex gap-3">

                            <i class="bi bi-shield-check fs-4"></i>

                            <div>

                                <strong>Payment Record Protection</strong>

                                <p class="small mb-0 mt-1">
                                    Submitted transactions are stored in your PostgreSQL payment table. Please verify the student, amount, date, and payment method before saving.
                                </p>

                            </div>

                        </div>

                    </div>

                    <!-- ACTIONS -->

                    <div class="d-flex justify-content-end gap-2 mt-4">

                        <a href="payments.php" class="btn btn-light border px-4">
                            Cancel
                        </a>

                        <button type="submit" class="btn btn-primary">

                            <i class="bi bi-check-lg me-2"></i>
                            Save Payment

                        </button>

                    </div>

                </form>

            <?php endif; ?>

        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
