# Cheesecake Factory Calorie Calculator

WordPress plugin: import a CSV menu dataset and display a <a href="google.com">calorie calculator</a> via shortcode. No external API calls on the frontend—data is loaded from your site database with the page.

## Requirements

- WordPress 5.8+
- PHP 7.4+

## Installation

**Use the ready-made zip** (one folder inside, nothing nested twice):

- File name: **`cheesecake-factory-calorie-calculator-WORDPRESS-UPLOAD.zip`**
- It lives in the same parent folder as your plugin project (next to the `cheesecake-factory-calorie-calculator` folder).

When you open that zip, the **first** thing inside must be a single folder named **`cheesecake-factory-calorie-calculator`**. Open it and you should see **`cheesecake-factory-calorie-calculator.php`** plus `includes/`, `public/`, etc.

**Do not** unzip that folder and then zip only the files inside (no outer folder) — WordPress will say the plugin could not be found. **Do not** put a zip inside another zip.

**If you see “Plugin file does not exist” on Activate:** delete the plugin from **Plugins** (Deactivate → **Delete**), then upload this zip again. That error is often a broken old install path or a zip created with Windows “Compress to ZIP” on Linux hosting (bad path separators). This project’s zip is built with `build-wordpress-zip.ps1` so paths use **/** and work on Linux.

1. WordPress admin → **Plugins → Add New → Upload Plugin**.
2. Choose **`cheesecake-factory-calorie-calculator-WORDPRESS-UPLOAD.zip`** → **Install Now** → **Activate**.
3. On activation, the plugin creates a custom database table `{prefix}cfc_menu_items`.

See **`INSTALL-WORDPRESS.txt`** inside the plugin folder for the same checklist.

To rebuild the upload zip after editing code: run **`build-wordpress-zip.ps1`** in the parent folder (`cheesecake factory calculator`).

## Import your CSV

1. Go to **Settings → Cheesecake Calculator**.
2. Check **I understand this will replace all existing menu data**.
3. Choose your `.csv` file (UTF-8 recommended; Excel “CSV UTF-8” export works).
4. Click **Import CSV**.

### Required columns (header row)

| Column        | Required | Notes                                      |
|---------------|----------|--------------------------------------------|
| `id`          | Yes      | Unique product ID (text)                  |
| `product_name`| Yes      | Menu item name                            |
| `category`    | Yes      | Used for the category filter              |
| `calories`    | Yes      | Numeric; values like `590 cal` are cleaned |
| `serving_size`| No       | Optional text                             |
| `description` | No       | Optional text                             |

- Blank rows are skipped.
- Duplicate `id` values in the same file are rejected (full import fails with an error list).
- Any validation error on any row aborts the import (no partial data).

## Put the calculator on a page (block editor)

Use **one** of these — all work:

1. **Pattern (easiest)** — Edit your page → click **+** (add block) → open the **Patterns** tab → search **Cheesecake** → insert **Cheesecake calorie calculator**.
2. **Normal paragraph** — Add a **Paragraph** block, type this **alone** on its own line (nothing else in that block):  
   `[cheesecake_factory_calorie_calculator]`  
   (Version 1.0.2+ runs the calculator even in a Paragraph block — you do not have to hunt for the Shortcode block.)
3. **Shortcode block** — Add the **Shortcode** block and paste the same line there.

Then **Update** the page and view it on the front of your site.

The calculator lists categories, lets the visitor pick an item, set quantity, add lines, and shows per-line and total calories. Adding the same item again merges quantities (up to 999 per line).

### Troubleshooting “nothing works”

1. **Import CSV first** — Settings → Cheesecake Calculator. Until data is imported, the shortcode only shows a yellow “menu data is not loaded” message.
2. **Confirm the plugin is active** — Plugins → Installed Plugins.
3. **Theme must call `wp_head()` and `wp_footer()`** — required for CSS/JS.
4. **If the calculator HTML appears but buttons do nothing** — check the browser console for JavaScript errors; disable “combine/minify JS” for this page temporarily to rule out optimizer plugins reordering scripts.
5. **Version 1.0.1+** embeds menu data in the page HTML so the calculator does not depend on `wp_localize_script` order (fixes many page-builder and caching edge cases).

## Updating the dataset later

Export your sheet as CSV again and use **Settings → Cheesecake Calculator → Import CSV**. Each import **replaces** all rows in the custom table with the new file.

Optional: delete single rows under **Browse items** on the same settings page.

## Sample file

See `sample-data.csv` in this folder for a minimal example.

## Uninstall

Deactivating the plugin leaves data in place. Deleting the plugin from **Plugins** runs `uninstall.php`, which drops the custom table and removes plugin options.

## Future extensions (architecture)

- **Carbs / protein / fat**: add columns to the table and importer; extend the JS cart object and table.
- **JSON / Excel / URL import**: add new classes (e.g. `CFC_Importer_JSON`) and admin tabs calling the same `CFC_Database::clear_all_items()` + `insert_batch()` pipeline.
- **AJAX search**: register a REST route or `admin-ajax.php` action that reads only from `{prefix}cfc_menu_items` (still no third-party calls).

## License

GPL v2 or later.
