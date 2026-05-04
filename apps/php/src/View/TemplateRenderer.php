<?php

declare(strict_types=1);

namespace Cataloga\View;

final class TemplateRenderer
{
    public function __construct(private readonly string $templateRoot)
    {
    }

    public function render(string $template, array $params = []): string
    {
        $templatePath = $this->templateRoot . '/' . $template . '.php';
        if (!is_file($templatePath)) {
            throw new \RuntimeException('Template not found: ' . $template);
        }

        extract($params, EXTR_SKIP);

        ob_start();
        require $templatePath;
        $content = ob_get_clean();
        if ($content === false) {
            throw new \RuntimeException('Failed to render template: ' . $template);
        }

        $title = $params['title'] ?? 'Cataloga';
        $currentPath = $params['currentPath'] ?? '/';

        ob_start();
        require $this->templateRoot . '/layout.php';
        $layout = ob_get_clean();

        return $layout === false ? $content : $layout;
    }
}
