<?php
include 'config.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$full_name = $_SESSION["full_name"] ?? "User";
$email = $_SESSION["email"] ?? "user@example.com";

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$success = $_GET['success'] ?? '';

$sql = "
    SELECT j.*,
           (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) AS application_count
    FROM jobs j
    WHERE 1=1
";

$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (j.job_title LIKE ? OR j.department LIKE ?)";
    $searchTerm = "%" . $search . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

if (!empty($status)) {
    $sql .= " AND j.status = ?";
    $params[] = $status;
    $types .= "s";
}

$sql .= " ORDER BY j.created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

function formatSalary($min, $max) {
    if (!empty($min) && !empty($max)) {
        return "$" . number_format($min / 1000, 0) . "k - $" . number_format($max / 1000, 0) . "k";
    } elseif (!empty($min)) {
        return "$" . number_format($min / 1000, 0) . "k+";
    }
    return "Not specified";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Postings | ATS System</title>
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
            padding: 26px 36px;
        }

        .page-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 26px;
        }

        .page-top h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .page-top p {
            color: #475569;
            font-size: 15px;
        }

        .post-btn {
            background: #4361ee;
            color: white;
            text-decoration: none;
            padding: 14px 18px;
            border-radius: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .post-btn .plus {
            font-size: 20px;
            line-height: 1;
        }

        .filter-box {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 16px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1.2fr 1.2fr;
            gap: 16px;
        }

        .input-icon,
        .select-icon {
            position: relative;
        }

        .input-icon svg,
        .select-icon svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            stroke: #9ca3af;
            stroke-width: 2;
            fill: none;
            pointer-events: none;
        }

        .input-icon input,
        .select-icon select {
            width: 100%;
            height: 46px;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            padding: 0 14px 0 42px;
            font-size: 15px;
            outline: none;
            background: #fff;
        }

        .input-icon input:focus,
        .select-icon select:focus {
            border-color: #4f6df5;
            box-shadow: 0 0 0 3px rgba(79,109,245,0.12);
        }

        .message {
            padding: 13px 16px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-size: 14px;
            font-weight: 700;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .job-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .job-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 24px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .job-left {
            display: flex;
            gap: 16px;
            flex: 1;
        }

        .job-icon-box {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: #dbeafe;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .job-icon-box svg {
            width: 22px;
            height: 22px;
            stroke: #4361ee;
            stroke-width: 2;
            fill: none;
        }

        .job-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 10px;
            color: #0f172a;
        }

        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            color: #475569;
            font-size: 14px;
            margin-bottom: 14px;
        }

        .job-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .job-meta svg {
            width: 16px;
            height: 16px;
            stroke: #64748b;
            stroke-width: 2;
            fill: none;
        }

        .job-desc {
            color: #334155;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 14px;
        }

        .job-footer {
            display: flex;
            gap: 14px;
            align-items: center;
            flex-wrap: wrap;
        }

        .salary {
            font-weight: 800;
            color: #0f172a;
        }

        .applications {
            color: #64748b;
        }

        .job-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
            gap: 18px;
            min-width: 180px;
        }

        .status-badge {
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-draft {
            background: #fef3c7;
            color: #92400e;
        }

        .status-closed {
            background: #fee2e2;
            color: #b91c1c;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .edit-btn {
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            background: #4361ee;
            color: white;
        }

        .empty-box {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            color: #64748b;
            border: 1px solid #e5e7eb;
        }

        @media (max-width: 1000px) {
            .filter-form {
                grid-template-columns: 1fr;
            }

            .job-card {
                flex-direction: column;
            }

            .job-right {
                align-items: flex-start;
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
        <div class="page-top">
            <div>
                <h1>Job Postings</h1>
                <p>Manage your job openings</p>
            </div>
            <a href="add_jobs.php" class="post-btn">
                <span class="plus">＋</span>
                <span>Post New Job</span>
            </a>
        </div>

        <?php if ($success === '1'): ?>
            <div class="message success">Job added successfully.</div>
        <?php endif; ?>

        <div class="filter-box">
            <form method="GET" class="filter-form">
                <div class="input-icon">
                    <svg viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="M20 20l-3.5-3.5"></path>
                    </svg>
                    <input
                        type="text"
                        name="search"
                        placeholder="Search jobs by title or department..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </div>

                <div class="select-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 5h18l-7 8v5l-4 2v-7L3 5z"></path>
                    </svg>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
            </form>
        </div>

        <div class="job-list">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($job = $result->fetch_assoc()): ?>
                    <div class="job-card">
                        <div class="job-left">
                            <div class="job-icon-box">
                                <svg viewBox="0 0 24 24">
                                    <rect x="3" y="7" width="18" height="13" rx="2"></rect>
                                    <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </div>

                            <div>
                                <div class="job-title"><?php echo htmlspecialchars($job['job_title']); ?></div>

                                <div class="job-meta">
                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <rect x="3" y="7" width="18" height="13" rx="2"></rect>
                                            <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                        <?php echo htmlspecialchars($job['department']); ?>
                                    </span>

                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="9"></circle>
                                            <path d="M12 7v5l3 3"></path>
                                        </svg>
                                        <?php echo htmlspecialchars($job['job_type'] ?: 'Not specified'); ?>
                                    </span>
                                </div>

                                <div class="job-desc">
                                    <?php echo htmlspecialchars(mb_strimwidth($job['job_description'], 0, 95, '...')); ?>
                                </div>

                                <div class="job-footer">
                                    <span class="salary"><?php echo formatSalary($job['salary_min'], $job['salary_max']); ?></span>
                                    <span class="applications"><?php echo (int)$job['application_count']; ?> applications</span>
                                </div>
                            </div>
                        </div>

                        <div class="job-right">
                            <div class="status-badge status-<?php echo htmlspecialchars($job['status']); ?>">
                                <?php echo ucfirst(htmlspecialchars($job['status'])); ?>
                            </div>

                            <div class="action-buttons">
                                <a href="edit_job.php?id=<?php echo $job['job_id']; ?>" class="edit-btn">Edit</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-box">No job postings found.</div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>