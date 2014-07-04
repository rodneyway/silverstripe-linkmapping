# SilverStripe Link Mapping

This module will allow you to set up simple/regex link redirection mappings and customisation, either replacing the default automated URL handling or hooking into a page not found. This is useful for something such as legacy page redirection.

## Requirements

* SilverStripe 3.1.X

## Getting Started

* Place this directory in the root of your SilverStripe installation.
* `/dev/build` to rebuild the database.

## Overview

This module is designed to function with or without the CMS module present.

### Automated URL Handling

SilverStripe's default automated URL handling will be disabled when using this module out of the box, however it may be enabled again through configuration.

```yml
LinkMappingRequestFilter:
  replace_default: false
```

When a certain depth of link mappings has been reached, the server will return with a 404 response to prevent inefficient mappings or infinite recursion. The following is the default configuration:

```yml
LinkMappingRequestFilter:
  maximum_requests: 10
```

### Link Mappings

It is also possible to customise the listing of response codes, where the default will be a `303`.

#### Priority

When multiple link mappings end up being matched, the one to be used is determined based on a priority field and how specific the definition is.

#### Automatic Creation

When the URL segment of a site tree element has been updated, a link mapping will automatically be created. This functionality will be removed as soon as you enable SilverStripe's default automated URL handling (as it will no longer be required).

## Maintainer Contacts

	Andrew Short, andrew@silverstripe.com.au
	Nathan Glasl, nathan@silverstripe.com.au
