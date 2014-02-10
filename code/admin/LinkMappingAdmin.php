<?php
/**
 * A simple administration interface to allow administrators to manage link
 * mappings.
 *
 * @package silverstripe-linkmapping
 */
class LinkMappingAdmin extends ModelAdmin {

	public static $menu_title = 'Link Mappings';
	public static $url_segment = 'link-mappings';
	public static $managed_models = 'LinkMapping';
	
	public function getExportFields() {
		if ($exportFields = singleton($this->owner->modelClass)->export_fields)
		{
			return $exportFields;
		}
		return singleton($this->owner->modelClass)->summaryFields();
	}

}