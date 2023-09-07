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

namespace Buckaroo\Magento2\Gateway\Command;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Http\Client\TransactionPayRemainder;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\ErrorMapper\ErrorMessageMapperInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

/**
 * Class GatewayCommand
 *
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.0.2
 */
class GatewayCommand implements CommandInterface
{
    /**
     * @var BuilderInterface
     */
    protected $requestBuilder;

    /**
     * @var TransferFactoryInterface
     */
    protected $transferFactory;

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var HandlerInterface
     */
    protected $handler;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ErrorMessageMapperInterface
     */
    protected $errorMessageMapper;

    /**
     * @var SkipCommandInterface|null
     */
    protected ?SkipCommandInterface $skipCommand;

    /**
     * @param BuilderInterface $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $client
     * @param LoggerInterface $logger
     * @param HandlerInterface|null $handler
     * @param ValidatorInterface|null $validator
     * @param ErrorMessageMapperInterface|null $errorMessageMapper
     * @param SkipCommandInterface|null $skipCommand
     */
    public function __construct(
        BuilderInterface            $requestBuilder,

        TransferFactoryInterface    $transferFactory,
        ClientInterface             $client,
        LoggerInterface             $logger,
        HandlerInterface            $handler = null,
        ValidatorInterface          $validator = null,
        ErrorMessageMapperInterface $errorMessageMapper = null,
        SkipCommandInterface        $skipCommand = null
    ) {
        $this->requestBuilder = $this->getRequestBuilder($requestBuilder);
        $this->transferFactory = $transferFactory;
        $this->client = $client;
        $this->handler = $handler;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->errorMessageMapper = $errorMessageMapper;
        $this->skipCommand = $skipCommand;
    }

    /**
     * Executes command basing on business object
     *
     * @param array $commandSubject
     * @return void
     * @throws CommandException
     * @throws ClientException
     * @throws ConverterException
     */
    public function execute(array $commandSubject): void
    {
        if ($this->client instanceof TransactionPayRemainder) {
            $paymentDO = SubjectReader::readPayment($commandSubject);
            $orderIncrementId = $paymentDO->getOrder()->getOrder()->getIncrementId();
            $commandSubject['action'] = $this->client->setServiceAction($orderIncrementId);
        }

        if ($this->skipCommand !== null && $this->skipCommand->isSkip($commandSubject)) {
            return;
        }

        // @TODO implement exceptions catching
        $transferO = $this->transferFactory->create(
            $this->requestBuilder->build($commandSubject)
        );

        $response = $this->client->placeRequest($transferO);
        if ($this->validator !== null) {
            $result = $this->validator->validate(
                array_merge($commandSubject, ['response' => $response])
            );
            if (!$result->isValid()) {
                $this->processErrors($result);
            }
        }

        if ($this->handler) {
            $this->handler->handle(
                $commandSubject,
                $response
            );
        }
    }

    /**
     * Tries to map error messages from validation result and logs processed message.
     * Throws an exception with mapped message or default error.
     *
     * @param ResultInterface $result
     * @throws CommandException
     */
    private function processErrors(ResultInterface $result)
    {
        $messages = [];
        if (empty($result->getFailsDescription())) {
            $errorsSource = array_merge($result->getErrorCodes(), $result->getFailsDescription());
            foreach ($errorsSource as $errorCodeOrMessage) {
                $errorCodeOrMessage = (string)$errorCodeOrMessage;

                // error messages mapper can be not configured if payment method doesn't have custom error messages.
                if ($this->errorMessageMapper !== null) {
                    $mapped = (string)$this->errorMessageMapper->getMessage($errorCodeOrMessage);
                    if (!empty($mapped)) {
                        $messages[] = $mapped;
                        $errorCodeOrMessage = $mapped;
                    }
                }
                $this->logger->critical('Payment Error: ' . $errorCodeOrMessage);
            }
        } else {
            $messages[] = (string)$result->getFailsDescription()[0] ?? '';
        }


        $errorMessage = '';
        if (!empty($messages)) {
            foreach ($messages as $message) {
                $errorMessage .= __($message) . PHP_EOL;
            }
            $errorMessage = rtrim($errorMessage);
        } else {
            $errorMessage ='Transaction has been declined. Please try again later.';
        }

        throw new CommandException(__($errorMessage));
    }

    /**
     * @param BuilderInterface $requestBuilder
     * @return BuilderInterface
     */
    protected function getRequestBuilder(BuilderInterface $requestBuilder): BuilderInterface
    {
        return $requestBuilder;
    }
}
