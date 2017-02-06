<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;

require __DIR__ . '/../../vendor/autoload.php';


/**
 * Custom Groups context.
 */
class CustomGroupsContext implements Context, SnippetAcceptingContext {
	use Webdav;
}
