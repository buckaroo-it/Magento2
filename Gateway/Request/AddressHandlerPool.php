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

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Api\AddressHandlerInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Exception;
use Magento\Sales\Model\Order;

class AddressHandlerPool
{
    /**
     * @var array|AddressHandlerInterface[]
     */
    protected array $addressHandlers;

    public function __construct(array $addressHandlers)
    {
        $this->addressHandlers = $addressHandlers;
    }

    /**
     * @param Order $order
     * @throws Exception
     */
    public function updateShippingAddress(Order $order)
    {
        try {
            foreach ($this->addressHandlers as $addressHandler) {
                $order = $addressHandler->handle($order);
            }
        } catch (\Throwable $th) {
            throw new Exception($th->getMessage(), 0, $th);
        }
    }
}
