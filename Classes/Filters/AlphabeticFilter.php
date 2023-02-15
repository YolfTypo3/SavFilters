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

use YolfTypo3\SavFilters\Controller\DefaultController;

/**
 * Alphabetic filter
 *
 * @package sav_filters
 */
class AlphabeticFilter extends AbstractFilter
{

    /**
     * Setter for addWhere
     *
     * @return void
     */
    protected function setAddWhereInSessionFilter()
    {
        $addWhere = '';
        if (! empty($this->httpVariables['searchAlphabeticFilter']) && $this->controller->getFilterSetting('searchFields') !== null) {
            $searchFields = $this->controller->getFilterSetting('searchFields');
            $matches = [];
            // Gets the fields that are comma-separated but \, are kept
            if (preg_match_all('/((?:\\\\,|[^,])+)/', $searchFields, $matches)) {
                $filterWhereClause = '';
                foreach ($matches[1] as $match) {
                    $expression = trim(str_replace('\,', ',', $match)) . ' LIKE \'%{post.searchAlphabeticFilter}%\'';
                    if ($filterWhereClause != '') {
                        $filterWhereClause = $filterWhereClause . ' OR ' . $expression;
                    } else {
                        $filterWhereClause = $expression;
                    }
                }
            }

            $addWhere .= '(' . $this->replaceParametersInFilterWhereClauseQuery($filterWhereClause) . ')';
        } else {
            $selected = $this->httpVariables['selected'];
            $filterWhereClause = $this->controller->getFilterSetting('filterWhereClause');
            $addWhere .= $this->replaceParametersInFilterWhereClauseQuery($filterWhereClause);
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
        if (! empty($this->httpVariables['searchAlphabeticFilter']) && $this->controller->getFilterSetting('searchFields') !== null) {
            $this->controller->getView()->assign('searchAlphabeticFilter', $this->httpVariables['searchAlphabeticFilter']);
        } else {
            $selected = $this->httpVariables['selected'];
            $this->controller->getView()->assign('selected', $selected);
        }
    }

    /**
     * Processes the filter
     *
     * @return void
     */
    protected function filterProcessing()
    {
        // Inits the letters array
        $values = [];
        foreach (range('A', 'Z') as $letter) {
            $values[$letter] = 0;
        }

        // Creates the query builder
        $queryBuilder = $this->createQueryBuilder();

        // Gets the rows
        $rows = $queryBuilder->execute()->fetchAll(\PDO::FETCH_BOTH);
        $column = array_column($rows, 0);

        // Sets the values
        foreach ($column as $row) {
            $values[$row] = 1;
        }

        $this->controller->getView()->assign('values', $values);

        // Renders the search box if required
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
                DefaultController::addError('error.missingSearchFields', [
                    $extensionKey
                ]);
            }

            $this->controller->getView()->assign('addSearchBox', 1);
        }
    }

    /**
     * Replaces special parameters in the where Clause
     *
     * @param string $whereClause
     * @return string
     */
    protected function replaceSpecialParametersInWhereClause(string $whereClause): string
    {
        $allowedGroups = explode(',', $this->controller->getFilterSetting('allowedGroups'));
        if (! empty($allowedGroups)) {
            $groups = '';
            foreach ($allowedGroups as $allowedGroup) {
                if ($allowedGroup) {
                    if ($groups) {
                        $groups .= ' OR ';
                    }
                    $groups .= 'FIND_IN_SET(' . $allowedGroup . ', fe_users.usergroup)';
                }
            }
            $groups = ($groups ? '(' . $groups . ')' : '1');
            $whereClause = str_replace('{allowedGroups}', $groups, $whereClause);
        }

        return $whereClause;
    }
}
