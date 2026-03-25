<?php
require_once 'config.php';
require_once 'includes/layout.php';
requireLogin();

$pdo = getDB();
$action = $_GET['action'] ?? 'list';
$id     = intval($_GET['id'] ?? 0);

// Handle DELETE
if ($action === 'delete' && $id) {
    $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$id]);
    redirect('student_profile.php', 'Student deleted successfully.');
}

// Handle ADD/EDIT submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'student_id','first_name','last_name','email','phone','date_of_birth',
        'gender','address','year_level','course','section','enrollment_status',
        'gpa','emergency_contact','emergency_phone',
        // NEW FIELDS
        'skills','non_academic_activities','violations','affiliations','academic_history'
    ];
    $data = [];
    foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');

    if (!$data['student_id'] || !$data['first_name'] || !$data['last_name']) {
        $_SESSION['flash'] = ['msg' => 'Student ID, first name and last name are required.', 'type' => 'error'];
    } else {
        if ($id) {
            $set = implode(', ', array_map(fn($f) => "$f=?", $fields));
            $stmt = $pdo->prepare("UPDATE students SET $set WHERE id=?");
            $stmt->execute([...array_values($data), $id]);
            redirect('student_profile.php', 'Student updated successfully.');
        } else {
            $cols = implode(',', $fields);
            $placeholders = implode(',', array_fill(0, count($fields), '?'));
            $stmt = $pdo->prepare("INSERT INTO students ($cols) VALUES ($placeholders)");
            $stmt->execute(array_values($data));
            redirect('student_profile.php', 'Student added successfully.');
        }
    }
}

$student = null;
if ($id && in_array($action, ['edit','view'])) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id=?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();
}

$students = $pdo->query("SELECT * FROM students ORDER BY last_name, first_name")->fetchAll();

renderHeader('Student Profiles', 'students');
flash();
?>

<!-- ========== QUERY / FILTERING PANEL ========== -->
<div class="card" style="margin-bottom:18px;">
    <div class="card-header" style="cursor:pointer;" onclick="togglePanel('queryPanel')">
        <h3>🔎 Query / Filtering</h3>
        <span style="font-size:12px;color:var(--muted);">Click to expand / collapse</span>
    </div>
    <div id="queryPanel" style="display:none; padding:18px 22px 10px;">

        <!-- QUERY 1 — Filter by Skill -->
        <div class="query-block">
            <p style="font-weight:600; margin-bottom:8px;">Query 1 — Show students with a specific <span style="color:var(--accent);">Skill</span></p>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <input type="text" id="q1Skill" placeholder='e.g. Programming, Basketball'
                    style="padding:9px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;min-width:220px;">
                <button class="btn btn-primary btn-sm" onclick="runQuery1()">▶ Run Query</button>
                <button class="btn btn-outline btn-sm" onclick="clearQuery1()">✕ Clear</button>
            </div>
            <div id="q1Results" style="margin-top:14px;"></div>
        </div>

        <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">

        <!-- QUERY 2 — Filter by Affiliation / Org -->
        <div class="query-block">
            <p style="font-weight:600; margin-bottom:8px;">Query 2 — Show students with a specific <span style="color:var(--accent);">Affiliation / Organization</span></p>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <input type="text" id="q2Affil" placeholder='e.g. Basketball, Red Cross, Robotics Club'
                    style="padding:9px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;min-width:220px;">
                <button class="btn btn-primary btn-sm" onclick="runQuery2()">▶ Run Query</button>
                <button class="btn btn-outline btn-sm" onclick="clearQuery2()">✕ Clear</button>
            </div>
            <div id="q2Results" style="margin-top:14px;"></div>
        </div>

        <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">

        <!-- QUERY 3 — Filter by Enrollment Status + Year Level -->
        <div class="query-block">
            <p style="font-weight:600; margin-bottom:8px;">Query 3 — Show students by <span style="color:var(--accent);">Year Level &amp; Status</span></p>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <select id="q3Year" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;">
                    <option value="">— Any Year —</option>
                    <option>1st Year</option><option>2nd Year</option>
                    <option>3rd Year</option><option>4th Year</option><option>5th Year</option>
                </select>
                <select id="q3Status" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;">
                    <option value="">— Any Status —</option>
                    <option>Active</option><option>Inactive</option>
                    <option>Graduated</option><option>LOA</option>
                </select>
                <button class="btn btn-primary btn-sm" onclick="runQuery3()">▶ Run Query</button>
                <button class="btn btn-outline btn-sm" onclick="clearQuery3()">✕ Clear</button>
            </div>
            <div id="q3Results" style="margin-top:14px;"></div>
        </div>

    </div>
</div>

<!-- ========== MAIN TOOLBAR ========== -->
<div class="toolbar">
    <input class="search-input" id="studentSearch" type="text" placeholder="🔍 Search students by name, ID, course, skill...">
    <select id="filterStatus" onchange="filterTable()" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;">
        <option value="">All Status</option>
        <option>Active</option>
        <option>Inactive</option>
        <option>Graduated</option>
        <option>LOA</option>
    </select>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Student</button>
</div>

<!-- ========== STUDENTS TABLE ========== -->
<div class="card">
    <div class="card-header">
        <h3>🎓 Student Records</h3>
        <span style="font-size:13px; color:var(--muted);"><?= count($students) ?> total students</span>
    </div>
    <div class="tbl-wrap">
        <table id="studentTable">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Course</th>
                    <th>Year / Section</th>
                    <th>GPA</th>
                    <th>Skills</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($students)): ?>
            <tr><td colspan="8"><div class="empty-state"><div class="icon">🎓</div><p>No students found. Add your first student!</p></div></td></tr>
            <?php else: foreach ($students as $s): ?>
            <tr data-status="<?= e($s['enrollment_status']) ?>"
                data-skills="<?= e(strtolower($s['skills'] ?? '')) ?>"
                data-affiliations="<?= e(strtolower($s['affiliations'] ?? '')) ?>"
                data-year="<?= e($s['year_level']) ?>">
                <td><code style="font-size:12px; background:var(--cream); padding:2px 7px; border-radius:4px;"><?= e($s['student_id']) ?></code></td>
                <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div class="profile-avatar" style="width:34px;height:34px;font-size:13px;"><?= strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1)) ?></div>
                        <div>
                            <strong><?= e($s['first_name'].' '.$s['last_name']) ?></strong>
                            <p style="font-size:11.5px; color:var(--muted);"><?= e($s['email']) ?></p>
                        </div>
                    </div>
                </td>
                <td><?= e($s['course']) ?></td>
                <td><?= e($s['year_level']) ?> — <?= e($s['section']) ?></td>
                <td><strong><?= number_format($s['gpa'],2) ?></strong></td>
                <td>
                    <?php if (!empty($s['skills'])): ?>
                        <?php foreach (array_slice(explode(',', $s['skills']), 0, 3) as $sk): ?>
                            <span style="display:inline-block;background:var(--cream);border:1px solid var(--border);border-radius:12px;padding:1px 8px;font-size:11px;margin:1px;"><?= e(trim($sk)) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color:var(--muted);font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge <?= match($s['enrollment_status']) {
                        'Active' => 'badge-success',
                        'Graduated' => 'badge-info',
                        'Inactive' => 'badge-danger',
                        default => 'badge-warning'
                    } ?>"><?= e($s['enrollment_status']) ?></span>
                </td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button class="btn btn-outline btn-sm" onclick='viewStudent(<?= json_encode($s) ?>)'>👁 View</button>
                        <button class="btn btn-primary btn-sm" onclick='editStudent(<?= json_encode($s) ?>)'>✏️ Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="confirmDelete('student_profile.php?action=delete&id=<?= $s['id'] ?>', '<?= e($s['first_name'].' '.$s['last_name']) ?>')">🗑</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========== ADD MODAL ========== -->
<div class="modal-overlay" id="addModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>+ Add New Student</h3>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <form method="POST" action="student_profile.php">
        <div class="modal-body">

            <!-- TAB NAV -->
            <div class="modal-tabs" style="display:flex;gap:4px;border-bottom:2px solid var(--border);margin-bottom:18px;">
                <button type="button" class="tab-btn active" onclick="switchTab('add','personal')">👤 Personal</button>
                <button type="button" class="tab-btn" onclick="switchTab('add','academic')">📚 Academic</button>
                <button type="button" class="tab-btn" onclick="switchTab('add','extracurricular')">🏅 Extra-curricular</button>
                <button type="button" class="tab-btn" onclick="switchTab('add','violations')">⚠️ Violations</button>
            </div>

            <!-- TAB: PERSONAL -->
            <div id="add-tab-personal" class="tab-content">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Student ID *</label>
                        <input type="text" name="student_id" placeholder="STU-2024-001" required>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender"><option>Male</option><option>Female</option><option>Prefer not to say</option></select>
                    </div>
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth">
                    </div>
                    <div class="form-group">
                        <label>Enrollment Status</label>
                        <select name="enrollment_status">
                            <option>Active</option><option>Inactive</option>
                            <option>Graduated</option><option>LOA</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Address</label>
                        <input type="text" name="address">
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact Name</label>
                        <input type="text" name="emergency_contact">
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact Phone</label>
                        <input type="tel" name="emergency_phone">
                    </div>
                </div>
            </div>

            <!-- TAB: ACADEMIC -->
            <div id="add-tab-academic" class="tab-content" style="display:none;">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Course</label>
                        <input type="text" name="course" placeholder="BS Computer Science">
                    </div>
                    <div class="form-group">
                        <label>Year Level</label>
                        <select name="year_level">
                            <option>1st Year</option><option>2nd Year</option>
                            <option>3rd Year</option><option>4th Year</option><option>5th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <input type="text" name="section" placeholder="CS3A">
                    </div>
                    <div class="form-group">
                        <label>GPA</label>
                        <input type="number" name="gpa" step="0.01" min="1" max="5" value="1.00">
                    </div>
                    <div class="form-group full">
                        <label>Academic History <small style="color:var(--muted);">(Honors, awards, past schools, etc.)</small></label>
                        <textarea name="academic_history" rows="4" placeholder="e.g. Cum Laude - AY 2023, Valedictorian - SHS 2022..." style="width:100%;padding:9px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;resize:vertical;"></textarea>
                    </div>
                </div>
            </div>

            <!-- TAB: EXTRA-CURRICULAR -->
            <div id="add-tab-extracurricular" class="tab-content" style="display:none;">
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Skills <small style="color:var(--muted);">(comma-separated, e.g. Programming, Basketball, Graphic Design)</small></label>
                        <input type="text" name="skills" placeholder="Programming, Web Development, Basketball">
                    </div>
                    <div class="form-group full">
                        <label>Non-Academic Activities <small style="color:var(--muted);">(sports, hobbies, volunteer work, etc.)</small></label>
                        <textarea name="non_academic_activities" rows="3" placeholder="e.g. Varsity Basketball Player (2022–2024), Red Cross Youth Volunteer, School Theater..."
                            style="width:100%;padding:9px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;resize:vertical;"></textarea>
                    </div>
                    <div class="form-group full">
                        <label>Affiliations / Organizations <small style="color:var(--muted);">(orgs, sports teams, clubs)</small></label>
                        <textarea name="affiliations" rows="3" placeholder="e.g. Junior Philippine Computer Society (VP), Robotics Club, University Basketball Team..."
                            style="width:100%;padding:9px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;resize:vertical;"></textarea>
                    </div>
                </div>
            </div>

            <!-- TAB: VIOLATIONS -->
            <div id="add-tab-violations" class="tab-content" style="display:none;">
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Violations / Disciplinary Records <small style="color:var(--muted);">(leave blank if none)</small></label>
                        <textarea name="violations" rows="5" placeholder="e.g. 2024-03-10 — Minor violation: Late submission of requirements. Warning issued.&#10;2024-06-02 — Attendance policy violation. Verbal reprimand."
                            style="width:100%;padding:9px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;resize:vertical;color:#c0392b;"></textarea>
                        <p style="font-size:11px;color:var(--muted);margin-top:5px;">⚠️ This information is confidential and restricted to authorized personnel only.</p>
                    </div>
                </div>
            </div>

        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Student</button>
        </div>
        </form>
    </div>
</div>

<!-- ========== EDIT MODAL ========== -->
<div class="modal-overlay" id="editModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>✏️ Edit Student</h3>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <form method="POST" id="editForm">
        <div class="modal-body">

            <!-- TAB NAV -->
            <div class="modal-tabs" style="display:flex;gap:4px;border-bottom:2px solid var(--border);margin-bottom:18px;">
                <button type="button" class="tab-btn active" onclick="switchTab('edit','personal')">👤 Personal</button>
                <button type="button" class="tab-btn" onclick="switchTab('edit','academic')">📚 Academic</button>
                <button type="button" class="tab-btn" onclick="switchTab('edit','extracurricular')">🏅 Extra-curricular</button>
                <button type="button" class="tab-btn" onclick="switchTab('edit','violations')">⚠️ Violations</button>
            </div>

            <!-- TAB: PERSONAL -->
            <div id="edit-tab-personal" class="tab-content">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Student ID *</label>
                        <input type="text" name="student_id" id="e_student_id" required>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" id="e_gender"><option>Male</option><option>Female</option><option>Prefer not to say</option></select>
                    </div>
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" id="e_first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" id="e_last_name" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="e_email">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" id="e_phone">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" id="e_dob">
                    </div>
                    <div class="form-group">
                        <label>Enrollment Status</label>
                        <select name="enrollment_status" id="e_status">
                            <option>Active</option><option>Inactive</option>
                            <option>Graduated</option><option>LOA</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Address</label>
                        <input type="text" name="address" id="e_address">
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact</label>
                        <input type="text" name="emergency_contact" id="e_ec">
                    </div>
                    <div class="form-group">
                        <label>Emergency Phone</label>
                        <input type="tel" name="emergency_phone" id="e_ep">
                    </div>
                </div>
            </div>

            <!-- TAB: ACADEMIC -->
            <div id="edit-tab-academic" class="tab-content" style="display:none;">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Course</label>
                        <input type="text" name="course" id="e_course">
                    </div>
                    <div class="form-group">
                        <label>Year Level</label>
                        <select name="year_level" id="e_year_level">
                            <option>1st Year</option><option>2nd Year</option>
                            <option>3rd Year</option><option>4th Year</option><option>5th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <input type="text" name="section" id="e_section">
                    </div>
                    <div class="form-group">
                        <label>GPA</label>
                        <input type="number" name="gpa" id="e_gpa" step="0.01" min="1" max="5">
                    </div>
                    <div class="form-group full">
                        <label>Academic History</label>
                        <textarea name="academic_history" id="e_academic_history" rows="4"
                            style="width:100%;padding:9px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;resize:vertical;"></textarea>
                    </div>
                </div>
            </div>

            <!-- TAB: EXTRA-CURRICULAR -->
            <div id="edit-tab-extracurricular" class="tab-content" style="display:none;">
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Skills <small style="color:var(--muted);">(comma-separated)</small></label>
                        <input type="text" name="skills" id="e_skills">
                    </div>
                    <div class="form-group full">
                        <label>Non-Academic Activities</label>
                        <textarea name="non_academic_activities" id="e_activities" rows="3"
                            style="width:100%;padding:9px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;resize:vertical;"></textarea>
                    </div>
                    <div class="form-group full">
                        <label>Affiliations / Organizations</label>
                        <textarea name="affiliations" id="e_affiliations" rows="3"
                            style="width:100%;padding:9px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;resize:vertical;"></textarea>
                    </div>
                </div>
            </div>

            <!-- TAB: VIOLATIONS -->
            <div id="edit-tab-violations" class="tab-content" style="display:none;">
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Violations / Disciplinary Records</label>
                        <textarea name="violations" id="e_violations" rows="5"
                            style="width:100%;padding:9px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;resize:vertical;color:#c0392b;"></textarea>
                        <p style="font-size:11px;color:var(--muted);margin-top:5px;">⚠️ Confidential — restricted to authorized personnel only.</p>
                    </div>
                </div>
            </div>

        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Student</button>
        </div>
        </form>
    </div>
</div>

<!-- ========== VIEW MODAL ========== -->
<div class="modal-overlay" id="viewModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>👁 Student Profile</h3>
            <button class="modal-close" onclick="closeModal('viewModal')">✕</button>
        </div>
        <div class="modal-body" id="viewContent"></div>
    </div>
</div>

<!-- ========== STYLES ========== -->
<style>
.tab-btn {
    background: none;
    border: none;
    padding: 8px 16px;
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    cursor: pointer;
    color: var(--muted);
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all .2s;
}
.tab-btn.active {
    color: var(--navy);
    font-weight: 600;
    border-bottom: 2px solid var(--accent);
}
.tab-btn:hover { color: var(--navy); }
.query-results-table { width:100%; border-collapse:collapse; font-size:13px; margin-top:8px; }
.query-results-table th { background:var(--cream); padding:8px 12px; text-align:left; font-weight:600; }
.query-results-table td { padding:8px 12px; border-bottom:1px solid var(--border); }
.query-results-table tr:hover td { background: #f9f5f0; }
.query-no-results { color:var(--muted); font-size:13px; padding:10px 0; }
.section-label {
    font-size:11px; font-weight:700; letter-spacing:.08em;
    text-transform:uppercase; color:var(--muted);
    margin: 16px 0 6px; border-bottom:1px solid var(--border); padding-bottom:4px;
}
</style>

<!-- ========== JAVASCRIPT ========== -->
<script>
// Expose all student data for JS queries
const ALL_STUDENTS = <?= json_encode($students) ?>;

// ── Search / Filter ──────────────────────────────────────────────────────────
tableSearch('studentSearch','studentTable');

function filterTable() {
    const val = document.getElementById('filterStatus').value.toLowerCase();
    document.querySelectorAll('#studentTable tbody tr').forEach(row => {
        row.style.display = (!val || row.dataset.status?.toLowerCase() === val) ? '' : 'none';
    });
}

// ── Tab Switching ─────────────────────────────────────────────────────────────
function switchTab(prefix, name) {
    document.querySelectorAll(`[id^="${prefix}-tab-"]`).forEach(el => el.style.display = 'none');
    document.getElementById(`${prefix}-tab-${name}`).style.display = '';
    document.querySelectorAll(`#${prefix}Modal .tab-btn`).forEach(b => b.classList.remove('active'));
    event.target.classList.add('active');
}

// ── Query Panel Toggle ────────────────────────────────────────────────────────
function togglePanel(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? '' : 'none';
}

// ── Render Query Results as Table ─────────────────────────────────────────────
function renderResults(containerId, rows, emptyMsg) {
    const el = document.getElementById(containerId);
    if (!rows.length) {
        el.innerHTML = `<p class="query-no-results">ℹ️ ${emptyMsg || 'No students found matching your query.'}</p>`;
        return;
    }
    const statusMap = {'Active':'badge-success','Inactive':'badge-danger','Graduated':'badge-info','LOA':'badge-warning'};
    let html = `<p style="font-size:12px;color:var(--muted);margin-bottom:6px;">Found <strong>${rows.length}</strong> student(s)</p>
    <div class="tbl-wrap"><table class="query-results-table">
    <thead><tr><th>Student ID</th><th>Name</th><th>Course</th><th>Year</th><th>Skills</th><th>Affiliations</th><th>Status</th></tr></thead><tbody>`;
    rows.forEach(s => {
        const badge = statusMap[s.enrollment_status] || 'badge-warning';
        const skillTags = (s.skills||'').split(',').filter(x=>x.trim()).map(sk =>
            `<span style="display:inline-block;background:var(--cream);border:1px solid var(--border);border-radius:12px;padding:1px 7px;font-size:11px;margin:1px;">${sk.trim()}</span>`
        ).join('') || '—';
        html += `<tr>
            <td><code style="font-size:11px;">${s.student_id||''}</code></td>
            <td><strong>${s.first_name} ${s.last_name}</strong></td>
            <td>${s.course||'—'}</td>
            <td>${s.year_level||'—'}</td>
            <td>${skillTags}</td>
            <td style="font-size:12px;">${s.affiliations||'—'}</td>
            <td><span class="badge ${badge}">${s.enrollment_status}</span></td>
        </tr>`;
    });
    html += '</tbody></table></div>';
    el.innerHTML = html;
}

// ── QUERY 1: Filter by Skill ──────────────────────────────────────────────────
function runQuery1() {
    const kw = document.getElementById('q1Skill').value.trim().toLowerCase();
    if (!kw) { document.getElementById('q1Results').innerHTML = '<p class="query-no-results">Please enter a skill to search.</p>'; return; }
    const res = ALL_STUDENTS.filter(s => (s.skills||'').toLowerCase().includes(kw));
    renderResults('q1Results', res, `No students found with skill "${kw}".`);
}
function clearQuery1() {
    document.getElementById('q1Skill').value = '';
    document.getElementById('q1Results').innerHTML = '';
}

// ── QUERY 2: Filter by Affiliation ───────────────────────────────────────────
function runQuery2() {
    const kw = document.getElementById('q2Affil').value.trim().toLowerCase();
    if (!kw) { document.getElementById('q2Results').innerHTML = '<p class="query-no-results">Please enter an affiliation to search.</p>'; return; }
    const res = ALL_STUDENTS.filter(s => (s.affiliations||'').toLowerCase().includes(kw));
    renderResults('q2Results', res, `No students found with affiliation "${kw}".`);
}
function clearQuery2() {
    document.getElementById('q2Affil').value = '';
    document.getElementById('q2Results').innerHTML = '';
}

// ── QUERY 3: Filter by Year Level + Status ───────────────────────────────────
function runQuery3() {
    const yr  = document.getElementById('q3Year').value;
    const st  = document.getElementById('q3Status').value;
    if (!yr && !st) { document.getElementById('q3Results').innerHTML = '<p class="query-no-results">Please select at least one filter.</p>'; return; }
    const res = ALL_STUDENTS.filter(s =>
        (!yr || s.year_level === yr) && (!st || s.enrollment_status === st)
    );
    renderResults('q3Results', res, 'No students found matching those filters.');
}
function clearQuery3() {
    document.getElementById('q3Year').value = '';
    document.getElementById('q3Status').value = '';
    document.getElementById('q3Results').innerHTML = '';
}

// ── Edit Student ─────────────────────────────────────────────────────────────
function editStudent(s) {
    // Personal
    document.getElementById('e_student_id').value = s.student_id;
    document.getElementById('e_first_name').value = s.first_name;
    document.getElementById('e_last_name').value  = s.last_name;
    document.getElementById('e_email').value      = s.email || '';
    document.getElementById('e_phone').value      = s.phone || '';
    document.getElementById('e_dob').value        = s.date_of_birth || '';
    document.getElementById('e_address').value    = s.address || '';
    document.getElementById('e_ec').value         = s.emergency_contact || '';
    document.getElementById('e_ep').value         = s.emergency_phone || '';
    setSelectVal('e_gender', s.gender);
    setSelectVal('e_status', s.enrollment_status);
    // Academic
    document.getElementById('e_gpa').value              = s.gpa || '';
    document.getElementById('e_course').value           = s.course || '';
    document.getElementById('e_section').value          = s.section || '';
    document.getElementById('e_academic_history').value = s.academic_history || '';
    setSelectVal('e_year_level', s.year_level);
    // Extra-curricular
    document.getElementById('e_skills').value      = s.skills || '';
    document.getElementById('e_activities').value  = s.non_academic_activities || '';
    document.getElementById('e_affiliations').value= s.affiliations || '';
    // Violations
    document.getElementById('e_violations').value  = s.violations || '';

    document.getElementById('editForm').action = 'student_profile.php?action=edit&id=' + s.id;
    // Reset tabs to first
    switchTabDirect('edit','personal');
    openModal('editModal');
}

function switchTabDirect(prefix, name) {
    document.querySelectorAll(`[id^="${prefix}-tab-"]`).forEach(el => el.style.display = 'none');
    document.getElementById(`${prefix}-tab-${name}`).style.display = '';
    document.querySelectorAll(`#${prefix}Modal .tab-btn`).forEach(b => b.classList.remove('active'));
    const btn = document.querySelector(`#${prefix}Modal .tab-btn[onclick*="${name}"]`);
    if (btn) btn.classList.add('active');
}

// ── View Student ─────────────────────────────────────────────────────────────
function viewStudent(s) {
    const statusMap = {'Active':'badge-success','Inactive':'badge-danger','Graduated':'badge-info','LOA':'badge-warning'};
    const badge = statusMap[s.enrollment_status] || 'badge-default';

    const skillTags = (s.skills||'').split(',').filter(x=>x.trim()).map(sk=>
        `<span style="display:inline-block;background:var(--cream);border:1px solid var(--border);border-radius:12px;padding:2px 10px;font-size:12px;margin:2px;">${sk.trim()}</span>`
    ).join('') || '<span style="color:var(--muted);">—</span>';

    const hasViolations = s.violations && s.violations.trim() !== '';

    document.getElementById('viewContent').innerHTML = `
        <div style="display:flex;gap:16px;align-items:center;margin-bottom:22px;">
            <div class="profile-avatar">${(s.first_name[0]+s.last_name[0]).toUpperCase()}</div>
            <div>
                <h2 style="font-family:'Playfair Display',serif;font-size:20px;color:var(--navy);">${s.first_name} ${s.last_name}</h2>
                <p style="color:var(--muted);font-size:13px;">${s.student_id} &nbsp;·&nbsp; ${s.course||'—'}</p>
                <span class="badge ${badge}" style="margin-top:4px;">${s.enrollment_status}</span>
            </div>
        </div>

        <p class="section-label">📋 Personal Information</p>
        <div class="detail-grid">
            <div class="detail-item"><label>Email</label><p>${s.email||'—'}</p></div>
            <div class="detail-item"><label>Phone</label><p>${s.phone||'—'}</p></div>
            <div class="detail-item"><label>Date of Birth</label><p>${s.date_of_birth||'—'}</p></div>
            <div class="detail-item"><label>Gender</label><p>${s.gender||'—'}</p></div>
            <div class="detail-item full"><label>Address</label><p>${s.address||'—'}</p></div>
            <div class="detail-item"><label>Emergency Contact</label><p>${s.emergency_contact||'—'}</p></div>
            <div class="detail-item"><label>Emergency Phone</label><p>${s.emergency_phone||'—'}</p></div>
        </div>

        <p class="section-label">📚 Academic Information</p>
        <div class="detail-grid">
            <div class="detail-item"><label>Course</label><p>${s.course||'—'}</p></div>
            <div class="detail-item"><label>Year Level</label><p>${s.year_level||'—'}</p></div>
            <div class="detail-item"><label>Section</label><p>${s.section||'—'}</p></div>
            <div class="detail-item"><label>GPA</label><p style="font-weight:700;color:var(--navy);">${parseFloat(s.gpa||0).toFixed(2)}</p></div>
            <div class="detail-item full"><label>Academic History</label><p style="white-space:pre-wrap;">${s.academic_history||'—'}</p></div>
        </div>

        <p class="section-label">🏅 Skills</p>
        <div style="margin-bottom:12px;">${skillTags}</div>

        <p class="section-label">🎯 Non-Academic Activities</p>
        <p style="font-size:13px;white-space:pre-wrap;margin-bottom:12px;">${s.non_academic_activities||'—'}</p>

        <p class="section-label">🏛️ Affiliations / Organizations</p>
        <p style="font-size:13px;white-space:pre-wrap;margin-bottom:12px;">${s.affiliations||'—'}</p>

        ${hasViolations ? `
        <p class="section-label" style="color:#c0392b;">⚠️ Violations / Disciplinary Records</p>
        <div style="background:#fff5f5;border:1px solid #fcc;border-radius:8px;padding:12px;font-size:13px;white-space:pre-wrap;color:#c0392b;">${s.violations}</div>
        ` : `
        <p class="section-label">⚠️ Violations / Disciplinary Records</p>
        <p style="font-size:13px;color:var(--muted);">✅ No violations on record.</p>
        `}
    `;
    openModal('viewModal');
}

function setSelectVal(id, val) {
    const sel = document.getElementById(id);
    if (!sel || !val) return;
    for (let opt of sel.options) { if (opt.value === val || opt.text === val) { sel.value = opt.value; break; } }
}
</script>

<?php renderFooter(); ?>