# AV-PvH File List

File lists with uploads, access control, search and paging for WordPress, maintained by grmt. Until 1.0.0 it was called Simple File List (grmt); a site running Simple File List moves over by itself when this plugin is activated (see `readme.txt`).

**Based on [Simple File List](https://simplefilelist.com/) by Mitchell Bennis (Element Engage).** The original plugin is GPL-licensed; its copyright notices are kept in the source files. This plugin is not affiliated with or endorsed by the original author, so please don't ask them for support for it: use the [issues](https://github.com/grmt/avpvh-filelist/issues) here instead.

Everything is free. The features that used to be paid add-ons (multiple lists, per-file access, search and paging) were rewritten for this plugin, and the upgrade prompts and links to the paid version are gone. Sub-folders, a feature of the old Pro version, are not supported.

## History

### Original upstream

- Project website: <https://simplefilelist.com/>
- WordPress.org listing: <https://wordpress.org/plugins/simple-file-list/>
- WordPress Plugin SVN: <https://plugins.svn.wordpress.org/simple-file-list/>
- Source browser: <https://plugins.trac.wordpress.org/browser/simple-file-list/>

The original project does not provide an official GitHub repository. Therefore
this GitHub repository is an independent downstream maintenance repository, not
a GitHub fork. The WordPress Plugin SVN repository above is the source upstream.

### Upstream status

WordPress.org reports that the plugin was closed on 23 July 2026 and is no
longer available for download. It describes the closure as temporary, pending a
full review. The original developer's website says that existing versions will
continue to work, but that there will be no active support or future plugin
updates.

The imported upstream `readme.txt` does not reflect this status and still
promises active developer support. Those statements should be treated as
historical.

This repository exists so the installed GPL-licensed code has a reviewable
history and can be maintained independently. It is not affiliated with or
endorsed by the original developer.

## Tests

- **Unit tests:** `composer install && vendor/bin/phpunit` (tests/*.php, WordPress stubbed out).
- **Integration tests:** `tests/integration/run.sh` needs Docker and Python 3. It starts WordPress (PHP 8.4, with the LDAP extension), MariaDB and OpenLDAP, sets up users, groups, a protected list and a per-file grant through the admin forms, then checks over HTTP who sees which file, the download handler's status codes and headers, blocked direct URLs, path traversal, and that group access fails closed when LDAP is down. It finishes by checking `debug.log` for PHP errors from the plugin. `KEEP=1` leaves the stack running on port 8891 (`SFL_IT_PORT`); `docker compose -f tests/integration/docker-compose.yml down -v` removes it.

Both run in CI (`.github/workflows/CI.yml`) on every push and pull request, next to the checks shared with the other own plugins ([grmt/wp-plugin-ci](https://github.com/grmt/wp-plugin-ci): PHP syntax on 8.4 and 8.5, PHP compatibility and WordPress Plugin Check) and the WordPress coding standards. CI builds the plugin with `git archive`, so `.gitattributes` decides what ships; the release workflow runs CI and zips that build.

## License and attribution

The plugin is licensed under GPL-2.0-or-later. Original copyright notices and
attribution are retained in the source files and `readme.txt`.
