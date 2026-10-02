# Fika System Analysis

## 1. Database Schema (CREATE TABLE statements)
``sql
CREATE TABLE `activity_logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(150) NOT NULL,
  `details` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `announcements` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `image` varchar(500) DEFAULT NULL,
  `file_attachment` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `applicants` (
  `id` int NOT NULL,
  `first_name` varchar(150) DEFAULT NULL,
  `last_name` varchar(150) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `position_applied` varchar(120) NOT NULL,
  `stage` varchar(50) NOT NULL DEFAULT 'Initial Interview',
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `valid_id_photo` varchar(500) DEFAULT NULL,
  `resume` varchar(500) DEFAULT NULL,
  `reference_name` varchar(100) DEFAULT NULL,
  `reference_phone` varchar(50) DEFAULT NULL,
  `valid_id_back_photo` varchar(500) DEFAULT NULL,
  `expected_hourly_rate` decimal(12,2) DEFAULT NULL,
  `agreed_hourly_rate` decimal(12,2) DEFAULT NULL,
  `education_level` varchar(50) DEFAULT NULL,
  `school_name` varchar(150) DEFAULT NULL,
  `school_address` varchar(255) DEFAULT NULL,
  `year_graduated` varchar(20) DEFAULT NULL,
  `education_status` varchar(50) DEFAULT NULL,
  `experience_level` varchar(50) DEFAULT NULL,
  `course_diploma` varchar(150) DEFAULT NULL,
  `employment_category` varchar(50) DEFAULT 'Full-Time',
  `birthdate` date DEFAULT NULL,
  `address` text,
  `sex` varchar(20) DEFAULT '',
  `nationality` varchar(100) DEFAULT '',
  `preferred_schedule` varchar(50) DEFAULT 'Any',
  `branch_id` int DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `applicant_logs` (
  `id` int NOT NULL,
  `applicant_id` int NOT NULL,
  `user_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attendance` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `time_in` datetime NOT NULL,
  `time_out` datetime DEFAULT NULL,
  `break_in` datetime DEFAULT NULL,
  `break_out` datetime DEFAULT NULL,
  `is_late` tinyint(1) NOT NULL DEFAULT '0',
  `overtime_hours` decimal(6,2) NOT NULL DEFAULT '0.00',
  `undertime_hours` decimal(6,2) NOT NULL DEFAULT '0.00',
  `status` varchar(50) NOT NULL DEFAULT 'Present',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `branches` (
  `id` int NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` text,
  `contact_number` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `branch_budgets` (
  `id` int NOT NULL,
  `branch_id` int NOT NULL,
  `budget_month` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'YYYY-MM format',
  `allocated_labor_budget` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `documents` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `document_type` varchar(120) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `email_verifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token` varchar(120) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `employees` (
  `id` int NOT NULL,
  `employee_id` varchar(80) NOT NULL,
  `first_name` varchar(150) DEFAULT NULL,
  `last_name` varchar(150) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `position` varchar(120) DEFAULT NULL,
  `department` varchar(120) DEFAULT NULL,
  `employment_type` varchar(50) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Active',
  `sick_leave_balance` int NOT NULL DEFAULT '14',
  `termination_reason` varchar(100) DEFAULT NULL,
  `termination_details` text,
  `termination_date` date DEFAULT NULL,
  `date_hired` date DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `hourly_rate` decimal(12,2) NOT NULL DEFAULT '0.00',
  `last_raise_date` date DEFAULT NULL,
  `last_bonus_date` date DEFAULT NULL,
  `address` text,
  `emergency_contact` varchar(200) DEFAULT NULL,
  `bank_account` varchar(120) DEFAULT NULL,
  `tin` varchar(50) DEFAULT NULL,
  `sss` varchar(50) DEFAULT NULL,
  `philhealth` varchar(50) DEFAULT NULL,
  `pagibig` varchar(50) DEFAULT NULL,
  `government_ids` varchar(255) DEFAULT NULL,
  `photo` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(50) DEFAULT NULL,
  `valid_id_back_photo` varchar(500) DEFAULT NULL,
  `valid_id_photo` varchar(500) DEFAULT NULL,
  `education_level` varchar(50) DEFAULT NULL,
  `school_name` varchar(150) DEFAULT NULL,
  `school_address` varchar(255) DEFAULT NULL,
  `year_graduated` varchar(20) DEFAULT NULL,
  `education_status` varchar(50) DEFAULT NULL,
  `course_diploma` varchar(150) DEFAULT NULL,
  `employment_category` varchar(50) DEFAULT 'Full-Time',
  `birthdate` date DEFAULT NULL,
  `sex` varchar(20) DEFAULT '',
  `nationality` varchar(100) DEFAULT '',
  `preferred_schedule` varchar(50) DEFAULT 'Any',
  `branch_id` int DEFAULT NULL,
  `employment_status` enum('Trainee','Probationary','Regular') NOT NULL DEFAULT 'Regular'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `equipment_assignments` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Released',
  `assigned_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `returned_at` datetime DEFAULT NULL,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `global_notifications` (
  `id` int NOT NULL,
  `message` text NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-bell',
  `color` varchar(50) DEFAULT 'primary',
  `link` varchar(255) DEFAULT NULL,
  `target_roles` varchar(255) NOT NULL,
  `target_user_id` int DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `read_by` int DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `interviews` (
  `id` int NOT NULL,
  `applicant_id` int NOT NULL,
  `interview_date` date NOT NULL,
  `interview_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `interviewer_id` int DEFAULT NULL,
  `stage` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Scheduled',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `interview_scorecards` (
  `id` int NOT NULL,
  `interview_id` int NOT NULL,
  `technical_score` int NOT NULL,
  `communication_score` int DEFAULT NULL,
  `reliability_score` int DEFAULT NULL,
  `culture_score` int NOT NULL,
  `problem_solving_score` int DEFAULT NULL,
  `strengths` text,
  `concerns` text,
  `recommendation` enum('Hire','Reject','Next Round','Global Pool') NOT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `hourly_wage` decimal(10,2) DEFAULT NULL,
  `monthly_wage` decimal(10,2) DEFAULT NULL,
  `global_pool_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `inventory` (
  `id` int NOT NULL,
  `branch_id` int NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT 'General',
  `unit` varchar(50) NOT NULL DEFAULT 'pcs',
  `stock_level` decimal(10,2) NOT NULL DEFAULT '0.00',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `reorder_level` decimal(10,2) NOT NULL DEFAULT '10.00',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `inventory_transactions` (
  `id` int NOT NULL,
  `branch_id` int NOT NULL,
  `inventory_id` int DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `type` enum('Restock','Write-off','Usage') NOT NULL,
  `quantity` int NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT 'Completed',
  `logged_by` int NOT NULL,
  `transaction_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `leaves` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `leave_type` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text,
  `medical_certificate` varchar(500) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `med_cert` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `leave_approvals` (
  `id` int NOT NULL,
  `leave_id` int NOT NULL,
  `approver_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_role` varchar(80) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `open_shifts` (
  `id` int NOT NULL,
  `original_employee_id` int NOT NULL,
  `shift_date` date NOT NULL,
  `shift_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Open',
  `claimed_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token` varchar(120) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `payroll` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `status` varchar(20) DEFAULT 'Draft',
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `hourly_rate` decimal(12,2) NOT NULL DEFAULT '0.00',
  `overtime_hours` decimal(6,2) NOT NULL DEFAULT '0.00',
  `overtime_pay` decimal(12,2) NOT NULL DEFAULT '0.00',
  `holiday_pay` decimal(12,2) NOT NULL DEFAULT '0.00',
  `night_differential` decimal(12,2) NOT NULL DEFAULT '0.00',
  `late_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `absent_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `bonus_amount` decimal(10,2) DEFAULT '0.00',
  `tax` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sss` decimal(12,2) NOT NULL DEFAULT '0.00',
  `philhealth` decimal(12,2) NOT NULL DEFAULT '0.00',
  `pagibig` decimal(12,2) NOT NULL DEFAULT '0.00',
  `gross_pay` decimal(14,2) NOT NULL DEFAULT '0.00',
  `deductions` decimal(14,2) NOT NULL DEFAULT '0.00',
  `net_pay` decimal(14,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pending_bonuses` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `performance_reviews` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `evaluator_id` int NOT NULL,
  `review_date` date NOT NULL,
  `is_leadership` tinyint(1) NOT NULL DEFAULT '0',
  `evaluation_type` varchar(50) NOT NULL DEFAULT 'Official',
  `score_kitchen` int DEFAULT NULL,
  `score_cashier` int DEFAULT NULL,
  `score_cleaning` int DEFAULT NULL,
  `score_inventory` int DEFAULT NULL,
  `score_floor_mgmt` int DEFAULT NULL,
  `score_staff_training` int DEFAULT NULL,
  `score_quality_control` int DEFAULT NULL,
  `score_reliability` int DEFAULT NULL,
  `score_setup` int DEFAULT NULL,
  `score_scheduling` int DEFAULT NULL,
  `score_recruitment` int DEFAULT NULL,
  `score_compliance` int DEFAULT NULL,
  `total_score` decimal(3,2) NOT NULL,
  `comments` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ;

CREATE TABLE `purchase_requests` (
  `id` int NOT NULL,
  `branch_id` int NOT NULL,
  `inventory_id` int DEFAULT NULL,
  `requested_by` int NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` int NOT NULL,
  `estimated_cost` decimal(10,2) NOT NULL,
  `status` enum('Pending','Approved','Rejected','Completed') DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `schedules` (
  `id` int NOT NULL,
  `employee_id` int DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Assigned',
  `shift_type` varchar(120) NOT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `assigned_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `branch_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `value` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `training` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Scheduled',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(200) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int NOT NULL,
  `employee_id` int DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  `branch_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
``

## 2. Folder Structure
``text
Folder PATH listing
Volume serial number is 00000009 741C:CA63
C:\LARAGON\WWW\FIKA\FIKA\CAFE_HRMS
¶   .htaccess
¶   account_management.php
¶   alter_schema.php
¶   applicants.php
¶   applicant_form.php
¶   applications.php
¶   attendance.php
¶   backup_restore.php
¶   careers.php
¶   check_all.php
¶   check_app.php
¶   check_branches.php
¶   check_db.php
¶   check_schema.php
¶   check_schema2.php
¶   check_unit.php
¶   check_users.php
¶   config.php
¶   create_db.php
¶   dashboard.php
¶   data_update.php
¶   data_update_reconfig.php
¶   db_corrections.php
¶   db_corrections2.php
¶   db_corrections3.php
¶   db_fix.php
¶   dump_interviews.php
¶   dump_schema.php
¶   employees.php
¶   employee_action.php
¶   employee_form.php
¶   employee_view.php
¶   ess.php
¶   ess_board.php
¶   export.php
¶   factory_reset.php
¶   favicon.svg
¶   finance_budgeting.php
¶   finance_reports.php
¶   find_schema.php
¶   fix_remaining.php
¶   fix_syntax.php
¶   flowcharts.html
¶   forgot_password.php
¶   functions.php
¶   implementation_plan_hourly_wage.md
¶   index.php
¶   init.php
¶   interview_workspace.php
¶   inventory.php
¶   kiosk.php
¶   leave.php
¶   list_dbs.php
¶   list_roles.php
¶   list_tables.php
¶   list_tables2.php
¶   list_tables_admin.php
¶   login.php
¶   logout.php
¶   menu_list.php
¶   migrate_db.php
¶   migrate_interviews.php
¶   migrate_names.php
¶   migrate_recipes.php
¶   payroll.php
¶   performance.php
¶   performance_form.php
¶   pos_dashboard.php
¶   procurement.php
¶   query
¶   raise_form.php
¶   README.md
¶   read_notif.php
¶   rehire_form.php
¶   replace_salary_rate.php
¶   reports.php
¶   reset_password.php
¶   run_migration.php
¶   settings.php
¶   setup.php
¶   shift_board.php
¶   show_tables.php
¶   system_accounts.php
¶   task.md
¶   terminate_form.php
¶   test_col.php
¶   update_kiosk_role.php
¶   verify.php
¶   walkthrough_hourly_wage.md
¶   
+---api
¶       convert_to_employee.php
¶       get_applicant_drawer.php
¶       inventory_crud.php
¶       menu_crud.php
¶       update_applicant_stage.php
¶       
+---assets
¶   +---css
¶   ¶       style.css
¶   ¶       
¶   +---js
¶           app.js
¶           
+---db
¶       hrms.sql
¶       
+---includes
¶   ¶   footer.php
¶   ¶   header.php
¶   ¶   
¶   +---PHPMailer
¶           Exception.php
¶           PHPMailer.php
¶           SMTP.php
¶           
+---uploads
    +---adjustments
    +---applicants
            id_1784443536_508.jpg
            id_1784444651_955.jpg
            id_1784449266_223.jpg
            id_1784464791_602.jpg
            id_1784465739_780.jpg
            id_1784467338_310.jpg
            id_1784511369_143.jpg
            id_1785178681_322.jpg
            id_1785226685_738.jpg
            id_1785255446_195.jpg
            id_1785306221_671.jpg
            id_1788701633_233.jpg
            id_1788702602_696.jpg
            id_1788703736_295.jpg
            id_1788704709_228.JPG
            id_back_1784445707_280.jpg
            id_back_1784449266_970.png
            id_back_1784464791_226.jpg
            id_back_1784465739_483.jpg
            id_back_1784467338_533.jpg
            id_back_1784511369_615.jpg
            id_back_1785178681_628.jpg
            id_back_1785226685_777.jpg
            id_back_1785255446_514.jpg
            id_back_1785306221_194.bmp
            id_back_1788701633_974.jpg
            id_back_1788702602_333.jpg
            id_back_1788703736_598.jpg
            id_back_1788704709_347.jpg
            resume_1784443536_525.pdf
            resume_1784444651_600.pdf
            resume_1784449266_835.jpg
            resume_1784464791_158.pdf
            resume_1784465739_597.pdf
            resume_1784467338_416.pdf
            resume_1784511369_198.pdf
            resume_1785178681_792.pdf
            resume_1785226685_108.pdf
            resume_1785255446_365.pdf
            resume_1785306221_459.pdf
            resume_1788701633_748.pdf
            resume_1788702602_867.pdf
            resume_1788703736_629.pdf
            resume_1788704709_808.pdf
            


CAFE_POS

Folder PATH listing
Volume serial number is 000000E9 741C:CA63
C:\LARAGON\WWW\FIKA\FIKA\CAFE_POS
¶   cashier.php
¶   check_cafe.php
¶   check_cafe_pos.php
¶   check_dbs.php
¶   check_emp.php
¶   check_inv.php
¶   check_orders.php
¶   check_sessions.php
¶   database.php
¶   index.html
¶   kiosk.php
¶   list_tables.php
¶   schema.sql
¶   temp_mig.php
¶   temp_mig2.php
¶   update_db.php
¶   
+---api
¶       menu.php
¶       orders.php
¶       orders_cancel.php
¶       orders_complete.php
¶       orders_get.php
¶       orders_pay.php
¶       orders_pending.php
¶       pos_auth.php
¶       pos_sessions.php
¶       sales_summary.php
¶       
+---css
¶       style.css
¶       
+---js
        app.js
        cashier.js
        
``

## 3. Walkthrough.md
``markdown
# ATS Kanban Refactor Completed

I have successfully refactored the Applicant Tracking System's Kanban board to utilize a much cleaner, more scalable UI pattern!

## Changes Made

### 1. Cleaned up the Kanban Card (`applications.php`)
- Removed all bulky components (heavy forms, large red/blue alert blocks, inline edit buttons, and document links).
- The card now acts as a lightweight summary containing just the Candidate's Name, Position, Employment Category pill, and a small warning icon (`‚ö†Ô∏è`) if they have an overdue interview.
- Made the entire card interactive‚Äîhovering over a card slightly highlights the border, and clicking it triggers the new sliding drawer.

### 2. Implemented the Sliding Drawer Component
- Injected a fixed, right-aligned sliding drawer UI into the bottom of `applications.php`.
- The drawer remains hidden (`translate-x-full`) by default and uses smooth CSS transitions to slide into view when triggered, dimming the background with a backdrop blur overlay.

### 3. Created Dynamic AJAX Payload Logic (`api/get_applicant_drawer.php`)
- Built a secure backend endpoint to fetch the heavy payload for a specific candidate.
- All the logic that was stripped from the Kanban cards (large Missed/Next Interview alerts, Document links, Hire/Reject action buttons, "Move Forward" dropdown logic) is now safely rendered inside this endpoint.
- Because it's loaded dynamically upon a click event, it ensures that your pipeline actions are always referencing real-time database states, effectively preventing you from acting on stale data (e.g., if another HR manager moved a candidate 10 seconds ago).

You can now click on any applicant card in the Kanban view to watch the drawer seamlessly slide open and present the full candidate context!
``

## 4. Finance-related Files
The following files are related to Finance inside the cafe_hrms module:
- **finance_budgeting.php** (21 KB)
- **finance_reports.php** (5 KB)

*Note: The source code for these PHP files is too large to include inline here, but they are located in C:\laragon\www\Fika\Fika\cafe_hrms\.*

