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
                   'logo.svg', 'kpi-grid', 'tp-table', 'data-notifications', '</html>']:
        assert marker in html, role + ' missing ' + marker
    assert 'Fatal error' not in html and 'Warning:' not in html
    expected_account = '/empresa/conta' if role == 'COMPANY' else '/profissional/perfil'
    assert ('href="' + expected_account + '"') in html, role + ' account chip link missing'
    notification_payload = json.loads(opener.open(base + '/notificacoes/feed', timeout=20).read().decode())
    assert notification_payload.get('ok') is True
    assert isinstance(notification_payload.get('data', {}).get('unread'), int)
    assert isinstance(notification_payload.get('data', {}).get('items'), list)
    css_url = re.search(r'<link rel="stylesheet" href="([^"]+)"', html).group(1)
    css_response = opener.open(urllib.parse.urljoin(base, css_url), timeout=20)
    assert css_response.status == 200 and css_response.headers.get_content_type() == 'text/css'
    css = css_response.read().decode()
    for selector in ['.app-shell', '.sidebar', '.topbar', '.main-content', '.dashboard-grid', '.kpi-grid', '.tp-table', '.verification-row-detailed']:
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

    support_route = '/empresa/suporte' if role == 'COMPANY' else '/profissional/suporte'
    support_html = opener.open(base + support_route, timeout=20).read().decode()
    expected_support_markers = (
        ['Suporte para empresas', 'Financeiro, pagamentos e cobranças', 'Candidatos e profissionais']
        if role == 'COMPANY'
        else ['Suporte para profissionais', 'Ganhos e pagamentos', 'Reputação e avaliações']
    )
    for marker in expected_support_markers:
        assert marker in support_html, role + ' support page missing ' + marker
    assert 'Novo chamado' in support_html and 'Meus chamados' in support_html
    print(role + ' segmented support page: PASS')

    search_term = 'Juliana' if role == 'COMPANY' else 'Vale'
    search_html = opener.open(base + '/buscar?q=' + urllib.parse.quote(search_term), timeout=20).read().decode()
    assert 'Busca' in search_html and search_term in search_html, role + ' global search page failed'
    expected_search_marker = 'Juliana Alves' if role == 'COMPANY' else 'Hotel Vale Eventos'
    assert expected_search_marker in search_html, role + ' global search did not return expected result'
    search_json = json.loads(opener.open(base + '/buscar?q=' + urllib.parse.quote(search_term) + '&format=json', timeout=20).read().decode())
    assert search_json.get('ok') is True and search_json.get('data', {}).get('items'), role + ' live search endpoint failed'
    print(role + ' global search: PASS')

    if role == 'PROFESSIONAL':
        opportunities_html = opener.open(base + '/profissional/oportunidades', timeout=20).read().decode()
        assert 'data-opportunity-location' in opportunities_html
        assert 'data-opportunity-distance' in opportunities_html
        assert 'data-opportunity-radius' in opportunities_html
        print('PROFESSIONAL browser geolocation UI: PASS')

        earnings_html = opener.open(base + '/profissional/ganhos', timeout=20).read().decode()
        assert 'Próximo repasse' in earnings_html
        assert 'R$ 780,00' not in earnings_html, 'professional earnings still contains the old hardcoded payout'
        print('PROFESSIONAL real payout values: PASS')

        reputation_html = opener.open(base + '/profissional/reputacao', timeout=20).read().decode()
        assert 'Confiabilidade ainda não calculada' in reputation_html
        assert 'Aguardando avaliação' in reputation_html
        print('PROFESSIONAL reputation waits for company feedback: PASS')

    print(role + ' authenticated dashboard and CSS: PASS')


# Admin must be able to open the full verification review before approving.
admin = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
login = admin.open(base + '/login', timeout=20).read().decode()
csrf = re.search(r'name="_csrf" value="([^"]+)"', login).group(1)
body = urllib.parse.urlencode({
    '_csrf': csrf,
    'email': os.environ.get('TP_TEST_ADMIN_EMAIL', 'admin@turnopronto.local'),
    'password': os.environ.get('TP_TEST_ADMIN_PASSWORD', os.environ.get('TP_TEST_PASSWORD', ''))
}).encode()
response = admin.open(base + '/login', data=body, timeout=20)
assert urllib.parse.urlparse(response.url).path == '/admin/dashboard', 'ADMIN login failed'

company_review = admin.open(base + '/admin/verificacao/empresa/1', timeout=20).read().decode()
for marker in ['Revisar Cadastro empresarial', 'Dados da empresa', 'Documentação empresarial', 'Decisão final']:
    assert marker in company_review, 'company admin review missing ' + marker
assert 'admin-support-upload' not in company_review or 'enctype="multipart/form-data"' in company_review

professional_review = admin.open(base + '/admin/verificacao/profissional/1', timeout=20).read().decode()
for marker in ['Revisar Cadastro profissional', 'Dados do profissional', 'Documentação do profissional',
               'Documento oficial com foto', 'Decisão final']:
    assert marker in professional_review, 'professional admin review missing ' + marker
assert 'admin-support-upload' not in professional_review or 'enctype="multipart/form-data"' in professional_review
assert 'data-inline-field="name"' in professional_review, 'professional admin review missing inline editing'
assert 'data-inline-field="company_email"' in company_review, 'company admin review missing inline editing'

admin_support = admin.open(base + '/admin/suporte', timeout=20).read().decode()
for marker in ['Central de suporte', 'Fila de atendimento', 'Empresas', 'Profissionais']:
    assert marker in admin_support, 'ADMIN support center missing ' + marker
print('ADMIN segmented support center: PASS')

admin_search = admin.open(base + '/buscar?q=Vale', timeout=20).read().decode()
assert 'Hotel Vale Eventos' in admin_search and 'Empresa' in admin_search, 'ADMIN global search failed'
print('ADMIN global search: PASS')

print('ADMIN verification review pages: PASS')


# Professionals browse vacancies before any signup.
public = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
jobs = public.open(base + '/vagas', timeout=20).read().decode()
for marker in ['Veja as vagas primeiro', 'Vagas disponíveis', 'Tenho interesse', 'data-public-job-modal-open', 'public-job-modal', 'Acompanhar vaga']:
    assert marker in jobs, 'public vacancy page missing ' + marker
assert 'name="cpf"' not in jobs and 'name="password"' not in jobs, 'public browsing unexpectedly asks for registration data'
assert re.search(r'<button[^>]+data-public-job-modal-open=[^>]*>Ver detalhes</button>', jobs), 'public vacancy details still redirect instead of opening a modal'

direct_signup = public.open(base + '/cadastro/profissional', timeout=20)
assert urllib.parse.urlparse(direct_signup.url).path == '/vagas', 'professional signup must not start before vacancy interest'

match = re.search(r'action="(/vagas/(\d+)/interesse)"', jobs)
assert match, 'no public vacancy interest action found'
interest_path = match.group(1)
shift_id = match.group(2)
csrf = re.search(r'name="_csrf" value="([^"]+)"', jobs).group(1)
request = urllib.request.Request(
    base + interest_path,
    data=urllib.parse.urlencode({'_csrf': csrf}).encode(),
    headers={'Content-Type':'application/x-www-form-urlencoded'}
)
response = public.open(request, timeout=20)
assert urllib.parse.urlparse(response.url).path == '/cadastro/profissional', 'first interest did not start professional onboarding'

basic = response.read().decode()
for marker in ['Etapa 1 de 3', 'Informações básicas', 'name="name"', 'name="cpf"', 'name="birth_date"', 'name="password"', 'name="password_confirm"']:
    assert marker in basic, 'basic application step missing ' + marker
for forbidden in ['name="email"', 'name="phone"', 'name="pix_key"', 'name="address"', 'name="rg"']:
    assert forbidden not in basic, 'basic application step asks too much: ' + forbidden

detail = public.open(base + '/vagas/' + shift_id, timeout=20).read().decode()
assert 'Acompanhar vaga' in detail and 'Quero me candidatar' in detail and 'sem assumir o compromisso do turno' in detail
print('GUEST vacancy-first onboarding: PASS')

login_page = public.open(base + '/login', timeout=20).read().decode()
assert 'name="identifier"' in login_page and 'CPF ou e-mail' in login_page
assert 'Ver vagas sem cadastro' in login_page
print('CPF/email login and guest browsing CTA: PASS')

company_signup = public.open(base + '/cadastro/empresa', timeout=20).read().decode()
assert 'name="email"' in company_signup, 'company signup missing responsible/user email'
assert 'name="company_email"' in company_signup, 'company signup missing institutional company email'
assert 'E-mail do responsável / usuário' in company_signup
assert 'E-mail da empresa' in company_signup
print('COMPANY separate emails: PASS')



