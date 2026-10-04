-- ==========================================================
-- Migration: 004_views.sql
-- Description: Financial reporting views
-- ==========================================================

-- 1. Monthly Revenue View
CREATE OR REPLACE VIEW v_revenue_monthly AS
SELECT 
    branch_id,
    DATE_FORMAT(created_at, '%Y-%m') AS month_year,
    SUM(final_amount) AS total_revenue
FROM orders
WHERE payment_status = 'PAID' AND is_test = 0
GROUP BY branch_id, month_year;

-- 2. Monthly COGS (Cost of Goods Sold) View
CREATE OR REPLACE VIEW v_cogs_monthly AS
SELECT 
    branch_id,
    DATE_FORMAT(created_at, '%Y-%m') AS month_year,
    SUM(quantity * unit_cost_snapshot) AS total_cogs
FROM inventory_ledger
WHERE type IN ('Sold', 'Spoilage')
GROUP BY branch_id, month_year;
