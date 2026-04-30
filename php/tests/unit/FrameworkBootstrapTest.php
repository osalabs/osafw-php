<?php
declare(strict_types=1);

require_once __DIR__ . '/../FrameworkTestCase.php';

final class FrameworkBootstrapTest extends FrameworkTestCase {
    public function testFrameworkBootstrapInitializesConfig(): void {
        $this->assertSame($_SERVER['HTTP_HOST'], $this->fw->config->ROOT_DOMAIN0);
        $this->assertIsArray($this->fw->GLOBAL);
    }

    public function testRouteParserMapsStandardRestListRoute(): void {
        $dispatcher = new Dispatcher([], $this->fw->config->ROOT_URL, $this->fw->config->ROUTE_PREFIXES);
        $route = $dispatcher->uriToRoute('GET', '/Admin/Demos', []);

        $this->assertSame('AdminDemos', $route->controller);
        $this->assertSame(fw::ACTION_INDEX, $route->action);
    }

    public function testSafeUrlAllowsOnlyClickableSchemes(): void {
        $this->assertSame('https://example.com/path', Utils::safeUrl('example.com/path'));
        $this->assertSame('mailto:test@example.com', Utils::safeUrl('mailto:test@example.com'));
        $this->assertSame('', Utils::safeUrl('javascript:alert(1)'));
        $this->assertSame('', Utils::safeUrl('//example.com/path'));
    }
}
