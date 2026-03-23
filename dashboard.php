<?php
require_once 'config.php';
require_once 'includes/layout.php';
requireLogin();

$pdo = getDB();

$stats = [
    'students'  => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    'faculty'   => $pdo->query("SELECT COUNT(*) FROM faculty")->fetchColumn(),
    'events'    => $pdo->query("SELECT COUNT(*) FROM events WHERE status='Upcoming'")->fetchColumn(),
    'research'  => $pdo->query("SELECT COUNT(*) FROM college_research")->fetchColumn(),
    'syllabus'  => $pdo->query("SELECT COUNT(*) FROM syllabus")->fetchColumn(),
    'lessons'   => $pdo->query("SELECT COUNT(*) FROM lessons")->fetchColumn(),
];

$recentStudents = $pdo->query("SELECT * FROM students ORDER BY created_at DESC LIMIT 5")->fetchAll();
$upcomingEvents = $pdo->query("SELECT * FROM events WHERE status='Upcoming' ORDER BY event_date ASC LIMIT 5")->fetchAll();
$activeStudents = $pdo->query("SELECT COUNT(*) FROM students WHERE enrollment_status='Active'")->fetchColumn();
$avgGpa = $pdo->query("SELECT ROUND(AVG(gpa),2) FROM students")->fetchColumn();

renderHeader('Dashboard', 'dashboard');
flash();
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon navy">🎓</div>
        <div class="stat-info">
            <strong><?= $stats['students'] ?></strong>
            <span>Total Students</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold">👩‍🏫</div>
        <div class="stat-info">
            <strong><?= $stats['faculty'] ?></strong>
            <span>Faculty Members</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">📅</div>
        <div class="stat-info">
            <strong><?= $stats['events'] ?></strong>
            <span>Upcoming Events</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">🔬</div>
        <div class="stat-info">
            <strong><?= $stats['research'] ?></strong>
            <span>Research Papers</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">📋</div>
        <div class="stat-info">
            <strong><?= $stats['syllabus'] ?></strong>
            <span>Syllabi Loaded</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">📝</div>
        <div class="stat-info">
            <strong><?= $stats['lessons'] ?></strong>
            <span>Lesson Plans</span>
        </div>
    </div>
</div>

<!-- Quick metrics -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px;">
    <div class="card">
        <div class="card-header">
            <h3>📊 Enrollment Summary</h3>
            <a href="student_profile.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body">
            <div style="display:flex; gap:24px; flex-wrap:wrap;">
                <div style="text-align:center;">
                    <div style="font-size:32px; font-weight:700; color:var(--navy); font-family:'Playfair Display',serif;"><?= $activeStudents ?></div>
                    <div style="font-size:12px; color:var(--muted); margin-top:4px;">Active Students</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:32px; font-weight:700; color:var(--gold); font-family:'Playfair Display',serif;"><?= number_format($avgGpa,2) ?></div>
                    <div style="font-size:12px; color:var(--muted); margin-top:4px;">Average GPA</div>
                </div>
            </div>
            <hr class="divider">
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>Student</th><th>Course</th><th>Year</th><th>GPA</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentStudents as $s): ?>
                    <tr>
                        <td><strong><?= e($s['first_name'].' '.$s['last_name']) ?></strong><br><small style="color:var(--muted)"><?= e($s['student_id']) ?></small></td>
                        <td><?= e($s['course']) ?></td>
                        <td><?= e($s['year_level']) ?></td>
                        <td><?= number_format($s['gpa'],2) ?></td>
                        <td><span class="badge <?= $s['enrollment_status']==='Active'?'badge-success':'badge-warning' ?>"><?= e($s['enrollment_status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>📅 Upcoming Events</h3>
            <a href="events.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body">
            <?php foreach ($upcomingEvents as $ev): ?>
            <div style="display:flex; gap:14px; align-items:flex-start; padding:10px 0; border-bottom:1px solid var(--border);">
                <div style="background:var(--navy); color:var(--gold); padding:8px 10px; border-radius:8px; text-align:center; min-width:44px; flex-shrink:0;">
                    <div style="font-size:17px; font-weight:700; font-family:'Playfair Display',serif;"><?= date('d', strtotime($ev['event_date'])) ?></div>
                    <div style="font-size:9px; text-transform:uppercase; letter-spacing:0.5px;"><?= date('M', strtotime($ev['event_date'])) ?></div>
                </div>
                <div>
                    <strong style="font-size:13.5px;"><?= e($ev['title']) ?></strong>
                    <p style="font-size:12px; color:var(--muted); margin-top:2px;">📍 <?= e($ev['location']) ?> &nbsp; 🕐 <?= $ev['event_time'] ?></p>
                    <span class="badge badge-info" style="margin-top:4px; font-size:10px;"><?= e($ev['category']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Quick Nav Links -->
<div class="card">
    <div class="card-header"><h3>⚡ Quick Access</h3></div>
    <div class="card-body">
        <div style="display:flex; flex-wrap:wrap; gap:12px;">
            <a href="student_profile.php" class="btn btn-primary">🎓 Student Profiles</a>
            <a href="faculty_profile.php" class="btn btn-primary">👩‍🏫 Faculty Profiles</a>
            <a href="events.php" class="btn btn-gold">📅 Events</a>
            <a href="scheduling.php" class="btn btn-outline">🗓️ Scheduling</a>
            <a href="college_research.php" class="btn btn-outline">🔬 Research</a>
            <a href="instructions.php?tab=syllabus" class="btn btn-outline">📋 Syllabus</a>
            <a href="instructions.php?tab=curriculum" class="btn btn-outline">📚 Curriculum</a>
            <a href="instructions.php?tab=lessons" class="btn btn-outline">📝 Lessons</a>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
