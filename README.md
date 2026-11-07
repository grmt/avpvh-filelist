# Simple File List — independent downstream repository

This repository contains version 6.3.13-grmt.1 (based on upstream 6.3.11) of the GPL-licensed WordPress plugin
Simple File List. The initial commit is a byte-for-byte import of the plugin
copy installed for `vp40-42.vve.rechtspreker.nl` on 14 September 2026.

## Original upstream

- Project website: <https://simplefilelist.com/>
- WordPress.org listing: <https://wordpress.org/plugins/simple-file-list/>
- WordPress Plugin SVN: <https://plugins.svn.wordpress.org/simple-file-list/>
- Source browser: <https://plugins.trac.wordpress.org/browser/simple-file-list/>

The original project does not provide an official GitHub repository. Therefore
this GitHub repository is an independent downstream maintenance repository, not
a GitHub fork. The WordPress Plugin SVN repository above is the source upstream.

## Upstream status

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

Both run in CI on every pull request.

## License and attribution

The plugin is licensed under GPL-2.0-or-later. Original copyright notices and
attribution are retained in the source files and `readme.txt`.
