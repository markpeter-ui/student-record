<?php
require 'db.php';

$feedback = '';
$feedbackType = 'success';

// ============================================================
// CONTROLLER 1: CREATE / UPDATE (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $name       = trim($_POST['name'] ?? '');
    $program    = trim($_POST['program'] ?? '');
    $student_id = $_POST['student_id'] ?? '';

    if ($name === '' || $program === '') {
        $feedback = 'Name and Program are required.';
        $feedbackType = 'danger';
    } elseif (strlen($name) > 100 || strlen($program) > 100) {
        $feedback = 'Name and Program must be 100 characters or less.';
        $feedbackType = 'danger';
    } else {
        try {
            if (!empty($student_id) && is_numeric($student_id)) {
                $stmt = $db->prepare("UPDATE students SET name=?, program=? 
                                     WHERE id=? AND is_archived=0");
                $stmt->execute([$name, $program, $student_id]);
                $feedback = "Student #{$student_id} updated successfully!";
            } else {
                $stmt = $db->prepare("INSERT INTO students (name, program) VALUES (?, ?)");
                $stmt->execute([$name, $program]);
                $newId = $db->lastInsertId();
                $feedback = "Student '{$name}' added successfully (ID: {$newId})!";
            }
        } catch (PDOException $e) {
            $feedback = 'Database error: ' . $e->getMessage();
            $feedbackType = 'danger';
        }
    }
}

// ============================================================
// CONTROLLER 2: ARCHIVE (SOFT DELETE)
// ============================================================
if (isset($_GET['archive']) && is_numeric($_GET['archive'])) {
    $id = (int)$_GET['archive'];
    try {
        $stmt = $db->prepare("UPDATE students SET is_archived=1 
                             WHERE id=? AND is_archived=0");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            $feedback = "Student #{$id} archived. Record is kept in database.";
            $feedbackType = 'warning';
        } else {
            $feedback = "Student #{$id} not found or already archived.";
            $feedbackType = 'warning';
        }
    } catch (PDOException $e) {
        $feedback = 'Archive error: ' . $e->getMessage();
        $feedbackType = 'danger';
    }
}

// ============================================================
// CONTROLLER 3: RESTORE
// ============================================================
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    $stmt = $db->prepare("UPDATE students SET is_archived=0 WHERE id=?");
    $stmt->execute([$id]);
    $feedback = "Student #{$id} restored to active list.";
    $feedbackType = 'success';
}

// ============================================================
// READ
// ============================================================
$filter = $_GET['filter'] ?? 'all';
$sql    = "SELECT id, name, program, is_archived FROM students";
if ($filter === 'active')   $sql .= " WHERE is_archived = 0";
if ($filter === 'archived') $sql .= " WHERE is_archived = 1";
$sql .= " ORDER BY is_archived ASC, id DESC";

$students = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$total    = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$active   = $db->query("SELECT COUNT(*) FROM students WHERE is_archived=0")->fetchColumn();
$archived = $total - $active;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Records</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
        --bg: #f8fafc;
        --surface: #ffffff;
        --border: #e2e8f0;
        --primary: #0d9488;
        --primary-dark: #0f766e;
        --text: #0f172a;
        --text-dim: #64748b;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --shadow: 0 1px 3px rgba(0,0,0,0.06), 0 4px 12px rgba(0,0,0,0.04);
        --shadow-lg: 0 10px 30px rgba(0,0,0,0.08);
    }

    body {
        font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
        background: var(--bg);
        color: var(--text);
        min-height: 100vh;
        padding: 48px 20px;
        line-height: 1.5;
    }

    .container { max-width: 1080px; margin: 0 auto; }

    /* HEADER */
    .header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 32px;
        padding-bottom: 24px;
        border-bottom: 1px solid var(--border);
    }
    .header .logo {
        width: 52px; height: 52px;
        background: linear-gradient(135deg, #0d9488, #14b8a6);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        box-shadow: 0 6px 16px rgba(13, 148, 136, 0.3);
    }
    .header-text h1 {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--text);
    }
    .header-text p {
        font-size: 0.875rem;
        color: var(--text-dim);
        margin-top: 2px;
    }

    /* STATS */
    .stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }
    .stat {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 20px;
        box-shadow: var(--shadow);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    .stat .label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-dim);
        margin-bottom: 8px;
    }
    .stat .value {
        font-size: 1.875rem;
        font-weight: 700;
        color: var(--text);
        line-height: 1;
    }
    .stat .value.green { color: var(--success); }
    .stat .value.amber { color: var(--warning); }

    /* ALERT */
    .alert {
        padding: 14px 18px;
        border-radius: 12px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 0.9rem;
        font-weight: 500;
        animation: slideDown 0.3s ease;
        border: 1px solid transparent;
    }
    .alert.success { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
    .alert.danger  { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .alert.warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .alert .icon {
        width: 22px; height: 22px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        flex-shrink: 0;
    }
    .alert.success .icon { background: var(--success); color: white; }
    .alert.danger  .icon { background: var(--danger);  color: white; }
    .alert.warning .icon { background: var(--warning); color: white; }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-8px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* CARD */
    .card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        margin-bottom: 24px;
        box-shadow: var(--shadow);
        overflow: hidden;
    }
    .card-header {
        padding: 18px 24px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        background: #fcfdfe;
    }
    .card-header h2 {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .card-header h2::before {
        content: '';
        width: 4px; height: 18px;
        background: var(--primary);
        border-radius: 2px;
    }
    .card-body { padding: 24px; }

    /* FORM */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 16px;
        align-items: end;
    }
    @media (max-width: 720px) { .form-grid { grid-template-columns: 1fr; } }

    .field { display: flex; flex-direction: column; gap: 6px; }
    .field label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text);
    }
    .field label .req { color: var(--danger); margin-left: 2px; }
    .field input {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 10px;
        padding: 11px 14px;
        font-size: 0.925rem;
        color: var(--text);
        font-family: inherit;
        outline: none;
        transition: 0.15s;
    }
    .field input::placeholder { color: #94a3b8; }
    .field input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
    }

    /* BUTTONS */
    .btn {
        padding: 11px 22px;
        border-radius: 10px;
        font-size: 0.875rem;
        font-weight: 600;
        font-family: inherit;
        border: none;
        cursor: pointer;
        transition: 0.15s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        text-decoration: none;
        white-space: nowrap;
    }
    .btn-primary {
        background: var(--primary);
        color: white;
        padding: 11px 28px;
        box-shadow: 0 2px 8px rgba(13, 148, 136, 0.25);
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        box-shadow: 0 4px 12px rgba(13, 148, 136, 0.35);
        transform: translateY(-1px);
    }
    .btn-ghost {
        background: #f1f5f9;
        color: var(--text-dim);
        border: 1px solid var(--border);
        margin-top: 12px;
        font-size: 0.8rem;
        padding: 8px 14px;
    }
    .btn-ghost:hover { background: #e2e8f0; color: var(--text); }
    .btn-sm { padding: 6px 12px; font-size: 0.78rem; border-radius: 8px; }

    .btn-edit {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }
    .btn-edit:hover { background: #dbeafe; }

    .btn-archive {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
    .btn-archive:hover { background: #fee2e2; }

    .btn-restore {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }
    .btn-restore:hover { background: #d1fae5; }

    /* FILTER TABS */
    .filters { display: flex; gap: 6px; flex-wrap: wrap; }
    .filter-tab {
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none;
        color: var(--text-dim);
        border: 1px solid var(--border);
        background: var(--surface);
        transition: 0.15s;
    }
    .filter-tab:hover { background: #f1f5f9; color: var(--text); }
    .filter-tab.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }

    /* TABLE */
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; min-width: 600px; }
    thead th {
        text-align: left;
        padding: 14px 20px;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-dim);
        background: #f8fafc;
        border-bottom: 1px solid var(--border);
    }
    tbody td {
        padding: 16px 20px;
        font-size: 0.9rem;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    tbody tr { transition: background 0.15s; }
    tbody tr:hover { background: #f8fafc; }
    tbody tr.archived { opacity: 0.55; }
    tbody tr.archived td { color: var(--text-dim); }
    tbody tr:last-child td { border-bottom: none; }

    .id-badge {
        font-family: ui-monospace, monospace;
        background: #f1f5f9;
        color: var(--text-dim);
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.78rem;
        font-weight: 600;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .badge::before {
        content: '';
        width: 6px; height: 6px;
        border-radius: 50%;
    }
    .badge.active {
        background: #ecfdf5;
        color: #047857;
    }
    .badge.active::before { background: var(--success); }
    .badge.archived {
        background: #f1f5f9;
        color: var(--text-dim);
    }
    .badge.archived::before { background: #94a3b8; }

    .actions {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .empty {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-dim);
    }
    .empty .icon { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.6; }
    .empty .title { font-weight: 600; color: var(--text); margin-bottom: 4px; }
    .empty .sub { font-size: 0.85rem; }

    /* FOOTER */
    .footer {
        text-align: center;
        color: var(--text-dim);
        font-size: 0.8rem;
        margin-top: 32px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
    }
</style>
</head>
<body>
<div class="container">

    <!-- HEADER -->
    <div class="header">
        <div class="logo">🎓</div>
        <div class="header-text">
            <h1>Student Records</h1>
            <p>Manage student information</p>
        </div>
    </div>

    <!-- FEEDBACK -->
    <?php if ($feedback): ?>
        <div class="alert <?= htmlspecialchars($feedbackType) ?>">
            <span class="icon">
                <?= $feedbackType === 'success' ? '✓' : ($feedbackType === 'danger' ? '!' : 'i') ?>
            </span>
            <?= htmlspecialchars($feedback) ?>
        </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats">
        <div class="stat"><div class="label">Total Students</div><div class="value"><?= $total ?></div></div>
        <div class="stat"><div class="label">Active</div><div class="value green"><?= $active ?></div></div>
        <div class="stat"><div class="label">Archived</div><div class="value amber"><?= $archived ?></div></div>
    </div>

    <!-- FORM -->
    <div class="card">
        <div class="card-header">
            <h2 id="formTitle">Add New Student</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="index.php">
                <input type="hidden" name="student_id" id="student_id" value="">
                <div class="form-grid">
                    <div class="field">
                        <label for="name">Full Name <span class="req">*</span></label>
                        <input type="text" name="name" id="name"
                               placeholder="e.g. Maria Santos"
                               required maxlength="100">
                    </div>
                    <div class="field">
                        <label for="program">Program <span class="req">*</span></label>
                        <input type="text" name="program" id="program"
                               placeholder="e.g. BS Computer Science"
                               required maxlength="100">
                    </div>
                    <button type="submit" name="save" id="saveBtn" class="btn btn-primary">Save</button>
                </div>
                <button type="button" class="btn btn-ghost" id="cancelBtn"
                        style="display:none;" onclick="resetForm()">Cancel Edit</button>
            </form>
        </div>
    </div>

    <!-- TABLE -->
    <div class="card">
        <div class="card-header">
            <h2>Student List</h2>
            <div class="filters">
                <a href="?filter=all"      class="filter-tab <?= $filter==='all'?'active':'' ?>">All (<?= $total ?>)</a>
                <a href="?filter=active"   class="filter-tab <?= $filter==='active'?'active':'' ?>">Active (<?= $active ?>)</a>
                <a href="?filter=archived" class="filter-tab <?= $filter==='archived'?'active':'' ?>">Archived (<?= $archived ?>)</a>
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!$students): ?>
                <div class="empty">
                    <div class="icon">📋</div>
                    <div class="title">No records found</div>
                    <div class="sub">Add your first student using the form above.</div>
                </div>
            <?php else: ?>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:100px;">Student ID</th>
                        <th>Name</th>
                        <th>Program</th>
                        <th style="width:120px;">Status</th>
                        <th style="width:200px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $s): ?>
                    <tr class="<?= $s['is_archived'] ? 'archived' : '' ?>">
                        <td><span class="id-badge">#<?= $s['id'] ?></span></td>
                        <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                        <td><?= htmlspecialchars($s['program']) ?></td>
                        <td>
                            <span class="badge <?= $s['is_archived'] ? 'archived' : 'active' ?>">
                                <?= $s['is_archived'] ? 'Archived' : 'Active' ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!$s['is_archived']): ?>
                                <div class="actions">
                                    <button class="btn btn-sm btn-edit"
                                        onclick="editStudent(<?= $s['id'] ?>, '<?= addslashes(htmlspecialchars($s['name'])) ?>', '<?= addslashes(htmlspecialchars($s['program'])) ?>')">
                                        Edit
                                    </button>
                                    <a href="?archive=<?= $s['id'] ?><?= $filter!=='all'?'&filter='.$filter:'' ?>"
                                       class="btn btn-sm btn-archive"
                                       onclick="return confirm('Archive this record?\n\nIt will be hidden from active list but STILL SAVED in the database.')">
                                        Archive
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="actions">
                                    <a href="?restore=<?= $s['id'] ?><?= $filter!=='all'?'&filter='.$filter:'' ?>"
                                       class="btn btn-sm btn-restore"
                                       onclick="return confirm('Restore this student to active list?')">
                                        Restore
                                    </a>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        Student Records System · <?= date('Y') ?>
    </div>
</div>

<script>
function editStudent(id, name, program) {
    document.getElementById('student_id').value = id;
    document.getElementById('name').value = name;
    document.getElementById('program').value = program;
    document.getElementById('formTitle').innerText = 'Edit Student #' + id;
    document.getElementById('saveBtn').innerText = 'Update';
    document.getElementById('cancelBtn').style.display = 'inline-block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetForm() {
    document.getElementById('student_id').value = '';
    document.getElementById('name').value = '';
    document.getElementById('program').value = '';
    document.getElementById('formTitle').innerText = 'Add New Student';
    document.getElementById('saveBtn').innerText = 'Save';
    document.getElementById('cancelBtn').style.display = 'none';
}
</script>
</body>
</html>