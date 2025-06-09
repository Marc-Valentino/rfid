# RFID Attendance System - Agent Guide

## Commands
- **Test**: Run tests manually by accessing PHP files via XAMPP localhost
- **Lint**: Use PHP linter: `php -l filename.php`
- **Server**: Start XAMPP Apache/MySQL services
- **Database**: Import schema with: `mysql -u root rfid_attendance_system < schema.sql`

## Code Style
- **Files**: PHP files use `.php` extension
- **Classes**: PascalCase (e.g., `AttendanceSystem`, `Database`)
- **Methods**: camelCase (e.g., `getStudentByRFID`, `recordAttendance`)
- **Variables**: snake_case for DB columns, camelCase for PHP vars
- **Constants**: UPPERCASE (e.g., `APP_NAME`, `BASE_URL`)
- **Database**: Use PDO with prepared statements, parameter binding
- **Sessions**: Start with `session_start()`, use `$_SESSION` array
- **Error Handling**: Use try-catch blocks, log errors with `error_log()`
- **Security**: Sanitize inputs, use CSRF tokens, password hashing
- **HTML**: Bootstrap 5 classes, FontAwesome icons, responsive design
- **Structure**: MVC-like pattern with config/, includes/, admin/, api/ folders

## Database
- MySQL/MariaDB with foreign key constraints
- Primary connection via `Database` class in `config/database.php`
- Main tables: users, students, courses, attendance_records, rfid_cards
