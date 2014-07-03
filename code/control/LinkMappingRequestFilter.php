<?php

/**
 *	Handles the current director request/response and appropriately delegates the link mapping control.
 *
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class LinkMappingRequestFilter implements RequestFilter {

	private static $maximum_requests = 10;

	public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model) {

		return true;
	}

	public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {

		// Attempt to redirect towards a link mapping on 404 (page not found).

		if($response->getStatusCode() === 404) {

			// Clean up the URL, making sure an external URL comes through correctly.

			$link = $request->getURL(true);
			$link = str_replace(':/', '://', $link);

			// Retrieve and redirect towards a link mapping where the URL matches.

			$map = LinkMapping::get_by_link($link);
			if($map) {

				// Update the redirect response code appropriately.

				$responseCode = $map->ResponseCode;
				if(($responseCode === 301) && $map->ForwardPOSTRequest) {
					$responseCode = 308;
				}
				if(($responseCode === 303) && $map->ForwardPOSTRequest) {
					$responseCode = 307;
				}

				// Enforce a maximum number of redirects, preventing inefficient link mappings and infinite recursion.

				$counter = 1;
				$redirect = $map->getLink();
				while($next = LinkMapping::get_by_link($redirect)) {
					if($counter === self::$maximum_requests) {
						return true;
					}
					$counter++;
					$redirect = $next->getLink();
				}

				// Trigger the redirect now that we're at the end of the link mapping chain.

				$response->redirect($redirect, $responseCode);
			}
			else {

				// Trigger any 404 fallbacks.

			}
		}
		return true;
	}

}
