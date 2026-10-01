# Implementation Plan: Hourly Wage Standardization

## Goal
Transition the payroll system to universally use an **Hourly Wage** for all employees (both Full-Time and Part-Time) to fix payroll inconsistencies, while still displaying the "Total Monthly Income" as a projection.

## User Review Required

> [!IMPORTANT]
> **Data Migration Decision**
> To make this switch, we need to convert the existing `salary_rate` data in the database. 
> - For **Part-Time** employees, the number in the database is currently assumed to be an hourly rate.
> - For **Full-Time** employees, the number is currently a monthly salary (e.g., 20,000). 
> 
> My plan is to run a one-time database migration that takes every Full-Time employee's current monthly salary and converts it to their new Hourly Wage (formula: `Monthly Salary ÷ 176 hours` (assuming 22 days * 8 hours)). Part-timers will be left as-is. Do you agree with this conversion formula?

## Proposed Changes

### 1. Database Schema
#### [MODIFY] Database Structure
- Rename the `salary_rate` column to `hourly_rate` across the `employees`, `applicants`, and `payroll` tables to avoid future confusion.
- Run a migration script to divide all existing Full-Time `salary_rate` values by 176 to calculate their new `hourly_rate`.

### 2. Form & UI Updates
#### [MODIFY] `employee_form.php`, `applications.php`, `rehire_form.php`, `raise_form.php`
- Change the input label from "Salary Rate" to "Hourly Rate (₱)".
- Add a dynamic JavaScript snippet below the input field that automatically calculates and displays: *"Estimated Monthly Income: ₱XX,XXX"* (Hourly Rate × 8 hours × 22 days) so you can still easily see and negotiate the monthly take-home.

#### [MODIFY] `employee_view.php`, `employees.php`, `ess.php`
- Update the display text to show the Hourly Rate and the Estimated Monthly Income side-by-side, e.g., `₱110.00/hr (Est. ₱19,360/mo)`.

### 3. Payroll Calculation Engine
#### [MODIFY] `payroll.php`
- Completely replace the old Full-Time "base pay minus absences" logic.
- **New Unified Logic:** 
  1. Calculate `Total Hours Worked` in the given period from the `attendance` table.
  2. For every approved paid `leave` day in that period, add **8 hours** to the total.
  3. `Gross Pay = Total Hours * hourly_rate`.
- This ensures Payroll is 100% accurate down to the minute, whether they are Full-Time, Part-Time, late, or under-time.

## Verification Plan

### Manual Verification
- We will view an existing Full-Time employee's profile to confirm their old monthly salary was successfully converted into an hourly rate and that the UI shows the correct Estimated Monthly Income.
- We will generate a Payroll Draft for a Full-Time employee and verify that the system correctly sums their hours from the attendance logs and multiplies it by their new hourly wage.
