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
use TYPO3\CMS\Core\Utility\ClassNamingUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Selectors filter
 */
class SelectorsFilterMvc extends AbstractFilterMvc
{

    /**
     * Render
     *
     * @return void
     */
    public function renderFilter()
    {
        $templates = [];

        // Processes the items
        $items = self::getFilterSetting('items');
        foreach ($items as $item) {

            $template = $item['item']['template'];

            // Creates a view for the processings of the template
            /** @var StandaloneView $view **/
            $view = GeneralUtility::makeInstance(StandaloneView::class);
            $partialRootPaths = $this->controller->getView()
                ->getTemplatePaths()
                ->getPartialRootPaths();
            $view->setPartialRootPaths($partialRootPaths);
            $controllerContext = $this->controller->getControllerContext();
            $view->setControllerContext($controllerContext);
            $view->setTemplateSource($template);

            // Gets the model class name
            $modelClassName = $item['item']['modelClassName'];

            if (! empty($modelClassName)) {

                // Gets the repository
                $repositoryClassName = ClassNamingUtility::translateModelNameToRepositoryName($modelClassName);
                $repository = $this->objectManager->get($repositoryClassName);

                // Gets the rows
                $rows = $repository->findAll();

                // Processes the template
                $matches = [];
                $fieldNames = [];
                if (preg_match_all('/{([^}]+)}/', $template, $matches)) {
                    foreach ($matches[0] as $matchKey => $match) {
                        if (strpos($matches[1][$matchKey], 'post.') === false && $matches[1][$matchKey] !== '_fields_') {
                            $fieldNames[] = $matches[1][$matchKey];
                        }
                    }

                    // Builds the values to assign
                    $values = [];
                    foreach ($rows as $row) {
                        $value = [];
                        foreach ($fieldNames as $fieldName) {
                            $getter = 'get' . GeneralUtility::underscoredToUpperCamelCase($fieldName);
                            if (method_exists($modelClassName, $getter)) {
                                $fieldValue = $row->$getter();
                                $value[$fieldName] = $fieldValue;
                            } else {
                                $this->addErrorMessage('error.unknownMethod', [
                                    $getter . '()'
                                ]);
                            }
                        }
                        $values[] = $value;
                    }

                    // Assigns the values
                    $values = array_column($values, $fieldName, $fieldName);
                    $view->assign($fieldName, $values);
                }

                // Assigns all the records
                $view->assign('_fields_', $rows);
            }

            // Adds the post variables
            $view->assign('post', self::$filterContext);

            // Renders the template
            $renderedTemplate = $view->render();

            // Replaces the name by the extension name
            $renderedTemplate = preg_replace('/name="([^"\[]+)/', 'name="tx_savfilters_default[$1]', $renderedTemplate);
            $templates[] = $renderedTemplate;
        }

        // Sets the search icon
        $extensionKey = $this->controller->getRequest()->getControllerExtensionKey();
        $searchIcon = self::getFilterSetting('searchIcon');
        if (empty($searchIcon)) {
            $searchIcon = 'EXT:' . $extensionKey . '/Resources/Public/Icons/search.png';
        }
        $this->controller->getView()->assign('searchIcon', $searchIcon);
        $this->controller->getView()->assign('templates', $templates);
        $this->controller->getView()->assign('filterName', $this->controller->getFilterName());
        $this->controller->getView()->assign('cid', self::$contentUid);
    }

    /**
     * Adds the filter WHERE clause part to the query
     *
     * @param QueryInterface $query
     * @return QueryInterface
     */
    public static function getFilterWhereClause($query)
    {
        $selectors = self::getParameterFromFilterContext('selector');
        $searches = self::getParameterFromFilterContext('search');

        $globalConstraints = [];
        if (! empty($selectors)) {
            // Builds the selectors constraints
            $selectorsConstraints = [];
            foreach ($selectors as $selectorKey => $selector) {
                if (! empty($selector)) {
                    $selectorsConstraints[] = $query->equals($selectorKey, $selector);
                }
            }
            if (! empty($selectorsConstraints)) {
                $globalConstraints[] = $query->logicalAnd($selectorsConstraints);
            }
        }

        if (! empty($searches)) {
            // Builds the search constraints
            $searchConstraints = [];
            foreach ($searches as $searchKey => $search) {
                $searchConstraints[] = $query->like($searchKey, '%' . $search . '%');
            }
            if (! empty($searchConstraints)) {
                $globalConstraints[] = $query->logicalOr($searchConstraints);
            }
        }

        // Builds the final constraints
        if (! empty($globalConstraints)) {
            return $query->logicalAnd($globalConstraints);
        } else {
            return null;
        }
    }
}
?>