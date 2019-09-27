<?php
/*
Plugin Name: Xverify Email
Description: Un-official Xverify plugin to validate emails.
Plugin URI: 
Version: 1.0
*/

define( 'XVERIFY_EMAIL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define('XVERIFY_LOG_LIMIT',50);
require_once 'xverify.inc.php';

//Plugin Activation/Deactivations 
register_activation_hook( __FILE__,'xverifyemail_activation');
register_deactivation_hook( __FILE__,'xverifyemail_deactivation');
register_uninstall_hook( __FILE__, 'xverifyemail_uninstall');

//Hooks
add_action('admin_menu', 'xverifyemail_menu'); //It create Menu on admin side
add_action( 'admin_head', 'settings_javascript' );//it add required js code to admin end
add_action('wp_ajax_save_settings', 'xverify_add_settings');//it submit form and save settings using ajax
add_action('wp_ajax_showreport', 'xverify_showreport');//it submit form and get reports using ajax
add_action('wp_ajax_showreportbyemail', 'xverify_showreportbyemail');//it submit form and get reports using ajax
add_action('wp_ajax_getstats', 'xverify_getstats');//it submit form and get reports using ajax
add_filter( 'registration_errors', 'xverify_registration_errors', 10, 3 );//hook to validate email on registration

function wooc_registeration_email_field( $username, $email, $validation_errors ) {
	
    $res=  Xverify_email($email);
	 if($res['status']!=='valid'){
		  $validation_errors->add( 'xverify_code', 'Email is invalid: '.$res['responsecode_str'] );
	 }

         return $validation_errors;
}

 add_action( 'woocommerce_register_post', 'wooc_registeration_email_field', 10, 3 );


function xverify_registration_errors( $errors, $sanitized_user_login, $user_email ) {
    
    $res=  Xverify_email($user_email);
            
    if($res['status']!=='valid'){
         $errors->add( 'xverify_code', 'Email is invalid: '.$res['responsecode_str'] );
    }
    

    return $errors;
}

function xverifyemail_activation() {
	global $wpdb;
	$xverify_log_table = $wpdb->prefix . "Xverify_Log";
		
	$sql = "CREATE TABLE IF NOT EXISTS {$xverify_log_table} (
	  `ID` int(11) NOT NULL,
	  `Email` varchar(200) NOT NULL,
	  `Auto_Corrected` varchar(200) DEFAULT NULL,
	  `Client_IP` varchar(25) DEFAULT NULL,
	  `Client_IP_Integer` int(11) DEFAULT NULL,
	  `Status` enum('valid','invalid') NOT NULL DEFAULT 'valid',
	  `Page_Info` varchar(500) DEFAULT NULL,
	  `Xverify_Response_Code` varchar(100) NOT NULL,
	  `Code` int(11) DEFAULT NULL,
	  `Xverify_Status` varchar(25) NOT NULL,
	  `Created_Timestamp` timestamp NOT NULL,
	  `Comments` varchar(200) DEFAULT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;

	--
	-- Indexes for table `Xverify_Log`
	--
	ALTER TABLE `Xverify_Log`
	  ADD PRIMARY KEY (`ID`), 
	  ADD KEY `Client_IP_Integer` (`Client_IP_Integer`),
	  ADD KEY `Client_IP` (`Client_IP`),
	  ADD KEY `Code` (`Code`,`Created_Timestamp`),
	  ADD KEY `email_createdTS` (`Email`,`Created_Timestamp`);

	--
	-- AUTO_INCREMENT for table `Xverify_Log`
	--
	ALTER TABLE `Xverify_Log`
	  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	
	add_option("xverify_response_valid",serialize(array(3,9)));
	
	
}

/* Attached to register_deactivation_hook()
 * 
 */
function xverifyemail_deactivation() {
	

}

/* Attached to register_uninstall_hook()
 *
 */
function xverifyemail_uninstall() {
	global $wpdb;
	$xverify_log_table = $wpdb->prefix . "Xverify_Log";
	$sql = "DROP TABLE {$xverify_log_table}";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	//deleting options
	
	delete_option("xverify_domain");
	delete_option("xverify_key");	
	delete_option('xverify_invalid_ip_attempts');

}

/* Attached to add_menu hook
 *
 */
function xverifyemail_menu(){
	add_menu_page('Xverify Email', 'Xverify Email', 'administrator','xverify_settings','xverify_settings_code','');

	add_submenu_page('xverify_settings','Settings', 'Settings', 'administrator','xverify_settings','xverify_settings_code');
	add_submenu_page('xverify_settings', 'Reports', 'Reports', 'administrator', 'xverify_reports','xverify_reports_code');

}

/* call back function to display html for menu item on admin side
 *
 */
 
function xverify_settings_code(){
		
?>
<div class="wrap">
<div id="loading"></div>
<h1>Xverify Settings</h1>

<form method="post" action="" id="settings_form" name="settings_form">
    <?php settings_fields( 'xverify-plugin-settings-group' ); ?>
    <?php do_settings_sections( 'xverify-plugin-settings-group' ); ?>
    <?php $max_ip_limit = esc_attr( get_option('xverify_invalid_ip_attempts') );?>
    <?php 
	$xverify_response_valid = array();
	$xverify_response_valid = unserialize(stripslashes( (get_option('xverify_response_valid') )));
	  $valid_codes = isset($xverify_response_valid) && !empty($xverify_response_valid)?$xverify_response_valid:array();
	
	
    ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Xverify Domain</th>
        <td><input type="text" name="xverify_domain" id="xverify_domain" value="<?php echo esc_attr( get_option('xverify_domain') ); ?>" /></td>
        </tr>
         
        <tr valign="top">
        <th scope="row">Xverify Api Key</th>
        <td><input type="text" name="xverify_key" id="xverify_key" value="<?php echo esc_attr( get_option('xverify_key') ); ?>" /></td>
        </tr>
	
	 <tr valign="top">
        <th scope="row">Maximum Invalid Attempts by IP in 24 hours</th>
        <td><input type="number" name="xverify_invalid" id="xverify_invalid" value="<?php echo isset($max_ip_limit)?$max_ip_limit:5; ?>" /></td>
        </tr>
	
	 <tr valign="top">
        <th scope="row">Mark Email as Valid with type</th>
        <td>
		<input type="checkbox" name="xverify_response_valid[]" class="xverify_response" value="3" <?php if(in_array(3,$valid_codes)) echo "checked";?> />Unknown<br>
		<input type="checkbox" name="xverify_response_valid[]" class="xverify_response" value="9" <?php if(in_array(9,$valid_codes)) echo "checked";?> />Temporary/Disposable Email<br>
		<input type="checkbox" name="xverify_response_valid[]" class="xverify_response" value="5" <?php if(in_array(5,$valid_codes)) echo "checked";?> />High Risk Email Address<br>
		<input type="checkbox" name="xverify_response_valid[]" class="xverify_response" value="4" <?php if(in_array(4,$valid_codes)) echo "checked";?> />Fraud List<br>
		<input type="checkbox" name="xverify_response_valid[]" class="xverify_response"  value="7" <?php if(in_array(7,$valid_codes)) echo "checked";?> />Complainer Email Address
	</td>
        </tr>
      
    </table>
    
    <?php submit_button(); ?>

</form>
</div>
<?php
	
}

/* call back function to display html for Reports section
 *
 */
 
function xverify_reports_code(){
		
?>
<div class="" style="overflow-x:auto">
<h1>Xverify Reports</h1>
<div>
<div id="loading"></div>
<form method="post" action="" id="reports_form" name="reports_form">
    <?php settings_fields( 'xverify-plugin-reports-group' ); ?>
    <?php do_settings_sections( 'xverify-plugin-reports-group' ); ?>
    <table class="form-table">
    <input type="hidden" id="page" name="page" value="1">
        <tr valign="top">
        <th scope="row">Start Date</th>
        <td><input type="text" name="start_date" id="start_date" /></td>
</tr></tr>
        <th scope="row">End Date</th>
        <td><input  type="text" name="end_date" id="end_date" /></td>
        </tr>
      
    </table>
    
    <?php submit_button("Show Report"); ?>

</form>
</div>
<hr>
<div class="widefat"> 
<form method="post" action="" id="email_search_form" name="email_search_form">
 <table class="form-table">
    <input type="hidden" id="page" name="page" value="1">
        <tr valign="top">
        <th scope="row">Email</th>
        <td><input type="text" id="searchEmail" > </td>
</tr></tr></table>
 <?php submit_button("Search By Email"); ?>
</div>
<hr>
<div>
<div id="loading"></div>
<table id="stats" align="center"  style="display:none;border:1px solid #000000">
<tr><th>Valid Email= </th><th id="valid"></th></tr>
<tr><th>Invalid= </th><th id="invalid"></th></tr>
<tr><th>Total= </th><th id="total"></th></tr>

</table>
</div>
<table id="xverify_report" style="display:none" class="widefat" cellspacing="0">
    <thead>
    <tr>


	<th id="columnname" class="manage-column column-columnname num" scope="col"></th> 
	<th id="columnname" class="manage-column column-columnname" scope="col">Email</th>
	<th id="columnname" class="manage-column column-columnname" scope="col">Status</th>
	<th id="columnname" class="manage-column column-columnname" scope="col">Code</th>
	<th id="columnname" class="manage-column column-columnname" scope="col">IP</th>
	<th id="columnname" class="manage-column column-columnname" scope="col">Xverify_Response_Code</th>
	<th id="columnname" class="manage-column column-columnname" scope="col">Created_Timestamp</th>
	<th id="columnname" class="manage-column column-columnname" scope="col">Comments</th>

    </tr>
    </thead>
    <tbody>
    </tbody>
</table>
<br>
   <div align="center" id="pagination" style="display:none">
	<div id="listingTable"></div>
	<a id="btn_pre" class="button button-primary" onclick="return false;">Prev</a>
	<a id="pagespan" class="button button-primary" onclick="return false;"></a> 
	<a id="btn_next" class="button button-primary" onclick="return false;">Next</a>
</div>

</div>
<?php
	
}	
//add ajax code
/* Attached to admin_footer hook
 *
 */
function settings_javascript() { 
	wp_register_style( 'xjquery-ui', 'https://code.jquery.com/ui/1.11.2/themes/smoothness/jquery-ui.css' );
	wp_enqueue_style( 'xjquery-ui' ); 
	wp_enqueue_script( 'jquery-ui-datepicker' );
	wp_enqueue_script( 'jquery-ui-slider' );
	wp_register_script('timepicker', plugins_url('/jquery-ui-timepicker-addon.js', __FILE__), array( 'jquery' ));
	wp_enqueue_script('timepicker');
	wp_register_style( 'timepicker-ui', plugins_url('/jquery-ui-timepicker-addon.css' , __FILE__));
	wp_enqueue_style('timepicker-ui');
 
			

	?>
	<style>
#loading{
  position:fixed;
  display:none;
  top:0px;
  right:0px;
  width:100%;
  height:100%;
  background-color:#666;
  background-image:url("<?php echo get_bloginfo('wpurl') ?>/wp-includes/js/mediaelement/loading.gif");
  background-repeat:no-repeat;
  background-position:center;
  z-index:10000000;
  opacity: 0.4;
  filter: alpha(opacity=40); /* For IE8 and earlier */
}
	</style>
	<script type="text/javascript" >
	jQuery(document).ready(function($) {
		var image = '<?php echo get_bloginfo('wpurl') ?>' +'wp-includes/js/mediaelement/loading.gif';
		
		jQuery("#settings_form").live("submit",function(){
			$('#loading').show();
			var values = $('input:checkbox:checked.xverify_response').map(function () {
			  return this.value;
			}).get();
			var data = {
			'action': 'save_settings',
			'xverify_domain': jQuery("#xverify_domain").val(),
			'xverify_key': jQuery("#xverify_key").val(),
			'xverify_invalid': jQuery("#xverify_invalid").val(),
			'xverify_response_valid': values
			};

			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			jQuery.post(ajaxurl, data, function(response) {
				
				res= jQuery.parseJSON(jQuery.trim(response));
				$('#loading').html("").hide();	
				alert(res.Msg);
				
			});
			return false;
		
		});
		
		jQuery("#reports_form").live("submit",function(){
			$('#loading').show();
			var page = 1;
			var data = {
			'action': 'showreport',
			'start_date': jQuery("#start_date").val(),
			'end_date': jQuery("#end_date").val(),
			'page':page
			};
                        getStats(jQuery("#start_date").val(),jQuery("#end_date").val());
			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			jQuery.post(ajaxurl, data, function(response) {

				result= jQuery.parseJSON(jQuery.trim(response));
				jQuery("#xverify_report").show();
				jQuery("#pagination").show();
				jQuery("#xverify_report tbody").html('');
				var current_page = page;
				var totalPages = result.pages;
				var count = result.count;
				res = result.data;
								
				changePage(current_page,totalPages,res);
				$('#loading').html("").hide();					
			});
			return false;
		 
		});
		
		jQuery("#email_search_form").live("submit",function(){
			$('#loading').show();
			var page = 1;
			var data = {
			'action': 'showreportbyemail',
			'email': jQuery("#searchEmail").val(),
			'page':page
			};
			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			jQuery.post(ajaxurl, data, function(response) {
				
				
				result= jQuery.parseJSON(jQuery.trim(response));
				jQuery("#xverify_report").show();
				jQuery("#pagination").show();
				jQuery("#xverify_report tbody").html('');
				var current_page = page;
				var totalPages = result.pages;
				var count = result.count;
				res = result.data;
								
				changePage(current_page,totalPages,res);
				$('#loading').hide();				
			});
			
			return false;
		 
		});
		
		jQuery("#btn_pre").live("click",function(){
			jQuery('#loading').show();
			var page = parseInt(jQuery("#page").val())-1;
			var data = {
			'action': 'showreport',
			'start_date': jQuery("#start_date").val(),
			'end_date': jQuery("#end_date").val(),
			'page':page
			};
			jQuery("#page").val(page);
			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			jQuery.post(ajaxurl, data, function(response) {

				result= jQuery.parseJSON(jQuery.trim(response));
				jQuery("#xverify_report").show();
				jQuery("#pagination").show();
				jQuery("#xverify_report tbody").html('');
				var current_page = page;
				var totalPages = result.pages;
				var count = result.count;
				res = result.data;
								
				changePage(current_page,totalPages,res);	
				$('#loading').hide();				
							});
				return false;
		 
		});
		jQuery("#btn_next").live("click",function(){
			jQuery('#loading').show();
			var page = parseInt(jQuery("#page").val())+1;
			var data = {
			'action': 'showreport',
			'start_date': jQuery("#start_date").val(),
			'end_date': jQuery("#end_date").val(),
			'page':page
			};
			jQuery("#page").val(page);
			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			jQuery.post(ajaxurl, data, function(response) {
	
				result= jQuery.parseJSON(jQuery.trim(response));
				jQuery("#xverify_report").show();
				jQuery("#pagination").show();
				jQuery("#xverify_report tbody").html('');
				var current_page = page;
				var totalPages = result.pages;
				var count = result.count;
				res = result.data;
								
				changePage(current_page,totalPages,res);
				$('#loading').hide();
							
			});
			return false;
		 
		});
	
		var dd = new Date();
		dd.setHours(0,0,0,0);
		jQuery('#start_date').datetimepicker({
			dateFormat: 'yy-mm-dd',timeFormat:'HH:mm:ss'
			}).datepicker("setDate", new Date(dd));
	
		jQuery('#end_date').datetimepicker({
			dateFormat: 'yy-mm-dd',timeFormat:'H:mm:ss'
			}).datepicker("setDate", new Date());	
		
		/* pagination functions*/		
		function changePage(page,pages,res)
		{
			var btn_next = jQuery("#btn_next");
			var btn_prev = jQuery("#btn_pre");
			var listing_table = jQuery("#listingTable");
			var page_span = jQuery("#pagespan");
			var start = (page - 1) * <?php echo XVERIFY_LOG_LIMIT;?>;
			// Validate page
			if (page < 1) page = 1;
			if (page > pages) page = pages;

			start = start+1;
			for (i = 0; i < res.length; i++) {
			    
				jQuery("#xverify_report tbody").append('<tr class="alternate"><th class="check-column" scope="row">'+start+'</th><td class="column-columnname">'+res[i]["Email"]+'</td><td class="column-columnname">'+res[i]["Status"]+'</td><td class="column-columnname">'+res[i]["Code"]+'</td><td class="column-columnname">'+res[i]["Client_IP"]+'</td><td class="column-columnname">'+res[i]["Xverify_Response_Code"]+'</td><th class="check-column" scope="row">'+res[i]["Created_Timestamp"]+'</th><td class="column-columnname">'+res[i]["Comments"]+'</td></tr>');
				start++;
			}
			    page_span.html("Page:"+page + "/" + pages);

			    if (page <= 1) {
				btn_prev.hide();
			    } else {
				btn_prev.show();
			    }

			    if (page == pages) {
				btn_next.hide();
			    } else {
				btn_next.show();
			    }
			}
		function getStats(start,end){
			var data = {
			'action': 'getstats',
			'start_date': start,
			'end_date': end
			};
		
			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			jQuery.post(ajaxurl, data, function(response) {

				result= jQuery.parseJSON(jQuery.trim(response));
				jQuery("#stats").show();
				var total = result.total;
				jQuery("#total").html(total);
				jQuery("#valid").html(result.valid);
				jQuery("#invalid").html(result.invalid);				
			});
					
		}
		});
		
		/* pagination functions*/
 


</script> <?php
}

//ajax call back function for admin_ajax hook

function xverify_add_settings() {

$xverify_domain = isset($_POST['xverify_domain'])?$_POST['xverify_domain']:'';
$xverify_key = isset($_POST['xverify_key'])?$_POST['xverify_key']:'';
$xverify_invalid = isset($_POST['xverify_invalid'])?$_POST['xverify_invalid']:'';
$xverify_response_valid = isset($_POST['xverify_response_valid'])?$_POST['xverify_response_valid']:'';
$x_valid=array();
$x_valid = $xverify_response_valid;
update_option("xverify_domain",$xverify_domain);
update_option("xverify_key",$xverify_key);
update_option("xverify_invalid_ip_attempts",$xverify_invalid);
update_option("xverify_response_valid",addslashes(serialize($x_valid)));
echo json_encode(array("isError"=>0,"Msg"=>"Settings Saved Successfully"));
wp_die(); 
}

//ajax call back function for admin_ajax hook (Reports section)

function xverify_showreport() {

$start_date = isset($_POST['start_date'])?$_POST['start_date']:'';
$end_date = isset($_POST['end_date'])?$_POST['end_date']:'';
$page = isset($_POST['page'])?$_POST['page']:'1';
$limit = XVERIFY_LOG_LIMIT;

/* Find the number of rows returned from a query; Note: Do NOT use a LIMIT clause in this query */
$cnt = getReport_total($start_date, $end_date);
 $count = $cnt[0]->total;

/* Find the number of pages based on $count and $limit */
$pages = (($count % $limit) == 0) ? $count / $limit : floor($count / $limit) + 1; 
 
/* Now we use the LIMIT clause to grab a range of rows */
$result = getReport_daterange($start_date, $end_date, $page, $limit);
 

echo json_encode(array("data"=>$result,"count"=>$count,"pages"=>$pages));
wp_die(); 
}

function xverify_showreportbyemail() {

$email = isset($_POST['email'])?$_POST['email']:'';

$page = isset($_POST['page'])?$_POST['page']:'1';
$limit = XVERIFY_LOG_LIMIT;

/* Find the number of rows returned from a query; Note: Do NOT use a LIMIT clause in this query */
$cnt = getReport_totalemail($email);
 $count = $cnt[0]->total;

/* Find the number of pages based on $count and $limit */
$pages = (($count % $limit) == 0) ? $count / $limit : floor($count / $limit) + 1; 
 
/* Now we use the LIMIT clause to grab a range of rows */
$result = getEmailSearch($email);
 

echo json_encode(array("data"=>$result,"count"=>$count,"pages"=>$pages));
wp_die(); 
}

function xverify_getstats() {

$ts_start = isset($_POST['start_date'])?$_POST['start_date']:'';
$ts_end = isset($_POST['end_date'])?$_POST['end_date']:'';
// usage//
$valid_count=getTotalStatusCount($ts_start, $ts_end);
$invalid_count=getTotalStatusCount($ts_start, $ts_end,'invalid');
$valid = $valid_count[0]->total;
$invalid = $invalid_count[0]->total;
$total=$valid+$invalid;
 

echo json_encode(array("total"=>$total,"valid"=>$valid,"invalid"=>$invalid));
wp_die(); 
}
?>