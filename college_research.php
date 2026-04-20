<?php
require_once 'config.php';
require_once 'includes/layout.php';
requireLogin();

$pdo    = getDB();
$action = $_GET['action'] ?? 'list';
$id     = intval($_GET['id'] ?? 0);

// ── Upload directory (create if missing) ──────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/uploads/research/');
define('UPLOAD_URL', 'uploads/research/');
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// ── Helper: handle PDF upload ─────────────────────────────────────────────────
function handlePdfUpload(string $fieldName): ?string
{
    if (empty($_FILES[$fieldName]['name'])) return null;

    $file = $_FILES[$fieldName];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($ext !== 'pdf') {
        $_SESSION['flash'] = ['msg' => 'Only PDF files are allowed.', 'type' => 'error'];
        return false;                          // false = error (distinguish from null = no file)
    }
    if ($file['size'] > 20 * 1024 * 1024) {   // 20 MB limit
        $_SESSION['flash'] = ['msg' => 'PDF file must be under 20 MB.', 'type' => 'error'];
        return false;
    }

    $filename = uniqid('res_', true) . '.pdf';
    $dest     = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $_SESSION['flash'] = ['msg' => 'Failed to save the uploaded file.', 'type' => 'error'];
        return false;
    }

    return UPLOAD_URL . $filename;
}

// ── Delete ────────────────────────────────────────────────────────────────────
if ($action === 'delete' && $id) {
    // Also remove the physical PDF file
    $row = $pdo->prepare("SELECT file_path FROM college_research WHERE id=?");
    $row->execute([$id]);
    $old = $row->fetchColumn();
    if ($old && file_exists(__DIR__ . '/' . $old)) {
        unlink(__DIR__ . '/' . $old);
    }
    $pdo->prepare("DELETE FROM college_research WHERE id=?")->execute([$id]);
    redirect('college_research.php', 'Research deleted.');
}

// ── Create / Update ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['title','author','co_authors','abstract','keywords',
               'research_type','department','year_published','status','adviser'];
    $data = [];
    foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '') ?: null;

    if (!$data['title'] || !$data['author']) {
        $_SESSION['flash'] = ['msg' => 'Title and author are required.', 'type' => 'error'];
    } else {
        // Handle PDF upload
        $uploadedPath = handlePdfUpload('pdf_file');
        if ($uploadedPath === false) {
            // Error message already set; fall through to re-render page
        } elseif ($id) {
            // ── UPDATE ──────────────────────────────────────────────────────
            $set = implode(', ', array_map(fn($f) => "$f=?", $fields));
            $params = [...array_values($data), $id];

            if ($uploadedPath !== null) {
                // Delete old file before replacing
                $stmt = $pdo->prepare("SELECT file_path FROM college_research WHERE id=?");
                $stmt->execute([$id]);
                $oldPath = $stmt->fetchColumn();
                if ($oldPath && file_exists(__DIR__ . '/' . $oldPath)) {
                    unlink(__DIR__ . '/' . $oldPath);
                }
                $set    .= ', file_path=?';
                $params  = [...array_values($data), $uploadedPath, $id];
            }

            $pdo->prepare("UPDATE college_research SET $set WHERE id=?")->execute($params);
            redirect('college_research.php', 'Research updated.');
        } else {
            // ── INSERT ──────────────────────────────────────────────────────
            $allFields  = [...$fields, 'file_path'];
            $allValues  = [...array_values($data), $uploadedPath];
            $cols = implode(',', $allFields);
            $ph   = implode(',', array_fill(0, count($allFields), '?'));
            $pdo->prepare("INSERT INTO college_research ($cols) VALUES ($ph)")->execute($allValues);
            redirect('college_research.php', 'Research added.');
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
    $types  = ['Thesis','Dissertation','Research Paper','Capstone'];
    $ticons = ['📜','🎓','📄','🛠️'];
    foreach (array_keys($types) as $i):
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
                    <th>PDF</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($research)): ?>
            <tr><td colspan="9"><div class="empty-state"><div class="icon">🔬</div><p>No research papers yet.</p></div></td></tr>
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
                        'Completed' => 'badge-success',
                        'Ongoing'   => 'badge-warning',
                        'Proposed'  => 'badge-info',
                        'Published' => 'badge-default',
                        default     => 'badge-default'
                    } ?>"><?= e($r['status']) ?></span>
                </td>
                <!-- PDF column -->
                <td style="text-align:center;">
                    <?php if ($r['file_path']): ?>
                        <button class="btn btn-outline btn-sm"
                                title="View PDF"
                                onclick="openPdfViewer('<?= e($r['file_path']) ?>', '<?= e(addslashes($r['title'])) ?>')">
                            📑 PDF
                        </button>
                    <?php else: ?>
                        <span style="font-size:11px;color:var(--muted);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <button class="btn btn-outline btn-sm"
                                data-rec='<?= htmlspecialchars(json_encode($r, JSON_HEX_APOS|JSON_HEX_QUOT), ENT_QUOTES) ?>'
                                onclick="viewRes(JSON.parse(this.dataset.rec))">👁</button>
                        <button class="btn btn-primary btn-sm"
                                data-rec='<?= htmlspecialchars(json_encode($r, JSON_HEX_APOS|JSON_HEX_QUOT), ENT_QUOTES) ?>'
                                onclick="editRes(JSON.parse(this.dataset.rec))">✏️</button>
                        <button class="btn btn-danger btn-sm" onclick="confirmDelete('college_research.php?action=delete&id=<?= $r['id'] ?>', '<?= e(addslashes($r['title'])) ?>')">🗑</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!--  ADD MODAL                                                                 -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="addResModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>+ Add Research Paper</h3>
            <button class="modal-close" onclick="closeModal('addResModal')">✕</button>
        </div>
        <!-- enctype required for file upload -->
        <form method="POST" action="college_research.php" enctype="multipart/form-data">
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
                <!-- PDF Upload -->
                <div class="form-group full">
                    <label>Upload PDF <span style="font-size:11px;color:var(--muted);">(optional · max 20 MB)</span></label>
                    <input type="file" name="pdf_file" id="pdf_file" accept=".pdf"
                           style="display:none"
                           onchange="showFileName(this,'addFileLabel')">
                    <div class="pdf-upload-box" id="addDropZone"
                         onclick="document.getElementById('pdf_file').click()"
                         ondragover="event.preventDefault();this.classList.add('drag-over')"
                         ondragleave="this.classList.remove('drag-over')"
                         ondrop="handleDrop(event,'pdf_file','addDropZone','addFileLabel')">
                        <div style="text-align:center;">
                            <div style="font-size:32px;margin-bottom:6px;">📎</div>
                            <p id="addFileLabel" style="font-size:13px;color:var(--muted);margin:0;">
                                Click anywhere here or drag &amp; drop a PDF
                            </p>
                        </div>
                    </div>
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

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!--  EDIT MODAL                                                                -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="editResModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>✏️ Edit Research</h3>
            <button class="modal-close" onclick="closeModal('editResModal')">✕</button>
        </div>
        <form method="POST" id="editResForm" enctype="multipart/form-data">
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
                <!-- PDF Upload -->
                <div class="form-group full">
                    <label>Replace PDF <span style="font-size:11px;color:var(--muted);">(leave blank to keep existing)</span></label>
                    <!-- Current file indicator -->
                    <div id="er_current_pdf" style="display:none;margin-bottom:8px;padding:8px 12px;background:var(--light);border-radius:8px;font-size:12.5px;align-items:center;gap:8px;">
                        <span>📑</span>
                        <span id="er_pdf_name" style="flex:1;color:var(--text);">current file</span>
                        <button type="button" class="btn btn-outline btn-sm"
                                onclick="openPdfViewerById()">👁 View</button>
                    </div>
                    <input type="file" name="pdf_file" id="edit_pdf_file" accept=".pdf"
                           style="display:none"
                           onchange="showFileName(this,'editFileLabel')">
                    <div class="pdf-upload-box" id="editDropZone"
                         onclick="document.getElementById('edit_pdf_file').click()"
                         ondragover="event.preventDefault();this.classList.add('drag-over')"
                         ondragleave="this.classList.remove('drag-over')"
                         ondrop="handleDrop(event,'edit_pdf_file','editDropZone','editFileLabel')">
                        <div style="text-align:center;">
                            <div style="font-size:32px;margin-bottom:6px;">📎</div>
                            <p id="editFileLabel" style="font-size:13px;color:var(--muted);margin:0;">
                                Click anywhere here or drag &amp; drop a new PDF
                            </p>
                        </div>
                    </div>
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

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!--  VIEW MODAL                                                                -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="viewResModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>📄 Research Details</h3>
            <button class="modal-close" onclick="closeModal('viewResModal')">✕</button>
        </div>
        <div class="modal-body" id="viewResContent"></div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!--  PDF VIEWER MODAL                                                          -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="pdfViewerModal">
    <div class="modal" style="width:92vw;max-width:960px;height:90vh;display:flex;flex-direction:column;">
        <div class="modal-header" style="flex-shrink:0;">
            <h3 id="pdfViewerTitle" style="font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:80%;">📑 PDF Viewer</h3>
            <div style="display:flex;gap:8px;align-items:center;">
                <a id="pdfDownloadBtn" href="#" download
                   class="btn btn-outline btn-sm" style="text-decoration:none;">⬇ Download</a>
                <a id="pdfNewTabBtn" href="#" target="_blank"
                   class="btn btn-outline btn-sm" style="text-decoration:none;">↗ Open Tab</a>
                <button class="modal-close" onclick="closeModal('pdfViewerModal')">✕</button>
            </div>
        </div>
        <div style="flex:1;overflow:hidden;padding:0;">
            <iframe id="pdfViewerFrame"
                    src=""
                    style="width:100%;height:100%;border:none;border-radius:0 0 12px 12px;"
                    allow="fullscreen">
            </iframe>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!--  Upload-box styles (add to your stylesheet if preferred)                   -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<style>
.pdf-upload-box {
    border: 2px dashed var(--border);
    border-radius: 10px;
    padding: 28px 20px;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    background: var(--light);
}
.pdf-upload-box:hover,
.pdf-upload-box.drag-over {
    border-color: var(--primary);
    background: color-mix(in srgb, var(--primary) 6%, transparent);
}
</style>

<script>
// ── Search ────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('resSearch').addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('#resTable tbody tr').forEach(row => {
            row.style.display = (!q || row.textContent.toLowerCase().includes(q)) ? '' : 'none';
        });
    });
});

// ── Filter by type ────────────────────────────────────────────────────────────
function filterRes() {
    const val = document.getElementById('filterType').value.toLowerCase();
    document.querySelectorAll('#resTable tbody tr').forEach(row => {
        row.style.display = (!val || row.dataset.type?.toLowerCase().includes(val)) ? '' : 'none';
    });
}

// ── Edit modal ────────────────────────────────────────────────────────────────
let _editCurrentPdf = null;

function editRes(r) {
    document.getElementById('er_title').value    = r.title;
    document.getElementById('er_author').value   = r.author  || '';
    document.getElementById('er_coauth').value   = r.co_authors || '';
    document.getElementById('er_dept').value     = r.department || '';
    document.getElementById('er_year').value     = r.year_published || '';
    document.getElementById('er_adv').value      = r.adviser || '';
    document.getElementById('er_kw').value       = r.keywords || '';
    document.getElementById('er_abstract').value = r.abstract || '';
    setSelectVal('er_type',   r.research_type);
    setSelectVal('er_status', r.status);

    _editCurrentPdf = r.file_path || null;
    const pdfBox = document.getElementById('er_current_pdf');
    if (_editCurrentPdf) {
        pdfBox.style.display = 'flex';
        const fname = _editCurrentPdf.split('/').pop();
        document.getElementById('er_pdf_name').textContent = fname;
    } else {
        pdfBox.style.display = 'none';
    }

    // Reset new-file label
    document.getElementById('editFileLabel').textContent = 'Click anywhere here or drag & drop a new PDF';
    document.getElementById('edit_pdf_file').value = '';

    document.getElementById('editResForm').action = 'college_research.php?action=edit&id=' + r.id;
    openModal('editResModal');
}

function openPdfViewerById() {
    if (_editCurrentPdf) openPdfViewer(_editCurrentPdf, document.getElementById('er_title').value);
}

// ── View modal ────────────────────────────────────────────────────────────────
function viewRes(r) {
    const keywords = (r.keywords || '').split(',').map(
        k => `<span class="badge badge-default" style="margin:2px;">${k.trim()}</span>`
    ).join('');

    const pdfBtn = r.file_path
        ? `<button class="btn btn-primary btn-sm" style="margin-top:12px;"
               onclick="openPdfViewer('${r.file_path}', '${r.title.replace(/'/g,"\\'")}')">
               📑 View PDF
           </button>`
        : `<span style="font-size:12px;color:var(--muted);">No PDF attached</span>`;

    document.getElementById('viewResContent').innerHTML = `
        <div style="background:var(--navy);padding:20px 24px;border-radius:10px;margin-bottom:20px;">
            <span class="badge badge-info">${r.research_type}</span>
            <h2 style="font-family:'Playfair Display',serif;font-size:17px;color:white;margin-top:8px;line-height:1.4;">${r.title}</h2>
            <p style="color:rgba(255,255,255,0.6);font-size:12.5px;margin-top:6px;">
                By ${r.author}${r.co_authors ? ' · ' + r.co_authors : ''}
            </p>
        </div>
        ${r.abstract ? `<div style="margin-bottom:20px;">
            <p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin-bottom:6px;">Abstract</p>
            <p style="font-size:13.5px;line-height:1.7;color:var(--text);">${r.abstract}</p>
        </div>` : ''}
        <div style="margin-bottom:16px;">${keywords}</div>
        <div class="detail-grid">
            <div class="detail-item"><label>Department</label><p>${r.department || '—'}</p></div>
            <div class="detail-item"><label>Year Published</label><p>${r.year_published || '—'}</p></div>
            <div class="detail-item"><label>Adviser</label><p>${r.adviser || '—'}</p></div>
            <div class="detail-item"><label>Status</label><p>${r.status}</p></div>
        </div>
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
            <p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin-bottom:8px;">Full Document</p>
            ${pdfBtn}
        </div>
    `;
    openModal('viewResModal');
}

// ── PDF Viewer ────────────────────────────────────────────────────────────────
function openPdfViewer(filePath, title) {
    document.getElementById('pdfViewerTitle').textContent = '📑 ' + title;
    document.getElementById('pdfViewerFrame').src = filePath;
    document.getElementById('pdfDownloadBtn').href = filePath;
    document.getElementById('pdfNewTabBtn').href   = filePath;
    openModal('pdfViewerModal');
}

// ── Upload helpers ────────────────────────────────────────────────────────────
function showFileName(input, labelId) {
    const label = document.getElementById(labelId);
    if (input.files && input.files[0]) {
        const name = input.files[0].name;
        const size = (input.files[0].size / 1024 / 1024).toFixed(2);
        label.innerHTML = `✅ <strong>${name}</strong> (${size} MB)`;
    }
}

function handleDrop(event, inputId, zoneId, labelId) {
    event.preventDefault();
    document.getElementById(zoneId).classList.remove('drag-over');
    const file = event.dataTransfer.files[0];
    if (!file) return;
    if (!file.name.toLowerCase().endsWith('.pdf')) {
        alert('Only PDF files are allowed.');
        return;
    }
    const input = document.getElementById(inputId);
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    showFileName(input, labelId);
}

// ── Utility ───────────────────────────────────────────────────────────────────
function setSelectVal(id, val) {
    const sel = document.getElementById(id);
    if (!sel || !val) return;
    for (let opt of sel.options) {
        if (opt.value === val || opt.text === val) { sel.value = opt.value; break; }
    }
}
</script>

<?php renderFooter(); ?>