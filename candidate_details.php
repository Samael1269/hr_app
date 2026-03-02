<?php
include 'config.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$full_name = $_SESSION["full_name"] ?? "Admin";
$email = $_SESSION["email"] ?? "admin@example.com";

if (!isset($_GET["application_id"]) || !is_numeric($_GET["application_id"])) {
    die("Invalid application ID.");
}

$application_id = (int)$_GET["application_id"];
$message = "";

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

function scoreMessage(int $score): string
{
    if ($score >= 90) {
        return "Excellent match. Strong technical background with relevant experience.";
    }
    if ($score >= 80) {
        return "Strong candidate with good alignment to the role requirements.";
    }
    if ($score >= 70) {
        return "Promising profile with several relevant qualifications.";
    }
    return "Potential match. Additional review is recommended.";
}

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

$allowedStatuses = ['new', 'reviewing', 'shortlisted', 'interview', 'offered', 'rejected'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["new_status"])) {
    $newStatus = strtolower(trim($_POST["new_status"]));

    if (in_array($newStatus, $allowedStatuses, true)) {
        $updateStmt = $conn->prepare("
            UPDATE applications
            SET application_status = ?
            WHERE application_id = ?
        ");
        $updateStmt->bind_param("si", $newStatus, $application_id);
        $updateStmt->execute();
        $updateStmt->close();

        header("Location: candidate_details.php?application_id=" . $application_id . "&updated=1");
        exit();
    }
}

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $message = "Candidate status updated successfully.";
}

$sql = "
    SELECT
        a.*,
        c.candidate_id,
        c.full_name,
        c.email,
        c.phone_number,
        c.highest_education,
        c.years_experience,
        j.job_id,
        j.job_title,
        j.required_skills,
        j.required_education,
        j.required_experience,
        rf.file_name AS resume_name,
        rf.file_path AS resume_path
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
    WHERE a.application_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $application_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Candidate application not found.");
}

$row = $result->fetch_assoc();
$stmt->close();

$appliedDate = getAppliedDate($row);
$aiScore = calculateAiScore($row);
$scoreText = scoreMessage($aiScore);

$skillsSource = '';
if (!empty($row['skills'])) {
    $skillsSource = $row['skills'];
} elseif (!empty($row['required_skills'])) {
    $skillsSource = $row['required_skills'];
}

$skillTags = [];
if ($skillsSource !== '') {
    $parts = preg_split('/[\n,]+/', $skillsSource);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $skillTags[] = $part;
        }
    }
}
$skillTags = array_slice($skillTags, 0, 8);

$coverLetter = trim((string)($row['cover_letter'] ?? ''));
if ($coverLetter === '') {
    $coverLetter = "Cover letter was not saved in the current database structure.";
}

$currentStatus = strtolower($row['application_status'] ?? 'new');
$yearsText = trim((string)($row['years_experience'] ?? 'Experience not specified'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Details | ATS System</title>
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

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #334155;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .message {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            font-weight: 700;
        }

        .grid-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 22px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 22px;
        }

        .candidate-head {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 18px;
        }

        .candidate-main {
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .candidate-avatar-lg {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #dbeafe;
            color: #4361ee;
            font-size: 34px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .candidate-name {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .candidate-role {
            font-size: 15px;
            color: #475569;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge-new { background: #dbeafe; color: #2563eb; }
        .badge-reviewing { background: #fef3c7; color: #b45309; }
        .badge-shortlisted { background: #dcfce7; color: #2f9e44; }
        .badge-interview { background: #ede9fe; color: #7c3aed; }
        .badge-offered { background: #dcfce7; color: #15803d; }
        .badge-rejected { background: #fee2e2; color: #dc2626; }
        .badge-default { background: #e5e7eb; color: #475569; }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 32px;
            color: #334155;
        }

        .score-card {
            background: linear-gradient(90deg, #4361ee, #5b3df5);
            color: white;
        }

        .score-card h3 {
            font-size: 18px;
            margin-bottom: 14px;
        }

        .score-value {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .score-value small {
            font-size: 20px;
            font-weight: 500;
            opacity: 0.95;
        }

        .section-title {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .skill-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .skill-tag {
            display: inline-flex;
            align-items: center;
            background: #dbeafe;
            color: #4361ee;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
        }

        .status-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .status-btn {
            width: 100%;
            border: none;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 15px;
            font-weight: 700;
            text-align: left;
            background: #f8fafc;
            color: #334155;
            cursor: pointer;
        }

        .status-btn.active {
            background: linear-gradient(90deg, #4361ee, #4f6df5);
            color: white;
        }

        .action-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .action-btn {
            width: 100%;
            text-decoration: none;
            border-radius: 12px;
            padding: 14px 16px;
            text-align: center;
            font-weight: 700;
            font-size: 15px;
            background: #f8fafc;
            color: #334155;
            display: block;
        }

        .action-btn.primary {
            background: linear-gradient(90deg, #4361ee, #4f6df5);
            color: white;
        }

        .timeline-item {
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
        }

        .timeline-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-top: 7px;
            flex-shrink: 0;
        }

        .timeline-blue { background: #4361ee; }
        .timeline-green { background: #2f9e44; }

        .timeline-title {
            font-weight: 700;
            margin-bottom: 4px;
        }

        .timeline-sub {
            color: #64748b;
            font-size: 14px;
        }

        .resume-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            background: #f8fafc;
            border-radius: 14px;
            padding: 16px;
        }

        .resume-name {
            font-weight: 800;
            margin-bottom: 4px;
        }

        .resume-sub {
            color: #64748b;
            font-size: 14px;
        }

        .resume-download {
            text-decoration: none;
            color: #4361ee;
            font-weight: 700;
        }

        @media (max-width: 1100px) {
            .grid-layout {
                grid-template-columns: 1fr;
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

            .info-grid {
                grid-template-columns: 1fr;
            }

            .candidate-head {
                flex-direction: column;
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
            <a href="candidates.php" class="back-link">← Back to Candidates</a>

            <?php if ($message !== ''): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="grid-layout">
                <div>
                    <div class="card">
                        <div class="candidate-head">
                            <div class="candidate-main">
                                <div class="candidate-avatar-lg">
                                    <?php echo strtoupper(substr($row['full_name'] ?? 'C', 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="candidate-name"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                    <div class="candidate-role"><?php echo htmlspecialchars($row['job_title']); ?></div>
                                </div>
                            </div>

                            <div>
                                <span class="status-badge <?php echo badgeClass($currentStatus); ?>">
                                    <?php echo ucfirst(htmlspecialchars($currentStatus)); ?>
                                </span>
                            </div>
                        </div>

                        <div class="info-grid">
                            <div>✉ <?php echo htmlspecialchars($row['email'] ?? '-'); ?></div>
                            <div>☎ <?php echo htmlspecialchars($row['phone_number'] ?? '-'); ?></div>
                            <div>🗓 Applied: <?php echo htmlspecialchars($appliedDate); ?></div>
                            <div>💼 <?php echo htmlspecialchars($yearsText); ?></div>
                        </div>
                    </div>

                    <div class="card score-card">
                        <h3>AI Match Score</h3>
                        <div class="score-value"><?php echo $aiScore; ?><small> /100</small></div>
                        <div><?php echo htmlspecialchars($scoreText); ?></div>
                    </div>

                    <div class="card">
                        <div class="section-title">Education</div>
                        <div><?php echo htmlspecialchars($row['highest_education'] ?? 'Not provided'); ?></div>
                    </div>

                    <div class="card">
                        <div class="section-title">Skills</div>
                        <?php if (!empty($skillTags)): ?>
                            <div class="skill-tags">
                                <?php foreach ($skillTags as $tag): ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div>No skills stored yet.</div>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <div class="section-title">Cover Letter</div>
                        <div><?php echo nl2br(htmlspecialchars($coverLetter)); ?></div>
                    </div>

                    <div class="card">
                        <div class="section-title">Resume</div>

                        <?php if (!empty($row['resume_path'])): ?>
                            <div class="resume-box">
                                <div>
                                    <div class="resume-name"><?php echo htmlspecialchars($row['resume_name'] ?? 'Resume File'); ?></div>
                                    <div class="resume-sub">PDF / DOC / DOCX Document</div>
                                </div>
                                <a href="<?php echo htmlspecialchars($row['resume_path']); ?>" class="resume-download" target="_blank">Download</a>
                            </div>
                        <?php else: ?>
                            <div>No resume uploaded.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <div class="card">
                        <div class="section-title">Change Status</div>
                        <form method="POST">
                            <div class="status-list">
                                <?php foreach ($allowedStatuses as $statusOption): ?>
                                    <button
                                        type="submit"
                                        name="new_status"
                                        value="<?php echo htmlspecialchars($statusOption); ?>"
                                        class="status-btn <?php echo $currentStatus === $statusOption ? 'active' : ''; ?>"
                                    >
                                        <?php echo ucfirst($statusOption); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    </div>

                    <div class="card">
                        <div class="section-title">Quick Actions</div>
                        <div class="action-list">
                            <a href="interviews.php?application_id=<?php echo (int)$application_id; ?>" class="action-btn primary">Schedule Interview</a>
                            <a href="mailto:<?php echo htmlspecialchars($row['email'] ?? ''); ?>" class="action-btn">Send Email</a>
                            <a href="#" class="action-btn">Add Note</a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="section-title">Activity Timeline</div>

                        <div class="timeline-item">
                            <div class="timeline-dot timeline-blue"></div>
                            <div>
                                <div class="timeline-title">Application Submitted</div>
                                <div class="timeline-sub"><?php echo htmlspecialchars($appliedDate); ?></div>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-dot timeline-green"></div>
                            <div>
                                <div class="timeline-title">Status: <?php echo ucfirst(htmlspecialchars($currentStatus)); ?></div>
                                <div class="timeline-sub">Current status</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>