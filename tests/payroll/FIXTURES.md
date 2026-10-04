# Payroll Engine Test Fixtures

> [!WARNING]
> **NEEDS HUMAN VERIFICATION**
> This file contains the exact arithmetic calculations that test the purity of the payroll engine. Do not alter these without consulting HR or DOLE guidelines. All amounts are processed internally in centavos.

## Assumptions
- **Monthly standard hours**: 176 (22 days * 8 hours)
- **Hourly rate calculation**: `floor(monthly_base / 176)`

## Scenario 1: Full-time (Standard)
- **Inputs**: Base ₱30,000/mo, 104 standard hrs, 104 worked hrs, 0 OT, Contributions: Yes
- **Hourly Rate**: 30000 / 176 = ₱170.45 (17045 centavos)
- **Gross Pay**: ₱15,000.00 (Since standard hours met)
- **Contributions**:
  - SSS (4.5%): ₱1,350.00
  - PhilHealth (2.5%): ₱750.00
  - Pag-IBIG: ₱100.00
  - Total: ₱2,200.00
- **Taxable Income**: 15,000 - 2,200 = ₱12,800.00
- **Tax (Semi-monthly)**: 12,800 falls in Bracket 2 (10,416.67 to 16,666.67).
  - 15% of excess over 10416.67 = 15% of 2383.33 = ₱357.50
- **Net Pay**: 15000 - 2200 - 357.50 = **₱12,442.50**

## Scenario 2: Part-time / Under-time
- **Inputs**: Base ₱20,000/mo, 104 standard hrs, 80 worked hrs, 0 OT, Contributions: No
- **Hourly Rate**: 20000 / 176 = ₱113.63 (11363 centavos)
- **Gross Pay**: 80 * 113.63 = ₱9,090.40
- **Contributions**: ₱0.00
- **Taxable Income**: ₱9,090.40
- **Tax**: Bracket 1 (<= 10416.67) = ₱0.00
- **Net Pay**: **₱9,090.40**

## Scenario 3: Overtime Power
- **Inputs**: Base ₱20,000/mo, 104 standard hrs, 104 worked hrs, 10 OT hrs, Contributions: Yes
- **Hourly Rate**: ₱113.63
- **OT Rate**: 113.63 * 1.25 = ₱142.03 (14203 centavos)
- **Basic Pay**: ₱10,000.00
- **OT Pay**: 10 * 142.03 = ₱1,420.30
- **Gross Pay**: 10000 + 1420.30 = ₱11,420.30
- **Contributions**: 
  - SSS (4.5%): ₱900.00
  - PhilHealth (2.5%): ₱500.00
  - Pag-IBIG: ₱100.00
  - Total: ₱1,500.00
- **Taxable Income**: 11,420.30 - 1500 = ₱9,920.30
- **Tax**: Bracket 1 = ₱0.00
- **Net Pay**: 11420.30 - 1500 - 0 = **₱9,920.30**

## Scenario 4: Paid Leave (Equivalent to Full-time)
- **Inputs**: Base ₱30,000/mo, 104 standard hrs, 80 worked hrs + 24 Paid Leave hrs (Total 104 hrs), 0 OT, Contributions: Yes
- *Identical to Scenario 1 since Total Hours == Standard Hours.*
- **Net Pay**: **₱12,442.50**

## Scenario 5: Mid-period Exit (Prorated)
- **Inputs**: Base ₱50,000/mo, 104 standard hrs, 40 worked hrs, 0 OT, Contributions: No
- **Hourly Rate**: 50000 / 176 = ₱284.09 (28409 centavos)
- **Gross Pay**: 40 * 284.09 = ₱11,363.60
- **Contributions**: ₱0.00
- **Taxable Income**: ₱11,363.60
- **Tax**: Bracket 2 (15% of excess over 10416.67) = 15% of 946.93 = ₱142.04
- **Net Pay**: 11363.60 - 142.04 = **₱11,221.56**
