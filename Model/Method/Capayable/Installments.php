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
namespace Buckaroo\Magento2\Model\Method\Capayable;

use Buckaroo\Magento2\Model\Method\Capayable;

class Installments extends Capayable
{
    /** Payment Code */
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_capayablein3';

    const CAPAYABLE_ORDER_SERVICE_ACTION = 'PayInInstallments';

    /** @var string */
    public $buckarooPaymentMethodCode = 'capayablein3';

    /** @var string */
    // @codingStandardsIgnoreLine
    protected $_code = 'buckaroo_magento2_capayablein3';

    /**
     * {@inheritDoc}
     */
    public function getCapayableService($payment)
    {
        $services = parent::getCapayableService($payment);

        $requestParameter = $services['RequestParameter'];

        $requestParameter[] = ['_' => 'true', 'Name' => 'IsInThreeGuarantee'];
        $services['RequestParameter'] = $requestParameter;

        return $services;
    }
}
