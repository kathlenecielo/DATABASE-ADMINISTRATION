
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
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

// ==========================================
// DELETE PROGRAM
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_program'])) {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errors[] = 'Invalid request. Please refresh the page and try again.';
    } else {

        $programId = filter_var(
            $_POST['program_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$programId || $programId < 1) {
            $errors[] = 'Invalid program ID.';
        } else {

            try {

                $delete = $pdo->prepare("
                    DELETE FROM week04_lab.programs
                    WHERE program_id = :program_id
                ");

                $delete->execute([
                    'program_id' => $programId
                ]);

                if ($delete->rowCount() > 0) {
                    header('Location: programs.php?status=deleted');
                } else {
                    header('Location: programs.php?status=notfound');
                }

                exit;

            } catch (PDOException $e) {

                error_log($e->getMessage());

                if ($e->getCode() === '23503') {
                    header('Location: programs.php?status=blocked');
                    exit;
                }

                $errors[] = 'Unable to delete this program. Please try again.';
            }
        }
    }
}

// ==========================================
// FETCH PROGRAMS
// ==========================================
try {

    $sql = "
        SELECT
            p.program_id,
            p.program_code,
            p.program_name,
            COUNT(s.student_id) AS student_count

        FROM week04_lab.programs p

        LEFT JOIN week04_lab.students s
            ON p.program_id = s.program_id
    ";

    if ($search !== '') {

        $sql .= "
            WHERE
                p.program_code ILIKE :search1
                OR p.program_name ILIKE :search2
        ";
    }

    $sql .= "
        GROUP BY
            p.program_id,
            p.program_code,
            p.program_name

        ORDER BY p.program_code ASC
    ";

    $stmt = $pdo->prepare($sql);

    if ($search !== '') {

        $keyword = "%{$search}%";

        $stmt->execute([
            'search1' => $keyword,
            'search2' => $keyword
        ]);

    } else {

        $stmt->execute();
    }

    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ======================================
    // PROGRAM STATISTICS
    // ======================================

    $statsStmt = $pdo->query("
        SELECT COUNT(*)
        FROM week04_lab.programs
    ");

    $totalPrograms = (int)$statsStmt->fetchColumn();

    $studentStmt = $pdo->query("
        SELECT COUNT(*)
        FROM week04_lab.students
    ");

    $totalStudents = (int)$studentStmt->fetchColumn();

    $assignedStmt = $pdo->query("
        SELECT COUNT(DISTINCT program_id)
        FROM week04_lab.students
        WHERE program_id IS NOT NULL
    ");

    $programsWithStudents = (int)$assignedStmt->fetchColumn();

} catch (PDOException $e) {

    error_log($e->getMessage());

    die('Unable to load program information. Please check your database connection and table structure.');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Program Management | CampusCore</title>

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

        .program-code {
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

                <h2 class="page-title mb-1">
                    Program Management
                </h2>

                <p class="text-secondary mb-0">
                    Manage academic programs and their assigned students.
                </p>

            </div>

            <a href="add_program.php" class="btn btn-primary px-4 py-2">

                <i class="bi bi-plus-lg me-2"></i>
                Add Program

            </a>

        </div>


        <!-- STATUS MESSAGES -->

        <?php if ($status === 'added'): ?>

            <div class="alert alert-success alert-dismissible fade show">

                <i class="bi bi-check-circle me-2"></i>
                Academic program successfully added.

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

            </div>

        <?php elseif ($status === 'updated'): ?>

            <div class="alert alert-success alert-dismissible fade show">

                <i class="bi bi-check-circle me-2"></i>
                Academic program successfully updated.

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

            </div>

        <?php elseif ($status === 'deleted'): ?>

            <div class="alert alert-success alert-dismissible fade show">

                <i class="bi bi-check-circle me-2"></i>
                Academic program successfully deleted.

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

            </div>

        <?php elseif ($status === 'blocked'): ?>

            <div class="alert alert-warning alert-dismissible fade show">

                <i class="bi bi-exclamation-triangle me-2"></i>
                This program cannot be deleted because students are still assigned to it.

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

            </div>

        <?php elseif ($status === 'notfound'): ?>

            <div class="alert alert-info alert-dismissible fade show">

                The selected program was not found.

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

            </div>

        <?php endif; ?>


        <!-- ERROR MESSAGES -->

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

                            <p class="text-secondary mb-1">
                                Total Programs
                            </p>

                            <div class="stat-number">
                                <?= number_format($totalPrograms) ?>
                            </div>

                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-mortarboard"></i>
                        </div>

                    </div>

                    <small class="text-secondary">
                        Registered academic programs
                    </small>

                </div>

            </div>


            <div class="col-md-4">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>

                            <p class="text-secondary mb-1">
                                Total Students
                            </p>

                            <div class="stat-number">
                                <?= number_format($totalStudents) ?>
                            </div>

                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-people"></i>
                        </div>

                    </div>

                    <small class="text-secondary">
                        Students in the database
                    </small>

                </div>

            </div>


            <div class="col-md-4">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>

                            <p class="text-secondary mb-1">
                                Programs With Students
                            </p>

                            <div class="stat-number">
                                <?= number_format($programsWithStudents) ?>
                            </div>

                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-diagram-3"></i>
                        </div>

                    </div>

                    <small class="text-secondary">
                        Programs with assigned students
                    </small>

                </div>

            </div>

        </div>


        <!-- PROGRAM DIRECTORY -->

        <div class="content-card">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

                <div>

                    <h5 class="fw-bold mb-1">
                        Academic Program Directory
                    </h5>

                    <p class="text-secondary small mb-0">
                        View and manage registered academic programs.
                    </p>

                </div>


                <form method="GET" class="d-flex gap-2">

                    <input
                        type="search"
                        name="search"
                        class="form-control search-input"
                        placeholder="Search programs..."
                        value="<?= escape($search) ?>"
                    >

                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search"></i>
                    </button>

                    <?php if ($search !== ''): ?>

                        <a href="programs.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i>
                        </a>

                    <?php endif; ?>

                </form>

            </div>


            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>Program Code</th>
                            <th>Program Name</th>
                            <th>Students</th>
                            <th class="text-center">Actions</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (count($programs) > 0): ?>

                            <?php foreach ($programs as $program): ?>

                                <tr>

                                    <td>

                                        <span class="program-code">

                                            <?= escape($program['program_code']) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <strong>

                                            <?= escape($program['program_name']) ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <span class="badge rounded-pill bg-light text-primary border px-3 py-2">

                                            <?= number_format((int)$program['student_count']) ?>
                                            student(s)

                                        </span>

                                    </td>


                                    <td>

                                        <div class="d-flex justify-content-center gap-2">

                                            <a
                                                href="edit_program.php?id=<?= (int)$program['program_id'] ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Edit Program"
                                            >

                                                <i class="bi bi-pencil-square"></i>

                                            </a>


                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Are you sure you want to delete this academic program?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= escape($_SESSION['csrf_token']) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="program_id"
                                                    value="<?= (int)$program['program_id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="delete_program"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Delete Program"
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

                                <td colspan="4">

                                    <div class="empty-state">

                                        <div class="empty-icon mb-3">
                                            <i class="bi bi-mortarboard"></i>
                                        </div>

                                        <h5 class="fw-bold">
                                            No Programs Found
                                        </h5>

                                        <p class="text-secondary mb-3">

                                            <?= $search !== ''
                                                ? 'No academic programs matched your search.'
                                                : 'There are no registered academic programs yet.' ?>

                                        </p>

                                        <?php if ($search === ''): ?>

                                            <a href="add_program.php" class="btn btn-primary">

                                                <i class="bi bi-plus-lg me-2"></i>
                                                Add Your First Program

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
                    Showing <?= count($programs) ?> program(s)
                </small>

                <small class="text-secondary">
                    CampusCore Program Management
                </small>

            </div>

        </div>


        <div class="text-secondary small mt-3">

            <i class="bi bi-database-check me-1"></i>

            Program records and student assignments are retrieved directly from PostgreSQL.

        </div>

    </main>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
