
<?php
session_start();

require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$errors = [];

// DELETE ENROLLMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_enrollment'])) {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errors[] = 'Invalid request. Please refresh the page and try again.';
    } else {

        $enrollmentId = filter_var(
            $_POST['enrollment_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$enrollmentId || $enrollmentId < 1) {
            $errors[] = 'Invalid enrollment ID.';
        } else {

            try {
                $delete = $pdo->prepare("
                    DELETE FROM week04_lab.enrollments
                    WHERE enrollment_id = :enrollment_id
                ");

                $delete->execute([
                    'enrollment_id' => $enrollmentId
                ]);

                header('Location: enrollments.php?status=deleted');
                exit;

            } catch (PDOException $e) {
                error_log($e->getMessage());
                $errors[] = 'Unable to delete this enrollment.';
            }
        }
    }
}

// FETCH ENROLLMENTS
try {

    $sql = "
        SELECT
            e.enrollment_id,
            e.semester,
            e.academic_year,
            s.student_id,
            s.student_no,
            s.first_name,
            s.last_name,
            c.course_id,
            c.course_code,
            c.course_name,
            c.units,
            p.program_code
        FROM week04_lab.enrollments e
        INNER JOIN week04_lab.students s
            ON e.student_id = s.student_id
        INNER JOIN week04_lab.courses c
            ON e.course_id = c.course_id
        LEFT JOIN week04_lab.programs p
            ON s.program_id = p.program_id
    ";

    $params = [];

    if ($search !== '') {
        $sql .= "
            WHERE
                s.student_no ILIKE :student_no
                OR s.first_name ILIKE :first_name
                OR s.last_name ILIKE :last_name
                OR c.course_code ILIKE :course_code
                OR c.course_name ILIKE :course_name
                OR e.academic_year ILIKE :academic_year
        ";

        $term = '%' . $search . '%';

        $params = [
            'student_no' => $term,
            'first_name' => $term,
            'last_name' => $term,
            'course_code' => $term,
            'course_name' => $term,
            'academic_year' => $term
        ];
    }

    $sql .= "
        ORDER BY
            e.academic_year DESC,
            e.semester ASC,
            s.last_name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // STATISTICS
    $totalEnrollments = (int)$pdo->query("
        SELECT COUNT(*) FROM week04_lab.enrollments
    ")->fetchColumn();

    $totalStudentsEnrolled = (int)$pdo->query("
        SELECT COUNT(DISTINCT student_id)
        FROM week04_lab.enrollments
    ")->fetchColumn();

    $totalCourseEnrollments = (int)$pdo->query("
        SELECT COUNT(DISTINCT course_id)
        FROM week04_lab.enrollments
    ")->fetchColumn();

} catch (PDOException $e) {

    error_log($e->getMessage());

    die('Unable to load enrollment records. Please check your database connection and table structure.');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Enrollment Management | CampusCore</title>

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

        .stat-card {
            background: #fff;
            border: 1px solid #e9edf5;
            border-radius: 14px;
            padding: 22px;
            height: 100%;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #edf2ff;
            color: #263e83;
            font-size: 21px;
        }

        .stat-number {
            font-size: 27px;
            font-weight: 700;
            color: #172858;
        }

        .content-card {
            background: #fff;
            border: 1px solid #e9edf5;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(16, 27, 61, .03);
        }

        .table thead th {
            background: #f7f8fc;
            color: #65708a;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 15px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #34415e;
        }

        .search-input {
            min-width: 220px;
            border-radius: 9px;
            padding: 10px 13px;
        }

        .btn-primary {
            background: #172858;
            border-color: #172858;
            border-radius: 9px;
        }

        .btn-primary:hover {
            background: #263e83;
            border-color: #263e83;
        }

        .student-name {
            font-weight: 600;
            color: #172858;
        }

        .course-code {
            font-weight: 700;
            color: #263e83;
        }

        .empty-state {
            padding: 55px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 45px;
            color: #aab6d2;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 12px;
            }

            .content-card {
                padding: 15px;
            }

            .stat-number {
                font-size: 23px;
            }
        }

    </style>

</head>

<body>

    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">

        <!-- HEADER -->

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">

            <div>
                <h2 class="page-title mb-1">Enrollment Management</h2>

                <p class="text-secondary mb-0">
                    Manage student course registrations and academic enrollment records.
                </p>
            </div>

            <a href="add_enrollment.php" class="btn btn-primary px-4 py-2">
                <i class="bi bi-plus-lg me-2"></i>
                New Enrollment
            </a>

        </div>

        <!-- STATUS -->

        <?php if ($status === 'added'): ?>

            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>
                Enrollment successfully created.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

        <?php elseif ($status === 'updated'): ?>

            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>
                Enrollment successfully updated.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

        <?php elseif ($status === 'deleted'): ?>

            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>
                Enrollment successfully deleted.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

        <?php endif; ?>

        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <div><?= escape($error) ?></div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

        <!-- STATISTICS -->

        <div class="row g-3 mb-4">

            <div class="col-md-4">
                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>
                            <p class="text-secondary mb-1">Total Enrollments</p>
                            <div class="stat-number">
                                <?= number_format($totalEnrollments) ?>
                            </div>
                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-journal-check"></i>
                        </div>

                    </div>

                    <small class="text-secondary">
                        All recorded course registrations
                    </small>

                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>
                            <p class="text-secondary mb-1">Students Enrolled</p>
                            <div class="stat-number">
                                <?= number_format($totalStudentsEnrolled) ?>
                            </div>
                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-people"></i>
                        </div>

                    </div>

                    <small class="text-secondary">
                        Unique students with enrollments
                    </small>

                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>
                            <p class="text-secondary mb-1">Courses With Enrollees</p>
                            <div class="stat-number">
                                <?= number_format($totalCourseEnrollments) ?>
                            </div>
                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-book"></i>
                        </div>

                    </div>

                    <small class="text-secondary">
                        Unique courses with registrations
                    </small>

                </div>
            </div>

        </div>

        <!-- ENROLLMENT DIRECTORY -->

        <div class="content-card">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

                <div>
                    <h5 class="fw-bold mb-1">Enrollment Records</h5>

                    <p class="text-secondary small mb-0">
                        View student registrations, courses, and academic periods.
                    </p>
                </div>

                <form method="GET" class="d-flex gap-2">

                    <input
                        type="search"
                        name="search"
                        class="form-control search-input"
                        placeholder="Search enrollments..."
                        value="<?= escape($search) ?>"
                    >

                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search"></i>
                    </button>

                    <?php if ($search !== ''): ?>
                        <a href="enrollments.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>

                </form>

            </div>

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Program</th>
                            <th>Course</th>
                            <th>Units</th>
                            <th>Semester</th>
                            <th>Academic Year</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($enrollments) > 0): ?>

                            <?php foreach ($enrollments as $enrollment): ?>

                                <tr>

                                    <td>
                                        <div class="student-name">
                                            <?= escape($enrollment['first_name'] . ' ' . $enrollment['last_name']) ?>
                                        </div>

                                        <small class="text-secondary">
                                            <?= escape($enrollment['student_no']) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <span class="badge bg-light text-primary border">
                                            <?= escape($enrollment['program_code'] ?? 'N/A') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="course-code">
                                            <?= escape($enrollment['course_code']) ?>
                                        </div>

                                        <small class="text-secondary">
                                            <?= escape($enrollment['course_name']) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?= (int)$enrollment['units'] ?>
                                    </td>

                                    <td>
                                        <?= escape($enrollment['semester']) ?>
                                    </td>

                                    <td>
                                        <?= escape($enrollment['academic_year']) ?>
                                    </td>

                                    <td>
                                        <div class="d-flex justify-content-center gap-2">

                                            <a
                                                href="edit_enrollment.php?id=<?= (int)$enrollment['enrollment_id'] ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Edit Enrollment"
                                            >
                                                <i class="bi bi-pencil-square"></i>
                                            </a>

                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Are you sure you want to delete this enrollment record?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= escape($_SESSION['csrf_token']) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="enrollment_id"
                                                    value="<?= (int)$enrollment['enrollment_id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="delete_enrollment"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Delete Enrollment"
                                                >
                                                    <i class="bi bi-trash"></i>
                                                </button>

                                            </form>

                                        </div>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="7">

                                    <div class="empty-state">

                                        <div class="empty-icon mb-3">
                                            <i class="bi bi-journal-x"></i>
                                        </div>

                                        <h5 class="fw-bold">No Enrollment Records Found</h5>

                                        <p class="text-secondary mb-3">
                                            <?= $search !== ''
                                                ? 'No records matched your search.'
                                                : 'Start by registering a student in a course.' ?>
                                        </p>

                                        <?php if ($search === ''): ?>
                                            <a href="add_enrollment.php" class="btn btn-primary">
                                                <i class="bi bi-plus-lg me-2"></i>
                                                Create First Enrollment
                                            </a>
                                        <?php endif; ?>

                                    </div>

                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">

                <small class="text-secondary">
                    Showing <?= count($enrollments) ?> enrollment record(s)
                </small>

                <small class="text-secondary">
                    CampusCore Enrollment Management
                </small>

            </div>

        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
