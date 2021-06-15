<?php

$config = new OC\CodingStandard\Config();

$config
	->setUsingCache(true)
	->getFinder()
	->exclude('l10n')
	->exclude('vendor')
	->exclude('vendor-bin')
	->exclude('lib/composer')
	->notPath('/^c3.php/')
	->in(__DIR__);

return $config;

