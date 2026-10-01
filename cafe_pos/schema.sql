CREATE DATABASE IF NOT EXISTS cafe_pos;
USE cafe_pos;

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL
);

CREATE TABLE IF NOT EXISTS modifiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price_adjustment DECIMAL(10, 2) DEFAULT 0.00,
    modifier_group VARCHAR(50) DEFAULT 'Add-ons'
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(10) NOT NULL UNIQUE,
    table_source VARCHAR(50),
    total_price DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('UNPAID', 'PAID') DEFAULT 'UNPAID',
    payment_method VARCHAR(50) DEFAULT 'CASH',
    payment_reference VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS order_item_modifiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT NOT NULL,
    modifier_id INT NOT NULL,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
    FOREIGN KEY (modifier_id) REFERENCES modifiers(id) ON DELETE CASCADE
);

-- Insert some dummy data for testing
INSERT INTO products (name, category, price) VALUES 
('Latte', 'Hot Beverage', 145.00),
('Espresso', 'Hot Beverage', 110.00),
('Iced Americano', 'Cold Beverage', 130.00),
('Mocha Frappe', 'Specials', 180.00),
('Blueberry Muffin', 'Pastries', 95.00),
('Croissant', 'Pastries', 85.00);

INSERT INTO modifiers (name, price_adjustment, modifier_group) VALUES 
('Tall', 0.00, 'Size'),
('Grande', 20.00, 'Size'),
('Venti', 40.00, 'Size'),
('Whole Milk', 0.00, 'Milk'),
('Oat Milk', 35.00, 'Milk'),
('Almond Milk', 35.00, 'Milk'),
('Extra Shot', 45.00, 'Add-ons'),
('No Sugar', 0.00, 'Add-ons');
