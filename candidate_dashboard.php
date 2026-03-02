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

$full_name = $_SESSION["full_name"] ?? "Candidate";
$email = $_SESSION["email"] ?? "candidate@example.com";

$search = trim($_GET["search"] ?? "");

$sql = "SELECT * FROM jobs WHERE status = 'active'";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (job_title LIKE ? OR department LIKE ?)";
    $searchTerm = "%" . $search . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

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
    <title>Candidate Dashboard | ATS System</title>
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

        .nav-menu a:hover {
            background: rgba(255,255,255,0.08);
        }

        .nav-menu a.active {
            background: linear-gradient(90deg, #4361ee, #4f6df5);
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
            text-transform: uppercase;
        }

        .user-details .name {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .user-details .role {
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

        .search-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 16px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .search-box {
            position: relative;
        }

        .search-box svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            stroke: #9ca3af;
            stroke-width: 2;
            fill: none;
        }

        .search-box input {
            width: 100%;
            height: 44px;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 0 14px 0 42px;
            font-size: 15px;
            outline: none;
            background: #fff;
        }

        .search-box input:focus {
            border-color: #4f6df5;
            box-shadow: 0 0 0 3px rgba(79,109,245,0.12);
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

        .salary {
            font-weight: 800;
            color: #0f172a;
        }

        .job-right {
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            min-width: 160px;
        }

        .apply-btn {
            text-decoration: none;
            background: #4361ee;
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            display: inline-block;
        }

        .empty-box {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            color: #64748b;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        @media (max-width: 1000px) {
            .job-card {
                flex-direction: column;
            }

            .job-right {
                justify-content: flex-start;
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
                <a href="candidate_dashboard.php" class="active">▣ Job Postings</a>
                <a href="my_interviews.php">☷ My Interviews</a>
            </nav>
        </div>

        <div class="sidebar-bottom">
            <div class="user-box">
                <div class="avatar"><?php echo strtoupper(substr($full_name, 0, 1)); ?></div>
                <div class="user-details">
                    <div class="name"><?php echo htmlspecialchars($full_name); ?></div>
                    <div class="role">Candidate</div>
                </div>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>Job Postings</h1>
            <p>Browse available positions</p>
        </div>

        <div class="search-card">
            <form method="GET" class="search-box">
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
                                <div class="job-title"><?php echo htmlspecialchars($job["job_title"]); ?></div>

                                <div class="job-meta">
                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <rect x="3" y="7" width="18" height="13" rx="2"></rect>
                                            <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                        <?php echo htmlspecialchars($job["department"]); ?>
                                    </span>

                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="9"></circle>
                                            <path d="M12 7v5l3 3"></path>
                                        </svg>
                                        <?php echo htmlspecialchars($job["job_type"] ?: "Not specified"); ?>
                                    </span>
                                </div>

                                <div class="job-desc">
                                    <?php echo htmlspecialchars(mb_strimwidth($job["job_description"], 0, 95, "...")); ?>
                                </div>

                                <div class="salary">
                                    <?php echo formatSalary($job["salary_min"], $job["salary_max"]); ?>
                                </div>
                            </div>
                        </div>

                        <div class="job-right">
                            <a href="apply_jobs.php?id=<?php echo $job["job_id"]; ?>" class="apply-btn">Apply Now</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-box">
                    No active job postings found.
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>