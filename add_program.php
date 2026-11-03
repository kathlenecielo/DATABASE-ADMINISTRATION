
<?php
session_start();

require_once __DIR__ . '/../includes/db.php';

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$errors = [];

$program_code = '';
$program_name = '';

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('Invalid request. Please refresh the page and try again.');
    }

    $program_code = strtoupper(trim($_POST['program_code'] ?? ''));
    $program_name = trim($_POST['program_name'] ?? '');

    // Validation
    if ($program_code === '') {
        $errors[] = 'Program code is required.';
    }

    if ($program_name === '') {
        $errors[] = 'Program name is required.';
    }

    if (strlen($program_code) > 50) {
        $errors[] = 'Program code must not exceed 50 characters.';
    }

    if (strlen($program_name) > 255) {
        $errors[] = 'Program name must not exceed 255 characters.';
    }

    // Insert into PostgreSQL
    if (empty($errors)) {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO week04_lab.programs
                    (program_code, program_name)
                VALUES
                    (:program_code, :program_name)
            ");

            $stmt->execute([
                'program_code' => $program_code,
                'program_name' => $program_name
            ]);

            header('Location: programs.php?status=added');
            exit;

        } catch (PDOException $e) {

            error_log($e->getMessage());

            if ($e->getCode() === '23505') {
                $errors[] = 'This program code is already registered.';
            } else {
                $errors[] = 'Unable to save the program. Please try again.';
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

    <title>Add Program | CampusCore</title>

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
            padding: 28px;
            box-shadow: 0 5px 20px rgba(16, 27, 61, .04);
        }

        .form-label {
            font-weight: 600;
            color: #34405b;
        }

        .form-control {
            min-height: 46px;
            border-color: #dfe4ee;
            border-radius: 9px;
        }

        .form-control:focus {
            border-color: #526db7;
            box-shadow: 0 0 0 .2rem rgba(38, 62, 131, .10);
        }

        .btn-primary {
            background: #172858;
            border-color: #172858;
        }

        .btn-primary:hover {
            background: #263e83;
            border-color: #263e83;
        }

        .form-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #edf2ff;
            color: #263e83;
            border-radius: 12px;
            font-size: 21px;
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

            <p class="text-secondary mb-1">
                CampusCore / Programs / Add Program
            </p>

            <h2 class="page-title mb-1">
                Add New Program
            </h2>

            <p class="text-secondary mb-0">
                Register a new academic program in the system.
            </p>

        </div>


        <!-- FORM CARD -->

        <div class="form-card">

            <div class="d-flex align-items-center gap-3 mb-4">

                <div class="form-icon">
                    <i class="bi bi-mortarboard"></i>
                </div>

                <div>

                    <h5 class="fw-bold mb-1">
                        Academic Program Information
                    </h5>

                    <small class="text-secondary">
                        Complete the fields below.
                    </small>

                </div>

            </div>


            <!-- ERRORS -->

            <?php if (!empty($errors)): ?>

                <div class="alert alert-danger">

                    <strong>Please review the following:</strong>

                    <ul class="mb-0 mt-2">

                        <?php foreach ($errors as $error): ?>

                            <li><?= escape($error) ?></li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>


            <!-- ADD PROGRAM FORM -->

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($_SESSION['csrf_token']) ?>"
                >

                <div class="row g-3">

                    <div class="col-md-6">

                        <label class="form-label">
                            Program Code
                        </label>

                        <input
                            type="text"
                            name="program_code"
                            class="form-control"
                            placeholder="e.g. BSIT"
                            value="<?= escape($program_code) ?>"
                            maxlength="50"
                            required
                        >

                        <small class="text-secondary">
                            Enter the official program abbreviation.
                        </small>

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Program Name
                        </label>

                        <input
                            type="text"
                            name="program_name"
                            class="form-control"
                            placeholder="e.g. Bachelor of Science in Information Technology"
                            value="<?= escape($program_name) ?>"
                            maxlength="255"
                            required
                        >

                        <small class="text-secondary">
                            Enter the complete academic program name.
                        </small>

                    </div>

                </div>


                <hr class="my-4">


                <div class="d-flex justify-content-end gap-2 flex-wrap">

                    <a
                        href="programs.php"
                        class="btn btn-outline-secondary px-4"
                    >

                        <i class="bi bi-arrow-left me-1"></i>
                        Cancel

                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary px-4"
                    >

                        <i class="bi bi-check-lg me-1"></i>
                        Save Program

                    </button>

                </div>

            </form>

        </div>


        <div class="text-secondary small mt-3">

            <i class="bi bi-database-check me-1"></i>

            Academic program information is saved directly to PostgreSQL.

        </div>

    </main>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
