<?php

namespace modules\registration;

use Craft;
use yii\base\Module;

class RegistrationModule extends Module
{
    public static self $instance;

    public $controllerNamespace = 'modules\registration\controllers';

    public function __construct($id, $parent = null, array $config = [])
    {
        Craft::setAlias('@modules/registration', $this->getBasePath());
        static::setInstance($this);

        parent::__construct($id, $parent, $config);
    }

    public function init(): void
    {
        parent::init();

        self::$instance = $this;

    }
}
