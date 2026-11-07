=== Simple File List (grmt) ===
Contributors: grmt, eemitch
Donate link: http://simplefilelist.com
Tags: file manager, file sharing, share documents, document library, ftp alternative
Requires at least: 6.0
Requires PHP: 8.1
Tested up to: 7.0
Stable tag: 6.3.13-grmt.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

File lists and uploads for WordPress, independently maintained by grmt. Original upstream credits retained.

== Description ==

**Simple File List is the easiest way to add a fully functional file manager to your WordPress website.** Drop the shortcode `[eeSFL]` on any page and your visitors instantly get a clean, browsable file library — complete with thumbnails, descriptions, an inline media player, email file sharing, and optional file uploading. You can control exactly who can see and manage files based on their WordPress role.

Stop wrestling with clumsy FTP clients or third-party cloud storage to exchange files with clients and colleagues. Simple File List keeps everything on your own server, where you own your data.

<img src="https://ps.w.org/simple-file-list/assets/screenshot-1.png" alt="Screenshot" />

## Why Simple File List?

* **One shortcode, instant file manager** -- `[eeSFL]` is all you need to deploy a fully featured file library.
* **Separate from the Media Library** -- your files stay organized and out of the way.
* **Role-based access** -- show the file manager to everyone, only logged-in users, only admins, or no one.
* **Your data, your server** -- no third-party cloud storage, no monthly SaaS fees.
* **Works on managed WordPress hosts** -- fully compatible with Pressable, Kinsta, WP Engine and similar environments.
* **PHP 8.5 approved** and actively maintained.

## File Manager Features

* Add, rename, delete and edit file descriptions from both the front end and the WordPress admin.
* Three display styles: **Table**, **Tiles** or **Flex** -- pick the look that fits your site.
* Light theme, dark theme, or bring your own CSS -- fully customizable.
* Show file name, date, size and auto-generated thumbnails for PDFs, images and videos.
* Give any file a friendly **display name** (Nice Name) that can contain characters not allowed in real file names.
* Add descriptions to files; optionally display them in the file manager.
* Sort by name, date added, date modified or file size -- ascending or descending.
* **Inline Media Player** -- audio and video files play directly in the browser without leaving the page.
* **Email File Sharing** -- let users send a file link by email straight from the file manager.
* **Bulk File Management** -- select multiple files at once and apply a description, download as a zip archive, or delete them all using the File Ops Bar.
* **Custom File Directory** -- define any path on your server as the file manager's storage directory.
* Optionally allow front-end users to rename, send, delete and edit files themselves.
* Includes custom action hooks: Uploaded, Added, Removed, Deleted, Edited, Listed, Loaded, Scanned.

## This Plugin is Perfect For:

* Sharing contracts, reports or official documents with clients.
* Building an internal document library for your team or organization.
* Distributing class materials, course downloads or e-learning resources.
* Hosting an archive of audio, video, PDF or other downloadable files.
* Replacing FTP or Dropbox for exchanging large files with non-technical clients.
* Any situation where files are too large or too numerous for email attachments.

## File Upload Features

* Simple, reliable uploader that works on mobile devices too.
* Drag-and-drop upload zone with a real-time progress bar.
* Allow uploads by everyone, logged-in users only, admins only, or disable entirely.
* Restrict accepted file types and maximum file size.
* Limit the number of files per submission.
* Email notification each time a file is uploaded.
* Optionally collect the uploader's name, email and a file description.


## Internationalized

* cz_CZ - Czech (Czech Republic)
* da_DK - Danish (Denmark)
* de_DE - German (Germany)
* es_ES - Spanish (Spain)
* es_MX - Spanish (Mexico)
* fr_CA - French (Canada)
* fr_FR - French (France)
* fr_BE - French (Belgium)
* it_IT - Italian (Italy)
* nl_NL - Dutch (Netherlands)
* pt_BR - Portuguese (Brazil)
* pt_PT - Portuguese (Portugal)
* sv_SE - Swedish (Sweden)


### Plus

* Lightweight and easy to style -- override any CSS to match your theme.
* Committed, responsive support directly from the developer.

### Try the Demo

[Simple File List Demo](https://free.simplefilelist.com)


## Upgrade to PRO -- Add Sub-Folder Support

Take your file manager to the next level with unlimited folder hierarchies:

* Create unlimited levels of sub-folders.
* Use a shortcode attribute to display any specific folder: **[eeSFL folder="folderA/folderB"]**
* Display different folders in different places on your site -- even multiple folders on the same page.
* Front-end users cannot navigate above the folder you specify.
* Breadcrumb navigation shows exactly where you are.
* Easily move files and folders as needed.
* Rename folders and add descriptions that appear in the file manager.
* Delete any folder along with all of its contents in one click.
* Sort folders first, or sort them together with files.
* Optionally display folder sizes.
* Define a custom file manager directory anywhere on your server.

[Get Simple File List Pro](https://get.simplefilelist.com/)


## PRO Extensions

### File Access Manager

Lock down your files so only the right people can see them:

* Prevent direct file URL access -- files are only reachable through the file manager.
* Limit access to logged-in users; specify a minimum role or an exact role match.
* Create a private file manager for a specific WordPress user or a group of users.
* Independently control upload permissions and front-end file management per access mode.

**Five access modes:**

**Normal Mode** -- No restrictions. Files are viewable by anyone who can reach the page and may be linked to from outside your site.

**Limited Mode** -- Restrict access to WordPress users with a specified role or higher.

**Group Mode** -- Restrict access to a defined group of WordPress users.

**User Mode** -- Restrict access to a single specific WordPress user.

**Restricted Mode** -- Restrict all files by default; grant access to specific files for specific users or roles.

### Search & Pagination

* Adds a search bar and pagination to any file manager instance.
* Search by file name and/or date within the current folder and sub-folders.
* Pagination keeps large file libraries fast and easy to navigate.
* Configure files per page, and show or hide search and pagination independently.
* Shortcode attributes for full control: **[eeSFL search="YES/NO" paged="YES/NO" filecount="25"]**
* Place a standalone search form anywhere on your site: **[eeSFLS permalink='file-list-url']**

[More Information](https://simplefilelist.com/) | [Try the Demo](https://demo.simplefilelist.com/add-search-and-pagination/)

### Tools Extension

The Tools extension adds a suite of utility features for power users and site administrators:

* Additional administrative tools for managing and maintaining your file library.
* Designed to complement the core file manager with advanced housekeeping capabilities.

[More Information](https://simplefilelist.com/)


== Installation ==

1. Install through the WordPress Plugin Directory, or upload the plugin zip file and activate it.
1. A new **File List** menu item will appear in the WordPress admin -- click it.
1. Click the **Settings** tab and configure your file manager options.
1. Add files by clicking the **Upload Files** button or by dropping them directly into your upload folder.
1. Place the file manager on any page or post using the shortcode: **[eeSFL]**
1. Override any setting on a per-page basis using shortcode attributes.


== Frequently Asked Questions ==

= Who is Simple File List for? =

Anyone who needs to share, organize or exchange files with clients, customers, students or coworkers -- directly from their WordPress website.

= Are files stored in the Media Library? =

No. Files are stored in a dedicated folder inside your WordPress uploads directory (or any custom path you specify), completely separate from the Media Library.

= Can I restrict who sees the file manager? =

Yes. You can limit the file manager and/or the upload form to everyone, logged-in users only, or admins only. For granular per-user or per-role file access control, see the <a href="https://simplefilelist.com/file-access-manager/">File Access Manager</a> extension.

= Can uploaded files overwrite existing ones? =

No -- by default a duplicate filename gets a series number appended (e.g. filename_(2).ext). You can allow overwrites by unchecking that option in the Upload Settings tab.

= Can I place different file managers in different places on my site? =

Yes. Place the [eeSFL] shortcode on any page, post or widget area. Upgrade to <a href="https://simplefilelist.com">Pro</a> to display different folders in different locations.

= Will my files be indexed by Google? =

Only if the file manager is publicly visible. If you restrict access to logged-in users or admins, search engines will not index those files.

= Can I customize the look of the file manager? =

Yes. Choose from Table, Tiles or Flex layouts plus light, dark or no theme. The CSS is easy to override for anyone with basic CSS knowledge. See the Instructions tab in the plugin admin for details.

= Can I trigger custom code when a file action happens? =

Yes. Hook into any of the provided <a href="https://free.simple-file-list.com">custom action hooks</a>: Uploaded, Added, Removed, Deleted, Edited, Listed, Loaded, Scanned.

= What is the maximum upload file size? =

The default is the maximum your host currently allows, which is detected automatically. You can set a lower limit in the Upload Settings tab.

= I need help -- will you support me? =

Absolutely. Reach out through the <a href="https://wordpress.org/support/plugin/simple-file-list/">WordPress support forum</a> or directly via <a href="https://simplefilelist.com/get-support/">simplefilelist.com</a>.


== Upgrade Notice ==

* 6.3.12 - Security hardening and XSS/access-control fixes.
* 6.3.11 - Performance fix for sites with low memory limits. Recommended update for all users.


== Screenshots ==

1. Front-side file manager with upload form activated.
2. Back-side file manager -- always active in the WordPress admin.
3. Back-side settings page.


== Changelog ==

= Unreleased =
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
* Removed: The leftover code for sub-folders from the old Pro version (create, move, folder download, breadcrumbs, archive extraction). It could never run. A list shows the files directly in its folder; folders and their contents are ignored.

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
