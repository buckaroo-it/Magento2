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

namespace Buckaroo\Magento2\Controller\Checkout;

use Buckaroo\Magento2\Logging\Log;

class SecondChance extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var \Buckaroo\Magento2\Model\SecondChanceRepository
     */
    protected $secondChanceRepository;

    /**
     * @param \Magento\Framework\App\Action\Context               $context
     * @param Log                                                 $logger
     * @param SecondChanceRepository                              $secondChanceRepository
     *
     * @throws \Buckaroo\Magento2\Exception
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        Log $logger,
        \Buckaroo\Magento2\Model\SecondChanceRepository $secondChanceRepository
    ) {
        parent::__construct($context);

        $this->logger                 = $logger;
        $this->secondChanceRepository = $secondChanceRepository;
    }

    /**
     * Process action
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Exception
     */
    public function execute()
    {
        if ($token = $this->getRequest()->getParam('token')) {
            $this->secondChanceRepository->getSecondChanceByToken($token);
        }
        return $this->_redirect('checkout', ['_fragment' => 'payment']);
    }
}
