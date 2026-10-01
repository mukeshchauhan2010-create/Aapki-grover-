<?php
/**
 * Migration v10 — CMS pages + FAQ system
 *  - `pages` : editable static pages (about-us, privacy-policy, terms-and-conditions, contact-us…)
 *  - `faqs`  : Q&A items grouped by category, with sort order + active flag.
 *
 * Seeds pages + FAQs with the supplied Aapki Grocery content (only if empty,
 * so re-running won't duplicate). Safe to run multiple times.
 */
require_once __DIR__.'/../config/db.php';
$pdo = db();

/* ── pages table ─────────────────────────────────────────── */
$pdo->exec("CREATE TABLE IF NOT EXISTS pages (
    id          INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(140) NOT NULL,
    title       VARCHAR(200) NOT NULL,
    body        MEDIUMTEXT NULL,
    meta_title  VARCHAR(200) NULL,
    meta_description VARCHAR(320) NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    updated_at  TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uniq_page_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✓ pages table ready\n";

/* ── faqs table ──────────────────────────────────────────── */
$pdo->exec("CREATE TABLE IF NOT EXISTS faqs (
    id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    category   VARCHAR(80) NOT NULL DEFAULT 'General',
    question   VARCHAR(500) NOT NULL,
    answer     MEDIUMTEXT NOT NULL,
    sort_order INT(11) NOT NULL DEFAULT 0,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_faq_cat (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✓ faqs table ready\n";

/* ── Seed pages (only if table empty) ────────────────────── */
$pageCount = (int)$pdo->query('SELECT COUNT(*) FROM pages')->fetchColumn();
if ($pageCount === 0) {
    $pages = require __DIR__.'/seed-pages.php';
    $ins = $pdo->prepare('INSERT INTO pages(slug,title,body,meta_title,meta_description,is_active) VALUES(?,?,?,?,?,1)');
    foreach ($pages as $p) {
        $ins->execute([$p['slug'], $p['title'], $p['body'], $p['meta_title'] ?? $p['title'].' | Aapki Grocery', $p['meta_description'] ?? null]);
    }
    echo "✓ seeded ".count($pages)." pages\n";
} else {
    echo "ℹ pages already has data ($pageCount rows) — not seeding\n";
}

/* ── Seed FAQs (only if table empty) ─────────────────────── */
$faqCount = (int)$pdo->query('SELECT COUNT(*) FROM faqs')->fetchColumn();
if ($faqCount === 0) {
    $faqs = require __DIR__.'/seed-faqs.php';
    $ins = $pdo->prepare('INSERT INTO faqs(category,question,answer,sort_order,is_active) VALUES(?,?,?,?,1)');
    $i = 0;
    foreach ($faqs as $f) {
        $ins->execute([$f[0], $f[1], $f[2], $i++]);
    }
    echo "✓ seeded ".count($faqs)." FAQs\n";
} else {
    echo "ℹ faqs already has data ($faqCount rows) — not seeding\n";
}

echo "\nMigration v10 complete.\n";
