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

/**
 * Months filter
 *
 * @package sav_filters
 */
class MonthsFilterMvc extends AbstractFilterMvc
{

    /**
     * Render
     *
     * @return void
     */
    public function renderFilter()
    {
        // Gets the model class name
        $modelClassName = self::getFilterSetting('modelClassName');

        // Gets the repository
        $repositoryClassName = ClassNamingUtility::translateModelNameToRepositoryName($modelClassName);
        $repository = $this->objectManager->get($repositoryClassName);

        // Inits the months array
        $values = [];
        $backwardMonths = self::getFilterSetting('backwardMonths');
        $forwardMonths = self::getFilterSetting('forwardMonths');
        for ($i = - $backwardMonths; $i < 12 + $forwardMonths; $i ++) {
            $monthName = ucfirst(strftime('%b', strtotime($i . 'months 01/01/2000')));
            $values[$i] = [
                'label' => $monthName,
                'active' => 0
            ];
        }

        // Gets the rows
        $rows = $repository->findAll();

        // Gets the getter for the field
        $fieldName = self::getFilterSetting('fieldName');
        $getter = 'get' . GeneralUtility::underscoredToUpperCamelCase($fieldName);

        if (! method_exists($modelClassName, $getter)) {
            $this->addErrorMessage('error.unknownMethod', [
                $getter . '()'
            ]);
            $this->controller->getView()->assign('filterName', $this->controller->getFilterName());
            return;
        }

        // Checks if the type of the field is \DateTime
        if ($rows->count() > 0 && ! $rows[0]->_getProperty(GeneralUtility::underscoredToLowerCamelCase($fieldName)) instanceof \DateTime) {
            $this->addErrorMessage('error.typeMustBe', [
                $fieldName,
                '\DateTime'
            ]);
            return;
        }

        // Sets months from the rows
        foreach ($rows as $row) {
            // Gets the field value
            $fieldValue = $row->$getter();

            if ($fieldValue > 0) {
                // Computes the interval in months from the begining of the year
                $differenceFromFirstDayOfYear = (new \DateTime('first day of January this year'))->diff($fieldValue);
                $intervalInMonths = (int) $differenceFromFirstDayOfYear->format('%R%m');

                // Marks the month as active if in the allowed range
                if ($intervalInMonths >= - $backwardMonths && $intervalInMonths < 12 + $forwardMonths) {
                    $values[$intervalInMonths]['active'] = (new \DateTime('first day of January this year ' . $intervalInMonths . 'month'))->format('Y-m');
                }
            }
        }

        // Gets the seachFields and adds a search box if any
        $searchFields = self::getFilterSetting('searchFields');
        if (! empty($searchFields)) {
            $this->controller->getView()->assign('addSearchBox', 1);

            // Sets the search icon
            $searchIcon = $this->controller->getFilterSetting('searchIcon');
            if (empty($searchIcon)) {
                $extensionKey = $this->controller->getRequest()->getControllerExtensionKey();
                $searchIcon = 'EXT:' . $extensionKey . '/Resources/Public/Icons/search.png';
            }
            $this->controller->getView()->assign('searchIcon', $searchIcon);

            // Sets the search value
            $searchValue = self::getParameterFromFilterContext('searchMonthsFilter');
            $this->controller->getView()->assign('searchMonthsFilter', $searchValue);
        }

        $this->controller->getView()->assign('values', $values);
        $this->controller->getView()->assign('filterContext', self::$filterContext);
        $this->controller->getView()->assign('filterName', $this->controller->getFilterName());
        $this->controller->getView()->assign('cid', self::$contentUid);
    }

    /**
     * Adds the filter WHERE clause part to the query
     *
     * @param QueryInterface $query
     * @return QueryInterface
     */
    public static function filterWhereClause($query)
    {
        // Gets the parameters from the filter context
        $selected = self::getParameterFromFilterContext('selected');
        $searchValue = self::getParameterFromFilterContext('searchMonthsFilter');

        // Builds the query constraints
        if ($selected !== null) {
            if ($selected == 'all') {
                // All is selected, return a constraint always true
                $constraints = [];
                $constraints[] = $query->greaterThan('uid', 0);
                return $query->logicalOr($constraints);
            } else {
                // A month is selected
                $currentMonthName = \DateTime::createFromFormat('Y-m', $selected)->format('F Y');

                $fieldName = self::getFilterSetting('fieldName');
                $constraints = [];
                $constraints[] = $query->logicalAnd($query->greaterThanOrEqual($fieldName, new \DateTime('first day of ' . $currentMonthName)), $query->lessThan($fieldName, new \DateTime('first day of ' . $currentMonthName . ' 1 month')));

                return $query->logicalOr($constraints);
            }
        } elseif (! empty($searchValue)) {
            // A search is requested
            $searchFields = explode(',', self::getFilterSetting('searchFields'));
            $constraints = [];
            $match = [];
            if (preg_match('/^(\d{4})(?:-(\d{2}))?(?:-(\d{2}))?$/', $searchValue, $match)) {
                if (count($match) == 4) {
                    $minValue = $searchValue;
                    $maxValue = $searchValue . ' 1 day';
                } elseif (count($match) == 3) {
                    $minValue = $searchValue . '-01';
                    $maxValue = $searchValue . '-01 1 month';
                } else {
                    $minValue = $searchValue . '-01-01';
                    $maxValue = $searchValue . '-01-01 1 year';
                }
            } else {
                return null;
            }

            foreach ($searchFields as $searchField) {
                $constraints[] = $query->logicalAnd($query->greaterThanOrEqual($searchField, new \DateTime($minValue)), $query->lessThan($searchField, new \DateTime($maxValue)));
            }
            return $query->logicalOr($constraints);
        } else {
            return null;
        }
    }
}
?>