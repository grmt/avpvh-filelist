#!/usr/bin/env bash
# Start the throwaway stack, set up the site, run the tests, and (unless KEEP=1) tear it down.
#   tests/integration/run.sh            run everything
#   KEEP=1 tests/integration/run.sh     leave the stack running afterwards
set -euo pipefail
cd "$(dirname "$0")"

export SFL_IT_PORT="${SFL_IT_PORT:-8891}"
URL="http://localhost:${SFL_IT_PORT}"
DC="docker compose"

cleanup() {
	status=$?
	if [ "$status" -ne 0 ]; then
		echo "--- wordpress container log (last 50 lines)"
		$DC logs --no-color --tail 50 wordpress || true
	fi
	if [ "${KEEP:-0}" != "1" ]; then
		$DC down -v --remove-orphans >/dev/null 2>&1 || true
	fi
	exit "$status"
}
trap cleanup EXIT

$DC up -d --build --wait-timeout 120 2>&1 | tail -n 3

wp() { $DC exec -T -u www-data wordpress wp "$@"; }

# Wait until the entrypoint has copied WordPress and written wp-config.php
for _ in $(seq 60); do
	if $DC exec -T wordpress test -f /var/www/html/wp-config.php; then break; fi
	sleep 1
done

# Site, users and the page with the list
wp core install --url="$URL" --title="SFL integration" --admin_user=admin --admin_password=password \
	--admin_email=admin@example.org --skip-email >/dev/null
wp user create jan jan@example.org --role=subscriber --user_pass=pw-jan --porcelain >/dev/null
wp user create piet piet@example.net --role=subscriber --user_pass=pw-piet --porcelain >/dev/null
wp user create kees kees@example.net --role=subscriber --user_pass=pw-kees --porcelain >/dev/null
wp user create redacteur redacteur@example.net --role=editor --user_pass=pw-redacteur --porcelain >/dev/null
# The site starts as a Simple File List site: a stand-in for the old plugin (it only answers
# [eeSFL]), its options, a page with [eeSFL], and the old File Access Manager add-on with
# list 1 left on its "show by file" setting. The stand-in add-on only sets the global its
# hooks looked for. AV-PvH File List is installed on top and has to move all of it over.
$DC exec -T -u www-data wordpress bash -c '
	set -e
	P=/var/www/html/wp-content/plugins
	mkdir -p "$P/simple-file-list" "$P/ee-simple-file-list-access"
	cat > "$P/simple-file-list/simple-file-list.php" <<"PHP"
<?php
/* Plugin Name: Simple File List (test stand-in for the old plugin) */
add_shortcode("eeSFL", function () { return "OLD SIMPLE FILE LIST"; });
PHP
	cat > "$P/ee-simple-file-list-access/ee-simple-file-list-access.php" <<"PHP"
<?php
/* Plugin Name: File Access Manager (test stand-in for the old add-on) */
define("eeSFLA_Version", "0-test");
add_action("plugins_loaded", function () {
	$GLOBALS["eeSFLA"] = new class {
		public $eeSFLA_FirstRun = TRUE;
		public $eeSFLA_IsListOwner = TRUE;
		public $eeSFLA_Slug = "ee-simple-file-list-access";
		public $eeSFLA_Settings = array("BCC" => "YES");
		public function __call($name, $args) { return FALSE; }
	};
});
PHP
'
wp plugin activate simple-file-list ee-simple-file-list-access >/dev/null
wp eval '
	update_option("eeSFL_Version", "6.3.13-grmt.1");
	update_option("eeSFL_ListIDs", array(1));
	update_option("eeSFL_Settings_1", array(
		"FileListDir" => "wp-content/uploads/simple-file-list/",
		"ListTitle" => "Ledenarchief",
		"ShowList" => "RESTRICTED",
		"Mode" => "RESTRICTED",
		"NotifyFromName" => "Simple File List",
	));
	update_option("eeSFL_TheLog", array("old log"));
'
PAGE=$(wp post create --post_type=page --post_status=publish --post_title=Files --post_content='<!-- wp:shortcode -->[eeSFL]<!-- /wp:shortcode -->' --porcelain)

# Install AV-PvH File List; its first request moves the site over
wp plugin activate avpvh-filelist >/dev/null
wp eval 'echo "";' >/dev/null

echo "--- moving over from Simple File List"
MIGRATION_FAILS=0
expect() { # label, condition result
	if [ "$2" = "yes" ]; then echo "ok    $1"; else echo "FAIL  $1"; MIGRATION_FAILS=$((MIGRATION_FAILS + 1)); fi
}
expect "migration: the old plugin is deactivated" "$(wp plugin is-active simple-file-list && echo no || echo yes)"
expect "migration: the page uses [avpvh_filelist]" "$([ "$(wp post get "$PAGE" --field=post_content)" = '<!-- wp:shortcode -->[avpvh_filelist]<!-- /wp:shortcode -->' ] && echo yes || echo no)"
expect "migration: list 1 keeps its settings" "$(wp eval '$s = get_option("avpvh_flst_settings_1"); echo ($s["ListTitle"] ?? "") === "Ledenarchief" && $s["FileListDir"] === "wp-content/uploads/simple-file-list/" ? "yes" : "no";')"
expect "migration: the old default sender name is updated" "$(wp eval '$s = get_option("avpvh_flst_settings_1"); echo $s["NotifyFromName"] === "AV-PvH File List" ? "yes" : "no";')"
expect "migration: the list ids are copied" "$(wp eval 'echo get_option("avpvh_flst_list_ids") === array(1) ? "yes" : "no";')"
expect "migration: an existing install, not a new one" "$(wp eval 'echo get_option("avpvh_flst_version") === AVPVH_FLST_VERSION ? "yes" : "no";')"
expect "migration: the old log is left behind" "$(wp eval 'echo get_option("avpvh_flst_log", "none") === "none" ? "yes" : "no";')"
expect "migration: the old options stay for a rollback" "$(wp eval 'echo is_array(get_option("eeSFL_Settings_1")) ? "yes" : "no";')"
expect "migration: runs once" "$(wp eval 'update_option("eeSFL_ListIDs", array(1, 7)); avpvh_flst_migrate_legacy(); echo get_option("avpvh_flst_list_ids") === array(1) ? "yes" : "no";')"

# Directory: jan gets an e-mail address (matched by mail), piet is matched by uid; bestuur = piet
for _ in $(seq 60); do
	if $DC exec -T ldap ldapsearch -x -H ldap://localhost:1389 -b dc=example,dc=org -s base >/dev/null 2>&1; then break; fi
	sleep 1
done
$DC exec -T ldap ldapmodify -a -c -x -H ldap://localhost:1389 -D cn=admin,dc=example,dc=org -w throwaway-admin >/dev/null <<'LDIF'
dn: cn=bestuur,ou=users,dc=example,dc=org
changetype: add
objectClass: groupOfNames
cn: bestuur
member: cn=piet,ou=users,dc=example,dc=org

dn: cn=jan,ou=users,dc=example,dc=org
changetype: modify
add: mail
mail: jan@example.org
LDIF

# Files in list 1
$DC exec -T -u www-data wordpress bash -c '
	set -e
	D=/var/www/html/wp-content/uploads/simple-file-list
	mkdir -p "$D" && cd "$D"
	printf "%%PDF-1.4 bestuur only\n" > notulen-bestuur.pdf
	printf "voor alle leden\n" > ledenbrief.txt
	printf "hallo\n" > publiek.txt
	printf "<script>alert(1)</script>\n" > evil.html
	head -c 300000 /dev/urandom > video.mp4
	mkdir -p oud && printf "%%PDF-1.4 in a subfolder\n" > oud/archief.pdf
'

# Logged-out visitors all share one nonce per action, so WP-CLI (no user) produces theirs
ANON_NONCE=$(wp eval 'echo wp_create_nonce("eeSFL_ActionNonce");')

SFL_IT_URL="$URL" SFL_IT_PAGE="$PAGE" SFL_IT_ANON_NONCE="$ANON_NONCE" python3 -I test_access.py
if [ "$MIGRATION_FAILS" -gt 0 ]; then echo "FAIL: $MIGRATION_FAILS migration check(s) failed"; exit 1; fi

# A page view adjusts the settings for its visitor (uploads off for someone who may not upload).
# A rescan in that view may store the server's lower upload maximum, but never the adjustments.
echo "--- settings after a page view"
wp eval '$s = get_option("avpvh_flst_settings_1"); $s["ShowList"] = "YES"; $s["AllowUploads"] = "ADMIN"; $s["UseCache"] = "OFF"; $s["UploadMaxFileSize"] = 9999; update_option("avpvh_flst_settings_1", $s);'
curl -s -o /dev/null "$URL/?page_id=$PAGE"
expect "page view: uploads for admins stay on" "$(wp eval '$s = get_option("avpvh_flst_settings_1"); echo $s["AllowUploads"] === "ADMIN" ? "yes" : "no";')"
expect "page view: the maximum follows the server" "$(wp eval '$s = get_option("avpvh_flst_settings_1"); echo (int) $s["UploadMaxFileSize"] > 0 && (int) $s["UploadMaxFileSize"] < 9999 ? "yes" : "no";')"
wp eval '$s = get_option("avpvh_flst_settings_1"); $s["UploadMaxFileSize"] = 9999; update_option("avpvh_flst_settings_1", $s); do_shortcode("[avpvh_filelist]");' >/dev/null 2>&1
expect "command line: the server maximum is left alone" "$(wp eval '$s = get_option("avpvh_flst_settings_1"); echo (int) $s["UploadMaxFileSize"] === 9999 && $s["AllowUploads"] === "ADMIN" ? "yes" : "no";')"
if [ "$MIGRATION_FAILS" -gt 0 ]; then echo "FAIL: $MIGRATION_FAILS check(s) failed"; exit 1; fi

echo "--- debug.log"
LOG=$($DC exec -T wordpress bash -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null || true')
if echo "$LOG" | grep -E "PHP (Fatal|Warning|Notice|Deprecated|Parse)" | grep -q "plugins/avpvh-filelist/"; then
	echo "$LOG" | grep "plugins/avpvh-filelist/"
	echo "FAIL: PHP errors from the plugin in debug.log"
	exit 1
fi
echo "ok: no PHP errors from the plugin"
