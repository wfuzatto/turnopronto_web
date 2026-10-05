"""Real CSRF login and authenticated dashboard checks; never log credentials."""
import http.cookiejar
import json
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

    if role == 'COMPANY':
        shift_html = opener.open(base + '/empresa/vagas/nova', timeout=20).read().decode()
        for marker in ['data-category-select', 'data-category-modal-open', 'data-category-modal', 'data-category-form']:
            assert marker in shift_html, 'category UI missing ' + marker
        shift_csrf = re.search(r'name="_csrf" value="([^"]+)"', shift_html).group(1)
        category_body = urllib.parse.urlencode({'_csrf': shift_csrf, 'name': 'Categoria CI'}).encode()
        request = urllib.request.Request(
            base + '/empresa/categorias',
            data=category_body,
            headers={'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
        )
        category_result = json.loads(opener.open(request, timeout=20).read().decode())
        assert category_result.get('ok') is True
        category_id = int(category_result['category']['id'])
        assert category_id > 0 and category_result['category']['name'] == 'Categoria CI'

        categories_result = json.loads(opener.open(base + '/api/v1/categories', timeout=20).read().decode())
        assert categories_result.get('ok') is True
        assert any(int(item['id']) == category_id for item in categories_result.get('data', []))
        print('COMPANY global category creation: PASS')

        account_html = opener.open(base + '/empresa/conta', timeout=20).read().decode()
        assert 'name="maps_url"' in account_html, 'company account missing maps_url field'
        assert 'Link do Google Maps' in account_html
        print('COMPANY Google Maps field: PASS')

        verification_html = opener.open(base + '/empresa/verificacao', timeout=20).read().decode()
        for marker in ['Verificação da empresa', 'verification-checklist', 'WhatsApp', 'Cartão do CNPJ',
                       'Documento do responsável', 'Comprovante de endereço']:
            assert marker in verification_html, 'company verification center missing ' + marker
        print('COMPANY verification center: PASS')

    print(role + ' authenticated dashboard and CSS: PASS')
