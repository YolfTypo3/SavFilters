<?php

declare(strict_types=1);

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

use TYPO3\CMS\Core\Utility\ClassNamingUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Repository;
use YolfTypo3\SavFilters\Controller\DefaultController;
use YolfTypo3\SavFilters\Parser\WhereClauseParser;

/**
 * Debug filter
 *
 * @package sav_filters
 */
class DebugFilterMvc extends DebugFilter
{
    /**
     * Processes the filter
     *
     * @return void
     */
    protected function filterProcessing()
    {
        // Checks if the filter should be processed
        $libraryType = $this->sessionFilter[$this->sessionFilterSelected]['libraryType'];
        if ($libraryType != DefaultController::FilterForSavLibraryMvc) {
            return;
        }

        // Gets the settings
        $modelClassName = $this->controller->getFilterSetting('modelClassName');
        $fieldsName = $this->controller->getFilterSetting('fieldsName');
        $queryResult = $this->controller->getFilterSetting('queryResult');

        if ($queryResult) {

            // Gets the repository
            $repository = $this->getRepository($modelClassName);

            // Gets the query and the filter contraints
            $query = $repository->createQuery();

            // Gets the additional WHERE clause in the selected filter
            $addWhere = $this->sessionFilter[$this->sessionFilterSelected]['addWhere'];
            if (! empty($addWhere)) {
                $whereClauseParser = GeneralUtility::makeInstance(WhereClauseParser::class);
                $whereClauseParser->injectRepository($repository);
                $query = $query->matching($whereClauseParser->processWhereClause($query, $addWhere));

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
            }
            $this->controller->getView()->assign('values', $values);
            $this->controller->getView()->assign('queryResult', 1);
        }

        $selectedFilterKey = $this->getTypoScriptFrontendController()->fe_user->getKey('ses', 'selectedFilterKey');
        $this->controller->getView()->assign('selectedFilterKey', $selectedFilterKey);
        $this->controller->getView()->assign('selectedFilterName', $this->selectedFilterName);
        $this->controller->getView()->assign('sessionSelectedFilter', $this->sessionFilter[$selectedFilterKey]);
    }

    /**
     * Gets the repository
     *
     * @param string $modelClassName
     * @return Repository
     */
    protected function getRepository($modelClassName): Repository
    {
        $repositoryClassName = ClassNamingUtility::translateModelNameToRepositoryName($modelClassName);
        $repository = GeneralUtility::makeInstance($repositoryClassName);
        return $repository;
    }

}
