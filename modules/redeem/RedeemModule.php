<?php

namespace modules\redeem;

use Craft;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
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
    }
}
