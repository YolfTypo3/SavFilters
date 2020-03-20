<?php
defined('TYPO3_MODE') or die();

// Configures the Dispatcher
if (version_compare(\YolfTypo3\SavFilters\Controller\DefaultController::getTypo3Version(), '10.0', '<')) {
    // @extensionScannerIgnoreLine
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin('YolfTypo3.sav_filters', 'Default',
        // Cachable controller actions
        [
            'Default' => 'default'
        ],
        // Non-cachable controller actions
        [
            'Default' => 'default'
        ]);
} else {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin('SavFilters', 'Default',
        // Cachable controller actions
        [
            \YolfTypo3\SavFilters\Controller\DefaultController::class => 'default'
        ],
        // Non-cachable controller actions
        [
            \YolfTypo3\SavFilters\Controller\DefaultController::class => 'default'
        ]);
}

// Adds a page module hook
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info']['savfilters_default']['sav_filters'] = \YolfTypo3\SavFilters\Hooks\PageLayoutView::class . '->getExtensionInformation';
?>
