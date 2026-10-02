<?php
final class View
{
    public static function render(string $view, array $variables = [], bool $withLayout = true): never
    {
        // Reserve renderer controls, but allow the view's nested $data payload.
        extract($variables, EXTR_SKIP);
        $viewFile = __DIR__ . '/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            exit('View não encontrada: ' . e($view));
        }
        ob_start();
        try {
            require $viewFile;
        } catch (Throwable $error) {
            ob_end_clean();
            throw $error;
        }
        $content = ob_get_clean();
        if ($withLayout) {
            $layoutFile = __DIR__ . '/views/layout.php';
            if (!is_file($layoutFile)) {
                http_response_code(500);
                exit('Layout principal não encontrado.');
            }
            require $layoutFile;
        } else {
            echo $content;
        }
        exit;
    }
}
