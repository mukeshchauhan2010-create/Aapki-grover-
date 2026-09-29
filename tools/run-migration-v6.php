<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();

// Step 1: Add parent_id if not exists
$cols = array_column($pdo->query('DESCRIBE categories')->fetchAll(), 'Field');
if (!in_array('parent_id', $cols)) {
    $pdo->exec('ALTER TABLE categories ADD COLUMN parent_id INT(10) UNSIGNED NULL DEFAULT NULL AFTER id');
    $pdo->exec('ALTER TABLE categories ADD INDEX idx_parent_id (parent_id)');
    echo "✓ parent_id column added\n";
} else {
    echo "ℹ parent_id already exists\n";
}

// Step 2: Get fresh-fruits id
$s = $pdo->prepare("SELECT id FROM categories WHERE slug='fresh-fruits' LIMIT 1");
$s->execute(); $freshFruitsId = (int)$s->fetchColumn();
if (!$freshFruitsId) { echo "✗ fresh-fruits category not found!\n"; exit; }
echo "✓ fresh-fruits id = $freshFruitsId\n";

// Step 3: Insert Mangoes subcategory (skip if already exists)
$check = $pdo->prepare("SELECT id FROM categories WHERE slug='mangoes' LIMIT 1");
$check->execute();
if ($check->fetchColumn()) {
    echo "ℹ Mangoes subcategory already exists\n";
} else {
    $pdo->prepare("INSERT INTO categories
        (parent_id, name, slug, is_active, sort_order, meta_title, meta_description, meta_keywords)
        VALUES (?, 'Mangoes', 'mangoes', 1, 1,
                'Fresh Mangoes Online | Aapki Grocery',
                'Buy fresh mangoes online — Alphonso, Kesar, Dasheri, Langra and more varieties.',
                'mangoes,aam,fresh mango,alphonso,kesar,dasheri,buy mango online')"
    )->execute([$freshFruitsId]);
    $mangoId = $pdo->lastInsertId();
    echo "✓ Mangoes subcategory created (id=$mangoId, parent=$freshFruitsId)\n";
}

// Step 4: Show final categories
echo "\n=== Categories ===\n";
foreach ($pdo->query(
    'SELECT c.id, c.parent_id, c.name, c.slug,
            p.name AS parent_name
     FROM categories c
     LEFT JOIN categories p ON p.id = c.parent_id
     ORDER BY COALESCE(c.parent_id,c.id), c.sort_order, c.id'
) as $r) {
    $indent = $r['parent_id'] ? '    └─ ' : '';
    echo $indent.$r['id'].' | '.$r['name'].' | slug:'.$r['slug'].
         ($r['parent_name'] ? ' | parent:'.$r['parent_name'] : '')."\n";
}
