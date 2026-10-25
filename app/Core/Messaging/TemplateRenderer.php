<?php

declare(strict_types=1);

namespace Sesam\Core\Messaging;

final class TemplateRenderer
{
    /**
     * @param array<string, string> $variables
     */
    public static function render(string $template, array $variables): string
    {
        $output = $template;
        foreach ($variables as $key => $value) {
            $output = str_replace('{{' . $key . '}}', $value, $output);
        }

        return preg_replace('/\{\{[a-zA-Z0-9_]+\}\}/', '', $output) ?? $output;
    }
}
