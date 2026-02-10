<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Config;
use Dynart\Micro\ConfigInterface;
use Dynart\Micro\Request;
use Dynart\Micro\RequestInterface;
use Dynart\Micro\Router;
use Dynart\Micro\Translation;
use Dynart\Micro\Middleware\LocaleResolver;

/**
 * @covers \Dynart\Micro\Middleware\LocaleResolver
 */
final class LocaleResolverTest extends TestCase
{
    private function createConfig(array $allLocales, string $default): ConfigInterface {
        $config = $this->getMockBuilder(ConfigInterface::class)
            ->getMock();

        $config->method('get')->willReturnCallback(function($name, $defaultVal = null) use ($default) {
            if ($name == Translation::CONFIG_DEFAULT) return $default;
            return $defaultVal;
        });
        $config->method('getCommaSeparatedValues')->willReturn($allLocales);
        return $config;
    }

    private function createRouter(ConfigInterface $config, RequestInterface $request): Router {
        return new Router($config, $request);
    }

    private function createRequest(string $route = '/', ?string $acceptLanguage = null): RequestInterface {
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $request->method('get')->willReturn($route);
        $request->method('httpMethod')->willReturn('GET');

        $request->method('server')->willReturnCallback(function($name) use ($acceptLanguage) {
            if ($name == 'HTTP_ACCEPT_LANGUAGE') return $acceptLanguage;
            return null;
        });

        return $request;
    }

    public function testDoesNothingWhenSingleLocale(): void {
        $config = $this->createConfig(['en'], 'en');
        $request = $this->createRequest();
        $router = $this->createRouter($config, $request);
        $translation = new Translation($config);

        $resolver = new LocaleResolver($request, $router, $translation);
        $resolver->run();

        $this->assertEquals('en', $translation->locale());
        $this->assertEmpty($router->prefixVariables());
    }

    public function testAddsPrefixVariableWhenMultiLocale(): void {
        $config = $this->createConfig(['en', 'hu'], 'en');
        $request = $this->createRequest('/en/test');
        $router = $this->createRouter($config, $request);
        $translation = new Translation($config);

        $resolver = new LocaleResolver($request, $router, $translation);
        $resolver->run();

        $this->assertCount(1, $router->prefixVariables());
    }

    public function testSetsLocaleViaAcceptLanguageHeader(): void {
        $config = $this->createConfig(['en', 'hu'], 'en');
        $request = $this->createRequest('/test', 'hu-HU,hu;q=0.9,en-US;q=0.8');
        $router = $this->createRouter($config, $request);
        $translation = new Translation($config);

        $resolver = new LocaleResolver($request, $router, $translation);
        $resolver->run();

        $this->assertEquals('hu', $translation->locale());
    }

    public function testAcceptLanguageIgnoredWhenLocaleNotInAllLocales(): void {
        $config = $this->createConfig(['en', 'hu'], 'en');
        $request = $this->createRequest('/test', 'fr-FR,fr;q=0.9');
        $router = $this->createRouter($config, $request);
        $translation = new Translation($config);

        $resolver = new LocaleResolver($request, $router, $translation);
        $resolver->run();

        $this->assertEquals('en', $translation->locale());
    }

    public function testSetsLocaleViaUrlSegment(): void {
        $config = $this->createConfig(['en', 'hu'], 'en');
        $request = $this->createRequest('/hu/some/path');
        $router = $this->createRouter($config, $request);
        $translation = new Translation($config);

        $resolver = new LocaleResolver($request, $router, $translation);
        $resolver->run();

        $this->assertEquals('hu', $translation->locale());
    }

    public function testUrlSegmentOverridesAcceptLanguage(): void {
        $config = $this->createConfig(['en', 'hu'], 'en');
        $request = $this->createRequest('/hu/test', 'en-US,en;q=0.9');
        $router = $this->createRouter($config, $request);
        $translation = new Translation($config);

        $resolver = new LocaleResolver($request, $router, $translation);
        $resolver->run();

        $this->assertEquals('hu', $translation->locale());
    }

    public function testInvalidUrlSegmentDoesNotChangeLocale(): void {
        $config = $this->createConfig(['en', 'hu'], 'en');
        $request = $this->createRequest('/xx/test');
        $router = $this->createRouter($config, $request);
        $translation = new Translation($config);

        $resolver = new LocaleResolver($request, $router, $translation);
        $resolver->run();

        $this->assertEquals('en', $translation->locale());
    }

    public function testNoAcceptLanguageHeaderKeepsDefault(): void {
        $config = $this->createConfig(['en', 'hu'], 'en');
        $request = $this->createRequest('/test', null);
        $router = $this->createRouter($config, $request);
        $translation = new Translation($config);

        $resolver = new LocaleResolver($request, $router, $translation);
        $resolver->run();

        $this->assertEquals('en', $translation->locale());
    }
}
