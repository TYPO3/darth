<?php
/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *  $ vendor/bin/php-cs-fixer fix --config=.php-cs.dist
 */

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->addRules(['declare_strict_types' => true]);
$config->getFinder()->in([__DIR__ . '/src', __DIR__ . '/tests']);
return $config;
