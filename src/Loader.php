<?php

namespace NAPPS;

use NAPPS\I18n;
use NAPPS\Router;
use NAPPS\Modules\InitModules;
use NAPPS\ActivationService;

class Loader {

    protected static $loaded = false;
    public static function init() {

        if(!static::$loaded) {
            I18n::instance()->init();
            ActivationService::instance()->init();
            Router::instance()->init();
        }

        InitModules::instance()->init();

        static::$loaded = true;
    }


}