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

namespace Buckaroo\Magento2\Gateway;

interface GatewayInterface
{
    /**
     * @param int $mode
     *
     * @return $this
     */
    public function setMode($mode);

    /**
     * @param Http\Transaction $transaction
     *
     * @return mixed
     */
    public function order(\Buckaroo\Magento2\Gateway\Http\Transaction $transaction);

    /**
     * @param Http\Transaction $transaction
     *
     * @return mixed
     */
    public function capture(\Buckaroo\Magento2\Gateway\Http\Transaction $transaction);

    /**
     * @param Http\Transaction $transaction
     *
     * @return mixed
     */
    public function authorize(\Buckaroo\Magento2\Gateway\Http\Transaction $transaction);

    /**
     * @param Http\Transaction $transaction
     *
     * @return mixed
     */
    public function refund(\Buckaroo\Magento2\Gateway\Http\Transaction $transaction);

    /**
     * @param Http\Transaction $transaction
     *
     * @return mixed
     */
    public function cancel(\Buckaroo\Magento2\Gateway\Http\Transaction $transaction);

    /**
     * @param Http\Transaction $transaction
     *
     * @return mixed
     */
    public function void(\Buckaroo\Magento2\Gateway\Http\Transaction $transaction);
}
