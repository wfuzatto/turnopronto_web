<?php
final class RendererV3
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

        if ($withLayout) {
            $layoutFile = __DIR__ . '/views/layout_runtime_v3.php';
            if (!is_file($layoutFile)) {
                http_response_code(500);
                exit('Layout runtime não encontrado.');
            }

            header('X-TurnoPronto-Renderer: v3');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            require $layoutFile;
        } else {
            echo $content;
        }

        exit;
    }
}
