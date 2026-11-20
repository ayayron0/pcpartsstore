-- DoubleBolt clean database and product seed.
-- 48 products; eight categories; demo CAD prices, not live retail offers.
-- Select the intended local project database in phpMyAdmin before importing.
-- RESET: importing this file replaces the five project tables and their data.
-- It does not drop the database or unrelated tables.
-- Demo customer: customer1@example.com / Customer1!2026
-- Demo admin: admin@example.com / Admin!2026
-- Passwords below are bcrypt hashes. These accounts are for the course demo.

SET NAMES utf8mb4;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS parts;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS brands;

CREATE TABLE brands (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    role VARCHAR(100) NOT NULL DEFAULT 'customer',
    PRIMARY KEY (id),
    UNIQUE KEY users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE parts (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    brand_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT DEFAULT NULL,
    image_url VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY parts_brand (brand_id),
    CONSTRAINT parts_brand_fk FOREIGN KEY (brand_id) REFERENCES brands (id),
    CONSTRAINT parts_price_nonnegative CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    status ENUM('Processing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Processing',
    checkout_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    PRIMARY KEY (id),
    KEY orders_user (user_id),
    UNIQUE KEY orders_user_checkout (user_id, checkout_key),
    CONSTRAINT orders_user_fk FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id INT NOT NULL AUTO_INCREMENT,
    order_id INT NOT NULL,
    part_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY order_items_order_part (order_id, part_id),
    KEY order_items_part (part_id),
    CONSTRAINT order_items_order_fk FOREIGN KEY (order_id) REFERENCES orders (id),
    CONSTRAINT order_items_part_fk FOREIGN KEY (part_id) REFERENCES parts (id),
    CONSTRAINT order_items_quantity_valid CHECK (quantity BETWEEN 1 AND 99),
    CONSTRAINT order_items_price_nonnegative CHECK (unit_price IS NULL OR unit_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

INSERT INTO brands (id, name) VALUES
(1, 'NVIDIA'),
(2, 'AMD'),
(3, 'MSI'),
(4, 'Corsair'),
(5, 'Samsung'),
(6, 'NZXT'),
(7, 'ARCTIC');

INSERT INTO users (id, first_name, last_name, email, password_hash, role) VALUES
(1, 'customer1', 'Account', 'customer1@example.com', '$2y$12$U9kWlslk67hPYLKXIP3aZ.vVE.pf.H.vOnZDvuFZ1un.LregsbG5e', 'customer'),
(2, 'admin', 'Account', 'admin@example.com', '$2y$12$OuQChqWiHw4w/NpdhD3GO.hZDwh0ygdLVfnJFoTqGGsEM8r/KPg8u', 'admin');

-- Product descriptions are original summaries based on manufacturer sources.
INSERT INTO parts (id, name, category, brand_id, price, description, image_url) VALUES
(1, 'GeForce RTX 5060', 'GPU', 1, 429.99, 'Blackwell graphics card with 8GB GDDR7 memory for gaming and creative applications. Supports ray tracing and DLSS in supported software. Product image shows the MSI 5060 8G VENTUS 2X OC board variant.', 'store/images/products/rtx-5060.webp'),
(2, 'GeForce RTX 5060 Ti 16GB', 'GPU', 1, 599.99, 'Blackwell graphics card with 16GB GDDR7 memory for gaming and creative applications. Supports ray tracing and DLSS in supported software. Product image shows the MSI 5060 Ti 16G VENTUS 2X OC PLUS board variant.', 'store/images/products/rtx-5060-ti.webp'),
(3, 'GeForce RTX 5070', 'GPU', 1, 749.99, 'Blackwell graphics card with 12GB GDDR7 memory for gaming and creative applications. Supports ray tracing and DLSS in supported software. Product image shows the MSI 5070 12G VENTUS 2X OC board variant.', 'store/images/products/rtx-5070.webp'),
(4, 'GeForce RTX 5070 Ti', 'GPU', 1, 1099.99, 'Blackwell graphics card with 16GB GDDR7 memory for gaming and creative applications. Supports ray tracing and DLSS in supported software. Product image shows the MSI 5070 Ti 16G GAMING TRIO board variant.', 'store/images/products/rtx-5070-ti.webp'),
(5, 'GeForce RTX 5080', 'GPU', 1, 1599.99, 'Blackwell graphics card with 16GB GDDR7 memory for gaming and creative applications. Supports ray tracing and DLSS in supported software. Product image shows the MSI 5080 16G VENTUS 3X OC board variant.', 'store/images/products/rtx-5080.webp'),
(6, 'GeForce RTX 5090', 'GPU', 1, 2899.99, 'Blackwell graphics card with 32GB GDDR7 memory for gaming and creative applications. Supports ray tracing and DLSS in supported software. Product image shows the MSI 5090 32G VENTUS 3X OC board variant.', 'store/images/products/rtx-5090.webp'),
(7, 'Ryzen 5 7600X', 'CPU', 2, 269.99, 'AMD Ryzen desktop processor with 6 cores and 12 threads. Uses the AM5 platform and DDR5 memory. Check motherboard CPU support and BIOS requirements before building.', 'store/images/products/ryzen-7600x.png'),
(8, 'Ryzen 5 9600X', 'CPU', 2, 329.99, 'AMD Ryzen desktop processor with 6 cores and 12 threads. Uses the AM5 platform and DDR5 memory. Check motherboard CPU support and BIOS requirements before building.', 'store/images/products/ryzen-9600x.png'),
(9, 'Ryzen 7 7700X', 'CPU', 2, 369.99, 'AMD Ryzen desktop processor with 8 cores and 16 threads. Uses the AM5 platform and DDR5 memory. Check motherboard CPU support and BIOS requirements before building.', 'store/images/products/ryzen-7700x.png'),
(10, 'Ryzen 7 9700X', 'CPU', 2, 449.99, 'AMD Ryzen desktop processor with 8 cores and 16 threads. Uses the AM5 platform and DDR5 memory. Check motherboard CPU support and BIOS requirements before building.', 'store/images/products/ryzen-9700x.png'),
(11, 'Ryzen 9 9900X', 'CPU', 2, 599.99, 'AMD Ryzen 9000 desktop processor with 12 cores and 24 threads. Uses the AM5 platform and DDR5 memory. Check motherboard CPU support and BIOS requirements before building.', 'store/images/products/ryzen-9900x.png'),
(12, 'Ryzen 7 9800X3D', 'CPU', 2, 669.99, 'AMD Ryzen desktop processor with 8 cores and 16 threads. Uses the AM5 platform and DDR5 memory. Check motherboard CPU support and BIOS requirements before building. Features AMD 3D V-Cache technology.', 'store/images/products/ryzen-9800x3d.png'),
(13, 'B550M PRO-VDH WIFI', 'Motherboard', 3, 139.99, 'Micro-ATX AMD B550 motherboard with an AM4 socket, DDR4 memory support and Wi-Fi. Check the CPU support list before selecting a processor.', 'store/images/products/b550m-pro-vdh.webp'),
(14, 'PRO B650-S WIFI', 'Motherboard', 3, 189.99, 'ATX AMD B650 motherboard with an AM5 socket and DDR5 memory support. Includes wireless networking for a desktop build.', 'store/images/products/pro-b650-s.webp'),
(15, 'B650 GAMING PLUS WIFI', 'Motherboard', 3, 219.99, 'ATX AMD B650 motherboard with an AM5 socket, DDR5 memory support and Wi-Fi. Verify the CPU support list and BIOS version.', 'store/images/products/b650-gaming-plus.webp'),
(16, 'MAG B760 TOMAHAWK WIFI', 'Motherboard', 3, 239.99, 'ATX Intel B760 motherboard with an LGA1700 socket and DDR5 memory support. This Intel platform does not accept AMD AM5 processors.', 'store/images/products/b760-tomahawk.webp'),
(17, 'MAG B650 TOMAHAWK WIFI', 'Motherboard', 3, 259.99, 'ATX AMD B650 motherboard with an AM5 socket, DDR5 memory support and Wi-Fi. Some supported processors require a BIOS update.', 'store/images/products/b650-tomahawk.png'),
(18, 'MAG X670E TOMAHAWK WIFI', 'Motherboard', 3, 349.99, 'ATX AMD X670E motherboard for AM5 processors and DDR5 memory. Includes Wi-Fi and expansion options for a higher-end build.', 'store/images/products/x670e-tomahawk.webp'),
(19, 'VENGEANCE LPX 32GB (2x16GB) DDR4-3200 CL16', 'RAM', 4, 89.99, 'Low-profile 32GB two-module DDR4 memory kit, rated up to 3200MT/s CL16. Requires a DDR4 motherboard. SKU: CMK32GX4M2E3200C16.', 'store/images/products/vengeance-lpx-ddr4.png'),
(20, 'VENGEANCE RGB PRO 32GB (2x16GB) DDR4-3200 CL16', 'RAM', 4, 109.99, '32GB two-module DDR4 memory kit with RGB lighting, rated up to 3200MT/s CL16. Requires a DDR4 motherboard. SKU: CMW32GX4M2E3200C16.', 'store/images/products/vengeance-rgb-ddr4.png'),
(21, 'VENGEANCE 32GB (2x16GB) DDR5-6000 CL36', 'RAM', 4, 129.99, 'Black 32GB two-module DDR5 memory kit, rated up to 6000MT/s CL36 with a supported memory profile. SKU: CMK32GX5M2B6000C36.', 'store/images/products/vengeance-ddr5-cl36.png'),
(22, 'VENGEANCE 32GB (2x16GB) DDR5-6000 CL30', 'RAM', 4, 139.99, 'Black 32GB two-module DDR5 memory kit, rated up to 6000MT/s CL30 with a supported memory profile. SKU: CMK32GX5M2B6000Z30.', 'store/images/products/vengeance-ddr5.png'),
(23, 'VENGEANCE RGB 32GB (2x16GB) DDR5-6000 CL30', 'RAM', 4, 169.99, '32GB two-module DDR5 memory kit with RGB lighting. Rated up to 6000MT/s CL30 with a supported memory profile. SKU: CMH32GX5M2B6000Z30K.', 'store/images/products/vengeance-rgb-ddr5.png'),
(24, 'VENGEANCE 64GB (2x32GB) DDR5-6000 CL30', 'RAM', 4, 259.99, '64GB two-module DDR5 memory kit for memory-intensive applications. Rated up to 6000MT/s CL30 with a supported memory profile. SKU: CMK64GX5M2B6000Z30.', 'store/images/products/vengeance-64gb.webp'),
(25, '990 EVO Plus 1TB', 'Storage', 5, 109.99, '1TB M.2 NVMe solid-state drive for applications, games and project files. Supports PCIe 4.0 x4 and PCIe 5.0 x2 interfaces. Check that the motherboard has a suitable M.2 slot.', 'store/images/products/990-evo-plus-1tb.jpg'),
(26, '990 PRO 1TB', 'Storage', 5, 139.99, '1TB M.2 NVMe solid-state drive for applications, games and project files. Uses a PCIe 4.0 interface. Check that the motherboard has a suitable M.2 slot.', 'store/images/products/990-pro-1tb.jpg'),
(27, '990 EVO Plus 2TB', 'Storage', 5, 179.99, '2TB M.2 NVMe solid-state drive for applications, games and project files. Supports PCIe 4.0 x4 and PCIe 5.0 x2 interfaces. Check that the motherboard has a suitable M.2 slot.', 'store/images/products/990-evo-plus-2tb.jpg'),
(28, '990 PRO 2TB', 'Storage', 5, 219.99, '2TB M.2 NVMe solid-state drive for applications, games and project files. Uses a PCIe 4.0 interface. Check that the motherboard has a suitable M.2 slot.', 'store/images/products/990-pro-2tb.jpg'),
(29, '990 EVO Plus 4TB', 'Storage', 5, 329.99, '4TB M.2 NVMe solid-state drive for applications, games and project files. Supports PCIe 4.0 x4 and PCIe 5.0 x2 interfaces. Check that the motherboard has a suitable M.2 slot.', 'store/images/products/990-evo-plus-4tb.jpg'),
(30, '990 PRO 4TB', 'Storage', 5, 399.99, '4TB M.2 NVMe solid-state drive for applications, games and project files. Uses a PCIe 4.0 interface. Check that the motherboard has a suitable M.2 slot.', 'store/images/products/990-pro-4tb.jpg'),
(31, 'H3 Flow', 'PC Case', 6, 99.99, 'Compact Micro-ATX airflow case. Verify motherboard size, power supply length, graphics card clearance and cooler height. Case only; other hardware shown in photos is sold separately.', 'store/images/products/h3-flow.png'),
(32, 'H5 Flow', 'PC Case', 6, 129.99, 'ATX mid-tower case with an airflow-focused design. Check the exact revision and component clearances before assembly. Case only; other hardware shown in photos is sold separately.', 'store/images/products/h5-flow.png'),
(33, 'H6 Flow', 'PC Case', 6, 159.99, 'Dual-chamber ATX case with an airflow-focused layout and panoramic glass panels. Check component dimensions before assembly. Case only; other hardware shown in photos is sold separately.', 'store/images/products/h6-flow.png'),
(34, 'H7 Flow', 'PC Case', 6, 189.99, 'Spacious ATX mid-tower case with mesh airflow panels. Verify radiator, graphics card and power supply clearances. Case only; other hardware shown in photos is sold separately.', 'store/images/products/h7-flow.png'),
(35, 'H7 Flow RGB', 'PC Case', 6, 219.99, 'ATX mid-tower airflow case with RGB fans. Verify the selected hardware against the case dimensions and supported cooling layouts. Case only; other hardware shown in photos is sold separately.', 'store/images/products/h7-flow-rgb.png'),
(36, 'H9 Flow', 'PC Case', 6, 219.99, 'Large dual-chamber ATX airflow case with a panoramic glass design. Check the exact model revision and component clearances. Case only; other hardware shown in photos is sold separately.', 'store/images/products/h9-flow.png'),
(37, 'RM750e 750W', 'PSU', 4, 149.99, '750W fully modular ATX 3.1 desktop power supply with a dedicated 12V-2x6 GPU cable. SKU: CP-9020295-NA.', 'store/images/products/rm750e.png'),
(38, 'RM850e 850W', 'PSU', 4, 169.99, '850W fully modular ATX 3.1 desktop power supply with a dedicated 12V-2x6 GPU cable. SKU: CP-9020296-NA.', 'store/images/products/rm850e.png'),
(39, 'RM1000e 1000W', 'PSU', 4, 209.99, '1000W fully modular ATX 3.1 desktop power supply with a dedicated 12V-2x6 GPU cable. SKU: CP-9020297-NA.', 'store/images/products/rm1000e.png'),
(40, 'SF750 750W', 'PSU', 4, 229.99, '750W fully modular SFX power supply with 80 PLUS Platinum efficiency. Designed for compatible small-form-factor builds. SKU: CP-9020284-NA.', 'store/images/products/sf750.png'),
(41, 'SF850 850W', 'PSU', 4, 259.99, '850W fully modular SFX power supply with 80 PLUS Platinum efficiency. Verify case fit and cable requirements. SKU: CP-9020256-NA.', 'store/images/products/sf850.png'),
(42, 'SF1000 1000W', 'PSU', 4, 299.99, '1000W fully modular SFX power supply with 80 PLUS Platinum efficiency. Verify case fit and system power requirements. SKU: CP-9020257-NA.', 'store/images/products/sf1000.png'),
(43, 'Freezer 36', 'Cooling', 7, 49.99, 'Tower CPU air cooler with two fans in a push-pull arrangement. Verify socket support and case clearance.', 'store/images/products/freezer-36.jpg'),
(44, 'Freezer 36 Black', 'Cooling', 7, 54.99, 'Black tower CPU air cooler with two fans. Verify socket support and case clearance before installation.', 'store/images/products/freezer-36-black.png'),
(45, 'Freezer 36 A-RGB Black', 'Cooling', 7, 64.99, 'Tower CPU air cooler with addressable RGB fans. Verify socket support, lighting connections and case clearance.', 'store/images/products/freezer-36-argb.png'),
(46, 'Liquid Freezer III 240', 'Cooling', 7, 129.99, 'All-in-one liquid CPU cooler with a 240mm radiator. Verify socket support and radiator mounting space.', 'store/images/products/liquid-freezer-240.jpg'),
(47, 'Liquid Freezer III 280', 'Cooling', 7, 149.99, 'All-in-one liquid CPU cooler with a 280mm radiator. Verify socket support and radiator mounting space.', 'store/images/products/liquid-freezer-280.png'),
(48, 'Liquid Freezer III 360', 'Cooling', 7, 169.99, 'All-in-one liquid CPU cooler with a 360mm radiator. Verify socket support and radiator mounting space.', 'store/images/products/liquid-freezer-360.png');

COMMIT;

-- Orders and order_items are intentionally empty. First checkout starts at ID 1.
