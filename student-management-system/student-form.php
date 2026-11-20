<?php
require_once __DIR__ . '/config/config.php';
auth();

$id = max(0, (int)($_GET['id'] ?? 0));
$edit = $id > 0;

$f = [
    'student_code'   => '',
    'first_name'     => '',
    'last_name'      => '',
    'email'          => '',
    'phone'          => '',
    'gender'         => '',
    'date_of_birth'  => '',
    'class_name'     => '',
    'section_name'   => '',
    'admission_date' => date('Y-m-d'),
    'guardian_name'  => '',
    'guardian_phone' => '',
    'address'        => '',
    'status'         => 'Active',
    'notes'          => '',
    'photo_path'     => '',
    'qualifications' => '',
    'achievements'   => ''
];

if ($edit) {
    $stmt = db()->prepare('SELECT * FROM students WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        exit('Student not found.');
    }

    $f = array_merge($f, $row);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    foreach ($f as $key => $value) {
        if ($key === 'photo_path') {
            continue;
        }

        $f[$key] = trim((string)($_POST[$key] ?? ''));
    }

    /*
     * CROPPED PHOTO HANDLING
     * The browser sends a cropped JPEG as a Base64 data URL.
     */
    $cropped = (string)($_POST['cropped_photo'] ?? '');
    $newPhotoPath = '';

    if ($cropped !== '') {
        if (!preg_match(
            '#^data:image/jpeg;base64,#',
            $cropped
        )) {
            $errors[] = 'Please select and crop a valid photo.';
        } else {
            $base64 = substr(
                $cropped,
                strpos($cropped, ',') + 1
            );

            $binary = base64_decode($base64, true);

            if (
                $binary === false ||
                strlen($binary) > 6000000
            ) {
                $errors[] = 'The cropped photo is invalid or too large.';
            } else {
                $imageInfo = @getimagesizefromstring($binary);

                if (
                    $imageInfo === false ||
                    ($imageInfo['mime'] ?? '') !== 'image/jpeg'
                ) {
                    $errors[] = 'The cropped image must be a valid JPEG.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/students';

                    if (
                        !is_dir($uploadDir) &&
                        !mkdir($uploadDir, 0755, true) &&
                        !is_dir($uploadDir)
                    ) {
                        $errors[] = 'Unable to create the student photo folder.';
                    } else {
                        try {
                            $fileName = 'student_' .
                                bin2hex(random_bytes(12)) .
                                '.jpg';

                            $destination = $uploadDir . '/' . $fileName;

                            if (file_put_contents(
                                $destination,
                                $binary,
                                LOCK_EX
                            ) === false) {
                                $errors[] = 'Unable to save the photo. Check folder permissions.';
                            } else {
                                $newPhotoPath = 'uploads/students/' . $fileName;
                                $f['photo_path'] = $newPhotoPath;
                            }
                        } catch (Throwable $e) {
                            $errors[] = 'Unable to process the uploaded photo.';
                        }
                    }
                }
            }
        }
    }

    /*
     * REQUIRED FIELDS
     */
    $requiredFields = [
        'student_code' => 'Student ID',
        'first_name'   => 'First name',
        'last_name'    => 'Last name',
        'class_name'   => 'Class'
    ];

    foreach ($requiredFields as $key => $label) {
        if ($f[$key] === '') {
            $errors[] = $label . ' is required.';
        }
    }

    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    /*
     * VALIDATE STATUS AND GENDER
     */
    $validStatuses = [
        'Active',
        'Inactive',
        'Graduated',
        'Transferred'
    ];

    if (!in_array($f['status'], $validStatuses, true)) {
        $errors[] = 'Please select a valid student status.';
    }

    $validGenders = [
        'Female',
        'Male',
        'Other',
        'Prefer not to say'
    ];

    if (
        $f['gender'] !== '' &&
        !in_array($f['gender'], $validGenders, true)
    ) {
        $errors[] = 'Please select a valid gender.';
    }

    /*
     * SAVE STUDENT
     */
    if (!$errors) {
        $stmt = db()->prepare(
            'SELECT id
             FROM students
             WHERE (student_code = ? OR email = ?)
             AND id <> ?'
        );

        $stmt->execute([
            $f['student_code'],
            $f['email'],
            $id
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Student ID or email already exists.';
        } else {
            try {
                $keys = array_keys($f);

                $values = array_map(
                    static function ($key) use ($f) {
                        if (
                            in_array(
                                $key,
                                ['date_of_birth', 'admission_date'],
                                true
                            ) &&
                            $f[$key] === ''
                        ) {
                            return null;
                        }

                        return $f[$key];
                    },
                    $keys
                );

                if ($edit) {
                    $setParts = array_map(
                        static fn($key) => "`$key` = ?",
                        $keys
                    );

                    $sql = 'UPDATE students SET ' .
                        implode(', ', $setParts) .
                        ' WHERE id = ?';

                    db()->prepare($sql)->execute(
                        array_merge($values, [$id])
                    );

                    $action = 'student.updated';
                } else {
                    $columnNames = implode(
                        ', ',
                        array_map(
                            static fn($key) => "`$key`",
                            $keys
                        )
                    );

                    $placeholders = implode(
                        ', ',
                        array_fill(0, count($keys), '?')
                    );

                    $sql = "INSERT INTO students ($columnNames)
                            VALUES ($placeholders)";

                    $stmt = db()->prepare($sql);
                    $stmt->execute($values);

                    $id = (int)db()->lastInsertId();

                    $action = 'student.created';
                }

                /*
                 * ACTIVITY LOG
                 */
                db()->prepare(
                    'INSERT INTO activity_logs
                    (
                        admin_id,
                        action,
                        entity_type,
                        entity_id,
                        details,
                        ip_address
                    )
                    VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    $_SESSION['admin_id'] ?? null,
                    $action,
                    'student',
                    $id,
                    $f['student_code'],
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);

                if (function_exists('flash')) {
                    flash(
                        $edit
                            ? 'Student updated successfully.'
                            : 'Student added successfully.'
                    );
                }

                header('Location: dashboard.php');
                exit;

            } catch (Throwable $e) {
                $errors[] = 'Unable to save the student. Please check your database and try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="theme-color"
        content="#111827"
    >

    <title>
        <?= $edit ? 'Edit Student' : 'Add Student' ?> | SMS
    </title>

    <link rel="stylesheet" href="assets/css/app.css">

    <style>
        /* ==========================================
           STUDENT FORM RESPONSIVE IMPROVEMENTS
        ========================================== */

        .form-content {
            width: 100%;
            min-width: 0;
        }

        .pro-student-form {
            width: 100%;
            min-width: 0;
        }

        .photo-editor-card {
            display: grid;
            grid-template-columns: 190px minmax(0, 1fr);
            gap: 28px;
            align-items: center;
            padding: 26px;
            margin-bottom: 32px;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            background: linear-gradient(
                135deg,
                #ffffff 0%,
                #f8fafc 100%
            );
            box-sizing: border-box;
        }

        .photo-editor-preview {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 190px;
            aspect-ratio: 1 / 1;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 2px;
            box-sizing: border-box;
        }

        .photo-editor-preview img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-editor-preview img[hidden] {
            display: none !important;
        }

        .photo-editor-copy {
            min-width: 0;
        }

        .photo-kicker {
            display: inline-block;
            margin-bottom: 8px;
            color: #64748b;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .photo-editor-copy h2,
        .crop-dialog-head h2 {
            margin: 0 0 10px;
            color: #111827;
            font-size: clamp(19px, 2.5vw, 25px);
            line-height: 1.3;
        }

        .photo-editor-copy p,
        .crop-dialog-head p {
            margin: 0 0 18px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.7;
        }

        .photo-file-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 46px;
            padding: 0 20px;
            border: 0;
            border-radius: 12px;
            background: #111827;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition:
                transform .2s ease,
                background .2s ease,
                box-shadow .2s ease;
            box-sizing: border-box;
        }

        .photo-file-button:hover {
            background: #273449;
            box-shadow: 0 8px 20px rgba(17, 24, 39, .14);
            transform: translateY(-1px);
        }

        .photo-file-button:focus-visible,
        .crop-close:focus-visible,
        .crop-tools button:focus-visible {
            outline: 3px solid #93c5fd;
            outline-offset: 3px;
        }

        .photo-hint {
            display: block;
            margin-top: 12px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
        }

        /* ==========================================
           CROP MODAL
        ========================================== */

        .crop-dialog[hidden] {
            display: none !important;
        }

        .crop-dialog {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
            background: rgba(15, 23, 42, .76);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-sizing: border-box;
        }

        .crop-dialog-card {
            width: min(100%, 560px);
            max-height: calc(100dvh - 40px);
            overflow-y: auto;
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, .6);
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 25px 80px rgba(0, 0, 0, .3);
            box-sizing: border-box;
        }

        .crop-dialog-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .crop-dialog-head > div {
            min-width: 0;
        }

        .crop-dialog-head p {
            margin-bottom: 0;
        }

        .crop-close {
            flex: 0 0 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #f8fafc;
            color: #334155;
            font-size: 25px;
            cursor: pointer;
        }

        .crop-close:hover {
            background: #e2e8f0;
        }

        .crop-stage {
            position: relative;
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #e2e8f0;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.5);
        }

        #cropCanvas {
            display: block;
            width: 100%;
            height: auto;
            aspect-ratio: 1 / 1;
            touch-action: none;
            cursor: grab;
            user-select: none;
            -webkit-user-select: none;
        }

        #cropCanvas:active {
            cursor: grabbing;
        }

        .crop-tools {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        .crop-tools label {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
        }

        .crop-tools input[type="range"] {
            width: 100%;
            min-width: 0;
            accent-color: #111827;
            cursor: pointer;
        }

        .crop-tools .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 14px;
            white-space: nowrap;
            cursor: pointer;
        }

        .crop-tools button:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        /* ==========================================
           FORM GRID
        ========================================== */

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .form-grid > label {
            min-width: 0;
        }

        .form-grid input,
        .form-grid select,
        .form-grid textarea {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .form-grid .full-field {
            grid-column: 1 / -1;
        }

        .form-section-title {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin: 32px 0 20px;
        }

        .form-section-title > span {
            flex: 0 0 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: #f1f5f9;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
        }

        .form-section-title h2 {
            margin: 0 0 5px;
            color: #111827;
            font-size: 17px;
        }

        .form-section-title p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
        }

        .field-help {
            display: block;
            margin: 6px 0;
            color: #64748b;
            font-size: 12px;
            font-weight: 400;
            line-height: 1.5;
        }

        .form-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 30px;
            padding-top: 22px;
            border-top: 1px solid #e5e7eb;
        }

        .form-actions .btn {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* ==========================================
           TABLET
        ========================================== */

        @media (max-width: 900px) {
            .photo-editor-card {
                grid-template-columns: 150px minmax(0, 1fr);
                gap: 20px;
                padding: 20px;
            }

            .photo-editor-preview {
                max-width: 150px;
            }

            .crop-dialog-card {
                width: min(100%, 520px);
            }
        }

        /* ==========================================
           MOBILE
        ========================================== */

        @media (max-width: 600px) {
            .photo-editor-card {
                grid-template-columns: minmax(0, 1fr);
                gap: 18px;
                padding: 16px;
                border-radius: 16px;
            }

            .photo-editor-preview {
                width: 150px;
                max-width: 100%;
                margin: 0 auto;
                border-radius: 16px;
            }

            .photo-editor-copy {
                text-align: center;
            }

            .photo-editor-copy h2 {
                font-size: 21px;
            }

            .photo-editor-copy p {
                font-size: 13px;
            }

            .photo-file-button {
                width: 100%;
                min-height: 48px;
            }

            .form-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 16px;
            }

            .form-grid .full-field {
                grid-column: auto;
            }

            .form-section-title {
                gap: 11px;
                margin-top: 26px;
            }

            .form-section-title > span {
                flex-basis: 34px;
                width: 34px;
                height: 34px;
            }

            .form-actions {
                display: grid;
                grid-template-columns: minmax(0, 1fr);
            }

            .form-actions .btn {
                width: 100%;
                box-sizing: border-box;
            }

            .crop-dialog {
                align-items: center;
                padding: 10px;
            }

            .crop-dialog-card {
                width: 100%;
                max-height: calc(100dvh - 20px);
                padding: 16px;
                border-radius: 18px;
            }

            .crop-dialog-head {
                gap: 10px;
                margin-bottom: 14px;
            }

            .crop-dialog-head h2 {
                font-size: 20px;
            }

            .crop-dialog-head p {
                font-size: 12px;
                line-height: 1.5;
            }

            .crop-stage {
                max-width: min(100%, 390px);
            }

            .crop-tools {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .crop-tools label {
                grid-column: 1 / -1;
                margin-bottom: 2px;
            }

            .crop-tools .btn {
                width: 100%;
                min-width: 0;
                padding: 0 8px;
                box-sizing: border-box;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .photo-file-button {
                transition: none;
            }
        }
    </style>
</head>

<body class="dashboard-page">

<aside class="sidebar">

    <a class="brand" href="dashboard.php">
        <span class="brand-mark">S</span>
        <span>
            SMS
            <small>STUDENT MANAGEMENT</small>
        </span>
    </a>

    <div class="side-label">WORKSPACE</div>

    <a class="side-link" href="dashboard.php">
        ▦ &nbsp; Dashboard
    </a>

    <a class="side-link active" href="student-form.php">
        ＋ &nbsp; Add Student
    </a>

    <div class="side-label">SYSTEM</div>

    <a class="side-link" href="index.php">
        ↗ &nbsp; Public Website
    </a>

    <div class="sidebar-bottom">
        <div class="admin-avatar">S</div>

        <div>
            <strong>
                <?= h($_SESSION['admin_name'] ?? 'Administrator') ?>
            </strong>
            <small>Administrator</small>
        </div>

        <a class="logout-link" href="logout.php" title="Logout">↪</a>
    </div>

</aside>

<main class="dashboard-main">

    <header class="dash-topbar">
        <div>
            <button
                class="sidebar-mobile-toggle"
                type="button"
                aria-label="Toggle sidebar"
            >☰</button>

            <span class="crumb">
                Workspace <b>/</b> Student record
            </span>
        </div>

        <a class="btn btn-ghost" href="dashboard.php">
            ← Back to directory
        </a>
    </header>

    <div class="dash-content form-content">

        <div class="dash-heading">
            <div>
                <div class="eyebrow">STUDENT INFORMATION</div>

                <h1>
                    <?= $edit ? 'Edit student' : 'Add a student' ?>
                </h1>

                <p>
                    Enter accurate information. Required fields are marked *.
                </p>
            </div>
        </div>

        <?php foreach ($errors as $error): ?>
            <div class="notice notice-error">
                <?= h($error) ?>
            </div>
        <?php endforeach; ?>

        <form
            method="post"
            class="panel form-panel pro-student-form"
            id="studentForm"
        >

            <input
                type="hidden"
                name="_csrf"
                value="<?= h(csrf()) ?>"
            >

            <input
                type="hidden"
                name="cropped_photo"
                id="cropped_photo"
                value=""
            >

            <!-- ======================================
                 RESPONSIVE PHOTO EDITOR
            ======================================= -->

            <section
                class="photo-editor-card"
                aria-label="Student photo"
            >

                <div class="photo-editor-preview" id="photoPreview">

                    <?php
                    $existingPhoto = '';

                    if (!empty($f['photo_path'])) {
                        $candidate = __DIR__ . '/' . $f['photo_path'];

                        if (is_file($candidate)) {
                            $existingPhoto = $f['photo_path'];
                        }
                    }
                    ?>

                    <?php if ($existingPhoto !== ''): ?>

                        <img
                            src="<?= h($existingPhoto) ?>"
                            alt="Current student photo"
                            id="photoPreviewImage"
                        >

                        <span id="photoPreviewFallback" hidden>
                            PHOTO
                        </span>

                    <?php else: ?>

                        <span id="photoPreviewFallback">
                            PHOTO
                        </span>

                        <img
                            id="photoPreviewImage"
                            alt="Student photo preview"
                            hidden
                        >

                    <?php endif; ?>

                </div>

                <div class="photo-editor-copy">

                    <span class="photo-kicker">
                        STUDENT PORTRAIT
                    </span>

                    <h2>Make the profile personal</h2>

                    <p>
                        Choose a clear portrait. Adjust the zoom,
                        reposition the image and preview the square
                        crop before saving.
                    </p>

                    <label
                        class="photo-file-button"
                        for="studentPhotoFile"
                    >
                        ＋ &nbsp; Choose student photo
                    </label>

                    <input
                        type="file"
                        id="studentPhotoFile"
                        accept="image/jpeg,image/png,image/webp"
                        hidden
                    >

                    <span class="photo-hint">
                        JPG, PNG or WebP · Maximum 6 MB · Square portrait
                    </span>

                </div>

            </section>

            <!-- ======================================
                 CROP DIALOG
            ======================================= -->

            <div
                id="cropDialog"
                class="crop-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="cropDialogTitle"
                hidden
            >

                <div class="crop-dialog-card">

                    <div class="crop-dialog-head">

                        <div>
                            <span class="photo-kicker">
                                IMAGE STUDIO
                            </span>

                            <h2 id="cropDialogTitle">
                                Crop student portrait
                            </h2>

                            <p>
                                Drag the image to reposition it.
                                Use zoom to adjust the frame.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="crop-close"
                            id="cropCancel"
                            aria-label="Cancel cropping"
                        >×</button>

                    </div>

                    <div class="crop-stage">
                        <canvas
                            id="cropCanvas"
                            width="640"
                            height="640"
                            aria-label="Student photo cropping area"
                        ></canvas>
                    </div>

                    <div class="crop-tools">

                        <label for="cropZoom">
                            Zoom
                            <input
                                id="cropZoom"
                                type="range"
                                min="1"
                                max="3"
                                value="1"
                                step="0.01"
                            >
                        </label>

                        <button
                            type="button"
                            class="btn btn-ghost"
                            id="cropReset"
                        >
                            Reset
                        </button>

                        <button
                            type="button"
                            class="btn btn-primary"
                            id="cropApply"
                        >
                            Use this photo →
                        </button>

                    </div>

                </div>

            </div>

            <!-- ======================================
                 SECTION 01: BASIC INFORMATION
            ======================================= -->

            <div class="form-section-title">
                <span>01</span>

                <div>
                    <h2>Basic information</h2>
                    <p>Student identity and contact details</p>
                </div>
            </div>

            <div class="form-grid">

                <?php
                $basicFields = [
                    'student_code'  => 'Student ID *',
                    'first_name'    => 'First name *',
                    'last_name'     => 'Last name *',
                    'email'         => 'Email address *',
                    'phone'         => 'Phone',
                    'date_of_birth' => 'Date of birth'
                ];
                ?>

                <?php foreach ($basicFields as $key => $label): ?>

                    <label>
                        <?= h($label) ?>

                        <input
                            name="<?= h($key) ?>"
                            value="<?= h($f[$key]) ?>"
                            <?php if ($key === 'email'): ?>
                                type="email"
                                autocomplete="email"
                            <?php elseif ($key === 'date_of_birth'): ?>
                                type="date"
                            <?php else: ?>
                                type="text"
                            <?php endif; ?>
                            <?= str_contains($label, '*') ? 'required' : '' ?>
                        >
                    </label>

                <?php endforeach; ?>

                <label>
                    Gender

                    <select name="gender">
                        <option value="">Select gender</option>

                        <?php foreach (
                            ['Female', 'Male', 'Other', 'Prefer not to say']
                            as $gender
                        ): ?>

                            <option
                                value="<?= h($gender) ?>"
                                <?= $f['gender'] === $gender ? 'selected' : '' ?>
                            >
                                <?= h($gender) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </label>

            </div>

            <!-- ======================================
                 SECTION 02: ACADEMIC INFORMATION
            ======================================= -->

            <div class="form-section-title">
                <span>02</span>

                <div>
                    <h2>Academic information</h2>
                    <p>Class placement and record status</p>
                </div>
            </div>

            <div class="form-grid">

                <label>
                    Class / Grade *

                    <input
                        name="class_name"
                        required
                        value="<?= h($f['class_name']) ?>"
                        placeholder="e.g. Grade 10"
                    >
                </label>

                <label>
                    Section

                    <input
                        name="section_name"
                        value="<?= h($f['section_name']) ?>"
                        placeholder="e.g. A"
                    >
                </label>

                <label>
                    Admission date

                    <input
                        type="date"
                        name="admission_date"
                        value="<?= h($f['admission_date']) ?>"
                    >
                </label>

                <label>
                    Status

                    <select name="status">

                        <?php foreach (
                            ['Active', 'Inactive', 'Graduated', 'Transferred']
                            as $status
                        ): ?>

                            <option
                                value="<?= h($status) ?>"
                                <?= $f['status'] === $status ? 'selected' : '' ?>
                            >
                                <?= h($status) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </label>

            </div>

            <!-- ======================================
                 SECTION 03: GUARDIAN AND ADDRESS
            ======================================= -->

            <div class="form-section-title">
                <span>03</span>

                <div>
                    <h2>Guardian & address</h2>
                    <p>Contact details and additional notes</p>
                </div>
            </div>

            <div class="form-grid">

                <label>
                    Guardian name

                    <input
                        name="guardian_name"
                        value="<?= h($f['guardian_name']) ?>"
                    >
                </label>

                <label>
                    Guardian phone

                    <input
                        name="guardian_phone"
                        value="<?= h($f['guardian_phone']) ?>"
                    >
                </label>

                <label class="full-field">
                    Address

                    <textarea
                        name="address"
                        rows="3"
                    ><?= h($f['address']) ?></textarea>
                </label>

                <label class="full-field">
                    Notes

                    <textarea
                        name="notes"
                        rows="3"
                    ><?= h($f['notes']) ?></textarea>
                </label>

            </div>

            <!-- ======================================
                 SECTION 04: PORTFOLIO CONTENT
            ======================================= -->

            <div class="form-section-title">
                <span>04</span>

                <div>
                    <h2>Portfolio content</h2>
                    <p>
                        Information displayed on the student's public portfolio
                    </p>
                </div>
            </div>

            <div class="form-grid">

                <label class="full-field">
                    Qualifications

                    <small class="field-help">
                        Add one qualification per line.
                    </small>

                    <textarea
                        name="qualifications"
                        rows="4"
                        placeholder="Certificates, qualifications and academic milestones"
                    ><?= h($f['qualifications']) ?></textarea>
                </label>

                <label class="full-field">
                    Achievements & highlights

                    <small class="field-help">
                        Add one achievement per line.
                    </small>

                    <textarea
                        name="achievements"
                        rows="4"
                        placeholder="Awards, competitions, leadership roles and achievements"
                    ><?= h($f['achievements']) ?></textarea>
                </label>

            </div>

            <!-- ======================================
                 FORM ACTIONS
            ======================================= -->

            <div class="form-actions">

                <a
                    class="btn btn-ghost"
                    href="dashboard.php"
                >
                    Cancel
                </a>

                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    <?= $edit ? 'Save changes' : 'Create student record' ?>
                    →
                </button>

            </div>

        </form>

        <div class="dashboard-foot">
            <span>
                © <?= date('Y') ?> Student Management System (SMS)
            </span>

            <span>PHP · MySQL</span>
        </div>

    </div>

</main>

<script src="assets/js/app.js"></script>
<script src="assets/js/photo-editor.js?v=3"></script>

</body>
</html>