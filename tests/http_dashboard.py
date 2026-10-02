"""Real CSRF login and authenticated dashboard checks; never log credentials."""
import http.cookiejar
import os
import re
import urllib.parse
import urllib.request

base = os.environ.get('TP_TEST_URL', 'http://127.0.0.1:8080').rstrip('/')
for role, route, defaults in [
    ('COMPANY', '/empresa/dashboard', 'empresa@turnopronto.local'),
    ('PROFESSIONAL', '/profissional/inicio', 'juliana@turnopronto.local'),
]:
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    login = opener.open(base + '/login', timeout=20).read().decode()
    csrf = re.search(r'name="_csrf" value="([^"]+)"', login).group(1)
    body = urllib.parse.urlencode({'_csrf': csrf,
        'email': os.environ.get('TP_TEST_' + role + '_EMAIL', defaults),
        'password': os.environ.get('TP_TEST_' + role + '_PASSWORD', os.environ.get('TP_TEST_PASSWORD', ''))}).encode()
    response = opener.open(base + '/login', data=body, timeout=20)
    assert urllib.parse.urlparse(response.url).path == route, role + ' login failed'
    html = opener.open(base + route, timeout=20).read().decode()
    for marker in ['<!doctype html>', 'app-shell', 'sidebar', 'topbar', 'main-content',
                   'logo.svg', 'kpi-grid', 'tp-table', '</html>']:
        assert marker in html, role + ' missing ' + marker
    assert 'Fatal error' not in html and 'Warning:' not in html
    css_url = re.search(r'<link rel="stylesheet" href="([^"]+)"', html).group(1)
    css_response = opener.open(urllib.parse.urljoin(base, css_url), timeout=20)
    assert css_response.status == 200 and css_response.headers.get_content_type() == 'text/css'
    css = css_response.read().decode()
    for selector in ['.app-shell', '.sidebar', '.topbar', '.main-content', '.dashboard-grid', '.kpi-grid', '.tp-table']:
        assert selector in css, 'CSS missing ' + selector
    print(role + ' authenticated dashboard and CSS: PASS')
