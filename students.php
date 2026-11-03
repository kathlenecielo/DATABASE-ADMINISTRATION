
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

// ==========================================
// DELETE STUDENT
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {

    if (!hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token'] ?? ''
    )) {
        die('Invalid request. Please refresh the page and try again.');
    }

    $studentId = filter_input(
        INPUT_POST,
        'student_id',
        FILTER_VALIDATE_INT
    );

    if ($studentId && $studentId > 0) {

        try {
            $delete = $pdo->prepare("
                DELETE FROM week04_lab.students
                WHERE student_id = :student_id
            ");

            $delete->execute([
                'student_id' => $studentId
            ]);

            if ($delete->rowCount() > 0) {
                header('Location: students.php?status=deleted');
            } else {
                header('Location: students.php?status=notfound');
            }

            exit;

        } catch (PDOException $e) {

            error_log($e->getMessage());

            header('Location: students.php?status=blocked');
            exit;
        }
    }

    header('Location: students.php?status=invalid');
    exit;
}

// ==========================================
// SEARCH STUDENTS
// ==========================================
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        s.student_id,
        s.student_no,
        s.first_name,
        s.last_name,
        s.email,
        s.year_level,
        p.program_code,
        p.program_name

    FROM week04_lab.students s

    LEFT JOIN week04_lab.programs p
        ON s.program_id = p.program_id
";

if ($search !== '') {

    $sql .= "
        WHERE
            s.student_no ILIKE :search1
            OR s.first_name ILIKE :search2
            OR s.last_name ILIKE :search3
            OR s.email ILIKE :search4
            OR p.program_code ILIKE :search5
            OR p.program_name ILIKE :search6
    ";
}

$sql .= " ORDER BY s.student_id DESC";

$stmt = $pdo->prepare($sql);

if ($search !== '') {

    $keyword = "%{$search}%";

    $stmt->execute([
        'search1' => $keyword,
        'search2' => $keyword,
        'search3' => $keyword,
        'search4' => $keyword,
        'search5' => $keyword,
        'search6' => $keyword
    ]);

} else {

    $stmt->execute();
}

$students = $stmt->fetchAll();

$status = $_GET['status'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Management | CampusCore</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <!-- Main Stylesheet -->
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
            font-weight: 700;
            color: #101b3d;
        }

        .student-card {
            background: #fff;
            border: 1px solid #e9edf5;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(16, 27, 61, 0.04);
        }

        .table thead th {
            background: #f7f8fc;
            color: #68738a;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 16px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 16px;
            vertical-align: middle;
        }

        .student-avatar {
            width: 38px;
            height: 38px;
            min-width: 38px;
            border-radius: 12px;
            background: #e9efff;
            color: #263e83;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 10px;
        }

        .program-badge {
            background: #edf2ff;
            color: #314d9b;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .btn-primary {
            background: #172858;
            border-color: #172858;
        }

        .btn-primary:hover {
            background: #263e83;
            border-color: #263e83;
        }

        .search-box {
            max-width: 420px;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 768px) {

            .main-content {
                margin-left: 0;
                padding: 20px 12px;
            }

            .page-heading {
                align-items: flex-start !important;
                flex-direction: column;
                gap: 15px;
            }

            .search-row {
                flex-direction: column;
                align-items: stretch !important;
            }

            .search-box {
                max-width: 100%;
            }

        }

    </style>

</head>

<body>

    <!-- SIDEBAR -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>


    <main class="main-content">

        <!-- PAGE HEADER -->
        <div class="d-flex justify-content-between page-heading align-items-center mb-4">

            <div>

                <p class="text-secondary mb-1">
                    CampusCore / Student Records
                </p>

                <h2 class="page-title mb-1">
                    Student Management
                </h2>

                <p class="text-secondary mb-0">
                    View and manage registered student information.
                </p>

            </div>

            <a href="add_student.php" class="btn btn-primary px-3 py-2">

                <i class="bi bi-plus-lg me-1"></i>
                Add Student

            </a>

        </div>


        <!-- STATUS MESSAGES -->
        <?php if ($status === 'added'): ?>

            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                Student added successfully!
            </div>

        <?php elseif ($status === 'updated'): ?>

            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                Student information updated successfully!
            </div>

        <?php elseif ($status === 'deleted'): ?>

            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                Student record deleted successfully.
            </div>

        <?php elseif ($status === 'blocked'): ?>

            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                This student cannot be deleted because another record may depend on it.
            </div>

        <?php elseif ($status === 'notfound'): ?>

            <div class="alert alert-info">
                The student record was not found.
            </div>

        <?php elseif ($status === 'invalid'): ?>

            <div class="alert alert-danger">
                Invalid student request.
            </div>

        <?php endif; ?>


        <!-- STUDENT DIRECTORY -->
        <div class="student-card p-3 p-md-4">

            <!-- DIRECTORY HEADER -->
            <div class="d-flex justify-content-between align-items-center search-row flex-wrap gap-3 mb-4">

                <div>

                    <h5 class="fw-bold mb-1">
                        Student Directory
                    </h5>

                    <small class="text-secondary">
                        <?= count($students) ?> student record(s) found
                    </small>

                </div>


                <!-- SEARCH FORM -->
                <form method="GET" class="d-flex gap-2 search-box w-100">

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Search student, number, or program..."
                        value="<?= escape($search) ?>"
                    >

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>

                    <?php if ($search !== ''): ?>

                        <a
                            href="students.php"
                            class="btn btn-outline-secondary"
                        >
                            <i class="bi bi-x-lg"></i>
                        </a>

                    <?php endif; ?>

                </form>

            </div>


            <!-- STUDENT TABLE -->
            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>Student</th>
                            <th>Student Number</th>
                            <th>Email</th>
                            <th>Program</th>
                            <th>Year Level</th>
                            <th class="text-center">Actions</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (!empty($students)): ?>

                            <?php foreach ($students as $student): ?>

                                <tr>

                                    <!-- STUDENT NAME -->
                                    <td>

                                        <div class="d-flex align-items-center">

                                            <div class="student-avatar">

                                                <?= escape(
                                                    strtoupper(
                                                        substr($student['first_name'], 0, 1)
                                                    )
                                                ) ?>

                                            </div>

                                            <div>

                                                <div class="fw-semibold">

                                                    <?= escape(
                                                        $student['first_name'] . ' ' .
                                                        $student['last_name']
                                                    ) ?>

                                                </div>

                                                <small class="text-secondary">

                                                    ID: <?= (int)$student['student_id'] ?>

                                                </small>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- STUDENT NUMBER -->
                                    <td>
                                        <?= escape($student['student_no']) ?>
                                    </td>


                                    <!-- EMAIL -->
                                    <td>
                                        <?= escape($student['email']) ?>
                                    </td>


                                    <!-- PROGRAM -->
                                    <td>

                                        <span class="program-badge">

                                            <?= escape(
                                                $student['program_code'] ?? 'No program'
                                            ) ?>

                                        </span>

                                        <div class="small text-secondary mt-1">

                                            <?= escape(
                                                $student['program_name'] ?? ''
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- YEAR LEVEL -->
                                    <td>

                                        <?= escape($student['year_level']) ?>

                                        <?= is_numeric($student['year_level'])
                                            ? ' Year'
                                            : '' ?>

                                    </td>


                                    <!-- ACTIONS -->
                                    <td class="text-center">

                                        <div class="d-inline-flex gap-1">

                                            <!-- VIEW PROFILE -->
                                            <a
                                                href="student_profile.php?id=<?= (int)$student['student_id'] ?>"
                                                class="btn btn-sm btn-outline-secondary action-btn"
                                                title="View student profile"
                                            >

                                                <i class="bi bi-eye"></i>

                                            </a>


                                            <!-- EDIT STUDENT -->
                                            <a
                                                href="edit_student.php?id=<?= (int)$student['student_id'] ?>"
                                                class="btn btn-sm btn-outline-primary action-btn"
                                                title="Edit student"
                                            >

                                                <i class="bi bi-pencil"></i>

                                            </a>


                                            <!-- DELETE STUDENT -->
                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Are you sure you want to delete this student record?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= escape($_SESSION['csrf_token']) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="student_id"
                                                    value="<?= (int)$student['student_id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="delete_student"
                                                    class="btn btn-sm btn-outline-danger action-btn"
                                                    title="Delete student"
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

                                <td colspan="6" class="text-center py-5">

                                    <i class="bi bi-people fs-1 text-secondary"></i>

                                    <h6 class="mt-3 fw-bold">
                                        No students found
                                    </h6>

                                    <p class="text-secondary mb-0">
                                        Try another search or add a new student.
                                    </p>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- DATABASE CONNECTION NOTICE -->
        <div class="text-secondary small mt-3">

            <i class="bi bi-shield-check me-1"></i>

            Student records are retrieved directly from your PostgreSQL database.

        </div>

    </main>


    <!-- BOOTSTRAP JAVASCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
