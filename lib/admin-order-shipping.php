<?php
// after order place store custom status 
    add_action('woocommerce_thankyou', 'enroll_student', 10, 1);
    function enroll_student( $order_id ) {
        if ( ! $order_id )
            return;

        // Allow code execution only once 
        if( ! get_post_meta( $order_id, '_thankyou_action_done', true ) ) {

            // Get an instance of the WC_Order object
            $order = wc_get_order( $order_id );

            update_post_meta($order_id, '_logistics_status', 'pending'); // add and save the custom field
            update_post_meta($order_id, '_source_api_code', '4'); // add and save the custom field
            
            // Flag the action as done (to avoid repetitions on reload for example)
            $order->update_meta_data( '_thankyou_action_done', true );
            $order->save();
        }
    }

//  admin orders show custom logistics columns

    // ADDING 2 NEW COLUMNS WITH THEIR TITLES (keeping "Total" and "Actions" columns at the end)
    add_filter( 'manage_edit-shop_order_columns', 'custom_shop_order_column', 20 );
    function custom_shop_order_column($columns)
    {
        $reordered_columns = array();

        // Inserting columns to a specific location
        foreach( $columns as $key => $column){
            $reordered_columns[$key] = $column;
            if( $key ==  'order_status' ){
                // Inserting after "Status" column
                $reordered_columns['logistics-status'] = __( 'Logistics Status','theme_domain');
            }
        }
        return $reordered_columns;
    }
	
	
	    // Adding custom fields meta data for each new column (example)
    add_action( 'manage_shop_order_posts_custom_column' , 'custom_orders_list_column_content', 20, 2 );
    function custom_orders_list_column_content( $column, $post_id )
    {
        switch ( $column )
        {
            case 'logistics-status' :
                // Get custom post meta data
                $logistics_status = get_post_meta( $post_id, '_logistics_status', true );
                if(!empty($logistics_status) && $logistics_status == 'processing')
                    echo '<button type="button" class="button action generate-label" data-order-id="'.$post_id.'">Generate Label</button><p id="response_label_'.$post_id.'"></p>';
                else
                    echo '<mark class="order-status status-pending"><span>Pending</span></mark>';
                break;
        }
    }

// admin side jquery code
function my_enqueue($hook) {
    // Only add to the edit.php admin page.
    // See WP docs.
    if ('edit.php' !== $hook) {
        return;
    }
    //wp_enqueue_script('my_custom_script_jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js', array('jquery'));

   //wp_register_script('ajaxHandle', plugin_dir_url(__FILE__) . '/js/myscript.js', array(), false,  true   );
    //wp_enqueue_script( 'ajaxHandle' );
   // wp_localize_script('ajaxHandle', 'ajax_object', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
    
}
add_action('admin_enqueue_scripts', 'my_enqueue');


// generate shipping label api
add_action( "wp_ajax_update_shipping_rate", "so_wp_ajax_function2" );
add_action( "wp_ajax_nopriv_update_shipping_rate", "so_wp_ajax_function2" );
function so_wp_ajax_function2(){
    global $wpdb; // this is how you get access to the database

    $order_id = intval($_POST['order']);
    $logistics_name = $_POST['logistics'];
    $shipping_rate = $_POST['rate'];

    $old_total_cost = get_post_meta( $order_id, '_order_total', true );
    $old_shipping_cost = get_post_meta( $order_id, '_order_shipping', true );

    $update_total_cost = ($old_total_cost - $old_shipping_cost) + $shipping_rate;

    
    $tbl_woo_order_items = $wpdb->prefix . 'woocommerce_order_items';
    $tbl_woo_order_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

    $woocommerce_item_result = $wpdb->get_row("SELECT * FROM $tbl_woo_order_items WHERE order_item_type='shipping' AND order_id = $order_id");
    // echo json_encode($woocommerce_item_result);

    // update logistics name 
    $wpdb->query($wpdb->prepare("UPDATE $tbl_woo_order_items SET order_item_name='$logistics_name' WHERE order_item_type='shipping' AND order_id = $order_id"));
   
    // update logistics cost 
    $wpdb->query($wpdb->prepare("UPDATE $tbl_woo_order_itemmeta SET meta_value='$shipping_rate' WHERE meta_key='cost' AND order_item_id = $woocommerce_item_result->order_item_id"));

    // update order total and shipping cost
    update_post_meta($order_id, '_order_shipping', $shipping_rate);
    update_post_meta($order_id, '_order_total', $update_total_cost);

    echo "success";
 
  wp_die(); // ajax call must die to avoid trailing 0 in your response
}






// order preview page

add_action( 'woocommerce_before_order_itemmeta', 'so_32457241_before_order_itemmeta', 10, 3 );
function so_32457241_before_order_itemmeta( $item_id, $item, $_product ){

    global $wpdb;

    $getShippingData = json_decode($item,true);

    $_logistics_status = get_post_meta( $_GET['post'], '_logistics_status', true );

    if(isset($getShippingData['method_title']) && $_logistics_status == 'pending'){
        
        $order_id = $_GET['post'];
        $order = new WC_Order($order_id); // Order id

        $store_postcode    = get_option( 'woocommerce_store_postcode' );
        $to_pincode   = $order->get_shipping_postcode();
        $payment_mode = $order->get_payment_method();
        $secret_key = ithink_secret_key;
        $access_token = ithink_access_token;

        $orderData = wc_get_order( $order_id );

        $old_shipping_cost = get_post_meta( $order_id, '_order_shipping', true );

        $shipping_weight = 0;
        $fd = 0;

        echo '<h3>Update Shipping</h3>';

        $tbl_woo_order_items = $wpdb->prefix . 'woocommerce_order_items';

        $woocommerce_item_result = $wpdb->get_row("SELECT * FROM $tbl_woo_order_items WHERE order_item_type='shipping' AND order_id = $order_id");

        // foreach( $orderData->get_items() as $item_id => $item ){
        foreach ( $orderData->get_items() as $item_id => $item ) {

            $_product = $item->get_product();

            $item_quantity  = $item->get_quantity(); // Get the item quantity
  
            // Now you have access to (see above)...
          
            //    echo $product->get_type();
            //    echo $product->get_length();exit;

           $shipping_weight = $shipping_weight + $_product->get_weight() *  $item->get_quantity();
            $totaldimension = $_product->get_length() + $_product->get_width() + $_product->get_height();
            if ($totaldimension > $fd)
            {
                $fd = $totaldimension;
                $shipping_length = $_product->get_length();
                $shipping_width = $_product->get_width();
                $shipping_height = $_product->get_height();
            }

            $data = "{\"data\":{\"from_pincode\":\"$store_postcode\",\"to_pincode\":\"$to_pincode\",\"shipping_length_cms\":\"$shipping_length\",\"shipping_width_cms\":\"$shipping_width\",\"shipping_height_cms\":\"$shipping_height\",\"shipping_weight_kg\":\"$shipping_weight\",\"payment_method\":\"$payment_mode\",\"order_type\":\"forward\",\"product_mrp\":\"1200.00\",\"access_token\":\"$access_token\",\"secret_key\":\"$secret_key\"}}\n";

            // print_r($data);exit;
    
            $url = 'https://manage.ithinklogistics.com/api_v3/rate/check.json';
            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8'
                ) ,
                'body' => ($data) ,
                'method' => 'POST',
                'data_format' => 'body',
            ));
            $data = json_decode($response['body']);
            
            // $shippingdata= $data->data->$to_pincode;
            $shippingdata = $data->data;
            //print_r($shippingdata);
            

            echo '<ul>';
            foreach ($shippingdata as $value)
            {
                if ($value->pickup == 'Y')
                {

                    $weight_slab = $value->weight_slab > 1 ? $value->weight_slab.' kgs' : $value->weight_slab.' kg';

                    ?>
                       
                        <li>
                            <input type="radio" data-order='<?=$order_id?>' data-logistics-full='<?=$value->logistic_name.' '.$weight_slab.' ( Delivery By '.$data->expected_delivery_date.' )'?>' id="shipping_method_0_<?=$value->logistic_name.' '.$weight_slab?>" name="shipping_update" class="shipping_update" value="<?=round($value->rate)?>" <?php echo ($woocommerce_item_result->order_item_name == $value->logistic_name.' '.$weight_slab.' ( Delivery By '.$data->expected_delivery_date.' )') ?  "checked" : "" ;  ?>>
                            <label for="shipping_method_0_<?=$value->logistic_name.' '.$weight_slab?>"><?php echo $value->logistic_name.' '.$weight_slab.' ( Delivery By '.$data->expected_delivery_date.' )'; ?>: <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol"><?php echo get_woocommerce_currency_symbol();?></span><?=round($value->rate);?></bdi></span></label>
                        </li>
                        
                    <?php

                }
            }
            echo '</ul>';

            ?>
            <script>
                jQuery(document).ready(function(){
                    jQuery('input[type=radio][name=shipping_update]').change(function() {
                        var logistics = jQuery(this).data('logistics-full');
                        var rate = jQuery(this).val();
                        var order = jQuery(this).data('order');
                        jQuery.ajax({
                            url: ajaxurl, // this is the object instantiated in wp_localize_script function
                            type: 'POST',
                            // dataType: 'json',
                            data:{ 
                                action: 'update_shipping_rate',
                                logistics: logistics,
                                rate: rate,
                                order: order
                            },
                            success: function( data ){
                                if(data == 'success'){
                                    window.location.reload();
                                }
                            },
                            error: function( err ){
                                alert('something went wrong');
                            }
                        });
                    });
                });
            </script>
            
            <?php

        }

    }

}