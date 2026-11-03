
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
$courseId = '';
$semester = '';
$academicYear = '';

try {
    // FETCH STUDENTS
    $studentsStmt = $pdo->query("
        SELECT
            student_id,
            student_no,
            first_name,
            last_name
        FROM week04_lab.students
        ORDER BY last_name ASC, first_name ASC
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
        ORDER BY course_code ASC
    ");

    $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Unable to load students and courses. Please check your database connection.');
}

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

    // CHECK WHETHER STUDENT AND COURSE EXIST
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

    // CHECK DUPLICATE ENROLLMENT
    if (empty($errors)) {

        try {
            $duplicateCheck = $pdo->prepare("
                SELECT COUNT(*)
                FROM week04_lab.enrollments
                WHERE student_id = :student_id
                AND course_id = :course_id
                AND semester = :semester
                AND academic_year = :academic_year
            ");

            $duplicateCheck->execute([
                'student_id' => (int)$studentId,
                'course_id' => (int)$courseId,
                'semester' => $semester,
                'academic_year' => $academicYear
            ]);

            if ((int)$duplicateCheck->fetchColumn() > 0) {
                $errors[] = 'This student is already enrolled in this course for the selected academic period.';
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Unable to check existing enrollment records.';
        }
    }

    // INSERT ENROLLMENT
    if (empty($errors)) {

        try {
            $insert = $pdo->prepare("
                INSERT INTO week04_lab.enrollments (
                    student_id,
                    course_id,
                    semester,
                    academic_year
                )
                VALUES (
                    :student_id,
                    :course_id,
                    :semester,
                    :academic_year
                )
            ");

            $insert->execute([
                'student_id' => (int)$studentId,
                'course_id' => (int)$courseId,
                'semester' => $semester,
                'academic_year' => $academicYear
            ]);

            header('Location: enrollments.php?status=added');
            exit;

        } catch (PDOException $e) {

            error_log($e->getMessage());

            if ($e->getCode() === '23503') {
                $errors[] = 'The selected student or course is no longer available.';
            } elseif ($e->getCode() === '23505') {
                $errors[] = 'This enrollment already exists.';
            } else {
                $errors[] = 'Unable to save the enrollment. Please try again.';
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

    <title>Add Enrollment | CampusCore</title>

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
            margin-bottom: 20px;
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

        <!-- HEADER -->

        <div class="mb-4">

            <a href="enrollments.php" class="text-decoration-none text-secondary small">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Enrollments
            </a>

            <h2 class="page-title mt-3 mb-1">Create New Enrollment</h2>

            <p class="text-secondary mb-0">
                Register a student in an academic course.
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

        <!-- FORM -->

        <div class="form-card">

            <div class="mb-4">

                <h5 class="form-section-title">
                    <i class="bi bi-journal-plus me-2"></i>
                    Enrollment Information
                </h5>

                <p class="text-secondary small">
                    Complete the required information below to create an enrollment record.
                </p>

            </div>

            <?php if (empty($students) || empty($courses)): ?>

                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>

                    <?php if (empty($students) && empty($courses)): ?>
                        No students or courses are registered yet. Add students and courses before creating enrollments.
                    <?php elseif (empty($students)): ?>
                        No students are registered yet. Please add a student first.
                    <?php else: ?>
                        No courses are registered yet. Please add a course first.
                    <?php endif; ?>

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
                                    <?= escape($student['last_name'] . ', ' . $student['first_name']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <small class="text-secondary">
                            Select an existing student from the database.
                        </small>

                    </div>

                    <!-- COURSE -->

                    <div class="mb-4">

                        <label class="form-label" for="course_id">
                            Select Course <span class="text-danger">*</span>
                        </label>

                        <select
                            class="form-select"
                            id="course_id"
                            name="course_id"
                            required
                        >

                            <option value="">Choose a course</option>

                            <?php foreach ($courses as $course): ?>

                                <option
                                    value="<?= (int)$course['course_id'] ?>"
                                    <?= (string)$courseId === (string)$course['course_id'] ? 'selected' : '' ?>
                                >

                                    <?= escape($course['course_code']) ?>
                                    —
                                    <?= escape($course['course_name']) ?>
                                    (<?= (int)$course['units'] ?> units)

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <small class="text-secondary">
                            Select the course where the student will be registered.
                        </small>

                    </div>

                    <hr class="my-4">

                    <h6 class="form-section-title">
                        Academic Period
                    </h6>

                    <div class="row g-3">

                        <!-- SEMESTER -->

                        <div class="col-md-6">

                            <label class="form-label" for="semester">
                                Semester <span class="text-danger">*</span>
                            </label>

                            <select
                                class="form-select"
                                id="semester"
                                name="semester"
                                required
                            >

                                <option value="">Choose semester</option>

                                <option
                                    value="1st Semester"
                                    <?= $semester === '1st Semester' ? 'selected' : '' ?>
                                >
                                    1st Semester
                                </option>

                                <option
                                    value="2nd Semester"
                                    <?= $semester === '2nd Semester' ? 'selected' : '' ?>
                                >
                                    2nd Semester
                                </option>

                                <option
                                    value="Summer"
                                    <?= $semester === 'Summer' ? 'selected' : '' ?>
                                >
                                    Summer
                                </option>

                            </select>

                        </div>

                        <!-- ACADEMIC YEAR -->

                        <div class="col-md-6">

                            <label class="form-label" for="academic_year">
                                Academic Year <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="academic_year"
                                name="academic_year"
                                placeholder="Example: 2026-2027"
                                pattern="[0-9]{4}-[0-9]{4}"
                                value="<?= escape($academicYear) ?>"
                                required
                            >

                            <small class="text-secondary">
                                Format: YYYY-YYYY
                            </small>

                        </div>

                    </div>

                    <!-- INFORMATION -->

                    <div class="info-box mt-4">

                        <div class="d-flex gap-3">

                            <i class="bi bi-info-circle fs-4"></i>

                            <div>
                                <strong>Enrollment Reminder</strong>

                                <p class="small mb-0 mt-1">
                                    Make sure the student, course, semester, and academic year are correct before saving. Duplicate enrollment records for the same academic period are not allowed.
                                </p>
                            </div>

                        </div>

                    </div>

                    <!-- ACTIONS -->

                    <div class="d-flex justify-content-end gap-2 mt-4">

                        <a href="enrollments.php" class="btn btn-light border px-4">
                            Cancel
                        </a>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>
                            Save Enrollment
                        </button>

                    </div>

                </form>

            <?php endif; ?>

        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
