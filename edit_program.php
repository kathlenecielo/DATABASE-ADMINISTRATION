
<?php
session_start();

require_once '../includes/db.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$programId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$programId || $programId <= 0) {
    header("Location: programs.php?status=invalid");
    exit;
}

// Fetch existing program
$stmt = $pdo->prepare("
    SELECT program_id, program_code, program_name
    FROM week04_lab.programs
    WHERE program_id = :id
");
$stmt->execute(['id' => $programId]);

$program = $stmt->fetch();

if (!$program) {
    header("Location: programs.php?status=notfound");
    exit;
}

$programCode = $program['program_code'];
$programName = $program['program_name'];

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Invalid request. Please refresh the page and try again.");
    }

    $programCode = strtoupper(trim($_POST['program_code'] ?? ''));
    $programName = trim($_POST['program_name'] ?? '');

    if ($programCode === '') {
        $errors[] = "Program code is required.";
    }

    if ($programName === '') {
        $errors[] = "Program name is required.";
    }

    if (empty($errors)) {
        try {
            $update = $pdo->prepare("
                UPDATE week04_lab.programs
                SET
                    program_code = :program_code,
                    program_name = :program_name
                WHERE program_id = :id
            ");

            $update->execute([
                'program_code' => $programCode,
                'program_name' => $programName,
                'id' => $programId
            ]);

            header("Location: programs.php?status=updated");
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                $errors[] = "This program code already exists. Please use another code.";
            } else {
                error_log($e->getMessage());
                $errors[] = "Unable to update the program. Please try again.";
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
    <title>Edit Program | CampusCore</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<?php
$basePath = '../';
include '../includes/sidebar.php';
?>

<main class="main-content">

    <div class="container-fluid py-4">

        <div class="mb-4">
            <a href="programs.php" class="text-decoration-none">
                ← Back to Programs
            </a>

            <h2 class="fw-bold mt-3">Edit Program</h2>
            <p class="text-muted">
                Update the program information in CampusCore.
            </p>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li>
                                    <?= htmlspecialchars($error) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Program Code
                        </label>

                        <input
                            type="text"
                            name="program_code"
                            class="form-control"
                            value="<?= htmlspecialchars($programCode) ?>"
                            placeholder="e.g. BSIT"
                            required
                        >
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            Program Name
                        </label>

                        <input
                            type="text"
                            name="program_name"
                            class="form-control"
                            value="<?= htmlspecialchars($programName) ?>"
                            placeholder="e.g. Bachelor of Science in Information Technology"
                            required
                        >
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            Save Changes
                        </button>

                        <a href="programs.php"
                           class="btn btn-light px-4">
                            Cancel
                        </a>
                    </div>

                </form>

            </div>
        </div>

    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
