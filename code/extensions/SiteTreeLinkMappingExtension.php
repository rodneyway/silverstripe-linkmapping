<?php

/**
 *	Automatically create a link mapping when a site tree URL segment has been updated.
 *
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class SiteTreeLinkMappingExtension extends DataExtension {

	public function onAfterWrite() {

		if(Config::inst()->get('LinkMappingRequestFilter', 'replace_default')) {

			// Make sure that the URL segment has been updated.

			$changed = $this->owner->getChangedFields();
			if(isset($changed['URLSegment']['before']) && isset($changed['URLSegment']['after']) && ($changed['URLSegment']['before'] != $changed['URLSegment']['after'])) {

				// Make sure that the link mapping doesn't already exist.

				$existing = LinkMapping::get()->filter(array(
					'MappedLink' => $changed['URLSegment']['before'],
					'RedirectPageID' => $this->owner->ID
				))->first();
				if($existing) {
					return;
				}

				// Create a new link mapping that points from the old URL segment to the site tree element itself.

				$mapping = LinkMapping::create();
				$mapping->MappedLink = $changed['URLSegment']['before'];
				$mapping->RedirectType = 'Page';
				$mapping->RedirectPageID = $this->owner->ID;
				$mapping->Priority = 1;
				$mapping->write();
			}
		}
	}

}
