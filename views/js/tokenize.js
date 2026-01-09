/**
* 2024 DigitalFemsa
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author DigitalFemsa <monitoreo.b2b@digitalfemsa.com>
*  @copyright  2024 DigitalFemsa
*  @version  v2.0.0
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

var digitalFemsaSuccessResponseHandler = function(response) {
	console.log(response);
	var $form = $('#digital-femsa-payment-form');
	$form.append($('<input type="hidden" name="digitalFemsaToken" id="digitalFemsaToken" />').val(response.id));
};
 
var digitalFemsaErrorResponseHandler = function(token) {
	if ($('.digital-femsa-payment-errors').length) {
		$('.digital-femsa-payment-errors').fadeIn(1000);
	} else {
		$('#digital-femsa-payment-form').prepend('<div class="digital-femsa-payment-errors">' + token +'</div>');
		$('.digital-femsa-payment-errors').fadeIn(1000);
	}
};

$(document).ready(function($) {
	var started = false;
	var paymentOption = document.querySelectorAll('input[data-module-name="digitalfemsa"]')[0];

	function canStart() {
		return !!document.getElementById('digitalFemsaIframeContainer') &&
			window.DigitalFemsaCheckoutComponents && !started;
	}

	function startIntegration() {
		if (!canStart()) return;

		var hasCheckoutId = (typeof digital_femsa_checkout_id !== 'undefined' && digital_femsa_checkout_id);
		var hasPublicKey = (typeof digital_femsa_public_key !== 'undefined' && digital_femsa_public_key);
		if (!hasCheckoutId || !hasPublicKey) {
			try { console.error('[DF] Missing checkout prerequisites', { hasCheckoutId: !!hasCheckoutId, hasPublicKey: !!hasPublicKey, checkoutId: digital_femsa_checkout_id }); } catch (e) {}
			return;
		}

		const uname = (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || 'unknown';
		const isMobile = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || (navigator.userAgentData && navigator.userAgentData.mobile);
		const deviceType = isMobile ? 'mobile' : 'desktop';
		const phpVersion = (typeof digital_femsa_php_version !== 'undefined' && digital_femsa_php_version) ?
			digital_femsa_php_version :
			((typeof digital_femsa_phpversion !== 'undefined' && digital_femsa_phpversion) ? digital_femsa_phpversion : 'unknown');
		const pluginVersion = typeof digital_femsa_plugin_version !== 'undefined' ? digital_femsa_plugin_version : 'unknown';
		const sdkVersion = typeof digital_femsa_sdk_version !== 'undefined' ? digital_femsa_sdk_version : 'unknown';
		const platformVersion = typeof digital_femsa_platform_version !== 'undefined' ? digital_femsa_platform_version : 'unknown';

		const metadata = [
			{ key: "lang", value: "php" },
            { key: "lang_version", value: phpVersion },
			{ key: "uname", value: uname },
            { key: "integration_type", value: "plugin" },
            { key: "integration_name", value: "spin-prestashop" },
			{ key: "plugin_version", value: pluginVersion },
			{ key: "sdk_version", value: sdkVersion },
			{ key: "platform_version", value: platformVersion },
            { key: "device_type", value: deviceType },
            { key: "plugin", value: "Prestashop" },
            { key: "sdk_name", value: "prestashop_plugin" },
        ];	

		started = true;
		window.DigitalFemsaCheckoutComponents.Integration({
			targetIFrame: "#digitalFemsaIframeContainer",
			metadata: metadata,
			checkoutRequestId: digital_femsa_checkout_id,
			publicKey: digital_femsa_public_key,
			options: {
				theme: 'default',
				styles: { fontSize: 'baseline', inputType: 'rounded', buttonType: 'sharp' }
			},
			onCreateTokenSucceeded: function (token) {
				console.log('Token creado');
				var container = document.getElementById('digitalFemsaIframeContainer');
				if (container) { container.remove(); }
				digitalFemsaSuccessResponseHandler(token);
			},
			onCreateTokenError: function (error) {
				console.log(error);
				digitalFemsaErrorResponseHandler(error);
			},
			onFinalizePayment: function(event) {
				var $form = $('#digital-femsa-payment-form');
				$form.append($('<input type="hidden" name="digital_femsa_orden_id" id="digital_femsa_orden_id" />').val(digital_femsa_order_id));
				$form.append($('<input type="hidden" name="digital_femsa_mount" id="digital_femsa_mount" />').val(digital_femsa_amount));
				$form.append($('<input type="hidden" name="chargeId" id="chargeId" />').val(event.charge.id));
				$form.append($('<input type="hidden" name="charge_currency" id="charge_currency" />').val(event.charge.currency));
				$form.append($('<input type="hidden" name="charge_status" id="charge_status" />').val(event.charge.status));
				$form.append($('<input type="hidden" name="payment_type" id="payment_type" />').val(event.charge.paymentMethod.type));
				$form.append($('<input type="hidden" name="reference" id="reference" />').val((event.reference)? event.reference : null));
				$form.get(0).submit();
				console.log('Pago exitoso.');
			},
			onErrorPayment: function(event) {
				console.log(event);
				alert('Pago declinado.');
			}
		});
	}

	// Try immediately (works if template already injected)
	startIntegration();

	// Also trigger after selecting the payment option
	$("input[name=payment-option]").on('click', function () {
		setTimeout(startIntegration, 0);
	});

	// Observe DOM for late injection of the container
	var obs = new MutationObserver(function() {
		if (canStart()) {
			startIntegration();
			obs.disconnect();
		}
	});
	obs.observe(document.documentElement, { childList: true, subtree: true });

	// Keep existing UI toggle
	$("input[name=payment-option]").click(function () {
		if (paymentOption && paymentOption.checked) {
			$('#payment-confirmation').find('button').hide();
			$('#conditions-to-approve').hide();
		} else {
			$('#payment-confirmation').find('button').show();
			$('#conditions-to-approve').show();
		}
	});
});