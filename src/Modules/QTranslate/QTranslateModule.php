<?php

namespace NAPPS\Modules\QTranslate;

use NAPPS\Contracts\ModuleImplementation;

class QTranslateModule implements ModuleImplementation
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

		if (is_admin() || napps_doing_cron()) {
			add_action('woocommerce_deliver_webhook_async', array($this, 'woocommerce_deliver_webhook_async'), 10, 2);
		}
	}

	public function woocommerce_rest_shop_coupon($response, $item, $request)
	{
		$context = !empty($request['context']) ? $request['context'] : 'view';
		if ($context == 'view') {
			$data = $response->get_data();
			$data['code'] = qtranxf_useDefaultLanguage($data['code']);
			$response->set_data($data);
		}
		return $response;
	}

	/*
	*	Override order line item meta data so we can have correct attribute values
	*/
	public function woocommerce_rest_prepare_order_object($response, $order, $request)
	{
		$context = !empty($request['context']) ? $request['context'] : 'view';
		if ($context != 'view') {
			return $response;
		}

		$data = $response->get_data();
		if (array_key_exists('line_items', $data)) {
			$lineItems = &$data['line_items'];

			// Loop line items
			foreach ($data['line_items'] as $key => $item) {

				if (!array_key_exists('meta_data', $item)) {
					continue;
				}

				// Loop line item metaData so we can translate attribute options
				foreach ($item['meta_data'] as $metaDataKey => $metaData) {
					$lineItems[$key]['meta_data'][$metaDataKey]['display_value'] = qtranxf_useDefaultLanguage($metaData['display_value']);
					$lineItems[$key]['meta_data'][$metaDataKey]['display_key'] = qtranxf_useDefaultLanguage($metaData['display_key']);
				}

				if (!array_key_exists('parent_name', $item)) {
					continue;
				}

				$lineItems[$key]['parent_name'] = qtranxf_useDefaultLanguage($item['parent_name']);
			}
		}

		$response->set_data($data);

		return $response;
	}

	public function woocommerce_rest_product_object($response, $item, $request)
	{
		$context = !empty($request['context']) ? $request['context'] : 'view';
		if ($context != 'view') {
			return $response;
		}

		$data = $response->get_data();
		if (array_key_exists('attributes', $data)) {
			$attributes = &$data['attributes'];

			foreach ($data['attributes'] as $key => $attribute) {
				$attributes[$key]['name'] = qtranxf_useDefaultLanguage($attribute['name']);

				if (array_key_exists('options', $attribute)) {
					foreach ($attribute['options'] as $keyOption => $option) {
						$attributes[$key]['options'][$keyOption] = qtranxf_useDefaultLanguage($option);
					}
				} else if (array_key_exists('option', $attribute)) {
					$attributes[$key]['option'] = qtranxf_useDefaultLanguage($attribute['option']);
				}
			}
		}

		$data['name'] = qtranxf_useDefaultLanguage($data['name']);
		$data['description'] = qtranxf_useDefaultLanguage($data['description']);
		$data['short_description'] = qtranxf_useDefaultLanguage($data['short_description']);

		$response->set_data($data);

		return $response;
	}

	/**
	*	Override attribute (option) name on rest woocommerce api /products/attributes/{id}
	* 
	* @param \WP_REST_Response  $response  The response object.
	* @param object            $item      The original attribute object.
	* @param \WP_REST_Request   $request   Request used to generate the response.
	*/
	public function woocommerce_rest_product_attribute($response, $item, $request)
	{
		$context = !empty($request['context']) ? $request['context'] : 'view';
		if ($context == 'view') {
			$data = $response->get_data();
			$data['name'] = qtranxf_useDefaultLanguage($data['name']);
			$response->set_data($data);
		}
		return $response;
	}

	/**
	*	Apply filters before rest api
	*	so we can get the correct field based on the default language
	*/
	public function on_rest_api_init()
	{
		if ($this->isQTranslateActive()) {

			add_filter('woocommerce_rest_prepare_product_object', array($this, 'woocommerce_rest_product_object'), 10, 3);
			add_filter('woocommerce_rest_prepare_shop_order_object', array($this, 'woocommerce_rest_prepare_order_object'), 10, 3);
			add_filter('woocommerce_rest_prepare_product_attribute', array($this, 'woocommerce_rest_product_attribute'), 10, 3);
			add_filter('woocommerce_rest_prepare_shop_coupon_object', array($this, 'woocommerce_rest_shop_coupon'), 10, 3);
		}
	}

	
	/**
	*   On rest api will check if qtranste is active
	*	If soo, apply necessary filters to retrieve correct information
	*/
	public function woocommerce_deliver_webhook_async($webhook_id, $arg)
	{
		$this->on_rest_api_init();
	}

	/**
	*	Is qTranslate active
	*	We dont support multi language in our system
	*	Lets ask for default language on each webhook
	*
	*	@return bool
	*/
	protected function isQTranslateActive()
	{
		return function_exists("qtranxf_add_filters") && function_exists("qtranxf_useDefaultLanguage");
	}
}
