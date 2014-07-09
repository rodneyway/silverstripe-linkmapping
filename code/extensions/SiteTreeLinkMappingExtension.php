<?php

/**
 *	Automatically create a link mapping when a site tree URL segment or parent ID has been updated.
 *
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class SiteTreeLinkMappingExtension extends DataExtension {

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
