
<?php
require_once __DIR__ . '/../includes/db.php';

function escape($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$studentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$studentId || $studentId < 1) {
    die('Invalid student ID.');
}

$studentStmt = $pdo->prepare("
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
    WHERE s.student_id = :id
");

$studentStmt->execute(['id' => $studentId]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die('Student record not found.');
}

$enrollmentStmt = $pdo->prepare("
    SELECT
        e.enrollment_id,
        e.semester,
        e.academic_year,
        c.course_code,
        c.course_name,
        c.units
    FROM week04_lab.enrollments e
    INNER JOIN week04_lab.courses c
        ON e.course_id = c.course_id
    WHERE e.student_id = :id
    ORDER BY e.academic_year DESC, e.semester DESC, c.course_code
");

$enrollmentStmt->execute(['id' => $studentId]);
$enrollments = $enrollmentStmt->fetchAll(PDO::FETCH_ASSOC);

$totalUnits = 0;

foreach ($enrollments as $enrollment) {
    $totalUnits += (int) $enrollment['units'];
}

$totalCourses = count($enrollments);

$fullName = $student['first_name'] . ' ' . $student['last_name'];

$initials = strtoupper(
    substr($student['first_name'], 0, 1) .
    substr($student['last_name'], 0, 1)
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Profile | CampusCore</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">

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

        .profile-card,
        .info-card,
        .enrollment-card {
            background: white;
            border: 1px solid #e9edf5;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(16, 27, 61, .04);
        }

        .profile-card {
            padding: 30px;
        }

        .profile-avatar {
            width: 85px;
            height: 85px;
            min-width: 85px;
            border-radius: 22px;
            background: #e9efff;
            color: #263e83;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .info-card {
            padding: 22px;
            height: 100%;
        }

        .info-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #edf2ff;
            color: #263e83;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .info-label {
            color: #7b8498;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 19px;
            font-weight: 700;
            color: #172858;
        }

        .program-badge {
            background: #edf2ff;
            color: #314d9b;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .enrollment-card {
            padding: 25px;
        }

        .table thead th {
            background: #f7f8fc;
            color: #68738a;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 15px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
        }

        .btn-primary {
            background: #172858;
            border-color: #172858;
        }

        .btn-primary:hover {
            background: #263e83;
            border-color: #263e83;
        }

        @media(max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 12px;
            }

            .profile-card {
                padding: 20px;
            }

            .profile-header {
                flex-direction: column;
                align-items: flex-start !important;
            }
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">

        <!-- PAGE HEADER -->
        <div class="mb-4">

            <a href="students.php" class="text-decoration-none text-secondary">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Students
            </a>

            <h2 class="page-title mt-3 mb-1">Student Profile</h2>

            <p class="text-secondary mb-0">
                View the student's personal and academic information.
            </p>

        </div>

        <!-- PROFILE HEADER -->
        <div class="profile-card mb-4">

            <div class="d-flex justify-content-between align-items-center profile-header gap-3">

                <div class="d-flex align-items-center gap-4">

                    <div class="profile-avatar">
                        <?= escape($initials) ?>
                    </div>

                    <div>
                        <h3 class="fw-bold mb-1">
                            <?= escape($fullName) ?>
                        </h3>

                        <p class="text-secondary mb-2">
                            <?= escape($student['student_no']) ?>
                        </p>

                        <span class="program-badge">
                            <?= escape($student['program_code'] ?? 'No Program') ?>
                        </span>
                    </div>

                </div>

                <a href="edit_student.php?id=<?= (int)$studentId ?>"
                   class="btn btn-primary px-3">

                    <i class="bi bi-pencil me-1"></i>
                    Edit Profile

                </a>

            </div>

        </div>

        <!-- QUICK STATISTICS -->
        <div class="row g-3 mb-4">

            <div class="col-md-4">
                <div class="info-card">
                    <div class="info-icon mb-3">
                        <i class="bi bi-book"></i>
                    </div>

                    <p class="info-label">Total Enrolled Courses</p>

                    <div class="info-value">
                        <?= $totalCourses ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="info-card">
                    <div class="info-icon mb-3">
                        <i class="bi bi-journal-bookmark"></i>
                    </div>

                    <p class="info-label">Total Enrolled Units</p>

                    <div class="info-value">
                        <?= $totalUnits ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="info-card">
                    <div class="info-icon mb-3">
                        <i class="bi bi-mortarboard"></i>
                    </div>

                    <p class="info-label">Current Year Level</p>

                    <div class="info-value">
                        <?= escape($student['year_level']) ?> Year
                    </div>
                </div>
            </div>

        </div>

        <!-- PERSONAL INFORMATION -->
        <div class="enrollment-card mb-4">

            <h5 class="fw-bold mb-4">
                <i class="bi bi-person-vcard me-2"></i>
                Personal Information
            </h5>

            <div class="row g-4">

                <div class="col-md-6">
                    <p class="info-label">First Name</p>
                    <h6 class="fw-semibold">
                        <?= escape($student['first_name']) ?>
                    </h6>
                </div>

                <div class="col-md-6">
                    <p class="info-label">Last Name</p>
                    <h6 class="fw-semibold">
                        <?= escape($student['last_name']) ?>
                    </h6>
                </div>

                <div class="col-md-6">
                    <p class="info-label">Student Number</p>
                    <h6 class="fw-semibold">
                        <?= escape($student['student_no']) ?>
                    </h6>
                </div>

                <div class="col-md-6">
                    <p class="info-label">Email Address</p>
                    <h6 class="fw-semibold">
                        <?= escape($student['email']) ?>
                    </h6>
                </div>

                <div class="col-md-6">
                    <p class="info-label">Program</p>
                    <h6 class="fw-semibold">
                        <?= escape($student['program_name'] ?? 'Not assigned') ?>
                    </h6>
                </div>

                <div class="col-md-6">
                    <p class="info-label">Year Level</p>
                    <h6 class="fw-semibold">
                        <?= escape($student['year_level']) ?>
                    </h6>
                </div>

            </div>

        </div>

        <!-- ENROLLMENT HISTORY -->
        <div class="enrollment-card">

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

                <div>
                    <h5 class="fw-bold mb-1">
                        <i class="bi bi-journal-text me-2"></i>
                        Enrollment History
                    </h5>

                    <small class="text-secondary">
                        Courses associated with this student.
                    </small>
                </div>

                <span class="badge text-bg-light border">
                    <?= $totalCourses ?> course(s)
                </span>

            </div>

            <div class="table-responsive">

                <table class="table table-hover mb-0">

                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Units</th>
                            <th>Semester</th>
                            <th>Academic Year</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (!empty($enrollments)): ?>

                            <?php foreach ($enrollments as $enrollment): ?>

                                <tr>
                                    <td class="fw-semibold">
                                        <?= escape($enrollment['course_code']) ?>
                                    </td>

                                    <td>
                                        <?= escape($enrollment['course_name']) ?>
                                    </td>

                                    <td>
                                        <?= escape($enrollment['units']) ?>
                                    </td>

                                    <td>
                                        <?= escape($enrollment['semester']) ?>
                                    </td>

                                    <td>
                                        <?= escape($enrollment['academic_year']) ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="text-center py-5">

                                    <i class="bi bi-journal-x fs-1 text-secondary"></i>

                                    <h6 class="fw-bold mt-3">
                                        No Enrollment Records
                                    </h6>

                                    <p class="text-secondary mb-0">
                                        This student does not have any recorded enrollments yet.
                                    </p>

                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

        <div class="text-secondary small mt-3">
            <i class="bi bi-shield-check me-1"></i>
            Academic information is retrieved from your PostgreSQL database.
        </div>

    </main>

</body>
</html>
