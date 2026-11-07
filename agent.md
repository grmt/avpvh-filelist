# Simple File List — Agent & Developer Guide

This file helps AI coding agents and developers understand the structure, conventions, and boundaries of **Simple File List (grmt)**: the version of Simple File List by Mitchell Bennis (simplefilelist.com) that grmt maintains at https://github.com/grmt/simple-file-list. It is not affiliated with the original author.

---

## Plugin Identity

| Key | Value |
|-----|-------|
| Plugin slug | `simple-file-list` |
| Text domain | `simple-file-list` |
| Main file | `simple-file-list.php` |
| Version constant | `eeSFL_Version` |
| DB version option | `eeSFL_Version` (was `eeSFL_BASE_Version` in 6.1.18) |
| Default upload dir | `wp-content/uploads/simple-file-list/` |
| Shortcode | `[eeSFL]` |

---

## What This Plugin Is (and Is Not)

There is one version and it is free. No licensing, registration or upsell code belongs here.

Built in (some of it used to be paid add-ons, now rewritten):
- Several file lists (`ee-lists.php`, `ee-lists-admin.php`); list IDs in `eeSFL_ListIDs`
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

The old File Access Manager and Search & Pagination add-ons are deactivated on the first wp-admin request (their features are built in). A list left on the add-on's "show by file" setting (`ShowList` = `RESTRICTED`) is read as `GRANTED` by `eeSFL_GetSettings()`.

---

## File Structure

```
simple-file-list.php              Main plugin file — constants, globals, WP hook registrations
AGENTS.md                         This file

includes/
  ee-class.php                    eeSFL_MainClass — core methods: GetEnv, GetSettings, UpdateFileListArray,
                                  UpdateFileDetail, CheckThumbnail, GetRootPath, GetThisURL, etc.
  ee-class-uploads.php            eeSFL_UploadClass — handles file upload processing and validation
  ee-class-send.php               eeSFLE_class — email file sharing
  ee-functions.php                Standalone functions: eeSFL_FileSystem wrapper, eeSFL_VersionCheck,
                                  eeSFL_FileEditor, AJAX job functions, asset registration, admin menu
  ee-front-end.php                eeSFL_FrontEnd() — [eeSFL] shortcode handler, front-end rendering
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
  simple-file-list.pot            Source strings template (regenerate with WP-CLI make-pot)
  simple-file-list-{locale}.po    Translator source files
  simple-file-list-{locale}.mo    Compiled binary translations
  simple-file-list-{locale}.l10n.php  PHP-native translation cache (WP 6.5+)

images/
  thumbnails/                     Generated image/video/PDF thumbnail cache
```

---

## Architecture Overview

The plugin uses a **hybrid class + functions** approach:

1. **`eeSFL_Setup()`** runs on `init`, loads all includes, instantiates classes, and bootstraps the environment
2. **`eeSFL_MainClass`** (`ee-class.php`) holds stateful methods and the core file-list logic
3. **`ee-functions.php`** holds stateless utility functions and all WordPress AJAX handlers
4. **Admin pages** are tab/subtab routed through `ee-admin-page.php` using `$_GET['tab']` and `$_GET['subtab']`
5. **Front-end** rendering is triggered by the `[eeSFL]` shortcode via `eeSFL_FrontEnd()`

### Request Flow

```
WordPress init
  └── eeSFL_Setup()
        ├── Load includes (ee-functions.php, ee-class.php, ee-class-uploads.php, ee-class-send.php)
        ├── $eeSFL = new eeSFL_MainClass()
        ├── $eeSFLU = new eeSFL_UploadClass()
        ├── $eeSFLE = new eeSFLE_class()
        ├── eeSFL_GetEnv() — detect OS, web server, PHP extensions, paths
        ├── eeSFL_GetRootPath() — resolve site root (handles managed host path offsets)
        ├── eeSFL_GetSettings(1) — load list settings from DB
        └── eeSFL_VersionCheck() — install/upgrade routine (admin only)

Shortcode [eeSFL]
  └── eeSFL_FrontEnd() in ee-front-end.php
        ├── Load file list array from DB
        ├── Apply access/display rules
        ├── eeSFL_ListDisplay() → table/flex/tiles renderer
        └── eeSFL_UploadForm() — if uploads enabled

AJAX: simplefilelist_edit_job
  └── eeSFL_FileEditor() in ee-functions.php
        ├── Verify nonce (eeSFL_ActionNonce)
        ├── Dispatch to eeSFL_DeleteFile() / eeSFL_RenameFile() / eeSFL_UpdateFile*()
        └── Update DB file array

AJAX: simplefilelist_upload_job
  └── simplefilelist_upload_job() → $eeSFLU->eeSFL_FileUploader()
```

---

## Naming Conventions

- **Functions**: `eeSFL_PascalCase()` — e.g. `eeSFL_VersionCheck()`, `eeSFL_FileEditor()`
- **Globals**: `$eeSFL`, `$eeSFLU`, `$eeSFLE`
- **DB options**: `eeSFL_ListIDs`, `eeSFL_Settings_{ID}`, `eeSFL_FileList_{ID}`, `eeSFL_LDAP`, `eeSFL_AccessSettings`, `eeSFL_Version`
- **AJAX actions**: `simplefilelist_upload_job`, `simplefilelist_edit_job`, `simplefilelist_sendfile_job`
- **WP action hooks**: `eeSFL_Hook_Uploaded`, `eeSFL_Hook_Deleted`, `eeSFL_Hook_Edited`, `eeSFL_Hook_Listed`
- **CSS IDs**: `#eeSFL` (wrapper), `#eeSFL_AdminMain`, `#eeSFL_ListOpsBarGo`, `#eeSFL_UploadForm`

---

## Key Patterns

### Filesystem Operations
All file I/O goes through `eeSFL_FileSystem($mode, $params)` in `ee-functions.php`. This wraps `WP_Filesystem` with a native PHP fallback for managed hosts (Pressable, Kinsta, WP Engine) where FTP credentials are unavailable at runtime. Never bypass with raw `file_get_contents()`, `rename()`, `unlink()`, etc.

```php
eeSFL_FileSystem('get_contents', array('file' => '/path/to/file'));
eeSFL_FileSystem('put_contents', array('file' => '/path', 'data' => $content));
eeSFL_FileSystem('move', array('from' => $old, 'to' => $new));
eeSFL_FileSystem('delete', array('file' => $path));
eeSFL_FileSystem('is_dir', array('path' => $dir));
eeSFL_FileSystem('mkdir', array('path' => $dir));
eeSFL_FileSystem('filemtime', array('file' => $path));
eeSFL_FileSystem('filesize', array('file' => $path));
eeSFL_FileSystem('dirlist', array('path' => $dir, 'include_hidden' => false, 'recursive' => false));
```

All return `array('success' => bool, 'data' => mixed)`.

### Settings
Stored per list in `get_option('eeSFL_Settings_{ID}')`. Access via `$eeSFL->eeListSettings['Key']`. Defaults are defined in `$eeSFL->eeDefaultListSettings` inside `ee-class.php`. Always merge new defaults over existing settings on upgrade — never overwrite blindly.

### File List Array
Stored per list in `get_option('eeSFL_FileList_{ID}')`. Each entry is an array with: `FilePath`, `FileExt`, `FileSize`, `FileDateAdded`, `FileDateChanged`, `FileNiceName`, `FileDescription`, and optionally `Access` (a per-file grant of users, roles and groups).

### Version / Update Routine
`eeSFL_VersionCheck()` in `ee-functions.php`:
1. Checks `eeSFL_Version` option first
2. Falls back to `eeSFL_BASE_Version` (used by 6.1.18 Free release)
3. If neither found → New Install path
4. If found but older → Update path (merge settings, clean up old options)
5. Deletes `eeSFL_BASE_Version` after migration

---

## Tests

- Unit: `vendor/bin/phpunit` (WordPress stubbed in `tests/bootstrap.php`).
- Integration: `tests/integration/run.sh` (Docker: WordPress, MariaDB, OpenLDAP; see README.md).

---

## Translations

```bash
wp i18n make-pot . languages/simple-file-list.pot \
  --domain=simple-file-list \
  --exclude=testing-guides
```

Then update each `.po` in Poedit: **Translation > Update from POT file**, save, and Poedit regenerates the `.mo`.
