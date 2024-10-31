<?php

namespace NAPPS\Modules\AttributeStock;

use Exception;
use NAPPS\Contracts\ModuleImplementation;

class AttributeStockModule implements ModuleImplementation
{
	/**
	 * Setup action & filter hooks.
	 */
	public function __construct()
	{	
		$this->init_hooks();
	}

    /**
	 * Init hooks
	 *
	 * @return void
	 */
	private function init_hooks()
	{
		// Trigger webhooks when stock changes
		add_action('mewz_wcas_trigger_product_stock_change', array($this, 'on_trigger_stock_change'), 10, 1);
		
    }

	public function on_trigger_stock_change($product) {

        do_action( 'woocommerce_update_product', $product->get_id(), $product );

	}
}