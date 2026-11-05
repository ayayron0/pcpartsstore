USE pcpartsstore_build2_demo;

START TRANSACTION;

-- Demo data only. All prices are illustrative CAD amounts.
-- IDs 1001–1012 are reserved for this demonstration.
-- Rerunning this script leaves existing rows unchanged.

-- Add four brands
INSERT INTO brands (id, name) VALUES
(1001, 'Demo Intel'),
(1002, 'Demo Corsair'),
(1003, 'Demo Samsung'),
(1004, 'Demo Kingston')
ON DUPLICATE KEY UPDATE id = id;

-- Add ten parts across different categories and brands
INSERT INTO parts (id, name, category, brand_id, price) VALUES
(1001, 'Demo Core i5 CPU',       'CPU', 1001, 249.99),
(1002, 'Demo Core i7 CPU',       'CPU', 1001, 399.99),
(1003, 'Demo Ryzen 5 CPU',       'CPU', 2,    229.99),
(1004, 'Demo Ryzen 7 CPU',       'CPU', 2,    379.99),
(1005, 'Demo Vengeance 16GB',    'RAM', 1002,  69.99),
(1006, 'Demo Vengeance 32GB',    'RAM', 1002, 119.99),
(1007, 'Demo Fury 32GB',         'RAM', 1004, 109.99),
(1008, 'Demo Samsung 1TB SSD',   'SSD', 1003,  99.99),
(1009, 'Demo Samsung 2TB SSD',   'SSD', 1003, 169.99),
(1010, 'Demo Kingston 1TB SSD',  'SSD', 1004,  89.99)
ON DUPLICATE KEY UPDATE id = id;

-- Add six synthetic users
-- Shared first/last names help demonstrate sorting tie-breakers
INSERT INTO users (id, first_name, last_name, email) VALUES
(1001, 'Alex',   'Chen',   'demo.alex.chen@example.com'),
(1002, 'Alex',   'Patel',  'demo.alex.patel@example.com'),
(1003, 'Maya',   'Chen',   'demo.maya.chen@example.com'),
(1004, 'Noah',   'Singh',  'demo.noah.singh@example.com'),
(1005, 'Olivia', 'Brown',  'demo.olivia.brown@example.com'),
(1006, 'Liam',   'Wilson', 'demo.liam.wilson@example.com')
ON DUPLICATE KEY UPDATE id = id;

-- Add ten orders covering every allowed status
INSERT INTO orders (id, user_id, status) VALUES
(1001, 1001, 'Processing'),
(1002, 1001, 'Shipped'),
(1003, 1002, 'Delivered'),
(1004, 1002, 'Cancelled'),
(1005, 1003, 'Processing'),
(1006, 1003, 'Shipped'),
(1007, 1004, 'Delivered'),
(1008, 1004, 'Cancelled'),
(1009, 1005, 'Processing'),
(1010, 1005, 'Delivered')
ON DUPLICATE KEY UPDATE id = id;

-- Orders 1009 and 1010 have no items, allowing deletion demonstrations.
-- User 1006 has no orders, allowing a user deletion demonstration.
-- Other demo orders have linked items to demonstrate protected deletion.
INSERT INTO order_items (id, order_id, part_id, quantity) VALUES
(1001, 1001, 1001, 1),
(1002, 1001, 1005, 2),
(1003, 1002, 1008, 1),
(1004, 1003, 1003, 1),
(1005, 1004, 1006, 1),
(1006, 1005, 1004, 1),
(1007, 1006, 1009, 1),
(1008, 1007, 1007, 2),
(1009, 1008, 1010, 1)
ON DUPLICATE KEY UPDATE id = id;

COMMIT;

-- Confirm the demo rows were added
SELECT 'brands' AS table_name, COUNT(*) AS demo_rows
FROM brands WHERE id BETWEEN 1001 AND 1012

UNION ALL

SELECT 'parts', COUNT(*)
FROM parts WHERE id BETWEEN 1001 AND 1012

UNION ALL

SELECT 'users', COUNT(*)
FROM users WHERE id BETWEEN 1001 AND 1012

UNION ALL

SELECT 'orders', COUNT(*)
FROM orders WHERE id BETWEEN 1001 AND 1012

UNION ALL

SELECT 'order_items', COUNT(*)
FROM order_items WHERE id BETWEEN 1001 AND 1012;