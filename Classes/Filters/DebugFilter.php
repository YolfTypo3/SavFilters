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
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Debug filter
 *
 * @package sav_filters
 */
class DebugFilter extends AbstractFilter
{

    /**
     * Setter for addWhere
     *
     * @return void
     */
    protected function setAddWhereInSessionFilter()
    {}

    /**
     * piVars processing
     *
     * @return void
     */
    protected function httpVariablesProcessing()
    {}

    /**
     * Processes the filter
     *
     * @return void
     */
    protected function filterProcessing()
    {
        if ($this->controller->getFilterSetting('queryResult')) {

            // Creates the query builder
            $queryBuilder = $this->createQueryBuilder();

            // Gets the additional WHERE clause in the selected filter
            $addWhere = $this->sessionFilter[$this->sessionFilterSelected]['addWhere'];
            if (! empty($addWhere)) {
                $queryBuilder->add('where', $addWhere, true);
            } else {
                $queryBuilder->add('where', 1, true);
            }

            // Gets the rows
            $values = $queryBuilder->execute()->fetchAll();

            $this->controller->getView()->assign('values', $values);
            $this->controller->getView()->assign('queryResult', 1);
        }

        $selectedFilterKey = $this->getTypoScriptFrontendController()->fe_user->getKey('ses', 'selectedFilterKey');
        $this->controller->getView()->assign('selectedFilterKey', $selectedFilterKey);
        $this->controller->getView()->assign('selectedFilterName', $this->selectedFilterName);
        $this->controller->getView()->assign('sessionSelectedFilter', $this->sessionFilter[$selectedFilterKey]);
    }
}
?>