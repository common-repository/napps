<?php

namespace NAPPS\Modules;

use NAPPS\Utils\SingletonTrait;
use NAPPS\Modules\Auth\AuthModule;
use NAPPS\Modules\Admin\AdminModule;
use NAPPS\Modules\QTranslate\QTranslateModule;
use NAPPS\Modules\LinkedVariation\LinkedVariation;
use NAPPS\Modules\Smartbanner\SmartbannerModule;
use NAPPS\Modules\Woocommerce\WoocommerceModule;
use NAPPS\Modules\Coupons\ExclusiveCouponsModule;
use NAPPS\Modules\AttributeStock\AttributeStockModule;
use NAPPS\Modules\ShippingRateCart\ShippingRateCartModule;

class InitModules {

    use SingletonTrait;
    
    /**
     * Init modules
     *
     * @return void
     */
    public function init() {

        new AuthModule();
        new WoocommerceModule();
        new SmartbannerModule();
        new ExclusiveCouponsModule();
        new ShippingRateCartModule();
        new AttributeStockModule();
        new AdminModule();

        $activePlugins = apply_filters('active_plugins', get_option('active_plugins'));
        // Check if we need to include qtranslate module
		if(in_array('qtranslate-xt/qtranslate.php', $activePlugins)) { 
			new QTranslateModule();
		}

        // Check if we need to include linked variation module
		if(in_array('linked-variation-for-woocommerce/linked-variation-for-woocommerce.php', $activePlugins)) { 
			new LinkedVariation();
		}
    }

}