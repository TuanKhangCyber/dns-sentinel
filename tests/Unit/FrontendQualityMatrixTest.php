<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class FrontendQualityMatrixTest extends TestCase
{
    #[DataProvider('frontendQualityCases')]
    public function test_frontend_quality_contract(string $kind, string $subject, string $rule): void
    {
        if ($kind === 'translation') {
            [$file, $key] = explode(':', $subject, 2);
            $english = require $this->projectPath("lang/en/{$file}.php");
            $vietnamese = require $this->projectPath("lang/vi/{$file}.php");

            $this->assertArrayHasKey($key, $vietnamese, "Vietnamese translation is missing {$file}.{$key}.");
            $this->assertSame(is_array($english[$key]), is_array($vietnamese[$key]), "Translation type differs for {$file}.{$key}.");
            $this->assertSame(
                array_keys($this->flatten($english[$key])),
                array_keys($this->flatten($vietnamese[$key])),
                "Nested translation keys differ for {$file}.{$key}."
            );

            foreach ([$english[$key], $vietnamese[$key]] as $value) {
                foreach ($this->flatten($value) as $text) {
                    $this->assertNotSame('', trim((string) $text), "Translation {$file}.{$key} contains an empty value.");
                    $this->assertStringNotContainsString("\u{FFFD}", (string) $text, "Translation {$file}.{$key} contains a replacement character.");
                }
            }

            return;
        }

        $content = file_get_contents($this->projectPath($subject));
        $this->assertIsString($content, "Unable to read {$subject}.");

        if ($kind === 'view') {
            match ($rule) {
                'non-empty' => $this->assertNotSame('', trim($content)),
                'escaped-output' => $this->assertStringNotContainsString('{!!', $content),
                'no-inline-events' => $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $content),
                'no-javascript-url' => $this->assertStringNotContainsString('javascript:', strtolower($content)),
                'no-placeholder-link' => $this->assertDoesNotMatchRegularExpression('/href\s*=\s*["\']\s*#?\s*["\']/i', $content),
                default => throw new RuntimeException("Unknown view rule: {$rule}"),
            };

            return;
        }

        if ($kind === 'contains') {
            $this->assertStringContainsString($rule, $content, "{$subject} is missing required frontend contract: {$rule}");

            return;
        }

        if ($kind === 'excludes') {
            $this->assertStringNotContainsString($rule, $content, "{$subject} contains forbidden frontend pattern: {$rule}");

            return;
        }

        throw new RuntimeException("Unknown frontend quality case: {$kind}");
    }

    public static function frontendQualityCases(): array
    {
        $root = dirname(__DIR__, 2);
        $cases = [];

        foreach (['platform', 'scanner', 'ui'] as $file) {
            $translations = require "{$root}/lang/en/{$file}.php";
            foreach (array_keys($translations) as $key) {
                $cases["translation {$file}.{$key}"] = ['translation', "{$file}:{$key}", 'locale-parity'];
            }
        }

        $views = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/resources/views"));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $views[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }
        sort($views);

        foreach ($views as $view) {
            foreach (['non-empty', 'escaped-output', 'no-inline-events', 'no-javascript-url', 'no-placeholder-link'] as $rule) {
                $cases["view {$view} {$rule}"] = ['view', $view, $rule];
            }
        }

        $css = 'resources/css/app.css';
        foreach ([
            '*, *::before, *::after { box-sizing: border-box; }',
            'html { min-width: 320px; overflow-x: clip; }',
            '[hidden] { display: none !important; }',
            ":root[data-theme='dark']",
            'color-scheme: dark;',
            '.dashboard-layout',
            '.history-panel',
            '.dashboard-main',
            '.result-tabs',
            '.tab-button',
            '.tab-panel',
            '.table-scroll',
            '.network-summary',
            '.connection-badge',
            '.dns-timeline',
            '.map-panel',
            '#ipMap',
            '.auth-page',
            '.auth-card',
            '.preferences',
            '.scanner-grid',
            '.scanner-card',
            '.scanner-form',
            '.scan-progress',
            '.scanner-result-panel',
            '.platform-shell',
            '.platform-card',
            '.pricing-grid',
            '.admin-layout',
            '.sr-only',
            ':focus-visible',
            '@media (max-width: 1180px)',
            '@media (max-width: 700px)',
            '@media (prefers-reduced-motion: reduce)',
            'overflow-wrap: anywhere;',
        ] as $required) {
            $cases["css contains {$required}"] = ['contains', $css, $required];
        }
        foreach ([
            'expression(',
            'javascript:',
            'behavior: url(',
            '@import url(http',
            '@import url(//',
            'width: 100vw;',
            'min-width: 1440px;',
            'z-index: 999999',
            'outline: none;',
            'transition: all ',
        ] as $forbidden) {
            $cases["css excludes {$forbidden}"] = ['excludes', $css, $forbidden];
        }

        $javascript = 'resources/js/app.js';
        $scannerJavascript = 'resources/js/scanner.js';
        foreach ([
            'textContent',
            'replaceChildren',
            'document.createElement',
            'AbortController',
            'clearTimeout',
            'response.ok',
            'response.text()',
            'JSON.parse',
            'encodeURIComponent',
            'noopener noreferrer',
            'aria-selected',
            'tabIndex',
            'ArrowLeft',
            'ArrowRight',
            'localStorage.getItem',
            'localStorage.setItem',
            'addEventListener',
            'querySelectorAll',
            'CustomEvent',
            'aria-expanded',
            'disabled = true',
            'finally',
            'new URL(',
            'controller.abort()',
            'window.confirm',
            'window.grecaptcha.execute',
        ] as $required) {
            $cases["app js contains {$required}"] = ['contains', $javascript, $required];
        }
        foreach ([
            'FormData',
            'Object.fromEntries',
            'Promise.all',
            'location.href',
            'terminal.has',
            'schedule(',
            'timers.delete',
            'controllers.add',
            'AbortError',
        ] as $required) {
            $cases["scanner js contains {$required}"] = ['contains', $scannerJavascript, $required];
        }
        foreach ([
            'innerHTML',
            'outerHTML',
            'insertAdjacentHTML',
            'eval(',
            'new Function',
            'document.write',
            'javascript:',
            'setInterval(',
            '.onclick =',
            'srcdoc',
        ] as $forbidden) {
            $cases["javascript excludes {$forbidden}"] = ['excludes', $javascript, $forbidden];
        }

        if (count($cases) < 500) {
            throw new RuntimeException('Frontend quality matrix must contain at least 500 distinct cases; found '.count($cases).'.');
        }

        return $cases;
    }

    private function projectPath(string $path): string
    {
        return dirname(__DIR__, 2).'/'.$path;
    }

    private function flatten(mixed $value, string $prefix = ''): array
    {
        if (! is_array($value)) {
            return [$prefix => $value];
        }

        $flattened = [];
        foreach ($value as $key => $item) {
            $child = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $flattened += $this->flatten($item, $child);
        }

        return $flattened;
    }
}
