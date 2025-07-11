<?php

$prestashop_config = new PrestaShop\CodingStandards\CsFixer\Config();
$prestashop_rules = $prestashop_config->getRules();
$prestashop_rules['blank_line_after_opening_tag'] = false;
$config = new PhpCsFixer\Config('PrestaShop coding standard');
$config->setRiskyAllowed(true)
    ->setRules($prestashop_rules);

/** @var Symfony\Component\Finder\Finder $finder */
$finder = $config->setUsingCache(true)->getFinder();
$finder->in(__DIR__)->exclude('vendor');

return $config;
