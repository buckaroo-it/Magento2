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

namespace TIG\Buckaroo\Gateway;

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
    public function order(\TIG\Buckaroo\Gateway\Http\Transaction $transaction);

    /**
     * @param Http\Transaction $transaction
     *
     * @return mixed
     */
    public function capture(\TIG\Buckaroo\Gateway\Http\Transaction $transaction);

    /**
     * @param Http\Transaction $transaction
     *
     * @return mixed
     */
    public function authorize(\TIG\Buckaroo\Gateway\Http\Transaction $transaction);

    /**
     * @param Http\Transaction $transaction
     *
     * @return mixed
     */
    public function refund(\TIG\Buckaroo\Gateway\Http\Transaction $transaction);

    /**
     * @param Http\Transaction $transaction
     *
     * @return mixed
     */
    public function cancel(\TIG\Buckaroo\Gateway\Http\Transaction $transaction);

    /**
     * @param Http\Transaction $transaction
     *
     * @return mixed
     */
    public function void(\TIG\Buckaroo\Gateway\Http\Transaction $transaction);
}
