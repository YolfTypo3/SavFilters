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
use YolfTypo3\SavFilters\Controller\DefaultController;

/**
 * Months filter
 *
 * @package sav_filters
 */
class MonthsFilter extends AbstractFilter
{

    /**
     * Setter for addWhere
     *
     * @return void
     */
    protected function setAddWhereInSessionFilter()
    {
        $addWhere = '';
        if (! empty($this->httpVariables['searchMonthsFilter']) && $this->controller->getFilterSetting('searchFields') !== null) {
            $searchFields = $this->controller->getFilterSetting('searchFields');
            $matches = [];
            // Gets the fields that are comma-separated but \, are kept
            if( preg_match_all('/((?:\\\\,|[^,])+)/', $searchFields, $matches)) {
                $filterWhereClause = '';
                foreach ($matches[1] as $match) {
                    $filterWhereClause .= 'FROM_UNIXTIME(' . trim(str_replace('\,', ',', $match)) . ') LIKE \'%{post.searchMonthsFilter}%\' OR ';
                }
            }
            $addWhere .= '(' . $this->replaceParametersInFilterWhereClauseQuery($filterWhereClause) . '0)';
        } else {
            $selected = $this->httpVariables['selected'];
            if ($selected != 'all') {
                $filterWhereClause = $this->controller->getFilterSetting('filterWhereClause');
                $addWhere .= $this->replaceParametersInFilterWhereClauseQuery($filterWhereClause);
            }
        }
        $this->setFieldInSessionFilter('addWhere', $this->buildFilterWhereClause($addWhere));
    }

    /**
     * Http variables processing
     *
     * @return void
     */
    protected function httpVariablesProcessing()
    {
        // Checks if a search was posted
        if (! empty($this->httpVariables['searchMonthsFilter']) && $this->controller->getFilterSetting('searchFields') !== null) {
            $this->controller->getView()->assign('searchMonthsFilter', $this->httpVariables['searchMonthsFilter']);
        } else {
            $this->controller->getView()->assign('selected', $this->httpVariables['selected']);
        }
    }

    /**
     * Processes the filter
     *
     * @return void
     */
    protected function filterProcessing()
    {
        // Inits the months array
        $values = [];
        $backwardMonths = $this->controller->getFilterSetting('backwardMonths');
        $forwardMonths = $this->controller->getFilterSetting('forwardMonths');
        for ($i = - $backwardMonths; $i < 12 + $forwardMonths; $i ++) {
            $monthName = ucfirst(strftime('%b', strtotime($i . 'months 01/01/2000')));
            $values[$i] = [
                'label' => $monthName,
                'active' => 0
            ];
        }

        // Creates the query builder
        $queryBuilder = $this->createQueryBuilder();

        // Gets the rows
        $rows = $queryBuilder->execute()->fetchAll(\PDO::FETCH_BOTH);
        $column = array_column($rows, 0);

        // Sets the values
        foreach ($column as $row) {
            // Gets the field value
            $fieldValue = new \DateTime($row);

            // Computes the interval in months from the begining of the year
            $differenceFromFirstDayOfYear = (new \DateTime('first day of January this year'))->diff($fieldValue);
            $intervalInMonths = (int) $differenceFromFirstDayOfYear->format('%R%m');

            // Marks the month as active if in the allowed range
            if ($intervalInMonths >= - $backwardMonths && $intervalInMonths < 12 + $forwardMonths) {
                $values[$intervalInMonths]['active'] = (new \DateTime('first day of January this year ' . $intervalInMonths . 'month'))->format('Y-m');
            }
        }

        $this->controller->getView()->assign('values', $values);

        $this->renderSearchBox();
    }

    /**
     * Renders the search box if required
     *
     * @return void
     */
    protected function renderSearchBox()
    {
        $extensionKey = $this->controller->getRequest()->getControllerExtensionKey();
        // Search button
        if ($this->controller->getFilterSetting('searchBox')) {
            if (! $this->controller->getFilterSetting('searchFields')) {
                DefaultController::addError('MonthFilter:error.missingSearchFields', [
                    $extensionKey
                ]);
            }
            // Sets the search icon
            $searchIcon = $this->controller->getFilterSetting('searchIcon');
            if (empty($searchIcon)) {
                $searchIcon = 'EXT:' . $extensionKey . '/Resources/Public/Icons/search.png';
            }

            $this->controller->getView()->assign('addSearchBox', 1);
            $this->controller->getView()->assign('searchIcon', $searchIcon);
        }
    }

    /**
     * Replaces special parameters in the where clause
     *
     * @param string $whereClause
     * @return string
     */
    protected function replaceSpecialParametersInWhereClause($whereClause): string
    {
        $backwardMonths = $this->controller->getFilterSetting('backwardMonths');
        $forwardMonths = $this->controller->getFilterSetting('forwardMonths');
        $whereClause = str_replace('{backwardMonths}', $backwardMonths, $whereClause);
        $whereClause = str_replace('{forwardMonths}', $forwardMonths, $whereClause);

        return $whereClause;
    }
}
?>