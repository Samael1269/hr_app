<?php
include 'config.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$full_name = $_SESSION["full_name"] ?? "User";
$email = $_SESSION["email"] ?? "user@example.com";

$error = "";
$job_updated = false;
$updated_job_title = "";

function parseSalaryRange($salary_range) {
    $salary_range = strtolower(trim($salary_range));

    if ($salary_range === "") {
        return [null, null];
    }

    preg_match_all('/(\d+(?:\.\d+)?)(k?)/', $salary_range, $matches, PREG_SET_ORDER);

    if (count($matches) >= 2) {
        $first = (float)$matches[0][1];
        $second = (float)$matches[1][1];

        if ($matches[0][2] === 'k') {
            $first *= 1000;
        }

        if ($matches[1][2] === 'k') {
            $second *= 1000;
        }

        return [$first, $second];
    }

    return [null, null];
}

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Invalid job ID.");
}

$job_id = (int)$_GET["id"];

$stmt = $conn->prepare("SELECT * FROM jobs WHERE job_id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Job not found.");
}

$job = $result->fetch_assoc();
$stmt->close();

$job_title = $job["job_title"];
$department = $job["department"];
$job_type = $job["job_type"];
$status = $job["status"];
$job_description = $job["job_description"];

$salary_range = "";
if (!empty($job["salary_min"]) && !empty($job["salary_max"])) {
    $salary_range = "$" . number_format($job["salary_min"] / 1000, 0) . "k - $" . number_format($job["salary_max"] / 1000, 0) . "k";
}

$requirements = !empty($job["required_skills"])
    ? array_filter(array_map("trim", explode("\n", $job["required_skills"])))
    : [""];

if (empty($requirements)) {
    $requirements = [""];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $job_title = trim($_POST["job_title"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $job_type = trim($_POST["job_type"] ?? "");
    $status = trim($_POST["status"] ?? "draft");
    $job_description = trim($_POST["job_description"] ?? "");
    $salary_range = trim($_POST["salary_range"] ?? "");
    $requirements = $_POST["requirements"] ?? [""];

    $requirements = array_map("trim", $requirements);
    $requirements = array_filter($requirements, function($item) {
        return $item !== "";
    });

    if (
        empty($job_title) ||
        empty($department) ||
        empty($job_type) ||
        empty($status) ||
        empty($job_description) ||
        empty($salary_range)
    ) {
        $error = "Please fill in all required fields.";
    } elseif (!in_array($job_type, ['Full-time', 'Part-time', 'Internship', 'Contract', 'Remote'])) {
        $error = "Invalid employment type selected.";
    } elseif (!in_array($status, ['active', 'draft', 'closed'])) {
        $error = "Invalid status selected.";
    } else {
        [$salary_min, $salary_max] = parseSalaryRange($salary_range);

        if ($salary_min === null || $salary_max === null) {
            $error = "Please enter a valid salary range, for example: \$120k - \$160k";
        } elseif ($salary_min > $salary_max) {
            $error = "Salary minimum cannot be greater than salary maximum.";
        } else {
            $required_skills = !empty($requirements) ? implode("\n", $requirements) : null;
            $required_education = null;
            $required_experience = null;

            $update_stmt = $conn->prepare("
                UPDATE jobs
                SET job_title = ?, department = ?, job_type = ?, job_description = ?, required_skills = ?, required_education = ?, required_experience = ?, salary_min = ?, salary_max = ?, status = ?
                WHERE job_id = ?
            ");

            if (!$update_stmt) {
                $error = "Database error: " . $conn->error;
            } else {
                $update_stmt->bind_param(
                    "sssssssddsi",
                    $job_title,
                    $department,
                    $job_type,
                    $job_description,
                    $required_skills,
                    $required_education,
                    $required_experience,
                    $salary_min,
                    $salary_max,
                    $status,
                    $job_id
                );

                if ($update_stmt->execute()) {
                    $job_updated = true;
                    $updated_job_title = $job_title;
                } else {
                    $error = "Failed to update job: " . $update_stmt->error;
                }

                $update_stmt->close();
            }
        }
    }

    if (empty($requirements)) {
        $requirements = [""];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job | ATS System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f3f4f6;
            color: #0f172a;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #0f172a, #111827);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            padding: 24px 16px 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .brand-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }

        .brand-row h2 {
            font-size: 18px;
            font-weight: 800;
        }

        .menu-toggle {
            font-size: 24px;
            color: rgba(255,255,255,0.9);
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: white;
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 8px;
            font-size: 16px;
            transition: 0.2s ease;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            background: rgba(255,255,255,0.08);
        }

        .sidebar-bottom {
            border-top: 1px solid rgba(255,255,255,0.08);
            padding-top: 16px;
        }

        .user-box {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #4361ee;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        .user-details .name {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .user-details .email {
            font-size: 13px;
            color: #cbd5e1;
        }

        .logout-btn {
            display: block;
            width: 100%;
            text-align: center;
            text-decoration: none;
            background: #dc2626;
            color: white;
            padding: 14px;
            border-radius: 14px;
            font-weight: 700;
        }

        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            padding: 28px 36px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #475569;
            font-size: 15px;
            margin-bottom: 28px;
        }

        .form-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .error-box {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-size: 14px;
            font-weight: 700;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        label {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 10px;
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 13px 16px;
            font-size: 15px;
            outline: none;
            background: #fff;
            color: #0f172a;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #4f6df5;
            box-shadow: 0 0 0 3px rgba(79,109,245,0.12);
        }

        textarea {
            min-height: 160px;
            resize: vertical;
        }

        .requirements-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .add-requirement-btn {
            background: none;
            border: none;
            color: #4361ee;
            font-size: 15px;
            cursor: pointer;
            font-weight: 600;
        }

        .requirements-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .requirement-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .requirement-row input {
            flex: 1;
        }

        .remove-btn {
            background: none;
            border: none;
            color: #ef4444;
            font-size: 28px;
            line-height: 1;
            cursor: pointer;
            padding: 0 4px;
        }

        .button-row {
            margin-top: 24px;
            display: flex;
            gap: 12px;
        }

        .btn {
            border: none;
            border-radius: 12px;
            padding: 13px 22px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: #4361ee;
            color: white;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #334155;
        }

        .btn-icon {
            font-size: 18px;
            line-height: 1;
        }

        @media (max-width: 900px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: auto;
            }
        }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div>
            <div class="brand-row">
                <h2>ATS System</h2>
                <div class="menu-toggle">☰</div>
            </div>

            <nav class="nav-menu">
                <a href="dashboard.php">⌘ Dashboard</a>
                <a href="jobs.php" class="active">▣ Job Postings</a>
                <a href="candidates.php">◔ Candidates</a>
                <a href="resume_upload.php">⇪ Resume Upload</a>
                <a href="ai_analysis.php">✦ AI Analysis</a>
                <a href="interviews.php">☷ Interviews</a>
            </nav>
        </div>

        <div class="sidebar-bottom">
            <div class="user-box">
                <div class="avatar"><?php echo strtoupper(substr($full_name, 0, 1)); ?></div>
                <div class="user-details">
                    <div class="name"><?php echo htmlspecialchars($full_name); ?></div>
                    <div class="email"><?php echo htmlspecialchars($email); ?></div>
                </div>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>Edit Job</h1>
            <p>Update job posting details</p>
        </div>

        <div class="form-card">
            <?php if (!empty($error)): ?>
                <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" id="editJobForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="job_title">Job Title *</label>
                        <input
                            type="text"
                            id="job_title"
                            name="job_title"
                            value="<?php echo htmlspecialchars($job_title); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="department">Department *</label>
                        <input
                            type="text"
                            id="department"
                            name="department"
                            value="<?php echo htmlspecialchars($department); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="job_type">Employment Type *</label>
                        <select id="job_type" name="job_type" required>
                            <option value="Full-time" <?php echo $job_type === 'Full-time' ? 'selected' : ''; ?>>Full-time</option>
                            <option value="Part-time" <?php echo $job_type === 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                            <option value="Internship" <?php echo $job_type === 'Internship' ? 'selected' : ''; ?>>Internship</option>
                            <option value="Contract" <?php echo $job_type === 'Contract' ? 'selected' : ''; ?>>Contract</option>
                            <option value="Remote" <?php echo $job_type === 'Remote' ? 'selected' : ''; ?>>Remote</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select id="status" name="status" required>
                            <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label for="salary_range">Salary Range *</label>
                        <input
                            type="text"
                            id="salary_range"
                            name="salary_range"
                            value="<?php echo htmlspecialchars($salary_range); ?>"
                            required
                        >
                    </div>

                    <div class="form-group full-width">
                        <label for="job_description">Job Description *</label>
                        <textarea
                            id="job_description"
                            name="job_description"
                            required
                        ><?php echo htmlspecialchars($job_description); ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <div class="requirements-header">
                            <label>Requirements</label>
                            <button type="button" class="add-requirement-btn" id="addRequirementBtn">+ Add Requirement</button>
                        </div>

                        <div class="requirements-list" id="requirementsList">
                            <?php foreach ($requirements as $index => $requirement): ?>
                                <div class="requirement-row">
                                    <input
                                        type="text"
                                        name="requirements[]"
                                        value="<?php echo htmlspecialchars($requirement); ?>"
                                        placeholder="e.g. 5+ years of React experience"
                                    >
                                    <?php if ($index > 0): ?>
                                        <button type="button" class="remove-btn" onclick="removeRequirement(this)">×</button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="button-row">
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-icon">💾</span>
                        <span>Update Job</span>
                    </button>

                    <a href="jobs.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
    const requirementsList = document.getElementById('requirementsList');
    const addRequirementBtn = document.getElementById('addRequirementBtn');

    if (addRequirementBtn && requirementsList) {
        addRequirementBtn.addEventListener('click', function () {
            const row = document.createElement('div');
            row.className = 'requirement-row';

            row.innerHTML = `
                <input type="text" name="requirements[]" placeholder="e.g. 5+ years of React experience">
                <button type="button" class="remove-btn" onclick="removeRequirement(this)">×</button>
            `;

            requirementsList.appendChild(row);
        });
    }

    function removeRequirement(button) {
        const row = button.closest('.requirement-row');
        if (row) {
            row.remove();
        }

        const rows = requirementsList.querySelectorAll('.requirement-row');
        if (rows.length === 0) {
            const defaultRow = document.createElement('div');
            defaultRow.className = 'requirement-row';
            defaultRow.innerHTML = `
                <input type="text" name="requirements[]" placeholder="e.g. 5+ years of React experience">
            `;
            requirementsList.appendChild(defaultRow);
        }
    }
</script>

<?php if ($job_updated): ?>
    <div class="modal-overlay" id="successModal">
        <div class="success-modal-box">
            <div class="success-icon-wrap">
                <div class="success-icon-circle">
                    <svg viewBox="0 0 24 24">
                        <path d="M20 6L9 17l-5-5"></path>
                    </svg>
                </div>
            </div>

            <h3>Job Updated Successfully!</h3>
            <p>
                <strong><?php echo htmlspecialchars($updated_job_title); ?></strong>
                has been updated. What would you like to do next?
            </p>

            <div class="success-modal-buttons">
                <a href="edit_job.php?id=<?php echo $job_id; ?>" class="success-btn primary">
                    <span>✎</span>
                    <span>Continue Editing</span>
                </a>

                <a href="jobs.php?success=1" class="success-btn secondary">
                    <span>◔</span>
                    <span>Return to Job Manager</span>
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>
</body>
</html>