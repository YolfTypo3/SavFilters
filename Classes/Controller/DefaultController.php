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

namespace YolfTypo3\SavFilters\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use YolfTypo3\SavFilters\Filters\AlphabeticFilter;
use YolfTypo3\SavFilters\Filters\AlphabeticFilterMvc;
use YolfTypo3\SavFilters\Filters\DebugFilter;
use YolfTypo3\SavFilters\Filters\DebugFilterMvc;
use YolfTypo3\SavFilters\Filters\DefaultFilter;
use YolfTypo3\SavFilters\Filters\MiniCalendarFilter;
use YolfTypo3\SavFilters\Filters\MiniCalendarFilterMvc;
use YolfTypo3\SavFilters\Filters\MonthsFilter;
use YolfTypo3\SavFilters\Filters\MonthsFilterMvc;
use YolfTypo3\SavFilters\Filters\PageAccessFilter;
use YolfTypo3\SavFilters\Filters\SearchFilter;
use YolfTypo3\SavFilters\Filters\SearchFilterMvc;
use YolfTypo3\SavFilters\Filters\SelectorsFilter;
use YolfTypo3\SavFilters\Filters\SelectorsFilterMvc;

/**
 * Default Controller
 */
class DefaultController extends ActionController
{

    const DefaultFilter = 0;

    const AlphabeticFilter = 1;

    const MonthsFilter = 2;

    const SearchFilter = 3;

    const SelectorsFilter = 4;

    const MiniCalendarFilter = 5;

    const PageAccessFilter = 6;

    const DebugFilter = 7;

    /**
     * The filter classes
     *
     * @var array
     */
    protected $filterClasses = [
        self::DefaultFilter => [
            DefaultFilter::class,
            DefaultFilter::class
        ],
        self::AlphabeticFilter => [
            AlphabeticFilter::class,
            AlphabeticFilterMvc::class
        ],
        self::MonthsFilter => [
            MonthsFilter::class,
            MonthsFilterMvc::class
        ],
        self::SearchFilter => [
            SearchFilter::class,
            SearchFilterMvc::class
        ],
        self::SelectorsFilter => [
            SelectorsFilter::class,
            SelectorsFilterMvc::class
        ],
        self::MiniCalendarFilter => [
            MiniCalendarFilter::class,
            MiniCalendarFilterMvc::class
        ],
        self::PageAccessFilter => [
            PageAccessFilter::class
        ],
        self::DebugFilter => [
            DebugFilter::class,
            DebugFilterMvc::class
        ]
    ];

    /**
     * Css path
     *
     * @var string
     */
    protected static $cssPath = 'Resources/Public/Css/SavFilters.css';

    /**
     * Initializes the controller before invoking an action method.
     *
     * @return void
     */
    protected function initializeAction()
    {
        // Gets the extension key
        $extensionKey = $this->request->getControllerExtensionKey();

        // Checks if the static extension template is included
        /** @var FrontendConfigurationManager $frontendConfigurationManager */
        $frontendConfigurationManager = GeneralUtility::makeInstance(FrontendConfigurationManager::class);
        $typoScriptSetup = $frontendConfigurationManager->getTypoScriptSetup();
        $pluginSetupName = 'tx_' . strtolower($this->request->getControllerExtensionName()) . '.';
        if (! @is_array($typoScriptSetup['plugin.'][$pluginSetupName]['view.'])) {
            die('Fatal error: You have to include the static template of the extension ' . $extensionKey . '.');
        }

        // Gets the filter name
        $filterName = lcfirst($this->getFilterName());

        // Adds the css file
        $cssFile = $this->getCssFile();
        if (empty($cssFile)) {
            $cssFile = $this->getFilterSetting('includeCSS');
            if (empty($cssFile)) {
                $cssFile = self::getExtensionWebPath($extensionKey) . self::$cssPath;
            }
        }
        $this->addCascadingStyleSheet($cssFile);

        // Replaces the pid list by the Record page storage if any and by the page uid otherwise
        // @extensionScannerIgnoreLine
        $contentObject = $this->configurationManager->getContentObject();
        if (! empty($contentObject->data['pages'])) {
            $this->settings['flexform'][$filterName]['pidList'] = $contentObject->data['pages'];
        } else {
            $this->settings['flexform'][$filterName]['pidList'] = $contentObject->data['pid'];
        }
    }

    /**
     * default action
     *
     * @return void|ResponseInterface
     */
    public function defaultAction()
    {

        // Gets the view type
        $filterType = $this->getFilterType();

        // Gets the library type
        $librarytype = $this->getLibraryType();

        // Gets the filter class name
        $filterClassName = $this->filterClasses[$filterType][$librarytype];

        // Gets the template file name
        $templateFileName = $this->getFilterName() . '.html';
        $template = $this->getDefaultTemplateRootPath() . $templateFileName;

        $this->view->setTemplatePathAndFilename($template);
        if (! empty($filterClassName)) {
            $filter = GeneralUtility::makeInstance($filterClassName);
            $filter->injectController($this);
            $filter->render();
        }

        // For TYPO3 V11: action must return an instance of Psr\Http\Message\ResponseInterface
        if (method_exists($this, 'htmlResponse')) {
            return $this->htmlResponse($this->view->render());
        }
    }

    /**
     * Gets the configuration manager
     *
     * @return ConfigurationManagerInterface
     */
    public function getConfigurationManager()
    {
        return $this->configurationManager;
    }

    /**
     * Gets the request
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Adds an error to the flashmessages
     *
     * @param string $key
     *            The message key
     * @param array $arguments
     *            The argument array
     *
     * @return boolean Returns always false so that it can be used in return statements
     */
    public static function addError(string $key, $arguments = null)
    {
        $message = LocalizationUtility::translate($key, 'sav_filters', $arguments);
        $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $message, '', FlashMessage::ERROR);
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $flashMessageService->getMessageQueueByIdentifier('extbase.flashmessages.tx_savfilters_default')->enqueue($flashMessage);
        return false;
    }

    /**
     * Gets the filter type
     *
     * @return int
     */
    public function getFilterType(): int
    {
        return $this->settings['flexform']['type'];
    }

    /**
     * Gets the library type
     *
     * @return int
     */
    public function getLibraryType(): int
    {
        if (! isset($this->settings['flexform']['libraryType'])) {
            return 0;
        } else {
            return $this->settings['flexform']['libraryType'];
        }
    }

    /**
     * Gets the extension WHERE clause action
     *
     * @return int|null
     */
    public function getExtensionWhereClauseAction()
    {
        return $this->settings['flexform']['extensionWhereClauseAction'];
    }

    /**
     * Gets the additional filter WHERE clause
     *
     * @return int|null
     */
    public function getAdditionalFilterWhereClause()
    {
        return $this->settings['flexform']['additionalFilterWhereClause'];
    }

    /**
     * Gets the CSS file
     *
     * @return int|null
     */
    public function getCssFile()
    {
        return $this->settings['flexform']['cssFile'];
    }

    /**
     * Gets the filter name
     *
     * @return string
     */
    public function getFilterName(): string
    {
        $filterType = $this->getFilterType();
        $libraryType = 0;
        return (new \ReflectionClass($this->filterClasses[$filterType][$libraryType]))->getShortName();
    }

    /**
     * Gets the settings
     *
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Gets field in filter settings
     *
     * @param string $field
     * @param bool $isNewItem
     * @return mixed
     */
    public function getFilterSetting(string $field, $isNewItem = false)
    {
        $filterName = lcfirst($this->getFilterName());
        if ($field === 'items') {
            $this->itemCounter = - 1;
            return $this->settings['flexform'][$filterName]['items'];
        }

        if (isset($this->settings['flexform'][$filterName]['items'])) {
            $items = array_column($this->settings['flexform'][$filterName]['items'], 'item');
            if ($isNewItem) {
                $this->itemCounter ++;
            }
            $item = $items[$this->itemCounter][$field] ?? null;
            if ($item === null) {
                $item = $this->settings['flexform'][$filterName][$field] ?? null;
            }
            return $item;
        } elseif (isset($this->settings['flexform'][$filterName][$field])) {
            return $this->settings['flexform'][$filterName][$field];
        } else {
            return $this->settings[$filterName][$field] ?? null;
        }
    }

    /**
     * Gets the view
     *
     * @return ViewInterface
     */
    public function getView(): ViewInterface
    {
        return $this->view;
    }

    /**
     * Gets the default root path
     *
     * @return string
     */
    public function getDefaultTemplateRootPath(): string
    {
        $templateRootPaths = $this->view->getTemplateRootPaths();
        return $templateRootPaths[0] . 'Default/';
    }

    /**
     * Adds a cascading style Sheet
     *
     * @param string $cascadingStyleSheet
     *
     * @return void
     */
    protected function addCascadingStyleSheet(string $cascadingStyleSheet)
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addCssFile($cascadingStyleSheet);
    }

    /**
     * Gets the relative web path of a given extension.
     *
     * @param string $extension
     *            The extension
     *
     * @return string The relative web path
     */
    protected static function getExtensionWebPath(string $extension): string
    {
        $extensionWebPath = PathUtility::getAbsoluteWebPath(ExtensionManagementUtility::extPath($extension));
        if ($extensionWebPath[0] === '/') {
            // Makes the path relative
            $extensionWebPath = substr($extensionWebPath, 1);
        }
        return $extensionWebPath;
    }
}
