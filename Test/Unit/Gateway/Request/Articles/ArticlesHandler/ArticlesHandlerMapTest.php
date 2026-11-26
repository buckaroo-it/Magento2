<?php

/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\Articles\ArticlesHandler;

use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\AfterpayOldHandler;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\ArticlesHandlerFactory;
use PHPUnit\Framework\TestCase;

/**
 * The article handler map lives in etc/di.xml; a method without an entry silently gets the default handler,
 * whose article lines the legacy Afterpay service does not accept.
 */
class ArticlesHandlerMapTest extends TestCase
{
    /**
     * @return array<string, string> method code => handler class
     */
    private function getConfiguredHandlers(): array
    {
        $xml = simplexml_load_file(dirname(__DIR__, 6) . '/etc/di.xml');
        $nodes = $xml->xpath(sprintf(
            '//type[@name="%s"]/arguments/argument[@name="articlesHandlers"]/item',
            ArticlesHandlerFactory::class
        ));

        $handlers = [];
        foreach ($nodes as $node) {
            $handlers[(string)$node['name']] = trim((string)$node);
        }

        return $handlers;
    }

    public function testBothLegacyAfterpayMethodsUseTheLegacyAfterpayHandler(): void
    {
        $handlers = $this->getConfiguredHandlers();

        $this->assertSame(AfterpayOldHandler::class, $handlers['afterpay'] ?? null);
        $this->assertSame(AfterpayOldHandler::class, $handlers['afterpay2'] ?? null);
    }

    public function testEveryConfiguredHandlerClassExists(): void
    {
        $handlers = $this->getConfiguredHandlers();

        $this->assertSame(
            [
                'default', 'afterpay', 'afterpay2', 'afterpay20', 'capayablein3',
                'zakelijkoprekening', 'billink', 'klarnakp',
            ],
            array_keys($handlers),
            'Unexpected set of configured article handlers'
        );

        foreach ($handlers as $method => $class) {
            $this->assertTrue(class_exists($class), sprintf('Handler for "%s" does not exist: %s', $method, $class));
        }
    }
}
