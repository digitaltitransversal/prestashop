{**
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
 * @category  DigitalFemsa
 * @version   GIT: @2.3.7@
 * @see       https://digitalfemsa.io/
 *}
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script type="text/javascript" src="{$df_pay_js|escape:'htmlall':'UTF-8'}"></script>
<script type="text/javascript" src="{$path|escape:'htmlall':'UTF-8'}views/js/tokenize.js"></script>


<script type="text/javascript">
	var digital_femsa_public_key = "{$api_key|escape:'htmlall':'UTF-8'}";
	var digital_femsa_checkout_id = "{$checkoutRequestId|escape:'htmlall':'UTF-8'}";
	var digital_femsa_php_version = "{$phpversion|escape:'htmlall':'UTF-8'}";
	var digital_femsa_phpversion = "{$phpversion|escape:'htmlall':'UTF-8'}";
	var digital_femsa_plugin_version = "{$digitalfemsa_version|escape:'htmlall':'UTF-8'}";
	var digital_femsa_sdk_version = "{$digitalfemsa_sdk_version|escape:'htmlall':'UTF-8'}";
	var digital_femsa_platform_version = "{$digitalfemsa_platform_version|escape:'htmlall':'UTF-8'}";
	var digital_femsa_order_id = "{$orderID|escape:'htmlall':'UTF-8'}";
	var digital_femsa_amount = "{$amount|escape:'htmlall':'UTF-8'}";
</script> 
