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

namespace DigitalFemsa\Payments\UseCases;

use DigitalFemsa\Configuration as DFConfiguration;
use DigitalFemsa\Api\WebhooksApi;
use DigitalFemsa\Model\WebhookRequest;
use Tools;
use Configuration;

class CreateWebhook
{
    public const webhookSetting = 'DIGITAL_FEMSA_WEBHOOK';

    public const webhookFailedUrlSetting = 'DIGITAL_FEMSA_WEBHOOK_FAILED_URL';

    public const webhookErrorSetting = 'DIGITAL_FEMSA_WEBHOOK_ERROR_MESSAGE';

    public const webhookAttemptsSetting = 'DIGITAL_FEMSA_WEBHOOK_FAILED_ATTEMPTS';

    public const MaxFailedAttempts = 5;

    public function __invoke(
        bool $digitalFemsaMode,
        string $privateKey,
        string $isoCode,
        string $pluginVersion,
        string $oldWebhook
    ): bool {
        // Configure SDK with Bearer access token and merged UA/integration headers
        $cfg = DFConfiguration::getDefaultConfiguration();
        $cfg->setAccessToken($privateKey);
        $headerSelector = new \DigitalFemsa\HeaderSelector();
        $userAgentHeaders = $headerSelector->getFemsaUserAgent();
        $existingUserAgent = [];
        if (isset($userAgentHeaders['X-DigitalFemsa-Client-User-Agent'])) {
            $decoded = json_decode($userAgentHeaders['X-DigitalFemsa-Client-User-Agent'], true);
            if (is_array($decoded)) {
                $existingUserAgent = $decoded;
            }
        }
        $integrationParams = [
            'integration_type' => 'plugin',
            'integration_name' => 'spin-prestashop',
            'integration_version' => '1.0.5',
            'ecommerce_platform' => 'prestashop',
            'ecommerce_version' => defined('_PS_VERSION_') ? _PS_VERSION_ : 'unknown',
            'device_type' => 'server',
        ];
        $finalUserAgent = array_merge($existingUserAgent, $integrationParams);
        $cfg->setXDigitalFemsaUserAgent(json_encode($finalUserAgent));

        $newWebhook = Tools::safeOutput(Tools::getValue(self::webhookSetting));
        Configuration::deleteByName(self::webhookErrorSetting);

        if ($oldWebhook === $newWebhook) {
            return true;
        }

        $failedAttempts = (int) Configuration::get(self::webhookAttemptsSetting);
        $failedWebhook = Configuration::get(self::webhookFailedUrlSetting);

        if ($newWebhook === $failedWebhook && $failedAttempts >= self::MaxFailedAttempts) {
            Configuration::updateValue(
                self::webhookErrorSetting,
                'Webhook register was fail some times, try changing webhook!'
            );
            Configuration::deleteByName(self::webhookAttemptsSetting);

            return false;
        }

        if ($failedAttempts < self::MaxFailedAttempts) {
            try {
                $api = new WebhooksApi(null, $cfg);

                // list existing webhooks
                $list = $api->getWebhooks($isoCode ?: 'es');
                $data = method_exists($list, 'getData') ? $list->getData() : [];

                $isWebhooksRegistered = array_filter((array) $data, function ($webhook) use ($newWebhook) {
                    // WebhookResponse::getUrl()
                    return method_exists($webhook, 'getUrl') ? $webhook->getUrl() === $newWebhook : false;
                });

                if (count($isWebhooksRegistered) <= 0) {
                    // environment is inferred from the API key (test/live), events configured server-side
                    $request = new WebhookRequest([
                        'url' => $newWebhook,
                        'synchronous' => false,
                    ]);
                    $api->createWebhook($request, $isoCode ?: 'es');

                    Configuration::updateValue(self::webhookSetting, $newWebhook);

                    // delete error variables
                    Configuration::deleteByName(self::webhookAttemptsSetting);
                    Configuration::deleteByName(self::webhookFailedUrlSetting);
                    Configuration::deleteByName(self::webhookErrorSetting);
                }

                return true;
            } catch (\Throwable $e) {
                ++$failedAttempts;
                Configuration::updateValue(self::webhookErrorSetting, $e->getMessage());
                Configuration::updateValue(self::webhookAttemptsSetting, $failedAttempts);
                Configuration::updateValue(self::webhookFailedUrlSetting, $newWebhook);

                return false;
            }
        }

        return true;
    }
}
