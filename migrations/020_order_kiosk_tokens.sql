-- Migration 020: Order Kiosk Tokens
-- Purpose: Store hashed authentication tokens issued to self-service kiosks at order creation.

CREATE TABLE IF NOT EXISTS order_kiosk_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
