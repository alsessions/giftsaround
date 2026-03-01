<?php

namespace modules\redeem;

use Craft;
use craft\elements\User;
use craft\events\ElementEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Elements;
use craft\web\UrlManager;
use Throwable;
use yii\base\Event;

/**
 * Redeem module
 *
 * @property-read array $cpNavItem
 */
class RedeemModule extends \yii\base\Module
{
    /**
     * @var RedeemModule
     */
    public static $instance;

    /**
     * @var string
     */
    public $controllerNamespace = 'modules\redeem\controllers';

    /**
     * @inheritdoc
     */
    public function __construct($id, $parent = null, array $config = [])
    {
        Craft::setAlias('@modules/redeem', $this->getBasePath());
        $this->controllerNamespace = 'modules\redeem\controllers';

        static::setInstance($this);

        parent::__construct($id, $parent, $config);
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$instance = $this;

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'modules\redeem\console\controllers';
        }

        // Register event listeners
        $this->_registerEventListeners();

        Craft::info(
            Craft::t('app', '{name} module loaded', [
                'name' => 'Redeem'
            ]),
            __METHOD__
        );
    }

    /**
     * @return RedeemModule
     */
    public static function getInstance(): RedeemModule
    {
        return static::$instance;
    }

    // Private Methods
    // =========================================================================

    /**
     * Register event listeners
     */
    private function _registerEventListeners(): void
    {
        // Register URL rules
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['business/redeem/<token:([a-zA-Z0-9]{32})>'] = 'redeem/default/show-redemption';
                $event->rules['admin/redemptions'] = 'redeem/default/admin-redemptions';
                $event->rules['redeem/default/clear-user-history'] = 'redeem/default/clear-user-history';
            }
        );

        // Ensure newly registered pending users receive an activation email.
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $event) {
                if (
                    !$event->isNew ||
                    !$event->element instanceof User ||
                    !Craft::$app->getRequest()->getIsSiteRequest() ||
                    $event->element->getStatus() !== User::STATUS_PENDING
                ) {
                    return;
                }

                try {
                    Craft::$app->getUsers()->sendActivationEmail($event->element);
                } catch (Throwable $e) {
                    Craft::error(
                        sprintf(
                            'Could not send activation email for user %d (%s): %s',
                            (int)$event->element->id,
                            (string)$event->element->email,
                            $e->getMessage()
                        ),
                        __METHOD__
                    );
                }
            }
        );
    }
}
