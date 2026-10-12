-- Migration 017: Add Menu Item Images
-- Purpose: Support product/menu item images with alt text for accessibility and kiosk display.

-- 1. Standard menu_items table
ALTER TABLE menu_items 
ADD COLUMN image_path VARCHAR(255) NULL, 
ADD COLUMN image_alt VARCHAR(160) NULL;

-- 2. Products table (used in cafe_pos database catalog)
ALTER TABLE products 
ADD COLUMN image_path VARCHAR(255) NULL, 
ADD COLUMN image_alt VARCHAR(160) NULL;
