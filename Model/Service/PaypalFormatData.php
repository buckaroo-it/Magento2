<?php

namespace Buckaroo\Magento2\Model\Service;

use Magento\Framework\DataObject;

class PaypalFormatData implements FormatFormDataInterface
{
    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    private \Magento\Framework\DataObjectFactory $dataObjectFactory;

    /**
     * @param \Magento\Framework\DataObjectFactory $dataObjectFactory
     */
    public function __construct(
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * @param array $productData
     * @return DataObject
     * @throws AddProductException
     */
    public function getProductObject(array $productData): DataObject
    {
        $data = [];

        foreach ($productData as $orderKeyValue) {
            $data[$orderKeyValue->getName()] = $orderKeyValue->getValue();
        }
        $dataObject = $this->dataObjectFactory->create();

        return $dataObject->setData($data);
    }

    public function getShippingAddressFormData(array $formData): DataObject
    {
        // TODO: Implement getShippingAddressFormData() method.
    }
}
