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
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Buckaroo\Magento2\Model\Giftcard\RemoveException;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Magento2\Model\Giftcard\Remove as GiftcardRemover;
use Magento\Checkout\Model\Session;

class GiftcardRemove implements ActionInterface
{

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Framework\Controller\ResultFactory
     */
    protected $resultFactory;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards
     */
    protected $giftcardConfig;

    /**
     * @var \Buckaroo\Magento2\Logging\Log
     */
    protected $logger;

    /**
     * @var \Buckaroo\Magento2\Model\Giftcard\Remove
     */
    protected $giftcardRemover;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        Giftcards $giftcardConfig,
        Log $logger,
        GiftcardRemover $giftcardRemover,
        Session $checkoutSession
    ) {
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->giftcardConfig = $giftcardConfig;
        $this->logger = $logger;
        $this->giftcardRemover = $giftcardRemover;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Process action
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Exception
     */
    public function execute()
    {
        if (!$this->giftcardConfig->isRemoveButtonEnabled()) {
            return $this->renderError(
                __('Cannot remove giftcard, function disabled')
            );
        }

        if ($this->request->getParam('transaction_id') === null) {
            return $this->renderError(
                __('A `transaction_id` is required')
            );
        }

        try {
            $this->giftcardRemover->remove(
                $this->request->getParam('transaction_id'),
                $this->checkoutSession->getQuote()->getReservedOrderId()
            );
        } catch (RemoveException $th) {
            return $this->renderError($th->getMessage());
        } catch (\Throwable $th) {
            $this->logger->addDebug((string)$th);
            return $this->renderError(
                __('Cannot remove giftcard, internal server error')
            );
        }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData([
            'error' => false,
            'message' => __('Giftcard successfully removed')
        ]);
    }

    /**
     * Render any errors
     *
     * @param string $errorMessage
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    protected function renderError(string $errorMessage)
    {
        return $this->resultFactory
            ->create(ResultFactory::TYPE_JSON)
            ->setData([
                'error' => true,
                'message' => $errorMessage
            ]);
    }
}
