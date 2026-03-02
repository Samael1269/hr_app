<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$full_name = $_SESSION["full_name"] ?? "Admin User";
$email = $_SESSION["email"] ?? "admin@gmail.com";

$stats = [
    "active_jobs" => 3,
    "total_candidates" => 4,
    "scheduled_interviews" => 2,
    "applications" => 73
];

$recent_candidates = [
    [
        "initial" => "S",
        "name" => "Sarah Johnson",
        "position" => "Senior Frontend Developer",
        "status" => "Shortlisted",
        "status_class" => "green"
    ],
    [
        "initial" => "M",
        "name" => "Michael Chen",
        "position" => "Senior Frontend Developer",
        "status" => "Interview",
        "status_class" => "purple"
    ],
    [
        "initial" => "E",
        "name" => "Emily Rodriguez",
        "position" => "Product Manager",
        "status" => "New",
        "status_class" => "gray"
    ],
    [
        "initial" => "D",
        "name" => "David Kim",
        "position" => "UX Designer",
        "status" => "Reviewing",
        "status_class" => "gray"
    ]
];

$interviews = [
    [
        "name" => "Michael Chen",
        "role" => "Senior Frontend Developer",
        "date" => "2026-03-05",
        "time" => "14:00"
    ],
    [
        "name" => "Sarah Johnson",
        "role" => "Senior Frontend Developer",
        "date" => "2026-03-03",
        "time" => "10:00"
    ]
];

$chart_data = [
    ["label" => "Product Manager", "value" => 24],
    ["label" => "UX Designer", "value" => 18],
    ["label" => "Marketing Intern", "value" => 31]
];

$max_chart = max(array_column($chart_data, "value"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | ATS System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f3f4f6;
            color: #111827;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #0f172a, #111827);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            padding: 22px 16px 16px;
        }

        .sidebar-top h2 {
            font-size: 19px;
            font-weight: 700;
        }

        .brand-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 26px;
        }

        .menu-toggle {
            font-size: 24px;
            opacity: 0.9;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #ffffff;
            padding: 15px 14px;
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

        .nav-icon {
            width: 18px;
            text-align: center;
            font-size: 18px;
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
            font-size: 16px;
        }

        .user-details .name {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .user-details .email {
            font-size: 13px;
            color: #cbd5e1;
        }

        .logout-btn {
            display: block;
            width: 100%;
            text-align: center;
            background: #dc2626;
            color: #ffffff;
            text-decoration: none;
            padding: 14px;
            border-radius: 14px;
            font-weight: 700;
        }

        .main-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            padding: 26px 30px;
        }

        .page-header h1 {
            font-size: 50px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #4b5563;
            font-size: 16px;
            margin-bottom: 28px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 22px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .stat-card .label {
            color: #4b5563;
            font-size: 15px;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: 800;
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 24px;
        }

        .blue { background: #4f7cf3; }
        .green { background: #5ac95f; }
        .purple { background: #9b59f6; }
        .orange { background: #f48c1f; }

        .content-grid {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 22px;
            margin-bottom: 24px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .card-header h3 {
            font-size: 20px;
            font-weight: 800;
        }

        .view-link {
            text-decoration: none;
            color: #4f6df5;
            font-size: 15px;
        }

        .chart-box {
            height: 340px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .chart-area {
            height: 260px;
            border-left: 2px solid #9ca3af;
            border-bottom: 2px solid #9ca3af;
            padding: 0 12px 0 12px;
            display: flex;
            align-items: flex-end;
            gap: 26px;
            position: relative;
            background-image:
                linear-gradient(to top, rgba(156,163,175,0.25) 1px, transparent 1px);
            background-size: 100% 25%;
        }

        .bar-group {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            height: 100%;
        }

        .bar {
            width: 100%;
            max-width: 95px;
            background: #5c82e6;
            border-radius: 0;
        }

        .bar-label {
            margin-top: 10px;
            font-size: 14px;
            color: #4b5563;
            text-align: center;
        }

        .candidate-list,
        .interview-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .candidate-item,
        .interview-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
        }

        .candidate-left,
        .interview-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .candidate-avatar,
        .interview-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #e0e7ff;
            color: #4361ee;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }

        .interview-icon {
            background: #f3e8ff;
            color: #9333ea;
            font-size: 20px;
        }

        .candidate-name,
        .interview-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .candidate-role,
        .interview-role {
            color: #4b5563;
            font-size: 14px;
        }

        .status-badge {
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
        }

        .status-badge.green {
            background: #dcfce7;
            color: #2f855a;
        }

        .status-badge.purple {
            background: #f3e8ff;
            color: #7c3aed;
        }

        .status-badge.gray {
            background: #f3f4f6;
            color: #374151;
        }

        .interview-item {
            background: #f9fafb;
            border-radius: 14px;
            padding: 18px 18px;
        }

        .interview-right {
            text-align: right;
        }

        .interview-date {
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .interview-time {
            color: #4b5563;
            font-size: 14px;
        }

        .quick-actions {
            background: linear-gradient(90deg, #4f6df5, #5a46f2);
            color: #ffffff;
            border-radius: 20px;
            padding: 26px;
        }

        .quick-actions h3 {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .quick-btn {
            background: rgba(255,255,255,0.12);
            color: #ffffff;
            text-decoration: none;
            padding: 18px 20px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 16px;
        }

        .quick-plus {
            font-size: 30px;
            line-height: 1;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .quick-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .layout {
                flex-direction: column;
            }

            .stats-grid {
                grid-template-columns: 1fr;
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
                    <div class="menu-toggle">≡</div>
                </div>

                <nav class="nav-menu">
                    <a href="dashboard.php" class="active"><span class="nav-icon">⌘</span> Dashboard</a>
                    <a href="jobs.php"><span class="nav-icon">▣</span> Job Postings</a>
                    <a href="candidates.php"><span class="nav-icon">◔</span> Candidates</a>
                    <a href="resume_upload.php"><span class="nav-icon">⇪</span> Resume Upload</a>
                    <a href="ai_analysis.php"><span class="nav-icon">✦</span> AI Analysis</a>
                    <a href="interviews.php"><span class="nav-icon">☷</span> Interviews</a>
                </nav>
            </div>

            <div class="sidebar-bottom">
                <div class="user-box">
                    <div class="avatar">
                        <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                    </div>
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
                <h1>Dashboard</h1>
                <p>Welcome back! Here's what's happening today.</p>
            </div>

            <section class="stats-grid">
                <div class="stat-card">
                    <div>
                        <div class="label">Active Jobs</div>
                        <div class="value"><?php echo $stats["active_jobs"]; ?></div>
                    </div>
                    <div class="stat-icon blue">▣</div>
                </div>

                <div class="stat-card">
                    <div>
                        <div class="label">Total Candidates</div>
                        <div class="value"><?php echo $stats["total_candidates"]; ?></div>
                    </div>
                    <div class="stat-icon green">◔</div>
                </div>

                <div class="stat-card">
                    <div>
                        <div class="label">Scheduled Interviews</div>
                        <div class="value"><?php echo $stats["scheduled_interviews"]; ?></div>
                    </div>
                    <div class="stat-icon purple">☷</div>
                </div>

                <div class="stat-card">
                    <div>
                        <div class="label">Applications</div>
                        <div class="value"><?php echo $stats["applications"]; ?></div>
                    </div>
                    <div class="stat-icon orange">↗</div>
                </div>
            </section>

            <section class="content-grid">
                <div class="card">
                    <div class="card-header">
                        <h3>Applications by Job</h3>
                    </div>

                    <div class="chart-box">
                        <div class="chart-area">
                            <?php foreach ($chart_data as $item): ?>
                                <div class="bar-group">
                                    <div
                                        class="bar"
                                        style="height: <?php echo ($item['value'] / $max_chart) * 250; ?>px;">
                                    </div>
                                    <div class="bar-label"><?php echo htmlspecialchars($item["label"]); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Recent Candidates</h3>
                        <a href="candidates.php" class="view-link">View all</a>
                    </div>

                    <div class="candidate-list">
                        <?php foreach ($recent_candidates as $candidate): ?>
                            <div class="candidate-item">
                                <div class="candidate-left">
                                    <div class="candidate-avatar">
                                        <?php echo htmlspecialchars($candidate["initial"]); ?>
                                    </div>
                                    <div>
                                        <div class="candidate-name"><?php echo htmlspecialchars($candidate["name"]); ?></div>
                                        <div class="candidate-role"><?php echo htmlspecialchars($candidate["position"]); ?></div>
                                    </div>
                                </div>
                                <div class="status-badge <?php echo $candidate["status_class"]; ?>">
                                    <?php echo htmlspecialchars($candidate["status"]); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h3>Upcoming Interviews</h3>
                    <a href="interviews.php" class="view-link">View all</a>
                </div>

                <div class="interview-list">
                    <?php foreach ($interviews as $interview): ?>
                        <div class="interview-item">
                            <div class="interview-left">
                                <div class="interview-icon">☷</div>
                                <div>
                                    <div class="interview-name"><?php echo htmlspecialchars($interview["name"]); ?></div>
                                    <div class="interview-role"><?php echo htmlspecialchars($interview["role"]); ?></div>
                                </div>
                            </div>

                            <div class="interview-right">
                                <div class="interview-date"><?php echo htmlspecialchars($interview["date"]); ?></div>
                                <div class="interview-time"><?php echo htmlspecialchars($interview["time"]); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="quick-actions">
                <h3>Quick Actions</h3>
                <div class="quick-grid">
                    <a href="jobs.php" class="quick-btn">
                        <span class="quick-plus">+</span>
                        <span>Post New Job</span>
                    </a>

                    <a href="resume_upload.php" class="quick-btn">
                        <span class="quick-plus">+</span>
                        <span>Upload Resume</span>
                    </a>

                    <a href="ai_analysis.php" class="quick-btn">
                        <span class="quick-plus">+</span>
                        <span>Run AI Analysis</span>
                    </a>
                </div>
            </section>
        </main>
    </div>
</body>
</html>