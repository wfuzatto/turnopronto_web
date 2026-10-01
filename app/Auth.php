<?php
final class Auth
{
    private static ?array $cachedUser = null;

    public static function attempt(string $email, string $password): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM tp_users WHERE email = ? AND status = "active" LIMIT 1');
        $stmt->execute([mb_strtolower(trim($email))]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) return false;

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        self::$cachedUser = $user;
        $pdo->prepare('UPDATE tp_users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        return true;
    }

    public static function user(): ?array
    {
        if (self::$cachedUser !== null) return self::$cachedUser;
        $id = $_SESSION['user_id'] ?? null;
        if (!$id || !Database::available()) return null;
        $stmt = Database::connection()->prepare('SELECT * FROM tp_users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return self::$cachedUser = ($stmt->fetch() ?: null);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function logout(): void
    {
        self::$cachedUser = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function requireRole(string ...$roles): array
    {
        $user = self::user();
        if (!$user) redirect('login');
        if ($roles && !in_array($user['role'], $roles, true)) {
            http_response_code(403);
            exit('Acesso negado.');
        }
        return $user;
    }

    public static function dashboardPath(array $user): string
    {
        return match ($user['role']) {
            'company' => 'empresa/dashboard',
            'professional' => 'profissional/inicio',
            'admin' => 'admin/dashboard',
            default => 'login',
        };
    }
}
