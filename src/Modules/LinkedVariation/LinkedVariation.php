<?php

namespace NAPPS\Modules\LinkedVariation;

use NAPPS\Contracts\ModuleImplementation;

class LinkedVariation implements ModuleImplementation
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
		add_action('rest_api_init', array($this, 'on_rest_api_init'));
		
        
		// Set a lower priority in order to make sure we add the filter to override product_object
        add_action('woocommerce_deliver_webhook_async', array($this, 'woocommerce_deliver_webhook_async'), 5, 2);
	}

	/**
	 * Override values on rest api call
	 *
	 * @return void
	 */ 
	public function on_rest_api_init()
	{
		$this->setUpFilterFoProductRelatiedIds();
	}

	/**
	 * Override values on webhook payload
	 *
	 * @return void
	 */
	public function woocommerce_deliver_webhook_async($webhook_id, $arg)
	{
		$this->setUpFilterFoProductRelatiedIds();
	}

	/**
	*	Add filter when on product object
	*/
	public function setUpFilterFoProductRelatiedIds() 
	{
		add_filter('woocommerce_rest_prepare_product_object', array($this, 'woocommerce_rest_product_object'), 10, 3);
	}

    /**
    *   Override related ids with their linked variation products
    */
    public function woocommerce_rest_product_object($response, $item, $request)
	{
		$context = !empty($request['context']) ? $request['context'] : 'view';
		if ($context != 'view') {
			return $response;
		}

		$data = $response->get_data();

		// Its not a parent product update, we dont need to override related ids
		if($data['parent_id'] != 0) {
			return $response;
		}

        // Get linked variation
        $linked_variation_id = get_post_meta($data['id'], 'linked_variation_id', true);
	
		// We dont have variations linked for this product
        if (!$linked_variation_id) {
            return $response;
        }

        // Get Products linked
        $linked_variation_products = get_post_meta($linked_variation_id, 'linked_variation_products', true);
		if(is_array($linked_variation_products)) {

			$keyOfCurrentProductId = array_search($data['id'], $linked_variation_products);
			if($keyOfCurrentProductId) {
				unset($linked_variation_products[$keyOfCurrentProductId]);
			}
			
			$data['related_ids'] = array_values($linked_variation_products);
			$response->set_data($data);
		}

		return $response;
	}

}
