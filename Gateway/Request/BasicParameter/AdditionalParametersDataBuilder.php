<?php

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

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
     * @param string $action
     * @param array  $additionalParameters
     */
    public function __construct(
        string $action,
        array $additionalParameters = []
    ) {
        $this->action = $action;
        $this->additionalParameters = $additionalParameters;
    }

    /**
     * Set service action
     *
     * @param  array   $buildSubject
     * @return array[]
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function build(array $buildSubject): array
    {
        return [
            'additionalParameters' => $this->getAdditionalParameters($buildSubject)
        ];
    }

    /**
     * Get additional parameters
     *
     * @param  array $buildSubject
     * @return array
     */
    private function getAdditionalParameters(array $buildSubject): array
    {
        $parameterLine = [];

        $action = $buildSubject['action'] ?? $this->getAction() ?? '';
        $parameterLine['service_action_from_magento'] = strtolower($action);

        $parameterLine['initiated_by_magento'] = 1;

        if ($additionalParameters = $this->getAllAdditionalParameters()) {
            foreach ($additionalParameters as $key => $value) {
                $parameterLine[$key] = $value;
            }
        }

        return $parameterLine;
    }

    /**
     * Get service action
     *
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Set service action
     *
     * @param  string $action
     * @return $this
     */
    public function setAction(string $action): AdditionalParametersDataBuilder
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Get all additional parameters
     *
     * @return array
     */
    public function getAllAdditionalParameters(): array
    {
        return $this->additionalParameters;
    }

    /**
     * Set additional parameter with key
     *
     * @param  string $key
     * @param  string $value
     * @return $this
     */
    public function setAdditionalParameter(string $key, string $value): AdditionalParametersDataBuilder
    {
        $this->additionalParameters[$key] = $value;

        return $this;
    }

    /**
     * Get additional parameter by key
     *
     * @param  string      $key
     * @return string|null
     */
    public function getAdditionalParameter(string $key): ?string
    {
        return $this->additionalParameters[$key] ?? null;
    }
}
