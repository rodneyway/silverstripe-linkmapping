<?php
/**
 * A link mapping that connects a link to either a redirected link or another
 * page on the site.
 *
 * @package silverstripe-linkmapping
 */
class LinkMapping extends DataObject {

	// Define the redirect page through DB fields if the CMS module doesn't exist.

	private static $db = array(
		'MappedLink'   => 'Varchar(255)',
		'RedirectType' => "Enum('Page, Link', 'Link')",
		'RedirectLink' => 'Varchar(255)',
		'RedirectPageID' => 'Int',
		'ResponseCode' => "Enum('301, 303', '303')",
		'ForwardPOSTRequest' => 'Boolean'
	);

	private static $summary_fields = array(
		'MappedLink',
		'RedirectType',
		'RedirectPageTitle',
		'RedirectLink'
	);

	private static $field_labels = array(
		'RedirectPageTitle' => 'Redirect Page Title'
	);

	private static $searchable_fields = array(
		'MappedLink'   => array('filter' => 'PartialMatchFilter'),
		'RedirectType' => array('filter' => 'ExactMatchFilter')
	);

	// Make sure a link mapping with a query string is returned first.

	private static $default_sort = array(
		'ID' => 'DESC'
	);

	/**
	 * Returns a link mapping for a link if one exists.
	 *
	 * @param  string $link
	 * @return LinkMapping
	 */
	public static function get_by_link($link) {
		$link = self::unify_link(Director::makeRelative($link));

		// check for an exact match
		$match = LinkMapping::get()->filter('MappedLink', $link)->first();
		if($match) {
			return $match;
		}
	
		// check for a match with the same get vars in a different order
		if(strpos($link, '?')){
			$linkParts 		= explode('?', $link);
			$url 			= Convert::raw2sql($linkParts[0]);

			// Retrieve the matching link mappings, ordered by query string (with newest given priority).

			$matches = LinkMapping::get()->where("(MappedLink = '{$url}') OR (MappedLink LIKE '{$url}?%')")->sort(array('MappedLink' => 'DESC', 'ID' => 'DESC'));
			parse_str($linkParts[1], $queryParams);

			if($matches->count()){
				foreach ($matches as $match) {
					$matchQueryString = explode('?', $match->MappedLink);
					if(count($matchQueryString) > 1) {
						parse_str($matchQueryString[1], $matchParams);

						// Make sure each URL parameter matches against the link mapping.

						if($matchParams == $queryParams){
							return $match;
						}
					}
					else {
						return $match;
					}
				}
			}
		}
		return null;
	}

	/**
	 * Unifies a link so mappings are predictable.
	 *
	 * @param  string $link
	 * @return string
	 */
	public static function unify_link($link) {
		return strtolower(trim($link, '/?'));
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->removeByName('RedirectType');
		$fields->removeByName('RedirectLink');
		$fields->removeByName('RedirectPageID');

		$fields->insertBefore(new HeaderField(
			'MappedLinkHeader', $this->fieldLabel('MappedLinkHeader')
		), 'MappedLink');

		$fields->addFieldToTab('Root.Main', new HeaderField(
			'RedirectToHeader', $this->fieldLabel('RedirectToHeader')
		));
		if(ClassInfo::exists('SiteTree')) {
			$pageLabel = $this->fieldLabel('RedirectToPage');
			$linkLabel = $this->fieldLabel('RedirectToLink');
			$fields->addFieldToTab('Root.Main', new SelectionGroup('RedirectType', array(
				"Page//$pageLabel" => new TreeDropdownField('RedirectPageID', '', 'Page'),
				"Link//$linkLabel" => new TextField('RedirectLink', '')
			)));
		}
		else {
			$fields->addFieldToTab('Root.Main', new TextField('RedirectLink'));
		}

		// Allow the user to select and customise the redirect response code.

		$responseCodes = array(
			301 => '301: ' . _t('LinkMapping.RESPONSECODE.301', 'Moved Permanently'),
			303 => '303: ' . _t('LinkMapping.RESPONSECODE.303', 'See Other')
		);
		$fields->addFieldToTab('Root.Main', new DropdownField('ResponseCode', _t('LinkMapping.RESPONSECODE', 'Response Code'), $responseCodes));
		$fields->addFieldToTab('Root.Main', new CheckboxField('ForwardPOSTRequest', _t('LinkMapping.FORWARDPOSTREQUEST', 'Forward POST Request')));

		return $fields;
	}

	public function onBeforeWrite() {

		parent::onBeforeWrite();
		$this->MappedLink = self::unify_link($this->MappedLink);

		// Make sure an external link has been written correctly, otherwise it'll be treated as relative.

		if((substr($this->RedirectLink, 0, 4) !== 'http') && (substr($this->RedirectLink, 0, 2) !== '//') && strpos($this->RedirectLink, '.com') !== false) {
			$this->RedirectLink = "//{$this->RedirectLink}";
		}
	}

	public function fieldLabels($includerelations = true) {
		return parent::fieldLabels($includerelations) + array(
			'MappedLinkHeader' => _t('LinkMapping.MAPPEDLINK', 'Mapped Link'),
			'RedirectToHeader' => _t('LinkMapping.REDIRECTTO', 'Redirect To'),
			'RedirectionType'  => _t('LinkMapping.REDIRECTIONTYPE', 'Redirection type'),
			'RedirectToPage'   => _t('LinkMapping.REDIRTOPAGE', 'Redirect to a page'),
			'RedirectToLink'   => _t('LinkMapping.REDIRTOLINK', 'Redirect to a link')
		);
	}

	/**
	 * @return string
	 */
	public function getLink() {

		if ($page = $this->getRedirectPage()) {
			return $page->Link();
		} else {
			return $this->RedirectLink;
		}
	}


	/**
	 * Retrieve the redirect page associated with this link mapping (where applicable).
	 * @return SiteTree
	 */
	public function getRedirectPage() {

		return ($this->RedirectType == 'Page' && $this->RedirectPageID) ? SiteTree::get_by_id('SiteTree', $this->RedirectPageID) : null;
	}

	/**
	 * Retrieve the redirect page title associated with this link mapping (where applicable).
	 * @return string
	 */
	public function getRedirectPageTitle() {

		$page = $this->getRedirectPage();
		return $page ? $page->Title : '-';
	}

}
