<?php

/**
 *	Automatically create a link mapping when a site tree URL segment or parent ID has been updated.
 *
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class SiteTreeLinkMappingExtension extends DataExtension {
	
	private static $db = array(
		'FallbackRule'		=> 'Varchar',
		'FallbackUrl'		=> 'Varchar(255)',
		'FallbackResponse'	=> 'Varchar',
	);
	
	public function updateSettingsFields(\FieldList $fields) {
		$options = array(
			'URL'		=> _t('LinkMapping.STRAIGHT_URL', 'Specific URL'),
			'ThisPage'	=> _t('LinkMapping.THIS_PAGE', 'This Page'),
			'Nearest'	=> _t('LinkMapping.NEAREST', 'Nearest Parent')
		);
		
		// Retrieve the response code listing.

		$responseCodes = Config::inst()->get('SS_HTTPResponse', 'status_codes');
		foreach($responseCodes as $code => &$description) {

			// Make sure the response code has been included in the description.

			if(substr($code, 0, 1) === '3') {
				$description = "{$code}: $description";
			}

			// Remove any response codes that are not a redirect.

			else {
				unset($responseCodes[$code]);
			}
		}

		$info = _t('LinkMapping.FALLBACK_DETAILS', 'Select a method to use for handling any missing child page');
		$field = DropdownField::create(
				'FallbackRule', 
				_t('LinkMapping.FALLBACK_RULE', 'Fallback rule'), 
				$options
			)->setRightTitle($info)
			 ->setHasEmptyDefault(true);
		
		$fields->addFieldToTab('Root.LinkMappings', $field);
		$fields->addFieldToTab('Root.LinkMappings', TextField::create('FallbackUrl', _t('LinkMapping.FALLBACK_URL', 'Fallback url')));
		
		$fields->addFieldToTab('Root.LinkMappings', DropdownField::create('FallbackResponse', _t('LinkMapping.FALLBACK_RESPONSE', 'Response code'), $responseCodes));
		
	}

	public function onAfterWrite() {

		parent::onAfterWrite();
		if(Config::inst()->get('LinkMappingRequestFilter', 'replace_default')) {

			// Make sure that the URL segment or parent ID has been updated.

			$changed = $this->owner->getChangedFields();
			if((isset($changed['URLSegment']['before']) && isset($changed['URLSegment']['after']) && ($changed['URLSegment']['before'] != $changed['URLSegment']['after']))
				|| (isset($changed['ParentID']['before']) && isset($changed['ParentID']['after']) && ($changed['ParentID']['before'] != $changed['ParentID']['after']))) {

				// Make sure we don't create a link mapping for newly created pages.

				$URLsegment = isset($changed['URLSegment']['before']) ? $changed['URLSegment']['before'] : $this->owner->URLSegment;
				if($URLsegment !== 'new-page') {

					// Construct the URL to be used for the link mapping.

					$parentID = isset($changed['ParentID']['before']) ? $changed['ParentID']['before'] : $this->owner->ParentID;
					$parent = SiteTree::get_one('SiteTree', "SiteTree.ID = {$parentID}");
					while($parent) {
						$URLsegment = Controller::join_links($parent->URLSegment, $URLsegment);
						$parent = SiteTree::get_one('SiteTree', "SiteTree.ID = {$parent->ParentID}");
					}

					// Create a link mapping for this site tree element.

					$this->createLinkMapping($URLsegment, $this->owner->ID);

					// Recursively create link mappings for any children of this site tree element.

					$children = $this->owner->AllChildrenIncludingDeleted();
					if($children->count()) {
						$this->recursiveLinkMapping($URLsegment, $children);
					}
				}
			}
		}
	}

	public function onAfterDelete() {

		parent::onAfterDelete();

		// When this site tree element has been removed from both staging and live.

		if(Config::inst()->get('LinkMappingRequestFilter', 'replace_default') && $this->owner->getIsDeletedFromStage() && !$this->owner->isPublished()) {

			// Remove any link mappings that are directly associated with this page.

			LinkMapping::get()->filter(array(
				'RedirectType' => 'Page',
				'RedirectPageID' => $this->owner->ID
			))->removeAll();
		}
	}

	/**
	 *	Recursively create link mappings for any site tree children.
	 *	@param string
	 *	@param ArrayList
	 */

	public function recursiveLinkMapping($baseURL, $children) {

		foreach($children as $child) {
			$URLsegment = Controller::join_links($baseURL, $child->URLSegment);
			$this->createLinkMapping($URLsegment, $child->ID);

			// Recursively create link mappings for any children of this child.

			$recursiveChildren = $child->AllChildrenIncludingDeleted();
			if($recursiveChildren->count()) {
				$this->recursiveLinkMapping($URLsegment, $recursiveChildren);
			}
		}
	}

	/**
	 *	Create a new link mapping from a URL segment to a site tree element by ID.
	 *	@param string
	 *	@param integer
	 */

	public function createLinkMapping($URLsegment, $redirectPageID) {

		// Make sure that the link mapping doesn't already exist.

		$existing = LinkMapping::get()->filter(array(
			'MappedLink' => $URLsegment,
			'RedirectPageID' => $redirectPageID
		))->first();
		if($existing) {
			return;
		}

		// Create the new link mapping with appropriate defaults.

		$mapping = LinkMapping::create();
		$mapping->MappedLink = $URLsegment;
		$mapping->RedirectType = 'Page';
		$mapping->RedirectPageID = $redirectPageID;
		$mapping->Priority = 1;
		$mapping->write();
	}

}
