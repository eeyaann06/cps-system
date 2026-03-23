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
    $fields = ['student_id','first_name','last_name','email','phone','date_of_birth','gender','address','year_level','course','section','enrollment_status','gpa','emergency_contact','emergency_phone'];
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
    $student = $pdo->prepare("SELECT * FROM students WHERE id=?")->execute([$id]) ?
        $pdo->prepare("SELECT * FROM students WHERE id=?")->execute([$id]) : null;
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id=?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();
}

$students = $pdo->query("SELECT * FROM students ORDER BY last_name, first_name")->fetchAll();
$courses = $pdo->query("SELECT DISTINCT course FROM students WHERE course != '' ORDER BY course")->fetchAll(PDO::FETCH_COLUMN);

renderHeader('Student Profiles', 'students');
flash();
?>

<div class="toolbar">
    <input class="search-input" id="studentSearch" type="text" placeholder="🔍 Search students by name, ID, course...">
    <select id="filterStatus" onchange="filterTable()" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;">
        <option value="">All Status</option>
        <option>Active</option>
        <option>Inactive</option>
        <option>Graduated</option>
        <option>LOA</option>
    </select>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Student</button>
</div>

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
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($students)): ?>
            <tr><td colspan="7"><div class="empty-state"><div class="icon">🎓</div><p>No students found. Add your first student!</p></div></td></tr>
            <?php else: foreach ($students as $s): ?>
            <tr data-status="<?= e($s['enrollment_status']) ?>">
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

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>+ Add New Student</h3>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <form method="POST" action="student_profile.php">
        <div class="modal-body">
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
                    <label>GPA</label>
                    <input type="number" name="gpa" step="0.01" min="1" max="5" value="1.00">
                </div>
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
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Student</button>
        </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>✏️ Edit Student</h3>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <form method="POST" id="editForm">
        <input type="hidden" name="_id" id="edit_id">
        <div class="modal-body">
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
                    <label>GPA</label>
                    <input type="number" name="gpa" id="e_gpa" step="0.01" min="1" max="5">
                </div>
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
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Student</button>
        </div>
        </form>
    </div>
</div>

<!-- VIEW MODAL -->
<div class="modal-overlay" id="viewModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>👁 Student Profile</h3>
            <button class="modal-close" onclick="closeModal('viewModal')">✕</button>
        </div>
        <div class="modal-body" id="viewContent"></div>
    </div>
</div>

<script>
tableSearch('studentSearch','studentTable');

function filterTable() {
    const val = document.getElementById('filterStatus').value.toLowerCase();
    document.querySelectorAll('#studentTable tbody tr').forEach(row => {
        row.style.display = (!val || row.dataset.status?.toLowerCase() === val) ? '' : 'none';
    });
}

function editStudent(s) {
    document.getElementById('e_student_id').value = s.student_id;
    document.getElementById('e_first_name').value = s.first_name;
    document.getElementById('e_last_name').value  = s.last_name;
    document.getElementById('e_email').value      = s.email || '';
    document.getElementById('e_phone').value      = s.phone || '';
    document.getElementById('e_dob').value        = s.date_of_birth || '';
    document.getElementById('e_gpa').value        = s.gpa || '';
    document.getElementById('e_course').value     = s.course || '';
    document.getElementById('e_section').value    = s.section || '';
    document.getElementById('e_address').value    = s.address || '';
    document.getElementById('e_ec').value         = s.emergency_contact || '';
    document.getElementById('e_ep').value         = s.emergency_phone || '';
    setSelectVal('e_gender', s.gender);
    setSelectVal('e_year_level', s.year_level);
    setSelectVal('e_status', s.enrollment_status);
    document.getElementById('editForm').action = 'student_profile.php?action=edit&id=' + s.id;
    openModal('editModal');
}

function viewStudent(s) {
    const statusMap = {'Active':'badge-success','Inactive':'badge-danger','Graduated':'badge-info','LOA':'badge-warning'};
    const badge = statusMap[s.enrollment_status] || 'badge-default';
    document.getElementById('viewContent').innerHTML = `
        <div style="display:flex;gap:16px;align-items:center;margin-bottom:22px;">
            <div class="profile-avatar">${(s.first_name[0]+s.last_name[0]).toUpperCase()}</div>
            <div>
                <h2 style="font-family:'Playfair Display',serif;font-size:20px;color:var(--navy);">${s.first_name} ${s.last_name}</h2>
                <p style="color:var(--muted);font-size:13px;">${s.student_id} &nbsp;·&nbsp; ${s.course}</p>
                <span class="badge ${badge}" style="margin-top:4px;">${s.enrollment_status}</span>
            </div>
        </div>
        <div class="detail-grid">
            <div class="detail-item"><label>Email</label><p>${s.email||'—'}</p></div>
            <div class="detail-item"><label>Phone</label><p>${s.phone||'—'}</p></div>
            <div class="detail-item"><label>Date of Birth</label><p>${s.date_of_birth||'—'}</p></div>
            <div class="detail-item"><label>Gender</label><p>${s.gender||'—'}</p></div>
            <div class="detail-item"><label>Year Level</label><p>${s.year_level||'—'}</p></div>
            <div class="detail-item"><label>Section</label><p>${s.section||'—'}</p></div>
            <div class="detail-item"><label>GPA</label><p style="font-weight:700;color:var(--navy);">${parseFloat(s.gpa||0).toFixed(2)}</p></div>
            <div class="detail-item"><label>Enrollment Status</label><p>${s.enrollment_status||'—'}</p></div>
            <div class="detail-item full"><label>Address</label><p>${s.address||'—'}</p></div>
            <div class="detail-item"><label>Emergency Contact</label><p>${s.emergency_contact||'—'}</p></div>
            <div class="detail-item"><label>Emergency Phone</label><p>${s.emergency_phone||'—'}</p></div>
        </div>
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
