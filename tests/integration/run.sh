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
wp plugin activate simple-file-list >/dev/null
PAGE=$(wp post create --post_type=page --post_status=publish --post_title=Files --post_content='[eeSFL]' --porcelain)

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
'

SFL_IT_URL="$URL" SFL_IT_PAGE="$PAGE" python3 -I test_access.py

echo "--- debug.log"
LOG=$($DC exec -T wordpress bash -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null || true')
if echo "$LOG" | grep -E "PHP (Fatal|Warning|Notice|Deprecated|Parse)" | grep -q "plugins/simple-file-list/"; then
	echo "$LOG" | grep "plugins/simple-file-list/"
	echo "FAIL: PHP errors from the plugin in debug.log"
	exit 1
fi
echo "ok: no PHP errors from the plugin"
