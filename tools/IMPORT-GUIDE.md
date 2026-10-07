# Product Import Guide — Aapki Grocery

Import products in bulk from a CSV file using `tools/import-catalog.php`.

## 1. CSV format

Columns (in this exact order — keep the header row):

| Column          | Required | Example | Notes |
|-----------------|----------|---------|-------|
| `name`          | ✅ | `Potato` | Product name shown on the site |
| `category_slug` | ✅ | `fresh-vegetables` | Must match an existing category slug |
| `sku`           | ✅ | `AG-VEG-001` | Must be **unique**; duplicates are skipped |
| `description`   | – | `Fresh farm potatoes` | Short description |
| `search_terms`  | – | `potato,aalu,आलू` | Comma-separated keywords/synonyms for search |
| `image`         | – | `uploads/products/potato.webp` | Path to the image (see §3). If left blank, defaults to `uploads/products/<slug>.webp` |
| `mrp`           | ✅ | `40` | Strike-through price (₹) |
| `price`         | ✅ | `32` | Selling price (₹) |
| `stock`         | ✅ | `200` | Total stock (split across variants) |
| `variants`      | – | `500 GM\|1 KG\|2 KG` | Pipe-separated pack sizes; unit auto-detected |

A ready-to-edit file is provided: **`tools/fresh-vegetables-template.csv`** (15 common vegetables).

## 2. The slug

You do **not** put the slug in the CSV — it is generated automatically from the `name`:
- `Potato` → `potato`
- `Green Capsicum` → `green-capsicum`
- If a slug already exists, `-1`, `-2`, … is appended automatically.

Product URL becomes: `https://aapkigrocery.com/fresh-vegetables/<slug>/`

## 3. Images — named after the slug

**Convention: each image file is named after the product's slug**, e.g.:
- `Potato` → `uploads/products/potato.webp`
- `Green Capsicum` → `uploads/products/green-capsicum.webp`

Steps:
1. Prepare your own product images (JPG/PNG/WEBP). WEBP recommended for size.
2. Rename each to its slug, e.g. `potato.webp`, `onion.webp`.
3. Upload them to the `uploads/products/` folder on the server.
4. In the CSV, set the `image` column to `uploads/products/<slug>.webp` (or leave blank — the importer defaults to exactly that path).

> If an image file is missing, the product still imports; the site shows the "no product" placeholder until you add the image.

## 4. Run the import

1. Put your CSV in the `tools/` folder (e.g. `tools/fresh-vegetables.csv`).
2. Log in to the admin panel first.
3. Open in your browser:
   ```
   https://aapkigrocery.com/tools/import-catalog.php?csv=fresh-vegetables.csv
   ```
   (replace with your filename; default is `mangoes-catalog.csv` if omitted)
4. Click **Preview CSV** → review the list → **Import now**.

Duplicate SKUs are skipped, so you can safely re-run after fixing rows.

## 5. After import
- Check **Admin → Products** to edit/verify.
- Visit **/fresh-vegetables/** to see them live.
