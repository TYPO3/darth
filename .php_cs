<?php

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->getFinder()->in(__DIR__)->exclude(['vendor', 'publish', 'working']);
return $config;
