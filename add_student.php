
<?php
session_start();

require_once __DIR__ . '/../includes/db.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$errors = [];

$student_no = '';
$first_name = '';
$last_name = '';
$email = '';
$program_id = '';
$year_level = '';

// Fetch available programs from PostgreSQL
$programStmt = $pdo->query("
    SELECT program_id, program_code, program_name
    FROM week04_lab.programs
    ORDER BY program_code
");

$programs = $programStmt->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF token
    if (!hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token'] ?? ''
    )) {
        die('Invalid request. Please refresh the page and try again.');
    }

    // Get submitted values
    $student_no = trim($_POST['student_no'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $program_id = trim($_POST['program_id'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');

    // Validate fields
    if ($student_no === '') {
        $errors[] = 'Student number is required.';
    }

    if ($first_name === '') {
        $errors[] = 'First name is required.';
    }

    if ($last_name === '') {
        $errors[] = 'Last name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (
        !ctype_digit($program_id) ||
        !in_array(
            (int)$program_id,
            array_map(
                'intval',
                array_column($programs, 'program_id')
            ),
            true
        )
    ) {
        $errors[] = 'Please select a valid program.';
    }

    if (
        !in_array($year_level, ['1', '2', '3', '4'], true)
    ) {
        $errors[] = 'Please select a valid year level.';
    }

    // Insert into PostgreSQL
    if (empty($errors)) {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO week04_lab.students
                (
                    student_no,
                    first_name,
                    last_name,
                    email,
                    program_id,
                    year_level
                )
                VALUES
                (
                    :student_no,
                    :first_name,
                    :last_name,
                    :email,
                    :program_id,
                    :year_level
                )
            ");

            $stmt->execute([
                'student_no' => $student_no,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'program_id' => (int)$program_id,
                'year_level' => (int)$year_level
            ]);

            header('Location: students.php?status=added');
            exit;

        } catch (PDOException $e) {

            error_log($e->getMessage());

            // PostgreSQL unique violation
            if ($e->getCode() === '23505') {
                $errors[] = 'Student number or email already exists.';
            } else {
                $errors[] = 'Unable to save the student. Please check the database configuration and try again.';
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

    <title>Add Student | CampusCore</title>

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
            color: #202b45;
            font-family: Arial, sans-serif;
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

        .form-control,
        .form-select {
            min-height: 45px;
            border-color: #dfe4ee;
            border-radius: 9px;
        }

        .form-control:focus,
        .form-select:focus {
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

            <p class="text-secondary mb-1">
                CampusCore / Students / Add Student
            </p>

            <h2 class="page-title mb-1">
                Add New Student
            </h2>

            <p class="text-secondary mb-0">
                Enter the student's information to register a new record.
            </p>

        </div>


        <div class="form-card">

            <div class="d-flex align-items-center gap-2 mb-4">

                <i class="bi bi-person-plus fs-4"></i>

                <h5 class="fw-bold mb-0">
                    Student Information
                </h5>

            </div>


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


            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($_SESSION['csrf_token']) ?>"
                >


                <div class="row g-3">

                    <div class="col-md-6">

                        <label class="form-label">
                            Student Number
                        </label>

                        <input
                            type="text"
                            name="student_no"
                            class="form-control"
                            placeholder="e.g. 2026-0004"
                            value="<?= escape($student_no) ?>"
                            maxlength="50"
                            required
                        >

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Email Address
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            placeholder="student@pup.edu.ph"
                            value="<?= escape($email) ?>"
                            maxlength="255"
                            required
                        >

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            First Name
                        </label>

                        <input
                            type="text"
                            name="first_name"
                            class="form-control"
                            placeholder="Enter first name"
                            value="<?= escape($first_name) ?>"
                            maxlength="100"
                            required
                        >

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Last Name
                        </label>

                        <input
                            type="text"
                            name="last_name"
                            class="form-control"
                            placeholder="Enter last name"
                            value="<?= escape($last_name) ?>"
                            maxlength="100"
                            required
                        >

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Program
                        </label>

                        <select
                            name="program_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Select a program
                            </option>

                            <?php foreach ($programs as $program): ?>

                                <option
                                    value="<?= (int)$program['program_id'] ?>"
                                    <?= (string)$program_id === (string)$program['program_id'] ? 'selected' : '' ?>
                                >

                                    <?= escape($program['program_code']) ?>
                                    - 
                                    <?= escape($program['program_name']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Year Level
                        </label>

                        <select
                            name="year_level"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Select year level
                            </option>

                            <?php for ($year = 1; $year <= 4; $year++): ?>

                                <option
                                    value="<?= $year ?>"
                                    <?= $year_level === (string)$year ? 'selected' : '' ?>
                                >

                                    <?= $year ?><?= $year === 1 ? 'st' : ($year === 2 ? 'nd' : ($year === 3 ? 'rd' : 'th')) ?> Year

                                </option>

                            <?php endfor; ?>

                        </select>

                    </div>

                </div>


                <hr class="my-4">


                <div class="d-flex justify-content-end gap-2">

                    <a
                        href="students.php"
                        class="btn btn-outline-secondary px-4"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary px-4"
                    >

                        <i class="bi bi-check-lg me-1"></i>
                        Save Student

                    </button>

                </div>

            </form>

        </div>


        <div class="text-secondary small mt-3">

            <i class="bi bi-database-check me-1"></i>

            New student records are saved directly to PostgreSQL.

        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
