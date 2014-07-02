<?php

/**
 *	Handles the current director request/response and appropriately delegates the link mapping control.
 *
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class LinkMappingRequestFilter implements RequestFilter {

	public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model) {

		return true;
	}

	public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {

		// Attempt to redirect towards a link mapping on 404 (page not found).

		if($response->getStatusCode() === 404) {

			// Clean up the URL.

			$link = $request->getURL();
			if(count($request->getVars()) > 1) {
				$link = $link . str_replace('url=' . $request->requestVar('url') . '&', '?', $_SERVER['QUERY_STRING']);
			}

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
				$response->redirect($map->getLink(), $responseCode);
			}
		}
		return true;
	}

}
