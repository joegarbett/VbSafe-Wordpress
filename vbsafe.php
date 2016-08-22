<?php
/**
 * Plugin Name: VBSafe
 * Plugin URI: http://www.vbsafe.co.uk
 * Description: This plugin revisions database content
 * Version: 1.0.0
 * Author: Joseph Garbett at VBSafe.co.uk (Garbott Ltd)
 * Author URI: http://www.garbott.co.uk
 * License: GPL2
 */

/**
 * @param string $optionName
 * @param string $old_value
 * @param string $new_value
 * @return bool
 */
function vbsafe_vault($optionName = "", $old_value = "", $new_value = "")
{
	if(!defined("WP_ADMIN")) {
		return false;
	}
	
	if(WP_ADMIN !== true) {
		return false;
	}
	
	if(!function_exists("openssl_encrypt")
		OR !function_exists("json_decode")) {
		return false;
	}
	
	$apiToken = get_option("api_token");
	if(empty($apiToken)) {
		return false;
	}

	$apiUrl = "http://vbsafe-prod.apigee.net/";

	$response = wp_remote_get($apiUrl . "encryption-key?" . http_build_query(array(
		"api_token" => $apiToken, 
		"application" => "wordpress"
	)), array(
		'timeout' => 5,
		'sslverify' => false,
	));

	if(!is_array($response)) {
		return false;
	}
	
	$decodedContent = json_decode($response['body']);
	$decodedContent = (array)$decodedContent;

	if(!isset($decodedContent['key'])) {
		return false;
	}
	
	if(in_array($optionName, $decodedContent['preferences'])) {	
		$transmittableContent = ["key" => $optionName, "original_value" => $old_value, "new_value" => $new_value];
		$transmittableContent = openssl_encrypt(wp_json_encode($transmittableContent), "aes-256-ecb", $decodedContent['key']);

		$response = wp_remote_post($apiUrl . "vault", array(
			'timeout' => 5,
			"body" => array(
				"url" => get_site_url(),
				"application" => "wordpress",
				"api_token" => $apiToken,
				"content" => $transmittableContent,
				"username" => wp_get_current_user()->display_name,
			)
		));
	}

	return true;
}

/**
 * Set Option
 */
function vbsafe_options()
{
	register_setting('vbsafe', 'api_token');
}

/**
 * Set Form
 */
function vbsafe_options_page()
{
	?>
<div class="wrap">
<h1>VBSafe</h1>
<form method="post" action="options.php">
    <?php settings_fields( 'vbsafe' ); ?>
    <?php do_settings_sections( 'vbsafe' ); ?>
    <table class="form-table">
        <tr valign="top">
			<th scope="row">API Token</th>
			<td><input type="text" name="api_token" value="<?php echo esc_attr( get_option('api_token') ); ?>" /></td>
        </tr>
    </table>
    <?php submit_button(); ?>
</form>
</div>
<?php
}

/**
 * Process all options
 */
function vbsafe_manual_page()
{
	$allOptions = wp_load_alloptions();
	foreach($allOptions AS $optionKey => $optionValue) {
		$test = vbsafe_vault($optionKey, $optionValue, $optionValue);
		
		if(!$test) {
			?>
			<div id="setting-error-settings_updated" class="error settings-error notice is-dismissible"> 
		<p><strong>Something went wrong. Please make sure you have added your API token.</strong></p>
	</div>
	<?
			return;
		}
		
	}
	?>
	<h1>VBSafe</h1>
	<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible"> 
		<p><strong>All options revisioned.</strong></p>
	</div>
	<?php
}

/**
 * Custom admin options
 */
function wporg_custom_admin_menu() 
{
    add_options_page(
        'VBSafe',
        'VBSafe',
        'manage_options',
        'vbsafe',
        'vbsafe_options_page'
    );
	
	add_options_page(
        'VBSafe',
        'VBSafe Manual',
        'manage_options',
        'vbsafe-manual',
        'vbsafe_manual_page'
    );
	
	add_action( 'admin_init', 'vbsafe_options' );
}

// Fluff
add_action('updated_option', 'vbsafe_vault', 0, 3);
add_action('admin_menu', 'wporg_custom_admin_menu');
