<?php
include 'config.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$full_name = $_SESSION["full_name"] ?? "Admin";
$email = $_SESSION["email"] ?? "admin@example.com";
$created_by = $_SESSION["user_id"];

$error = "";
$job_posted = false;
$posted_job_title = "";

$job_title = "";
$department = "";
$job_type = "Full-time";
$status = "draft";
$salary_range = "";
$job_description = "";
$required_education = "";
$required_experience = "";
$requirements = [""];

function parseSalaryRange($salaryRange)
{
    $salaryRange = trim($salaryRange);

    if ($salaryRange === "") {
        return [null, null];
    }

    $normalized = str_replace([",", "–", "—"], ["", "-", "-"], $salaryRange);

    preg_match_all('/\d+/', $normalized, $matches);
    $numbers = $matches[0] ?? [];

    if (count($numbers) >= 2) {
        $min = (int)$numbers[0];
        $max = (int)$numbers[1];

        if (stripos($normalized, 'k') !== false) {
            $min *= 1000;
            $max *= 1000;
        }

        return [$min, $max];
    }

    if (count($numbers) === 1) {
        $min = (int)$numbers[0];

        if (stripos($normalized, 'k') !== false) {
            $min *= 1000;
        }

        return [$min, null];
    }

    return [null, null];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $job_title = trim($_POST["job_title"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $job_type = trim($_POST["job_type"] ?? "Full-time");
    $status = trim($_POST["status"] ?? "draft");
    $salary_range = trim($_POST["salary_range"] ?? "");
    $job_description = trim($_POST["job_description"] ?? "");
    $required_education = trim($_POST["required_education"] ?? "");
    $required_experience = trim($_POST["required_experience"] ?? "");

    $requirements = $_POST["requirements"] ?? [];
    if (!is_array($requirements)) {
        $requirements = [];
    }

    $requirements = array_map('trim', $requirements);
    $requirements = array_values(array_filter($requirements, function ($item) {
        return $item !== "";
    }));

    if (empty($requirements)) {
        $requirements = [""];
    }

    if (
        $job_title === "" ||
        $department === "" ||
        $job_type === "" ||
        $status === "" ||
        $salary_range === "" ||
        $job_description === "" ||
        $required_education === "" ||
        $required_experience === "" ||
        empty(array_filter($requirements))
    ) {
        $error = "Please fill in all required fields.";
    } else {
        [$salary_min, $salary_max] = parseSalaryRange($salary_range);

        $required_skills = implode("\n", $requirements);

        $stmt = $conn->prepare("
            INSERT INTO jobs (
                job_title,
                department,
                job_type,
                job_description,
                required_skills,
                required_education,
                required_experience,
                salary_min,
                salary_max,
                status,
                created_by,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "sssssssissi",
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
            $created_by
        );

        if ($stmt->execute()) {
            $job_posted = true;
            $posted_job_title = $job_title;

            $job_title = "";
            $department = "";
            $job_type = "Full-time";
            $status = "draft";
            $salary_range = "";
            $job_description = "";
            $required_education = "";
            $required_experience = "";
            $requirements = [""];
        } else {
            $error = "Failed to save job posting.";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post New Job | ATS System</title>
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

        .nav-menu a.active {
            background: linear-gradient(90deg, #4361ee, #4f6df5);
        }

        .nav-menu a:hover {
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
            font-weight: 700;
            text-transform: uppercase;
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

        .content-wrap {
            max-width: 1240px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #475569;
            font-size: 15px;
        }

        .form-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
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
            margin-bottom: 22px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
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

        input::placeholder,
        textarea::placeholder {
            color: #94a3b8;
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

        .desc-box {
            min-height: 160px;
        }

        .requirements-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .add-link {
            color: #4361ee;
            font-size: 14px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }

        .requirements-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .requirement-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .requirement-row input {
            flex: 1;
        }

        .remove-requirement {
            border: none;
            background: transparent;
            color: #dc2626;
            font-size: 28px;
            line-height: 1;
            cursor: pointer;
            padding: 0 4px;
        }

        .button-row {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }

        .btn {
            border: none;
            border-radius: 12px;
            padding: 12px 22px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999;
            padding: 20px;
        }

        .success-modal-box {
            width: 100%;
            max-width: 510px;
            background: #ffffff;
            border-radius: 18px;
            padding: 26px 24px 24px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.25);
        }

        .success-icon-wrap {
            margin-bottom: 18px;
        }

        .success-icon-circle {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #dcfce7;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        .success-icon-circle svg {
            width: 30px;
            height: 30px;
            fill: none;
            stroke: #3fa34d;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .success-modal-box h3 {
            font-size: 18px;
            color: #0f172a;
            margin-bottom: 10px;
            font-weight: 800;
        }

        .success-modal-box p {
            color: #64748b;
            line-height: 1.6;
            font-size: 15px;
            margin-bottom: 18px;
        }

        .success-modal-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .success-btn {
            text-decoration: none;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 15px;
        }

        .success-btn.primary {
            background: linear-gradient(90deg, #4361ee, #4f6df5);
            color: #ffffff;
        }

        .success-btn.secondary {
            background: #f1f5f9;
            color: #334155;
        }

        @media (max-width: 1000px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 22px 20px;
            }
        }

        @media (max-width: 900px) {
            .sidebar {
                display: none;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .button-row {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .requirement-row {
                gap: 8px;
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
        <div class="content-wrap">
            <div class="page-header">
                <h1>Post New Job</h1>
                <p>Create a new job posting</p>
            </div>

            <div class="form-card">
                <?php if (!empty($error)): ?>
                    <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" id="jobForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="job_title">Job Title *</label>
                            <input
                                type="text"
                                id="job_title"
                                name="job_title"
                                placeholder="e.g. Senior Frontend Developer"
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
                                placeholder="e.g. Engineering"
                                value="<?php echo htmlspecialchars($department); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="job_type">Employment Type *</label>
                            <select id="job_type" name="job_type" required>
                                <option value="Full-time" <?php echo $job_type === 'Full-time' ? 'selected' : ''; ?>>Full-time</option>
                                <option value="Part-time" <?php echo $job_type === 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                                <option value="Contract" <?php echo $job_type === 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                <option value="Internship" <?php echo $job_type === 'Internship' ? 'selected' : ''; ?>>Internship</option>
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

                        <div class="form-group">
                            <label for="salary_range">Salary Range *</label>
                            <input
                                type="text"
                                id="salary_range"
                                name="salary_range"
                                placeholder="e.g. $120k - $160k"
                                value="<?php echo htmlspecialchars($salary_range); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="required_experience">Required Experience *</label>
                            <input
                                type="text"
                                id="required_experience"
                                name="required_experience"
                                placeholder="e.g. 5+ years"
                                value="<?php echo htmlspecialchars($required_experience); ?>"
                                required
                            >
                        </div>

                        <div class="form-group full-width">
                            <label for="job_description">Job Description *</label>
                            <textarea
                                id="job_description"
                                name="job_description"
                                class="desc-box"
                                placeholder="Describe the role, responsibilities, and what makes this position unique..."
                                required
                            ><?php echo htmlspecialchars($job_description); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="required_education">Required Education *</label>
                            <input
                                type="text"
                                id="required_education"
                                name="required_education"
                                placeholder="e.g. Bachelor's Degree in Computer Science"
                                value="<?php echo htmlspecialchars($required_education); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Requirements *</label>
                            <div class="requirements-top">
                                <span></span>
                                <a href="#" class="add-link" id="addRequirementBtn">＋ Add Requirement</a>
                            </div>

                            <div class="requirements-list" id="requirementsList">
                                <?php foreach ($requirements as $index => $requirement): ?>
                                    <div class="requirement-row">
                                        <input
                                            type="text"
                                            name="requirements[]"
                                            placeholder="e.g. 5+ years of React experience"
                                            value="<?php echo htmlspecialchars($requirement); ?>"
                                            required
                                        >
                                        <button
                                            type="button"
                                            class="remove-requirement"
                                            <?php echo count($requirements) === 1 ? 'style="display:none;"' : ''; ?>
                                        >×</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="button-row">
                        <button type="submit" class="btn btn-primary">💾 Post Job</button>
                        <a href="jobs.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<?php if ($job_posted): ?>
    <div class="modal-overlay" id="successModal">
        <div class="success-modal-box">
            <div class="success-icon-wrap">
                <div class="success-icon-circle">
                    <svg viewBox="0 0 24 24">
                        <path d="M20 6L9 17l-5-5"></path>
                    </svg>
                </div>
            </div>

            <h3>Job Posted Successfully!</h3>
            <p>
                <strong><?php echo htmlspecialchars($posted_job_title); ?></strong> has been posted to your job board. What would you like to do next?
            </p>

            <div class="success-modal-buttons">
                <a href="add_jobs.php" class="success-btn primary">
                    <span>＋</span>
                    <span>Post Another Job</span>
                </a>

                <a href="jobs.php" class="success-btn secondary">
                    <span>◔</span>
                    <span>View All Job Postings</span>
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    const requirementsList = document.getElementById('requirementsList');
    const addRequirementBtn = document.getElementById('addRequirementBtn');

    function updateRemoveButtons() {
        const rows = requirementsList.querySelectorAll('.requirement-row');
        rows.forEach((row, index) => {
            const btn = row.querySelector('.remove-requirement');
            if (!btn) return;
            btn.style.display = rows.length === 1 ? 'none' : 'inline-block';
        });
    }

    function createRequirementRow(value = '') {
        const row = document.createElement('div');
        row.className = 'requirement-row';

        row.innerHTML = `
            <input
                type="text"
                name="requirements[]"
                placeholder="e.g. 5+ years of React experience"
                value="${value.replace(/"/g, '&quot;')}"
                required
            >
            <button type="button" class="remove-requirement">×</button>
        `;

        const removeBtn = row.querySelector('.remove-requirement');
        removeBtn.addEventListener('click', function () {
            row.remove();
            updateRemoveButtons();
        });

        return row;
    }

    addRequirementBtn.addEventListener('click', function (e) {
        e.preventDefault();
        requirementsList.appendChild(createRequirementRow());
        updateRemoveButtons();
    });

    document.querySelectorAll('.remove-requirement').forEach(btn => {
        btn.addEventListener('click', function () {
            this.closest('.requirement-row').remove();
            updateRemoveButtons();
        });
    });

    updateRemoveButtons();
</script>
</body>
</html>