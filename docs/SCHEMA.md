# Schema Documentation

## Core (001_core.sql)
- **branches**: Stores business locations.
- **roles** & **role_permissions**: Standard RBAC structures.
- **employees**: Central employee record (soft deletes active).
- **users**: System authentication layer tied to roles, branches, and employees.
- **devices**: Registers approved POS/HRMS terminals per branch.
- **audit_logs** & **login_attempts**: System security tracing.
- **doc_counters**: Sequential sequence generators for human-readable IDs (e.g. Orders, Payslips).

## POS & Inventory (002_pos_inventory.sql)
- **products** & **modifiers**: The sales catalogue.
- **inventory_items**: Global master list of ingredients/supplies.
- **recipes**: Links products to inventory items (BOM mapping).
- **inventory_stock**: The current QOH (Quantity on Hand) per branch.
- **inventory_ledger**: Immutable append-only log of inventory movements (`Sold`, `Restock`, `Spoilage`).
- **cash_sessions**: Defines the POS operational shift.
- **orders**, **order_items**, **order_item_modifiers**, **order_discounts**, **payments**: The full receipt structure for transactional recording.
- **order_audit**: Tracks voids and refunds.

## Payroll & Finance (003_payroll_attendance.sql)
- **attendance**: Daily time-clock records.
- **leaves**: Employee time-off requests.
- **contribution_rates**: System tax and benefit boundaries (SSS, PagIBIG, PhilHealth).
- **payroll_runs** & **payroll_items**: Generated salary data.
- **pending_bonuses**: Allowances and penalties pending distribution.
- **branch_budgets**: Finance allocations per branch per month.

## Views (004_views.sql)
- **v_revenue_monthly**: Aggregates `PAID` orders by month and branch.
- **v_cogs_monthly**: Aggregates ledger movements mapping to `Sold` or `Spoilage`.

## Standards Applied
1. **Foreign Keys**: Enforced strictly via `RESTRICT` to prevent accidental orphaned data during deletion.
2. **Soft Deletes**: Configured on critical configuration tables using `deleted_at DATETIME DEFAULT NULL`.
3. **Money Storage**: Handled strictly via `DECIMAL(12,2)`.
4. **Collation**: `utf8mb4_unicode_ci` for safe emoji and localized string support.
