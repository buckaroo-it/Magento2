<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

class AdditionalParametersDataBuilder implements BuilderInterface
{
    /**
     * @var string
     */
    private $action;

    /**
     * @var array
     */
    private $additionalParameters;

    /**
     * Constructor
     *
     * @param $action
     * @param $additionalParameters
     */
    public function __construct(
        $action,
        $additionalParameters
    ) {
        $this->action = $action;
        $this->additionalParameters = $additionalParameters;
    }


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
            $parameterLine[] = $this->getParameterLine(
                'service_action_from_magento',
                strtolower($this->getAction())
            );
        }

        $parameterLine[] = $this->getParameterLine('initiated_by_magento', 1);

        if ($additionalParameters = $this->getAllAdditionalParameters()) {
            foreach ($additionalParameters as $key => $value) {
                $parameterLine[] = $this->getParameterLine($key, $value);
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
     * @param $name
     * @param $value
     *
     * @return array
     */
    private function getParameterLine($name, $value)
    {
        return [
            $name => $value,
        ];
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * {@inheritdoc}
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
