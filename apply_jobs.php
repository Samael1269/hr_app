<?php
include 'config.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "candidate") {
    header("Location: dashboard.php");
    exit();
}

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Invalid job ID.");
}

$job_id = (int)$_GET["id"];
$user_id = $_SESSION["user_id"];
$full_name_session = $_SESSION["full_name"] ?? "Candidate";
$email_session = $_SESSION["email"] ?? "";

$error = "";
$application_submitted = false;
$submitted_job_title = "";

$stmt = $conn->prepare("SELECT * FROM jobs WHERE job_id = ? AND status = 'active'");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Job not found or not available.");
}

$job = $result->fetch_assoc();
$stmt->close();

$job_title = $job["job_title"] ?? "Untitled Job";
$department = $job["department"] ?? "General";
$location = $job["location"] ?? "Location not specified";
$job_type = $job["job_type"] ?? "Full-time";
$job_description = $job["job_description"] ?? "";
$requirements_text = $job["required_skills"] ?? "";

$requirements = !empty($requirements_text)
    ? array_filter(array_map("trim", explode("\n", $requirements_text)))
    : [];

$full_name = $full_name_session;
$email = $email_session;
$phone_number = "";
$years_experience = "";
$education = "";
$skills = "";
$cover_letter = "";
$why_join_us = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone_number = trim($_POST["phone_number"] ?? "");
    $years_experience = trim($_POST["years_experience"] ?? "");
    $education = trim($_POST["education"] ?? "");
    $skills = trim($_POST["skills"] ?? "");
    $cover_letter = trim($_POST["cover_letter"] ?? "");
    $why_join_us = trim($_POST["why_join_us"] ?? "");

    if (
        empty($full_name) ||
        empty($email) ||
        empty($phone_number) ||
        empty($years_experience) ||
        empty($education) ||
        empty($skills) ||
        empty($cover_letter) ||
        empty($why_join_us)
    ) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!isset($_FILES["resume_cv"]) || $_FILES["resume_cv"]["error"] !== UPLOAD_ERR_OK) {
        $error = "Please upload your resume/CV.";
    } else {
        $allowed_extensions = ['pdf', 'doc', 'docx'];
        $file_name = $_FILES["resume_cv"]["name"];
        $file_tmp = $_FILES["resume_cv"]["tmp_name"];
        $file_size = $_FILES["resume_cv"]["size"];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_extensions)) {
            $error = "Only PDF, DOC, and DOCX files are allowed.";
        } elseif ($file_size > 10 * 1024 * 1024) {
            $error = "File size must not exceed 10MB.";
        } else {
            $upload_dir = "uploads/resumes/";

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_file_name = time() . "_" . preg_replace('/[^A-Za-z0-9_\.-]/', '_', $file_name);
            $file_path = $upload_dir . $new_file_name;

            if (!move_uploaded_file($file_tmp, $file_path)) {
                $error = "Failed to upload resume file.";
            } else {
                $conn->begin_transaction();

                try {
                    $candidate_stmt = $conn->prepare("SELECT candidate_id FROM candidates WHERE email = ?");
                    $candidate_stmt->bind_param("s", $email);
                    $candidate_stmt->execute();
                    $candidate_result = $candidate_stmt->get_result();

                    if ($candidate_result->num_rows > 0) {
                        $candidate = $candidate_result->fetch_assoc();
                        $candidate_id = $candidate["candidate_id"];

                        $update_candidate_stmt = $conn->prepare("
                            UPDATE candidates
                            SET full_name = ?, phone_number = ?, highest_education = ?, years_experience = ?
                            WHERE candidate_id = ?
                        ");
                        $update_candidate_stmt->bind_param(
                            "ssssi",
                            $full_name,
                            $phone_number,
                            $education,
                            $years_experience,
                            $candidate_id
                        );
                        $update_candidate_stmt->execute();
                        $update_candidate_stmt->close();
                    } else {
                        $insert_candidate_stmt = $conn->prepare("
                            INSERT INTO candidates (full_name, email, phone_number, highest_education, years_experience)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $insert_candidate_stmt->bind_param(
                            "sssss",
                            $full_name,
                            $email,
                            $phone_number,
                            $education,
                            $years_experience
                        );
                        $insert_candidate_stmt->execute();
                        $candidate_id = $insert_candidate_stmt->insert_id;
                        $insert_candidate_stmt->close();
                    }

                    $candidate_stmt->close();

                    $duplicate_stmt = $conn->prepare("
                        SELECT application_id FROM applications
                        WHERE candidate_id = ? AND job_id = ?
                    ");
                    $duplicate_stmt->bind_param("ii", $candidate_id, $job_id);
                    $duplicate_stmt->execute();
                    $duplicate_result = $duplicate_stmt->get_result();

                    if ($duplicate_result->num_rows > 0) {
                        throw new Exception("You have already applied for this job.");
                    }

                    $duplicate_stmt->close();

                    $application_stmt = $conn->prepare("
                        INSERT INTO applications (candidate_id, job_id, application_status)
                        VALUES (?, ?, 'new')
                    ");
                    $application_stmt->bind_param("ii", $candidate_id, $job_id);
                    $application_stmt->execute();
                    $application_stmt->close();

                    $resume_stmt = $conn->prepare("
                        INSERT INTO resume_files (candidate_id, file_name, file_path, file_type)
                        VALUES (?, ?, ?, ?)
                    ");
                    $mime_type = $_FILES["resume_cv"]["type"] ?? $file_ext;
                    $resume_stmt->bind_param("isss", $candidate_id, $file_name, $file_path, $mime_type);
                    $resume_stmt->execute();
                    $resume_stmt->close();

                    $conn->commit();

                    $application_submitted = true;
                    $submitted_job_title = $job_title;

                    $full_name = $full_name_session;
                    $email = $email_session;
                    $phone_number = "";
                    $years_experience = "";
                    $education = "";
                    $skills = "";
                    $cover_letter = "";
                    $why_join_us = "";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
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
    <title>Apply Job | ATS System</title>

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
            width: 100%;
        }

        /* SIDEBAR */
        .sidebar {
            width: 255px;
            min-width: 255px;
            background: #0a1430;
            color: #fff;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-right: 1px solid rgba(255,255,255,0.06);
        }

        .sidebar-top {
            padding: 26px 14px 0;
        }

        .brand-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .brand-row h2 {
            font-size: 20px;
            font-weight: 800;
            color: #fff;
        }

        .menu-toggle {
            font-size: 24px;
            color: #fff;
            line-height: 1;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fff;
            text-decoration: none;
            padding: 14px 12px;
            border-radius: 10px;
            font-size: 16px;
            margin-bottom: 6px;
            transition: background 0.2s ease;
        }

        .nav-menu a:hover {
            background: rgba(255,255,255,0.04);
        }

        /* match reference: no obvious blue active pill */
        .nav-menu a.active {
            background: transparent;
            color: #fff;
        }

        .nav-icon {
            width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #e5e7eb;
        }

        .sidebar-bottom {
            padding: 16px 14px 16px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }

        .user-box {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #4361ee;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .user-details .name {
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 2px;
        }

        .user-details .role {
            font-size: 13px;
            color: #cbd5e1;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            background: #dc2626;
            color: #fff;
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 255px;
            width: calc(100% - 255px);
            min-height: 100vh;
            display: flex;
            justify-content: center;   /* this is the important part */
            padding: 30px 20px 40px;
        }

        .content-wrap {
            width: 100%;
            max-width: 780px;          /* fixed centered content like the reference */
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 10px;
            color: #0f172a;
        }

        .page-header .meta {
            font-size: 15px;
            color: #475569;
            margin-bottom: 24px;
        }

        .info-card {
            background: #eef4ff;
            border: 1px solid #bfd1ff;
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 24px;
        }

        .info-card h3 {
            font-size: 18px;
            font-weight: 800;
            color: #25408f;
            margin-bottom: 14px;
        }

        .info-card p {
            color: #2f55b5;
            line-height: 1.6;
            font-size: 15px;
            margin-bottom: 14px;
        }

        .info-card strong {
            display: block;
            margin-bottom: 8px;
            color: #25408f;
            font-size: 15px;
        }

        .info-card ul {
            margin-left: 18px;
            color: #2f55b5;
            line-height: 1.7;
            font-size: 15px;
        }

        .form-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
        }

        .form-card h2 {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 24px;
        }

        .error-box {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 10px;
        }

        input,
        textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 13px 15px;
            font-size: 15px;
            background: #fff;
            color: #0f172a;
            outline: none;
        }

        input::placeholder,
        textarea::placeholder {
            color: #94a3b8;
        }

        input:focus,
        textarea:focus {
            border-color: #4f6df5;
            box-shadow: 0 0 0 3px rgba(79,109,245,0.10);
        }

        textarea {
            min-height: 160px;
            resize: vertical;
        }

        .upload-box {
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 34px 20px;
            text-align: center;
            background: #fff;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .upload-box:hover,
        .upload-box.dragover {
            border-color: #a5b4fc;
            background: #fafcff;
        }

        .upload-box .icon {
            font-size: 36px;
            color: #94a3b8;
            margin-bottom: 10px;
        }

        .upload-box .main-text {
            color: #4361ee;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .upload-box .sub-text {
            color: #64748b;
            font-size: 14px;
        }

        .hidden-file {
            display: none;
        }

        .file-name {
            margin-top: 10px;
            color: #334155;
            font-size: 14px;
            font-weight: 600;
        }

        .button-row {
            margin-top: 22px;
            display: flex;
            gap: 12px;
        }

        .btn {
            border: none;
            border-radius: 12px;
            padding: 13px 22px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
        }

        .btn-primary {
            background: #4f63f6;
            color: #fff;
            min-width: 215px;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #334155;
            min-width: 96px;
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
            max-width: 460px;
            background: #fff;
            border-radius: 20px;
            padding: 28px 24px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.25);
        }

        .success-icon-wrap {
            margin-bottom: 18px;
        }

        .success-icon-circle {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        .success-icon-circle svg {
            width: 32px;
            height: 32px;
            fill: none;
            stroke: #16a34a;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .success-modal-box h3 {
            font-size: 24px;
            color: #0f172a;
            margin-bottom: 12px;
            font-weight: 800;
        }

        .success-modal-box p {
            color: #475569;
            line-height: 1.6;
            font-size: 15px;
            margin-bottom: 22px;
        }

        .success-modal-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .success-btn {
            text-decoration: none;
            padding: 13px 16px;
            border-radius: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 15px;
        }

        .success-btn.primary {
            background: #4361ee;
            color: #fff;
        }

        .success-btn.secondary {
            background: #f1f5f9;
            color: #334155;
        }

        @media (max-width: 900px) {
            .sidebar {
                display: none;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px 16px 30px;
            }

            .content-wrap {
                max-width: 100%;
            }
        }

        @media (max-width: 640px) {
            .button-row {
                flex-direction: column;
            }

            .btn-primary,
            .btn-secondary {
                width: 100%;
                min-width: auto;
            }
        }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="brand-row">
                <h2>ATS System</h2>
                <div class="menu-toggle">☰</div>
            </div>

            <nav class="nav-menu">
                <a href="candidate_dashboard.php" class="active">
                    <span class="nav-icon">👜</span>
                    <span>Job Postings</span>
                </a>
                <a href="my_interviews.php">
                    <span class="nav-icon">🗓</span>
                    <span>My Interviews</span>
                </a>
            </nav>
        </div>

        <div class="sidebar-bottom">
            <div class="user-box">
                <div class="avatar"><?php echo strtoupper(substr($full_name_session, 0, 1)); ?></div>
                <div class="user-details">
                    <div class="name"><?php echo htmlspecialchars($full_name_session); ?></div>
                    <div class="role">Candidate</div>
                </div>
            </div>

            <a href="logout.php" class="logout-btn">⇱ Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="content-wrap">
            <div class="page-header">
                <h1>Apply for <?php echo htmlspecialchars($job_title); ?></h1>
                <div class="meta">
                    <?php echo htmlspecialchars($department); ?> •
                    <?php echo htmlspecialchars($location); ?> •
                    <?php echo htmlspecialchars($job_type); ?>
                </div>
            </div>

            <div class="info-card">
                <h3>About this position</h3>
                <p><?php echo htmlspecialchars($job_description); ?></p>

                <?php if (!empty($requirements)): ?>
                    <strong>Requirements:</strong>
                    <ul>
                        <?php foreach ($requirements as $requirement): ?>
                            <li><?php echo htmlspecialchars($requirement); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="form-card">
                <h2>Your Information</h2>

                <?php if (!empty($error)): ?>
                    <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" placeholder="John Doe" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="john@example.com" required>
                    </div>

                    <div class="form-group">
                        <label for="phone_number">Phone Number *</label>
                        <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>" placeholder="(555) 123-4567" required>
                    </div>

                    <div class="form-group">
                        <label for="years_experience">Years of Experience *</label>
                        <input type="text" id="years_experience" name="years_experience" value="<?php echo htmlspecialchars($years_experience); ?>" placeholder="e.g. 5 years" required>
                    </div>

                    <div class="form-group">
                        <label for="education">Education *</label>
                        <input type="text" id="education" name="education" value="<?php echo htmlspecialchars($education); ?>" placeholder="e.g. BS Computer Science, MIT" required>
                    </div>

                    <div class="form-group">
                        <label for="skills">Skills (comma-separated) *</label>
                        <input type="text" id="skills" name="skills" value="<?php echo htmlspecialchars($skills); ?>" placeholder="React, TypeScript, Node.js" required>
                    </div>

                    <div class="form-group">
                        <label for="resume_cv">Resume/CV *</label>
                        <label for="resume_cv" class="upload-box" id="uploadBox">
                            <div class="icon">📎</div>
                            <div class="main-text">Click to upload or drag and drop</div>
                            <div class="sub-text">PDF, DOC, DOCX up to 10MB</div>
                            <div class="file-name" id="fileName"></div>
                        </label>
                        <input type="file" id="resume_cv" name="resume_cv" class="hidden-file" accept=".pdf,.doc,.docx" required>
                    </div>

                    <div class="form-group">
                        <label for="cover_letter">Cover Letter *</label>
                        <textarea id="cover_letter" name="cover_letter" placeholder="Tell us why you're interested in this position and what makes you a great fit..." required><?php echo htmlspecialchars($cover_letter); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="why_join_us">Why Join Us? *</label>
                        <textarea id="why_join_us" name="why_join_us" placeholder="Tell us why you're interested in joining our team..." required><?php echo htmlspecialchars($why_join_us); ?></textarea>
                    </div>

                    <div class="button-row">
                        <button type="submit" class="btn btn-primary">✈ Submit Application</button>
                        <a href="candidate_dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<?php if ($application_submitted): ?>
    <div class="modal-overlay" id="successModal">
        <div class="success-modal-box">
            <div class="success-icon-wrap">
                <div class="success-icon-circle">
                    <svg viewBox="0 0 24 24">
                        <path d="M20 6L9 17l-5-5"></path>
                    </svg>
                </div>
            </div>

            <h3>Application Submitted!</h3>
            <p>
                Your application for <strong><?php echo htmlspecialchars($submitted_job_title); ?></strong> has been submitted successfully.
            </p>

            <div class="success-modal-buttons">
                <a href="candidate_dashboard.php" class="success-btn primary">
                    <span>◔</span>
                    <span>Back to Job Postings</span>
                </a>

                <a href="my_interviews.php" class="success-btn secondary">
                    <span>☷</span>
                    <span>Go to My Interviews</span>
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    const fileInput = document.getElementById('resume_cv');
    const fileName = document.getElementById('fileName');
    const uploadBox = document.getElementById('uploadBox');

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (this.files.length > 0) {
                fileName.textContent = this.files[0].name;
            } else {
                fileName.textContent = '';
            }
        });
    }

    if (uploadBox && fileInput) {
        uploadBox.addEventListener('dragover', function (e) {
            e.preventDefault();
            uploadBox.classList.add('dragover');
        });

        uploadBox.addEventListener('dragleave', function () {
            uploadBox.classList.remove('dragover');
        });

        uploadBox.addEventListener('drop', function (e) {
            e.preventDefault();
            uploadBox.classList.remove('dragover');

            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                fileName.textContent = e.dataTransfer.files[0].name;
            }
        });
    }
</script>
</body>
</html>