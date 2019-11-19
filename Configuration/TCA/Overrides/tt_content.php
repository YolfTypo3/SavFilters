<?php
defined('TYPO3_MODE') or die();

$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['savfilters_default'] = 'layout,select_key';
// Adds the flexform field to plugin option
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['savfilters_default'] = 'pi_flexform';

// Adds the flexform DataStructure
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
    'savfilters_default',
    'FILE:EXT:sav_filters/Configuration/Flexforms/ExtensionFlexform.xml'
);

// Registers the Plugin to be listed in the Backend.
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'sav_filters',
	'Default',
	'LLL:EXT:sav_filters/Resources/Private/Language/locallang_db.xlf:tt_content.list_type_pi1'
);

// Adds addToInsertRecords() if any

?>
