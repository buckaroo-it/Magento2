<?php

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Magento\Payment\Gateway\Request\BuilderInterface;

class AdditionalParametersDataBuilder implements BuilderInterface
{
    /**
     * @var string
     */
    private string $action;

    /**
     * @var array
     */
    private array $additionalParameters;

    /**
     * Constructor
     *
     * @param string $action
     * @param array $additionalParameters
     */
    public function __construct(
        string $action,
        array $additionalParameters = []
    ) {
        $this->action = $action;
        $this->additionalParameters = $additionalParameters;
    }

    /**
     * @param array $buildSubject
     * @return array[]
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function build(array $buildSubject)
    {
        return [
            'additionalParameters' => $this->getAdditionalParameters()
        ];
    }

    /**
     * @return array
     */
    private function getAdditionalParameters()
    {
        $parameterLine = [];
        if (!empty($this->getAction())) {
            $parameterLine['service_action_from_magento'] = strtolower($this->getAction());
        }

        $parameterLine['initiated_by_magento'] =  1;

        if ($additionalParameters = $this->getAllAdditionalParameters()) {
            foreach ($additionalParameters as $key => $value) {
                $parameterLine[$key] = $value;
            }
        }

        return $parameterLine;
    }

    public function setAdditionalParameter($key, $value)
    {
        $this->additionalParameters[$key] = $value;

        return $this;
    }

    public function getAdditionalParameter($key)
    {
        return $this->additionalParameters[$key];
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @inheritdoc
     */
    public function setAction($action)
    {
        $this->action = $action;

        return $this;
    }

    public function getAllAdditionalParameters(): array
    {
        return $this->additionalParameters;
    }
}
