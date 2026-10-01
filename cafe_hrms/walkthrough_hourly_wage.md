# Walkthrough: Hourly Wage Standardization

I've successfully completely overhauled the payroll compensation logic and forms across the HRMS system. The system now strictly tracks pay via an **Hourly Wage** structure for all employees, fixing the previous payroll inconsistencies.

### 1. Form & UI Updates
- **Public Application Intake (`careers.php`):** Successfully deleted all expected salary/compensation fields from the public application form to restrict applicant data visibility.
- **ATS Onboarding Gate (`applications.php`):** The `hourly_rate` input was safely isolated and moved into the internal "Onboard Employee" modal. This input is now completely invisible to the public applicant and only surfaces when HR finalizes the hiring status.
- **Employee & Raise Profiles (`employee_form.php`, `raise_form.php`, `rehire_form.php`):** Re-labeled internal compensation inputs to "Hourly Rate (₱)" and added dynamic real-time projection scripts. As HR admins type in an hourly rate, it immediately projects the *Estimated Monthly Income* beneath the box.
- **Display Views (`employee_view.php`):** Profiles now explicitly display the employee's `Hourly Rate` alongside a projected monthly equivalent (e.g., `₱114.40/hr (Est. ₱20,134.40/mo)`).

### 2. Database Migration
- Fully verified that the `employees`, `applicants`, and `payroll` tables use the new `hourly_rate` column format.
- Executed a script to normalize the corrupted Full-Time values back to their correct mathematically-derived hourly rates, multiplying the broken `0.65` rates by `176` (yielding `₱114.40/hr`). Part-Time values remain unaffected (₱100/hr).

### 3. Payroll Engine Replacement
- **Unified Engine (`payroll.php`):** Rewrote the Draft Generation logic entirely. The rigid "base pay minus absences" formula has been replaced with a perfectly accurate time-tracking formula for *all* employees (regardless of Full-Time/Part-Time status).
- **Formula Deployed:** `Gross Pay = (Total Hours Worked + (Approved Leave Days * 8)) * hourly_rate`.

Everything is locked in and ready to go. You can test out generating a new Payroll Draft or viewing any employee's compensation profile!
