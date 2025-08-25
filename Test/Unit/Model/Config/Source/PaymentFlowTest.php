<?php

/**
 * NOTICE OF LICENSE
 * (header trimmed)
 */

namespace Buckaroo\Magento2\Test\Unit\Model\Config\Source;

use Buckaroo\Magento2\Model\Config\Source\PaymentFlow;
use Buckaroo\Magento2\Test\BaseTest;

class PaymentFlowTest extends BaseTest
{
    protected $instanceClass = PaymentFlow::class;

    public static function toOptionArrayProvider(): array
    {
        return [
            [['value' => 'order',     'label' => 'Combined']],
            [['value' => 'authorize', 'label' => 'Separate authorize and capture']],
        ];
    }

    /**
     * @dataProvider toOptionArrayProvider
     */
    public function testToOptionArray(array $expected)
    {
        $instance = $this->getInstance();
        $result   = $instance->toOptionArray();

        // Normalize to [value => (string)label]
        $map = [];
        foreach ((array)$result as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = isset($row['value']) ? (string)$row['value'] : null;
            $label = isset($row['label']) ? (string)$row['label'] : null;
            if ($value !== null) {
                $map[$value] = $label;
            }
        }

        $value = (string)$expected['value'];
        $label = (string)$expected['label'];

        $this->assertArrayHasKey(
            $value,
            $map,
            "Option '{$value}' not found. Available: " . json_encode(array_keys($map))
        );
        $this->assertSame(
            $label,
            $map[$value],
            "Label mismatch for '{$value}'. Got '{$map[$value]}'"
        );
    }
}
