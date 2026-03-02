<?php
include 'config.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$full_name = $_SESSION["full_name"] ?? "Admin";
$email = $_SESSION["email"] ?? "admin@example.com";

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$job_id = trim($_GET['job_id'] ?? '');

function getAppliedDate(array $row): string
{
    $dateValue = $row['applied_at'] ?? $row['application_date'] ?? $row['created_at'] ?? '';
    if (!empty($dateValue) && strtotime($dateValue)) {
        return date('Y-m-d', strtotime($dateValue));
    }
    return '-';
}

function getYearsOnly($value): int
{
    if (preg_match('/\d+/', (string)$value, $m)) {
        return (int)$m[0];
    }
    return 0;
}

function calculateAiScore(array $row): int
{
    $score = 60;

    $years = getYearsOnly($row['years_experience'] ?? '');
    $score += min($years * 3, 18);

    if (!empty($row['highest_education'])) {
        $score += 8;
    }

    if (!empty($row['resume_path'])) {
        $score += 8;
    }

    $status = strtolower($row['application_status'] ?? 'new');
    if ($status === 'shortlisted') {
        $score += 8;
    } elseif ($status === 'interview') {
        $score += 6;
    } elseif ($status === 'reviewing') {
        $score += 4;
    }

    return max(60, min(99, $score));
}

/* stats */
$statsSql = "
    SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN LOWER(a.application_status) = 'new' THEN 1 ELSE 0 END) AS new_count,
        SUM(CASE WHEN LOWER(a.application_status) = 'shortlisted' THEN 1 ELSE 0 END) AS shortlisted_count,
        SUM(CASE WHEN LOWER(a.application_status) = 'interview' THEN 1 ELSE 0 END) AS interview_count
    FROM applications a
";
$statsResult = $conn->query($statsSql);
$stats = $statsResult ? $statsResult->fetch_assoc() : [
    'total_count' => 0,
    'new_count' => 0,
    'shortlisted_count' => 0,
    'interview_count' => 0
];

/* jobs for filter */
$jobsForFilter = [];
$jobFilterSql = "SELECT job_id, job_title FROM jobs ORDER BY job_title ASC";
$jobFilterResult = $conn->query($jobFilterSql);
if ($jobFilterResult) {
    while ($row = $jobFilterResult->fetch_assoc()) {
        $jobsForFilter[] = $row;
    }
}

/* candidate list */
$sql = "
    SELECT
        a.*,
        c.candidate_id,
        c.full_name,
        c.email,
        c.phone_number,
        c.highest_education,
        c.years_experience,
        j.job_title,
        j.required_skills,
        rf.file_path AS resume_path,
        rf.file_name AS resume_name
    FROM applications a
    INNER JOIN candidates c ON a.candidate_id = c.candidate_id
    INNER JOIN jobs j ON a.job_id = j.job_id
    LEFT JOIN (
        SELECT r1.*
        FROM resume_files r1
        INNER JOIN (
            SELECT candidate_id, MAX(resume_id) AS latest_resume_id
            FROM resume_files
            GROUP BY candidate_id
        ) latest ON latest.latest_resume_id = r1.resume_id
    ) rf ON rf.candidate_id = c.candidate_id
    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (
        c.full_name LIKE ?
        OR c.email LIKE ?
        OR j.job_title LIKE ?
    )";
    $searchTerm = "%" . $search . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

if ($status !== '') {
    $sql .= " AND LOWER(a.application_status) = LOWER(?)";
    $params[] = $status;
    $types .= "s";
}

if ($job_id !== '') {
    $sql .= " AND a.job_id = ?";
    $params[] = $job_id;
    $types .= "i";
}

$sql .= " ORDER BY a.application_id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

function badgeClass($status)
{
    $status = strtolower(trim($status));
    return match ($status) {
        'new' => 'badge-new',
        'reviewing' => 'badge-reviewing',
        'shortlisted' => 'badge-shortlisted',
        'interview' => 'badge-interview',
        'offered' => 'badge-offered',
        'rejected' => 'badge-rejected',
        default => 'badge-default'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidates | ATS System</title>
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
            padding: 26px 34px;
        }

        .content-wrap {
            max-width: 1240px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 22px;
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

        .filter-box {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 16px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1.6fr 1fr 1fr;
            gap: 16px;
        }

        .field-icon {
            position: relative;
        }

        .field-icon span {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 18px;
            pointer-events: none;
        }

        .field-icon input,
        .field-icon select {
            width: 100%;
            height: 44px;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            padding: 0 14px 0 40px;
            font-size: 15px;
            background: #fff;
            outline: none;
        }

        .field-icon input:focus,
        .field-icon select:focus {
            border-color: #4f6df5;
            box-shadow: 0 0 0 3px rgba(79,109,245,0.12);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 18px 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .stat-card .label {
            color: #475569;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 18px;
            font-weight: 800;
        }

        .value.blue { color: #4361ee; }
        .value.green { color: #2f9e44; }
        .value.purple { color: #7c3aed; }

        .table-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .table-head,
        .candidate-row {
            display: grid;
            grid-template-columns: 2.1fr 1.9fr 2fr 1.2fr 1.2fr 0.9fr 0.9fr;
            gap: 18px;
            align-items: center;
            padding: 16px 24px;
        }

        .table-head {
            background: #ffffff;
            font-size: 12px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #64748b;
            border-bottom: 1px solid #e5e7eb;
        }

        .candidate-row {
            border-bottom: 1px solid #e5e7eb;
        }

        .candidate-row:last-child {
            border-bottom: none;
        }

        .candidate-main {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .candidate-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #dbeafe;
            color: #4361ee;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .candidate-name {
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .candidate-sub {
            color: #475569;
            font-size: 14px;
        }

        .contact-block div {
            font-size: 14px;
            margin-bottom: 6px;
            color: #334155;
        }

        .job-title {
            font-weight: 700;
            color: #0f172a;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge-new {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge-reviewing {
            background: #fef3c7;
            color: #b45309;
        }

        .badge-shortlisted {
            background: #dcfce7;
            color: #2f9e44;
        }

        .badge-interview {
            background: #ede9fe;
            color: #7c3aed;
        }

        .badge-offered {
            background: #dcfce7;
            color: #15803d;
        }

        .badge-rejected {
            background: #fee2e2;
            color: #dc2626;
        }

        .badge-default {
            background: #e5e7eb;
            color: #475569;
        }

        .score {
            font-weight: 800;
            color: #0f172a;
        }

        .score small {
            color: #64748b;
            font-weight: 500;
        }

        .details-link {
            color: #4361ee;
            font-weight: 700;
            text-decoration: none;
        }

        .empty-box {
            padding: 40px;
            text-align: center;
            color: #64748b;
        }

        @media (max-width: 1200px) {
            .table-head,
            .candidate-row {
                grid-template-columns: 2fr 1.6fr 1.7fr 1.1fr 1fr 0.8fr 0.9fr;
            }
        }

        @media (max-width: 1000px) {
            .filter-form,
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-card {
                overflow-x: auto;
            }

            .table-head,
            .candidate-row {
                min-width: 1150px;
            }
        }

        @media (max-width: 900px) {
            .sidebar {
                display: none;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
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
                <a href="jobs.php">▣ Job Postings</a>
                <a href="candidates.php" class="active">◔ Candidates</a>
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
                <h1>Candidates</h1>
                <p>View and manage all job applicants</p>
            </div>

            <div class="filter-box">
                <form method="GET" class="filter-form" id="filterForm">
                    <div class="field-icon">
                        <span>⌕</span>
                        <input
                            type="text"
                            name="search"
                            placeholder="Search by name, email, or job..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                    </div>

                    <div class="field-icon">
                        <span>⏷</span>
                        <select name="status" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="new" <?php echo $status === 'new' ? 'selected' : ''; ?>>New</option>
                            <option value="reviewing" <?php echo $status === 'reviewing' ? 'selected' : ''; ?>>Reviewing</option>
                            <option value="shortlisted" <?php echo $status === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                            <option value="interview" <?php echo $status === 'interview' ? 'selected' : ''; ?>>Interview</option>
                            <option value="offered" <?php echo $status === 'offered' ? 'selected' : ''; ?>>Offered</option>
                            <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="field-icon">
                        <span>⏷</span>
                        <select name="job_id" onchange="this.form.submit()">
                            <option value="">All Jobs</option>
                            <?php foreach ($jobsForFilter as $jobOption): ?>
                                <option value="<?php echo (int)$jobOption['job_id']; ?>" <?php echo $job_id == $jobOption['job_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($jobOption['job_title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="label">Total</div>
                    <div class="value"><?php echo (int)($stats['total_count'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">New</div>
                    <div class="value blue"><?php echo (int)($stats['new_count'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Shortlisted</div>
                    <div class="value green"><?php echo (int)($stats['shortlisted_count'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Interview</div>
                    <div class="value purple"><?php echo (int)($stats['interview_count'] ?? 0); ?></div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-head">
                    <div>Candidate</div>
                    <div>Contact</div>
                    <div>Position</div>
                    <div>Applied Date</div>
                    <div>Status</div>
                    <div>AI Score</div>
                    <div>Actions</div>
                </div>

                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                        $appliedDate = getAppliedDate($row);
                        $score = calculateAiScore($row);
                        $yearsText = trim((string)($row['years_experience'] ?? ''));
                        if ($yearsText === '') {
                            $yearsText = 'Experience not specified';
                        }
                        ?>
                        <div class="candidate-row">
                            <div class="candidate-main">
                                <div class="candidate-avatar">
                                    <?php echo strtoupper(substr($row['full_name'] ?? 'C', 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="candidate-name"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                    <div class="candidate-sub"><?php echo htmlspecialchars($yearsText); ?></div>
                                </div>
                            </div>

                            <div class="contact-block">
                                <div><?php echo htmlspecialchars($row['email'] ?? '-'); ?></div>
                                <div><?php echo htmlspecialchars($row['phone_number'] ?? '-'); ?></div>
                            </div>

                            <div class="job-title"><?php echo htmlspecialchars($row['job_title'] ?? '-'); ?></div>

                            <div><?php echo htmlspecialchars($appliedDate); ?></div>

                            <div>
                                <span class="status-badge <?php echo badgeClass($row['application_status'] ?? ''); ?>">
                                    <?php echo ucfirst(htmlspecialchars($row['application_status'] ?? 'Unknown')); ?>
                                </span>
                            </div>

                            <div class="score">
                                <?php echo $score; ?> <small>/100</small>
                            </div>

                            <div>
                                <a href="candidate_details.php?application_id=<?php echo (int)$row['application_id']; ?>" class="details-link">
                                    View Details
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-box">No candidates found.</div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>