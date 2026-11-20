<?php
require_once __DIR__ . '/config/config.php';

if (logged()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $email = strtolower(trim($_POST['email'] ?? ''));

    try {
        $s = db()->prepare('SELECT * FROM admins WHERE email = ? LIMIT 1');
        $s->execute([$email]);
        $a = $s->fetch();

        if ($a && password_verify($_POST['password'] ?? '', $a['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['admin_id'] = (int) $a['id'];
            $_SESSION['admin_name'] = $a['full_name'];

            db()->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?')
                ->execute([$a['id']]);

            header('Location: ' . url('dashboard.php'));
            exit;
        }

        $err = 'Email or password is incorrect.';
    } catch (Throwable $x) {
        $err = 'Unable to sign in. Check database setup.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SMS | Admin Login</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>

<body class="auth-page">
    <main class="auth-card">

        <a class="brand" href="index.php">
            <span class="brand-mark">S</span>
            <span>
                SMS
                <small>STUDENT MANAGEMENT SYSTEM</small>
            </span>
        </a>

        <div class="eyebrow">SECURE ADMIN ACCESS</div>

        <h1>Welcome back.</h1>
        <p class="muted">Sign in to manage student records.</p>

        <?php if ($err): ?>
            <div class="notice notice-error">
                <?= h($err) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="_csrf" value="<?= h(csrf()) ?>">

            <label>
                Email address
                <input
                    type="email"
                    name="email"
                    autocomplete="username"
                    required
                >
            </label>

            <label>
                Password
                <input
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </label>

            <button class="btn btn-primary btn-wide" type="submit">
                Sign in securely →
            </button>
        </form>

        <p class="small muted">
            First run? Import database/schema.sql, configure the database,
            then open setup-admin.php once. Delete setup-admin.php after setup.
        </p>

        <a class="text-link" href="index.php">← Back to website</a>

    </main>
</body>
</html>