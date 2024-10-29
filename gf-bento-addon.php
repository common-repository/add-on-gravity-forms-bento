<?php
/*
Plugin Name: Add-on for Gravity Forms + Bento
Plugin URI: https://jacksonwhelan.com/plugin/add-on-for-gravity-forms-bento/
Description: Community plugin to integrate Gravity Forms with Bento.
Version: 2.0
Author: Terrier Tenacity
Author URI: https://terriertenacity.com
*/

define( 'GF_BENTO_ADDON_VERSION', '2.0' );

add_action( 'gform_loaded', array( 'GF_Bento_AddOn_Bootstrap', 'load' ), 5 );

class GF_Bento_AddOn_Bootstrap {

	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gf-bento-addon.php' );

		GFAddOn::register( 'GFBentoAddOn' );
	}

}

function gf_bento_addon() {
	return GFBentoAddOn::get_instance();
}