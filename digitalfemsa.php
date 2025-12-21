<?php
/**
 * NOTICE OF LICENSE
 * Title   : DigitalFemsa Cash Payment Gateway for Prestashop
 * Author  : DigitalFemsa.io
 * URL     : https://digital-femsa.readme.io/docs/prestashop-1.
 * PHP Version 7.0.0
 * DigitalFemsa File Doc Comment
 *
 * @author    DigitalFemsa <monitoreo.b2b@digitalfemsa.com>
 * @copyright 2024 DigitalFemsa
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @category  DigitalFemsa
 *
 * @version   GIT: @2.3.7@
 *
 * @see       https://digitalfemsa.io/
 */
require __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/model/DigitalFemsaConfig.php';

require_once __DIR__ . '/model/DigitalFemsaDatabase.php';

 


require_once __DIR__ . '/src/UseCases/CreateWebhook.php';

require_once __DIR__ . '/src/UseCases/ValidateAdminForm.php';

use DigitalFemsa\Payments\UseCases\CreateWebhook;
use DigitalFemsa\Payments\UseCases\ValidateAdminForm;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use DigitalFemsa\Configuration as FemsaConfig;
use DigitalFemsa\Api\OrdersApi;
use DigitalFemsa\Api\CustomersApi;

if (!defined('_PS_VERSION_')) {
    exit(403);
}

define('DIGITAL_FEMSA_METADATA_LIMIT', 12);

/**
 *
 * @category Class
 *
 * @author   DigitalFemsa <monitoreo.b2b@digitalfemsa.com>
 * @license  http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @see      https://digitalfemsa.io/
 */
class digitalfemsa extends PaymentModule
{
    public const DigitalFemsaSettings = [
        'DIGITAL_FEMSA_PRESTASHOP_VERSION',
        'DIGITAL_FEMSA_MODE',
        'DIGITAL_FEMSA_PUBLIC_KEY_TEST',
        'DIGITAL_FEMSA_PUBLIC_KEY_LIVE',
        'DIGITAL_FEMSA_PRIVATE_KEY_TEST',
        'DIGITAL_FEMSA_PRIVATE_KEY_LIVE',
        'DIGITAL_FEMSA_WEBHOOK',
        'DIGITAL_FEMSA_WEBHOOK_FAILED_URL',
        'DIGITAL_FEMSA_WEBHOOK_ERROR_MESSAGE',
        'DIGITAL_FEMSA_WEBHOOK_FAILED_ATTEMPTS',
        'DIGITAL_FEMSA_METHOD_CASH',
        'DIGITAL_FEMSA_EXPIRATION_DATE_LIMIT',
        'DIGITAL_FEMSA_EXPIRATION_DATE_TYPE',
        'DIGITAL_FEMSA_ORDER_METADATA',
        'DIGITAL_FEMSA_PRODUCT_METADATA',
    ];

    public const CartProductAttribute = [
        'id_product_attribute',
        'id_product',
        'cart_quantity',
        'id_shop',
        'id_customization',
        'name',
        'is_virtual',
        'description_short',
        'available_now',
        'available_later',
        'id_category_default',
        'id_supplier',
        'id_manufacturer',
        'manufacturer_name',
        'on_sale',
        'ecotax',
        'additional_shipping_cost',
        'available_for_order',
        'show_price',
        'price',
        'active',
        'unity',
        'unit_price_ratio',
        'quantity_available',
        'width',
        'height',
        'depth',
        'out_of_stock',
        'weight',
        'available_date',
        'date_add',
        'date_upd',
        'quantity',
        'link_rewrite',
        'category',
        'unique_id',
        'id_address_delivery',
        'advanced_stock_management',
        'supplier_reference',
        'customization_quantity',
        'price_attribute',
        'ecotax_attr',
        'reference',
        'weight_attribute',
        'ean13',
        'isbn',
        'upc',
        'minimal_quantity',
        'wholesale_price',
        'id_image',
        'legend',
        'reduction_type',
        'is_gift',
        'reduction',
        'reduction_without_tax',
        'price_without_reduction',
        'attributes',
        'attributes_small',
        'rate',
        'tax_name',
        'stock_quantity',
        'price_without_reduction_without_tax',
        'price_with_reduction',
        'price_with_reduction_without_tax',
        'total',
        'total_wt',
        'price_wt',
        'reduction_applies',
        'quantity_discount_applies',
        'allow_oosp',
    ];

    protected $html = '';

    public $settings;

    private $orderElements;

    private $productElements;

    public $extra_mail_vars;

    /**
     * @var string
     */
    private $digitalFemsaMode;

    /**
     * Implement the configuration of the DigitalFemsa Prestashop module
     */
    public function __construct()
    {
        $this->name = 'digitalfemsa';
        $this->tab = 'payments_gateways';
        $this->version = '1.1.0';
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_,
        ];
        $this->author = 'DigitalFemsa';
        $this->displayName = $this->l('OXXO PAY powered by Spin');
        $this->description = $this->l('Vende en línea y recibe pagos en más de 25,000 tiendas OXXO de México.');
        $this->controllers = ['validation', 'notification'];
        $this->is_eu_compatible = 1;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->cash = true;
        $this->amount_min = 2000;

        $this->settings = array_keys(self::DigitalFemsaSettings);

        $this->orderElements = array_keys(
            array_diff_key(get_class_vars('Cart'), ['definition' => '', 'htmlFields' => ''])
        );
        sort($this->orderElements);
        array_walk($this->orderElements, function ($element) {
            $this->settings[] = sprintf('ORDER_%s', Tools::strtoupper($element));
        });

        $this->productElements = self::CartProductAttribute;
        sort($this->productElements);
        array_walk($this->productElements, function ($element) {
            $this->settings[] = sprintf('PRODUCT_%s', Tools::strtoupper($element));
        });

        $config = Configuration::getMultiple($this->settings);
        $this->setConfiguration($config);

        $this->bootstrap = true;
        parent::__construct();

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    /**
     * Get configured DigitalFemsa SDK instance
     * Centralizes all SDK configuration to ensure consistency across all API calls
     *
     * @return \DigitalFemsa\Configuration
     */
    private function getDigitalFemsaConfig(): \DigitalFemsa\Configuration
    {
        $femsaCfg = FemsaConfig::getDefaultConfiguration();
        
        // Set API key based on mode
        $key = Configuration::get('DIGITAL_FEMSA_MODE') ?
            Configuration::get('DIGITAL_FEMSA_PRIVATE_KEY_LIVE') :
            Configuration::get('DIGITAL_FEMSA_PRIVATE_KEY_TEST');
        $femsaCfg->setAccessToken($key);
        
        // Set debug logging
        $femsaCfg->setDebug(true);
        $femsaCfg->setDebugFile(_PS_MODULE_DIR_ . $this->name . '/femsa_sdk.log');
        
        // Set correct API host based on mode
        $isLive = (bool) Configuration::get('DIGITAL_FEMSA_MODE');
        $host = $isLive ? 
            'https://api.digitalfemsa.io' : 
            'https://api.stg.digitalfemsa.io';
        $femsaCfg->setHost($host);

        return $femsaCfg;
    }

    /**
     * @param array $config
     *
     * @return void
     */
    private function setConfiguration(array $config)
    {
        $mappedConfig = $this->mappedConfig();

        foreach ($config as $key => $value) {
            if (!isset($mappedConfig[$key])) {
                continue;
            }
            $attr = $mappedConfig[$key];
            $this->$attr = $value;

            if ($key == 'DIGITAL_FEMSA_MODE') {
                $this->digitalFemsaMode = ($value) ? 'live' : 'test';
            }
        }
    }

    /**
     * @return string[]
     */
    private function mappedConfig()
    {
        return [
            'DIGITAL_FEMSA_MODE' => 'mode',
            'DIGITAL_FEMSA_WEBHOOK' => 'web_hook',
            'DIGITAL_FEMSA_METHOD_CASH' => 'payment_method_cash',
            'DIGITAL_FEMSA_EXPIRATION_DATE_TYPE' => 'expiration_date_type',
            'DIGITAL_FEMSA_EXPIRATION_DATE_LIMIT' => 'expiration_date_limit',
            'DIGITAL_FEMSA_PRIVATE_KEY_TEST' => 'test_private_key',
            'DIGITAL_FEMSA_PUBLIC_KEY_TEST' => 'test_public_key',
            'DIGITAL_FEMSA_PRIVATE_KEY_LIVE' => 'live_private_key',
            'DIGITAL_FEMSA_PUBLIC_KEY_LIVE' => 'live_public_key'
        ];
    }

    /**
     * Install configuration, create table in database and register hooks
     *
     * @return bool
     */
    public function install()
    {
        $updateConfig = [
            'PS_OS_CHEQUE' => 1,
            'PS_OS_PAYMENT' => 2,
            'PS_OS_PREPARATION' => 3,
            'PS_OS_SHIPPING' => 4,
            'PS_OS_DELIVERED' => 5,
            'PS_OS_CANCELED' => 6,
            'PS_OS_REFUND' => 7,
            'PS_OS_ERROR' => 8,
            'PS_OS_OUTOFSTOCK' => 9,
            'PS_OS_BANKWIRE' => 10,
            'PS_OS_PAYPAL' => 11,
            'PS_OS_WS_PAYMENT' => 12,
        ];
        $this->upgradeConfig();

        foreach ($updateConfig as $u => $v) {
            if (!Configuration::get($u) || (int) Configuration::get($u) < 1) {
                if (defined('_' . $u . '_') && (int) constant('_' . $u . '_') > 0) {
                    Configuration::updateValue($u, constant('_' . $u . '_'));
                } else {
                    Configuration::updateValue($u, $v);
                }
            }
        }

        if (!parent::install()
            || !$this->createPendingCashState()
            || !$this->registerHook('header')
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('adminOrder')
            || !$this->registerHook('updateOrderStatus')
            && Configuration::updateValue('DIGITAL_FEMSA_METHOD_CASH', 1)
            && Configuration::updateValue('DIGITAL_FEMSA_MODE', 0)
            || !DigitalFemsaDatabase::installDb()
            || !DigitalFemsaDatabase::createTableDigitalFemsaOrder()
            || !DigitalFemsaDatabase::createTableMetaData()
        ) {
            return false;
        }

        Configuration::updateValue('DIGITAL_FEMSA_PRESTASHOP_VERSION', $this->version);

        return true;
    }

    /**
     * Delete configuration and drop table in database.
     *
     * @return bool
     */
    public function uninstall()
    {
        $configDeleted = array_filter(array_map(function ($setting) {
            return Configuration::deleteByName($setting);
        }, self::DigitalFemsaSettings), function ($result) {
            return !$result;
        });

        $customStateId = Configuration::get('waiting_cash_payment'); // Reemplaza con el nombre de configuración correcto

        if ($customStateId) {
            $customState = new OrderState($customStateId);
            if (Validate::isLoadedObject($customState)) {
                $customState->delete();
            }
        }

        return parent::uninstall()
            && count($configDeleted) == 0
            && Db::getInstance()->Execute(
                'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'digital_femsa_transaction`'
            )
            && Db::getInstance()->Execute(
                'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'digital_femsa_metadata`'
            )
            && Db::getInstance()->Execute(
                'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'digital_femsa_order_checkout`'
            );
    }

    /**
     * Returns the order confirmation checkout
     *
     * @param array $params payment information parameter
     *
     * @return template
     */
    public function hookPaymentReturn($params)
    {
        if ($params['order'] && Validate::isLoadedObject($params['order'])) {
            $id_order = (int) $params['order']->id;
            $digital_femsa_tran_details = DigitalFemsaDatabase::getOrderById($id_order);

            if (!empty($digital_femsa_tran_details)) {
                $this->smarty->assign('cash', true);
                $this->smarty->assign(
                    'digital_femsa_order',
                    [
                        'barcode' => $digital_femsa_tran_details['reference'],
                        'type' => 'cash',
                        'barcode_url' => $digital_femsa_tran_details['barcode'],
                        'amount' => $digital_femsa_tran_details['amount'],
                        'currency' => $digital_femsa_tran_details['currency'],
                    ]
                );
            } else {
                $this->smarty->assign('cash', false);
            }
        }

        return $this->fetchTemplate('checkout-confirmation-all.tpl');
    }

    /**
     * The order is refunded
     *
     * @param array $params information of order to update it
     *
     * @return void
     */
    public function hookUpdateOrderStatus($params)
    {
        if ($params['newOrderStatus']->id == 7) {
            // order refunded - use centralized configuration
            $femsaCfg = $this->getDigitalFemsaConfig();

            $id_order = (int) $params['id_order'];
            $digital_femsa_tran_details = DigitalFemsaDatabase::getOrderById($id_order);

            // only credit card refund
            if (!$digital_femsa_tran_details['barcode']
                && !(isset($digital_femsa_tran_details['reference'])
                    && !empty($digital_femsa_tran_details['reference']))
            ) {
                $ordersApi = new OrdersApi(null, $femsaCfg);
                $refundAmount = (int) round(((float) $digital_femsa_tran_details['amount']) * 100);
                $ordersApi->orderRefund($digital_femsa_tran_details['id_digital_femsa_order'], [
                    'amount' => $refundAmount,
                    'reason' => 'requested_by_client',
                ]);
            }
        }
    }

    /**
     * Create pending chash state
     *
     * @return bool
     */
    private function createPendingCashState()
    {
        $state = new OrderState();
        $languages = Language::getLanguages();
        $names = [];

        foreach ($languages as $lang) {
            $names[$lang['id_lang']] = 'En espera de pago';
        }

        $state->name = $names;
        $state->color = '#4169E1';
        $state->send_email = true;
        $state->module_name = 'digitalfemsa';
        $templ = [];

        foreach ($languages as $lang) {
            $templ[$lang['id_lang']] = 'digital_femsa_efectivo';
        }

        $state->template = $templ;

        if ($state->save()) {
            Configuration::updateValue('waiting_cash_payment', $state->id);
            $directory = _PS_MAIL_DIR_;

            if ($dhvalue = opendir($directory)) {
                while (($file = readdir($dhvalue)) !== false) {
                    if (is_dir($directory . $file) && $file[0] != '.') {
                        $new_html_file = _PS_MODULE_DIR_ . $this->name . '/mails/' . $file . '/digital_femsa_efectivo.html';
                        $new_txt_file = _PS_MODULE_DIR_ . $this->name . '/mails/' . $file . '/digital_femsa_efectivo.txt';

                        $html_folder = $directory . $file . '/digital_femsa_efectivo.html';
                        $txt_folder = $directory . $file . '/digital_femsa_efectivo.txt';

                        try {
                            Tools::copy($new_html_file, $html_folder);
                            Tools::copy($new_txt_file, $txt_folder);
                        } catch (\Exception $e) {
                            $error_copy = $e->getMessage();

                            if (class_exists('Logger')) {
                                Logger::addLog(json_encode($error_copy), 1, null, null, null, true);
                            }
                        }
                    }
                }
                closedir($dhvalue);
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * Generate method payment and checkout
     *
     * @return template
     */
    public function hookHeader()
    {
        // Use centralized configuration
        $femsaCfg = $this->getDigitalFemsaConfig();
        
        // Expose correct Checkout JS host to template based on mode
        $isLive = (bool) Configuration::get('DIGITAL_FEMSA_MODE');
        $this->smarty->assign('df_pay_js', $isLive ? 
            'https://pay.digitalfemsa.io/v1.0/js/digitalfemsa-checkout.min.js' : 
            'https://pay.stg.digitalfemsa.io/v1.0/js/digitalfemsa-checkout.min.js'
        );
        
        if (Tools::getValue('controller') != 'order-opc'
            && (!($_SERVER['PHP_SELF'] == __PS_BASE_URI__ . 'order.php'
                || $_SERVER['PHP_SELF'] == __PS_BASE_URI__ . 'order-opc.php'
                || Tools::getValue('controller') == 'order'
                || Tools::getValue('controller') == 'orderopc'
                || Tools::getValue('step') == 3))) {
            return;
        }

        Media::addJsDef(
            [
                'ajax_link' => $this->_path . 'ajax.php',
            ]
        );
        $this->context->controller->addCSS($this->_path . 'views/css/digital-femsa-prestashop.css');
        $this->context->controller->addJS($this->_path . 'views/js/checkout-style.js');

        if (Configuration::get('DIGITAL_FEMSA_MODE')) {
            $this->smarty->assign('api_key', addslashes(Configuration::get('DIGITAL_FEMSA_PUBLIC_KEY_LIVE')));
        } else {
            $this->smarty->assign('api_key', addslashes(Configuration::get('DIGITAL_FEMSA_PUBLIC_KEY_TEST')));
        }

        $this->smarty->assign('path', $this->_path);

        $cart = $this->context->cart;
        $customer = $this->context->customer;
        $payment_options = ['cash'];

        $address_delivery = new Address((int) $cart->id_address_delivery);
        $state = State::getNameById($address_delivery->id_state);
        $country = Country::getIsoById($address_delivery->id_country);
        $carrier = new Carrier((int) $cart->id_carrier);
        $shp_price = $cart->getTotalShippingCost();
        $discounts = $cart->getCartRules();
        $items = $cart->getProducts();
        $shippingLines = null;

        if (!empty($carrier)) {
            if ($carrier->name != null) {
                $shp_carrier = $carrier->name;
                $shp_service = implode(',', $carrier->delay);
                $shippingLines = DigitalFemsaConfig::getShippingLines($shp_service, $shp_carrier, $shp_price);
            }
        }

        $shippingContact = DigitalFemsaConfig::getShippingContact($customer, $address_delivery, $state, $country);
        $customerInfo = DigitalFemsaConfig::getCustomerInfo($customer, $address_delivery);

        $result = DigitalFemsaDatabase::getDigitalFemsaMetadata($customer->id, $this->digitalFemsaMode, 'digital_femsa_customer_id');

        if (count($payment_options) > 0
            && !empty($shippingContact['address']['postal_code'])
            && !empty($shippingLines)) {
            $order_details = [];
            $taxlines = [];

            if (empty($result['meta_value'])) {
                $customer_id = $this->createCustomer($customer, $customerInfo);
            } else {
                $customer_id = $result['meta_value'];
            }

            $taxlines = DigitalFemsaConfig::getTaxLines($items);

            $checkout = [
                'type' => 'HostedPayment',
                'allowed_payment_methods' => $payment_options,
                'failure_url' => Configuration::get('DIGITAL_FEMSA_WEBHOOK'),
                'success_url' => Configuration::get('DIGITAL_FEMSA_WEBHOOK'),
            ];

            if (in_array('cash', $payment_options)) {
                $expirationDateLimit = Configuration::get('DIGITAL_FEMSA_EXPIRATION_DATE_LIMIT');
                $expirationDateType = Configuration::get('DIGITAL_FEMSA_EXPIRATION_DATE_TYPE') == 0 ? 86400 : 3600;
                $checkout['expires_at'] = time() + ($expirationDateLimit * $expirationDateType);
            }

            $order_details = [
                'currency' => $this->context->currency->iso_code,
                'line_items' => DigitalFemsaConfig::getLineItems($items),
                'customer_info' => ['customer_id' => $customer_id],
                'discount_lines' => DigitalFemsaConfig::getDiscountLines($discounts),
                'shipping_lines' => [],
                'shipping_contact' => $shippingContact,
                'tax_lines' => [],
                'metadata' => [
                    'plugin' => 'Prestashop',
                    'plugin_version' => _PS_VERSION_,
                    'reference_id' => $this->context->cart->id,
                ],
                'checkout' => $checkout,
            ];

            $order_elements = array_keys(get_class_vars('Cart'));

            foreach ($order_elements as $element) {
                $elementKey = sprintf('ORDER_%s', Tools::strtoupper($element));

                if (!empty(Configuration::get($elementKey)) && property_exists($this->context->cart, $element)) {
                    $order_details['metadata'][$element] = $this->context->cart->$element;
                }
            }

            foreach ($items as $item) {
                $index = 'product-' . $item['id_product'];
                $order_details['metadata'][$index] = '';

                foreach ($this->productElements as $element) {
                    $elementKey = sprintf('PRODUCT_%s', Tools::strtoupper($element));

                    if (!empty(Configuration::get($elementKey)) && array_key_exists($element, $item)) {
                        $order_details['metadata'][$index] .= $this->buildRecursiveMetadata($item[$element], $element);
                    }
                }
                $order_details['metadata'][$index] = Tools::substr($order_details['metadata'][$index], 0, -2);
            }

            $amount = 0;

            if (!empty($shippingLines)) {
                foreach ($shippingLines as $shipping) {
                    $order_details['shipping_lines'][] = [
                        'amount' => $shipping['amount'],
                        'tracking_number' => $this->removeSpecialCharacter($shipping['tracking_number']),
                        'carrier' => $this->removeSpecialCharacter($shipping['carrier']),
                        'method' => $this->removeSpecialCharacter($shipping['method']),
                    ];
                    $amount = $amount + $shipping['amount'];
                }
            }

            if (isset($taxlines)) {
                foreach ($taxlines as $tax) {
                    $order_details['tax_lines'][] = [
                        'description' => $this->removeSpecialCharacter($tax['description']),
                        'amount' => $tax['amount'],
                    ];
                    $amount = $amount + $tax['amount'];
                }
            }

            foreach ($order_details['line_items'] as $item) {
                $amount = $amount + ($item['quantity'] * $item['unit_price']);
            }

            if (isset($order_details['discount_lines'])) {
                foreach ($order_details['discount_lines'] as $discount) {
                    $amount = $amount - $discount['amount'];
                }
            }

            $result = DigitalFemsaDatabase::getDigitalFemsaOrder($customer->id, $this->digitalFemsaMode, $this->context->cart->id);
            $ordersApi = new OrdersApi(null, $femsaCfg);

            try {
                if ($order_details['currency'] == 'MXN' && $amount < $this->amount_min) {
                    $message = 'El monto minimo de compra con DigitalFemsa tiene que ser mayor a $20.00 ';
                    $this->context->smarty->assign(
                        [
                            'message' => $message,
                        ]
                    );

                    return false;
                }

                if (isset($result) && $result != false && $result['status'] == 'unpaid') {
                    $order = $ordersApi->getOrderById($result['id_digital_femsa_order']);
                    $charges = $order && $order->getCharges() ? $order->getCharges()->getData() : [];
                    if (!empty($charges) && isset($charges[0]) && $charges[0]->getStatus() === 'paid') {
                        DigitalFemsaDatabase::updateDigitalFemsaOrder(
                            $customer->id,
                            $this->context->cart->id,
                            $this->digitalFemsaMode,
                            $order->getId(),
                            $charges[0]->getStatus()
                        );
                    }
                }

                if (empty($order)) {
                    if (class_exists('Logger')) {
                        Logger::addLog(
                            'DigitalFemsa createOrder request body=' . json_encode($order_details),
                            1,
                            null,
                            'Cart',
                            (int) $this->context->cart->id,
                            true
                        );
                    }
                    $order = $ordersApi->createOrder($order_details);
                    if (class_exists('Logger')) {
                        $createdOrderId = (isset($order) && method_exists($order, 'getId')) ? $order->getId() : (isset($order->id) ? $order->id : '');
                        Logger::addLog(
                            'DigitalFemsa createOrder success id=' . $createdOrderId,
                            1,
                            null,
                            'Cart',
                            (int) $this->context->cart->id,
                            true
                        );
                        Logger::addLog(
                            'DigitalFemsa createOrder response body=' . json_encode($order),
                            1,
                            null,
                            'Cart',
                            (int) $this->context->cart->id,
                            true
                        );
                    }
                    DigitalFemsaDatabase::updateDigitalFemsaOrder(
                        $customer->id,
                        $this->context->cart->id,
                        $this->digitalFemsaMode,
                        $order->getId(),
                        'unpaid'
                    );
                } else {
                    $charges = $order && method_exists($order, 'getCharges') && $order->getCharges() ? $order->getCharges()->getData() : [];
                    if (empty($charges) || $charges[0]->getStatus() !== 'paid') {
                        unset($order_details['customer_info']);
                        if (class_exists('Logger')) {
                            Logger::addLog(
                                'DigitalFemsa updateOrder request id=' . (method_exists($order, 'getId') ? $order->getId() : '') . ' body=' . json_encode($order_details),
                                1,
                                null,
                                'Cart',
                                (int) $this->context->cart->id,
                                true
                            );
                        }
                        $updatedOrder = $ordersApi->updateOrder($order->getId(), $order_details);
                        if (class_exists('Logger')) {
                            Logger::addLog(
                                'DigitalFemsa updateOrder success id=' . (method_exists($order, 'getId') ? $order->getId() : ''),
                                1,
                                null,
                                'Cart',
                                (int) $this->context->cart->id,
                                true
                            );
                            if (isset($updatedOrder)) {
                                Logger::addLog(
                                    'DigitalFemsa updateOrder response body=' . json_encode($updatedOrder),
                                    1,
                                    null,
                                    'Cart',
                                    (int) $this->context->cart->id,
                                    true
                                );
                            }
                        }
                    } else {
                        if (class_exists('Logger')) {
                            Logger::addLog(
                                'DigitalFemsa createOrder (recreate) request body=' . json_encode($order_details),
                                1,
                                null,
                                'Cart',
                                (int) $this->context->cart->id,
                                true
                            );
                        }
                        $order = $ordersApi->createOrder($order_details);
                        if (class_exists('Logger')) {
                            $recreatedOrderId = (isset($order) && method_exists($order, 'getId')) ? $order->getId() : (isset($order->id) ? $order->id : '');
                            Logger::addLog(
                                'DigitalFemsa recreateOrder success id=' . $recreatedOrderId,
                                1,
                                null,
                                'Cart',
                                (int) $this->context->cart->id,
                                true
                            );
                            Logger::addLog(
                                'DigitalFemsa createOrder (recreate) response body=' . json_encode($order),
                                1,
                                null,
                                'Cart',
                                (int) $this->context->cart->id,
                                true
                            );
                        }
                        DigitalFemsaDatabase::updateDigitalFemsaOrder(
                            $customer->id,
                            $this->context->cart->id,
                            $this->digitalFemsaMode,
                            $order->getId(),
                            'unpaid'
                        );
                    }
                }
            } catch (\Exception $e) {
                $log_message = $e->getMessage();

                if (class_exists('Logger')) {
                    Logger::addLog(
                        $this->l('Payment transaction failed') . ' ' . $log_message,
                        2,
                        null,
                        'Cart',
                        (int) $this->context->cart->id,
                        true
                    );
                }

                $recovered = false;
                $code = method_exists($e, 'getCode') ? (int) $e->getCode() : 0;
                if ($code === 422 && (stripos($log_message, 'customer_id') !== false || stripos($log_message, 'customer_info') !== false)) {
                    $newCustomerId = $this->createCustomer($customer, $customerInfo);
                    if (!empty($newCustomerId)) {
                        $order_details['customer_info'] = ['customer_id' => $newCustomerId];
                        try {
                            if (class_exists('Logger')) {
                                Logger::addLog(
                                    'DigitalFemsa retry createOrder request body=' . json_encode($order_details),
                                    1,
                                    null,
                                    'Cart',
                                    (int) $this->context->cart->id,
                                    true
                                );
                            }
                            $order = $ordersApi->createOrder($order_details);
                            DigitalFemsaDatabase::updateDigitalFemsaOrder(
                                $customer->id,
                                $this->context->cart->id,
                                $this->digitalFemsaMode,
                                $order->getId(),
                                'unpaid'
                            );
                            $recovered = true;
                        } catch (\Exception $ie) {
                        }
                    }
                }

                if (!$recovered) {
                    $message = $e->getMessage();
                    $this->context->smarty->assign('message', $message);
                }
            }
        }

        if (isset($order)) {
            $this->smarty->assign('orderID', method_exists($order, 'getId') ? $order->getId() : (isset($order->id) ? $order->id : ''));
            $checkout = method_exists($order, 'getCheckout') ? $order->getCheckout() : (isset($order->checkout) ? $order->checkout : null);
            $checkoutId = is_object($checkout) && method_exists($checkout, 'getId') ? $checkout->getId() : (is_array($checkout) && isset($checkout['id']) ? $checkout['id'] : '');
            $this->smarty->assign('checkoutRequestId', $checkoutId);
            $this->smarty->assign('amount', $amount);
        } else {
            $this->smarty->assign('checkoutRequestId', '');
            $this->smarty->assign('orderID', '');
            $this->smarty->assign('amount', '');
        }

        return $this->fetchTemplate('hook-header.tpl');
    }

    /**
     * Generates the metadata of the order attributes.
     *
     * @param array $data_object Object to generate metadata
     * @param string $key Key the data_object
     *
     * @return string
     */
    public function buildRecursiveMetadata($data_object, $key)
    {
        $string = '';

        if (gettype($data_object) == 'array') {
            foreach (array_keys($data_object) as $data_key) {
                $key_concat = (string) $key . '-' . (string) $data_key;

                if (empty($data_object[$data_key])) {
                    $string .= (string) $key_concat . ': NULL | ';
                } else {
                    $string .= $this->buildRecursiveMetadata($data_object[$data_key], $key_concat);
                }
            }
        } else {
            if (empty($data_object)) {
                $string .= (string) $key . ': NULL | ';
            } else {
                $string .= (string) $key . ': ' . (string) $data_object . ' | ';
            }
        }

        return $string;
    }

    /**
     * Returns the order information.
     *
     * @param array $params The order info
     *
     * @return template
     */
    public function hookAdminOrder($params)
    {
        $id_order = (int) $params['id_order'];
        $status = $this->getTransactionStatus($id_order);

        return $status;
    }

    /**
     * The different payment methods are added.
     *
     * @param array $params Payment options
     *
     * @return array
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $this->smarty->assign(
            [
                'test_private_key' => Configuration::get('DIGITAL_FEMSA_PRIVATE_KEY_TEST'),
            ]
        );
        $payment_options = [
            $this->getDigitalFemsaPaymentOption()
        ];

        return $payment_options;
    }

    /**
     * Remove special character
     *
     * @param string $param character string
     *
     * @return string
     */
    public function removeSpecialCharacter($param)
    {
        $param = str_replace(['#', '-', '_', '.', '/', '(', ')', '[', ']', '!', '¡', '%'], ' ', $param);

        return $param;
    }

    /**
     * Check if the currency is correct
     *
     * @param array $cart payment cart
     *
     * @return bool
     */
    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add payment method
     *
     * @return PaymentOption
     */
    public function getDigitalFemsaPaymentOption()
    {
        $embeddedOption = new PaymentOption();
        $embeddedOption->setModuleName($this->name)->setCallToActionText(
            $this->l('Paga en efectivo con OXXO PAY')
        )->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true))->setForm(
            $this->generateCardPaymentForm()
        )->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/oxxo.png'));

        return $embeddedOption;
    }

    /**
     * @return void
     */
    private function upgradeConfig()
    {
        $configMap = [
            'PAYMENT_METHS_CASH' => 'DIGITAL_FEMSA_METHOD_CASH',
            'EXPIRATION_DATE_LIMIT' => 'DIGITAL_FEMSA_EXPIRATION_DATE_LIMIT',
            'EXPIRATION_DATE_TYPE' => 'DIGITAL_FEMSA_EXPIRATION_DATE_TYPE',
            'MODE' => 'DIGITAL_FEMSA_MODE',
            'WEB_HOOK' => 'DIGITAL_FEMSA_WEBHOOK',
            'TEST_PRIVATE_KEY' => 'DIGITAL_FEMSA_PRIVATE_KEY_TEST',
            'TEST_PUBLIC_KEY' => 'DIGITAL_FEMSA_PUBLIC_KEY_TEST',
            'LIVE_PUBLIC_KEY' => 'DIGITAL_FEMSA_PUBLIC_KEY_LIVE',
            'LIVE_PRIVATE_KEY' => 'DIGITAL_FEMSA_PRIVATE_KEY_LIVE'
        ];
        array_walk($configMap, function ($destiny, $source) {
            Configuration::updateValue($destiny, Configuration::get($source));
            Configuration::deleteByName($source);
        });
    }

    /**
     * Update value and notify
     *
     * @return void
     */
    private function postProcess()
    {
        $configurationValues = [
            'DIGITAL_FEMSA_WEBHOOK' => rtrim(Tools::getValue('DIGITAL_FEMSA_WEBHOOK')),
            'DIGITAL_FEMSA_MODE' => Tools::getValue('DIGITAL_FEMSA_MODE'),
            'DIGITAL_FEMSA_PUBLIC_KEY_TEST' => rtrim(Tools::getValue('DIGITAL_FEMSA_PUBLIC_KEY_TEST')),
            'DIGITAL_FEMSA_PUBLIC_KEY_LIVE' => rtrim(Tools::getValue('DIGITAL_FEMSA_PUBLIC_KEY_LIVE')),
            'DIGITAL_FEMSA_PRIVATE_KEY_TEST' => rtrim(Tools::getValue('DIGITAL_FEMSA_PRIVATE_KEY_TEST')),
            'DIGITAL_FEMSA_PRIVATE_KEY_LIVE' => rtrim(Tools::getValue('DIGITAL_FEMSA_PRIVATE_KEY_LIVE')),
            'DIGITAL_FEMSA_METHOD_CASH' => rtrim(Tools::getValue('DIGITAL_FEMSA_METHOD_CASH')),
            'DIGITAL_FEMSA_EXPIRATION_DATE_LIMIT' => rtrim(Tools::getValue('DIGITAL_FEMSA_EXPIRATION_DATE_LIMIT')),
            'DIGITAL_FEMSA_EXPIRATION_DATE_TYPE' => rtrim(Tools::getValue('DIGITAL_FEMSA_EXPIRATION_DATE_TYPE')),
        ];
        array_walk($configurationValues, function ($value, $key) {
            Configuration::updateValue($key, $value);
        });

        array_walk($this->orderElements, function ($element) {
            $key = sprintf('ORDER_%s', Tools::strtoupper($element));
            Configuration::updateValue($key, Tools::getValue($key));
        });

        array_walk($this->productElements, function ($element) {
            $key = sprintf('PRODUCT_%s', Tools::strtoupper($element));
            Configuration::updateValue($key, Tools::getValue($key));
        });

        $this->html .= $this->displayConfirmation(
            $this->trans('Settings updated', [], 'Admin.Notifications.Success')
        );
    }

    /**
     * Display check
     *
     * @return template
     */
    private function displayCheck()
    {
        return $this->display(__FILE__, './views/templates/hook/infos.tpl');
    }

    /**
     * Returns the values of the fields in the configuration
     *
     * @return array
     */
    public function getConfigFieldsValues()
    {
        $settings = self::DigitalFemsaSettings;
        $settingsFieldsValues = [];

        array_walk($settings, function ($setting) use (&$settingsFieldsValues) {
            $settingValue = Tools::getValue($setting, Configuration::get($setting));

            if ($setting === 'DIGITAL_FEMSA_WEBHOOK') {
                $settingValue = !empty($settingValue) ? $settingValue : Context::getContext()->link->getModuleLink(
                    'digitalfemsa',
                    'notification',
                    ['ajax' => true]
                );
            }

            $settingsFieldsValues[$setting] = $settingValue;
        });

        array_walk($this->orderElements, function ($element) use (&$settingsFieldsValues) {
            $key = sprintf('ORDER_%s', Tools::strtoupper($element));
            $settingsFieldsValues[$key] = Configuration::get($key);
        });

        array_walk($this->productElements, function ($element) use (&$settingsFieldsValues) {
            $key = sprintf('PRODUCT_%s', Tools::strtoupper($element));
            $settingsFieldsValues[$key] = Configuration::get($key);
        });

        return $settingsFieldsValues;
    }

    /**
     * Build Admin Content
     *
     * @return array
     */
    public function buildAdminContent()
    {
        $this->context->controller->addJS($this->_path . 'views/js/functions.js');

        $orderMetadata = array_map(function ($value) {
            return [
                'id' => Tools::strtoupper($value),
                'name' => $value,
                'val' => $value,
            ];
        }, $this->orderElements);

        $productMetadata = array_map(function ($value) {
            return [
                'id' => Tools::strtoupper($value),
                'name' => $value,
                'val' => $value,
            ];
        }, $this->productElements);

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Contact details', [], 'Modules.DigitalFemsa.Admin'),
                    'icon' => 'icon-envelope',
                ],
                'input' => [
                    [
                        'type' => 'radio',
                        'label' => $this->l('Mode'),
                        'name' => 'DIGITAL_FEMSA_MODE',
                        'required' => true,
                        'class' => 't',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Production')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Sandbox')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Webhook', [], 'Modules.DigitalFemsa.Admin'),
                        'desc' => $this->trans(
                            'https://{domain}/es/module/digitalfemsa/notification?ajax=true',
                            [],
                            'Modules.DigitalFemsa.Admin'
                        ),
                        'name' => 'DIGITAL_FEMSA_WEBHOOK',
                        'required' => true,
                    ],
                    [
                        'type' => 'checkbox',
                        'label' => $this->l('Payment Method'),
                        'desc' => $this->l('Choose options.'),
                        'name' => 'DIGITAL_FEMSA_METHOD',
                        'values' => [
                            'query' => [
                                [
                                    'id' => 'CASH',
                                    'name' => $this->l('Cash'),
                                    'val' => 'cash_payment_method'
                                ],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'expand' => [
                            'print_total' => 1,
                            'default' => 'show',
                            'show' => ['text' => $this->l('show'), 'icon' => 'plus-sign-alt'],
                            'hide' => ['text' => $this->l('hide'), 'icon' => 'minus-sign-alt'],
                        ],
                    ],
                    [
                        'type' => 'radio',
                        'label' => $this->l('Expiration date type'),
                        'name' => 'DIGITAL_FEMSA_EXPIRATION_DATE_TYPE',
                        'class' => 't',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'DIGITAL_FEMSA_EXPIRATION_DATE_TYPE_DAYS', 'value' => 0, 'label' => $this->l('Days')],
                            ['id' => 'DIGITAL_FEMSA_EXPIRATION_DATE_TYPE_HOURS', 'value' => 1, 'label' => $this->l('Hours')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Expiration date limit', [], 'Modules.DigitalFemsa.Admin'),
                        'name' => 'DIGITAL_FEMSA_EXPIRATION_DATE_LIMIT',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Test Private Key', [], 'Modules.DigitalFemsa.Admin'),
                        'name' => 'DIGITAL_FEMSA_PRIVATE_KEY_TEST',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Test Public Key', [], 'Modules.DigitalFemsa.Admin'),
                        'name' => 'DIGITAL_FEMSA_PUBLIC_KEY_TEST',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Live Private Key', [], 'Modules.DigitalFemsa.Admin'),
                        'name' => 'DIGITAL_FEMSA_PRIVATE_KEY_LIVE',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Live Public Key', [], 'Modules.DigitalFemsa.Admin'),
                        'name' => 'DIGITAL_FEMSA_PUBLIC_KEY_LIVE',
                        'required' => true,
                    ],
                    [
                        'type' => 'checkbox',
                        'label' => $this->l('Additional Order Metadata'),
                        'name' => 'ORDER',
                        'values' => [
                            'query' => $orderMetadata,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'expand' => [
                            'print_total' => count($orderMetadata),
                            'default' => 'show',
                            'show' => [
                                'text' => $this->l('show'),
                                'icon' => 'plus-sign-alt',
                            ],
                            'hide' => [
                                'text' => $this->l('hide'),
                                'icon' => 'minus-sign-alt',
                            ],
                        ],
                    ],
                    [
                        'type' => 'checkbox',
                        'label' => $this->l('Additional Product Metadata'),
                        'name' => 'PRODUCT',
                        'values' => [
                            'query' => $productMetadata,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'expand' => [
                            'print_total' => count($productMetadata),
                            'default' => 'show',
                            'show' => [
                                'text' => $this->l('show'),
                                'icon' => 'plus-sign-alt',
                            ],
                            'hide' => [
                                'text' => $this->l('hide'),
                                'icon' => 'minus-sign-alt',
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
            ],
        ];

        return $fields_form;
    }

    /**
     * Render form
     *
     * @return string
     */
    public function renderForm()
    {
        $adminUrl = $this->context->link->getAdminLink('AdminModules', false);
        $templateCurrentIndex = '%s&configure=%s&tab_module=%s&module_name=%s';
        $urlCurrentIndex = sprintf($templateCurrentIndex, $adminUrl, $this->name, $this->tab, $this->name);

        $fields_form = $this->buildAdminContent();
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $urlCurrentIndex;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = ['fields_value' => $this->getConfigFieldsValues()];
        $this->fields_form = [];

        return $helper->generateForm([$fields_form]);
    }

    /**
     * Check settings keys
     *
     * @return bool
     */
    public function checkSettings()
    {
        $mode = Tools::getValue('DIGITAL_FEMSA_MODE');
        $valid = !empty(Tools::getValue('DIGITAL_FEMSA_PUBLIC_KEY_LIVE'))
            && !empty(Tools::getValue('DIGITAL_FEMSA_PRIVATE_KEY_LIVE'));

        if (!$mode) {
            $valid = !empty(Tools::getValue('DIGITAL_FEMSA_PUBLIC_KEY_TEST'))
                && !empty(Tools::getValue('DIGITAL_FEMSA_PRIVATE_KEY_TEST'));
        }

        return $valid;
    }

    /**
     * Check requirements
     *
     * @return array
     */
    public function checkRequirements()
    {
        $testRequirements = [];

        if (!function_exists('curl_init')) {
            $testRequirements['curl'] = [
                'name' => $this->l('PHP cURL extension must be enabled on your server'),
            ];
        }

        if (Tools::getValue('DIGITAL_FEMSA_MODE')
            && (!Configuration::get('PS_SSL_ENABLED')
                || (!empty(filter_input(INPUT_SERVER, 'HTTPS'))
                    && Tools::strtolower(filter_input(INPUT_SERVER, 'HTTPS')) === 'off'))
        ) {
            $testRequirements['ssl'] = [
                'name' => $this->l('SSL must be enabled on your store (before entering Live mode)'),
            ];
        }

        if (version_compare(PHP_VERSION, '7.1', '<')) {
            $testRequirements['php7.1'] = [
                'name' => $this->l('Your server must run PHP 7.1 or greater'),
            ];
        }

        if (!$this->checkSettings()) {
            $testRequirements['configuration'] = [
                'name' => $this->l('You must sign-up for DIGITAL_FEMSA and configure your account settings in the module'),
            ];
        }

        if (version_compare(_PS_VERSION_, '1.7', '<')) {
            $tests['backward'] = [
                'name' => $this->l('You are using the backward compatibility module'),
            ];
        }

        $testRequirements['result'] = count($testRequirements) === 0;

        return $testRequirements;
    }

    /**
     * Returns the template's HTML content.
     *
     * @return string HTML content
     */
    public function getContent()
    {
        // CODE FOR WEBHOOK VALIDATION UNTESTED DONT ERASE

        $this->smarty->assign('base_uri', __PS_BASE_URI__);
        $this->smarty->assign('mode', Configuration::get('DIGITAL_FEMSA_MODE'));

        $this->html = '';

        if (Tools::isSubmit('btnSubmit')) {
            $errors = (new ValidateAdminForm())($this->orderElements, $this->productElements);

            if (count($errors) > 0) {
                array_walk($errors, function ($error) {
                    $this->html .= $this->displayError($error);
                });
            }

            $requirements = $this->checkRequirements();
            $this->smarty->assign('_path', $this->_path);

            if (!$requirements['result']) {
                $this->smarty->assign('requirements', $requirements);
                $this->smarty->assign('config_check', $requirements['result']);
                $this->smarty->assign('msg_show', $this->l('Please resolve the following errors:'));
            }

            if (count($errors) <= 0 && $requirements['result']) {
                $oldWebHook = Configuration::get('DIGITAL_FEMSA_WEBHOOK');
                $this->postProcess();

                if (!$this->createWebhook($oldWebHook)) {
                    $webhookMessage = Configuration::get('DIGITAL_FEMSA_WEBHOOK_ERROR_MESSAGE');
                    $this->smarty->assign('error_webhook_message', $webhookMessage);
                }
            }
        }

        $this->html .= $this->displayCheck();
        $this->html .= $this->renderForm();

        return $this->html;
    }

    /**
     * Create customer of DigitalFemsa
     *
     * @param $customer Info user in Prestashop
     * @param $params   Info of user
     *
     * @return string
     */
    public function createCustomer($customer, $params)
    {
        try {
            // Use centralized configuration
            $femsaCfg = $this->getDigitalFemsaConfig();

            $customersApi = new CustomersApi(null, $femsaCfg);
            $customerModel = new \DigitalFemsa\Model\Customer([
                'name' => $params['name'] ?? ($customer->firstname . ' ' . $customer->lastname),
                'email' => $params['email'] ?? $customer->email,
                'phone' => $params['phone'] ?? '',
                'metadata' => $params['metadata'] ?? []
            ]);

            $customerResponse = $customersApi->createCustomer($customerModel);
            $customerId = method_exists($customerResponse, 'getId') ? $customerResponse->getId() : (isset($customerResponse->id) ? $customerResponse->id : null);

            if ($customerId) {
                DigitalFemsaDatabase::updateDigitalFemsaMetadata(
                    $customer->id,
                    $this->digitalFemsaMode,
                    'digital_femsa_customer_id',
                    $customerId
                );
            }

            return $customerId;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create Webhook
     *
     * @return bool
     */
    private function createWebhook(string $oldWebhook): bool
    {
        $key = Configuration::get('DIGITAL_FEMSA_MODE') ? Configuration::get('DIGITAL_FEMSA_PRIVATE_KEY_LIVE')
            : Configuration::get('DIGITAL_FEMSA_PRIVATE_KEY_TEST');
        $isoCode = $this->context->language->iso_code;

        return (new CreateWebhook())(
            Configuration::get('DIGITAL_FEMSA_MODE'),
            $key,
            $isoCode,
            $this->version,
            $oldWebhook
        );
    }

    /**
     * Generate Payment form
     *
     * @return string HTML generate payment form
     */
    protected function generateCardPaymentForm()
    {
        $months = [];

        for ($i = 1; $i <= 12; ++$i) {
            $months[] = sprintf('%02d', $i);
        }

        $years = [];

        for ($i = 0; $i <= 10; ++$i) {
            $years[] = date('Y', strtotime('+' . $i . ' years'));
        }

        $this->context->smarty->assign(
            [
                'action' => $this->context->link->getModuleLink($this->name, 'validation', [], true),
                'months' => $months,
                'years' => $years,
                'test_private_key' => Configuration::get('DIGITAL_FEMSA_PRIVATE_KEY_TEST'),
                'path' => $this->_path,
            ]
        );

        return $this->context->smarty->fetch('module:digitalfemsa/views/templates/front/payment_form.tpl');
    }

    /**
     * Payment process and validates if the payment was made correctly
     *
     * @param $digitalFemsaOrderId The id of the order to pay
     *
     * @return string link redirect
     */
    public function processPayment($digitalFemsaOrderId)
    {
        // Use centralized configuration
        $femsaCfg = $this->getDigitalFemsaConfig();
        $ordersApi = new OrdersApi(null, $femsaCfg);

        try {
            $order = $ordersApi->getOrderById($digitalFemsaOrderId->id);
            $charges = $order && $order->getCharges() ? $order->getCharges()->getData() : [];
            $charge_response = !empty($charges) ? $charges[0] : null;
            $order_status = (int) Configuration::get('PS_OS_PAYMENT');
            $createAtDate = new DateTime();
            if ($charge_response && method_exists($charge_response, 'getCreatedAt')) {
                $createAtDate->setTimestamp($charge_response->getCreatedAt());
            }

            $message = $this->l('DigitalFemsa Transaction Details:')
                . "\n\n" . $this->l('Amount:') . ' ' . ($order->getAmount() * 0.01) . "\n"
                . $this->l('Status:') . ' '
                . ($charge_response && $charge_response->getStatus() == 'paid' ? $this->l('Paid') : $this->l('Unpaid'))
                . "\n" . $this->l('Processed on:') . ' ' . $createAtDate->format('Y-m-d H:i:s')
                . "\n" . $this->l('Currency:') . ' ' . Tools::strtoupper($order->getCurrency())
                . "\n" . $this->l('Mode:') . ' '
                . (($order->getLivemode()) ? $this->l('Live') : $this->l('Test')) . "\n";

            $this->validateOrder(
                (int) $this->context->cart->id,
                (int) $order_status,
                $order->getAmount() / 100,
                $this->displayName,
                $message,
                [],
                null,
                false,
                $this->context->customer->secure_key
            );

            if (version_compare(_PS_VERSION_, '1.5', '>=')) {
                $new_order = new Order((int) $this->currentOrder);

                if (Validate::isLoadedObject($new_order)) {
                    $payment = $new_order->getOrderPaymentCollection();

                    if (isset($payment[0]) && $charge_response) {
                        $payment[0]->transaction_id = pSQL($charge_response->getId());
                        $payment[0]->save();
                    }
                }
            }

            $reference = $charge_response && $charge_response->getPaymentMethod() ? $charge_response->getPaymentMethod()->getReference() : '';

            $legacyOrder = (object) [
                'id' => method_exists($order, 'getId') ? $order->getId() : null,
                'amount' => method_exists($order, 'getAmount') ? $order->getAmount() : null,
            ];
            $legacyCharge = (object) [
                'id' => $charge_response && method_exists($charge_response, 'getId') ? $charge_response->getId() : null,
                'currency' => $charge_response && method_exists($charge_response, 'getCurrency') ? $charge_response->getCurrency() : '',
                'livemode' => method_exists($order, 'getLivemode') && $order->getLivemode() ? 'true' : 'false',
            ];

            DigitalFemsaDatabase::insertOxxoPayment(
                $legacyOrder,
                $legacyCharge,
                $reference,
                $this->currentOrder,
                $this->context->cart->id
            );


            DigitalFemsaDatabase::updateDigitalFemsaOrder(
                $this->context->customer->id,
                $this->context->cart->id,
                $this->digitalFemsaMode,
                $order->getId(),
                $charge_response ? $charge_response->getStatus() : ''
            );

            $redirect = $this->context->link->getPageLink(
                'order-confirmation',
                true,
                null,
                [
                    'id_order' => (int) $this->currentOrder,
                    'id_cart' => (int) $this->context->cart->id,
                    'key' => $this->context->customer->secure_key,
                    'id_module' => (int) $this->id,
                ]
                );

            if (class_exists('Logger')) {
                Logger::addLog(
                    $this->l('Redirecting to') . ' ' . $redirect,
                    1,
                    null,
                    'Cart',
                    (int) $this->context->cart->id,
                    true
                );
            }

            Tools::redirect($redirect);
                
        } catch (\Exception $e) {
            $message = $e->getMessage();

            $controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
            $location = $this->context->link->getPageLink($controller, true)
                . (strpos($controller, '?') !== false ? '&' : '?')
                . 'step=3&digital_femsa_error=1&message=' . $message . '#digital_femsa_error';

            Tools::redirectLink($location);
        }
    }

    /**
     * Fetch template with the name
     *
     * @param $name Name of template
     *
     * @return string link template
     */
    public function fetchTemplate($name)
    {
        $views = 'views/templates/';

        if (@filemtime(dirname(__FILE__) . '/' . $name)) {
            return $this->display(__FILE__, $name);
        } elseif (@filemtime(dirname(__FILE__) . '/' . $views . 'hook/' . $name)) {
            return $this->display(__FILE__, $views . 'hook/' . $name);
        } elseif (@filemtime(dirname(__FILE__) . '/' . $views . 'front/' . $name)) {
            return $this->display(__FILE__, $views . 'front/' . $name);
        } elseif (@filemtime(dirname(__FILE__) . '/' . $views . 'admin/' . $name)) {
            return $this->display(__FILE__, $views . 'admin/' . $name);
        }

        return $this->display(__FILE__, $name);
    }

    /**
     * Returns a template with the order status
     *
     * @param $order_id The id of order
     *
     * @return string HTML
     */
    public function getTransactionStatus($order_id)
    {
        if (DigitalFemsaDatabase::getOrderDigitalFemsa($order_id) == $this->name) {
            $digital_femsa_tran_details = DigitalFemsaDatabase::getDigitalFemsaTransaction($order_id);

            $this->smarty->assign('digital_femsa_tran_details', $digital_femsa_tran_details);

            if ($digital_femsa_tran_details['status'] === 'paid') {
                $this->smarty->assign('color_status', 'green');
                $this->smarty->assign('message_status', $this->l('Paid'));
            } else {
                $this->smarty->assign('color_status', '#CC0000');
                $this->smarty->assign('message_status', $this->l('Unpaid'));
            }
            $this->smarty->assign(
                'display_price',
                Tools::displayPrice($digital_femsa_tran_details['amount'])
            );
            $this->smarty->assign(
                'processed_on',
                Tools::safeOutput($digital_femsa_tran_details['date_add'])
            );

            if ($digital_femsa_tran_details['mode'] === 'live') {
                $this->smarty->assign('color_mode', 'green');
                $this->smarty->assign('txt_mode', $this->l('Live'));
            } else {
                $this->smarty->assign('color_mode', '#CC0000');
                $this->smarty->assign(
                    'txt_mode',
                    $this->l(
                        'Test (No payment has been processed and you will'
                        . ' need to enable the &quot;Live&quot; mode)'
                    )
                );
            }

            return $this->fetchTemplate('admin-order.tpl');
        }
    }
}
