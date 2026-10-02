<?php
function app_config(?string $key = null): mixed
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/../config/app.php';
        $local = __DIR__ . '/../config/config.local.php';
        if (is_file($local)) {
            $config = array_replace_recursive($config, require $local);
        }
    }
    if ($key === null) return $config;
    if (array_key_exists($key,$config)) return $config[$key];

    $value=$config;
    foreach(explode('.',$key) as $part){
        if(!is_array($value) || !array_key_exists($part,$value)) return null;
        $value=$value[$part];
    }
    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function base_path(): string
{
    static $base;
    if ($base !== null) return $base;

    $configured = app_config('base_path');
    if ($configured !== null) {
        $configured = trim((string)$configured);
        return $base = ($configured === '' || $configured === '/') ? '' : '/' . trim($configured, '/');
    }

    $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $isLocal = in_array($host, ['localhost','127.0.0.1','::1'], true) || str_ends_with($host, '.local');

    // Em produção o TurnoPronto é publicado na raiz do domínio.
    // Não usamos paths físicos da hospedagem para construir URLs públicas.
    if (!$isLocal && $host !== '') {
        return $base = '';
    }

    // XAMPP/local: descobre a subpasta a partir da raiz física.
    $projectRoot = realpath(dirname(__DIR__));
    $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($projectRoot && $documentRoot) {
        $project = str_replace('\\', '/', $projectRoot);
        $document = rtrim(str_replace('\\', '/', $documentRoot), '/');
        if ($project === $document) return $base = '';
        if (str_starts_with($project . '/', $document . '/')) {
            return $base = '/' . trim(substr($project, strlen($document)), '/');
        }
    }

    return $base = '';
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $clean = ltrim($path, '/');
    $publicUrl = url('public/assets/' . $clean);
    $file = dirname(__DIR__) . '/public/assets/' . $clean;

    if (is_file($file)) {
        return $publicUrl . '?v=' . (string)filemtime($file);
    }

    return $publicUrl;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function money(float|int|string $value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function br_date(?string $date, string $format = 'd/m/Y'): string
{
    if (!$date) return '—';
    return (new DateTime($date))->format($format);
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Sessão expirada. Atualize a página e tente novamente.');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function json_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = base_path();
    if ($base && str_starts_with($uri, $base)) $uri = substr($uri, strlen($base));
    return '/' . trim($uri, '/');
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function icon(string $name, int $size = 20): string
{
    $paths = [
        'home' => '<path d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'wallet' => '<rect x="2" y="5" width="20" height="15" rx="2"/><path d="M16 12h6M18 9v6M2 9h12"/>',
        'star' => '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
        'help' => '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 1 1 5.8 1c0 2-3 2-3 4M12 18h.01"/>',
        'chart' => '<path d="M3 3v18h18M7 16v-5M12 16V8M17 16V5"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'map' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3M21 19V5a2 2 0 0 0-2-2h-6"/>',
    ];
    $path = $paths[$name] ?? $paths['help'];
    return '<svg class="tp-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}
