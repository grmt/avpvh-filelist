=== Simple File List (grmt) ===
Contributors: grmt, eemitch
Tags: file manager, file sharing, document library, file access, ldap
Requires at least: 7.1
Requires PHP: 8.4
Tested up to: 7.1
Stable tag: 6.3.13-grmt.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

File lists with uploads, access control, search and paging for WordPress. Maintained by grmt, based on Simple File List by Mitchell Bennis.

== Description ==

Put a list of files on any page with the shortcode `[eeSFL]`. Visitors can open, download and (if you allow it) upload files. The files stay on your own server, separate from the Media Library.

This is an independently maintained version of **Simple File List** by **Mitchell Bennis** (Element Engage, [simplefilelist.com](https://simplefilelist.com)). The original plugin was closed on WordPress.org in July 2026. This version is maintained by grmt at [github.com/grmt/simple-file-list](https://github.com/grmt/simple-file-list) and is not affiliated with or endorsed by the original author. Everything is free; there is no paid version.

= Lists and display =

* Several file lists, each with its own folder, settings and shortcode: `[eeSFL list="2"]`.
* Table, Tiles or Flex layout; light, dark or no theme.
* File name, date, size and thumbnails for images, PDFs and videos.
* A display name (Nice Name) and a description per file.
* Sort by name, date added, date changed, size or at random.
* Search in names and descriptions, and pages for long lists: `[eeSFL search="YES" filecount="25"]`.
* Inline audio and video player.

= Access =

* Show a list to everyone, logged-in users, administrators, or specific users, roles and directory groups.
* Limit single files to specific users, roles or groups.
* Protect files: every download goes through WordPress, which checks access first. Direct URLs to the folder are blocked.
* Groups from an LDAP directory, looked up by user name or e-mail address.
* Choose per list which role manages it in wp-admin.

= Files =

* Upload from the front end or wp-admin, with drag and drop and a progress bar.
* Limit who may upload, which file types and how large.
* E-mail notification of uploads.
* Rename, delete and describe files, from wp-admin or (if allowed) the front end.
* Select several files to describe, download as a ZIP, or delete.
* Send a file link by e-mail.
* Action hooks: Uploaded, Added, Removed, Deleted, Edited, Listed, Loaded, Scanned.

= Translations =

Czech, Danish, Dutch, French (Belgium, Canada, France), German, Italian, Portuguese (Brazil, Portugal), Spanish (Mexico, Spain) and Swedish, from the original plugin. New texts are in English.

= Credits =

Simple File List was created by Mitchell Bennis of Element Engage ([simplefilelist.com](https://simplefilelist.com)) and released under the GPL. This version keeps the original copyright notices and builds on that work. The features that used to be paid add-ons (multiple lists, file access, search and paging) were rewritten for this version.


== Installation ==

1. Download the plugin zip from the [releases on GitHub](https://github.com/grmt/simple-file-list/releases) and upload it under Plugins, Add New. Updates then arrive through the normal WordPress updates.
1. A **File List** menu item appears in wp-admin.
1. Set up the list under **List Settings** and add files with **Upload Files**, or put them in the list's folder.
1. Put the shortcode `[eeSFL]` on a page or post. Shortcode attributes override the list settings for that page.


== Frequently Asked Questions ==

= Are files stored in the Media Library? =

No. Each list has its own folder, by default inside the uploads directory.

= Who can see a list? =

Choose under List Settings: everyone, logged-in users, administrators, or specific users, roles and groups. Single files can be limited further under the File Access tab. Turn on Protect Files so that the file URLs only work for the right people.

= Can uploaded files overwrite existing ones? =

By default a duplicate name gets a number added (file_(2).ext). You can allow overwrites in the Upload Settings; an upload never overwrites a file the uploader may not open.

= Are there sub-folders? =

No. A list shows the files directly in its folder. Use several lists instead.

= Where do I report a problem? =

In the [issues on GitHub](https://github.com/grmt/simple-file-list/issues). Please don't ask the original author for help with this version.


== Upgrade Notice ==

* 6.3.12 - Security hardening and XSS/access-control fixes.
* 6.3.11 - Performance fix for sites with low memory limits. Recommended update for all users.


== Screenshots ==

1. Front-side file list with upload form.
2. The file list in wp-admin.
3. List settings.


== Changelog ==

= Unreleased =
* Change: Requires PHP 8.4 and WordPress 7.1, the versions it is tested on. Both are now also in the plugin header, so WordPress won't activate it on older versions.
* New: Multiple file lists. Create, rename and remove lists under "All File Lists" and "Create List"; each list has its own folder, settings and `[eeSFL list="N"]` shortcode. Lists left by the old File Access Manager add-on are picked up automatically.
* New: A list switcher in wp-admin, and per-list admin access based on each list's "Admin Role" setting.
* Change: A new list starts visible to admins only, without uploads, until its settings are reviewed. Two lists can never share or nest folders.
* Fix: Shortcode attributes (style, sorting, visibility) no longer carry over to the next shortcode on the same page.
* Fix: A failed folder change keeps the current folder instead of switching to the default one.
* New: "Protect files" per list. Files are then served by WordPress, which checks access on every download (with Range support for media, and an optional nginx X-Accel-Redirect). Apache gets a deny-all .htaccess for the folder; for nginx the settings show the location block to add.
* New: Show a list to specific users, roles or directory groups ("Show To: Specific Users, Roles or Groups").
* New: Per-file access under "File Access": a file can be limited to users, roles or groups within the list's audience. Grants made with the old File Access Manager add-on are honoured.
* New: Groups from an LDAP directory ("Groups" tab), looked up by user name or e-mail address and cached for 15 minutes. The bind password can be set in wp-config.php as EESFL_LDAP_BIND_PASSWORD. Other plugins can supply groups through the `simple_file_list_user_groups` filter.
* Security: Protected files that could run scripts in the browser (HTML, SVG and the like) are always sent as downloads. ZIP downloads and sent links only include files the user may open.
* Security: Front-end management (edit, rename, delete and bulk actions) only reaches files the visitor may read, and only in lists they may see. It never touches items outside the list, such as the folder's .htaccess.
* Security: An upload never overwrites a file the uploader may not read; it is saved under a new name instead.
* Security: The .htaccess for unprotected lists now blocks every configured file type (the first type in the list was skipped).
* Fix: Users who manage a list through its "Admin Role" (not only administrators) can now edit, rename, delete and upload files in it.
* New: Search and pages, built in (previously the paid Search & Pagination add-on). Turn them on per list under "Search and Pages", or per shortcode with `search="YES"`, `filecount="25"` and `paged="NO"`. Search looks in file names, nice names and descriptions; every word must match. Results and pages are links (`sfl_search_N`, `sfl_page_N`), so they can be bookmarked. Files a visitor may not open are left out of the counts. Search is always available in wp-admin.
* Change: Maintained by grmt, with credit to the original author, Mitchell Bennis, in the plugin header, the readme, the admin footer and every source file. Help and documentation links go to the GitHub repository.
* Removed: The "Get Pro Version" tab and links, the upgrade texts, the settings that only the paid version used, and the links to the original author's support pages. The index page placed in list folders no longer loads a logo from the original author's website.
* Removed: The leftover code for sub-folders from the old Pro version (create, move, folder download, breadcrumbs, archive extraction). It could never run. A list shows the files directly in its folder; folders and their contents are ignored.
* Removed: The hooks for the old File Access Manager add-on. If that add-on, or the old Search & Pagination add-on, is still active, it is deactivated, because its features are built in; its lists and per-file grants are kept. A list it left on "show by file" becomes "Specific Users, Roles or Groups" with no one chosen yet, so only its managers see it until its access is set.
* Fix: Sending files by e-mail now works in lists shown to specific users, roles or groups, for the people who may see them.
* Fix: Creating the first list folder no longer tries to copy the removed PDF manual into it.

= 6.3.12 =
* Security Fix: Escaped file names, descriptions, submitter metadata, and column labels across Table, Flex, and Tiles display templates to prevent Stored XSS.
* Security Fix: Removed broken `&& !is_admin()` guard in settings input processors and enforced `manage_options` capability across all settings tabs.
* Security Fix: Expanded forbidden file extension blocklist (`php3`–`php8`, `pht`, `inc`, `htm`, `html`, `svg`, `shtml`, `xhtml`) and enforced `AllowUploads` prior to upload job processing.
* Security Fix: Added `ShowList` permission verification, file-list existence validation, and rate limiting to the email sharing handler.
* Security Fix: Randomized temporary ZIP archive filenames and added `index.html` to the temporary directory.
* Performance: Properly cached `ffmpeg`/`gs` binary checks in `eeSFL_CheckSupported()`.

= 6.3.11 =
* Performance: Admin-only WordPress files (`wp-admin/includes/plugin.php`) are no longer loaded on frontend page requests, reducing memory usage on every page with the shortcode.
* Performance: The email sharing class is now lazy-loaded — only instantiated when the admin page, an email send action, or the email AJAX handler is actually invoked.
* Performance: The filesystem compatibility write test now runs once and caches the result for 24 hours, instead of running on every page load.

= 6.3.10 =
* Security Fix: Archive extraction and folder delete dispatch code removed from the free plugin — both are Pro-only features and have no attack surface in the free version.
* Security Fix: Removed broken `&& !is_admin()` short-circuit from the email send nonce check. `is_admin()` always returns `true` on `admin-ajax.php`, so the nonce was never enforced on AJAX requests.

= 6.3.9 =
* Security Fix: Reflected XSS — the current page URL was embedded into a JavaScript variable block without escaping. Applied `esc_js()` to all PHP values interpolated into the front-end JavaScript output.

= 6.3.8 =
* Security Fix: Added capability checks to admin AJAX handlers `simplefilelist_confirm` and `simplefilelist_dismiss` — previously accessible to any logged-in user (CVE-2025-68591).
* Security Fix: Replaced broken `is_admin()` authorization guard with `current_user_can('manage_options')` in the front-end file management handler. The previous check was always bypassed on AJAX requests.
* Security Fix: The `eeSubFolder` POST parameter is no longer accepted in the file management AJAX handler. Subfolder support is a Pro feature; ignoring this parameter in the free version eliminates the path traversal attack surface (CVE-2026-11911) entirely.
* Security: Added a `realpath()` confinement check in the file delete function as defense-in-depth against path traversal in any file path component.
* UX: Added an admin warning when Front-End Management is enabled, reminding administrators that the feature will be open to all page visitors.

= 6.3.7 =
* Security Fix: Removed the `frontmanage` shortcode attribute that allowed Contributors and Authors to bypass administrator security settings.
* Security Fix: Email handler now validates the `AllowFrontSend` setting before processing requests.
* Important: When `AllowFrontManage` or `AllowFrontSend` are enabled in settings, these features are intentionally available to all page visitors. Administrators should secure pages using WordPress's built-in access controls or page protection plugins.
* Breaking Change: Sites using `[eeSFL frontmanage="YES"]` must now enable `AllowFrontManage` in backend List Settings instead.

= 6.3.6 =
* NEW: Inline Media Player is now included free -- audio and video files play directly in the browser.
* NEW: Email File Sharing is now included free -- users can send file links by email from the file manager.
* NEW: Bulk File Management is now included free -- apply descriptions, download as zip, or delete multiple files at once with the File Ops Bar.
* NEW: Custom File Directory is now included free -- define any server path as the file manager's storage directory.
* NEW: Tools extension is now available for the free version.
* Improved file and folder name sanitization.
* Improved file system methods with PHP fallbacks for better compatibility on managed WordPress hosts (Pressable, Kinsta, WP Engine, etc.).
* Removed the Re-Scan Files button -- the file manager is scanned on every page load automatically.
* Security fix: Upload notification emails are no longer triggered by probes -- notifications only send when a file was actually saved.
* PDF thumbnail generation no longer requires the Imagick PHP extension -- GhostScript alone is sufficient.
* Many under-the-hood performance, compatibility, and security improvements.
* PHP 8.5 Approved.

= 6.1.18 =
* Fixed broken translations.

= 6.1.17 =
* Security Fix: Fixed broken access control vulnerability (CVE-2025-68591) in file management operations.
* Added back-end capability checks to help prevent malicious back-end users from circumventing the Back-End Access setting.

= 6.1.16 =
* Security Fix: Fixed critical directory traversal vulnerability in upload confirmation routine.
* Security: Added comprehensive input validation to prevent path traversal attacks in file upload processing.
* Improved: Enhanced validation for upload folder paths and file list arrays.
* Improved: Added MIME type validation to verify file types match their extensions and prevent malicious uploads.
* Fixed WordPress Plugin Check (PCP) compliance issues.
* Renamed thumbnail default files to remove special characters.
* Fixed text domain issues for internationalization.
* Major code refactoring and improvements across multiple files.

= 6.1.15 =
* Addressed a security issue involving file renaming.

= 6.1.14 =
* Removed the feature to force English on the back-end.
* Quelled some PHP 8 depreciation notices.

= 6.1.13 =
* Security Fix for a reflected cross-site scripting (XSS) issue
* Security improvements to back-end navigation tabs.

= 6.1.11 =
* General Code Improvements

= 6.1.10 =
* Fixed a major security bug where a malicious attacker could delete your files.
* Fixed a minor XSS vulnerability in the email settings form.

= 6.1.9 =
* Directory Traversal Vulnerability Fix

= 6.1.8 =
* Bug fix where a fatal error was being thrown on the list settings page for Windows installs.

= 6.1.7 =
* Bug fix where file names with multiple spaces caused an error on upload.
* Bug fix where the file Nice Name was ignored if Preserve File Name was OFF.

= 6.1.6 =
* Bug fix where those with a certain WP configuration saw broken links in the back-end.

= 6.1.5 =
* Now ready for the Simple File List Media extension
** https://wordpress.org/plugins/ee-simple-file-list-media/
* Other minor improvements

= 6.1.4 =
* Added a bunch of new hooks you can use to help make SFL do what you need it to do:
** Uploaded, Added, Removed, Deleted, Edited, Listed, Loaded, Scanned
* Improved the function that obtains the current URL of the page for back-end (AJAX) operations.
* Fixed a function name conflict issue with the Pro version.

= 6.1.3 =
* Bug fix where the upload failed to start if non-logged-in user info was not present.

= 6.1.2 =
* Bug fix where sorting was not working.
* Bug fix where upload class was throwing an error.

= 6.1.1 =
* Added support for the new Simple File List Media extension.
** Go to the new List Settings > Extension Settings tab to access it.
* Rewrote the entire upload routine to improve reliability and efficiency.
* Now recording file owner Id on back-end uploads too.
* File Nice Name option is now available in the edit dialog even if Preserve File Name is OFF.
* Improved results messaging, front and back.
* Bug fix where if preserving the file name, that name was also used for subsequent files in the upload job.
* Bug fix where sorting attributes were not working in the shortcode.

= 6.0.10 =
* Fixed some low threat XXS vulnerabilities.

= 6.0.9 =
* Bug fix where files with uppercase extensions which were added outside of the plugin would not create a thumbnail file.
* Other minor fixes and improvements.

= 6.0.8 =
* Cleaned up file manager display by not showing the item owner/submitter information for yourself.
* Fixed an issue where item submitter info was being unnecessarily recorded on the back-end.
* Took care of some undefined variable notices.

= 6.0.7 =
* Improved the fail condition if bad file manager directory.
* Improved thumbnails display to use the default icon should the source thumb file be missing.
* Bug fix where the upload description was missing on the notification message.

= 6.0.6 =
* Bug fix where leading slash within the FileListDir settings was causing directory read failure.

= 6.0.5 =
* Bug fix where the footer language selector was not working.
* General code improvements.

= 6.0.4 =
* Added additional shortcode attributes: style and theme
* Bug fix where eeSFL_ScrollToIt was not defined.
* Bug fix where the file link within the notice email was broken.

= 6.0.3 =
* Bug Fix where upload form appeared even if turned off.
* Bug fix where PHP Warning was thrown on missing array key.

= 6.0.2 =
* Major UI and code base improvements.
* NEW -> Added ability to give a file a "Nice Name". This displays in place of the real file and can contain characters that are not allowed in file names.
* NEW -> Added option to choose from three responsive file manager styles: Table, Tiles or Flex
* NEW -> Added option to choose from three theme options: Light, Dark or None.
* NEW -> Added option to allow the upload form to appear either above or below the file manager.
* NEW -> Added to choose the date type displayed. This shows the selected date type (added or modified) despite the sort settings.
* NEW -> Added inputs for customizing the description and file submitter label text.
* NEW -> Added an option to bypass the post-upload results page and go straight to the file manager.
* NEW -> Added shortcode display with copy-to-clipboard button to admin UI (top-right)
* NEW -> Added ability to override the locale setting in order to display English on the back-end list and settings tabs.
* Redesigned the File Edit dialog to open in a modal dialog box rather than inline with the file. All changes can be saved at once now.
* Improved the upload form to display a list of the files to be uploaded. You can also remove individual files if needed.
* Separated the 'Get Uploader Info' form settings. Now you can get the description and/or the submitter's information, rather than only both.
* The file manager is now responsive, shifting to a vertical block display on small screens.
* Removed the Shortcode Builder. If you need to over-ride existing settings, refer to: https://simplefilelist.com/shortcode/ for a list of attributes.
* Now Requires PHP 7 +

See the changelog archive here: https://simplefilelist.com/sfl-base-changelog-archive/
