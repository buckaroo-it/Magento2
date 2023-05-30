<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Request\BasicParameter\AdditionalParametersDataBuilder;
use PHPUnit\Framework\TestCase;

class AdditionalParametersDataBuilderTest extends TestCase
{
    private AdditionalParametersDataBuilder $dataBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataBuilder = new AdditionalParametersDataBuilder(
            'test_action',
            [
                'add_param_test_1' => 'test1',
                'add_param_test_2' => 'test2',
            ]
        );
    }

    public function testBuild(): void
    {
        $result = $this->dataBuilder->build([]);
        $expectedAdditionalParameters = [
            'service_action_from_magento' => 'test_action',
            'initiated_by_magento'        => 1,
            'add_param_test_1'            => 'test1',
            'add_param_test_2'            => 'test2'
        ];

        $this->assertEquals(['additionalParameters' => $expectedAdditionalParameters], $result);
    }

    public function testGetSetAction(): void
    {
        $action = 'new_action';
        $this->dataBuilder->setAction($action);

        $this->assertEquals($action, $this->dataBuilder->getAction());
    }

    public function testGetSetAdditionalParameter(): void
    {
        $key = 'example_key';
        $value = 'example_value';

        $this->dataBuilder->setAdditionalParameter($key, $value);

        $this->assertEquals($value, $this->dataBuilder->getAdditionalParameter($key));
    }
}