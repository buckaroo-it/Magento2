<?php
/**
 * Stub declarations for Magento code-generated factory classes.
 *
 * These factories only exist in generated/code after Magento's code generation
 * has run. When the unit suite runs outside a full Magento runtime (host
 * machine, CI without generation), reflection on these class names fails.
 * Each stub is guarded by class_exists(), so in an environment where the real
 * generated class is autoloadable the stub is skipped entirely.
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Api\Data {
    if (!class_exists(\Buckaroo\Magento2\Api\Data\BreakdownItemInterfaceFactory::class)) {
        class BreakdownItemInterfaceFactory
        {
            /**
             * @param array $data
             * @return BreakdownItemInterface|null
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function create(array $data = [])
            {
                return null;
            }
        }
    }

    if (!class_exists(\Buckaroo\Magento2\Api\Data\SecondChanceInterfaceFactory::class)) {
        class SecondChanceInterfaceFactory
        {
            /**
             * @param array $data
             * @return SecondChanceInterface|null
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function create(array $data = [])
            {
                return null;
            }
        }
    }

    if (!class_exists(\Buckaroo\Magento2\Api\Data\SecondChanceSearchResultsInterfaceFactory::class)) {
        class SecondChanceSearchResultsInterfaceFactory
        {
            /**
             * @param array $data
             * @return SecondChanceSearchResultsInterface|null
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function create(array $data = [])
            {
                return null;
            }
        }
    }
}

namespace Buckaroo\Magento2\Api\Data\PaypalExpress {
    if (!class_exists(\Buckaroo\Magento2\Api\Data\PaypalExpress\OrderCreateResponseInterfaceFactory::class)) {
        class OrderCreateResponseInterfaceFactory
        {
            /**
             * @param array $data
             * @return OrderCreateResponseInterface|null
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function create(array $data = [])
            {
                return null;
            }
        }
    }
}

namespace Buckaroo\Magento2\Model\PaypalExpress {
    if (!class_exists(\Buckaroo\Magento2\Model\PaypalExpress\OrderUpdateFactory::class)) {
        class OrderUpdateFactory
        {
            /**
             * @param array $data
             * @return OrderUpdate|null
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function create(array $data = [])
            {
                return null;
            }
        }
    }
}

namespace Magento\Payment\Gateway\Validator {
    if (!class_exists(\Magento\Payment\Gateway\Validator\ResultInterfaceFactory::class)) {
        class ResultInterfaceFactory
        {
            /**
             * @param array $data
             * @return ResultInterface|null
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function create(array $data = [])
            {
                return null;
            }
        }
    }
}

namespace Magento\MediaStorage\Model\File {
    if (!class_exists(\Magento\MediaStorage\Model\File\UploaderFactory::class)) {
        class UploaderFactory
        {
            /**
             * @param array $data
             * @return Uploader|null
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function create(array $data = [])
            {
                return null;
            }
        }
    }
}
