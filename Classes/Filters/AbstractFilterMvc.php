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
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use YolfTypo3\SavFilters\Controller\DefaultController;

/**
 * Abstract class for SAV Library Mvc filters
 *
 * @package SavFilters
 */
abstract class AbstractFilterMvc
{

    /**
     * True if the filter is selected
     *
     * @var boolean
     */
    protected static $filterIsSelected = FALSE;

    /**
     * Filter context
     *
     * @var array
     */
    protected static $filterContext = null;

    /**
     * Filter settings
     *
     * @var array
     */
    protected static $filterSettings = null;

    /**
     * The controller
     *
     * @var DefaultController
     */
    protected $controller;

    /**
     * The content Uid
     *
     * @var int
     */
    protected static $contentUid;

    /**
     * The extension key with the content Uid
     *
     * @var string
     */
    protected static $extensionKeyWithUid;

    /**
     * The object manager
     *
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     *
     * @var boolean
     */
    protected static $keepWhereClause = true;

    /**
     * Injects the object manager
     *
     * @param ObjectManagerInterface $objectManager
     * @return void
     */
    public function injectObjectManager(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
        $this->arguments = $this->objectManager->get(Arguments::class);
    }

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
     * Render
     *
     * @return void
     */
    public function render()
    {
        // Creates an extension key with the content uid
        $extensionKey = $this->controller->getRequest()->getControllerExtensionKey();
        self::$contentUid = $this->controller->getConfigurationManager()->getContentObject()->data['uid'];
        self::$extensionKeyWithUid = $extensionKey . '_' . self::$contentUid;

        // Removes the selected filter key if there is no parameter
        if (empty(GeneralUtility::_GET()) && empty(GeneralUtility::_POST())) {
            self::getTypoScriptFrontendController()->fe_user->setKey('ses', 'selectedFilterKey', null);
        }

        // Sets the keepWhereClause flag
        self::$keepWhereClause = $this->controller->getExtensionWhereClauseAction() == 0;

        // Gets the filter context
        self::$filterSettings = $this->getFilterSettings();
        self::$filterContext = self::getFilterContext();

        // Renders the filter
        $this->renderFilter();
        self::setFilterContextInSession();
    }

    /**
     * Gets the filter WHERE clause part to the query
     *
     * @param QueryInterface $query
     * @return QueryInterface
     */
    public static function getFilterWhereClause($query)
    {
        // Gets the filter settings from the session
        self::$filterSettings = self::getFilterSettingsFromSession();

        // Gets the parameters from the filter context saved in session
        self::$filterContext = self::getFilterContextFromSession();

        return static::filterWhereClause($query);
    }

    /**
     * Gets keep WHERE clause flag
     *
     * @return integer
     */
    public static function keepWhereClause()
    {
        return static::$keepWhereClause;
    }

    /**
     * Gets the filter settings
     *
     * @return array
     */
    protected function getFilterSettings()
    {
        $settings = $this->controller->getSettings();
        $shortClassName = lcfirst((new \ReflectionClass($this))->getShortName());
        return $settings['flexform'][$shortClassName];
    }

    /**
     * Gets a filter setting
     *
     * @param string $parameter
     * @return mixed
     */
    protected static function getFilterSetting($parameter)
    {
        return self::$filterSettings[$parameter];
    }

    /**
     * Gets a variable from the session
     *
     * @param string $variableName
     * @return void
     */
    protected static function getVariableFromSession($variableName)
    {
        return self::getTypoScriptFrontendController()->fe_user->getKey('ses', $variableName);
    }

    /**
     * Gets the filter context
     *
     * @return array
     */
    protected static function getFilterContext()
    {
        $filterContext = self::getFilterContextFromHttpRequest();
        if (empty($filterContext)) {
            $filterContext = self::getFilterContextFromSession();
            if (empty($filterContext)) {
                $filterContext = [];
            }
        }

        return $filterContext;
    }

    /**
     * Gets the filter context from the http request
     *
     * @return array
     */
    protected static function getFilterContextFromHttpRequest()
    {
        $filterContext = GeneralUtility::_GET('tx_savfilters_default');

        if ($filterContext['cid'] != self::$contentUid) {
            $filterContext = GeneralUtility::_POST('tx_savfilters_default');
            if ($filterContext['cid'] != self::$contentUid) {
                self::$filterIsSelected = false;
                return [];
            }
        }
        self::$filterIsSelected = true;

        return $filterContext;
    }

    /**
     * Gets the filter context from the session
     *
     * @return array
     */
    protected static function getFilterContextFromSession()
    {
        // Gets the session variables
        $sessionFilters = self::getVariableFromSession('filters');
        $selectedFilterKey = self::getVariableFromSession('selectedFilterKey');

        $filterClassName = self::getFilterClassName();
        if ($sessionFilters[$selectedFilterKey]['Mvc']['pageId'] != self::getPageId() || $sessionFilters[$selectedFilterKey]['Mvc']['filterClassName'] != $filterClassName) {
            return [];
        } else {
            return $sessionFilters[$selectedFilterKey]['Mvc']['context'];
        }
    }

    /**
     * Gets the filter settings from the session
     *
     * @return array
     */
    protected static function getFilterSettingsFromSession()
    {
        // Gets the session variables
        $sessionFilters = self::getVariableFromSession('filters');
        $selectedFilterKey = self::getVariableFromSession('selectedFilterKey');

        $filterClassName = self::getFilterClassName();
        if ($sessionFilters[$selectedFilterKey]['Mvc']['pageId'] != self::getPageId() || $sessionFilters[$selectedFilterKey]['Mvc']['filterClassName'] != $filterClassName) {
            return [];
        } else {
            return $sessionFilters[$selectedFilterKey]['Mvc']['settings'];
        }
    }

    /**
     * Sets the filter context in the session
     *
     * @param array $filterContext
     * @return void
     */
    protected static function setFilterContextInSession()
    {
        if (self::$filterIsSelected) {
            $sessionFilters = self::getVariableFromSession('filters');
            $sessionFilters[self::$extensionKeyWithUid]['Mvc'] = [
                'filterClassName' => self::getFilterClassName(),
                'pageId' => self::getPageId(),
                'context' => self::$filterContext,
                'settings' => self::$filterSettings
            ];

            // Sets session data
            self::getTypoScriptFrontendController()->fe_user->setKey('ses', 'selectedFilterKey', self::$extensionKeyWithUid);
            self::getTypoScriptFrontendController()->fe_user->setKey('ses', 'filters', $sessionFilters);
            self::getTypoScriptFrontendController()->fe_user->storeSessionData();
        }
    }

    /**
     * Gets the parameter from filter context
     *
     * @param string $parameter
     * @return array
     */
    protected static function getParameterFromFilterContext($parameter)
    {
        if (empty(self::$filterContext)) {
            self::$filterContext = self::getFilterContext();
        }
        return self::$filterContext[$parameter];
    }

    /**
     * Adds an error mesage
     *
     * @param string $key
     * @param array $arguments
     * @return void
     */
    protected function addErrorMessage($key, $arguments)
    {
        $this->controller->addFlashMessage(LocalizationUtility::translate($key, $this->controller->getRequest()
            ->getControllerExtensionName(), $arguments));
    }

    /**
     * Gets the filter class name
     *
     * @return string
     */
    protected static function getFilterClassName()
    {
        return get_called_class();
    }

    /**
     * Gets the TypoScript Frontend Controller
     *
     * @return TypoScriptFrontendController
     */
    protected static function getTypoScriptFrontendController()
    {
        return $GLOBALS['TSFE'];
    }

    /**
     * Gets the page id
     *
     * @return integer
     */
    protected static function getPageId()
    {
        return self::getTypoScriptFrontendController()->id;
    }

    /**
     * Translates the model name to the table name
     *
     * @param string $modelClassName
     *
     * @return string
     */
    protected static function translateModelNameToTableName($modelClassName)
    {
        $tableName = 'tx_' . strtolower(str_replace('\\', '_', substr($modelClassName, strpos($modelClassName, '\\') + 1)));
        return $tableName;
    }
}
?>