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

use Buckaroo\Magento2\Model\ConfigProvider\Method\CapayableIn3;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Gateway\ErrorMapper\ErrorMessageMapperInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

class In3GatewayCommand extends GatewayCommand
{
    /**
     * @var CapayableIn3
     */
    private CapayableIn3 $capayableIn3Config;

    /**
     * @param CapayableIn3 $capayableIn3Config
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
        CapayableIn3 $capayableIn3Config,
        BuilderInterface $requestBuilderV3,
        BuilderInterface $requestBuilderV2,
        TransferFactoryInterface $transferFactory,
        ClientInterface $client,
        LoggerInterface $logger,
        HandlerInterface $handler = null,
        ValidatorInterface $validator = null,
        ErrorMessageMapperInterface $errorMessageMapper = null,
        SkipCommandInterface $skipCommand = null
    ) {
        $this->capayableIn3Config = $capayableIn3Config;

        parent::__construct(
            $requestBuilder,
            $transferFactory,
            $client,
            $logger,
            $handler,
            $validator,
            $errorMessageMapper,
            $skipCommand
        );

    }

    /**
     * @param BuilderInterface $requestBuilder
     * @return BuilderInterface
     */
    protected function getRequestBuilder(BuilderInterface $requestBuilder): BuilderInterface
    {
        if ($this->capayableIn3Config->isV3()) {
            $requestBuilder = $this->objectManager->get()
        }

        return $requestBuilder;
    }
}
