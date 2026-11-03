
<?php
session_start();

require_once __DIR__ . '/../includes/db.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Escape output
function escape($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$errors = [];
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

// DELETE COURSE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errors[] = 'Invalid request. Please refresh the page and try again.';
    } else {

        $courseId = filter_var(
            $_POST['course_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$courseId || $courseId < 1) {
            $errors[] = 'Invalid course ID.';
        } else {
            try {
                $delete = $pdo->prepare("
                    DELETE FROM week04_lab.courses
                    WHERE course_id = :course_id
                ");

                $delete->execute([
                    'course_id' => $courseId
                ]);

                header('Location: courses.php?status=deleted');
                exit;

            } catch (PDOException $e) {

                error_log($e->getMessage());

                if ($e->getCode() === '23503') {
                    header('Location: courses.php?status=blocked');
                    exit;
                }

                $errors[] = 'Unable to delete this course. Please try again.';
            }
        }
    }
}

// FETCH COURSES
try {

    $sql = "
        SELECT
            c.course_id,
            c.course_code,
            c.course_name,
            c.units,
            COUNT(e.enrollment_id) AS enrolled_count
        FROM week04_lab.courses c
        LEFT JOIN week04_lab.enrollments e
            ON c.course_id = e.course_id
    ";

    $params = [];

    if ($search !== '') {
        $sql .= "
            WHERE
                c.course_code ILIKE :search
                OR c.course_name ILIKE :search
        ";

        $params['search'] = '%' . $search . '%';
    }

    $sql .= "
        GROUP BY
            c.course_id,
            c.course_code,
            c.course_name,
            c.units
        ORDER BY c.course_code ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // DASHBOARD STATISTICS
    $statsStmt = $pdo->query("
        SELECT
            COUNT(*) AS total_courses,
            COALESCE(SUM(units), 0) AS total_units
        FROM week04_lab.courses
    ");

    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    $enrollmentStmt = $pdo->query("
        SELECT COUNT(*) FROM week04_lab.enrollments
    ");

    $totalEnrollments = (int)$enrollmentStmt->fetchColumn();

} catch (PDOException $e) {

    error_log($e->getMessage());

    die('Unable to load course information. Please check your database connection and table structure.');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Course Management | CampusCore</title>

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
            padding: 16px 15px;
            vertical-align: middle;
            color: #34415e;
        }

        .course-code {
            font-weight: 700;
            color: #263e83;
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

        .btn-outline-secondary,
        .btn-outline-primary {
            border-radius: 8px;
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

        <!-- PAGE HEADER -->

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">

            <div>
                <h2 class="page-title mb-1">Course Management</h2>

                <p class="text-secondary mb-0">
                    Manage academic courses and their enrollment records.
                </p>
            </div>

            <a href="add_course.php" class="btn btn-primary px-4 py-2">
                <i class="bi bi-plus-lg me-2"></i>
                Add Course
            </a>

        </div>

        <!-- STATUS MESSAGES -->

        <?php if ($status === 'added'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>
                Course successfully added.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

        <?php elseif ($status === 'updated'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>
                Course successfully updated.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

        <?php elseif ($status === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>
                Course successfully deleted.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

        <?php elseif ($status === 'blocked'): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i>
                This course cannot be deleted because it has existing enrollments.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

        <?php elseif ($status === 'invalid'): ?>
            <div class="alert alert-danger">
                Invalid course ID.
            </div>

        <?php elseif ($status === 'notfound'): ?>
            <div class="alert alert-warning">
                Course record not found.
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
                            <p class="text-secondary mb-1">Total Courses</p>
                            <div class="stat-number">
                                <?= number_format((int)$stats['total_courses']) ?>
                            </div>
                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-book"></i>
                        </div>

                    </div>

                    <small class="text-secondary">Registered academic courses</small>

                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>
                            <p class="text-secondary mb-1">Total Units</p>
                            <div class="stat-number">
                                <?= number_format((int)$stats['total_units']) ?>
                            </div>
                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-journal-check"></i>
                        </div>

                    </div>

                    <small class="text-secondary">Combined course units</small>

                </div>
            </div>

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
                            <i class="bi bi-people"></i>
                        </div>

                    </div>

                    <small class="text-secondary">Recorded course enrollments</small>

                </div>
            </div>

        </div>

        <!-- COURSE TABLE -->

        <div class="content-card">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

                <div>
                    <h5 class="fw-bold mb-1">Course Directory</h5>

                    <p class="text-secondary small mb-0">
                        View and manage all registered courses.
                    </p>
                </div>

                <form method="GET" class="d-flex gap-2">

                    <input
                        type="search"
                        name="search"
                        class="form-control search-input"
                        placeholder="Search courses..."
                        value="<?= escape($search) ?>"
                    >

                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search"></i>
                    </button>

                    <?php if ($search !== ''): ?>
                        <a href="courses.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>

                </form>

            </div>

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Units</th>
                            <th>Enrollments</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($courses) > 0): ?>

                            <?php foreach ($courses as $course): ?>

                                <tr>

                                    <td>
                                        <span class="course-code">
                                            <?= escape($course['course_code']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= escape($course['course_name']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= number_format((int)$course['units']) ?>
                                        <small class="text-secondary">units</small>
                                    </td>

                                    <td>
                                        <span class="badge rounded-pill bg-light text-primary border px-3 py-2">
                                            <?= number_format((int)$course['enrolled_count']) ?>
                                        </span>
                                    </td>

                                    <td>

                                        <div class="d-flex justify-content-center gap-2">

                                            <a
                                                href="edit_course.php?id=<?= (int)$course['course_id'] ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Edit Course"
                                            >
                                                <i class="bi bi-pencil-square"></i>
                                            </a>

                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Are you sure you want to delete this course?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= escape($_SESSION['csrf_token']) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="course_id"
                                                    value="<?= (int)$course['course_id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="delete_course"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Delete Course"
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
                                <td colspan="5">

                                    <div class="empty-state">

                                        <div class="empty-icon mb-3">
                                            <i class="bi bi-journal-x"></i>
                                        </div>

                                        <h5 class="fw-bold">
                                            No Courses Found
                                        </h5>

                                        <p class="text-secondary mb-3">
                                            <?= $search !== ''
                                                ? 'No courses matched your search.'
                                                : 'There are no registered courses yet.' ?>
                                        </p>

                                        <?php if ($search === ''): ?>
                                            <a href="add_course.php" class="btn btn-primary">
                                                <i class="bi bi-plus-lg me-2"></i>
                                                Add Your First Course
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
                    Showing <?= count($courses) ?> course(s)
                </small>

                <small class="text-secondary">
                    CampusCore Course Management
                </small>

            </div>

        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
