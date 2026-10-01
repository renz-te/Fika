# Café HRMS

A modern Human Resource Management System for café operations built with HTML, CSS, JavaScript, PHP, MySQL, and Bootstrap.

## Features

- Secure login with role-based access
- Employee management with add/edit/archive/delete
- Attendance clock in/out, overtime, and late detection
- Leave request and approval workflow
- Payroll generation with deductions and net pay calculation
- Recruitment tracking and applicant status
- Reports with CSV export and printable views
- Settings, backup, and restore utilities
- Notification-ready and premium café-themed dashboard UI

## Installation

1. Create a MySQL database named `hrms`.
2. Update `config.php` with your database credentials.
3. Place the project folder in your PHP server root.
4. Open `setup.php` in your browser and run the setup.
5. Default Super Admin login:
   - Email: `admin@cafehrms.local`
   - Password: `Admin123!`
6. Remove or protect `setup.php` after setup.

## Project Structure

- `index.php` - redirects to login
- `login.php`, `forgot_password.php`, `reset_password.php` - authentication
- `dashboard.php` - main analytics dashboard
- `employees.php`, `employee_form.php`, `employee_action.php` - employee CRUD
- `attendance.php` - attendance management
- `leave.php` - leave requests
- `payroll.php` - payroll generation
- `applicants.php` - recruitment tracking
- `reports.php`, `export.php` - reporting and export
- `settings.php`, `backup_restore.php` - configuration and backup/restore
- `setup.php` - database initialization script
- `assets/css/style.css`, `assets/js/app.js` - styles and client scripts
- `db/hrms.sql` - complete database schema

## Notes

- Update `config.php` and `app_url` setting after deployment.
- Use a real SMTP server in `send_email` for password emails.
- The system is responsive and designed for desktop, tablet, and mobile.
