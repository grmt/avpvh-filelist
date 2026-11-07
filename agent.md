# AV-PvH File List — Agent & Developer Guide

This file helps AI coding agents and developers understand the structure, conventions, and boundaries of **AV-PvH File List**, maintained by grmt at https://github.com/grmt/avpvh-filelist. It is based on Simple File List by Mitchell Bennis (simplefilelist.com) and not affiliated with the original author. Until 1.0.0 it was called Simple File List (grmt).

---

## Plugin Identity

| Key | Value |
|-----|-------|
| Plugin slug | `avpvh-filelist` |
| Text domain | `avpvh-filelist` |
| Main file | `avpvh-filelist.php` |
| Version constant | `AVPVH_FILELIST_VERSION` |
| DB version option | `avpvh_filelist_version` (Simple File List used `eeSFL_Version`; see Migration) |
| Default upload dir | `wp-content/uploads/avpvh-filelist/` |
| Shortcode | `[avpvh_filelist]` (`[eeSFL]` still works) |

---

## What This Plugin Is (and Is Not)

There is one version and it is free. No licensing, registration or upsell code belongs here.

Built in (some of it used to be paid add-ons, now rewritten):
- Several file lists (`ee-lists.php`, `ee-lists-admin.php`); list IDs in `avpvh_filelist_list_ids`
- Access per list and per file, protected downloads through WordPress (`ee-access.php`, `ee-access-admin.php`)
- Groups from LDAP (`ee-groups.php`)
- Search and paging (`ee-search.php`)
- File upload with type/size restrictions
- File editing: rename, describe, date, delete
- Email file sharing (via `ee-class-send.php`)
- Media player (audio/video inline)
- Image thumbnails
- Front-end management (configurable per list)
- Light/dark themes; table, tiles, flex display styles
- Bulk file operations for deleting, downloading and applying descriptions

Not supported:
- Sub-folders: a list shows only the files directly in its folder. The old Pro folder code was removed.
- Background tasks / WP-Cron scanning

The old File Access Manager and Search & Pagination add-ons are deactivated on the first wp-admin request (their features are built in). A list left on the add-on's "show by file" setting (`ShowList` = `RESTRICTED`) is read as `GRANTED` by `get_settings()`.

---

## File Structure

```
avpvh-filelist.php                Main plugin file — constants, globals, WP hook registrations
AGENTS.md                         This file

includes/
  ee-class.php                    Avpvh_Filelist_Main — core methods: GetEnv, GetSettings, UpdateFileListArray,
                                  UpdateFileDetail, CheckThumbnail, GetRootPath, GetThisURL, etc.
  ee-class-uploads.php            Avpvh_Filelist_Uploads — handles file upload processing and validation
  ee-class-send.php               Avpvh_Filelist_Send — email file sharing
  ee-functions.php                Standalone functions: avpvh_filelist_file_system wrapper, avpvh_filelist_version_check,
                                  avpvh_filelist_file_editor, AJAX job functions, asset registration, admin menu
  ee-front-end.php                avpvh_filelist_front_end() — [avpvh_filelist] shortcode handler, front-end rendering
  ee-admin-page.php               Admin page router — tab/subtab dispatcher
  ee-admin-header.php             Admin page header and tab navigation
  ee-admin-footer.php             Admin page footer
  ee-admin-tools.php              Admin Tools tab (log viewer, re-scan, debug info)
  ee-list-display.php             List display router — selects table/flex/tiles based on settings
  ee-list-display-table.php       Table layout renderer
  ee-list-display-flex.php        Flex layout renderer
  ee-list-display-tiles.php       Tiles layout renderer
  ee-list-ops-bar-display.php     Operations bar HTML (sort controls, search, folder breadcrumb)
  ee-list-ops-bar-process.php     Operations bar form processing (sort, filter, folder nav)
  ee-list-settings.php            List Settings tab — display, access, sort, date options
  ee-upload-settings.php          Upload Settings tab — allowed types, size limits, notifications
  ee-email-settings.php           Email Settings tab — notify addresses, message template
  ee-lists.php / ee-lists-admin.php   List registry, create/rename/remove lists, list admin rights
  ee-access.php                   Who may see a list or read a file; the protected download handler
  ee-access-admin.php             File Access and Groups tabs
  ee-groups.php                   LDAP group lookup with caching (fails closed)
  ee-search.php                   Search matching, paging, search form and page links
  ee-index-template.html          Template copied as index.html into new upload directories (security)
  htaccess-template.txt           Template for .htaccess hotlink protection (Apache/LiteSpeed only)

css/
  admin.css                       Admin panel styles
  styles.css                      Base front-end styles
  styles-theme-dark.css           Dark theme
  styles-theme-light.css          Light theme
  styles-table.css                Table layout styles
  styles-flex.css                 Flex layout styles
  styles-tiles.css                Tiles layout styles
  styles-upload-form.css          Upload form styles
  styles-upload-theme-light.css   Upload form light theme
  ee-media-styles.css             Media player styles

js/
  ee-head.js                      Scripts loaded in <head> (early init)
  ee-footer.js                    Main front-end scripts (file actions, copy link, UI)
  ee-back.js                      Admin-only scripts
  ee-edit-file.js                 File editor modal (rename, describe, date, delete)
  ee-uploader.js                  Drag-and-drop / multi-file uploader
  ee-email.js                     Email file sharing UI
  ee-media-scripts-footer.js      Audio/video media player

languages/
  avpvh-filelist.pot              Source strings template (regenerate with WP-CLI make-pot)
  avpvh-filelist-{locale}.po    Translator source files
  avpvh-filelist-{locale}.mo    Compiled binary translations
  avpvh-filelist-{locale}.l10n.php  PHP-native translation cache (WP 6.5+)

images/
  thumbnails/                     Generated image/video/PDF thumbnail cache
```

---

## Architecture Overview

The plugin uses a **hybrid class + functions** approach:

1. **`avpvh_filelist_setup()`** runs on `init`, loads all includes, instantiates classes, and bootstraps the environment
2. **`Avpvh_Filelist_Main`** (`ee-class.php`) holds stateful methods and the core file-list logic
3. **`ee-functions.php`** holds stateless utility functions and all WordPress AJAX handlers
4. **Admin pages** are tab/subtab routed through `ee-admin-page.php` using `$_GET['tab']` and `$_GET['subtab']`
5. **Front-end** rendering is triggered by the `[avpvh_filelist]` shortcode via `avpvh_filelist_front_end()`

### Request Flow

```
WordPress init
  └── avpvh_filelist_setup()
        ├── Load includes (ee-functions.php, ee-class.php, ee-class-uploads.php, ee-class-send.php)
        ├── $eeSFL = new Avpvh_Filelist_Main()
        ├── $eeSFLU = new Avpvh_Filelist_Uploads()
        ├── $eeSFLE = new Avpvh_Filelist_Send()
        ├── get_env() — detect OS, web server, PHP extensions, paths
        ├── get_root_path() — resolve site root (handles managed host path offsets)
        ├── get_settings(1) — load list settings from DB
        └── avpvh_filelist_version_check() — install/upgrade routine (admin only)

Shortcode [avpvh_filelist]
  └── avpvh_filelist_front_end() in ee-front-end.php
        ├── Load file list array from DB
        ├── Apply access/display rules
        ├── ee-list-display.php → table/flex/tiles renderer
        └── upload_form() — if uploads enabled

AJAX: avpvh_filelist_edit_job
  └── avpvh_filelist_file_editor() in ee-functions.php
        ├── Verify nonce (eeSFL_ActionNonce)
        ├── Dispatch to avpvh_filelist_delete_file() / avpvh_filelist_rename_file() / avpvh_filelist_update_file_*()
        └── Update DB file array

AJAX: avpvh_filelist_upload_job
  └── avpvh_filelist_upload_job() → $eeSFLU->file_uploader()
```

---

## Naming Conventions

- **Functions**: `avpvh_filelist_snake_case()`; methods `snake_case()`; classes `Avpvh_Filelist_*`. The naming check of grmt/wp-plugin-ci (WordPress or PSR style, never mixed in one name) applies.
- **Globals**: `$eeSFL`, `$eeSFLU`, `$eeSFLE`
- **DB options**: `avpvh_filelist_list_ids`, `avpvh_filelist_settings_{ID}`, `avpvh_filelist_files_{ID}`, `avpvh_filelist_ldap`, `avpvh_filelist_access_settings`, `AVPVH_FILELIST_VERSION`
- **AJAX actions**: `avpvh_filelist_upload_job`, `avpvh_filelist_edit_job`, `avpvh_filelist_sendfile_job`
- **WP action hooks**: `avpvh_filelist_uploaded`, `avpvh_filelist_deleted`, `avpvh_filelist_edited`, `avpvh_filelist_listed`
- **CSS IDs** (kept from Simple File List, so sites' own CSS keeps working; JS function names and HTML ids/classes keep the `eeSFL` names too): `#eeSFL` (wrapper), `#eeSFL_AdminMain`, `#eeSFL_ListOpsBarGo`, `#upload_form`

---

## Key Patterns

### Filesystem Operations
All file I/O goes through `avpvh_filelist_file_system($mode, $params)` in `ee-functions.php`. This wraps `WP_Filesystem` with a native PHP fallback for managed hosts (Pressable, Kinsta, WP Engine) where FTP credentials are unavailable at runtime. Never bypass with raw `file_get_contents()`, `rename()`, `unlink()`, etc.

```php
avpvh_filelist_file_system('get_contents', array('file' => '/path/to/file'));
avpvh_filelist_file_system('put_contents', array('file' => '/path', 'data' => $content));
avpvh_filelist_file_system('move', array('from' => $old, 'to' => $new));
avpvh_filelist_file_system('delete', array('file' => $path));
avpvh_filelist_file_system('is_dir', array('path' => $dir));
avpvh_filelist_file_system('mkdir', array('path' => $dir));
avpvh_filelist_file_system('filemtime', array('file' => $path));
avpvh_filelist_file_system('filesize', array('file' => $path));
avpvh_filelist_file_system('dirlist', array('path' => $dir, 'include_hidden' => false, 'recursive' => false));
```

All return `array('success' => bool, 'data' => mixed)`.

### Settings
Stored per list in `get_option('avpvh_filelist_settings_{ID}')`. Access via `$eeSFL->eeListSettings['Key']`. Defaults are defined in `$eeSFL->eeDefaultListSettings` inside `ee-class.php`. Always merge new defaults over existing settings on upgrade — never overwrite blindly.

### File List Array
Stored per list in `get_option('avpvh_filelist_files_{ID}')`. Each entry is an array with: `FilePath`, `FileExt`, `FileSize`, `FileDateAdded`, `FileDateChanged`, `FileNiceName`, `FileDescription`, and optionally `Access` (a per-file grant of users, roles and groups).

### Version / Update Routine
`avpvh_filelist_version_check()` in `ee-functions.php` (wp-admin only):
1. Reads the `avpvh_filelist_version` option
2. Not found → New Install path (writes default settings for list 1)
3. Found but different → Update path (merge settings, clean up old options)

### Migration from Simple File List
`avpvh_filelist_migrate_legacy()` in `ee-migrate.php` runs once, early in `avpvh_filelist_setup()` before anything reads settings, and records itself in `avpvh_filelist_migrated`:
1. Copies `eeSFL_*` options to their new names (`avpvh_filelist_new_option_name()`); logs, versions and task flags are not carried over. The old options stay, for a rollback.
2. Sets `avpvh_filelist_version`, so the New Install path doesn't reset list 1.
3. Rewrites `[eeSFL]` to `[avpvh_filelist]` in post content (not revisions) and the text, block and custom HTML widgets. `[eeSFL]` stays registered for page builder data and other places it doesn't reach.
4. Deactivates `simple-file-list/simple-file-list.php`.
5. Shows an administrator one notice with what it did.

List folders, files, thumbnails (`_eeSFL_Thumbnails/`) and download links (`?sfl-list=…&sfl-file=…`) don't change.

---

## Tests

- Unit: `vendor/bin/phpunit` (WordPress stubbed in `tests/bootstrap.php`).
- Integration: `tests/integration/run.sh` (Docker: WordPress, MariaDB, OpenLDAP; see README.md).

---

## Translations

```bash
wp i18n make-pot . languages/avpvh-filelist.pot \
  --domain=avpvh-filelist \
  --exclude=testing-guides
```

Then update each `.po` in Poedit: **Translation > Update from POT file**, save, and Poedit regenerates the `.mo`.
