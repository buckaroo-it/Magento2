<?php

/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
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
