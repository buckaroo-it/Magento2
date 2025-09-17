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

namespace Buckaroo\Magento2\Controller\Redirect;

use Magento\Framework\App\ResponseInterface;

class AnalyticsProcess extends Process
{
    /**
     * Redirect to Success url, which means everything seems to be going fine
     *
     * @return ResponseInterface
     */
    protected function redirectSuccess(): ResponseInterface
    {
        $this->eventManager->dispatch('buckaroo_process_redirect_success_before');

        $store = $this->order->getStore();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $url = $this->accountConfig->getSuccessRedirect($store);

        // Store the status code in payment additional information for success page plugin
        $statusCode = (int)$this->redirectRequest->getStatusCode();
        $this->payment->setAdditionalInformation('buckaroo_statuscode', $statusCode);
        $this->payment->save();

        $successMessage = __('Your order has been placed successfully.');
        if (method_exists($this, 'addSuccessMessage')) {
            $this->addSuccessMessage($successMessage);
        } else {
            $this->messageManager->addSuccessMessage($successMessage);
        }

        $this->quote->setReservedOrderId(null);
        $this->customerSession->setSkipSecondChance(false);

        $this->redirectSuccessApplePay();

        // Only include analytics-related query parameters, not all Buckaroo response data
        $queryArguments = [];
        if (class_exists(\Buckaroo\Magento2\Service\CookieParamService::class)) {
            $cookieParamService = $this->_objectManager->get(
                \Buckaroo\Magento2\Service\CookieParamService::class
            );

            $queryArguments = $cookieParamService->getQueryArgumentsByCookies($this->getRequest()->getParams());
        }

        if (strpos($url, '?') !== false) {
            $url = substr($url, 0, strpos($url, '?'));
        }

        if (method_exists($this, 'handleProcessedResponse')) {
            return $this->handleProcessedResponse($url, ['_query' => $queryArguments]);
        }
        return $this->_redirect($url, ['_query' => $queryArguments]);
    }
}
