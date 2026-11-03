
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

$paymentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$paymentId || $paymentId < 1) {
    die('Invalid payment ID.');
}

$allowedMethods = [
    'Cash',
    'GCash',
    'Bank Transfer',
    'Credit Card',
    'Debit Card',
    'Other'
];

try {

    // FETCH EXISTING PAYMENT
    $stmt = $pdo->prepare("
        SELECT
            p.payment_id,
            p.student_id,
            p.amount,
            p.payment_date,
            p.payment_method,
            s.student_no,
            s.student_name,
            s.program
        FROM week10_lab.payments p
        INNER JOIN week10_lab.students s
            ON p.student_id = s.student_id
        WHERE p.payment_id = :payment_id
    ");

    $stmt->execute([
        'payment_id' => $paymentId
    ]);

    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        die('Payment record not found.');
    }

} catch (PDOException $e) {

    error_log($e->getMessage());
    die('Unable to load the payment record.');
}

// INITIAL FORM VALUES
$amount = $payment['amount'];
$paymentDate = $payment['payment_date'];
$paymentMethod = $payment['payment_method'];

// PROCESS FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

    // AMOUNT VALIDATION
    if (
        $amount === '' ||
        !is_numeric($amount) ||
        !is_finite((float)$amount) ||
        (float)$amount <= 0
    ) {
        $errors[] = 'Enter a valid payment amount greater than zero.';
    } elseif (
        strlen($amount) > 15 ||
        !preg_match('/^\d+(\.\d{1,2})?$/', $amount)
    ) {
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

    // METHOD VALIDATION
    if (!in_array($paymentMethod, $allowedMethods, true)) {
        $errors[] = 'Please select a valid payment method.';
    }

    // UPDATE PAYMENT AND RECORD AUDIT
    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            // LOCK AND RECHECK THE PAYMENT
            $lockStmt = $pdo->prepare("
                SELECT amount
                FROM week10_lab.payments
                WHERE payment_id = :payment_id
                FOR UPDATE
            ");

            $lockStmt->execute([
                'payment_id' => $paymentId
            ]);

            $currentPayment = $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentPayment) {
                throw new RuntimeException('Payment record no longer exists.');
            }

            $oldAmount = $currentPayment['amount'];
            $newAmount = number_format((float)$amount, 2, '.', '');

            // UPDATE PAYMENT
            $updateStmt = $pdo->prepare("
                UPDATE week10_lab.payments
                SET
                    amount = :amount,
                    payment_date = :payment_date,
                    payment_method = :payment_method
                WHERE payment_id = :payment_id
            ");

            $updateStmt->execute([
                'amount' => $newAmount,
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'payment_id' => $paymentId
            ]);

            // INSERT AUDIT RECORD ONLY WHEN SOMETHING CHANGED
            $oldAmountFormatted = number_format((float)$oldAmount, 2, '.', '');

            $detailsChanged =
                $oldAmountFormatted !== $newAmount ||
                $payment['payment_date'] !== $paymentDate ||
                $payment['payment_method'] !== $paymentMethod;

            if ($detailsChanged) {

                $note = sprintf(
                    'Payment details updated. Previous date: %s; New date: %s; Previous method: %s; New method: %s.',
                    $payment['payment_date'],
                    $paymentDate,
                    $payment['payment_method'],
                    $paymentMethod
                );

                $auditStmt = $pdo->prepare("
                    INSERT INTO week10_lab.payment_audit (
                        payment_id,
                        action_type,
                        old_amount,
                        new_amount,
                        note
                    )
                    VALUES (
                        :payment_id,
                        'UPDATE',
                        :old_amount,
                        :new_amount,
                        :note
                    )
                ");

                $auditStmt->execute([
                    'payment_id' => $paymentId,
                    'old_amount' => $oldAmount,
                    'new_amount' => $newAmount,
                    'note' => $note
                ]);
            }

            $pdo->commit();

            header('Location: payments.php?status=updated');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $errors[] = 'Unable to update the payment. Please check your database configuration and audit triggers.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Payment | CampusCore</title>

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

        .student-box {
            background: #f0f4ff;
            border: 1px solid #dfe7ff;
            border-radius: 12px;
            padding: 18px;
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

        <div class="mb-4">

            <a href="payments.php" class="text-decoration-none text-secondary small">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Payments
            </a>

            <h2 class="page-title mt-3 mb-1">Edit Payment</h2>

            <p class="text-secondary mb-0">
                Update an existing student payment transaction.
            </p>

        </div>

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

        <div class="form-card">

            <div class="mb-4">

                <h5 class="form-section-title">
                    <i class="bi bi-pencil-square me-2"></i>
                    Payment Information
                </h5>

                <p class="text-secondary small">
                    Review the student and update the transaction details.
                </p>

            </div>

            <!-- STUDENT DETAILS -->

            <div class="student-box mb-4">

                <div class="small text-secondary mb-1">
                    PAYMENT ID #<?= (int)$payment['payment_id'] ?>
                </div>

                <h5 class="fw-bold mb-1">
                    <?= escape($payment['student_name']) ?>
                </h5>

                <div class="text-secondary small">
                    <?= escape($payment['student_no']) ?>
                    &nbsp; | &nbsp;
                    <?= escape($payment['program']) ?>
                </div>

            </div>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($_SESSION['csrf_token']) ?>"
                >

                <h6 class="form-section-title mb-3">
                    Transaction Details
                </h6>

                <div class="row g-3">

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
                                min="0.01"
                                step="0.01"
                                value="<?= escape($amount) ?>"
                                required
                            >

                        </div>

                    </div>

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

                            <?php foreach ($allowedMethods as $method): ?>

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

                <div class="alert alert-info mt-4 mb-0">

                    <i class="bi bi-clock-history me-2"></i>

                    <strong>Audit History:</strong>

                    <span class="small">
                        Changes will be recorded in the payment audit table.
                    </span>

                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">

                    <a href="payments.php" class="btn btn-light border px-4">
                        Cancel
                    </a>

                    <button type="submit" class="btn btn-primary">

                        <i class="bi bi-check-lg me-2"></i>
                        Save Changes

                    </button>

                </div>

            </form>

        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
