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

use DigitalFemsa\Configuration as FemsaConfig;
use DigitalFemsa\Api\WebhooksApi;
use DigitalFemsa\Model\WebhookRequest;
use Configuration;
use Tools;

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
        FemsaConfig::getDefaultConfiguration()->setAccessToken($privateKey);
        $host = $digitalFemsaMode ? 'https://api.digitalfemsa.io' : 'https://api.stg.digitalfemsa.io';
        FemsaConfig::getDefaultConfiguration()->setHost($host);

        $events = ['events' => ['order.paid', 'order.expired']];

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
                $api = new WebhooksApi(null, FemsaConfig::getDefaultConfiguration());
                $request = new WebhookRequest([
                    'url' => $newWebhook,
                    'synchronous' => false,
                ]);
                $api->createWebhook($request, $isoCode ?: 'es');

                Configuration::updateValue(self::webhookSetting, $newWebhook);
                Configuration::deleteByName(self::webhookAttemptsSetting);
                Configuration::deleteByName(self::webhookFailedUrlSetting);
                Configuration::deleteByName(self::webhookErrorSetting);

                return true;
            } catch (\Exception $e) {
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
