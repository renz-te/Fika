====================================================================
FIKA CAFÉ — SHARED HOSTING DATABASE IMPORT PACKAGE
====================================================================
Generated: 2026-10-28 19:43:07
Target DB Collation: utf8mb4_unicode_ci
Foreign Keys: SET FOREIGN_KEY_CHECKS=0 included in all files
Total Files: 58 SQL files + verification

IMPORT INSTRUCTIONS:
--------------------
Option A (cPanel / phpMyAdmin):
  1. Log in to phpMyAdmin on your hosting provider.
  2. Select your target application database.
  3. Go to the 'Import' tab.
  4. Import the SQL files sequentially from 01 to 58 (or in batch if supported).
  5. Finally, run 99_verify_counts.sql in the SQL tab to verify row counts.

Option B (Command Line / SSH):
  Execute the following command in terminal from this folder:
  for file in *.sql; do
    echo "Importing $file...";
    mysql -u [USERNAME] -p[PASSWORD] [DATABASE_NAME] < "$file";
  done

EXACT FILE IMPORT ORDER & INVENTORY:
------------------------------------
  01. 01_schema_migrations.sql         (TABLE) Rows: 50     Size: 5210 bytes
  02. 02_roles.sql                     (TABLE) Rows: 10     Size: 3469 bytes
  03. 03_permissions.sql               (TABLE) Rows: 22     Size: 4226 bytes
  04. 04_role_permissions.sql          (TABLE) Rows: 136    Size: 9047 bytes
  05. 05_branches.sql                  (TABLE) Rows: 3      Size: 3081 bytes
  06. 06_expense_categories.sql        (TABLE) Rows: 7      Size: 2463 bytes
  07. 07_categories.sql                (TABLE) Rows: 8      Size: 2754 bytes
  08. 08_inventory_items.sql           (TABLE) Rows: 27     Size: 5033 bytes
  09. 09_suppliers.sql                 (TABLE) Rows: 1      Size: 2721 bytes
  10. 10_leave_types.sql               (TABLE) Rows: 6      Size: 2683 bytes
  11. 11_holidays.sql                  (TABLE) Rows: 0      Size: 2296 bytes
  12. 12_doc_counters.sql              (TABLE) Rows: 1      Size: 2662 bytes
  13. 13_job_openings.sql              (TABLE) Rows: 4      Size: 3899 bytes
  14. 14_applicants.sql                (TABLE) Rows: 20     Size: 7170 bytes
  15. 15_applicant_rate_limits.sql     (TABLE) Rows: 8      Size: 2741 bytes
  16. 16_applicant_logs.sql            (TABLE) Rows: 1      Size: 2529 bytes
  17. 17_interviews.sql                (TABLE) Rows: 0      Size: 2779 bytes
  18. 18_interview_scorecards.sql      (TABLE) Rows: 0      Size: 2926 bytes
  19. 19_login_attempts.sql            (TABLE) Rows: 43     Size: 4614 bytes
  20. 20_contribution_rates.sql        (TABLE) Rows: 69     Size: 7246 bytes
  21. 21_employees.sql                 (TABLE) Rows: 33     Size: 14788 bytes
  22. 22_users.sql                     (TABLE) Rows: 41     Size: 9956 bytes
  23. 23_devices.sql                   (TABLE) Rows: 9      Size: 4246 bytes
  24. 24_employee_documents.sql        (TABLE) Rows: 0      Size: 2864 bytes
  25. 25_settings.sql                  (TABLE) Rows: 19     Size: 4051 bytes
  26. 26_audit_logs.sql                (TABLE) Rows: 1179   Size: 143314 bytes
  27. 27_pending_bonuses.sql           (TABLE) Rows: 0      Size: 2648 bytes
  28. 28_products.sql                  (TABLE) Rows: 21     Size: 5410 bytes
  29. 29_modifiers.sql                 (TABLE) Rows: 42     Size: 5941 bytes
  30. 30_recipes.sql                   (TABLE) Rows: 66     Size: 8370 bytes
  31. 31_branch_products.sql           (TABLE) Rows: 12     Size: 3431 bytes
  32. 32_inventory_stock.sql           (TABLE) Rows: 81     Size: 9334 bytes
  33. 33_inventory_ledger.sql          (TABLE) Rows: 8139   Size: 1029090 bytes
  34. 34_inventory_adjustments.sql     (TABLE) Rows: 0      Size: 3379 bytes
  35. 35_purchase_orders.sql           (TABLE) Rows: 0      Size: 3321 bytes
  36. 36_purchase_order_items.sql      (TABLE) Rows: 0      Size: 2747 bytes
  37. 37_po_receipts.sql               (TABLE) Rows: 0      Size: 2650 bytes
  38. 38_shifts.sql                    (TABLE) Rows: 405    Size: 64916 bytes
  39. 39_open_shifts.sql               (TABLE) Rows: 0      Size: 3316 bytes
  40. 40_leave_requests.sql            (TABLE) Rows: 6      Size: 4349 bytes
  41. 41_attendance_logs.sql           (TABLE) Rows: 376    Size: 59442 bytes
  42. 42_attendance_certifications.sql (TABLE) Rows: 12     Size: 4240 bytes
  43. 43_payroll_runs.sql              (TABLE) Rows: 10     Size: 4567 bytes
  44. 44_payroll_items.sql             (TABLE) Rows: 50     Size: 9203 bytes
  45. 45_branch_budgets.sql            (TABLE) Rows: 9      Size: 3669 bytes
  46. 46_expenses.sql                  (TABLE) Rows: 57     Size: 16012 bytes
  47. 47_cash_sessions.sql             (TABLE) Rows: 0      Size: 3034 bytes
  48. 48_orders.sql                    (TABLE) Rows: 969    Size: 161950 bytes
  49. 49_order_items.sql               (TABLE) Rows: 1746   Size: 126128 bytes
  50. 50_order_item_modifiers.sql      (TABLE) Rows: 1609   Size: 86095 bytes
  51. 51_order_discounts.sql           (TABLE) Rows: 0      Size: 2604 bytes
  52. 52_order_audit.sql               (TABLE) Rows: 0      Size: 2809 bytes
  53. 53_order_kiosk_tokens.sql        (TABLE) Rows: 0      Size: 2561 bytes
  54. 54_payments.sql                  (TABLE) Rows: 969    Size: 151551 bytes
  55. 55_order_ledger_deductions.sql   (TABLE) Rows: 962    Size: 39079 bytes
  56. 56_reconciliation_logs.sql       (TABLE) Rows: 0      Size: 2661 bytes
  57. 57_v_cogs_monthly.sql            (VIEW ) Rows: -      Size: 746 bytes
  58. 58_v_revenue_monthly.sql         (VIEW ) Rows: -      Size: 685 bytes

====================================================================
