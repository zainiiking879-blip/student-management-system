<?php
require_once __DIR__ . '/config/config.php';
auth();

$pdo = db();
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$class = $_GET['class'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 10;

$w = [];
$p = [];

if ($q !== '') {
    $w[] = '(student_code LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR class_name LIKE ?)';
    $like = "%$q%";
    array_push($p, $like, $like, $like, $like, $like);
}

if (in_array($status, ['Active', 'Inactive', 'Graduated', 'Transferred'], true)) {
    $w[] = 'status = ?';
    $p[] = $status;
}

if ($class !== '') {
    $w[] = 'class_name = ?';
    $p[] = $class;
}

$where = $w ? ' WHERE ' . implode(' AND ', $w) : '';

$total = (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$active = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
$inactive = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status='Inactive'")->fetchColumn();
$classCount = (int) $pdo->query('SELECT COUNT(DISTINCT class_name) FROM students')->fetchColumn();

$classes = $pdo->query(
    'SELECT DISTINCT class_name FROM students ORDER BY class_name'
)->fetchAll(PDO::FETCH_COLUMN);

$s = $pdo->prepare('SELECT COUNT(*) FROM students' . $where);
$s->execute($p);
$filtered = (int) $s->fetchColumn();

$pages = max(1, (int) ceil($filtered / $per));
$page = min($page, $pages);
$offset = ($page - 1) * $per;

$s = $pdo->prepare(
    'SELECT * FROM students' . $where .
    ' ORDER BY created_at DESC LIMIT ' . $per . ' OFFSET ' . $offset
);
$s->execute($p);
$students = $s->fetchAll();

$notice = popflash();

function initials($a, $b): string {
    return strtoupper(substr((string)$a, 0, 1) . substr((string)$b, 0, 1));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | SMS</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="dashboard-page">

<aside class="sidebar">
    <a class="brand" href="dashboard.php">
        <span class="brand-mark">S</span>
        <span>SMS<small>STUDENT MANAGEMENT</small></span>
    </a>

    <div class="side-label">WORKSPACE</div>

    <a class="side-link active" href="dashboard.php">
        ▦ &nbsp; Dashboard
    </a>

    <a class="side-link" href="student-form.php">
        ＋ &nbsp; Add Student
    </a>

    

    <div class="side-label">SYSTEM</div>

    <a class="side-link" href="index.php">
        ↗ &nbsp; Public Website
    </a>

    <div class="sidebar-bottom">
        <div class="admin-avatar">S</div>
        <div>
            <strong><?= h($_SESSION['admin_name'] ?? 'Administrator') ?></strong>
            <small>Administrator</small>
        </div>
        <a class="logout-link" href="logout.php" title="Logout">↪</a>
    </div>
</aside>

<main class="dashboard-main">

    <header class="dash-topbar">
        <div>
            <button class="sidebar-mobile-toggle" type="button" aria-label="Toggle sidebar">☰</button>
            <span class="crumb">Workspace <b>/</b> Dashboard</span>
        </div>
        <span class="system-pill"><i></i> Secure workspace</span>
    </header>

    <div class="dash-content">

        <div class="dash-heading">
            <div>
                <div class="eyebrow">STUDENT ADMINISTRATION</div>
                <h1>Student overview</h1>
                <p>Manage student information from one organized workspace.</p>
            </div>
            <a class="btn btn-primary" href="student-form.php">＋ Add Student</a>
        </div>

        <?php if ($notice): ?>
            <div class="notice notice-<?= h($notice['type']) ?>">
                <?= h($notice['message']) ?>
            </div>
        <?php endif; ?>

        <div class="stat-grid">
            <article class="stat-card">
                <div class="stat-top">
                    Total students
                    <span class="stat-icon icon-blue">♙</span>
                </div>
                <strong><?= $total ?></strong>
                <small>All student records</small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    Active students
                    <span class="stat-icon icon-green">✓</span>
                </div>
                <strong><?= $active ?></strong>
                <small>Currently active</small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    Inactive records
                    <span class="stat-icon icon-amber">Ⅱ</span>
                </div>
                <strong><?= $inactive ?></strong>
                <small>Not currently active</small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    Classes represented
                    <span class="stat-icon icon-violet">▤</span>
                </div>
                <strong><?= $classCount ?></strong>
                <small>Distinct class groups</small>
            </article>
        </div>

        <section class="panel" id="student-table">

            <div class="panel-heading">
                <div>
                    <h2>Student directory</h2>
                    <p>Search, filter and manage student records.</p>
                </div>
                <a class="btn btn-outline" href="student-form.php">＋ New Student</a>
            </div>

            <form class="filters" method="get">
                <div class="search-field">
                    <span>⌕</span>
                    <input
                        name="q"
                        value="<?= h($q) ?>"
                        placeholder="Search name, ID, email or class…"
                    >
                </div>

                <select name="class">
                    <option value="">All classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= h($c) ?>" <?= $class === $c ? 'selected' : '' ?>>
                            <?= h($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status">
                    <option value="">All statuses</option>
                    <?php foreach (['Active', 'Inactive', 'Graduated', 'Transferred'] as $x): ?>
                        <option value="<?= h($x) ?>" <?= $status === $x ? 'selected' : '' ?>>
                            <?= h($x) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="btn btn-dark" type="submit">Filter</button>
                <a class="btn btn-ghost" href="dashboard.php">Reset</a>
            </form>

            <div class="table-wrap">
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>STUDENT</th>
                            <th>STUDENT ID</th>
                            <th>CLASS / SECTION</th>
                            <th>CONTACT</th>
                            <th>STATUS</th>
                            <th>ADMITTED</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td>
                                <div class="student-cell">
                                    <span class="student-avatar">
                                        <?php if (!empty($s['photo_path']) && is_file(__DIR__ . '/' . $s['photo_path'])): ?>
                                            <img src="<?= h($s['photo_path']) ?>" alt="">
                                        <?php else: ?>
                                            <?= h(initials($s['first_name'], $s['last_name'])) ?>
                                        <?php endif; ?>
                                    </span>
                                    <span>
                                        <strong><?= h($s['first_name'] . ' ' . $s['last_name']) ?></strong>
                                        <small><?= h($s['email']) ?></small>
                                    </span>
                                </div>
                            </td>

                            <td>
                                <span class="code-pill"><?= h($s['student_code']) ?></span>
                            </td>

                            <td>
                                <strong><?= h($s['class_name']) ?></strong>
                                <small class="table-sub">
                                    <?= h($s['section_name'] ?: 'No section') ?>
                                </small>
                            </td>

                            <td><?= h($s['phone'] ?: '—') ?></td>

                            <td>
                                <span class="status status-<?= h(strtolower($s['status'])) ?>">
                                    <i></i><?= h($s['status']) ?>
                                </span>
                            </td>

                            <td><?= h($s['admission_date'] ?: '—') ?></td>

                            <td>
                                <div class="row-actions">
                                    <a href="student-view.php?id=<?= (int)$s['id'] ?>" title="View student">↗</a>
                                    <a href="student-form.php?id=<?= (int)$s['id'] ?>" title="Edit student">✎</a>

                                    <form
                                        method="post"
                                        action="student-delete.php"
                                        data-confirm="Delete this student? This cannot be undone."
                                    >
                                        <input type="hidden" name="_csrf" value="<?= h(csrf()) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                        <button type="submit" title="Delete student">×</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$students): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <span>⌕</span>
                                    <strong>No student records found</strong>
                                    <p>Add your first student or change the filters.</p>
                                    <a class="btn btn-primary" href="student-form.php">Add Student</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span>
                    Showing <?= $filtered ? ($offset + 1) : 0 ?>–<?= min($offset + count($students), $filtered) ?>
                    of <?= $filtered ?> records
                </span>

                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= h(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">←</a>
                    <?php endif; ?>

                    <span>Page <?= $page ?> / <?= $pages ?></span>

                    <?php if ($page < $pages): ?>
                        <a href="?<?= h(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">→</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <div class="dashboard-foot">
            <span>© <?= date('Y') ?> Student Management System (SMS)</span>
            <span>PHP · MySQL · REST API</span>
        </div>

    </div>
</main>

<script src="assets/js/app.js"></script>
</body>
</html>