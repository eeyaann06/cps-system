<?php
require_once 'config.php';
require_once 'includes/layout.php';
requireLogin();

$pdo = getDB();
$action = $_GET['action'] ?? 'list';
$id     = intval($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    $pdo->prepare("DELETE FROM faculty WHERE id=?")->execute([$id]);
    redirect('faculty_profile.php', 'Faculty member deleted.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['faculty_id','first_name','last_name','email','phone','date_of_birth','gender','address','department','position','specialization','employment_type','hire_date','bio'];
    $data = [];
    foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');

    if (!$data['faculty_id'] || !$data['first_name'] || !$data['last_name']) {
        $_SESSION['flash'] = ['msg' => 'Faculty ID, first name and last name are required.', 'type' => 'error'];
    } else {
        if ($id) {
            $set = implode(', ', array_map(fn($f) => "$f=?", $fields));
            $stmt = $pdo->prepare("UPDATE faculty SET $set WHERE id=?");
            $stmt->execute([...array_values($data), $id]);
            redirect('faculty_profile.php', 'Faculty updated successfully.');
        } else {
            $cols = implode(',', $fields);
            $ph   = implode(',', array_fill(0, count($fields), '?'));
            $pdo->prepare("INSERT INTO faculty ($cols) VALUES ($ph)")->execute(array_values($data));
            redirect('faculty_profile.php', 'Faculty added successfully.');
        }
    }
}

$faculty = $pdo->query("SELECT * FROM faculty ORDER BY last_name, first_name")->fetchAll();

renderHeader('Faculty Profiles', 'faculty');
flash();
?>

<div class="toolbar">
    <input class="search-input" id="facSearch" type="text" placeholder="🔍 Search by name, department, specialization...">
    <select id="filterDept" onchange="filterFaculty()" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;">
        <option value="">All Departments</option>
        <?php
        $depts = $pdo->query("SELECT DISTINCT department FROM faculty WHERE department!='' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($depts as $d) echo "<option>" . e($d) . "</option>";
        ?>
    </select>
    <button class="btn btn-primary" onclick="openModal('addFacModal')">+ Add Faculty</button>
</div>

<div class="card">
    <div class="card-header">
        <h3>👩‍🏫 Faculty Records</h3>
        <span style="font-size:13px; color:var(--muted);"><?= count($faculty) ?> members</span>
    </div>
    <div class="tbl-wrap">
        <table id="facTable">
            <thead>
                <tr>
                    <th>Faculty ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Specialization</th>
                    <th>Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($faculty)): ?>
            <tr><td colspan="7"><div class="empty-state"><div class="icon">👩‍🏫</div><p>No faculty found. Add your first faculty member!</p></div></td></tr>
            <?php else: foreach ($faculty as $f): ?>
            <tr data-dept="<?= e($f['department']) ?>">
                <td><code style="font-size:12px; background:var(--cream); padding:2px 7px; border-radius:4px;"><?= e($f['faculty_id']) ?></code></td>
                <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div class="profile-avatar" style="width:36px;height:36px;font-size:13px;background:var(--navy-mid);"><?= strtoupper(substr($f['first_name'],0,1).substr($f['last_name'],0,1)) ?></div>
                        <div>
                            <strong><?= e($f['first_name'].' '.$f['last_name']) ?></strong>
                            <p style="font-size:11.5px; color:var(--muted);"><?= e($f['email']) ?></p>
                        </div>
                    </div>
                </td>
                <td><?= e($f['department']) ?></td>
                <td><?= e($f['position']) ?></td>
                <td><?= e($f['specialization']) ?></td>
                <td>
                    <span class="badge <?= $f['employment_type']==='Full-Time'?'badge-success':'badge-warning' ?>"><?= e($f['employment_type']) ?></span>
                </td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button class="btn btn-outline btn-sm" onclick='viewFaculty(<?= json_encode($f) ?>)'>👁 View</button>
                        <button class="btn btn-primary btn-sm" onclick='editFaculty(<?= json_encode($f) ?>)'>✏️ Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="confirmDelete('faculty_profile.php?action=delete&id=<?= $f['id'] ?>', '<?= e($f['first_name'].' '.$f['last_name']) ?>')">🗑</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addFacModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>+ Add Faculty Member</h3>
            <button class="modal-close" onclick="closeModal('addFacModal')">✕</button>
        </div>
        <form method="POST" action="faculty_profile.php">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>Faculty ID *</label>
                    <input type="text" name="faculty_id" placeholder="FAC-001" required>
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
                    <label>Hire Date</label>
                    <input type="date" name="hire_date">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department" placeholder="College of Computing">
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="position" placeholder="Associate Professor">
                </div>
                <div class="form-group">
                    <label>Specialization</label>
                    <input type="text" name="specialization">
                </div>
                <div class="form-group">
                    <label>Employment Type</label>
                    <select name="employment_type">
                        <option>Full-Time</option><option>Part-Time</option><option>Adjunct</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Address</label>
                    <input type="text" name="address">
                </div>
                <div class="form-group full">
                    <label>Biography / Profile</label>
                    <textarea name="bio" placeholder="Brief professional biography..."></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('addFacModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Faculty</button>
        </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editFacModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>✏️ Edit Faculty Member</h3>
            <button class="modal-close" onclick="closeModal('editFacModal')">✕</button>
        </div>
        <form method="POST" id="editFacForm">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>Faculty ID *</label>
                    <input type="text" name="faculty_id" id="ef_fid" required>
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" id="ef_gender"><option>Male</option><option>Female</option><option>Prefer not to say</option></select>
                </div>
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" id="ef_fn" required>
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" id="ef_ln" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="ef_email">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" id="ef_phone">
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" id="ef_dob">
                </div>
                <div class="form-group">
                    <label>Hire Date</label>
                    <input type="date" name="hire_date" id="ef_hire">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department" id="ef_dept">
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="position" id="ef_pos">
                </div>
                <div class="form-group">
                    <label>Specialization</label>
                    <input type="text" name="specialization" id="ef_spec">
                </div>
                <div class="form-group">
                    <label>Employment Type</label>
                    <select name="employment_type" id="ef_type">
                        <option>Full-Time</option><option>Part-Time</option><option>Adjunct</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Address</label>
                    <input type="text" name="address" id="ef_addr">
                </div>
                <div class="form-group full">
                    <label>Biography</label>
                    <textarea name="bio" id="ef_bio"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('editFacModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Faculty</button>
        </div>
        </form>
    </div>
</div>

<!-- VIEW MODAL -->
<div class="modal-overlay" id="viewFacModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>👁 Faculty Profile</h3>
            <button class="modal-close" onclick="closeModal('viewFacModal')">✕</button>
        </div>
        <div class="modal-body" id="viewFacContent"></div>
    </div>
</div>

<script>
tableSearch('facSearch','facTable');

function filterFaculty() {
    const val = document.getElementById('filterDept').value.toLowerCase();
    document.querySelectorAll('#facTable tbody tr').forEach(row => {
        row.style.display = (!val || row.dataset.dept?.toLowerCase().includes(val)) ? '' : 'none';
    });
}

function editFaculty(f) {
    const map = {ef_fid:'faculty_id',ef_fn:'first_name',ef_ln:'last_name',ef_email:'email',
                 ef_phone:'phone',ef_dob:'date_of_birth',ef_hire:'hire_date',ef_dept:'department',
                 ef_pos:'position',ef_spec:'specialization',ef_addr:'address',ef_bio:'bio'};
    for (const [id, key] of Object.entries(map)) {
        const el = document.getElementById(id);
        if (el) el.value = f[key] || '';
    }
    setSelectVal('ef_gender', f.gender);
    setSelectVal('ef_type', f.employment_type);
    document.getElementById('editFacForm').action = 'faculty_profile.php?action=edit&id=' + f.id;
    openModal('editFacModal');
}

function viewFaculty(f) {
    document.getElementById('viewFacContent').innerHTML = `
        <div style="display:flex;gap:16px;align-items:center;margin-bottom:22px;">
            <div class="profile-avatar" style="background:var(--navy-mid);">${(f.first_name[0]+f.last_name[0]).toUpperCase()}</div>
            <div>
                <h2 style="font-family:'Playfair Display',serif;font-size:20px;color:var(--navy);">${f.first_name} ${f.last_name}</h2>
                <p style="color:var(--muted);font-size:13px;">${f.faculty_id} &nbsp;·&nbsp; ${f.position}</p>
                <p style="color:var(--muted);font-size:12px;">${f.department}</p>
                <span class="badge ${f.employment_type==='Full-Time'?'badge-success':'badge-warning'}" style="margin-top:4px;">${f.employment_type}</span>
            </div>
        </div>
        ${f.bio ? `<div style="background:var(--cream);padding:14px;border-radius:8px;font-size:13.5px;color:var(--text);line-height:1.6;margin-bottom:18px;">${f.bio}</div>` : ''}
        <div class="detail-grid">
            <div class="detail-item"><label>Email</label><p>${f.email||'—'}</p></div>
            <div class="detail-item"><label>Phone</label><p>${f.phone||'—'}</p></div>
            <div class="detail-item"><label>Date of Birth</label><p>${f.date_of_birth||'—'}</p></div>
            <div class="detail-item"><label>Gender</label><p>${f.gender||'—'}</p></div>
            <div class="detail-item"><label>Hire Date</label><p>${f.hire_date||'—'}</p></div>
            <div class="detail-item"><label>Specialization</label><p>${f.specialization||'—'}</p></div>
            <div class="detail-item full"><label>Address</label><p>${f.address||'—'}</p></div>
        </div>
    `;
    openModal('viewFacModal');
}

function setSelectVal(id, val) {
    const sel = document.getElementById(id);
    if (!sel || !val) return;
    for (let opt of sel.options) { if (opt.value === val || opt.text === val) { sel.value = opt.value; break; } }
}
</script>

<?php renderFooter(); ?>