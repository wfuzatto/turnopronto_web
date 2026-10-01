<?php
final class View
{
    public static function render(string $view, array $data = [], bool $withLayout = true): never
    {
        extract($data, EXTR_SKIP);
        $viewFile = __DIR__ . '/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            exit('View não encontrada: ' . e($view));
        }
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        if ($withLayout) require __DIR__ . '/views/layout.php';
        else echo $content;
        exit;
    }
}
