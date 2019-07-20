<?php
defined('TYPO3_MODE') or die();

// Configures the Dispatcher
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin('YolfTypo3.sav_filters', 'Default', [
    'Default' => 'default'
], [
    'Default' => 'default'
]);

// Adds a page module hook
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info']['savfilters_default']['sav_filters'] = \YolfTypo3\SavFilters\Hooks\PageLayoutView::class . '->getExtensionInformation';
?>
