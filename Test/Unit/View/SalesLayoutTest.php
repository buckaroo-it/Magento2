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

namespace Buckaroo\Magento2\Test\Unit\View;

use Buckaroo\Magento2\Block\Order\Totals\Fee;
use PHPUnit\Framework\TestCase;

/**
 * Guards the customer and guest sales page layouts (order, invoice, credit memo; view and print).
 *
 * Declaring a block whose name Magento core or a theme also declares makes Magento destroy the earlier
 * declaration and everything under it (ScheduledStructure\Helper::_overrideElementWorkaround). Doing that on these
 * pages emptied item renderer lists and dropped theme or third-party totals, so the layouts may only attach the fee
 * block to the existing totals blocks.
 */
class SalesLayoutTest extends TestCase
{
    private const TOTALS_BLOCK = [
        'view' => 'order_totals',
        'print' => 'order_totals',
        'invoice' => 'invoice_totals',
        'printinvoice' => 'invoice_totals',
        'creditmemo' => 'creditmemo_totals',
        'printcreditmemo' => 'creditmemo_totals',
    ];

    /**
     * @return array<string, array{string, string}>
     */
    public static function salesPageLayouts(): array
    {
        $cases = [];
        foreach (['order', 'guest'] as $scope) {
            foreach (self::TOTALS_BLOCK as $page => $totalsBlock) {
                $handle = "sales_{$scope}_{$page}";
                $cases[$handle] = [$handle, $totalsBlock];
            }
        }
        return $cases;
    }

    /**
     * @dataProvider salesPageLayouts
     */
    public function testOnlyAttachesTheFeeBlockToTheExistingTotalsBlock(string $handle, string $totalsBlock): void
    {
        $file = dirname(__DIR__, 3) . "/view/frontend/layout/{$handle}.xml";
        $this->assertFileExists($file, "$handle.xml must exist so the fee shows on that page");
        $xml = simplexml_load_file($file);
        $this->assertNotFalse($xml, "$handle.xml is not valid XML");

        $this->assertSame([], $this->xpathNames($xml, '/page/body/block | /page/body/referenceContainer'),
            "$handle.xml must not add page structure");
        $this->assertSame([$totalsBlock], $this->xpathNames($xml, '/page/body/referenceBlock'),
            "$handle.xml must only reference $totalsBlock");

        $children = $xml->xpath("/page/body/referenceBlock[@name='$totalsBlock']/*");
        $this->assertCount(1, $children, "$handle.xml must add exactly one block to $totalsBlock");
        $this->assertSame('block', $children[0]->getName());
        $this->assertSame(Fee::class, (string)$children[0]['class']);
        $this->assertStringStartsWith('buckaroo_fee_', (string)$children[0]['name'],
            'The fee block needs its own name, never one that core or a theme declares');
        $this->assertSame([], $this->xpathNames($xml, '//block[contains(@class, "RendererList")]'),
            "$handle.xml must not declare item renderer lists");
    }

    /**
     * @param \SimpleXMLElement $xml
     * @param string            $query
     * @return string[]
     */
    private function xpathNames(\SimpleXMLElement $xml, string $query): array
    {
        return array_map(static fn ($node) => (string)$node['name'], $xml->xpath($query) ?: []);
    }
}
