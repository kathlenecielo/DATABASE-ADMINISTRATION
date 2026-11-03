
<?php
session_start();

require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];

$course_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$course_id || $course_id <= 0) {
    header('Location: courses.php?status=invalid');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT course_id, course_code, course_name, units
        FROM week04_lab.courses
        WHERE course_id = :course_id
    ");

    $stmt->execute(['course_id' => $course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        header('Location: courses.php?status=notfound');
        exit;
    }

} catch (PDOException $e) {
    die('Unable to load course information.');
}

$course_code = $course['course_code'];
$course_name = $course['course_name'];
$units = $course['units'];

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

    if (
        $units === '' ||
        filter_var($units, FILTER_VALIDATE_INT) === false ||
        (int)$units <= 0
    ) {
        $errors[] = 'Units must be a positive whole number.';
    }

    if (empty($errors)) {
        try {
            $check = $pdo->prepare("
                SELECT course_id
                FROM week04_lab.courses
                WHERE UPPER(course_code) = UPPER(:course_code)
                  AND course_id <> :course_id
            ");

            $check->execute([
                'course_code' => $course_code,
                'course_id' => $course_id
            ]);

            if ($check->fetch()) {
                $errors[] = 'Another course already uses this course code.';
            } else {
                $update = $pdo->prepare("
                    UPDATE week04_lab.courses
                    SET course_code = :course_code,
                        course_name = :course_name,
                        units = :units
                    WHERE course_id = :course_id
                ");

                $update->execute([
                    'course_code' => $course_code,
                    'course_name' => $course_name,
                    'units' => (int)$units,
                    'course_id' => $course_id
                ]);

                header('Location: courses.php?status=updated');
                exit;
            }

        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                $errors[] = 'This course code is already registered.';
            } else {
                $errors[] = 'Unable to update the course. Please try again.';
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

    <title>Edit Course | CampusCore</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

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
                &larr; Back to Courses
            </a>

            <h2 class="fw-bold mt-3 mb-1">Edit Course</h2>

            <p class="text-secondary mb-0">
                Update the information of this registered course.
            </p>
        </div>

        <div class="form-card">

            <div class="mb-4">
                <h5 class="fw-bold mb-1">Course Information</h5>

                <p class="text-secondary small mb-0">
                    Course ID: <?= htmlspecialchars((string)$course_id) ?>
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
                            maxlength="20"
                            value="<?= htmlspecialchars($course_code) ?>"
                            required
                        >
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
                            min="1"
                            step="1"
                            value="<?= htmlspecialchars((string)$units) ?>"
                            required
                        >
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
                        Save Changes
                    </button>

                </div>

            </form>

        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
