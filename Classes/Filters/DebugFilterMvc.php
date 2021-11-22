<?php

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

namespace YolfTypo3\SavFilters\Filters;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Debug filter
 *
 * @package sav_filters
 */
class DebugFilterMvc extends AbstractFilterMvc
{

    /**
     * Render
     *
     * @return void
     */
    public function renderFilter()
    {
        // Gets the selected filter
        $sessionFilters = self::getVariableFromSession('filters');
        $selectedFilterKey = self::getVariableFromSession('selectedFilterKey');

        // Gets the settings
        $modelClassName = self::getFilterSetting('modelClassName');
        $fieldsName = self::getFilterSetting('fieldsName');
        $queryResult = self::getFilterSetting('queryResult');

        if (! empty($selectedFilterKey)) {
            $sessionSelectedFilter = $sessionFilters[$selectedFilterKey]['Mvc'];
            $filterClassName = $sessionSelectedFilter['filterClassName'];
            $selectedFilterName = basename($filterClassName);

            if ($queryResult) {

                // Gets the repository
                $repository = $this->getRepository($modelClassName);

                // Gets the query and the filter contraints
                $query = $repository->createQuery();
                $filterConstraints = $filterClassName::getFilterWhereClause($query);
                if ($filterConstraints !== null) {
                    $query = $query->matching($query->logicalAnd([
                        $filterConstraints
                    ]));
                }

                // Gets and processes the rows
                $rows = $query->execute();
                $fieldsName = explode(',', $fieldsName);
                $values = [];
                foreach ($fieldsName as $fieldName) {
                    foreach ($rows as $row) {
                        $getter = 'get' . GeneralUtility::underscoredToUpperCamelCase(trim($fieldName));
                        if (method_exists($modelClassName, $getter)) {
                            $fieldValue = $row->$getter();
                            $values[trim($fieldName)] = $fieldValue;
                        } else {
                            $this->addErrorMessage('error.unknownMethod', [
                                $this->controller->getFilterName(),
                                $getter . '()'
                            ]);
                        }
                    }
                }

                $this->controller->getView()->assign('values', $values);
                $this->controller->getView()->assign('queryResult', 1);
            }
        }

        $this->controller->getView()->assign('selectedFilterKey', $selectedFilterKey);
        $this->controller->getView()->assign('selectedFilterName', $selectedFilterName);
        $this->controller->getView()->assign('sessionSelectedFilter', $sessionSelectedFilter);
    }
}
