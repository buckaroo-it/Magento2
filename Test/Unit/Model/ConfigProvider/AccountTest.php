<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
namespace TIG\Buckaroo\Test\Unit\Model\ConfigProvider;

use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Model\ConfigProvider\Account;

class AccountTest extends BaseTest
{
    protected $instanceClass = Account::class;

    /**
     * Test the getConfig function.
     */
    public function testGetConfig()
    {
        $expectedKeys = [
            'active', 'secret_key', 'merchant_key', 'merchant_guid', 'transaction_label', 'certificate_file',
            'order_confirmation_email', 'invoice_email', 'success_redirect', 'failure_redirect', 'cancel_on_failed',
            'digital_signature', 'debug_types', 'debug_email', 'limit_by_ip', 'fee_percentage_mode',
            'payment_fee_label', 'order_status_new', 'order_status_pending', 'order_status_success',
            'order_status_failed', 'create_order_before_transaction'
        ];

        $instance = $this->getInstance();
        $result = $instance->getConfig();

        $this->assertInternalType('array', $result);

        $resultKeys = array_keys($result);
        $this->assertEmpty(array_merge(array_diff($expectedKeys, $resultKeys), array_diff($resultKeys, $expectedKeys)));
    }
}
