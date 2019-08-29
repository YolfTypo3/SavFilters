<?php
namespace YolfTypo3\SavFilters\Filters;

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
 * The TYPO3 project - inspiring people to share
 */
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ClassNamingUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * Alphabetic filter
 *
 * @package sav_filters
 */
class AlphabeticFilterMvc extends AbstractFilterMvc
{

    /**
     * Render
     *
     * @return void
     */
    public function renderFilter()
    {
        // Gets the model class name
        $modelClassName = self::getFilterSetting('modelClassName');

        // Gets the repository
        $repositoryClassName = ClassNamingUtility::translateModelNameToRepositoryName($modelClassName);
        $repository = $this->objectManager->get($repositoryClassName);

        // Gets the rows
        $rows = $repository->findAll();

        // Inits the letters array
        $values = [];
        foreach (range('A', 'Z') as $letter) {
            $values[$letter] = 0;
        }

        // Sets the letters array from the rows
        $fieldNames = explode(',', self::getFilterSetting('fieldName'));
        foreach ($fieldNames as $fieldName) {
            foreach ($rows as $row) {
                $getter = 'get' . GeneralUtility::underscoredToUpperCamelCase(trim($fieldName));
                if (method_exists($modelClassName, $getter)) {
                    $fieldValue = $row->$getter();
                    $values[strtoupper($fieldValue[0])] = 1;
                } else {
                    $this->addErrorMessage('error.unknownMethod', [
                        $getter . '()'
                    ]);
                    return;
                }
            }
        }

        // Gets the seachFields and adds a search box if any
        $searchFields = self::getFilterSetting('searchFields');
        if (! empty($searchFields)) {
            $this->controller->getView()->assign('addSearchBox', 1);

            // Sets the search icon
            $searchIcon = $this->controller->getFilterSetting('searchIcon');
            if (empty($searchIcon)) {
                $extensionKey = $this->controller->getRequest()->getControllerExtensionKey();
                $searchIcon = 'EXT:' . $extensionKey . '/Resources/Public/Icons/search.png';
            }
            $this->controller->getView()->assign('searchIcon', $searchIcon);

            // Sets the search value
            $searchValue = self::getParameterFromFilterContext('searchAlphabeticFilter');
            $this->controller->getView()->assign('searchAlphabeticFilter', $searchValue);
        }

        $this->controller->getView()->assign('values', $values);
        $this->controller->getView()->assign('filterContext', self::$filterContext);
        $this->controller->getView()->assign('filterName', $this->controller->getFilterName());
        $this->controller->getView()->assign('cid', self::$contentUid);
    }

    /**
     * Adds the filter WHERE clause part to the query
     *
     * @param QueryInterface $query
     * @return QueryInterface
     */
    public static function filterWhereClause($query)
    {
        // Gets the parameters from the filter context
        $arguments = self::getParameterFromFilterContext('selected');
        $searchValue = self::getParameterFromFilterContext('searchAlphabeticFilter');

        // Builds the query constraints
        if (strlen($arguments) == 1 && $arguments >= 'A' && $arguments <= 'Z') {
            // A letter is selected
            $fieldNames = explode(',', self::getFilterSetting('fieldName'));
            $constraints = [];
            foreach ($fieldNames as $fieldName) {
                $constraints[] = $query->like($fieldName, $arguments . '%');
            }
            return $query->logicalOr($constraints);
        } elseif ($arguments == 'all') {
            // All is selected, return a constraint always true
            $constraints = [];
            $constraints[] = $query->greaterThan('uid', 0);
            return $query->logicalOr($constraints);
        } elseif (! empty($searchValue)) {
            // A search is requested
            $searchFields = explode(',', self::getFilterSetting('searchFields'));
            $constraints = [];
            foreach ($searchFields as $searchField) {
                $constraints[] = $query->like($searchField, '%' . $searchValue . '%');
            }
            return $query->logicalOr($constraints);
        } else {
            return null;
        }
    }
}
?>