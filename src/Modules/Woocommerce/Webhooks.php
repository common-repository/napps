<?php

namespace NAPPS\Modules\Woocommerce;
use Automattic\WooCommerce\Utilities\RestApiUtil;

class Webhooks
{

    public function __construct()
    {
        //Filter to add new topics / resources / hooks to woocommerce
        add_filter('woocommerce_webhook_topics', array($this, 'add_new_webhook_topics'));
        add_filter('woocommerce_valid_webhook_resources', array($this, 'add_new_resource'));
        add_filter('woocommerce_webhook_topic_hooks', array($this, 'add_new_topic_hooks'));
        add_filter('woocommerce_webhook_payload', array($this, 'woocommerce_webhook_payload'), 10, 4);

        add_action( 'rest_api_init', array( $this, 'on_rest_api_init' ));


        if (is_admin()) {

            // Hooks for created, edited and deleted terms
            add_action('created_term', array( $this, 'on_attribute_created' ), 15, 3 );
            add_action('delete_term', array($this, 'on_deleted_term'), 10, 5);
            add_action('edited_terms', array($this, 'on_attribute_field_edit'), 15, 2);

            // Hooks for added and deleted meta for terms
            add_action('added_term_meta', array($this, 'on_attribute_field'), 10, 4);
            add_action('deleted_term_meta', array($this, 'on_attribute_field_delete'), 10, 4);

            // Hooks for categories
            add_action('created_product_cat', array($this, 'created_product_cat'), 10, 2);
            add_action('edited_product_cat', array($this, 'saved_product_cat'), 10, 2);
            add_action('delete_product_cat', array($this, 'delete_product_cat'), 10, 4);

            add_action('delete_product_shipping_class', array($this, 'delete_shipping_class'), 10, 4);

            add_action('woocommerce_init', array($this, 'woocommerce_init'), 10, 0);
        }

    }

    public function on_rest_api_init() {
        add_filter( 'woocommerce_rest_prepare_product_variation_object', array( $this, 'woocommerce_rest_product_object' ), 20, 3);
    }

    /**
    *	Additional information returned on rest api about product information
    *
    *	Send information about shipping class and tax class (if is equal to parent)
    */
    public function woocommerce_rest_product_object($response, $item, $request) {
        $context = ! empty( $request['context'] ) ? $request['context'] : 'view';
        if($context != 'view'){
            return $response;
        }

        $data = $response->get_data();
        if(!array_key_exists('id', $data) || !$item) {
            return $response;
        }

        $parentId = $item->get_parent_id();
        if($parentId != 0) {
            $data["shipping_class_from_parent"] = empty(wp_get_post_terms( $data["id"], 'product_shipping_class' )) ? true : false; 
            $data["tax_class_from_parent"] = get_post_meta( $data["id"], '_tax_class', true ) == "parent" ? true : false; 
        }

        $response->set_data($data);
        return $response;
    }

    /**
    *	Return resource_id changed in woocommerce_webhook_process_delivery_new_topics function
    */
    public function woocommerce_webhook_payload($payload, $resource, $resource_id, $this_id)
    {
        if (!class_exists("WC_Webhook") || !class_exists("WC_Shipping_Zones")) {
            return $payload;
        }

        if ($resource == "category") {
            return $resource_id;
        } else if ($resource == "action") {
            $webhook = (new \WC_Webhook($this_id));

            switch ($webhook->get_event()) {
                //Fetch General or Tax settings from api
                case "woocommerce_settings_saved":
                    return $resource_id;
                //Get Data from shippingZone
                case "woocommerce_after_shipping_zone_object_save":
                    return $resource_id;
                case "woocommerce_delete_shipping_zone":
                    return $resource_id;
                case "woocommerce_shipping_zone_method_deleted":
                    return $resource_id;
                //Fetch Tax from api
                case "woocommerce_tax_rate_added":
                    return $resource_id;
                case "woocommerce_tax_rate_updated":
                    return $resource_id;
                //Fetch ShippingZone from api
                case "woocommerce_shipping_zone_method_saved":
                case "woocommerce_shipping_zone_method_added":
                    $zone = \WC_Shipping_Zones::get_zone_by('instance_id', $resource_id);
                    $zoneId = $zone->get_id();
                    $arg = array(
                        "zone_id" => $zoneId,
                        "method_id" => $resource_id
                    );
                    return $arg;
                case "woocommerce_attribute_added":
                case "woocommerce_attribute_updated":
                case "woocommerce_attribute_deleted":
                    return $resource_id;
            }
        } else if ($resource == "product") {

            // If is not a parent product
            // Add aditional payload needed for webhooks, we remove the need to fetch the shop again
            if ($payload["parent_id"] != 0) {
                $webhook = (new \WC_Webhook($this_id));
                $payload["parent_product"] = $webhook->build_payload($payload["parent_id"]);
                $payload["shipping_class_from_parent"] = empty(wp_get_post_terms($payload["id"], 'product_shipping_class')) ? true : false;
                $payload["tax_class_from_parent"] = get_post_meta($payload["id"], '_tax_class', true) == "parent" ? true : false;
            }
        }
        return $payload;
    }

    /**
    *   Hook for when a tax rate is updated
    */
    public function on_tax_rate_postalcode_updated($value, $expiration, $transient) {

        if(array_key_exists("changes", $_POST)) {
            $changes = $_POST["changes"];

            foreach($changes as $taxRateUpdated) {
                if(array_key_exists("tax_rate_id", $taxRateUpdated)) {
                    do_action( 'woocommerce_tax_rate_updated', sanitize_text_field($taxRateUpdated["tax_rate_id"]), null );
                }
            }
        }
        
    }
    
    /**
     * Hook for when a shipping zone method was updated
     */
    public function on_shipping_zone_method_update($option, $old_value) {
        $this->on_shipping_zone_method_save($option, $old_value, null);
    }

    /**
     * Hook for when a shipping zone method was saved
     */
    public function on_shipping_zone_method_save($option, $old_value, $value ) {
        if($_POST != null && array_key_exists("instance_id", $_POST)) {
            $zoneChanged = sanitize_text_field($_POST["instance_id"]);
            do_action( 'woocommerce_shipping_zone_method_saved', $zoneChanged );
        }
    }

    /**
    *	Woocommerce init event
    *   Only called on admin pages
    */
    public function woocommerce_init()
    {

        //Trigger for when tax rate (postalcode) is updated
        if (array_key_exists("action", $_GET) && $_GET["action"] == 'woocommerce_tax_rates_save_changes') {
            add_action("set_transient_shipping-transient-version", array($this, 'on_tax_rate_postalcode_updated'), 10, 3);
        }

        if (!class_exists("WC_Shipping_Zones")) {
            return;
        }

        // Get all shipping zones
        $zones = \WC_Shipping_Zones::get_zones();
        
        $restOfTheWorld = new \WC_Shipping_Zone(0);
        $restOfTheWorldZone = $restOfTheWorld->get_data();
        $restOfTheWorldZone['zone_id'] = $restOfTheWorld->get_id();
        $restOfTheWorldZone['formatted_zone_location'] = $restOfTheWorld->get_formatted_location();
        $restOfTheWorldZone['shipping_methods']        = $restOfTheWorld->get_shipping_methods(false, 'admin');
        array_push($zones, $restOfTheWorldZone);

        // woocommerce_flat_rate_9_settings
        // add hook for when a shipping zone method is updated, so we can trigger a webhook
        foreach ($zones as $zone) {
            foreach ($zone["shipping_methods"] as $sm) {
                add_action("update_option_woocommerce_{$sm->id}_{$sm->instance_id}_settings", array($this, 'on_shipping_zone_method_save'), 10, 3);

                //Needed when we create a shipping method and edit the name of it
                add_action("add_option_woocommerce_{$sm->id}_{$sm->instance_id}_settings", array($this, 'on_shipping_zone_method_update'), 10, 2);
            }
        }
    }

    /**
    *	Show new option on select from webhook page
    */
    public function add_new_webhook_topics($topics)
    {

        // New topic array to add to the list, must match hooks being created.
        $new_topics = array(
            'category.created' => __('Category Created', 'woocommerce'),
            'category.updated' => __('Category Updated', 'woocommerce'),
            'category.deleted' => __('Category Deleted', 'woocommerce'),
        );

        return array_merge($topics, $new_topics);
    }


    /**
    *	Add new Resource to woocommerce webhook list
    */
    public function add_new_resource($topic_events)
    {

        // New events to be used for resources.
        $new_events = array(
            'category',
            'shipping_zone_method'
        );

        return array_merge($topic_events, $new_events);
    }

    /**
    *	Add new hook to the topic
    */
    public function add_new_topic_hooks($topic_hooks)
    {

        // Array that has the topic as resource.event with arrays of actions that call that topic.
        $new_hooks = array(
            'category.created' => array(
                'category_created',
            ),
            'category.updated' => array(
                'category_updated',
            ),
            'category.deleted' => array(
                'category_deleted',
            ),
            'action.updated' => array(
                'shipping_zone_method_saved',
                'stock_alert'
            ),
            'product.deleted' => array(
                'woocommerce_delete_product',
                'wp_trash_post'
            )
        );

        return array_merge($topic_hooks, $new_hooks);
    }

    /**
    *	On new attribute field
    */
    public function on_attribute_field($mid, $object_id, $meta_key, $_meta_value)
    {
        if (!strpos($meta_key, "order_pa_") === 0)
        {
            return;
        }

        $name = str_replace("order_pa_", "", $meta_key);
        $id = wc_attribute_taxonomy_id_by_name($name);
        if ($id == 0)
        {
            return;
        }

        do_action('woocommerce_attribute_field_added', $id, []);
    }
    
    /**
     *  Fired when a term is edited
     */
    public function on_attribute_field_edit($term_id, $taxonomy)
    {
        // If a brand is edited, broadcast as a category update
        if ( 'pwb-brand' == $taxonomy ) {
            clean_term_cache($term_id, $taxonomy);
			$brand = $this->getDataForBrand($term_id);
            if(!$brand) {
                return;
            }
            
            do_action('category_updated', $brand);
            return;
		}

        $id = wc_attribute_taxonomy_id_by_name($taxonomy);
        if ($id == 0)
        {
            return;
        }

        do_action('woocommerce_attribute_field_updated', $id, []);
    }

    /**
    *	On new attribute delete
    */
    public function on_attribute_field_delete($mid, $object_id, $meta_key, $_meta_value)
    {
        if (!strpos($meta_key, "order_pa_") === 0)
        {
            return;
        }

        $name = str_replace("order_pa_", "", $meta_key);
        $id = wc_attribute_taxonomy_id_by_name($name);
        if ($id == 0)
        {
            return;
        }

        do_action('woocommerce_attribute_field_deleted', $id, []);
    }

    /**
    *   On a term is deleted (category, brand)
    *   Because we already have a hook for when a category is deleted
    *   We only want to fire a category delete when brand term is removed
    */
    public function on_deleted_term($term_id, $taxonomy_id, $taxonomy, $deleted_term, $ids) {
        if ( 'pwb-brand' != $taxonomy ) {
			return;
		}

        do_action('category_deleted', $term_id);
    }

    /**
    *   Check if term created is brand
    *   If so, report as a category
    */
    public function on_attribute_created( $term_id, $tt_id, $taxonomy )
    {
        if ( 'pwb-brand' != $taxonomy ) {
			return;
		}

        $brand = $this->getDataForBrand($term_id);
        if(!$brand) {
            return;
        }
        
        do_action('category_updated', $brand);
    }

    // Get payload for brand
    public function getDataForBrand($term_id) {
        $term = get_term_by( 'term_taxonomy_id', $term_id, 'pwb-brand' );
        if(!$term ) {
            return null;
        }

        $brand = [];
        $brand["id"] = $term_id;
        $brand["name"] = $term->name;
        $brand["slug"] = $term->slug;
        return $brand;
    }

    // Get payload for category
    public function getDataForCategory($categoryId) {
        $data = wc_get_container()->get( RestApiUtil::class )->get_endpoint_data( "/wc/v3/products/categories/{$categoryId}" );
        if(!is_array($data)) {
            return $categoryId;
        }

        unset($data['yoast_head']);
        unset($data['yoast_head_json']);

        // If args for webhooks is higher than 8000 chars, it will fail because there is a maxlength for woocommerce
        $encoded = json_encode($data);
        if($encoded && strlen($encoded) > 8000) {
            return $categoryId;
        }

        return $data;
    }

    /**
     * Hook for a category update
     */
    public function saved_product_cat($term_id, $tt_id)
    {
        do_action('category_updated', $this->getDataForCategory($term_id));
    }

    /**
     * Hook for a category created
     */
    public function created_product_cat($term_id, $tt_id)
    {
        do_action('category_created', $this->getDataForCategory($term_id));
    }

    /**
     * Hook for a category deleted
     */
    public function delete_product_cat($term, $tt_id, $deleted_term, $object_ids)
    {
        do_action('category_deleted', $term);
    }

    /**
     * Hook for shipping class deleted
     */
    public function delete_shipping_class($term, $tt_id, $deleted_term, $object_ids)
    {
        do_action('woocommerce_shipping_classes_deleted');
    }
}
