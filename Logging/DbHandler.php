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
namespace Buckaroo\Magento2\Logging;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class DbHandler extends Base
{
    protected $logFactory;

    // @codingStandardsIgnoreLine
    protected $loggerType = Logger::DEBUG;

    protected $checkoutSession;

    protected $session;

    protected $customerSession;

    /**
     */
    public function __construct(
        \Buckaroo\Magento2\Model\LogFactory $logFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Session\SessionManager $sessionManager,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->logFactory = $logFactory;
        $this->checkoutSession  = $checkoutSession;
        $this->session = $sessionManager;
        $this->customerSession = $customerSession;
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $record)
    {
        $now = new \DateTime();
        $logFactory = $this->logFactory->create();
        try {
            $logData = json_decode($record['message'],true);
        } catch(\Exception $e) {
            $logData=[];
        }

        $logFactory->setData([
            'channel'     => $record['channel'],
            'level'       => $record['level'],
            'message'     => $record['message'],
            'time'        => $now->format('Y-m-d H:i:s'),
            'session_id'  => ($logData['sid']) ?? '',
            'customer_id' => ($logData['cid']) ?? '',
            'quote_id'    => ($logData['qid']) ?? '',
            'order_id'    => ($logData['id']) ?? ''
        ]);
        $logFactory->save();
    }
}
