<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

namespace Buckaroo\Magento2\Controller\Checkout;

use Buckaroo\Magento2\Block\Checkout\SecondChance\Confirm;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\SecondChanceRepository;
use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\View\Result\PageFactory;

/**
 * Opening a reminder link shows a page with a button; only submitting that form restores the cart
 *
 * E-mail security scanners open links with a GET to inspect them. Redeeming the single-use link
 * on that GET would use it up before the customer clicks it.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SecondChance extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    /**
     * Format of the tokens generated for Second Chance links
     */
    private const TOKEN_PATTERN = '/\A[A-Za-z0-9]{32}\z/';

    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var SecondChanceRepository
     */
    protected $secondChanceRepository;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * @param Context                $context
     * @param Log                    $logger
     * @param SecondChanceRepository $secondChanceRepository
     * @param CheckoutSession        $checkoutSession
     * @param CustomerSession        $customerSession
     * @param PageFactory|null       $resultPageFactory
     */
    public function __construct(
        Context $context,
        Log $logger,
        SecondChanceRepository $secondChanceRepository,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        ?PageFactory $resultPageFactory = null
    ) {
        parent::__construct($context);
        $this->logger                 = $logger;
        $this->secondChanceRepository = $secondChanceRepository;
        $this->checkoutSession        = $checkoutSession;
        $this->customerSession        = $customerSession;
        $this->resultPageFactory      = $resultPageFactory
            ?: ObjectManager::getInstance()->get(PageFactory::class);
    }

    /**
     * Process action
     *
     * @throws Exception
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $token = $this->getRequest()->getParam('token');

        if (!$token) {
            $this->logger->addWarning('SecondChance: No token provided');
            $this->messageManager->addErrorMessage(__('Invalid link. Please try again.'));
            return $this->handleRedirect('checkout/cart');
        }

        if (!$this->isConfirmed()) {
            return $this->showConfirmation($token);
        }

        $failedRedirect = $this->restoreCart($token);
        if ($failedRedirect !== null) {
            return $failedRedirect;
        }

        $quote = $this->checkoutSession->getQuote();

        if (!$quote->getId()) {
            $this->logger->addError('SecondChance: No quote in session after restoration');
            $this->messageManager->addErrorMessage(__('Unable to restore your cart. Please try again or contact support.'));
            return $this->handleRedirect('checkout/cart');
        }

        // Link for a guest order or another customer's order clicked while a customer is logged
        // in: log out, so a guest order continues as guest and a customer order asks its owner
        // to sign in below. Auto-login is a security risk (link reuse, shared inboxes) and would
        // assign the order to the wrong account. logout() destroys the PHP session, which detaches
        // the customer and checkout storage. regenerateId() binds only its own storage to the new
        // session, so call it on both: the customer one keeps the sign-in redirect set below.
        if ($this->customerSession->isLoggedIn()
            && (int) $quote->getCustomerId() !== (int) $this->customerSession->getCustomerId()
        ) {
            $this->customerSession->logout();
            $this->customerSession->regenerateId();
            $this->checkoutSession->regenerateId();
            $this->checkoutSession->replaceQuote($quote);
            $this->checkoutSession->setQuoteId($quote->getId());
        }

        if ($quote->getCustomerId() && !$this->customerSession->isLoggedIn()) {
            $targetUrl = $this->_url->getUrl('checkout', ['_fragment' => 'payment']);
            $this->customerSession->setBeforeAuthUrl($targetUrl);
            $this->customerSession->setAfterAuthUrl($targetUrl);
            $this->messageManager->addNoticeMessage(__('Please sign in to continue with your restored cart.'));
            return $this->handleRedirect('customer/account/login');
        }

        $this->messageManager->addSuccessMessage(__('Your cart has been restored. You can now complete your purchase.'));

        return $this->handleRedirect('checkout', ['_query' => $this->getUtmParams(), '_fragment' => 'payment']);
    }

    /**
     * Show the page that asks the customer to confirm restoring the cart
     *
     * @param mixed $token
     * @return ResultInterface
     */
    private function showConfirmation($token): ResultInterface
    {
        $failedRedirect = $this->handleLinkErrors(function () use ($token) {
            $this->secondChanceRepository->assertTokenRedeemable($token);
        }, $token);
        if ($failedRedirect !== null) {
            return $failedRedirect;
        }

        $page = $this->resultPageFactory->create();
        $page->getConfig()->getTitle()->set(__('Restore your cart'));

        return $page;
    }

    /**
     * Whether the customer submitted the confirmation form
     *
     * Magento checks the form key of every frontend POST before the action runs.
     *
     * @return bool
     */
    private function isConfirmed(): bool
    {
        $request = $this->getRequest();

        return $request instanceof HttpRequest && $request->isPost();
    }

    /**
     * Tracking parameters of the request, as plain strings
     *
     * @return array
     */
    private function getUtmParams(): array
    {
        return array_filter(
            array_intersect_key($this->getRequest()->getParams(), array_flip(Confirm::UTM_PARAMS)),
            'is_string'
        );
    }

    /**
     * Restore the cart for the token, or return the redirect when it cannot be restored
     *
     * @param mixed $token
     * @return Redirect|null
     */
    private function restoreCart($token): ?Redirect
    {
        return $this->handleLinkErrors(function () use ($token) {
            $this->secondChanceRepository->getSecondChanceByToken($token);
        }, $token);
    }

    /**
     * Run a step for the link, and turn a link that cannot be used into a redirect to the cart
     *
     * @param callable $step
     * @param mixed    $token
     * @return Redirect|null
     */
    private function handleLinkErrors(callable $step, $token): ?Redirect
    {
        try {
            if (!$this->isWellFormedToken($token)) {
                throw new NoSuchEntityException(__('Invalid token.'));
            }
            $step();
        } catch (StateException $e) {
            $this->messageManager->addNoticeMessage($e->getMessage());
            return $this->handleRedirect('checkout/cart');
        } catch (Exception $e) {
            $this->logger->addWarning('SecondChance: invalid or expired token');
            $this->messageManager->addErrorMessage(__('Invalid or expired link. Please try again.'));
            return $this->handleRedirect('checkout/cart');
        }

        return null;
    }

    /**
     * Accept only a single string in the generated token format
     *
     * @param mixed $token
     * @return bool
     */
    private function isWellFormedToken($token): bool
    {
        return is_string($token) && preg_match(self::TOKEN_PATTERN, $token) === 1;
    }

    /**
     * Handle redirect to a specified path with arguments
     *
     * @param string $path
     * @param array $arguments
     * @return Redirect
     */
    protected function handleRedirect($path, $arguments = []): Redirect
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $fragment = isset($arguments['_fragment']) ? '#' . $arguments['_fragment'] : '';
        $queryParams = $arguments['_query'] ?? [];
        unset($arguments['_fragment'], $arguments['_query']);

        $arguments['_nosid'] = true;
        $url = $this->_url->getUrl($path, $arguments);
        $separator = strpos($url, '?') === false ? '?' : '&';
        // Pass "&" explicitly. PHP's arg_separator.output is often "&amp;", which
        // would put a literal amp; into every tracking parameter after the first.
        $queryString = $queryParams ? $separator . http_build_query($queryParams, '', '&') : '';

        return $resultRedirect->setUrl($url . $queryString . $fragment);
    }
}
