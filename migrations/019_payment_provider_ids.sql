-- Migration 019: Add provider_payment_id to payments table
-- Purpose: Store PayMongo payment ID (pay_...) alongside intent/checkout_session ID for accurate API refund targeting.

ALTER TABLE payments 
ADD COLUMN provider_payment_id VARCHAR(64) NULL AFTER intent_id,
ADD INDEX idx_provider_payment_id (provider_payment_id);
