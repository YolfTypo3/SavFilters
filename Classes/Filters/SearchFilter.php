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
 * Search filter
 *
 * @package sav_filters
 */
class SearchFilter extends AbstractFilter
{

    /**
     * Setter for addWhere
     *
     * @return void
     */
    protected function setAddWhereInSessionFilter()
    {
        $addWhere = '';
        if (! empty($this->httpVariables['searchSearchFilter']) && $this->controller->getFilterSetting('searchFields') !== null) {
            $searchFields = $this->controller->getFilterSetting('searchFields');
            $matches = [];
            // Gets the fields that are comma-separated but \, are kept
            if( preg_match_all('/((?:\\\\,|[^,])+)/', $searchFields, $matches)) {
                $filterWhereClause = '';
                foreach ($matches[1] as $match) {
                    $filterWhereClause .= trim(str_replace('\,', ',', $match)) . ' LIKE \'%{post.searchSearchFilter}%\' OR ';
                }
                $addWhere .= '(' . $this->replaceParametersInFilterWhereClauseQuery($filterWhereClause) . '0)';
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
        if (! empty($this->httpVariables['searchSearchFilter']) && $this->controller->getFilterSetting('searchFields') !== null) {
            $this->controller->getView()->assign('searchSearchFilter', $this->httpVariables['searchSearchFilter']);
        }
    }

    /**
     * Processes the filter
     *
     * @return void
     */
    protected function filterProcessing()
    {
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

        if (! $this->controller->getFilterSetting('searchFields')) {
            DefaultController::addError('AlphabeticFilter:error.missingSearchFields', [
                $extensionKey
            ]);
        }
    }
}
