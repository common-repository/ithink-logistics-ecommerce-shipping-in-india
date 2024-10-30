<?php 
function itl_config()
{
	global $wpdb;
    $ithink_logistics_table   = $wpdb->prefix."ithink_logistics";
    $woo_shipping_check   = $wpdb->prefix."woocommerce_shipping_zone_methods";

    $get_token_query = "SELECT * from $ithink_logistics_table where id='1'";
    $result_get_token_query = $wpdb->get_results($get_token_query,ARRAY_A);

    $db_access_token    = $result_get_token_query[0]['api_key'];
    $db_secret_key      = $result_get_token_query[0]['secret_key'];

	$get_shipping_details = "SELECT * from $woo_shipping_check where method_id='ithk_pickup_shipping_method'";
    $result_shipping_details = $wpdb->get_row($get_shipping_details);

    $freeship_shipping_details = "SELECT * from $woo_shipping_check where method_id='free_shipping'";
    $free_shipping_details = $wpdb->get_row($freeship_shipping_details);
	//echo '<pre>'; print_r($free_shipping_details); echo '</pre>';
	$ithink_pincode_setting = get_option( 'ithink_pincode_setting' );

    $db_access_token    = $result_get_token_query[0]['api_key'];
    $db_secret_key      = $result_get_token_query[0]['secret_key'];

    if(isset($_POST['config_setting']))
	{
		
			$api_key    = sanitize_key($_POST['api_key']);
			$secret_key      = sanitize_key($_POST['secret_key']);

			$update_query = "UPDATE $ithink_logistics_table set api_key='$api_key',secret_key = '$secret_key'";
			//echo '<pre>'; print_r($update_query); echo '</pre>';
			$result_update_query = $wpdb->query($update_query);
	
			if($result_update_query)
			{
				$db_access_token    = $api_key;
				$db_secret_key      = $secret_key;	
			}
			
		
	}

	// woocommerce setting update

	if(isset($_POST['setting_update'])){
		
		if (isset($_POST['pincode_enable'])) {
			update_option( 'ithink_pincode_setting', 'enable' );
		}else{
			update_option( 'ithink_pincode_setting', 'disable' );
		}

		if (isset($_POST['rate_calu_enable'])) {
			
			$update_query = "UPDATE $woo_shipping_check set is_enabled='1' where method_id='ithk_pickup_shipping_method'";
			$result_update_query = $wpdb->query($update_query);
			
		}else{
			$update_query = "UPDATE $woo_shipping_check set is_enabled='0' where method_id='ithk_pickup_shipping_method'";
			$result_update_query = $wpdb->query($update_query);
		}
		
		if (isset($_POST['free_shipping_enable'])) {
			$cart_value = $_POST['cart_value'];
			$free_update_query = "UPDATE $woo_shipping_check set is_enabled='1' where method_id='free_shipping'";
			$free_result_update_query = $wpdb->query($free_update_query);
			update_option( 'freeshipping_totalvalue', $cart_value );
		}else{
			$free_update_query = "UPDATE $woo_shipping_check set is_enabled='0' where method_id='free_shipping'";
			$free_result_update_query = $wpdb->query($free_update_query);
			update_option( 'freeshipping_totalvalue', '0' ); 
		}

		echo '<script language="javascript">';
		echo 'location.href = location.href + "&update=success";';
		echo '</script>';
		

	}

	if(isset($_GET['update']) && $_GET['update'] == 'success'){ ?>
		<div class="notice notice-success is-dismissible">
			<p><?php _e('The setting has been updated!', 'textdomain') ?></p>
		</div>
	<?php }	?>
	
	<form method="post" action="#">
	    <table class="wc_status_table widefat" cellspacing="0" id="status" style="margin-top:20px">
			<thead>
				<tr>
					<th colspan="3" data-export-label="WordPress Environment"><h2>I Think Logistics Settings</h2></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td data-export-label="API Key">API Key:</td>
					<td><input type="text" style="width:300px" name="api_key" value="<?php echo sanitize_key( $db_access_token ); ?>"/></td>
				</tr>

				<tr>
					<td data-export-label="Secret Key">secret Key:</td>
					<td><input type="text" style="width:300px" name="secret_key" value="<?php echo sanitize_key( $db_secret_key ); ?>"/></td>
				</tr>

				<tr>
					<td ></td>
					<td><input type="submit" value="Submit" name="config_setting" class="button-primary"/></td>
				</tr>
				
			</tbody>
		</table>
	</form>

	<div class="well">
		<form method="post" action="#">
			<table class="wc_status_table widefat" cellspacing="0" id="status" style="margin-top:20px">
				<tbody>
					<tr>
						<td style="width: 40%" data-export-label="Pincode serviceability Enable/Disable">Pincode serviceability Enable/Disable</td>
						<td style="width: 10%">
							<input type="checkbox" name="pincode_enable" value="1" <?php if ($ithink_pincode_setting == 'enable'){?> checked="checked" <?php } ?>>	
						</td>
						<td></td>
					</tr>
					<tr>
						<td data-export-label="Rate Calculation Enable/Disable">Rate Calculation Enable/Disable</td><?php //echo '</pre>'; print_r($result_shipping_details); echo '</pre>'; ?>
						<td>
							<input type="checkbox" name="rate_calu_enable" value="1" <?php if (isset($result_shipping_details->is_enabled) && $result_shipping_details->is_enabled == 1){?> checked="checked" <?php } ?>>	
						</td>
						<td>		<?php if($result_shipping_details->is_enabled==0){ echo '<span>Please enable ithink shipping</span>'; } ?> </td>
					</tr>
					<tr>
						<td data-export-label="Rate Calculation Enable/Disable">Free Shipping Enable/Disable((It will depend on cart total amount)</td>
						<td>                     <?php $freeoption = get_option('freeshipping_totalvalue'); ?> 
			<input type="checkbox" name="free_shipping_enable" value="1" <?php if($freeoption==0) { ?> onclick="showHtmlDiv()" <?php } ?> <?php if (isset($free_shipping_details->is_enabled) && $free_shipping_details->is_enabled == 1){ echo 'enable' ;  } else { echo 'disabled'; }  ?> <?php if($freeoption >=1){ echo "checked"; }?>>							<?php if($free_shipping_details->is_enabled==0){ echo '<span>Please enable free shipping</span>'; } ?> 
							
							</td><td><input type="text" name="cart_value" id="cart_value" placeholder="Enter total vale" value="<?php echo $freeoption; ?>" 
							<?php if($freeoption>=1) { echo 'style="display:block;"'; }  else { echo 'style="display:none;"'; }?>>
						</td>
					</tr>
					<tr>
						<td ></td>
						<td><input type="submit" value="Save Setting" name="setting_update" class="button-primary"/></td>
					</tr>
				</tbody>
			</table>
		</form>
	</div>

    <?php

}