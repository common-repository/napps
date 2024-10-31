<?php

namespace NAPPS;

use NAPPS\Utils\SingletonTrait;
use NAPPS\Controllers\AuthController;
use NAPPS\Controllers\HealthController;
use NAPPS\Controllers\ProxyController;
use NAPPS\Controllers\SmartBannerController;
use NAPPS\Controllers\WooCommerceController;

class Router {

    use SingletonTrait;
    
    /**
     * Init router controllers using rest api init event
     *
     * @return void
     */
    public function init() {
       
        add_action( 'init', array($this, 'register_web_routes' ));
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

    }
        
    /**
     * Register web routes controllers
     *
     * @return void
     */
    public function register_web_routes() {
        tap(new ProxyController(), function($controller) {
            $controller->registerRoutes();
        });
    }

    /**
     * Register plugin controllers for rest api
     *
     * @return void
     */
    public function register_rest_routes() {
        tap(new HealthController(), function($controller) {
            $controller->registerRoutes();
        });

        tap(new AuthController(), function($controller) {
            $controller->registerRoutes();
        });

        tap(new SmartBannerController(), function($controller) {
            $controller->registerRoutes();
        });

        tap(new WooCommerceController(), function($controller) {
            $controller->registerRoutes();
        });
    }

}