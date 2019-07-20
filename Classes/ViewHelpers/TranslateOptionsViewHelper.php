<?php
namespace YolfTypo3\SavFilters\ViewHelpers;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with TYPO3 source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;


/**
 * Compresses parameters
 *
 * @package SavLibraryPlus
 */
class TranslateOptionsViewHelper extends AbstractViewHelper
{

    /**
     * Initializes arguments.
     */
    public function initializeArguments()
    {
        $this->registerArgument('tableName', 'string', 'Table name', true, null);
        $this->registerArgument('fieldName', 'string', 'Field name', true, null);
    }

    /**
     * Renders the function
     *
     * @return mixed
     */
    public function render()
    {
        // Gets the arguments
        $tableName = trim($this->arguments['tableName']);
        $fieldName = trim($this->arguments['fieldName']);
        $options = $this->renderChildren();

        // Gets the items configuration in the TCA
        $items = $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config']['items'];

        // Translate the options
        $result = [];
        foreach ($options as $option) {
            $result[$option] = LocalizationUtility::translate($items[$option][0]);
        }

        return $result;
    }
}
?>
