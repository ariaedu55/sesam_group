<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Bootstrap;
use Sesam\Core\Request\Request;
use Sesam\Helpers\Html;

final class DesignSystemTest extends TestCase
{
    public function testDemoPageUsesTheVisualSystem(): void
    {
        $response = Bootstrap::kernel(dirname(__DIR__))->handle(new Request('GET', '/design-system'));
        $css = (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/sesam.css');

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('Système visuel', $response->content());
        $this->assertStringContainsString('btn btn-primary', $response->content());
        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertStringContainsString('--sesam-navy: #071a3d', $css);
        $this->assertStringContainsString('--sesam-cyan: #00d9f5', $css);
        $this->assertStringNotContainsString('#d4af37', strtolower($css));
    }

    public function testEveryLayoutIsMobileReady(): void
    {
        $layouts = glob(dirname(__DIR__) . '/app/Views/layouts/*.php') ?: [];
        $this->assertNotEmpty($layouts);
        foreach ($layouts as $layout) {
            $html = (string) file_get_contents($layout);
            $name = basename($layout);
            $this->assertStringContainsString('<meta name="viewport" content="width=device-width, initial-scale=1">', $html, $name);
            $this->assertStringContainsString('<html lang="fr"', $html, $name);
            $this->assertStringNotContainsString('user-scalable=no', $html, $name);
        }
        foreach (glob(dirname(__DIR__) . '/public/assets/css/*.css') ?: [] as $stylesheet) {
            $this->assertMatchesRegularExpression('/@media\s*\((max|min)-width/', (string) file_get_contents($stylesheet), basename($stylesheet));
        }
    }

    public function testEveryTableScrollsInsideItsOwnWrapper(): void
    {
        $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__) . '/app/Views', \FilesystemIterator::SKIP_DOTS));
        $checked = 0;
        foreach ($views as $view) {
            $lines = file((string) $view->getPathname(), FILE_IGNORE_NEW_LINES) ?: [];
            foreach ($lines as $index => $line) {
                if (preg_match('/<table[\s>]/', $line) !== 1) {
                    continue;
                }
                ++$checked;
                $this->assertStringContainsString('class="table-wrap', $lines[$index - 1] ?? '', $view->getFilename() . ':' . ($index + 1));
            }
        }
        $this->assertGreaterThan(0, $checked);
    }

    public function testAssetAndPageUrlsFollowTheFrontController(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $version = '?v=' . filemtime(dirname(__DIR__) . '/public/assets/css/sesam.css');
        $this->assertSame('/assets/css/sesam.css' . $version, Html::asset('assets/css/sesam.css'));
        $this->assertSame('/assets/css/absent.css', Html::asset('assets/css/absent.css'));
        $this->assertSame('/design-system', Html::url('design-system'));

        $_SERVER['SCRIPT_NAME'] = '/projet Sesam/public/index.php';
        $this->assertSame('/projet%20Sesam/public/assets/css/sesam.css' . $version, Html::asset('assets/css/sesam.css'));
        $this->assertSame('/projet%20Sesam/design-system', Html::url('design-system'));
    }
}
