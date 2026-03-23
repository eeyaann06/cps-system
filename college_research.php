<?php
require_once 'config.php';
require_once 'includes/layout.php';
requireLogin();

$pdo    = getDB();
$action = $_GET['action'] ?? 'list';
$id     = intval($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    $pdo->prepare("DELETE FROM college_research WHERE id=?")->execute([$id]);
    redirect('college_research.php', 'Research deleted.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['title','author','co_authors','abstract','keywords','research_type','department','year_published','status','adviser'];
    $data = [];
    foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '') ?: null;
    if (!$data['title'] || !$data['author']) {
        $_SESSION['flash'] = ['msg'=>'Title and author are required.','type'=>'error'];
    } else {
        if ($id) {
            $set = implode(', ', array_map(fn($f) => "$f=?", $fields));
            $pdo->prepare("UPDATE college_research SET $set WHERE id=?")->execute([...array_values($data),$id]);
            redirect('college_research.php','Research updated.');
        } else {
            $cols = implode(',', $fields);
            $ph   = implode(',', array_fill(0, count($fields), '?'));
            $pdo->prepare("INSERT INTO college_research ($cols) VALUES ($ph)")->execute(array_values($data));
            redirect('college_research.php','Research added.');
        }
    }
}

$research = $pdo->query("SELECT * FROM college_research ORDER BY year_published DESC, title")->fetchAll();

renderHeader('College Research', 'research');
flash();
?>

<div class="toolbar">
    <input class="search-input" id="resSearch" type="text" placeholder="🔍 Search by title, author, keywords...">
    <select id="filterType" onchange="filterRes()" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;">
        <option value="">All Types</option>
        <option>Thesis</option><option>Dissertation</option>
        <option>Research Paper</option><option>Capstone</option>
    </select>
    <button class="btn btn-primary" onclick="openModal('addResModal')">+ Add Research</button>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;">
    <?php
    $types = ['Thesis','Dissertation','Research Paper','Capstone'];
    $ticons = ['📜','🎓','📄','🛠️'];
    foreach (array_keys($types) as $i):
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM college_research WHERE research_type=?")->execute([$types[$i]]) ?
            $pdo->query("SELECT COUNT(*) FROM college_research WHERE research_type='{$types[$i]}'")->fetchColumn() : 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM college_research WHERE research_type=?");
        $stmt->execute([$types[$i]]);
        $cnt = $stmt->fetchColumn();
    ?>
    <div class="stat-card">
        <div class="stat-icon <?= ['navy','gold','blue','green'][$i] ?>"><?= $ticons[$i] ?></div>
        <div class="stat-info"><strong><?= $cnt ?></strong><span><?= $types[$i] ?></span></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3>🔬 Research Repository</h3>
        <span style="font-size:13px;color:var(--muted);"><?= count($research) ?> papers</span>
    </div>
    <div class="tbl-wrap">
        <table id="resTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Author(s)</th>
                    <th>Department</th>
                    <th>Type</th>
                    <th>Year</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($research)): ?>
            <tr><td colspan="8"><div class="empty-state"><div class="icon">🔬</div><p>No research papers yet.</p></div></td></tr>
            <?php else: foreach ($research as $i => $r): ?>
            <tr data-type="<?= e($r['research_type']) ?>">
                <td style="color:var(--muted);font-size:12px;"><?= $i+1 ?></td>
                <td>
                    <strong style="font-size:13px;"><?= e($r['title']) ?></strong>
                    <?php if ($r['keywords']): ?>
                    <p style="font-size:11px;color:var(--muted);margin-top:3px;">🏷 <?= e(mb_strimwidth($r['keywords'],0,60,'...')) ?></p>
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?= e($r['author']) ?></strong>
                    <?php if ($r['co_authors']): ?>
                    <p style="font-size:11.5px;color:var(--muted);"><?= e($r['co_authors']) ?></p>
                    <?php endif; ?>
                </td>
                <td style="font-size:12.5px;"><?= e($r['department']) ?></td>
                <td><span class="badge badge-info"><?= e($r['research_type']) ?></span></td>
                <td><strong><?= e($r['year_published']) ?></strong></td>
                <td>
                    <span class="badge <?= match($r['status']) {
                        'Completed'   => 'badge-success',
                        'Ongoing'     => 'badge-warning',
                        'Proposed'    => 'badge-info',
                        'Published'   => 'badge-default',
                        default       => 'badge-default'
                    } ?>"><?= e($r['status']) ?></span>
                </td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <button class="btn btn-outline btn-sm" onclick='viewRes(<?= json_encode($r) ?>)'>👁</button>
                        <button class="btn btn-primary btn-sm" onclick='editRes(<?= json_encode($r) ?>)'>✏️</button>
                        <button class="btn btn-danger btn-sm" onclick="confirmDelete('college_research.php?action=delete&id=<?= $r['id'] ?>', '<?= e($r['title']) ?>')">🗑</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addResModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>+ Add Research Paper</h3>
            <button class="modal-close" onclick="closeModal('addResModal')">✕</button>
        </div>
        <form method="POST" action="college_research.php">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Title *</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Author(s) *</label>
                    <input type="text" name="author" required>
                </div>
                <div class="form-group">
                    <label>Co-Authors</label>
                    <input type="text" name="co_authors">
                </div>
                <div class="form-group">
                    <label>Research Type</label>
                    <select name="research_type">
                        <option>Thesis</option><option>Dissertation</option>
                        <option>Research Paper</option><option>Capstone</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department">
                </div>
                <div class="form-group">
                    <label>Year Published</label>
                    <input type="number" name="year_published" value="<?= date('Y') ?>" min="1990" max="<?= date('Y') ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option>Completed</option><option>Ongoing</option>
                        <option>Proposed</option><option>Published</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Adviser</label>
                    <input type="text" name="adviser">
                </div>
                <div class="form-group full">
                    <label>Keywords</label>
                    <input type="text" name="keywords" placeholder="keyword1, keyword2, keyword3">
                </div>
                <div class="form-group full">
                    <label>Abstract</label>
                    <textarea name="abstract" rows="4"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('addResModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Research</button>
        </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editResModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>✏️ Edit Research</h3>
            <button class="modal-close" onclick="closeModal('editResModal')">✕</button>
        </div>
        <form method="POST" id="editResForm">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Title *</label>
                    <input type="text" name="title" id="er_title" required>
                </div>
                <div class="form-group">
                    <label>Author(s) *</label>
                    <input type="text" name="author" id="er_author" required>
                </div>
                <div class="form-group">
                    <label>Co-Authors</label>
                    <input type="text" name="co_authors" id="er_coauth">
                </div>
                <div class="form-group">
                    <label>Research Type</label>
                    <select name="research_type" id="er_type">
                        <option>Thesis</option><option>Dissertation</option>
                        <option>Research Paper</option><option>Capstone</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department" id="er_dept">
                </div>
                <div class="form-group">
                    <label>Year Published</label>
                    <input type="number" name="year_published" id="er_year" min="1990" max="<?= date('Y') ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="er_status">
                        <option>Completed</option><option>Ongoing</option>
                        <option>Proposed</option><option>Published</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Adviser</label>
                    <input type="text" name="adviser" id="er_adv">
                </div>
                <div class="form-group full">
                    <label>Keywords</label>
                    <input type="text" name="keywords" id="er_kw">
                </div>
                <div class="form-group full">
                    <label>Abstract</label>
                    <textarea name="abstract" id="er_abstract" rows="4"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('editResModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Research</button>
        </div>
        </form>
    </div>
</div>

<!-- VIEW MODAL -->
<div class="modal-overlay" id="viewResModal">
    <div class="modal modal-lg">
        <div class="modal-header"><h3>📄 Research Details</h3>
            <button class="modal-close" onclick="closeModal('viewResModal')">✕</button>
        </div>
        <div class="modal-body" id="viewResContent"></div>
    </div>
</div>

<script>
tableSearch('resSearch','resTable');

function filterRes() {
    const val = document.getElementById('filterType').value.toLowerCase();
    document.querySelectorAll('#resTable tbody tr').forEach(row => {
        row.style.display = (!val || row.dataset.type?.toLowerCase().includes(val)) ? '' : 'none';
    });
}

function editRes(r) {
    document.getElementById('er_title').value    = r.title;
    document.getElementById('er_author').value   = r.author||'';
    document.getElementById('er_coauth').value   = r.co_authors||'';
    document.getElementById('er_dept').value     = r.department||'';
    document.getElementById('er_year').value     = r.year_published||'';
    document.getElementById('er_adv').value      = r.adviser||'';
    document.getElementById('er_kw').value       = r.keywords||'';
    document.getElementById('er_abstract').value = r.abstract||'';
    setSelectVal('er_type', r.research_type);
    setSelectVal('er_status', r.status);
    document.getElementById('editResForm').action = 'college_research.php?action=edit&id=' + r.id;
    openModal('editResModal');
}

function viewRes(r) {
    const keywords = (r.keywords||'').split(',').map(k => `<span class="badge badge-default" style="margin:2px;">${k.trim()}</span>`).join('');
    document.getElementById('viewResContent').innerHTML = `
        <div style="background:var(--navy);padding:20px 24px;border-radius:10px;margin-bottom:20px;">
            <span class="badge badge-info">${r.research_type}</span>
            <h2 style="font-family:'Playfair Display',serif;font-size:17px;color:white;margin-top:8px;line-height:1.4;">${r.title}</h2>
            <p style="color:rgba(255,255,255,0.6);font-size:12.5px;margin-top:6px;">By ${r.author}${r.co_authors ? ' · '+r.co_authors : ''}</p>
        </div>
        ${r.abstract ? `<div style="margin-bottom:20px;"><p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin-bottom:6px;">Abstract</p><p style="font-size:13.5px;line-height:1.7;color:var(--text);">${r.abstract}</p></div>` : ''}
        <div style="margin-bottom:16px;">${keywords}</div>
        <div class="detail-grid">
            <div class="detail-item"><label>Department</label><p>${r.department||'—'}</p></div>
            <div class="detail-item"><label>Year Published</label><p>${r.year_published||'—'}</p></div>
            <div class="detail-item"><label>Adviser</label><p>${r.adviser||'—'}</p></div>
            <div class="detail-item"><label>Status</label><p>${r.status}</p></div>
        </div>
    `;
    openModal('viewResModal');
}

function setSelectVal(id, val) {
    const sel = document.getElementById(id);
    if (!sel || !val) return;
    for (let opt of sel.options) { if (opt.value===val||opt.text===val) { sel.value=opt.value; break; } }
}
</script>

<?php renderFooter(); ?>
