<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace PrestaShop\Module\PrestashopCheckout\Presenter\Store\Modules;

use Monolog\Logger;
use PrestaShop\Module\PrestashopCheckout\ExpressCheckout\ExpressCheckoutConfiguration;
use PrestaShop\Module\PrestashopCheckout\FundingSource\FundingSourceProvider;
use PrestaShop\Module\PrestashopCheckout\Logger\LoggerFactory;
use PrestaShop\Module\PrestashopCheckout\PayPal\PayPalConfiguration;
use PrestaShop\Module\PrestashopCheckout\PayPal\PayPalPayIn4XConfiguration;
use PrestaShop\Module\PrestashopCheckout\Presenter\PresenterInterface;
use Ps_checkout;

/**
 * Construct the configuration module
 */
class ConfigurationModule implements PresenterInterface
{
    /**
     * @var PayPalPayIn4XConfiguration
     */
    private $payIn4XConfiguration;

    /**
     * @var ExpressCheckoutConfiguration
     */
    private $ecConfiguration;

    /**
     * @var PayPalConfiguration
     */
    private $paypalConfiguration;

    /**
     * @var FundingSourceProvider
     */
    private $fundingSourceProvider;
    /**
     * @var Ps_checkout
     */
    private $module;

    /**
     * @param PayPalPayIn4XConfiguration $payIn4XConfiguration
     * @param ExpressCheckoutConfiguration $ecConfiguration
     * @param PayPalConfiguration $paypalConfiguration
     * @param FundingSourceProvider $fundingSourceProvider
     */
    public function __construct(
        PayPalPayIn4XConfiguration $payIn4XConfiguration,
        ExpressCheckoutConfiguration $ecConfiguration,
        PayPalConfiguration $paypalConfiguration,
        FundingSourceProvider $fundingSourceProvider,
        Ps_checkout $module
    ) {
        $this->payIn4XConfiguration = $payIn4XConfiguration;
        $this->ecConfiguration = $ecConfiguration;
        $this->paypalConfiguration = $paypalConfiguration;
        $this->fundingSourceProvider = $fundingSourceProvider;
        $this->module = $module;
    }

    /**
     * Present the paypal module (vuex)
     *
     * @return array
     */
    public function present()
    {
        return [
            'config' => [
                'paymentMethods' => $this->getPaymentMethods(),
                'captureMode' => $this->paypalConfiguration->getIntent(),
                'paymentMode' => $this->paypalConfiguration->getPaymentMode(),
                'isFundingSourceCardEnabled' => $this->isFundingSourceCardEnabled(),
                'cardIsEnabled' => $this->paypalConfiguration->isCardPaymentEnabled(),
                'cardInlinePaypalIsEnabled' => $this->paypalConfiguration->isCardInlinePaypalIsEnabled(),
                'logger' => [
                    'levels' => [
                        Logger::DEBUG => 'DEBUG : Detailed debug information',
                        // Logger::INFO => 'INFO : Interesting events',
                        // Logger::NOTICE => 'NOTICE : Normal but significant events',
                        // Logger::WARNING => 'WARNING : Exceptional occurrences that are not errors',
                        Logger::ERROR => 'ERROR : Runtime errors that do not require immediate action',
                        // Logger::CRITICAL => 'CRITICAL : Critical conditions',
                        // Logger::ALERT => 'ALERT : Action must be taken immediately',
                        // Logger::EMERGENCY => 'EMERGENCY : system is unusable',
                    ],
                    'httpFormats' => [
                        'CLF' => 'Apache Common Log Format',
                        'DEBUG' => 'Debug format',
                        'SHORT' => 'Short format',
                    ],
                    'level' => (int) \Configuration::getGlobalValue(LoggerFactory::PS_CHECKOUT_LOGGER_LEVEL),
                    'maxFiles' => (int) \Configuration::getGlobalValue(LoggerFactory::PS_CHECKOUT_LOGGER_MAX_FILES),
                    'http' => (int) \Configuration::getGlobalValue(LoggerFactory::PS_CHECKOUT_LOGGER_HTTP),
                    'httpFormat' => \Configuration::getGlobalValue(LoggerFactory::PS_CHECKOUT_LOGGER_HTTP_FORMAT),
                ],
                'expressCheckout' => [
                    'orderPage' => (bool) $this->ecConfiguration->isOrderPageEnabled(),
                    'checkoutPage' => (bool) $this->ecConfiguration->isCheckoutPageEnabled(),
                    'productPage' => (bool) $this->ecConfiguration->isProductPageEnabled(),
                ],
                'payIn4X' => [
                    'activeForMerchant' => (bool) $this->payIn4XConfiguration->isActiveForMerchant(),
                    'orderPage' => (bool) $this->payIn4XConfiguration->isOrderPageActive(),
                    'productPage' => (bool) $this->payIn4XConfiguration->isProductPageActive(),
                ],
                'paypalButton' => $this->paypalConfiguration->getButtonConfiguration(),
                'nonDecimalCurrencies' => $this->checkNonDecimalCurrencies(),
            ],
        ];
    }

    /**
     * Get payment methods order
     *
     * @return array payment method
     */
    private function getPaymentMethods()
    {
        return $this->fundingSourceProvider->getAll(true);
    }

    /**
     * Is funding source card enabled
     *
     * @return bool
     */
    private function isFundingSourceCardEnabled()
    {
        foreach ($this->fundingSourceProvider->getAll(true) as $fundingSource) {
            if ('card' === $fundingSource->name) {
                return $fundingSource->isEnabled;
            }
        }

        return false;
    }

    /**
     * Checks if any currencies are enabled for which PayPal doesn't support decimal values
     * Returns error message with listed currencies that have to be configured correctly
     *
     * @return array
     */
    private function checkNonDecimalCurrencies()
    {
        $nonDecimalCurrencies = ['HUF', 'JPY', 'TWD'];

        $currencies = \Currency::getCurrencies(false, true);

        $misConfiguredCurrencies = [];

        foreach ($currencies as $currency) {
            if (in_array($currency['iso_code'], $nonDecimalCurrencies) && $this->checkCurrencyPrecision($currency)) {
                $misConfiguredCurrencies[] = $currency['iso_code'];
            }
        }

        return [
            'showError' => !empty($misConfiguredCurrencies),
            'errorMessage' => sprintf(
                $this->module->l('Attention: you have activated %s currencies, you need to configure those currencies to use 0 decimals as PayPal does not support decimals for those currencies', 'configurationmodule'),
                implode(', ', $misConfiguredCurrencies)
            ),
        ];
    }

    /**
     * @param array $currency
     *
     * @return bool
     */
    private function checkCurrencyPrecision($currency)
    {
        if (isset($currency['precision'])) {
            return (int) $currency['precision'] !== 0;
        }

        return (int) $currency['decimals'] !== 0;
    }
}
