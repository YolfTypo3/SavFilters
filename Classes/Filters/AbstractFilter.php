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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;
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
     * The extension key with the content Id
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
     * True if piVars are reloaded from the session
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
     * @return boolean (false if a problem occured)
     */
    protected function filterInitialisation()
    {
        // Gets the session variables
        $this->sessionFilter = $this->getTypoScriptFrontendController()->fe_user->getKey('ses', 'filters');
        $this->sessionFilterSelected = $this->getTypoScriptFrontendController()->fe_user->getKey('ses', 'selectedFilterKey');
        $this->selectedFilterName = $this->getTypoScriptFrontendController()->fe_user->getKey('ses', 'selectedFilterName');

        // Gets the http variables
        $this->httpVariables = $this->controller->getRequest()->getArguments();

        // Creates an extension key with the content uid
        $extensionKey = $this->controller->getRequest()->getControllerExtensionKey();
        $this->contentUid = $this->controller->getConfigurationManager()->getContentObject()->data['uid'];
        $this->extensionKeyWithUid = $extensionKey . '_' . $this->contentUid;

        // Sets the pageId
        if ($this->sessionFilter[$this->extensionKeyWithUid]['pageID'] != $this->getTypoScriptFrontendController()->id && $this->sessionFilterSelected == $this->extensionKeyWithUid) {
            unset($this->sessionFilterSelected);
            unset($this->selectedFilterName);
        }
        $this->sessionFilter[$this->extensionKeyWithUid]['pageID'] = $this->getTypoScriptFrontendController()->id;
        $this->sessionFilter[$this->extensionKeyWithUid]['contentID'] = $this->contentUid;
        $this->sessionFilter[$this->extensionKeyWithUid]['tstamp'] = time();

        // Recovers the http variable from the session
        $httpVariablesInSessionFilter = $this->getFieldInSessionFilter('httpVariables');
        if (! count($this->httpVariables) && GeneralUtility::_GP('sav_library_plus') && $httpVariablesInSessionFilter !== null) {
            $this->httpVariables = $httpVariablesInSessionFilter;
            $this->sessionFilterSelected = $this->extensionKeyWithUid;
            $this->controller->httpVariablesReloaded = true;
        }

        $this->controller->getView()->assign('cid', $this->contentUid);
        $this->controller->getView()->assign('filterName', $this->controller->getFilterName());

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

            // Sets the filterSelected with the current extension
            if (($this->httpVariables['cid'] == $this->contentUid) || $this->forceSetSessionFields) {
                $this->sessionFilterSelected = $this->extensionKeyWithUid;
                $this->selectedFilterName = basename(get_class($this));
                $this->getTypoScriptFrontendController()->fe_user->setKey('ses', 'selectedFilterKey', $this->sessionFilterSelected);
                $this->getTypoScriptFrontendController()->fe_user->setKey('ses', 'selectedFilterName', $this->selectedFilterName);
            }

            // Adds the http variables in the session filter
            $this->setFieldInSessionFilter('httpVariables', $this->httpVariables);
        }

        // Sets session data
        $this->getTypoScriptFrontendController()->fe_user->setKey('ses', 'filters', $this->sessionFilter);
        $this->getTypoScriptFrontendController()->fe_user->storeSessionData();
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
     * Gets a http variable from string path
     *
     * @param string $path
     * @return mixed
     */
    protected function getHttpVariableFromPath($path)
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
    protected function createQueryBuilder()
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
     * @return QueryBuilder
     */
    protected function getQueryBuilder($table)
    {
        // Filters the FROM clause to get the INNER JOIN parts if any);
        $match = [];
        preg_match('/(?P<From>\w+)(?P<InnerJoin>.*)/', $table, $match);
        $fromClause = $match['From'];
        $matches = [];
        preg_match_all('/\s+INNER JOIN\s+(?P<Table>\w+)(?P<Alias>\s+\w+)?\s+ON\s+(?P<OnLeft>[^=\s]+)\s*=\s*(?P<OnRight>[^\s]*)/', $match['InnerJoin'], $matches);

        // Creates the query builder
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
     * @param
     *            string whereClause
     * @return string
     */
    protected function buildFilterWhereClause($whereClause)
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
     * @param string $filterWhereClause
     * @return string
     */
    protected function replaceParametersInFilterWhereClauseQuery($filterWhereClause): string
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
    protected function getTypoScriptFrontendController()
    {
        return $GLOBALS['TSFE'];
    }

    /**
     * Gets the Page Repository
     *
     * @return PageRepository
     */
    protected function getPageRepository(): PageRepository
    {
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        return $pageRepository;
    }
}
?>
