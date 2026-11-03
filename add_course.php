
<?php
session_start();

require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$course_code = '';
$course_name = '';
$units = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errors[] = 'Invalid form request. Please refresh the page and try again.';
    }

    $course_code = strtoupper(trim($_POST['course_code'] ?? ''));
    $course_name = trim($_POST['course_name'] ?? '');
    $units = trim($_POST['units'] ?? '');

    if ($course_code === '') {
        $errors[] = 'Course code is required.';
    } elseif (strlen($course_code) > 20) {
        $errors[] = 'Course code must not exceed 20 characters.';
    }

    if ($course_name === '') {
        $errors[] = 'Course name is required.';
    } elseif (strlen($course_name) > 150) {
        $errors[] = 'Course name must not exceed 150 characters.';
    }

    if ($units === '' || filter_var($units, FILTER_VALIDATE_INT) === false || (int)$units <= 0) {
        $errors[] = 'Units must be a positive whole number.';
    }

    if (empty($errors)) {
        try {
            $check = $pdo->prepare("
                SELECT course_id
                FROM week04_lab.courses
                WHERE UPPER(course_code) = UPPER(:course_code)
            ");

            $check->execute(['course_code' => $course_code]);

            if ($check->fetch()) {
                $errors[] = 'A course with this course code already exists.';
            } else {
                $insert = $pdo->prepare("
                    INSERT INTO week04_lab.courses
                        (course_code, course_name, units)
                    VALUES
                        (:course_code, :course_name, :units)
                ");

                $insert->execute([
                    'course_code' => $course_code,
                    'course_name' => $course_name,
                    'units' => (int)$units
                ]);

                header('Location: courses.php?status=added');
                exit;
            }

        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                $errors[] = 'This course code is already registered.';
            } else {
                $errors[] = 'Unable to save the course. Please check your database connection and try again.';
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
    <title>Add Course | CampusCore</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            background: #f5f7fb;
        }

        .main-content {
            margin-left: 260px;
            padding: 35px;
            min-height: 100vh;
        }

        .page-heading {
            margin-bottom: 28px;
        }

        .form-card {
            background: #fff;
            border: 1px solid #e9edf5;
            border-radius: 16px;
            padding: 30px;
            max-width: 850px;
            box-shadow: 0 4px 15px rgba(20, 40, 80, 0.04);
        }

        .form-label {
            font-weight: 600;
            color: #26334d;
        }

        .form-control {
            padding: 12px 14px;
            border-radius: 9px;
        }

        .form-control:focus {
            border-color: #5277d9;
            box-shadow: 0 0 0 0.2rem rgba(82, 119, 217, 0.12);
        }

        .btn-primary {
            background: #243b78;
            border-color: #243b78;
            padding: 10px 22px;
            border-radius: 9px;
        }

        .btn-primary:hover {
            background: #192c5e;
            border-color: #192c5e;
        }

        .btn-light {
            padding: 10px 22px;
            border-radius: 9px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .form-card {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="page-heading">
            <a href="courses.php" class="text-decoration-none text-secondary small">
                <i class="bi bi-arrow-left"></i> Back to Courses
            </a>

            <h2 class="fw-bold mt-3 mb-1">Add New Course</h2>
            <p class="text-secondary mb-0">
                Register a new course in the academic database.
            </p>
        </div>

        <div class="form-card">

            <div class="mb-4">
                <h5 class="fw-bold mb-1">Course Information</h5>
                <p class="text-secondary small mb-0">
                    Enter the course details below.
                </p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Please review the following:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                >

                <div class="row g-3">

                    <div class="col-md-6">
                        <label for="course_code" class="form-label">
                            Course Code
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="course_code"
                            name="course_code"
                            placeholder="e.g. IT101"
                            maxlength="20"
                            value="<?= htmlspecialchars($course_code) ?>"
                            required
                        >

                        <div class="form-text">
                            Enter the unique code assigned to the course.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="units" class="form-label">
                            Number of Units
                        </label>

                        <input
                            type="number"
                            class="form-control"
                            id="units"
                            name="units"
                            placeholder="e.g. 3"
                            min="1"
                            step="1"
                            value="<?= htmlspecialchars($units) ?>"
                            required
                        >

                        <div class="form-text">
                            Enter a positive whole number.
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="course_name" class="form-label">
                            Course Name
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="course_name"
                            name="course_name"
                            placeholder="e.g. Introduction to Computing"
                            maxlength="150"
                            value="<?= htmlspecialchars($course_name) ?>"
                            required
                        >
                    </div>

                </div>

                <hr class="my-4">

                <div class="d-flex flex-wrap gap-2 justify-content-end">
                    <a href="courses.php" class="btn btn-light border">
                        Cancel
                    </a>

                    <button type="submit" class="btn btn-primary">
                        Save Course
                    </button>
                </div>

            </form>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
