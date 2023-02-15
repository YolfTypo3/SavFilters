<?php

defined('TYPO3') or die();

(function () {

    // Configures the Dispatcher
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'SavFilters',
        'Default',
        // Cachable controller actions
        [
            \YolfTypo3\SavFilters\Controller\DefaultController::class => 'default',
        ],
        // Non-cachable controller actions
        [
            \YolfTypo3\SavFilters\Controller\DefaultController::class => 'default',
        ]
    );

    // Adds a page module hook
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info']['savfilters_default']['sav_filters'] = \YolfTypo3\SavFilters\Hooks\PageLayoutView::class . '->getExtensionInformation';

})();

