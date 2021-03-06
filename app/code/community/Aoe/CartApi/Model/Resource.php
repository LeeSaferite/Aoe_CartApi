<?php

abstract class Aoe_CartApi_Model_Resource extends Mage_Api2_Model_Resource
{
    /**
     * Hash of external/internal attribute codes
     *
     * @var string[]
     */
    protected $attributeMap = [];

    /**
     * Hash of external attribute codes and their data type
     *
     * @var string[]
     */
    protected $attributeTypeMap = [];

    /**
     * Array of external attribute codes that are manually generated
     *
     * @var string[]
     */
    protected $manualAttributes = [];

    /**
     * Hash of default embed codes
     *
     * @var string[]
     */
    protected $defaultEmbeds = [];

    public function dispatch()
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }

    /**
     * @inheritdoc
     */
    public function getFilter()
    {
        $this->_filter = null;

        return parent::getFilter();
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    protected function loadQuote()
    {
        return $this->getHelper()->loadQuote();
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    protected function saveQuote()
    {
        return $this->getHelper()->saveQuote();
    }

    /**
     * @return Aoe_CartApi_Helper_Data
     */
    protected function getHelper()
    {
        return Mage::helper('Aoe_CartApi');
    }

    /**
     * Read in the attributes from a resource
     *
     * @param Varien_Object $resource
     * @param string[]      $attributeCodes
     * @param mixed[]       $data
     *
     * @return mixed[]
     */
    protected function loadResourceAttributes(Varien_Object $resource, array $attributeCodes, array $data = [])
    {
        $attributeCodes = array_diff($attributeCodes, $this->manualAttributes);
        $attributeCodes = array_combine($attributeCodes, $attributeCodes);
        $attributeCodes = array_merge($attributeCodes, array_intersect_key($this->attributeMap, $attributeCodes));
        foreach ($attributeCodes as $externalKey => $internalKey) {
            $data[$externalKey] = $resource->getDataUsingMethod($internalKey);
        }

        return $data;
    }

    /**
     * Update a resource
     *
     * @param Varien_Object $resource
     * @param string[]      $attributeCodes
     * @param mixed[]       $data
     *
     * @return $this
     */
    protected function saveResourceAttributes(Varien_Object $resource, array $attributeCodes, array $data)
    {
        $attributeCodes = array_diff($attributeCodes, $this->manualAttributes);
        $attributeCodes = array_combine($attributeCodes, $attributeCodes);
        $attributeCodes = array_merge($attributeCodes, array_intersect_key($this->attributeMap, $attributeCodes));
        foreach (array_intersect_key($data, $attributeCodes) as $externalKey => $value) {
            $resource->setDataUsingMethod($attributeCodes[$externalKey], $value);
        }

        return $this;
    }

    /**
     * Remap attribute keys
     *
     * @param array $data
     *
     * @return array
     */
    protected function mapAttributes(array &$data)
    {
        return $this->getHelper()->mapAttributes($this->attributeMap, $data);
    }

    /**
     * Reverse remap the attribute keys
     *
     * @param array $data
     *
     * @return array
     */
    protected function unmapAttributes(array &$data)
    {
        return $this->getHelper()->unmapAttributes($this->attributeMap, $data);
    }

    /**
     * Type cast array values
     *
     * @param array $data
     * @param array $typeMap
     *
     * @return array
     *
     * @throws Zend_Currency_Exception
     * @throws Zend_Locale_Exception
     */
    protected function fixTypes(array $data, array $typeMap = [])
    {
        // This makes me a bit nervous
        $currencyCode = $this->loadQuote()->getQuoteCurrencyCode();
        if (empty($currencyCode)) {
            $currencyCode = $this->loadQuote()->getStore()->getDefaultCurrencyCode();
        }

        if (empty($typeMap)) {
            $typeMap = $this->attributeTypeMap;
        }

        foreach ($typeMap as $code => $type) {
            if (array_key_exists($code, $data) && (is_scalar($data[$code]) || is_null($data[$code]))) {
                switch ($type) {
                    case 'bool':
                        $data[$code] = (!empty($data[$code]) && strtolower($data[$code]) !== 'false');
                        break;
                    case 'int':
                        $data[$code] = intval($data[$code]);
                        break;
                    case 'float':
                        $data[$code] = floatval($data[$code]);
                        break;
                    case 'currency':
                        $amount = floatval($data[$code]);
                        $precision = Zend_Locale_Data::getContent(null, 'currencyfraction', $currencyCode);
                        if ($precision === false) {
                            $precision = Zend_Locale_Data::getContent(null, 'currencyfraction');
                        }
                        if ($precision !== false) {
                            $amount = round($amount, $precision);
                            $formatted = Mage::app()->getLocale()->currency($currencyCode)->toCurrency($amount, ['precision' => $precision]);
                        } else {
                            $formatted = Mage::app()->getLocale()->currency($currencyCode)->toCurrency($amount);
                        }
                        $data[$code] = ['currency' => $currencyCode, 'amount' => $amount, 'formatted' => $formatted];
                        break;
                    case 'string':
                    default:
                        $data[$code] = (string)$data[$code];
                        break;
                }
            }
        }

        return $data;
    }

    /**
     * @param false|null|string|string[] $embeds
     *
     * @return string[]
     */
    protected function parseEmbeds($embeds)
    {
        if ($embeds === false || $embeds === '') {
            return [];
        } elseif ($embeds === null) {
            return $this->defaultEmbeds;
        }

        if (is_string($embeds)) {
            $embeds = explode(',', $embeds);
        }

        if (is_array($embeds)) {
            $embeds = array_filter(array_map('trim', $embeds));
        } else {
            $embeds = [];
        }

        return $embeds;
    }

    // @codingStandardsIgnoreStart
    protected function __()
    {
        // @codingStandardsIgnoreEnd
        return call_user_func_array([$this->getHelper(), '__'], func_get_args());
    }
}
