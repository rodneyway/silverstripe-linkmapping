<?php

/**
 *	Handles the current director request/response and appropriately delegates the link mapping control.
 *
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class LinkMappingRequestFilter implements RequestFilter {

	private static $replace_default = true;
	private static $maximum_requests = 10;

	public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model) {

		return true;
	}

	/**
	 *	Attempt to redirect towards a link mapping.
	 */

	public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {

		// Allow customisation around whether the default SS automated redirect is replaced, where a page not found (404) will always attempt to trigger a link mapping.

		$status = $response->getStatusCode();
		if((self::$replace_default || ($status === 404)) && ($map = $this->getLinkMapping($request)) && ($redirect = $this->getRedirectLink($map))) {

			// Update the redirect response code appropriately.

			$responseCode = (int)$map->ResponseCode;
			if($responseCode === 0) {
				$responseCode = 303;
			}
			else if(($responseCode === 301) && $map->ForwardPOSTRequest) {
				$responseCode = 308;
			}
			else if(($responseCode === 303) && $map->ForwardPOSTRequest) {
				$responseCode = 307;
			}

			// Traverse the link mapping chain and direct the response towards the last.

			$response->redirect($redirect, $responseCode);
		}

		// Trigger any fallbacks.

		else if($status === 404) {
		}
		return true;
	}

	/**
	 *	Retrieve a link mapping where the URL matches appropriately.
	 *	@return LinkMapping
	 */

	public function getLinkMapping(SS_HTTPRequest $request) {

		// Clean up the URL, making sure an external URL comes through correctly.

		$link = $request->getURL(true);
		$link = str_replace(':/', '://', $link);

		// Return the appropriate link mapping, otherwise trigger any defined fallbacks.

		$map = LinkMapping::get_by_link($link);
		if($map) {
			return $map;
		}
		return null;
	}

	/**
	 *	Traverse the link mapping chain and return the final redirect link.
	 *	@return string
	 */

	public function getRedirectLink(LinkMapping $map) {

		$counter = 1;
		$redirect = $map->getLink();
		while($next = LinkMapping::get_by_link($redirect)) {

			// Enforce a maximum number of redirects, preventing inefficient link mappings and infinite recursion.

			if($counter === self::$maximum_requests) {
				return null;
			}
			$counter++;
			$redirect = $next->getLink();
		}
		return $redirect;
	}

}
