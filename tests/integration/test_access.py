"""Integration tests: drive a real WordPress with this plugin and an LDAP server over HTTP.

Started by run.sh, which sets up the site, users, directory and files. Uses only the Python
standard library. Steps run in order, because later steps depend on the settings saved earlier.
"""
import html
import http.cookiejar
import os
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser

BASE = os.environ.get('SFL_IT_URL', 'http://localhost:8891')
PAGE = os.environ.get('SFL_IT_PAGE', '')
ADMIN = '/wp-admin/admin.php?page=simple-file-list'
FILES = ['notulen-bestuur.pdf', 'ledenbrief.txt', 'publiek.txt', 'video.mp4', 'evil.html']
USERS = {'admin': 'password', 'jan': 'pw-jan', 'piet': 'pw-piet', 'kees': 'pw-kees', 'redacteur': 'pw-redacteur'}

failures = []


def check(label, ok, detail=''):
    print(('ok    ' if ok else 'FAIL  ') + label + ('' if ok else '  -- ' + str(detail)))
    if not ok:
        failures.append(label)


def notices(page):
    """Text of the WordPress admin notices on a page."""
    found = re.findall(r'<div class="[^"]*notice[^"]*"[^>]*>\s*<p>(.*?)</p>', page, re.S)
    return [re.sub(r'\s+', ' ', html.unescape(re.sub(r'<[^>]+>', ' ', n))).strip() for n in found]


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


class Forms(HTMLParser):
    """Collect forms and the fields a browser would submit."""

    def __init__(self):
        super().__init__()
        self.forms, self.cur, self.select, self.textarea = [], None, None, None

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'form':
            self.cur = {'action': a.get('action', ''), 'fields': [], 'ids': []}
            self.forms.append(self.cur)
        if self.cur is None:
            return
        if a.get('id'):
            self.cur['ids'].append(a['id'])
        if tag == 'input' and a.get('name') and 'disabled' not in a:
            kind = a.get('type', 'text')
            if kind in ('checkbox', 'radio'):
                if 'checked' in a:
                    self.cur['fields'].append((a['name'], a.get('value', 'on')))
            elif kind not in ('submit', 'button', 'reset'):
                self.cur['fields'].append((a['name'], a.get('value', '')))
        elif tag == 'select':
            self.select = [a.get('name'), None, None]
        elif tag == 'option' and self.select:
            if self.select[2] is None:
                self.select[2] = a.get('value', '')
            if 'selected' in a:
                self.select[1] = a.get('value', '')
        elif tag == 'textarea':
            self.textarea = [a.get('name'), '']

    def handle_endtag(self, tag):
        if tag == 'select' and self.select and self.cur is not None:
            value = self.select[1] if self.select[1] is not None else self.select[2]
            self.cur['fields'].append((self.select[0], value or ''))
            self.select = None
        elif tag == 'textarea' and self.textarea and self.cur is not None:
            self.cur['fields'].append(tuple(self.textarea))
            self.textarea = None
        elif tag == 'form':
            self.cur = None

    def handle_data(self, data):
        if self.textarea is not None:
            self.textarea[1] += data


class Browser:
    def __init__(self, user=None):
        jar = http.cookiejar.CookieJar()
        self.jar = jar
        self.follow = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
        self.stay = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect)
        if user:
            self.get('/wp-login.php')
            self.post('/wp-login.php', {'log': user, 'pwd': USERS[user], 'wp-submit': 'Log In', 'testcookie': '1'})
            if not any(c.name.startswith('wordpress_logged_in') for c in jar):
                raise SystemExit('could not log in as ' + user)

    def request(self, path, data=None, headers=None, method=None, follow=True):
        req = urllib.request.Request(BASE + path, data=data, headers=headers or {}, method=method)
        try:
            r = (self.follow if follow else self.stay).open(req, timeout=30)
            return r.status, r.headers, r.read()
        except urllib.error.HTTPError as e:
            return e.code, e.headers, e.read()

    def get(self, path, **kwargs):
        return self.request(path, **kwargs)

    def text(self, path):
        return self.get(path)[2].decode('utf-8', 'replace')

    def post(self, path, fields):
        return self.request(path, data=urllib.parse.urlencode(fields).encode())

    def upload(self, path, fields, name, content):
        """POST multipart/form-data with one file, as the uploader script does."""
        boundary = 'sfl-it-boundary'
        parts = []
        for k, v in fields.items():
            parts.append('--%s\r\nContent-Disposition: form-data; name="%s"\r\n\r\n%s\r\n' % (boundary, k, v))
        head = '--%s\r\nContent-Disposition: form-data; name="file"; filename="%s"\r\nContent-Type: application/octet-stream\r\n\r\n' % (boundary, name)
        data = (''.join(parts) + head).encode() + content + ('\r\n--%s--\r\n' % boundary).encode()
        return self.request(path, data=data, headers={'Content-Type': 'multipart/form-data; boundary=' + boundary})

    def submit(self, path, marker, values=None, button=None):
        """Load `path`, take the form holding element #marker, change `values`, submit it."""
        parser = Forms()
        parser.feed(self.text(path))
        form = next((f for f in parser.forms if marker in f['ids']), None)
        if form is None:
            raise SystemExit('no form with #%s on %s' % (marker, path))
        values = values or {}
        fields = [(k, v) for k, v in form['fields'] if k not in values]
        for k, v in values.items():
            for item in (v if isinstance(v, list) else [v]):
                fields.append((k, item))
        if button:
            fields.append(button)
        action = html.unescape(form['action']) or path
        if action.startswith(BASE):
            action = action[len(BASE):]
        status, _, body = self.post(action, fields)
        return status, body.decode('utf-8', 'replace')


def handler(name, extra=''):
    return '/?sfl-list=1' + extra + '&sfl-file=' + urllib.parse.quote(name)


def step_ldap_settings(admin):
    status, page = admin.submit(ADMIN + '&tab=groups', 'eeLDAPURI', {
        'eeLDAPEnabled': 'YES',
        'eeLDAPURI': 'ldap://ldap:1389',
        'eeLDAPBindDN': 'cn=admin,dc=example,dc=org',
        'eeLDAPBindPassword': 'throwaway-admin',
        'eeLDAPUserBase': 'ou=users,dc=example,dc=org',
        'eeLDAPGroupBase': 'dc=example,dc=org',
        'eeLDAPTestUser': 'piet',
    }, button=('eeSFL_TestGroups', '1'))
    text = ' | '.join(notices(page))
    check('groups tab: saved', status == 200, status)
    check('groups tab: test finds the directory groups', 'leden' in text and 'bestuur' in text, text)
    check('groups tab: never echoes the bind password', 'throwaway-admin' not in page)


def step_list_settings(admin):
    admin.get(ADMIN + '&tab=list&eeListID=1')  # Creates the list settings and scans the files
    status, page = admin.submit(ADMIN + '&tab=settings&eeListID=1', 'eeShowList', {
        'eeShowList': 'GRANTED',
        'eeListAccess[groups][]': ['leden'],
        'eeProtectFiles': 'YES',
    })
    check('list settings: saved', status == 200, status)
    parser = Forms()
    parser.feed(admin.text(ADMIN + '&tab=settings&eeListID=1'))
    form = next(f for f in parser.forms if 'eeShowList' in f['ids'])
    saved = dict(form['fields'])
    check('list settings: show to granted', saved.get('eeShowList') == 'GRANTED', saved.get('eeShowList'))
    check('list settings: leden granted', ('eeListAccess[groups][]', 'leden') in form['fields'], form['fields'])
    check('list settings: files protected', saved.get('eeProtectFiles') == 'YES', saved.get('eeProtectFiles'))


def step_file_access(admin):
    page = admin.text(ADMIN + '&tab=list&eeListID=1')
    check('admin list: access link per file', len(re.findall(r'tab=access&(?:amp;|#038;)eeListID=1&(?:amp;|#038;)eeFile=', page)) >= len(FILES))
    status, page = admin.submit(ADMIN + '&tab=access&eeListID=1&eeFile=notulen-bestuur.pdf', 'eeSFL_FileGrant_users', {
        'eeSFL_FileGrant[groups][]': ['bestuur'],
        'eeSFL_FileGrant[users]': 'kees, nobody@example.org',
    })
    text = ' | '.join(notices(page))
    check('file access: saved', status == 200, status)
    check('file access: unknown user reported', 'nobody@example.org' in text, text)


def step_front_end():
    """Who sees which file, in the list and through the download handler."""
    expected = {
        None: ({}, 'login'),
        'kees': ({}, 404),  # In the file grant, but not in leden: sees nothing
        'jan': ({n: 200 for n in FILES if n != 'notulen-bestuur.pdf'}, 404),
        'piet': ({n: 200 for n in FILES}, 404),
        'admin': ({n: 200 for n in FILES}, 404),
    }
    for user, (readable, otherwise) in expected.items():
        who = user or 'anonymous'
        b = Browser(user)
        page = b.text('/?page_id=' + PAGE)
        listed = sorted(n for n in FILES if 'sfl-file=' + n in page)
        check('front end %s: lists %s' % (who, sorted(readable) or 'nothing'), listed == sorted(readable), listed)
        direct = re.findall(r'uploads/simple-file-list/(?!_eeSFL)[^"\'<> ]+\.(?:pdf|txt|mp4|html)', page)
        check('front end %s: no direct file links' % who, not direct, direct)
        for name in FILES:
            status, headers, _ = b.get(handler(name), follow=False)
            got = 'login' if status == 302 and 'wp-login.php' in headers.get('Location', '') else status
            want = readable.get(name, otherwise)
            check('handler %s %s: %s' % (who, name, want), got == want, got)


def step_headers():
    jan = Browser('jan')

    status, h, body = jan.get(handler('evil.html'))
    check('evil.html is an attachment', status == 200 and h.get('Content-Disposition', '').startswith('attachment'), h.get('Content-Disposition'))
    check('nosniff header', h.get('X-Content-Type-Options') == 'nosniff', h.get('X-Content-Type-Options'))
    check('not cached by proxies', 'private' in h.get('Cache-Control', ''), h.get('Cache-Control'))

    status, h, body = jan.get(handler('ledenbrief.txt'))
    check('txt opens inline', h.get('Content-Disposition', '').startswith('inline') and body == b'voor alle leden\n', (h.get('Content-Disposition'), body))
    status, h, _ = jan.get(handler('ledenbrief.txt', '&sfl-dl=1'))
    check('download link forces attachment', h.get('Content-Disposition', '').startswith('attachment'), h.get('Content-Disposition'))

    status, h, body = jan.get(handler('video.mp4'), headers={'Range': 'bytes=100-199'})
    check('range: 206 with 100 bytes', status == 206 and len(body) == 100 and h.get('Content-Range') == 'bytes 100-199/300000', (status, len(body), h.get('Content-Range')))
    status, h, _ = jan.get(handler('video.mp4'), headers={'Range': 'bytes=999999-'})
    check('range past the end: 416', status == 416 and h.get('Content-Range') == 'bytes */300000', (status, h.get('Content-Range')))
    status, h, body = jan.get(handler('video.mp4'), method='HEAD')
    check('HEAD: length without body', status == 200 and h.get('Content-Length') == '300000' and body == b'', (status, h.get('Content-Length'), len(body)))

    for name in ('ledenbrief.txt', 'notulen-bestuur.pdf', 'evil.html'):
        url = '/wp-content/uploads/simple-file-list/' + name
        check('direct URL blocked: %s' % name, Browser().get(url)[0] == 403 and jan.get(url)[0] == 403)

    for path in ('../../../wp-config.php', 'x/../../../../wp-config.php', 'ledenbrief.txt%00.pdf'):
        status, _, body = jan.get('/?sfl-list=1&sfl-file=' + path, follow=False)
        check('handler refuses %s' % path, status == 404 and b'DB_PASSWORD' not in body, status)


def step_ldap_down(admin):
    """When the directory can't be reached, group grants fail closed."""
    admin.submit(ADMIN + '&tab=groups', 'eeLDAPURI', {'eeLDAPURI': 'ldap://ldap:1390'}, button=('eeSFL_SaveGroups', '1'))
    jan = Browser('jan')
    check('directory down: jan loses group access', jan.get(handler('ledenbrief.txt'), follow=False)[0] == 404)
    check('directory down: admin still manages the list', admin.get(handler('ledenbrief.txt'), follow=False)[0] == 200)
    admin.submit(ADMIN + '&tab=groups', 'eeLDAPURI', {'eeLDAPURI': 'ldap://ldap:1389'}, button=('eeSFL_SaveGroups', '1'))
    check('directory back: jan reads again', Browser('jan').get(handler('ledenbrief.txt'), follow=False)[0] == 200)


def edit_job(browser, nonce, action, name, **extra):
    fields = {'action': 'simplefilelist_edit_job', 'eeSFL_ID': '1', 'eeFileName': name, 'eeFileAction': action, 'eeSecurity': nonce}
    fields.update(extra)
    return browser.post('/wp-admin/admin-ajax.php', fields)[2].decode('utf-8', 'replace').strip()


def page_value(page, pattern):
    found = re.search(pattern, page)
    return found.group(1) if found else ''


def content(user, name):
    status, _, body = Browser(user).get(handler(name), follow=False)
    return body if status == 200 else status


def step_front_manage(admin):
    """Front-end management, uploads and bulk actions stay inside each user's file access."""
    # An editor who manages the list (Admin Role 4) may change any file from wp-admin,
    # without front-end management
    admin.submit(ADMIN + '&tab=settings&eeListID=1', 'eeShowList', {'eeAdminRole': '4'})
    editor = Browser('redacteur')
    page = editor.text(ADMIN + '&tab=list&eeListID=1')
    answer = edit_job(editor, page_value(page, r'id="eeSFL_ActionNonce">([^<]+)<'), 'Edit', 'notulen-bestuur.pdf', eeFileDescNew='Notulen')
    check('list manager (editor) can edit any file', answer.startswith('SUCCESS'), answer)

    admin.submit(ADMIN + '&tab=settings&eeListID=1', 'eeShowList', {'eeAllowFrontManage': 'YES'})
    admin.submit(ADMIN + '&tab=settings&subtab=uploader_settings&eeListID=1', 'eeAllowUploads', {'eeAllowUploads': 'USER', 'eeAllowOverwrite': 'YES', 'eeFileFormats': 'pdf, txt'})
    original = content('piet', 'notulen-bestuur.pdf')

    # Item IDs as the bulk actions use them, from the list as the admin sees it
    page = admin.text('/?page_id=' + PAGE)
    ids = dict((html.unescape(n), i) for i, n in re.findall(r'id="eeSFL_FileID-(\d+)".*?eeSFL_RealFileName[^>]*>([^<]+)<', page, re.S))
    check('front manage: item IDs found', set(FILES) <= set(ids), ids)

    # Logged-out visitor, who may not see the list at all
    anon_nonce = os.environ.get('SFL_IT_ANON_NONCE', '')
    answer = edit_job(Browser(), anon_nonce, 'Delete', 'ledenbrief.txt')
    check('front manage: anonymous cannot delete in a list it cannot see', answer == 'File Not Found' and content('jan', 'ledenbrief.txt') == b'voor alle leden\n', answer)

    # jan sees the list but not the minutes
    jan = Browser('jan')
    page = jan.text('/?page_id=' + PAGE)
    nonce = page_value(page, r'id="eeSFL_ActionNonce">([^<]+)<')
    check('front manage: jan gets the action nonce', bool(nonce))
    answer = edit_job(jan, nonce, 'Delete', 'notulen-bestuur.pdf')
    check('front manage: jan cannot delete a file restricted to bestuur', answer == 'File Not Found' and content('piet', 'notulen-bestuur.pdf') == original, answer)
    answer = edit_job(jan, nonce, 'Edit', 'notulen-bestuur.pdf', eeFileNameNew='weg.pdf')
    check('front manage: jan cannot rename it', answer == 'File Not Found' and content('piet', 'notulen-bestuur.pdf') == original, answer)
    answer = edit_job(jan, nonce, 'Delete', '.htaccess')
    check('front manage: jan cannot delete the .htaccess', answer == 'File Not Found' and Browser().get('/wp-content/uploads/simple-file-list/ledenbrief.txt')[0] == 403, answer)
    answer = edit_job(jan, nonce, 'Edit', 'ledenbrief.txt', eeFileDescNew='Brief')
    check('front manage: jan can edit a file he may read', answer.startswith('SUCCESS'), answer)

    # Uploads with overwrite on: only files the uploader may read are replaced
    upload_nonce = page_value(page, r'var eeSFL_Nonce = "([^"]+)"')
    check('upload: jan gets the upload nonce', bool(upload_nonce))
    fields = {'action': 'simplefilelist_upload_job', 'eeSFL_ID': '1', 'eeSFL_FileUploadDir': '', 'ee-simple-file-list-upload': upload_nonce}
    answer = jan.upload('/wp-admin/admin-ajax.php', fields, 'notulen-bestuur.pdf', b'%PDF-1.4 overwritten\n')[2].decode().strip()
    check('upload: a restricted file is not overwritten', content('piet', 'notulen-bestuur.pdf') == original, (answer, content('piet', 'notulen-bestuur.pdf')))
    answer = jan.upload('/wp-admin/admin-ajax.php', fields, 'ledenbrief.txt', b'nieuwe brief\n')[2].decode().strip()
    check('upload: a readable file is overwritten', content('jan', 'ledenbrief.txt') == b'nieuwe brief\n', (answer, content('jan', 'ledenbrief.txt')))

    # Bulk delete of every item: only jan's own readable files go
    page = jan.text('/?page_id=' + PAGE)
    jan.post('/?page_id=' + PAGE, {
        'ee': '1', 'eeListID': '1', 'eeSFL_FileOpsAction': 'Delete', 'eeSFL_FileOpsFiles': ','.join(sorted(ids.values())),
        'ee-simple-file-list-file-ops-bar-nonce': page_value(page, r'name="ee-simple-file-list-file-ops-bar-nonce" value="([^"]+)"'),
        'eeSFL_ListOpsBarGo': 'GO',
    })
    check('bulk delete: the restricted file survives', content('piet', 'notulen-bestuur.pdf') == original, content('piet', 'notulen-bestuur.pdf'))
    check('bulk delete: jan\'s readable files are gone', content('admin', 'publiek.txt') == 404, content('admin', 'publiek.txt'))


def step_no_folders(admin):
    """Lists hold only the files directly in their folder; there is no folder feature left."""
    page = admin.text(ADMIN + '&tab=list&eeListID=1')
    check('no folders: a subfolder is not listed', 'archief.pdf' not in page and 'eeSFL_FileID' in page)
    check('no folders: bulk actions offer no folder actions', not re.search(r'value="(Folder|Move|GetProFolder)"', page), re.findall(r'<option value="([^"]+)"', page))
    check('no folders: a file in a subfolder is not served', admin.get(handler('oud/archief.pdf'), follow=False)[0] == 404)


def listed(page):
    return [n for n in FILES if 'sfl-file=' + n in page or '>' + n + '<' in page]


def step_search_and_pages(admin):
    """Search and paging on the front end, through GET parameters per list."""
    admin.submit(ADMIN + '&tab=settings&eeListID=1', 'eeShowList', {'eeShowSearch': 'YES', 'eeFilesPerPage': '2', 'eeShowList': 'YES'})
    front = '/?page_id=' + PAGE
    visitor = Browser()

    page = visitor.text(front)
    check('search: the form is shown', 'name="sfl_search_1"' in page, page[:200])
    check('pages: two files on page 1', len(re.findall(r'eeSFL_RealFileName', page)) == 2, len(re.findall(r'eeSFL_RealFileName', page)))
    check('pages: links to page 3 of 3', 'sfl_page_1=3' in page and 'sfl_page_1=4' not in page)
    page3 = visitor.text(front + '&sfl_page_1=3')
    check('pages: the last page has the last file', len(re.findall(r'eeSFL_RealFileName', page3)) == 1)
    check('pages: a page past the end shows the last page', visitor.text(front + '&sfl_page_1=99').count('eeSFL_RealFileName') == 1)

    page = visitor.text(front + '&sfl_search_1=BRIEF')
    check('search: finds by file name, any case', page.count('eeSFL_RealFileName') == 1 and 'ledenbrief.txt' in page)
    check('search: says how many match', '1 file matches' in page)
    page = visitor.text(front + '&sfl_search_1=' + urllib.parse.quote('"><script>alert(1)</script>'))
    check('search: the query is escaped', '<script>alert(1)</script>' not in page and 'No files match' in page)

    page = admin.text(ADMIN + '&tab=list&eeListID=1&sfl_search_1=video')
    check('search in wp-admin: finds the file', page.count('eeSFL_RealFileName') == 1 and 'video.mp4' in page)
    check('search in wp-admin: the form stays on the admin page', 'name="page" value="simple-file-list"' in page and 'name="eeListID" value="1"' in page)

    admin.submit(ADMIN + '&tab=settings&eeListID=1', 'eeShowList', {'eeShowSearch': '', 'eeFilesPerPage': '0'})
    page = visitor.text(front + '&sfl_search_1=brief')
    check('search: off means the parameter is ignored', 'name="sfl_search_1"' not in page and page.count('eeSFL_RealFileName') == len(FILES))


def step_pages_count_only_readable_files():
    """Files a visitor may not open don't take up room on the pages."""
    admin = Browser('admin')
    admin.submit(ADMIN + '&tab=settings&eeListID=1', 'eeShowList', {'eeFilesPerPage': '1'})
    page = Browser('jan').text('/?page_id=' + PAGE)
    check('pages: jan gets 4 pages for his 4 files', 'sfl_page_1=4' in page and 'sfl_page_1=5' not in page, re.findall(r'sfl_page_1=\d+', page))
    admin.submit(ADMIN + '&tab=settings&eeListID=1', 'eeShowList', {'eeFilesPerPage': '0'})


def main():
    if not PAGE:
        raise SystemExit('SFL_IT_PAGE is not set; start the tests with run.sh')
    admin = Browser('admin')
    step_no_folders(admin)
    step_search_and_pages(admin)
    step_ldap_settings(admin)
    step_list_settings(admin)
    step_file_access(admin)
    step_front_end()
    step_pages_count_only_readable_files()
    step_headers()
    step_ldap_down(admin)
    step_front_manage(admin)
    print()
    if failures:
        print('%d check(s) failed' % len(failures))
        sys.exit(1)
    print('all checks passed')


if __name__ == '__main__':
    main()
