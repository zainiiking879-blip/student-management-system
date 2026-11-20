# Student Management System (SMS) — Student Portfolio Edition

A PHP + MySQL student administration application with a public, read-only student portfolio directory and a protected admin workspace.

## What's included

- Premium responsive public homepage with student cards, cover styling, animated campus announcement ticker, search, class and section filters.
- Public student profile pages with student portrait, class/section, student ID, admission date, qualifications and achievements.
- Public profile pages intentionally omit private contact, guardian, address and internal notes fields.
- Admin-only create/edit/delete workflows and dashboard analytics.
- Student photo upload with an in-browser square crop editor, drag-to-position, zoom and preview.
- Qualification and achievement fields, each supporting one item per line.
- Upload directory configured to block PHP execution and directory listing.

## Local XAMPP setup

1. Extract this folder to `C:\xampp\htdocs\student-management-system`.
2. Start Apache and MySQL in XAMPP.
3. In phpMyAdmin, create/use the database `auspify_sms`.
4. For a fresh install, import `database/schema.sql`.
5. For an existing SMS database, run `database/migration_student_portfolio.sql` once in phpMyAdmin. It checks for existing portfolio columns before adding missing ones.
6. Confirm the MySQL host/port/user/password in `config/config.php` match your local XAMPP setup. The current default port in this project is `3307`.
7. Open `http://localhost/student-management-system/` for the public directory. Use `login.php` to access the admin workspace.

## Admin workflow

Open the dashboard and choose Add Student or edit an existing record. Choose a portrait, reposition/zoom it in the crop studio, then select Use this photo. Add qualifications and achievements one per line; these appear on the public portfolio. Guardian/contact information and notes are kept out of public profiles.

## Notes

- Public users can browse active students only and cannot edit records.
- The project has been PHP syntax-checked. Verify database connectivity and test the migration on a backup before using it with important production data.
