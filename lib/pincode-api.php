<?php
if (!function_exists('ithk_addtocartbutton')) { 
function ithk_addtocartbutton($content)
{
    $ithink_pincode_setting = get_option( 'ithink_pincode_setting' );
    if (is_single() && $ithink_pincode_setting == 'enable')
    {
       $content .= '<div><input type="text" class="input-text" value="" placeholder="Enter Pincode" id="pincode" name="pincode"><button type="button" id="checkp" class="button">Check Pincode</button><p id="msg"></p></div>
        ';
        echo $content;
    }
    else
    {
        echo $content;
    }
}
}
add_action('woocommerce_before_add_to_cart_form', 'ithk_addtocartbutton', 10, 2);


add_action('wp_ajax_nopriv_cpincode', 'cpincode');
add_action('wp_ajax_cpincode', 'cpincode');

if (!function_exists('cpincode')) { 

    function cpincode()
    {
        global $wpdb; // this is how you get access to the database
        $pincode = intval($_POST['pincode']);
        if ($pincode == '')
        {
            echo "Invalid pincode";
			
			
        $data = array(
            'data' => array(
                'error' => 'Invalid Pincode',
                'access_token' => ithink_access_token,
                'secret_key' => ithink_secret_key
    
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
		//echo '<pre>'; print_r($response); echo '</pre>'; 
        $data = json_decode($response['body']);
		
       $flag = "Not available";
       
        echo $flag; 
        }
        else
        {
            ithk_checkpincode($pincode);
        }

        wp_die(); 
        // this is required to terminate immediately and return a proper response
        
    }
}

/***** Checking pincode is valid or not ******/
if (!function_exists('ithk_checkpincode')) { 

    function ithk_checkpincode($pincode)
    {
    
        $data = array(
            'data' => array(
                'pincode' => $pincode,
                'access_token' => ithink_access_token,
                'secret_key' => ithink_secret_key
    
            )
        );
    
        //$url = 'https://manage.ithinklogistics.com/api_v3/pincode/check.json';
        $url = 'https://my.ithinklogistics.com/api_v3/pincode/check.json';
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8'
            ) ,
            'body' => json_encode($data) ,
            'method' => 'POST',
            'data_format' => 'body',
        ));
		//echo '<pre>'; print_r($response); echo '</pre>'; 
        $data = json_decode($response['body']);
        $flag = "Not available";
        foreach ($data
            ->data->$pincode as $shipping => $value)
        {
            if ($shipping != 'state_name' && $shipping != 'city_name')
            {
                if ($value->pickup == 'Y')
                {
                    $flag = 'Available';
                }
            }
        }
        echo $flag;
    
    }
}