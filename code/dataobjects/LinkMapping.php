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
		'LinkType' => "Enum('Simple, Regular Expression', 'Simple')",
		'MappedLink'   => 'Varchar(255)',
		'RedirectType' => "Enum('Page, Link', 'Link')",
		'RedirectLink' => 'Varchar(255)',
		'RedirectPageID' => 'Int',
		'ResponseCode' => 'Int',
		'ForwardPOSTRequest' => 'Boolean',
		'Priority' => 'Int'
	);

	private static $defaults = array(
		'ResponseCode' => 303
	);

	private static $summary_fields = array(
		'MappedLink',
		'RedirectType',
		'Stage',
		'RedirectPageLink',
		'RedirectPageTitle'
	);

	private static $field_labels = array(
		'RedirectPageLink' => 'Redirect Link',
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

	// Use the initial request URL for a redirect link regular expression replacement.

	private $matchedURL;

	public function setMatchedURL($matchedURL) {

		$this->matchedURL = $matchedURL;
	}

	/**
	 * Returns a link mapping for a link if one exists.
	 *
	 * @param  string $link
	 * @return LinkMapping
	 */
	public static function get_by_link($link) {

		$link = self::unify_link(Director::makeRelative($link));
		$linkParts = explode('?', $link);
		$url = Convert::raw2sql($linkParts[0]);

		// Retrieve the link mappings, ordered by query string and priority.

		$matches = LinkMapping::get()->sort(array(
			'MappedLink' => 'DESC',
			'Priority' => 'DESC',
			'ID' => 'DESC'
		));

		// Retrieve the matching link mappings depending on the database connection type.

		if(DB::getConn() instanceof MySQLDatabase) {

			// Filter the link mappings from a database level (currently only limited to MySQL due to the syntax/support).

			$matches = $matches->where(
				"((LinkType = 'Simple') AND ((MappedLink = '{$url}') OR (MappedLink LIKE '{$url}?%'))) OR ((LinkType = 'Regular Expression') AND ('{$url}' REGEXP MappedLink))"
			);
		}
		else {

			// Filter the link mappings manually.

			$filtered = ArrayList::create();
			foreach($matches as $match) {
				if((($match->LinkType === 'Simple') && (($match->MappedLink === $url) || (strpos($match->MappedLink, "{$url}?") === 0))) || (($match->LinkType === 'Regular Expression') && (preg_match("|{$match->MappedLink}|", $url)))) {
					$filtered->push($match);
				}
			}
			$matches = $filtered;
		}

		// Determine which link mapping should be returned.

		$queryParams = array();
		if(isset($linkParts[1])) {
			parse_str($linkParts[1], $queryParams);
		}
		if($matches->count()) {
			foreach($matches as $match) {

				// Make sure the link mapping matches the current stage, where a staging only link mapping will return 'Live' for only '?stage=Stage'.

				if($match->getStage() !== 'Stage') {

					// Check for a match with the same GET variables in any order.

					$matchQueryString = explode('?', $match->MappedLink);
					if(isset($matchQueryString[1])) {
						$matchParams = array();
						parse_str($matchQueryString[1], $matchParams);

						// Make sure each URL parameter matches against the link mapping.

						if($matchParams == $queryParams){
							$match->setMatchedURL($linkParts[0]);
							return $match;
						}
					}
					else {

						// Otherwise return the first link mapping which matches the current stage.

						$match->setMatchedURL($linkParts[0]);
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
		Requirements::css(LINK_MAPPING_PATH . '/css/link-mapping.css');
		$fields->removeByName('RedirectType');
		$fields->removeByName('RedirectLink');
		$fields->removeByName('RedirectPageID');
		$fields->removeByName('ResponseCode');
		$fields->removeByName('ForwardPOSTRequest');
		$fields->removeByName('Priority');

		$fields->insertBefore(HeaderField::create(
			'MappedLinkHeader', $this->fieldLabel('MappedLinkHeader')
		), 'LinkType');

		// Generate the link mapping priority selection from 1 - 10.

		$range = array();
		for($i = 1; $i <= 10; $i++) {
			$range[$i] = $i;
		}
		$fields->addFieldToTab('Root.Main', DropdownField::create('Priority', _t('LinkMapping.PRIORITY', 'Priority'), $range));
		$fields->addFieldToTab('Root.Main', HeaderField::create(
			'RedirectToHeader', $this->fieldLabel('RedirectToHeader')
		));

		// Collate the redirect settings into a single grouping.

		$redirect = FieldGroup::create()->addExtraClass('redirect-link');
		if(ClassInfo::exists('SiteTree')) {
			$pageLabel = $this->fieldLabel('RedirectToPage');
			$linkLabel = $this->fieldLabel('RedirectToLink');
			$fields->addFieldToTab('Root.Main', SelectionGroup::create('RedirectType', array(
				"Page//$pageLabel" => TreeDropdownField::create('RedirectPageID', '', 'Page'),
				"Link//$linkLabel" => $redirect
			))->addExtraClass('field redirect'));
			$redirect->push($redirectLink = TextField::create('RedirectLink', ''));
		}
		else {
			$redirect->setTitle(_t('LinkMapping.REDIRECTLINK', 'Redirect Link'));
			$fields->addFieldToTab('Root.Main', $redirect);
			$redirect->push($redirectLink = TextField::create('RedirectLink', ''));
		}
		$redirect->push(CheckboxField::create('ValidateExternalURL'));
		$redirectLink->setRightTitle('External URLs will require the protocol explicitly defined');

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

		// Collate the response settings into a single grouping.

		$response = FieldGroup::create(
			DropdownField::create('ResponseCode', '', $responseCodes),
			CheckboxField::create('ForwardPOSTRequest', _t('LinkMapping.FORWARDPOSTREQUEST', 'Forward POST Request'))
		)->setTitle(_t('LinkMapping.RESPONSECODE', 'Response Code'))->addExtraClass('response');
		$fields->addFieldToTab('Root.Main', $response);

		return $fields;
	}

	public function onBeforeWrite() {

		parent::onBeforeWrite();
		$this->MappedLink = self::unify_link($this->MappedLink);
		$this->RedirectLink = self::unify_link($this->RedirectLink);
	}

	public function validate() {

		$result = parent::validate();
		if($this->ValidateExternalURL && $this->RedirectLink) {

			// The following validation translation comes from: https://gist.github.com/dperini/729294 and http://mathiasbynens.be/demo/url-regex

			$this->RedirectLink = trim($this->RedirectLink, '!"#$%&\'()*+,-./@:;<=>[\\]^_`{|}~');
			preg_match('%^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@|\d{1,3}(?:\.\d{1,3}){3}|(?:(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)(?:\.(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(?:\.[a-z\x{00a1}-\x{ffff}]{2,6}))(?::\d+)?(?:[^\s]*)?$%iu', $this->RedirectLink) ?
				$result->valid() :
				$result->error('External URL validation failed!');
		}
		return $result;
	}

	public function fieldLabels($includerelations = true) {
		return parent::fieldLabels($includerelations) + array(
			'MappedLinkHeader' => _t('LinkMapping.MAPPEDLINK', 'Mapped Link'),
			'RedirectToHeader' => _t('LinkMapping.REDIRECTTO', 'Redirect To'),
			'RedirectionType'  => _t('LinkMapping.REDIRECTIONTYPE', 'Redirection Type'),
			'RedirectToPage'   => _t('LinkMapping.REDIRTOPAGE', 'Redirect to a Page'),
			'RedirectToLink'   => _t('LinkMapping.REDIRTOLINK', 'Redirect to a Link')
		);
	}

	/**
	 * @return string
	 */
	public function getLink() {

		if ($page = $this->getRedirectPage()) {
			return $page->Link();
		} else {
			return ($this->LinkType === 'Regular Expression') ?
				preg_replace("|{$this->MappedLink}|i", $this->RedirectLink, $this->matchedURL) : $this->RedirectLink;
		}
	}


	/**
	 * Retrieve the redirect page associated with this link mapping (where applicable).
	 * @return SiteTree
	 */
	public function getRedirectPage() {

		return ($this->RedirectType === 'Page' && $this->RedirectPageID) ? SiteTree::get_by_id('SiteTree', $this->RedirectPageID) : null;
	}

	/**
	 * Retrieve the stage of this link mapping.
	 * @return string
	 */
	public function getStage() {

		return (($this->RedirectType !== 'Link') && ClassInfo::exists('SiteTree')) ? (
			$this->getRedirectPage() ?
				'Live' : 'Stage'
		) : '-';
	}

	/**
	 * Retrieve the redirect page link associated with this link mapping.
	 * @return string
	 */
	public function getRedirectPageLink() {

		return (($this->RedirectType !== 'Link') && ClassInfo::exists('SiteTree')) ? (
			(($page = $this->getRedirectPage()) && $page->Link()) ?
				$page->Link() : '-'
		) : ($this->RedirectLink ? $this->RedirectLink : '-');
	}

	/**
	 * Retrieve the redirect page title associated with this link mapping (where applicable).
	 * @return string
	 */
	public function getRedirectPageTitle() {

		$page = $this->getRedirectPage();
		return $page ?
			$page->Title : '-';
	}

}
