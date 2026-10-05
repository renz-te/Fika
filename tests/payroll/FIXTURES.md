# Payroll Engine Test Fixtures

> [!WARNING]
> **NEEDS HUMAN VERIFICATION**
> This file contains the exact arithmetic calculations that test the purity of the payroll engine. Do not alter these without consulting HR or DOLE guidelines. All amounts are processed internally in centavos.

## Assumptions
- **Monthly standard hours**: 176 (22 days * 8 hours)
- **Hourly rate calculation**: `floor(monthly_base / 176)`
- **Daily rate calculation**: `floor(monthly_base / 22)`
- **Contributions**: Computed on a monthly basis, split 50/50 per cutoff. First cutoff = floor(monthly/2).

## Scenario 1: Full-time (Standard, Cutoff 1) (CHANGED - needs accountant re-verification)
- **Inputs**: Base ₱30,000/mo, PayType: MONTHLY, Absent: 0 days, 0 late, 0 OT, Contributions: Yes (Cutoff 1)
- **Daily Rate**: 30000 / 22 = ₱1,363.63
- **Hourly Rate**: 30000 / 176 = ₱170.45
- **Gross Pay**: ₱15,000.00
- **Contributions (Half)**:
  - SSS (4.5% of 30k = 1350): ₱675.00
  - PhilHealth (2.5% of 30k = 750): ₱375.00
  - Pag-IBIG (fixed 100): ₱50.00
  - Total: ₱1,100.00
- **Taxable Income**: 15,000 - 1100 = ₱13,900.00
- **Tax (Semi-monthly)**: Bracket 2 (10,416.67 to 16,666.67).
  - 15% of excess over 10416.67 = 15% of 3483.33 = ₱522.50
- **Net Pay**: 15000 - 1100 - 522.50 = **₱13,377.50**

## Scenario 1b: Full-time (Standard, Cutoff 2) (CHANGED - needs accountant re-verification)
- **Inputs**: Same as above, but Cutoff 2.
- Since 1350 / 2 is exactly 675, Cutoff 2 contributions are identical.
- **Net Pay**: **₱13,377.50**
- *Property Check: Cutoff 1 (1100) + Cutoff 2 (1100) = 2200 Monthly Total.*

## Scenario 2: 1 Absence (CHANGED - needs accountant re-verification)
- **Inputs**: Base ₱30,000/mo, PayType: MONTHLY, Absent: 1 day, 0 late, 0 OT, Contributions: Yes (Cutoff 1)
- **Absent Deduction**: 1 * 1363.63 = ₱1,363.63
- **Basic Pay**: 15000 - 1363.63 = ₱13,636.37
- **Contributions (Half)**: ₱1,100.00
- **Taxable Income**: 13,636.37 - 1100 = ₱12,536.37
- **Tax**: Bracket 2. 15% of (12536.37 - 10416.67) = 15% of 2119.70 = ₱317.96
- **Net Pay**: 13636.37 - 1100 - 317.96 = **₱12,218.41**

## Scenario 3: 2 Absences (CHANGED - needs accountant re-verification)
- **Inputs**: Base ₱30,000/mo, PayType: MONTHLY, Absent: 2 days, 0 late, 0 OT, Contributions: Yes (Cutoff 1)
- **Absent Deduction**: 2 * 1363.63 = ₱2,727.26
- **Basic Pay**: 15000 - 2727.26 = ₱12,272.74
- **Contributions (Half)**: ₱1,100.00
- **Taxable Income**: 12272.74 - 1100 = ₱11,172.74
- **Tax**: Bracket 2. 15% of (11172.74 - 10416.67) = 15% of 756.07 = ₱113.41
- **Net Pay**: 12272.74 - 1100 - 113.41 = **₱11,059.33**

## Scenario 4: Late Minutes (CHANGED - needs accountant re-verification)
- **Inputs**: Base ₱30,000/mo, PayType: MONTHLY, Absent: 0 days, Late: 45 minutes, 0 OT, Contributions: Yes (Cutoff 1)
- **Late Deduction**: 45 * (170.45 / 60) = 45 * 2.8408... = ₱127.83 (12783 centavos)
- **Basic Pay**: 15000 - 127.83 = ₱14,872.17
- **Contributions (Half)**: ₱1,100.00
- **Taxable Income**: 14872.17 - 1100 = ₱13,772.17
- **Tax**: Bracket 2. 15% of (13772.17 - 10416.67) = 15% of 3355.50 = ₱503.33
- **Net Pay**: 14872.17 - 1100 - 503.33 = **₱13,268.84**

## Scenario 5: Approved Unpaid Leave (CHANGED - needs accountant re-verification)
- **Inputs**: Base ₱30,000/mo, PayType: MONTHLY, Absent: 1 day (unpaid leave), 0 late, 0 OT, Contributions: Yes (Cutoff 1)
- *Identical calculation to Scenario 2 (1 Absence).*
- **Net Pay**: **₱12,218.41**

## Scenario 6: Approved Paid Leave (SIL) (CHANGED - needs accountant re-verification)
- **Inputs**: Base ₱30,000/mo, PayType: MONTHLY, Absent: 0 days (leave was paid), 0 late, 0 OT, Contributions: Yes (Cutoff 1)
- *Identical calculation to Scenario 1 (Full-time).*
- **Net Pay**: **₱13,377.50**

## Scenario 7: Hourly Part-timer (Unchanged)
- **Inputs**: Base ₱20,000/mo, PayType: HOURLY, Worked: 80 hours, Contributions: No
- **Hourly Rate**: 20000 / 176 = ₱113.63
- **Gross Pay**: 80 * 113.63 = ₱9,090.40
- **Contributions**: ₱0.00
- **Taxable Income**: ₱9,090.40
- **Tax**: Bracket 1 (<= 10416.67) = ₱0.00
- **Net Pay**: **₱9,090.40**
