
<?php
session_start();
require_once "../includes/db.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Selected module view.
$view = $_GET["view"] ?? "students";

$allowedViews = [
    "students",
    "courses",
    "enrollments",
    "programs",
    "grouped"
];

if (!in_array($view, $allowedViews, true)) {
    $view = "students";
}

// Search and filter inputs.
$search = trim($_GET["search"] ?? "");
$yearLevel = $_GET["year_level"] ?? "";
$sort = $_GET["sort"] ?? "name";

// Validate year level.
if (!in_array($yearLevel, ["", "1", "2", "3", "4"], true)) {
    $yearLevel = "";
}

// Allow only predefined sorting options.
$sortOptions = [
    "name" => "s.last_name ASC, s.first_name ASC",
    "student_no" => "s.student_no ASC",
    "year_asc" => "s.year_level ASC, s.last_name ASC",
    "year_desc" => "s.year_level DESC, s.last_name ASC"
];

if (!isset($sortOptions[$sort])) {
    $sort = "name";
}

// Dashboard statistics.
$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM week04_lab.students) AS total_students,
        (SELECT COUNT(*) FROM week04_lab.programs) AS total_programs,
        (SELECT COUNT(*) FROM week04_lab.courses) AS total_courses,
        (SELECT COUNT(*) FROM week04_lab.enrollments) AS total_enrollments
")->fetch();

$records = [];
$pageTitle = "";
$pageDescription = "";
$errorMessage = "";

try {

    switch ($view) {

        // RETRIEVE + FILTER + SORT STUDENTS
        case "students":

            $pageTitle = "Student Records";
            $pageDescription = "Retrieve, filter, search, and sort student records.";

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
                    ON p.program_id = s.program_id

                WHERE 1 = 1
            ";

            $params = [];

            if ($search !== "") {
                $sql .= "
                    AND (
                        s.student_no ILIKE :search1
                        OR s.first_name ILIKE :search2
                        OR s.last_name ILIKE :search3
                        OR s.email ILIKE :search4
                    )
                ";

                $keyword = "%" . $search . "%";

                $params["search1"] = $keyword;
                $params["search2"] = $keyword;
                $params["search3"] = $keyword;
                $params["search4"] = $keyword;
            }

            if ($yearLevel !== "") {
                $sql .= " AND s.year_level = :year_level";
                $params["year_level"] = (int)$yearLevel;
            }

            $sql .= " ORDER BY " . $sortOptions[$sort];

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $records = $stmt->fetchAll();

            break;


        // RETRIEVE + SORT COURSES
        case "courses":

            $pageTitle = "Course Records";
            $pageDescription = "Retrieve and arrange all available course records.";

            $sql = "
                SELECT
                    course_id,
                    course_code,
                    course_name,
                    units

                FROM week04_lab.courses

                ORDER BY course_code ASC
            ";

            $records = $pdo->query($sql)->fetchAll();

            break;


        // RETRIEVE ENROLLMENTS
        case "enrollments":

            $pageTitle = "Enrollment Records";
            $pageDescription = "Retrieve enrollment information with related student and course details.";

            $sql = "
                SELECT
                    en.enrollment_id,
                    s.student_no,
                    s.first_name,
                    s.last_name,
                    c.course_code,
                    c.course_name,
                    en.semester,
                    en.academic_year

                FROM week04_lab.enrollments en

                INNER JOIN week04_lab.students s
                    ON s.student_id = en.student_id

                INNER JOIN week04_lab.courses c
                    ON c.course_id = en.course_id

                ORDER BY
                    en.academic_year DESC,
                    en.semester ASC,
                    s.last_name ASC
            ";

            $records = $pdo->query($sql)->fetchAll();

            break;


        // RETRIEVE PROGRAMS
        case "programs":

            $pageTitle = "Academic Programs";
            $pageDescription = "Retrieve all programs and their corresponding student counts.";

            $sql = "
                SELECT
                    p.program_id,
                    p.program_code,
                    p.program_name,
                    COUNT(s.student_id) AS total_students

                FROM week04_lab.programs p

                LEFT JOIN week04_lab.students s
                    ON s.program_id = p.program_id

                GROUP BY
                    p.program_id,
                    p.program_code,
                    p.program_name

                ORDER BY p.program_code ASC
            ";

            $records = $pdo->query($sql)->fetchAll();

            break;


        // GROUP BY + AGGREGATE
        case "grouped":

            $pageTitle = "Grouped Student Summary";
            $pageDescription = "Use GROUP BY and aggregate functions to summarize students by program and year level.";

            $sql = "
                SELECT
                    p.program_code,
                    p.program_name,
                    s.year_level,
                    COUNT(s.student_id) AS total_students

                FROM week04_lab.students s

                INNER JOIN week04_lab.programs p
                    ON p.program_id = s.program_id

                GROUP BY
                    p.program_code,
                    p.program_name,
                    s.year_level

                ORDER BY
                    p.program_code ASC,
                    s.year_level ASC
            ";

            $records = $pdo->query($sql)->fetchAll();

            break;
    }

} catch (PDOException $e) {

    error_log($e->getMessage());

    $errorMessage = "Unable to retrieve records. Check your week04_lab schema permissions and table structure.";

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Week 6 Queries | DB Practical</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        .stat-card,
        .table-card,
        .query-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 22px;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .stat-number {
            font-size: 27px;
            font-weight: 800;
            margin-top: 12px;
        }

        .stat-label {
            font-size: 11px;
            color: var(--muted);
            font-weight: 700;
        }

        .module-nav {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .module-nav a {
            text-decoration: none;
            border: 1px solid var(--border);
            padding: 9px 15px;
            border-radius: 9px;
            color: var(--text);
            font-size: 13px;
            font-weight: 600;
            background: white;
        }

        .module-nav a.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .query-label {
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        .query-description {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.6;
        }

        .table-card {
            overflow: hidden;
        }

        .record-count {
            font-size: 12px;
            font-weight: 700;
        }

        .empty-state {
            text-align: center;
            padding: 45px 20px;
        }

        .empty-state i {
            font-size: 40px;
            color: #adb5bd;
        }

        .sql-keyword {
            color: #7c3aed;
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .stat-card,
            .table-card,
            .query-card {
                padding: 16px;
            }

            .module-nav {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
            }

            .module-nav a {
                text-align: center;
            }
        }

    </style>

</head>

<body>

<div class="app-layout">

    <?php
    $basePath = "../";
    require "../includes/sidebar.php";
    ?>

    <main class="main-content">

        <header class="topbar">

            <div>
                <div class="breadcrumb-text">
                    PART 2 / WEEK 6
                </div>

                <h4>Retrieve, Filter, Sort & Group</h4>
            </div>

        </header>

        <div class="content-area">

            <!-- Heading -->
            <div class="welcome-section">

                <div>
                    <h2>Week 6 SQL Operations</h2>

                    <p class="text-muted small">
                        Practice retrieving, filtering, sorting, and grouping database records.
                    </p>
                </div>

                <span class="badge bg-primary-subtle text-primary px-3 py-2">
                    WEEK 6
                </span>

            </div>


            <!-- Statistics -->
            <div class="row g-3 mb-4">

                <div class="col-6 col-xl-3">
                    <div class="stat-card">

                        <div class="stat-icon bg-primary-subtle text-primary">
                            <i class="bi bi-people"></i>
                        </div>

                        <div class="stat-number">
                            <?= number_format((int)$stats["total_students"]) ?>
                        </div>

                        <div class="stat-label">
                            TOTAL STUDENTS
                        </div>

                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">

                        <div class="stat-icon bg-info-subtle text-info">
                            <i class="bi bi-mortarboard"></i>
                        </div>

                        <div class="stat-number">
                            <?= number_format((int)$stats["total_programs"]) ?>
                        </div>

                        <div class="stat-label">
                            TOTAL PROGRAMS
                        </div>

                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">

                        <div class="stat-icon bg-success-subtle text-success">
                            <i class="bi bi-book"></i>
                        </div>

                        <div class="stat-number">
                            <?= number_format((int)$stats["total_courses"]) ?>
                        </div>

                        <div class="stat-label">
                            TOTAL COURSES
                        </div>

                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">

                        <div class="stat-icon bg-warning-subtle text-warning">
                            <i class="bi bi-journal-check"></i>
                        </div>

                        <div class="stat-number">
                            <?= number_format((int)$stats["total_enrollments"]) ?>
                        </div>

                        <div class="stat-label">
                            TOTAL ENROLLMENTS
                        </div>

                    </div>
                </div>

            </div>


            <!-- SQL Concepts -->
            <div class="row g-3 mb-4">

                <div class="col-md-3">
                    <div class="query-card h-100">

                        <div class="query-label">01 · Retrieve</div>

                        <h6 class="fw-bold mt-2">
                            SELECT
                        </h6>

                        <p class="query-description mb-0">
                            Retrieve specific columns and records from database tables.
                        </p>

                    </div>
                </div>

                <div class="col-md-3">
                    <div class="query-card h-100">

                        <div class="query-label">02 · Filter</div>

                        <h6 class="fw-bold mt-2">
                            WHERE
                        </h6>

                        <p class="query-description mb-0">
                            Display only records that meet specified conditions.
                        </p>

                    </div>
                </div>

                <div class="col-md-3">
                    <div class="query-card h-100">

                        <div class="query-label">03 · Sort</div>

                        <h6 class="fw-bold mt-2">
                            ORDER BY
                        </h6>

                        <p class="query-description mb-0">
                            Arrange query results in ascending or descending order.
                        </p>

                    </div>
                </div>

                <div class="col-md-3">
                    <div class="query-card h-100">

                        <div class="query-label">04 · Group</div>

                        <h6 class="fw-bold mt-2">
                            GROUP BY
                        </h6>

                        <p class="query-description mb-0">
                            Organize records into groups and calculate aggregate values.
                        </p>

                    </div>
                </div>

            </div>


            <!-- Module Navigation -->
            <section class="table-card mb-4">

                <h5 class="fw-bold mb-3">
                    SQL Practice Modules
                </h5>

                <div class="module-nav">

                    <a href="?view=students"
                       class="<?= $view === "students" ? "active" : "" ?>">
                        <i class="bi bi-people me-1"></i>
                        Students
                    </a>

                    <a href="?view=courses"
                       class="<?= $view === "courses" ? "active" : "" ?>">
                        <i class="bi bi-book me-1"></i>
                        Courses
                    </a>

                    <a href="?view=enrollments"
                       class="<?= $view === "enrollments" ? "active" : "" ?>">
                        <i class="bi bi-journal-check me-1"></i>
                        Enrollments
                    </a>

                    <a href="?view=programs"
                       class="<?= $view === "programs" ? "active" : "" ?>">
                        <i class="bi bi-mortarboard me-1"></i>
                        Programs
                    </a>

                    <a href="?view=grouped"
                       class="<?= $view === "grouped" ? "active" : "" ?>">
                        <i class="bi bi-bar-chart me-1"></i>
                        Grouped Summary
                    </a>

                </div>

            </section>


            <!-- Results Section -->
            <section class="table-card">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

                    <div>

                        <h5 class="fw-bold mb-1">
                            <?= e($pageTitle) ?>
                        </h5>

                        <p class="text-muted small mb-0">
                            <?= e($pageDescription) ?>
                        </p>

                    </div>

                    <span class="badge bg-primary-subtle text-primary px-3 py-2">
                        <?= count($records) ?> records
                    </span>

                </div>


                <?php if ($view === "students"): ?>

                    <!-- Student Search and Filter -->
                    <form method="GET" class="row g-2 mb-4">

                        <input type="hidden" name="view" value="students">

                        <div class="col-lg-5">

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search student number, name, or email..."
                                value="<?= e($search) ?>"
                            >

                        </div>

                        <div class="col-lg-3">

                            <select name="year_level" class="form-select">

                                <option value="">All Year Levels</option>

                                <?php for ($year = 1; $year <= 4; $year++): ?>

                                    <option
                                        value="<?= $year ?>"
                                        <?= $yearLevel === (string)$year ? "selected" : "" ?>
                                    >
                                        Year <?= $year ?>
                                    </option>

                                <?php endfor; ?>

                            </select>

                        </div>

                        <div class="col-lg-2">

                            <select name="sort" class="form-select">

                                <option value="name" <?= $sort === "name" ? "selected" : "" ?>>
                                    Name A–Z
                                </option>

                                <option value="student_no" <?= $sort === "student_no" ? "selected" : "" ?>>
                                    Student Number
                                </option>

                                <option value="year_asc" <?= $sort === "year_asc" ? "selected" : "" ?>>
                                    Year Ascending
                                </option>

                                <option value="year_desc" <?= $sort === "year_desc" ? "selected" : "" ?>>
                                    Year Descending
                                </option>

                            </select>

                        </div>

                        <div class="col-lg-2 d-flex gap-2">

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-1"></i>
                                Apply
                            </button>

                            <a href="?view=students" class="btn btn-light">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>

                        </div>

                    </form>

                <?php endif; ?>


                <?php if ($errorMessage !== ""): ?>

                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= e($errorMessage) ?>
                    </div>

                <?php elseif (empty($records)): ?>

                    <div class="empty-state">

                        <i class="bi bi-database"></i>

                        <h6 class="fw-bold mt-3">
                            No Records Found
                        </h6>

                        <p class="text-muted small">
                            There are no records matching your selected criteria.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table table-hover align-middle">

                            <thead>

                                <?php if ($view === "students"): ?>

                                    <tr>
                                        <th>Student Number</th>
                                        <th>Student Name</th>
                                        <th>Email</th>
                                        <th>Program</th>
                                        <th>Year Level</th>
                                    </tr>

                                <?php elseif ($view === "courses"): ?>

                                    <tr>
                                        <th>Course ID</th>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Units</th>
                                    </tr>

                                <?php elseif ($view === "enrollments"): ?>

                                    <tr>
                                        <th>Enrollment ID</th>
                                        <th>Student</th>
                                        <th>Course</th>
                                        <th>Semester</th>
                                        <th>Academic Year</th>
                                    </tr>

                                <?php elseif ($view === "programs"): ?>

                                    <tr>
                                        <th>Program Code</th>
                                        <th>Program Name</th>
                                        <th>Total Students</th>
                                    </tr>

                                <?php elseif ($view === "grouped"): ?>

                                    <tr>
                                        <th>Program Code</th>
                                        <th>Program Name</th>
                                        <th>Year Level</th>
                                        <th>Total Students</th>
                                    </tr>

                                <?php endif; ?>

                            </thead>

                            <tbody>

                                <?php foreach ($records as $record): ?>

                                    <tr>

                                        <?php if ($view === "students"): ?>

                                            <td>
                                                <strong><?= e($record["student_no"]) ?></strong>
                                            </td>

                                            <td>
                                                <?= e($record["first_name"] . " " . $record["last_name"]) ?>
                                            </td>

                                            <td>
                                                <?= e($record["email"]) ?>
                                            </td>

                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?= e($record["program_code"] ?? "N/A") ?>
                                                </span>
                                            </td>

                                            <td>
                                                Year <?= (int)$record["year_level"] ?>
                                            </td>


                                        <?php elseif ($view === "courses"): ?>

                                            <td><?= (int)$record["course_id"] ?></td>

                                            <td>
                                                <strong><?= e($record["course_code"]) ?></strong>
                                            </td>

                                            <td><?= e($record["course_name"]) ?></td>

                                            <td><?= (int)$record["units"] ?> units</td>


                                        <?php elseif ($view === "enrollments"): ?>

                                            <td>
                                                #<?= (int)$record["enrollment_id"] ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= e($record["first_name"] . " " . $record["last_name"]) ?>
                                                </strong>

                                                <div class="text-muted small">
                                                    <?= e($record["student_no"]) ?>
                                                </div>
                                            </td>

                                            <td>
                                                <strong><?= e($record["course_code"]) ?></strong>

                                                <div class="text-muted small">
                                                    <?= e($record["course_name"]) ?>
                                                </div>
                                            </td>

                                            <td><?= e($record["semester"]) ?></td>

                                            <td><?= e($record["academic_year"]) ?></td>


                                        <?php elseif ($view === "programs"): ?>

                                            <td>
                                                <strong><?= e($record["program_code"]) ?></strong>
                                            </td>

                                            <td><?= e($record["program_name"]) ?></td>

                                            <td>
                                                <span class="badge bg-primary-subtle text-primary">
                                                    <?= number_format((int)$record["total_students"]) ?>
                                                    students
                                                </span>
                                            </td>


                                        <?php elseif ($view === "grouped"): ?>

                                            <td>
                                                <strong><?= e($record["program_code"]) ?></strong>
                                            </td>

                                            <td><?= e($record["program_name"]) ?></td>

                                            <td>
                                                Year <?= (int)$record["year_level"] ?>
                                            </td>

                                            <td>
                                                <span class="badge bg-primary-subtle text-primary">
                                                    <?= number_format((int)$record["total_students"]) ?>
                                                </span>
                                            </td>

                                        <?php endif; ?>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </section>


            <!-- Footer -->
            <footer class="footer">

                <span>
                    Database Administration Practical System
                </span>

                <span>
                    SQL Operations · Week 6
                </span>

            </footer>

        </div>

    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
