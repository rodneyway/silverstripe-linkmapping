<?php
/**
 * A simple administration interface to allow administrators to manage link
 * mappings.
 *
 * @package silverstripe-linkmapping
 */
class LinkMappingAdmin extends ModelAdmin {

	private static $menu_title = 'Link Mappings';
	private static $url_segment = 'link-mappings';
	private static $managed_models = 'LinkMapping';

}