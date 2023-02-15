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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Selectors filter
 *
 * @package sav_filters
 */
class SelectorsFilter extends AbstractFilter
{

    /**
     * Setter for addWhere
     *
     * @return void
     */
    protected function setAddWhereInSessionFilter()
    {
        $addWhere = '';
        $items = $this->controller->getFilterSetting('items');
        foreach ($items as $item) {
            $filterWhereClause = $this->controller->getFilterSetting('filterWhereClause', true);

            $isEmpty = true;
            // Finds the variable path
            $matches = [];
            if (preg_match_all('/{([^}]+)}/', $filterWhereClause, $matches)) {
                $result = $filterWhereClause;
                // Replaces each path by its value
                foreach ($matches[0] as $matchKey => $match) {
                    $path = $matches[1][$matchKey];
                    $value = $this->getHttpVariableFromPath($path);
                    $isEmpty = $isEmpty & empty($value);
                    $result = str_replace('{' . $path . '}', $value, $result);
                }
            }
            if (! $isEmpty) {
                if (empty($addWhere)) {
                    $addWhere = '(' . $result . ')';
                } else {
                    $addWhere .= ' AND (' . $result . ')';
                }
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
    {}

    /**
     * Processes the filter
     *
     * @return void
     */
    protected function filterProcessing()
    {
        $templates = [];
        $items = $this->controller->getFilterSetting('items');

        if (is_array($items)) {
            foreach ($items as $item) {
                $template = $this->controller->getFilterSetting('template', true);

                // Creates a view for the processings of the template
                /** @var StandaloneView $view **/
                $view = GeneralUtility::makeInstance(StandaloneView::class);
                $partialRootPaths = $this->controller->getView()
                    ->getTemplatePaths()
                    ->getPartialRootPaths();
                $view->setPartialRootPaths($partialRootPaths);
                $view->setTemplateSource($template);

                $match = [];
                if (preg_match('/<f:form.select.*?options="{([^"\-}]+).*?}"/', $template, $match)) {

                    // Creates the query builder
                    $queryBuilder = $this->createQueryBuilder();

                    // Gets the rows
                    $values = [];
                    if ($queryBuilder !== null) {
                        $rows = $queryBuilder->execute()->fetchAll(\PDO::FETCH_BOTH);
                        if (is_array($rows[0]) && key_exists('uid', $rows[0])) {
                            $values = array_column($rows, $match[1], 'uid');
                        } else {
                            $values = array_column($rows, 0, 0);
                        }
                    }
                    $view->assign($match[1], $values);
                }

                // Adds the post variables
                $view->assign('post', $this->httpVariables);

                // Renders the template
                $renderedTemplate = $view->render();

                // Replaces the name by the extension name
                $renderedTemplate = preg_replace('/name="([^"\[]+)/', 'name="tx_savfilters_default[$1]', $renderedTemplate);

                $templates[] = $renderedTemplate;
            }
        }

        $this->controller->getView()->assign('templates', $templates);
    }
}
