<?php
/**
 * Migration v14 — Purchasing / daily inventory
 *  - suppliers       : vendors / farmers / market sources.
 *  - purchases       : a purchase entry (one bill/event on a date).
 *  - purchase_items  : line items — product, qty, cost price (that day's market price).
 *  - products.cost_price : latest cost price (updated on each purchase).
 *
 * Buying auto-adds to products.stock and updates products.cost_price.
 * Safe to run multiple times.
 */
require_once __DIR__.'/../config/db.php';
$pdo = db();

$pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
    id         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(160) NOT NULL,
    type       ENUM('farmer','vendor','market','other') NOT NULL DEFAULT 'vendor',
    phone      VARCHAR(20) DEFAULT NULL,
    notes      VARCHAR(500) DEFAULT NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✓ suppliers table ready\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS purchases (
    id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id   INT(10) UNSIGNED DEFAULT NULL,
    purchase_date DATE NOT NULL,
    invoice_no    VARCHAR(80) DEFAULT NULL,
    notes         VARCHAR(500) DEFAULT NULL,
    total_cost    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_by    BIGINT(20) UNSIGNED DEFAULT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_pur_date (purchase_date),
    KEY idx_pur_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✓ purchases table ready\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS purchase_items (
    id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_id   BIGINT(20) UNSIGNED NOT NULL,
    product_id    BIGINT(20) UNSIGNED NOT NULL,
    product_name  VARCHAR(180) NOT NULL,
    qty           DECIMAL(12,3) NOT NULL,
    unit          VARCHAR(12) NOT NULL DEFAULT 'kg',
    cost_price    DECIMAL(10,2) NOT NULL,
    line_total    DECIMAL(12,2) NOT NULL,
    purchase_date DATE NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_pi_purchase (purchase_id),
    KEY idx_pi_product (product_id),
    KEY idx_pi_date (purchase_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✓ purchase_items table ready\n";

/* products.cost_price (latest cost) */
$cols = array_column($pdo->query('DESCRIBE products')->fetchAll(), 'Field');
if (!in_array('cost_price', $cols, true)) {
    $pdo->exec('ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price');
    echo "✓ products.cost_price column added\n";
} else {
    echo "ℹ products.cost_price already exists\n";
}

echo "\nMigration v14 complete.\n";
