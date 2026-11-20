# Student JSON API

Base: `/api/students.php`. All routes require a logged-in admin session. Write routes require header `X-CSRF-TOKEN` equal to the `_csrf` value from an authenticated page.

- `GET /api/students.php?q=ayesha&page=1&limit=25` — list/search
- `GET /api/students.php?id=1` — one student
- `POST /api/students.php` — create JSON record
- `PUT /api/students.php?id=1` — update JSON record
- `PATCH /api/students.php?id=1` — update JSON record
- `DELETE /api/students.php?id=1` — delete record

Required fields for create/update: `student_code`, `first_name`, `last_name`, `email`, `class_name`. Other schema fields are optional. JSON response includes `success`, and either `data`, `message`, `meta` or `error`.