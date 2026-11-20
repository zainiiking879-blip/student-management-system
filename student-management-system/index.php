<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();

$isAdminLoggedIn =
    !empty($_SESSION['admin_id']) ||
    !empty($_SESSION['admin_logged_in']) ||
    (isset($_SESSION['role']) &&
        strtolower((string) $_SESSION['role']) === 'admin');

$adminUrl = $isAdminLoggedIn
    ? 'dashboard.php'
    : 'login.php';

$q = trim((string) ($_GET['q'] ?? ''));
$class = trim((string) ($_GET['class'] ?? ''));
$section = trim((string) ($_GET['section'] ?? ''));

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

function student_initials(string $first, string $last): string
{
    $a = function_exists('mb_substr')
        ? mb_substr($first, 0, 1)
        : substr($first, 0, 1);

    $b = function_exists('mb_substr')
        ? mb_substr($last, 0, 1)
        : substr($last, 0, 1);

    return strtoupper($a . $b) ?: 'ST';
}

function student_photo_url(?string $path): string
{
    $path = trim((string) $path);

    if (
        $path === '' ||
        preg_match('~^(?:https?:)?//~i', $path)
    ) {
        return '';
    }

    $path = ltrim(
        str_replace('\\', '/', $path),
        '/'
    );

    if (
        $path === '' ||
        in_array('..', explode('/', $path), true)
    ) {
        return '';
    }

    if (!is_file(__DIR__ . '/' . $path)) {
        return '';
    }

    $dir = str_replace(
        '\\',
        '/',
        dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))
    );

    $base = ($dir === '/' || $dir === '.')
        ? ''
        : rtrim($dir, '/');

    return $base . '/' . implode(
        '/',
        array_map('rawurlencode', explode('/', $path))
    );
}

function student_lines(?string $text): array
{
    return array_values(
        array_filter(
            array_map(
                'trim',
                preg_split(
                    '/\r\n|\r|\n/',
                    trim((string) $text)
                ) ?: []
            ),
            static fn($value) => $value !== ''
        )
    );
}

/* Get available classes */
$classes = $pdo->query("
    SELECT DISTINCT class_name
    FROM students
    WHERE status = 'Active'
      AND class_name IS NOT NULL
      AND class_name <> ''
    ORDER BY class_name
")->fetchAll(PDO::FETCH_COLUMN);

/* Get available sections */
$sections = $pdo->query("
    SELECT DISTINCT section_name
    FROM students
    WHERE status = 'Active'
      AND section_name IS NOT NULL
      AND section_name <> ''
    ORDER BY section_name
")->fetchAll(PDO::FETCH_COLUMN);

/* Build student search */
$where = ["status = 'Active'"];
$params = [];

if ($q !== '') {
    $where[] = "(
        student_code LIKE ?
        OR first_name LIKE ?
        OR last_name LIKE ?
        OR class_name LIKE ?
        OR section_name LIKE ?
    )";

    $like = '%' . $q . '%';

    array_push(
        $params,
        $like,
        $like,
        $like,
        $like,
        $like
    );
}

if ($class !== '') {
    $where[] = 'class_name = ?';
    $params[] = $class;
}

if ($section !== '') {
    $where[] = 'section_name = ?';
    $params[] = $section;
}

/* Fetch student records */
$sql = "
    SELECT
        id,
        student_code,
        first_name,
        last_name,
        class_name,
        section_name,
        photo_path,
        qualifications,
        achievements
    FROM students
    WHERE " . implode(' AND ', $where) . "
    ORDER BY
        class_name,
        section_name,
        first_name,
        last_name
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Dashboard statistics */
$total = (int) $pdo->query("
    SELECT COUNT(*)
    FROM students
    WHERE status = 'Active'
")->fetchColumn();

$classTotal = count($classes);
$shownTotal = count($students);

$featured = $students[0] ?? null;

$featuredName = $featured
    ? trim(
        $featured['first_name'] . ' ' .
        $featured['last_name']
    )
    : 'Your student community';

$featuredInitials = $featured
    ? student_initials(
        (string) $featured['first_name'],
        (string) $featured['last_name']
    )
    : 'ST';

$featuredPhoto = $featured
    ? student_photo_url($featured['photo_path'] ?? '')
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0a1530">

<title>Student Directory | Student Management System</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --navy: #0a1530;
    --blue: #536df4;
    --mint: #70ead8;
    --ink: #17213a;
    --muted: #75819a;
    --line: #e6ebf4;
    --paper: #f5f7fc;
    --shadow: 0 18px 50px #16284a0d;
}

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    background: var(--paper);
    color: var(--ink);
    font-family: 'DM Sans', sans-serif;
}

a {
    color: inherit;
    text-decoration: none;
}

button, input, select {
    font: inherit;
}

.wrap {
    width: min(1240px, calc(100% - 40px));
    margin: auto;
}

.header {
    min-height: 82px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.logo {
    width: 45px;
    height: 45px;
    display: grid;
    place-items: center;
    border-radius: 14px;
    background: linear-gradient(140deg, #8ba0ff, #425be6);
    color: white;
    font: 800 20px Manrope;
    box-shadow: 0 9px 24px #536df433;
}

.brand strong {
    display: block;
    font: 800 14px Manrope;
}

.brand small {
    display: block;
    margin-top: 4px;
    font-size: 9px;
    letter-spacing: 1.5px;
    color: #8590a6;
    font-weight: 800;
}

.admin {
    background: var(--navy);
    color: white;
    padding: 13px 18px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 800;
    transition: .2s ease;
}

.admin:hover {
    background: var(--blue);
    transform: translateY(-2px);
}

/* Animated information bar */

.ticker {
    overflow: hidden;
    margin: 0 auto 18px;
    border-radius: 12px;
    background: linear-gradient(100deg, #f0529c, #7659f6 55%, #238ee8);
    color: white;
    box-shadow: 0 10px 25px #7659f626;
}

.ticker-track {
    display: flex;
    width: max-content;
    gap: 34px;
    padding: 13px 0;
    animation: tickerMove 32s linear infinite;
    font-size: 11px;
    font-weight: 800;
}

.ticker-track span {
    white-space: nowrap;
}

.ticker-track span::before {
    content: '✦';
    margin-right: 10px;
    color: #baf8ef;
}

@keyframes tickerMove {
    to {
        transform: translateX(-50%);
    }
}

.ticker:hover .ticker-track {
    animation-play-state: paused;
}

/* Hero */

.hero {
    display: grid;
    grid-template-columns: 1.1fr .9fr;
    gap: 35px;
    align-items: center;
    padding: clamp(28px, 5vw, 66px);
    min-height: 445px;
    border-radius: 30px;
    background:
        radial-gradient(circle at 85% 10%, #526eff70, transparent 28%),
        radial-gradient(circle at 60% 100%, #2ac9b82b, transparent 35%),
        linear-gradient(120deg, #09132a, #14274f 65%, #233b70);
    color: white;
    box-shadow: 0 25px 70px #14284c20;
}

.eyebrow {
    display: inline-flex;
    gap: 9px;
    align-items: center;
    padding: 9px 12px;
    border: 1px solid #ffffff30;
    border-radius: 30px;
    background: #ffffff0c;
    color: #dce5ff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.3px;
}

.dot {
    width: 7px;
    height: 7px;
    background: var(--mint);
    border-radius: 50%;
}

.hero h1 {
    font: 800 clamp(38px, 4.4vw, 63px)/1.07 Manrope;
    margin: 24px 0 17px;
    letter-spacing: -2.5px;
}

.hero h1 em {
    color: var(--mint);
    font-style: normal;
}

.hero p {
    max-width: 570px;
    color: #bdc9e4;
    line-height: 1.9;
    font-size: 14px;
}

.actions {
    display: flex;
    gap: 11px;
    flex-wrap: wrap;
    margin-top: 25px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 14px 18px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
}

.primary {
    background: linear-gradient(120deg, #718bff, #4964ef);
    color: white;
}

.glass {
    border: 1px solid #ffffff38;
    background: #ffffff0b;
    color: white;
}

/* Student spotlight */

.showcase {
    padding: 22px;
    border: 1px solid #ffffff30;
    border-radius: 24px;
    background: linear-gradient(145deg, #ffffff20, #ffffff08);
    box-shadow: 0 24px 65px #0003;
    backdrop-filter: blur(12px);
}

.showtop, .showfoot {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
    color: #dce5ff;
    font-size: 10px;
    font-weight: 800;
}

.badge {
    color: #7cf0de;
    background: #70ead81b;
    padding: 7px 9px;
    border-radius: 20px;
}

.person {
    display: flex;
    align-items: center;
    gap: 15px;
    margin: 25px 0;
}

.person-photo {
    width: 82px;
    height: 82px;
    border-radius: 22px;
    border: 2px solid #ffffff5c;
    object-fit: cover;
    display: grid;
    place-items: center;
    background: #ffffff17;
    color: white;
    font-size: 24px;
    font-weight: 800;
    flex-shrink: 0;
    overflow: hidden;
}

.person-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.person strong {
    display: block;
    font: 800 17px Manrope;
}

.person small {
    display: block;
    margin-top: 7px;
    color: #b8c6e4;
    font-size: 11px;
}

.showstats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    padding-top: 17px;
    border-top: 1px solid #ffffff25;
}

.showstats strong {
    display: block;
    font: 800 23px Manrope;
}

.showstats span {
    display: block;
    margin-top: 5px;
    color: #b8c6e4;
    font-size: 9px;
}

.showfoot {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #ffffff25;
}

/* Statistics */

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin: 22px 0 65px;
}

.stat {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 22px;
    border: 1px solid var(--line);
    border-radius: 18px;
    background: white;
    box-shadow: var(--shadow);
}

.stat-icon {
    width: 46px;
    height: 46px;
    display: grid;
    place-items: center;
    border-radius: 14px;
    background: #eef1ff;
    color: var(--blue);
    font-size: 21px;
}

.stat strong {
    display: block;
    font: 800 22px Manrope;
}

.stat span {
    display: block;
    margin-top: 5px;
    color: var(--muted);
    font-size: 11px;
}

/* Directory */

.directory {
    margin-bottom: 65px;
}

.heading {
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 20px;
    margin-bottom: 25px;
}

.overline {
    color: var(--blue);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.5px;
}

.heading h2 {
    margin: 10px 0;
    font: 800 clamp(27px, 4vw, 38px) Manrope;
    letter-spacing: -1px;
}

.heading p {
    max-width: 600px;
    color: var(--muted);
    line-height: 1.8;
    font-size: 13px;
}

.count {
    white-space: nowrap;
    padding: 13px 16px;
    background: white;
    border: 1px solid var(--line);
    border-radius: 12px;
    color: var(--muted);
    font-size: 11px;
}

.count b {
    color: var(--blue);
    font-size: 17px;
    margin-right: 5px;
}

.filters {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 10px;
    padding: 15px;
    border: 1px solid var(--line);
    border-radius: 18px;
    background: white;
    box-shadow: var(--shadow);
}

.control {
    min-width: 0;
    width: 100%;
    padding: 14px;
    border: 1px solid #e3e8f2;
    border-radius: 11px;
    background: #fbfcff;
    color: var(--ink);
    outline: none;
    font-size: 12px;
}

.control:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px #536df41c;
}

.submit {
    padding: 14px 19px;
    border: 0;
    border-radius: 11px;
    background: var(--navy);
    color: white;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    transition: .2s;
}

.submit:hover {
    background: var(--blue);
}

.filterline {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin: 18px 2px;
    color: var(--muted);
    font-size: 11px;
}

.clear {
    color: var(--blue);
    font-weight: 800;
}

/* Student cards */

.grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.card {
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 20px;
    background: white;
    box-shadow: var(--shadow);
    transition: transform .2s, box-shadow .2s;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 22px 50px #16284a14;
}

.cover {
    height: 125px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    background:
        radial-gradient(circle at 20% 20%, #ffffff45, transparent 30%),
        linear-gradient(125deg, #657df5, #7961e8 55%, #42c8c1);
}

.card:nth-child(3n + 2) .cover {
    background: linear-gradient(125deg, #f17cb6, #a66af2, #647df6);
}

.card:nth-child(3n) .cover {
    background: linear-gradient(125deg, #29b9b0, #49a5df, #647df6);
}

.index {
    position: absolute;
    top: 14px;
    left: 14px;
    color: white;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 1px;
}

.student-photo {
    width: 78px;
    height: 78px;
    border: 4px solid white;
    border-radius: 24px;
    object-fit: cover;
    box-shadow: 0 8px 25px #0002;
}

.student-initial {
    display: grid;
    place-items: center;
    background: #ffffffeb;
    color: var(--blue);
    font: 800 22px Manrope;
}

.card-body {
    padding: 20px;
}

.student-name {
    margin: 0 0 8px;
    font: 800 17px Manrope;
    overflow-wrap: anywhere;
}

.code {
    color: var(--muted);
    font-size: 10px;
}

.tags {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 15px;
}

.tag {
    padding: 7px 10px;
    border-radius: 8px;
    background: #eef1ff;
    color: #4c61d7;
    font-size: 10px;
    font-weight: 800;
}

.tag.section {
    background: #e5faf5;
    color: #188d7e;
}

.details {
    margin-top: 17px;
    padding-top: 8px;
    border-top: 1px solid var(--line);
}

.detail {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin: 10px 0;
    font-size: 10px;
}

.detail b {
    color: #4c5871;
}

.detail span {
    color: var(--muted);
    text-align: right;
}

.cardfoot {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--line);
    font-size: 10px;
    color: var(--muted);
}

.view {
    color: var(--blue);
    font-weight: 800;
}

.empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 55px 20px;
    border: 1px dashed #ccd4e5;
    border-radius: 18px;
    background: white;
}

.empty h3 {
    margin: 12px 0;
}

.empty p {
    color: var(--muted);
    font-size: 13px;
}

/* About */

.values {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 65px;
}

.value {
    padding: 24px;
    border: 1px solid var(--line);
    border-radius: 18px;
    background: white;
    transition: .2s ease;
}

.value:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow);
}

.value-icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: #eef1ff;
    color: var(--blue);
    font-size: 20px;
}

.value h3 {
    margin: 17px 0 8px;
    font: 800 15px Manrope;
}

.value p {
    margin: 0;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.8;
}

/* Footer */

.footer {
    background: #091329;
    color: white;
    padding: 30px 0;
}

.footer-inner {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    align-items: center;
}

.footer strong {
    font: 800 14px Manrope;
}

.footer p, .footer-meta {
    color: #aab7d0;
    font-size: 11px;
    line-height: 1.8;
}

.footer p {
    margin: 7px 0 0;
}

.footer-meta {
    text-align: right;
}

/* Responsive design */

@media (max-width: 900px) {
    .hero {
        grid-template-columns: 1fr;
        gap: 25px;
    }

    .showcase {
        max-width: 500px;
    }

    .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .filters {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 600px) {
    .wrap {
        width: calc(100% - 26px);
    }

    .header {
        min-height: 72px;
    }

    .brand small {
        letter-spacing: 1px;
    }

    .admin {
        padding: 11px;
        font-size: 10px;
    }

    .hero {
        padding: 27px 21px;
        border-radius: 22px;
    }

    .hero h1 {
        letter-spacing: -1.5px;
    }

    .hero p {
        font-size: 13px;
    }

    .showcase {
        padding: 17px;
    }

    .stats, .values {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .stat {
        padding: 16px;
    }

    .heading {
        align-items: start;
        flex-direction: column;
    }

    .filters {
        grid-template-columns: 1fr;
        padding: 11px;
    }

    .grid {
        grid-template-columns: 1fr;
    }

    .cover {
        height: 200px;
    }

    .footer-inner {
        align-items: start;
        flex-direction: column;
    }

    .footer-meta {
        text-align: left;
    }

    .showstats strong {
        font-size: 20px;
    }
}
</style>
</head>

<body>

<header class="wrap header">

    <a class="brand" href="index.php">
        <span class="logo">S</span>

        <span>
            <strong>Student Management System</strong>
            <small>STUDENT DIRECTORY &amp; PROFILES</small>
        </span>
    </a>

    <a class="admin" href="<?= h($adminUrl) ?>">
        ♙ Admin Workplace ·
        <?= $isAdminLoggedIn ? 'Dashboard ↗' : 'Login ↗' ?>
    </a>

</header>

<!-- Animated SMS information line -->

<div class="wrap ticker" aria-label="Student Management System highlights">
    <div class="ticker-track">
        <span>STUDENT MANAGEMENT SYSTEM</span>
        <span>SMART STUDENT RECORDS</span>
        <span>ACADEMIC PROFILES &amp; QUALIFICATIONS</span>
        <span>CLASS AND SECTION ORGANIZATION</span>
        <span>STUDENT ACHIEVEMENTS</span>
        <span>ADMIN WORKPLACE</span>

        <span>STUDENT MANAGEMENT SYSTEM</span>
        <span>SMART STUDENT RECORDS</span>
        <span>ACADEMIC PROFILES &amp; QUALIFICATIONS</span>
        <span>CLASS AND SECTION ORGANIZATION</span>
        <span>STUDENT ACHIEVEMENTS</span>
        <span>ADMIN WORKPLACE</span>
    </div>
</div>

<main class="wrap">

<section class="hero">

    <div>
        <span class="eyebrow">
            <i class="dot"></i>
            STUDENT COMMUNITY
        </span>

        <h1>
            Every student has a story.
            <em>Show it.</em>
        </h1>

        <p>
            Discover student profiles, explore academic journeys,
            and celebrate qualifications and achievements through
            one organized directory.
        </p>

        <div class="actions">
            <a class="btn primary" href="#students">
                Explore students →
            </a>

            <a class="btn glass" href="#about">
                Discover our approach
            </a>
        </div>
    </div>

    <div class="showcase">

        <div class="showtop">
            <span>STUDENT SPOTLIGHT</span>
            <span class="badge">✦ Featured profile</span>
        </div>

        <div class="person">

            <div class="person-photo">
                <?php if ($featuredPhoto): ?>
                    <img
                        src="<?= h($featuredPhoto) ?>"
                        alt="Student photo"
                        onerror="this.remove();this.parentElement.textContent='<?= h($featuredInitials) ?>'"
                    >
                <?php else: ?>
                    <?= h($featuredInitials) ?>
                <?php endif; ?>
            </div>

            <div>
                <strong><?= h($featuredName) ?></strong>

                <small>
                    <?= $featured
                        ? h((string) $featured['class_name']) . ' · Student spotlight'
                        : 'Learning · Growth · Achievement'
                    ?>
                </small>
            </div>

        </div>

        <div class="showstats">
            <div>
                <strong><?= number_format($total) ?></strong>
                <span>Active students</span>
            </div>

            <div>
                <strong><?= number_format($classTotal) ?></strong>
                <span>Class groups</span>
            </div>

            <div>
                <strong><?= number_format($shownTotal) ?></strong>
                <span>Profiles shown</span>
            </div>
        </div>

        <div class="showfoot">
            <span>Student Management System</span>
            <span>Learning starts here ↗</span>
        </div>

    </div>

</section>

<section class="stats">

    <div class="stat">
        <div class="stat-icon">♙</div>
        <div>
            <strong><?= number_format($total) ?></strong>
            <span>Active student profiles</span>
        </div>
    </div>

    <div class="stat">
        <div class="stat-icon">▦</div>
        <div>
            <strong><?= number_format($classTotal) ?></strong>
            <span>Class groups represented</span>
        </div>
    </div>

    <div class="stat">
        <div class="stat-icon">✧</div>
        <div>
            <strong>One community</strong>
            <span>Many journeys and achievements</span>
        </div>
    </div>

</section>

<section class="directory" id="students">

    <div class="heading">
        <div>
            <div class="overline">THE STUDENT COMMUNITY</div>

            <h2>Explore our students</h2>

            <p>
                Search the directory, filter by class or section,
                and open a full profile to explore academic details.
            </p>
        </div>

        <div class="count">
            <b><?= number_format($shownTotal) ?></b>
            profiles shown
        </div>
    </div>

    <form class="filters" method="get" action="index.php">

        <input
            class="control"
            type="search"
            name="q"
            value="<?= h($q) ?>"
            placeholder="Search name, student ID or class..."
            aria-label="Search students"
        >

        <select class="control" name="class">
            <option value="">All classes</option>

            <?php foreach ($classes as $c): ?>
                <option
                    value="<?= h($c) ?>"
                    <?= $class === (string) $c ? 'selected' : '' ?>
                >
                    <?= h($c) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select class="control" name="section">
            <option value="">All sections</option>

            <?php foreach ($sections as $sec): ?>
                <option
                    value="<?= h($sec) ?>"
                    <?= $section === (string) $sec ? 'selected' : '' ?>
                >
                    Section <?= h($sec) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="submit" type="submit">
            Find students →
        </button>

    </form>

    <div class="filterline">

        <span>
            <?= ($q !== '' || $class !== '' || $section !== '')
                ? 'Showing matching student records'
                : 'Showing all active student records'
            ?>
        </span>

        <?php if ($q !== '' || $class !== '' || $section !== ''): ?>
            <a class="clear" href="index.php">
                Clear filters ↻
            </a>
        <?php endif; ?>

    </div>

    <div class="grid">

        <?php foreach ($students as $i => $s):

            $name = trim(
                (string) $s['first_name'] . ' ' .
                (string) $s['last_name']
            );

            $initial = student_initials(
                (string) $s['first_name'],
                (string) $s['last_name']
            );

            $photo = student_photo_url($s['photo_path'] ?? '');

            $quals = student_lines($s['qualifications'] ?? '');
            $ach = student_lines($s['achievements'] ?? '');

        ?>

        <a
            class="card"
            href="student-view.php?id=<?= (int) $s['id'] ?>"
        >

            <div class="cover">

                <span class="index">
                    PROFILE ·
                    <?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?>
                </span>

                <?php if ($photo): ?>

                    <img
                        class="student-photo"
                        src="<?= h($photo) ?>"
                        alt="<?= h($name) ?>"
                        loading="lazy"
                        decoding="async"
                        data-initial="<?= h($initial) ?>"
                        onerror="this.onerror=null;const d=document.createElement('div');d.className='student-photo student-initial';d.textContent=this.dataset.initial;this.replaceWith(d)"
                    >

                <?php else: ?>

                    <div class="student-photo student-initial">
                        <?= h($initial) ?>
                    </div>

                <?php endif; ?>

            </div>

            <div class="card-body">

                <h3 class="student-name">
                    <?= h($name) ?>
                </h3>

                <div class="code">
                    Student ID · <?= h($s['student_code']) ?>
                </div>

                <div class="tags">

                    <span class="tag">
                        <?= h($s['class_name']) ?>
                    </span>

                    <?php if (!empty($s['section_name'])): ?>
                        <span class="tag section">
                            Section <?= h($s['section_name']) ?>
                        </span>
                    <?php endif; ?>

                </div>

                <div class="details">

                    <div class="detail">
                        <b>Qualifications</b>
                        <span>
                            <?= count($quals)
                                ? count($quals) . ' listed'
                                : 'Profile details available'
                            ?>
                        </span>
                    </div>

                    <div class="detail">
                        <b>Achievements</b>
                        <span>
                            <?= count($ach)
                                ? count($ach) . ' highlights'
                                : 'Learning in progress'
                            ?>
                        </span>
                    </div>

                </div>

                <div class="cardfoot">
                    <span>Student portfolio</span>
                    <span class="view">View full profile ↗</span>
                </div>

            </div>

        </a>

        <?php endforeach; ?>

        <?php if (!$students): ?>

            <div class="empty">
                <h3>No student profiles found</h3>

                <p>
                    Try a different name, class or section,
                    or clear filters.
                </p>

                <a class="btn primary" href="index.php">
                    Reset directory ↻
                </a>
            </div>

        <?php endif; ?>

    </div>

</section>

<section class="values" id="about">

    <article class="value">
        <div class="value-icon">◎</div>
        <h3>Profiles with purpose</h3>
        <p>
            Open a student card to explore academic information
            and learner progress.
        </p>
    </article>

    <article class="value">
        <div class="value-icon">▦</div>
        <h3>Organized discovery</h3>
        <p>
            Search by student name or ID and filter the directory
            by class or section.
        </p>
    </article>

    <article class="value">
        <div class="value-icon">✧</div>
        <h3>Achievements matter</h3>
        <p>
            Qualifications and achievements are highlighted
            for quick discovery.
        </p>
    </article>

</section>

</main>

<footer class="footer">

    <div class="wrap footer-inner">

        <div>
            <strong>Student Management System · SMS</strong>

            <p>
                A modern view of student learning, progress
                and achievement.
            </p>
        </div>

        <div class="footer-meta">
            Student directory · View-only profiles
            <br>
            © <?= date('Y') ?> Student Management System
        </div>

    </div>

</footer>

</body>
</html>