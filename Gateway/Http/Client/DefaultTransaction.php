<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Http\Client;

use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Default Gateway Client
 */
class DefaultTransaction implements ClientInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Logs payment related information used for debug
     *
     * @var Logger
     */
    protected $paymentLogger;

    /**
     * @var BuckarooAdapter
     */
    protected $adapter;

    /**
     * @var string
     */
    protected $action;

    /**
     * @param LoggerInterface $logger
     * @param Logger          $paymentLogger
     * @param BuckarooAdapter $adapter
     * @param string          $action
     */
    public function __construct(
        LoggerInterface $logger,
        Logger $paymentLogger,
        BuckarooAdapter $adapter,
        string $action = TransactionType::PAY
    ) {
        $this->logger = $logger;
        $this->paymentLogger = $paymentLogger;
        $this->adapter = $adapter;
        $this->action = $action;
    }

    /**
     * @inheritdoc
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $data = $transferObject->getBody();
        $log = [
            'request' => $data,
            'client' => static::class
        ];
        $response['object'] = [];
        $paymentMethod = $data['payment_method'];
        unset($data['payment_method']);

        try {
            $response['object'] = $this->process($paymentMethod, $data);
        } catch (\Exception $e) {
            $message = __($e->getMessage() ?: 'Sorry, but something went wrong');
            $this->logger->critical($message);
            throw new ClientException($message);
        } finally {
            $log['response'] = (array)$response['object'];
            $this->paymentLogger->debug($log);
        }

        return $response;
    }

    /**
     * Process http request
     *
     * @param  string              $paymentMethod
     * @param  array               $data
     * @throws Throwable
     * @return TransactionResponse
     */
    protected function process(string $paymentMethod, array $data): TransactionResponse
    {
        if (isset($data['encryptedCardData'])) {
            $this->action = TransactionType::PAY_ENCRYPTED;
        }
        return $this->adapter->execute($this->action, $paymentMethod, $data);
    }
}
