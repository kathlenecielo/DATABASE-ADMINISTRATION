
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

$enrollmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$enrollmentId || $enrollmentId < 1) {
    die('Invalid enrollment ID. Please return to the Enrollment Management page.');
}

try {
    // FETCH EXISTING ENROLLMENT
    $enrollmentStmt = $pdo->prepare("
        SELECT
            enrollment_id,
            student_id,
            course_id,
            semester,
            academic_year
        FROM week04_lab.enrollments
        WHERE enrollment_id = :enrollment_id
    ");

    $enrollmentStmt->execute([
        'enrollment_id' => $enrollmentId
    ]);

    $enrollment = $enrollmentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$enrollment) {
        die('Enrollment record not found.');
    }

    // FETCH STUDENTS
    $studentsStmt = $pdo->query("
        SELECT
            student_id,
            student_no,
            first_name,
            last_name
        FROM week04_lab.students
        ORDER BY last_name, first_name
    ");

    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

    // FETCH COURSES
    $coursesStmt = $pdo->query("
        SELECT
            course_id,
            course_code,
            course_name,
            units
        FROM week04_lab.courses
        ORDER BY course_code
    ");

    $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Unable to load enrollment information.');
}

// INITIAL FORM VALUES
$studentId = (string)$enrollment['student_id'];
$courseId = (string)$enrollment['course_id'];
$semester = $enrollment['semester'];
$academicYear = $enrollment['academic_year'];

// PROCESS FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $studentId = trim($_POST['student_id'] ?? '');
    $courseId = trim($_POST['course_id'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $academicYear = trim($_POST['academic_year'] ?? '');

    // CSRF VALIDATION
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errors[] = 'Invalid request. Please refresh the page and try again.';
    }

    // VALIDATE STUDENT
    if (
        $studentId === '' ||
        filter_var($studentId, FILTER_VALIDATE_INT) === false ||
        (int)$studentId < 1
    ) {
        $errors[] = 'Please select a valid student.';
    }

    // VALIDATE COURSE
    if (
        $courseId === '' ||
        filter_var($courseId, FILTER_VALIDATE_INT) === false ||
        (int)$courseId < 1
    ) {
        $errors[] = 'Please select a valid course.';
    }

    // VALIDATE SEMESTER
    $allowedSemesters = [
        '1st Semester',
        '2nd Semester',
        'Summer'
    ];

    if (!in_array($semester, $allowedSemesters, true)) {
        $errors[] = 'Please select a valid semester.';
    }

    // VALIDATE ACADEMIC YEAR
    if (
        !preg_match('/^\d{4}-\d{4}$/', $academicYear) ||
        (int)substr($academicYear, 5, 4) !== (int)substr($academicYear, 0, 4) + 1
    ) {
        $errors[] = 'Enter a valid academic year, such as 2026-2027.';
    }

    // CHECK EXISTING STUDENT AND COURSE
    if (empty($errors)) {

        try {
            $checkStudent = $pdo->prepare("
                SELECT COUNT(*)
                FROM week04_lab.students
                WHERE student_id = :student_id
            ");

            $checkStudent->execute([
                'student_id' => (int)$studentId
            ]);

            if ((int)$checkStudent->fetchColumn() === 0) {
                $errors[] = 'The selected student does not exist.';
            }

            $checkCourse = $pdo->prepare("
                SELECT COUNT(*)
                FROM week04_lab.courses
                WHERE course_id = :course_id
            ");

            $checkCourse->execute([
                'course_id' => (int)$courseId
            ]);

            if ((int)$checkCourse->fetchColumn() === 0) {
                $errors[] = 'The selected course does not exist.';
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Unable to validate the selected records.';
        }
    }

    // CHECK DUPLICATES, EXCLUDING CURRENT RECORD
    if (empty($errors)) {

        try {
            $duplicateCheck = $pdo->prepare("
                SELECT COUNT(*)
                FROM week04_lab.enrollments
                WHERE student_id = :student_id
                AND course_id = :course_id
                AND semester = :semester
                AND academic_year = :academic_year
                AND enrollment_id <> :enrollment_id
            ");

            $duplicateCheck->execute([
                'student_id' => (int)$studentId,
                'course_id' => (int)$courseId,
                'semester' => $semester,
                'academic_year' => $academicYear,
                'enrollment_id' => $enrollmentId
            ]);

            if ((int)$duplicateCheck->fetchColumn() > 0) {
                $errors[] = 'Another enrollment already exists for this student and academic period.';
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Unable to check existing enrollment records.';
        }
    }

    // UPDATE ENROLLMENT
    if (empty($errors)) {

        try {
            $update = $pdo->prepare("
                UPDATE week04_lab.enrollments
                SET
                    student_id = :student_id,
                    course_id = :course_id,
                    semester = :semester,
                    academic_year = :academic_year
                WHERE enrollment_id = :enrollment_id
            ");

            $update->execute([
                'student_id' => (int)$studentId,
                'course_id' => (int)$courseId,
                'semester' => $semester,
                'academic_year' => $academicYear,
                'enrollment_id' => $enrollmentId
            ]);

            header('Location: enrollments.php?status=updated');
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Unable to update the enrollment. Please try again.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Enrollment | CampusCore</title>

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

            <a href="enrollments.php" class="text-decoration-none text-secondary small">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Enrollments
            </a>

            <h2 class="page-title mt-3 mb-1">Edit Enrollment</h2>

            <p class="text-secondary mb-0">
                Update the student's course registration information.
            </p>

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

        <div class="form-card">

            <h5 class="form-section-title mb-2">
                <i class="bi bi-pencil-square me-2"></i>
                Enrollment Information
            </h5>

            <p class="text-secondary small mb-4">
                Enrollment Record #<?= (int)$enrollmentId ?>
            </p>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($_SESSION['csrf_token']) ?>"
                >

                <div class="mb-4">

                    <label class="form-label" for="student_id">
                        Student <span class="text-danger">*</span>
                    </label>

                    <select
                        class="form-select"
                        name="student_id"
                        id="student_id"
                        required
                    >

                        <option value="">Choose a student</option>

                        <?php foreach ($students as $student): ?>

                            <option
                                value="<?= (int)$student['student_id'] ?>"
                                <?= $studentId === (string)$student['student_id'] ? 'selected' : '' ?>
                            >

                                <?= escape($student['student_no']) ?>
                                —
                                <?= escape($student['last_name'] . ', ' . $student['first_name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="mb-4">

                    <label class="form-label" for="course_id">
                        Course <span class="text-danger">*</span>
                    </label>

                    <select
                        class="form-select"
                        name="course_id"
                        id="course_id"
                        required
                    >

                        <option value="">Choose a course</option>

                        <?php foreach ($courses as $course): ?>

                            <option
                                value="<?= (int)$course['course_id'] ?>"
                                <?= $courseId === (string)$course['course_id'] ? 'selected' : '' ?>
                            >

                                <?= escape($course['course_code']) ?>
                                —
                                <?= escape($course['course_name']) ?>
                                (<?= (int)$course['units'] ?> units)

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <hr class="my-4">

                <h6 class="form-section-title mb-3">
                    Academic Period
                </h6>

                <div class="row g-3">

                    <div class="col-md-6">

                        <label class="form-label" for="semester">
                            Semester <span class="text-danger">*</span>
                        </label>

                        <select
                            class="form-select"
                            name="semester"
                            id="semester"
                            required
                        >

                            <option value="">Choose semester</option>

                            <?php foreach (['1st Semester', '2nd Semester', 'Summer'] as $option): ?>

                                <option
                                    value="<?= escape($option) ?>"
                                    <?= $semester === $option ? 'selected' : '' ?>
                                >
                                    <?= escape($option) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label" for="academic_year">
                            Academic Year <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="academic_year"
                            id="academic_year"
                            placeholder="2026-2027"
                            pattern="[0-9]{4}-[0-9]{4}"
                            value="<?= escape($academicYear) ?>"
                            required
                        >

                        <small class="text-secondary">
                            Format: YYYY-YYYY
                        </small>

                    </div>

                </div>

                <div class="info-box mt-4">

                    <i class="bi bi-info-circle me-2"></i>

                    Changes will be saved to the existing enrollment record.

                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">

                    <a href="enrollments.php" class="btn btn-light border px-4">
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
