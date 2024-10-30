<?php
/*
 * Plugin Name: iThink Logistics eCommerce shipping in India
 * Plugin URI: 
 * Description: iThink Logistics is an AI-based logistics aggregator. We are your one-stop solution for integrating multiple courier platforms over a single dashboard. 
 * Version: 2.3
 * Author: iThink Logistics 
 * Author URI: https://ithinklogistics.com/
 * Tested up to: 6.1.1
 *
*/
 error_reporting(E_ERROR | E_PARSE);

function activate() {

   if ( version_compare( PHP_VERSION, '8.1.20', '>' ) )  
{
    exit( sprintf( 'iThink Logistics requires PHP 8.1.16 or lower. You’re on %s.', PHP_VERSION ) );
}
}
register_activation_hook(__FILE__, 'activate'); 

global $wpdb;
$ithink_logistics = $wpdb->prefix . 'ithink_logistics';
$ithink_logistics_keys = $wpdb->get_row("SELECT * FROM $ithink_logistics WHERE id = '1'");
if (!defined('ithink_access_token'))
{
    define('ithink_access_token', $ithink_logistics_keys->api_key);
}
if (!defined('ithink_secret_key'))
{
    define('ithink_secret_key', $ithink_logistics_keys->secret_key);
}


add_action('admin_menu', 'itls_ithinklogistics_menu');
function itls_ithinklogistics_menu()
{
    add_menu_page('I Think Logistics', 'I Think Logistics',  'manage_options', 'I Think Logistics', 'itl_config' );
}

define('ITHINKLOGISTICS_PLUGIN_DIR', plugin_dir_path(__FILE__));
require_once (ITHINKLOGISTICS_PLUGIN_DIR . '/lib/ithink-config.php');
require_once (ITHINKLOGISTICS_PLUGIN_DIR . '/lib/pincode-api.php');
require_once (ITHINKLOGISTICS_PLUGIN_DIR . '/lib/shippingcalculate.php');
require_once (ITHINKLOGISTICS_PLUGIN_DIR . '/lib/admin-order-shipping.php');
require_once (ITHINKLOGISTICS_PLUGIN_DIR . '/lib/free-shipping.php');
//require_once (ITHINKLOGISTICS_PLUGIN_DIR . '/lib/core-apis.php'); 

define('Q_CORE_CSS', plugins_url('/assets/css/', __FILE__));
define('Q_CORE_JS', plugins_url('/assets/js/', __FILE__));

add_action('wp_enqueue_scripts','include_script');

function include_script() {
   
  wp_localize_script( 'ajax-script', 'my_ajax_object',array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
	 wp_enqueue_script( 'pincode-js', Q_CORE_JS.'pincode.js', array('jquery'));
	
}
 add_action( 'admin_enqueue_scripts', 'load_custom_script' ); 
  function load_custom_script() {
 wp_enqueue_script('custom_admin_script', Q_CORE_JS.'/admin_script.js', array('jquery'));
  }
add_action('wp_head', 'myplugin_ajaxurl');

function myplugin_ajaxurl() {

   echo '<script type="text/javascript">
           var ajaxurl = "' . admin_url('admin-ajax.php') . '";
         </script>';
}

global $at_db_version;
$at_db_version = '1.0';
register_activation_hook(__FILE__, 'itls_install_db');
// Create Database
function itls_install_db()
{
    global $wpdb;
    global $at_db_version;
	update_option( 'freeshipping_totalvalue', '0' ); 
    $table_name = $wpdb->prefix . 'ithink_logistics';
    $charset_collate = $wpdb->get_charset_collate();
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
    $sql_create = "CREATE TABLE IF NOT EXISTS " . $table_name . " (
          id mediumint(9) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          api_key VARCHAR(128) DEFAULT '' NOT NULL,
          secret_key VARCHAR(128) DEFAULT '' NOT NULL,
          UNIQUE KEY id (id)
          );";
  $sql_insert = "INSERT INTO $table_name (`id`, `api_key`, `secret_key`) VALUES ('1', '', '')"; 
	} else {
		$store = site_url();
        $data = array(
            'data' => array(
                'error' => 'Table already Exist' ,
                 'store_url' => $store	,
                 'access_token' => '',				 
                 'api_key' => ''				 
            )
        );
    
        //$url = 'https://manage.ithinklogistics.com/api_v3/pincode/check.json';
        $url = 'https://my-app.ithinklogistics.net/vendor_plugins/woocommerce/error_log.php';
      $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8'
            ) ,
            'body' => json_encode($data) ,
            'method' => 'POST',
            'data_format' => 'body',
        ));
		echo '<pre>'; print_r($response['body']); echo '</pre>'; 
        $data = json_decode($response['body']);
	}
    require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_create);
    dbDelta($sql_insert);
    add_option('at_db_version', $at_db_version);
}


function my_plugin_remove_database()
{
    global $wpdb;
    $db_table_name = $wpdb->prefix . 'ithink_logistics';  // table name
    $sql = "DROP TABLE IF EXISTS $db_table_name";
    $rslt=$wpdb->query($sql);
}

register_deactivation_hook( __FILE__, 'my_plugin_remove_database' );



// Get total order count
function ithinklogistics_get_total_orders($params)
{

    try
    {
        global $wpdb;
        $post_table = $wpdb->prefix . "posts";
        $shipping_table = $wpdb->prefix . "postmeta";
        $item_table = $wpdb->prefix . "woocommerce_order_items";
        $item_meta_table = $wpdb->prefix . "woocommerce_order_itemmeta";
        $comments_table = $wpdb->prefix . "comments";
        $commentmeta_table = $wpdb->prefix . "commentmeta";
        $last_updated = sanitize_text_field($_POST['last_updated']);
        if ($last_updated != '')
        {
            $get_order_query = "SELECT count(*) as total_orders from $post_table where post_type='shop_order' and post_modified >= '$last_updated'";
        }
        else
        {
            $get_order_query = "SELECT count(*) as total_orders from $post_table where post_type='shop_order'";
        }
        $total_orders = 0;
        $result_get_order_query = $wpdb->get_results($get_order_query, ARRAY_A);

        if ($result_get_order_query)
        {

            $total_orders = $result_get_order_query[0]['total_orders'];
            $return = array();
            $return["html_message"] = 'Success';
            $return["status"] = "success";
            $return["total_orders"] = $total_orders;
            echo json_encode($return);
            exit();

        }
        else
        {
            $return = array();
            $return["html_message"] = 'Error';
            $return["status"] = "error";
            $return["total_orders"] = 'Unable to get total';
            echo json_encode($return);
            exit();
        }
    }
    catch(\Exception $e)
    {
        echo json_encode($e->getMessage());
        exit();
    }

}

// Get All Order List
function ithinklogistics_get_orders_list($params)
{
    try
    {
        global $wpdb;
        $post_table = $wpdb->prefix . "posts";
        $wp_options_table = $wpdb->prefix . "options";
        $shipping_table = $wpdb->prefix . "postmeta";
        $item_table = $wpdb->prefix . "woocommerce_order_items";
        $item_meta_table = $wpdb->prefix . "woocommerce_order_itemmeta";
        $comments_table = $wpdb->prefix . "comments";
        $commentmeta_table = $wpdb->prefix . "commentmeta";
        $last_updated = (isset($_POST['last_updated'])) ? sanitize_text_field($_POST['last_updated']) : '';
        $page = (isset($_POST['page'])) ? sanitize_text_field($_POST['page']) : '';
        $order_id = (isset($_POST['order_id'])) ? sanitize_text_field($_POST['order_id']) : '';
        $per_page_orders = 50;
        if ($order_id != '')
        {
            $get_order_query = "SELECT * from $post_table where post_type='shop_order' and ID = '$order_id'";
        }
        else if ($last_updated != '' && $order_id == '')
        {
            $limit = ' Limit 0,50';
            if ($page != '' && $page > 0)
            {
                $sql_offset = ($page - 1) * $per_page_orders;
                $limit = " Limit " . $sql_offset . "," . $per_page_orders;
            }
            $get_order_query = "SELECT * from $post_table where post_type='shop_order' and post_modified >= '$last_updated' $limit";
        }
        else
        {
            $limit = ' Limit 0,50';
            if ($page != '' && $page > 0)
            {
                $sql_offset = ($page - 1) * $per_page_orders;
                $limit = " Limit " . $sql_offset . "," . $per_page_orders;
            }
            $get_order_query = "SELECT * from $post_table where post_type='shop_order' $limit";
        }
        $all_order_data_array = array();
        $result_get_order_query = $wpdb->get_results($get_order_query, ARRAY_A);
		//echo '<pre>'; print_r($result_get_order_query); echo '</pre>';
        if ($result_get_order_query) 
        {        
            foreach ($result_get_order_query as $result_get_order)
            {
				
                $all_order_data_array[$result_get_order['ID']] = $result_get_order;
                $order_id = $result_get_order['ID'];
            if($order_id){
                    $order_data =  getOrderDataById($order_id);
                    $product_data = getProductData($order_id);
                    $all_order_data_array[$result_get_order['ID']]['shipping_billing_details'] = $order_data;
                    $all_order_data_array[$result_get_order['ID']]['products']= $product_data;
            }                
            }
           // $plugin_data = get_plugin_data( __FILE__ );
            $weight_dimension_array = array(
               // 'itl_version' => $plugin_data['Version'],
                'dimension_unit' =>  get_option('woocommerce_dimension_unit') ? get_option('woocommerce_dimension_unit') : '',
                'weight_unit' => get_option('woocommerce_weight_unit') ? get_option('woocommerce_weight_unit') : '',
            );

            $return = array();
            $return["html_message"] = 'Success';
            $return["status"] = "success";
            $return["all_order_data_array"] = $all_order_data_array;
            $return["other_details"] = $weight_dimension_array;
			//echo count($all_order_data_array);
			//echo $order_id;
            echo json_encode($return);
            exit();
        } else {
            $return = array();
            $return["html_message"] = 'Unable to get data';
            $return["status"] = "error";
            echo json_encode($return);
            exit();
        }

    }catch(\Exception $e) {
            echo json_encode($e->getMessage());
            exit();
    }

}

// Get order data by order id
function getOrderDataById($order_id){

    global $wpdb;
    $postmeta = $wpdb->prefix . "postmeta";
    $query_data= "SELECT * from $postmeta where post_id = '$order_id'";
    $post_datas = $wpdb->get_results($query_data, ARRAY_A);
    $shipping_details = array();

        foreach($post_datas as $post_data)
        {
            $shipping_details[$post_data['meta_key']] = $post_data['meta_value'];            
        }     

    return $shipping_details;
   
}

// Get product data by order id
function getProductData($order_id){

    global $wpdb;
    $product_data = array();
    $product_array = array();
    $order = new WC_Order($order_id);

    foreach ( $order->get_items() as $item_id => $item ) {
        
    $item_table = $wpdb->prefix . "woocommerce_order_itemmeta";
    $query_data= "SELECT * from $item_table where meta_key ='_line_tax_data' and order_item_id = '$item_id'";
    $data = $wpdb->get_results($query_data, ARRAY_A);
        $product_array['order_item_id'] = $item_id;
        $product_array['order_item_name'] = $item->get_name() ? $item->get_name() : '';
        $product_array['order_item_type'] = $item->get_type() ? $item->get_type() : ''; 
		$product = $item->get_product(); 
	   
	     $taxonomy ='pa_size'; 
	   $attribute_label_name = wc_attribute_label($taxonomy);
	 	  if( !empty($attribute_label_name)){
		$attr = $item->get_meta($taxonomy) ;
	    $product_array['order_item_attribute'] = $attribute_label_name .':'. ucfirst($attr);
		//echo '<pre>'; print_r($attribute_label_name ); echo '</pre>';
	    }
		
	    $product_array['order_id'] = $order_id;
        $product_array['_product_id'] = $item->get_product_id() ? $item->get_product_id() : 0;
        $product_array['_variation_id'] = $item->get_variation_id() ? $item->get_variation_id() : 0;
        $product_array['_qty'] = $item->get_quantity() ? $item->get_quantity() : 0;
        $product_array['_tax_class'] = $item->get_tax_class() ? $item->get_tax_class() : '';
        $product_array['_line_subtotal'] = $item->get_subtotal() ? $item->get_subtotal() : 0;
        $product_array['_line_subtotal_tax'] = $item->get_subtotal_tax() ? $item->get_subtotal_tax() : 0;
        $product_array['_line_total'] = $item->get_subtotal() ? $item->get_subtotal() : 0;
        $product_array['_line_tax'] =  $item->get_subtotal_tax() ? $item->get_subtotal_tax() : 0;
        $product_array['_line_tax_data'] = $data[0]['meta_value'] ? $data[0]['meta_value'] : '';
        if($item->get_product_id()){
            $product_detail= getProductDetail($item->get_product_id(),$item->get_variation_id());
        }       
        $product_data[$item_id] = $product_array;
        $product_data[$item_id]['product_details'] = $product_detail;
        
}

return $product_data;

}

// Get product details by order id and variation sku by variation id
function getProductDetail($product_id, $variation_id){

    global $wpdb;
    $postmeta = $wpdb->prefix . "postmeta";
    $query_data= "SELECT * from $postmeta where post_id = '$product_id'";
    $post_datas = $wpdb->get_results($query_data, ARRAY_A);
    $current_variation_id = $variation_id ? $variation_id : 0;

    $product_detail_data = array();

    foreach($post_datas as $post_data)
    {
        $product_detail_data[$post_data['meta_key']] = $post_data['meta_value']; 
        $product_detail_data['_variation_sku'] = get_post_meta($current_variation_id, '_sku', true); 
    }

return $product_detail_data;

}

// Add shipment api
function ithinklogistics_fulfill_orders($params)
{

    try
    {
        global $wpdb;
        $post_table = $wpdb->prefix . "posts";
        $shipping_table = $wpdb->prefix . "postmeta";
        $item_table = $wpdb->prefix . "woocommerce_order_items";
        $item_meta_table = $wpdb->prefix . "woocommerce_order_itemmeta";
        $comments_table = $wpdb->prefix . "comments";
        $commentmeta_table = $wpdb->prefix . "commentmeta";
        $order_update = sanitize_text_field($_POST['order_update']);
        if ($order_update == 1)
        {
            $order_id = sanitize_text_field($_POST['order_id']);
            $order_status_id = sanitize_text_field($_POST['order_status_id']);
            $comment = sanitize_text_field($_POST['comment']);
            $notify = sanitize_text_field($_POST['notify']);
            $override = sanitize_text_field($_POST['override']);
            $current_date = date('Y-m-d H:i:s');
            if (!$comment || !$notify || !$override || !$order_status_id || !$order_id) {
                $return = array();
                $return["html_message"] = 'Please check comment, notify, override, order_status_id, order_id.';
                $return["status"] = "error";
                echo json_encode($return);
                exit();
            }

            if (wc_get_order($order_id) != '')
            {
                $insert_comment_query = "INSERT INTO $comments_table (comment_post_ID, comment_author, comment_date, comment_date_gmt, comment_content, comment_karma, comment_approved,comment_agent, comment_type)
                                           VALUES  ('$order_id', 'WooCommerce', '$current_date', '$current_date', '$comment', '0', '1','WooCommerce','order_note')";
                $result_insert_comment_query = $wpdb->query($insert_comment_query);
                if ($result_insert_comment_query)
                {
                    $insert_comment_id = $wpdb->insert_id;
                    $insert_commentmeta_query = "INSERT INTO $commentmeta_table (comment_id, meta_key, meta_value) VALUES  ('$insert_comment_id', 'is_customer_note', '1')";
                    $result_insert_commentmeta_query = $wpdb->query($insert_commentmeta_query);
                    $update_post_query = "UPDATE $post_table set post_status='$order_status_id',comment_count = comment_count+1 where ID='$order_id'";
                    $result_update_post_query = $wpdb->query($update_post_query);
                    $email_argv = array();
                    $email_argv['order_id'] = $order_id;
                    $email_argv['customer_note'] = $comment;
                    $email_oc = new WC_Email_Customer_Note();
                    $email_oc->trigger($email_argv);
                    $return = array();
                    $return["html_message"] = 'Order updated successfully.';
                    $return["status"] = "success";
                    echo json_encode($return);
                    exit();
                }
                else
                {
                    $return = array();
                    $return["html_message"] = 'Error updating order information.';
                    $return["status"] = "error";
                    echo json_encode($return);
                    exit();
                }
            }
            else
            {
                $return = array();
                $return["html_message"] = 'Error Order id does not exist.';
                $return["status"] = "error";
                echo json_encode($return);
                exit();
            }
        }
        else
        {
            
            $return = array();
            $return["html_message"] = 'Unable to update order information, please check order update.';
            $return["status"] = "error";
            echo json_encode($return);
            exit();
        }
    }
    catch(\Exception $e) {
            echo json_encode($e->getMessage());
            exit();
    }
}

// Verify Access key and secret key
function ithinklogistics_get_total_orders_permission()
{
    global $wpdb;
    $ithink_logistics_table = $wpdb->prefix . "ithink_logistics";
    $get_token_query = "SELECT * from $ithink_logistics_table where id='1'";
    $result_get_token_query = $wpdb->get_results($get_token_query, ARRAY_A);
    $db_access_token = $result_get_token_query[0]['api_key'];
    $db_secret_key = $result_get_token_query[0]['secret_key'];
    if (count($result_get_token_query) > 0)
    {
		
        $access_token = sanitize_text_field($_POST['access_token']);
        $secret_key = sanitize_text_field($_POST['secret_key']);
        if ($db_access_token == $access_token && $db_secret_key = $secret_key)
        {
            return true;
        }
        else
        {
            $return = array();
            $return["message"] = 'Access key & secret key does not match.';
            $return["status"] = "error";
            echo json_encode($return);
            exit();
        }
    }
    else
    {
        $return = array();
        $return["html_message"] = 'Access key & secret key does not exist.';
        $return["status"] = "error";
        echo json_encode($return);
        exit();
    }
}

add_action('rest_api_init', 'itls_orders_counts');

// Call count api
function itls_orders_counts()
{
    register_rest_route('ithinklogistics/orders', 'count', array(
        'methods' => 'POST',
        'callback' => 'ithinklogistics_get_total_orders',
        'permission_callback' => 'ithinklogistics_get_total_orders_permission'
    ));
};

add_action('rest_api_init', 'itls_orders_list');

// Call list api
function itls_orders_list()
{
    register_rest_route('ithinklogistics/orders', 'list', array(
        'methods' => 'POST',
        'callback' => 'ithinklogistics_get_orders_list',
        'permission_callback' => 'ithinklogistics_get_total_orders_permission'
    ));
};

add_action('rest_api_init', 'itls_order_fulfill');

// Call fulfill api
function itls_order_fulfill()
{
    register_rest_route('ithinklogistics/orders', 'fulfill', array(
        'methods' => 'POST',
        'callback' => 'ithinklogistics_fulfill_orders',
        'permission_callback' => 'ithinklogistics_get_total_orders_permission'
    ));
};
?>