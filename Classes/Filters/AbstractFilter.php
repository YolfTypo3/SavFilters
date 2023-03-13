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

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use YolfTypo3\SavFilters\Controller\DefaultController;

/**
 * Abstract class for filters
 *
 * @package SavFilters
 */
abstract class AbstractFilter
{

    abstract protected function setAddWhereInSessionFilter();

    /**
     * Controller
     *
     * @var DefaultController
     */
    protected $controller;

    /**
     * The extension key with the content Uid
     *
     * @var string
     */
    protected $extensionKeyWithUid;

    /**
     * The content Uid
     *
     * @var int
     */
    protected $contentUid;

    /**
     * Query builder
     *
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * Connection
     *
     * @var Connection
     */
    protected $connection = null;

    /**
     * Http variables
     *
     * @var array
     */
    protected $httpVariables;

    /**
     * True if Http variables are reloaded from the session
     *
     * @var bool
     */
    protected $httpVariablesReloaded = false;

    /**
     * Force the execution of setSessionFields
     *
     * @var bool
     */
    protected $forceSetSessionFields = false;

    /**
     * If false the filter is not selected
     *
     * @var bool
     */
    protected $setFilterSelected = true;

    /**
     * Filters data
     *
     * @var array
     */
    protected $sessionFilter = [];

    /**
     * Selected filter key.
     *
     * @var string
     */
    protected $sessionFilterSelected = '';

    /**
     * Selected filter name.
     *
     * @var string
     */
    protected $selectedFilterName = '';

    /**
     * @var boolean
     */
    protected static $keepWhereClause = true;

    /**
     * Injects the controller
     *
     * @param DefaultController $controller
     * @return void
     */
    public function injectController(DefaultController $controller)
    {
        $this->controller = $controller;
    }

    /**
     * Renders the filter
     *
     * @return void
     */
    public function render()
    {
        // Initialisation
        if (! $this->filterInitialisation()) {
            return;
        }

        // Processes the http variables
        $this->httpVariablesProcessing();

        // Processes the filter
        $this->filterProcessing();

        // Sets the session variables
        $this->setSessionFields();
    }

    /**
     * Initialisation of the filter
     *
     * @return boolean (false if the filter is cancelled)
     */
    protected function filterInitialisation(): bool
    {
        // Gets the session variables
        $this->sessionFilter = $this->getDataFromSession('filters');
        $this->sessionFilterSelected = $this->getDataFromSession('selectedFilterKey');
        $this->selectedFilterName = $this->getDataFromSession('selectedFilterName');

        // Creates an extension key with the content uid
        $extensionKey = $this->controller->getRequest()->getControllerExtensionKey();
        $this->contentUid = $this->getContentUid();
        $this->extensionKeyWithUid = $extensionKey . '_' . $this->contentUid;

        // Gets the http variables
        $this->httpVariables = $this->controller->getRequest()->getArguments();

        // Checks if the filter is selected
        if (empty(GeneralUtility::_GET()) && empty(GeneralUtility::_POST())) {
            self::getTypoScriptFrontendController()->fe_user->setKey('ses', 'selectedFilterKey', null);
        } elseif ($this->httpVariables['cid'] == $this->contentUid) {
            // Checks if the filter is cancelled
            if (isset($this->httpVariables['cancel']) && $this->httpVariables['cid'] == $this->contentUid) {
                self::getTypoScriptFrontendController()->fe_user->setKey('ses', 'selectedFilterKey', null);
                $this->httpVariables = [
                    'controller' => $this->httpVariables['controller'],
                    'cid' => $this->httpVariables['cid'],
                    'cancel' => ''
                ];
            } else {
                // The filter is selected
                $this->sessionFilterSelected = $this->extensionKeyWithUid;
            }
        } else {
            if ($this->sessionFilterSelected == $this->extensionKeyWithUid) {
                $this->httpVariables = $this->getFieldInSessionFilter('httpVariables');
            } else {
                $this->httpVariables = [
                    'controller' => $this->httpVariables['controller'],
                    'cid' => $this->httpVariables['cid'],
                ];
            }
        }

        // Sets the keepWhereClause flag
        self::$keepWhereClause = $this->controller->getExtensionWhereClauseAction() == 0;

        $this->sessionFilter[$this->extensionKeyWithUid]['pageId'] = $this->getPageId();
        $this->sessionFilter[$this->extensionKeyWithUid]['contentUid'] = $this->contentUid;
        $this->sessionFilter[$this->extensionKeyWithUid]['tstamp'] = time();
        $this->sessionFilter[$this->extensionKeyWithUid]['libraryType'] = $this->controller->getLibraryType();

        $this->controller->getView()->assign('filterName', $this->extensionKeyWithUid);
        $this->controller->getView()->assign('cid', $this->contentUid);
        $this->controller->getView()->assign('templateName', $this->controller->getTemplateName());

        return true;
    }

    /**
     * Calls the various setters for the session
     *
     * @return void
     */
    protected function setSessionFields()
    {
        if ((count($this->httpVariables) > 0 && ! $this->httpVariablesReloaded) || $this->forceSetSessionFields) {
            // Calls the defaut setters
            $this->setAddWhereInSessionFilter();
            $this->setSearchInSessionFilter();
            $this->setSearchOrderInSessionFilter();
            $this->setKeepWhereClauseInSessionFilter();

            // Sets the filterSelected with the current extension
            if (! isset($this->httpVariables['cancel']) && ($this->httpVariables['cid'] == $this->contentUid) || $this->forceSetSessionFields) {
                $this->sessionFilterSelected = $this->extensionKeyWithUid;
                $this->selectedFilterName = basename(get_class($this));
                $this->setDataToSession('selectedFilterKey', $this->sessionFilterSelected);
                $this->setDataToSession('selectedFilterName', $this->selectedFilterName);
            }

            // Adds the http variables in the session filter
            $this->setFieldInSessionFilter('httpVariables', $this->httpVariables);

        }

        // Sets session data
        $this->setDataToSession('filters', $this->sessionFilter);
        $this->storeDataInSession();
    }

    /**
     * Setter for a field in session filter
     *
     * @param string $field
     *            Field to set
     * @param mixed $value
     *            The value
     * @return void
     */
    protected function setFieldInSessionFilter(string $field, $value)
    {
        $this->sessionFilter[$this->extensionKeyWithUid][$field] = $value;
    }

    /**
     * Getter for a field in session filter
     *
     * @param string $field
     *            Field to set
     * @return mixed
     */
    protected function getFieldInSessionFilter(string $field)
    {
        return $this->sessionFilter[$this->extensionKeyWithUid][$field] ?? null;
    }

    /**
     * Setter for search
     *
     * @return void
     */
    protected function setSearchInSessionFilter()
    {
        $search = $this->controller->getExtensionWhereClauseAction() == 1;
        $this->setFieldInSessionFilter('search', $search);
    }

    /**
     * Setter for order
     *
     * @return void
     */
    protected function setSearchOrderInSessionFilter()
    {
        $this->setFieldInSessionFilter('searchOrder', '');
    }

    /**
     * Setter for keepWhereClause
     *
     * @return void
     */
    protected function setKeepWhereClauseInSessionFilter()
    {
        $this->setFieldInSessionFilter('keepWhereClause', self::$keepWhereClause);
    }

    /**
     * Gets a http variable from string path
     *
     * @param string $path
     * @return mixed
     */
    protected function getHttpVariableFromPath(string $path)
    {
        $result = $this->httpVariables;
        $parts = explode('.', $path);
        // Removes the first part (post or get)
        if ($parts[0] == 'post' || $parts[0] == 'get') {
            unset($parts[0]);
        } else {
            DefaultController::addError('error.parameterMustBeginByPostOrGet', [
                $path
            ]);
            return null;
        }

        foreach ($parts as $part) {
            $result = $result[$part];
        }

        // Sanitizes the result
        if ($this->connection === null) {
            $fromClause = $this->controller->getFilterSetting('fromClause');
            if (empty($fromClause)) {
                // The pages tabble is taken by default
                $fromClause = 'pages';
            }
            $this->connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($fromClause);
        }
        $result = substr($this->connection->quote($result), 1, - 1);

        return $result;
    }

    /**
     * Creates the query builder
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder(): ?QueryBuilder
    {
        // Gets the query parts and the pid list
        $selectClause = $this->controller->getFilterSetting('selectClause');
        $fromClause = $this->controller->getFilterSetting('fromClause');
        $whereClause = $this->controller->getFilterSetting('whereClause');
        if (empty($whereClause)) {
            $whereClause = '1';
        }
        $groupByClause = $this->controller->getFilterSetting('groupByClause');
        $orderByClause = $this->controller->getFilterSetting('orderByClause');

        $pidList = $this->controller->getFilterSetting('pidList');
        if (method_exists($this, 'replaceSpecialParametersInWhereClause')) {
            $whereClause = $this->replaceSpecialParametersInWhereClause($whereClause);
        }

        // Creates the query builder
        $queryBuilder = $this->getQueryBuilder($fromClause);
        if ($queryBuilder === null) {
            return null;
        }

        $fromPart = $queryBuilder->getQueryPart('from')[0]['table'];
        $queryBuilder->select('*')
            ->where($queryBuilder->expr()
            ->in($fromPart . '.pid', $queryBuilder->createNamedParameter(GeneralUtility::intExplode(',', $pidList, true), Connection::PARAM_INT_ARRAY, ':pid')))
            ->add('select', $selectClause);

        // Adds the WHERE Clause if any
        if (! empty($whereClause)) {
            $whereClause = str_replace('###user###', $this->getTypoScriptFrontendController()->fe_user->user['uid'], $whereClause);
            $queryBuilder->add('where', $whereClause, true);
        }
        // Adds the GROUP BY Clause if any
        if (! empty($groupByClause)) {
            $queryBuilder->add('groupBy', $groupByClause);
        }
        // Adds the ORDER BY Clause if any
        if (! empty($orderByClause)) {
            $queryBuilder->add('orderBy', $orderByClause);
        }

        return $queryBuilder;
    }

    /**
     * Gets querier builder
     *
     * @param string $table
     * @return QueryBuilder|null
     */
    protected function getQueryBuilder(?string $table): ?QueryBuilder
    {
        if ($table === null) {
            return null;
        }
        // Filters the FROM clause to get the INNER JOIN parts if any);
        $match = [];
        preg_match_all('/^\s*(?P<From>\w+)(?P<InnerJoin>.+)?$/s', $table, $match);
        $fromClause = $match['From'][0];
        $innerJoinClause = $match['InnerJoin'][0] ?? '';

        $matches = [];
        preg_match_all('/\s+INNER JOIN\s+(?P<Table>\w+)(?P<Alias>\s+\w+)?\s+ON\s+(?P<OnLeft>[^=\s]+)\s*=\s*(?P<OnRight>[^\s]*)/', $innerJoinClause, $matches);

        // Creates the query builder
        if ($fromClause === null) {
            return null;
        }
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($fromClause);

        // Adds the INNER JOIN clause if any
        $leftTable = $fromClause;
        foreach ($matches[0] as $matchKey => $match) {
            $rightTable = $matches['Table'][$matchKey];
            $alias = trim($matches['Alias'][$matchKey]);
            if (empty($alias)) {
                $alias = $rightTable;
            }

            $queryBuilder->join($leftTable, $rightTable, $alias, $queryBuilder->expr()
                ->eq($matches['OnLeft'][$matchKey], $queryBuilder->quoteIdentifier($matches['OnRight'][$matchKey])));
            $leftTable = $alias;
        }
        $queryBuilder->from($fromClause);

        return $queryBuilder;
    }

    /**
     * Builds the filter WHERE clause
     *
     * @param string|null $whereClause
     * @return string|null
     */
    protected function buildFilterWhereClause(?string $whereClause): ?string
    {
        $additionalFilterWhereClause = $this->controller->getAdditionalFilterWhereClause();
        if (! empty($additionalFilterWhereClause)) {
            $whereClause = $whereClause . ' AND (' . $additionalFilterWhereClause . ')';
        }

        return $whereClause;
    }

    /**
     * Replaces parameters in the filter WHERE clause
     *
     * @param string|null $filterWhereClause
     * @return string|null
     */
    protected function replaceParametersInFilterWhereClauseQuery(?string $filterWhereClause): ?string
    {
        // Finds the variable paths
        $matches = [];
        if (preg_match_all('/{([^}]+)}/', $filterWhereClause, $matches)) {
            $result = $filterWhereClause;
            // Replaces each path by its value
            foreach ($matches[0] as $matchKey => $match) {
                $path = $matches[1][$matchKey];
                $value = $this->getHttpVariableFromPath($path);
                $result = str_replace('{' . $path . '}', $value, $result);
            }
            return $result;
        } else {
            return $filterWhereClause;
        }
    }

    /**
     * Gets the TypoScript Frontend Controller
     *
     * @return TypoScriptFrontendController
     */
    protected function getTypoScriptFrontendController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    /**
     * Gets user session
     *
     * @return FrontendUserAuthentication
     */
    protected function getFrontendUser(): FrontendUserAuthentication
    {
        return $this->getTypoScriptFrontendController()->fe_user;
    }

    /**
     * Gets the page id
     *
     * @return int
     */
    protected function getPageId(): int
    {
        // @extensionScannerIgnoreLine
        return $this->getTypoScriptFrontendController()->id;
    }

    /**
     * Gets the controller content object
     *
     * @return ContentObjectRenderer|null
     */
    protected function getControllerContentObject(): ?ContentObjectRenderer
    {
        // @extensionScannerIgnoreLine
        return $this->controller->getConfigurationManager()->getContentObject();
    }

    /**
     * Gets the content uid
     *
     * @return int
     */
    protected function getContentUid(): int
    {
        return $this->getControllerContentObject()->data['uid'];
    }

    /**
     * Gets data from session
     *
     * @param string $key
     * @return mixed
     */
    protected function getDataFromSession(string $key)
    {
        $frontEndUser = $this->getTypoScriptFrontendController()->fe_user;
        return $frontEndUser->getKey('ses', $key);
    }

    /**
     * Sets data to session
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function setDataToSession(string $key, $value)
    {
        $frontEndUser = $this->getTypoScriptFrontendController()->fe_user;
        $frontEndUser->setKey('ses', $key, $value);
    }

    /**
     * Stores the data in session
     *
     * @return void
     */
    protected function storeDataInSession()
    {
        $frontEndUser = $this->getTypoScriptFrontendController()->fe_user;
        // @extensionScannerIgnoreLine
        $frontEndUser->storeSessionData();
    }

}
