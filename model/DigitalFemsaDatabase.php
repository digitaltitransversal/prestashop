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

/**
 * Database Class Doc Comment
 *
 * @author   DigitalFemsa <monitoreo.b2b@digitalfemsa.com>
 *
 * @category Class
 *
 * @license  http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @see     https://digitalfemsa.io/
 */
class DigitalFemsaDatabase
{
    /**
     * Returns the module that the payment of the order was made.
     *
     * @param $order_id Order id
     *
     * @return array|string
     */
    public static function getOrderDigitalFemsa($order_id)
    {
        return Db::getInstance()->getValue(
            'SELECT module FROM ' . _DB_PREFIX_ . 'orders '
            . 'WHERE id_order = ' . pSQL((int) $order_id)
        );
    }

    /**
     * Returns information of the order paid.
     *
     * @param $order_id The order id
     *
     * @return array
     */
    public static function getDigitalFemsaTransaction($order_id)
    {
        return Db::getInstance()->getRow(
            'SELECT * FROM ' . _DB_PREFIX_ . 'digital_femsa_transaction '
            . 'WHERE id_order = ' . (int) $order_id
            . ' AND type = \'payment\''
        );
    }

    /**
     * Insert payment with oxxo
     *
     * @param Order $order Object order
     * @param array $charge_response Charges made on the order
     * @param string $reference Payment reference code
     * @param int $currentOrder Order ID
     * @param int $cartId Cart ID
     *
     * @return bool
     */
    public static function insertOxxoPayment($order, $charge_response, $reference, $currentOrder, $cartId)
    {
        return Db::getInstance()->Execute(
            'INSERT INTO ' . _DB_PREFIX_ . 'digital_femsa_transaction ('
            . 'type, id_cart, id_order, id_digital_femsa_order, id_transaction, amount,'
            . 'status, currency, mode, date_add, reference, barcode, captured)'
            . 'VALUES (\'payment\', ' . pSQL((int) $cartId) . ', ' . pSQL((int) $currentOrder) . ', \''
            . pSQL($order->id) . '\', \'' . pSQL($charge_response->id) . '\',\''
            . (float) ($order->amount * 0.01) . '\', \''
            . ($charge_response->status == 'paid' ? 'paid' : 'unpaid') . '\', \''
            . pSQL($charge_response->currency) . '\', \''
            . ($charge_response->livemode == 'true' ? 'live' : 'test') . '\', NOW(),\''
            . pSQL($reference) . '\',\'' . pSQL($reference) . '\',\''
            . ($charge_response->livemode == 'true' ? '1' : '0') . '\' )'
        );
    }

    /**
     * Create table ps_digital_femsa_transaction
     *
     * @return bool
     */
    public static function installDb()
    {
        return
            Db::getInstance()->execute(
                'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'digital_femsa_transaction` (
                `id_digital_femsa_transaction` int(11) NOT NULL AUTO_INCREMENT,
                `type` enum(\'payment\',\'refund\') NOT NULL,
                `id_cart` int(10) unsigned NOT NULL,
                `id_order` int(10) unsigned NOT NULL,
                `id_digital_femsa_order` varchar(32) NOT NULL,
                `id_transaction` varchar(32) NOT NULL,
                `amount` decimal(10,2) NOT NULL,
                `status` enum(\'paid\',\'unpaid\') NOT NULL,
                `currency` varchar(3) NOT NULL,
                `mode` enum(\'live\',\'test\') NOT NULL,
                `date_add` datetime NOT NULL,
                `reference` varchar(30) NOT NULL,
                `barcode` varchar(230) NOT NULL,
                `captured` tinyint(1) NOT NULL DEFAULT \'1\',
                PRIMARY KEY (`id_digital_femsa_transaction`),
                KEY `idx_transaction` (`type`,`id_order`,`status`))
                ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 AUTO_INCREMENT=1'
            )
        ;
    }

    /**
     * Create table ps_digital_femsa_metadata
     *
     * @return bool
     */
    public static function createTableMetaData()
    {
        $table = _DB_PREFIX_ . 'digital_femsa_metadata';
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id_digital_femsa_metadata int(11) NOT NULL AUTO_INCREMENT,
            id_user int(11) unsigned NOT NULL,
            `mode` enum(\"live\",\"test\") NOT NULL,
            meta_option varchar(32) NOT NULL,
            meta_value varchar(128) NOT NULL,
            PRIMARY KEY (id_digital_femsa_metadata),
            KEY id_user (id_user),
            KEY id_digital_femsa_metadata (id_digital_femsa_metadata)
            )
            ENGINE=" . _MYSQL_ENGINE_ . 'DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;';

        return Db::getInstance()->execute($sql);
    }

    /**
     * Create table ps_digital_femsa_product_data
     *
     * @return bool
     */
    public static function createTableProductData()
    {
        $table = _DB_PREFIX_ . 'digital_femsa_product_data';
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id_digital_femsa_product_data int(11) NOT NULL AUTO_INCREMENT,
            id_product int(11) unsigned NOT NULL,
            product_attribute varchar(32) NOT NULL,
            product_value varchar(128) NOT NULL,
            PRIMARY KEY (id_digital_femsa_product_data),
            KEY id_product (id_product),
            KEY id_digital_femsa_product_data (id_digital_femsa_product_data)
            )
            ENGINE=" . _MYSQL_ENGINE_ . 'DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;';

        return Db::getInstance()->execute($sql);
    }

    /**
     * Create table ps_digital_femsa_order_checkout
     *
     * @return bool
     */
    public static function createTableDigitalFemsaOrder()
    {
        $table = _DB_PREFIX_ . 'digital_femsa_order_checkout';
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id int(11) NOT NULL AUTO_INCREMENT,
            id_user int(11) unsigned NOT NULL,
            id_cart int(11) unsigned NOT NULL,
            `mode` enum(\"live\",\"test\") NOT NULL,
            id_digital_femsa_order varchar(32) NOT NULL,
            `status` enum(\"paid\",\"pre_authorized\",\"unpaid\",\"pending_payment\",\"expired\",\"voided\","
            . '"fraudulent","preauthorized","canceled","pending_confirmation","charged_back",'
            . '"partially_refunded","refunded","reversed","approved","declined","in_review",'
            . '"insufficient_funds","card_declined","stolen_card","suspected_fraud",'
            . '"unprocessable_card_type") NOT NULL,
            PRIMARY KEY (id),
            KEY id_user (id_user),
            KEY id_cart (id_cart),
            KEY id (id),
            KEY id_digital_femsa_order (id_digital_femsa_order)
            )
            ENGINE=' . _MYSQL_ENGINE_ . 'DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;';

        return Db::getInstance()->execute($sql);
    }

    /**
     * Returns the order information
     *
     * @param int $id_order Order ID
     *
     * @return array
     */
    public static function getOrderById($id_order)
    {
        return Db::getInstance()->getRow(
            'SELECT * FROM ' . _DB_PREFIX_ . 'digital_femsa_transaction '
            . 'WHERE id_order = ' . pSQL((int) $id_order) . ';'
        );
    }

    /**
     * Returns the order information
     *
     * @param int $user_id Order ID
     * @param string $mode Mode (Production or Test)
     * @param string $meta_options Metadata option to be searched
     *
     * @return array|string
     */
    public static function getDigitalFemsaMetadata($user_id, $mode, $meta_options)
    {
        $table = _DB_PREFIX_ . 'digital_femsa_metadata';

        $sql = "SELECT meta_value FROM  $table WHERE id_user = " . (int) $user_id
        . " AND meta_option = '" . pSQL($meta_options) . "'"
        . " AND `mode` = '" . pSQL($mode) . "'";

        return Db::getInstance()->getRow($sql);
    }

    /**
     * Returns the product information
     *
     * @param int $id_product Product ID
     * @param string $product_attribute Attribute of the product
     *
     * @return array|string
     */
    public static function getDigitalFemsaProductData($id_product, $product_attribute)
    {
        $table = _DB_PREFIX_ . 'digital_femsa_product_data';

        $sql = "SELECT product_value FROM  $table WHERE id_product = " . (int) $id_product
        . " AND product_attribute = '" . pSQL($product_attribute) . "'";

        return Db::getInstance()->getRow($sql);
    }

    /**
     * Returns the ID of products
     *
     * @param string $product_value Plan key
     *
     * @return array|string
     */
    public static function getProductIdProductData($product_value)
    {
        $table = _DB_PREFIX_ . 'digital_femsa_product_data';

        $sql = "SELECT id_product FROM $table WHERE product_value = '" . pSQL($product_value) . "'";

        return Db::getInstance()->ExecuteS($sql);
    }

    /**
     * Save or update value.
     *
     * @param int $user_id User ID
     * @param string $mode Mode (Production or Test)
     * @param string $meta_options Metadata option to save
     * @param string $meta_value Value to be saved
     *
     * @return bool
     */
    public static function updateDigitalFemsaMetadata($user_id, $mode, $meta_options, $meta_value)
    {
        $table = _DB_PREFIX_ . 'digital_femsa_metadata';

        if (empty(DigitalFemsaDatabase::getDigitalFemsaMetadata($user_id, $mode, $meta_options))) {
            $sql = "INSERT INTO $table(id_user, mode, meta_option, meta_value) "
            . "VALUES (" . (int) $user_id . ",'" . pSQL($mode) . "','" . pSQL($meta_options) . "','" . pSQL($meta_value) . "')";
        } else {
            $sql = "UPDATE $table SET id_user = " . (int) $user_id . ", meta_option = '" . pSQL($meta_options) . "', "
            . "meta_value = '" . pSQL($meta_value) . "' WHERE id_user = " . (int) $user_id . " AND meta_option = '" . pSQL($meta_options) . "'"
            . " AND `mode` = '" . pSQL($mode) . "'";
        }

        return Db::getInstance()->Execute($sql);
    }

    /**
     * Save or update value.
     *
     * @param int $id_product User ID
     * @param string $product_attribute Mode (Production or Test)
     * @param string $product_value Metadata option to save
     *
     * @return bool
     */
    public static function updateDigitalFemsaProductData($id_product, $product_attribute, $product_value)
    {
        $table = _DB_PREFIX_ . 'digital_femsa_product_data';

        if (empty(self::getDigitalFemsaProductData($id_product, $product_attribute))) {
            $sql = "INSERT INTO $table(id_product, product_attribute, product_value) "
            . "VALUES (" . (int) $id_product . ",'" . pSQL($product_attribute) . "','" . pSQL($product_value) . "')";
        } else {
            $sql = "UPDATE $table SET product_value = '" . pSQL($product_value) . "'"
            . " WHERE id_product = " . (int) $id_product . " AND product_attribute = '" . pSQL($product_attribute) . "'";
        }

        return Db::getInstance()->Execute($sql);
    }

    /**
     * Returns the id of the order created
     *
     * @param int $user_id User ID
     * @param string $mode Mode (Production or Test)
     * @param int $cart_id Cart ID
     *
     * @return array|string
     */
    public static function getDigitalFemsaOrder($user_id, $mode, $cart_id)
    {
        $table = _DB_PREFIX_ . 'digital_femsa_order_checkout';

        $sql = "SELECT id_digital_femsa_order, `status` FROM  $table WHERE id_user = " . (int) $user_id
        . " AND `mode` = '" . pSQL($mode) . "' AND `status` = 'unpaid' AND id_cart = " . (int) $cart_id;

        return Db::getInstance()->getRow($sql);
    }

    /**
     * Add or update placed orders
     *
     * @param int $user_id User ID
     * @param int $cart_id Cart ID
     * @param string $mode Mode (Production or Test)
     * @param string $id_digital_femsa_order Order ID generate for DigitalFemsa
     * @param string $status Order status
     *
     * @return bool
     */
    public static function updateDigitalFemsaOrder($user_id, $cart_id, $mode, $id_digital_femsa_order, $status)
    {
        $table = _DB_PREFIX_ . 'digital_femsa_order_checkout';

        if (empty(DigitalFemsaDatabase::getDigitalFemsaOrder($user_id, $mode, $cart_id))) {
            $sql = "INSERT INTO $table(id_user,	id_cart, mode, id_digital_femsa_order, `status`) "
            . "VALUES (" . (int) $user_id . "," . (int) $cart_id . ",'" . pSQL($mode) . "','" . pSQL($id_digital_femsa_order) . "', '" . pSQL($status) . "')";
        } else {
            $sql = "UPDATE $table SET `status` = '" . pSQL($status) . "' WHERE id_user = " . (int) $user_id
            . " AND id_cart = " . (int) $cart_id . " AND id_digital_femsa_order = '" . pSQL($id_digital_femsa_order) . "' AND `mode` = '" . pSQL($mode) . "'";
        }

        return Db::getInstance()->Execute($sql);
    }

    /**
     * Returns the id of the order related to the reference_id
     *
     * @param string $reference_id alphabetical reference code assigned to the order
     *
     * @return array|string
     */
    public static function getOrderByReferenceId($reference_id)
    {
        $table = _DB_PREFIX_ . 'orders';
        $sql = "SELECT id_order FROM $table WHERE id_order = " . (int) $reference_id;

        return Db::getInstance()->getRow($sql);
    }
}
