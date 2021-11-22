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

namespace YolfTypo3\SavFilters\Filters;

/**
 * Minicalendar filter
 *
 * @package sav_filters
 */
class MiniCalendarFilter extends AbstractFilter
{

    /**
     * Setter for addWhere
     *
     * @return void
     */
    protected function setAddWhereInSessionFilter()
    {
        $addWhere = '';
        $selected = $this->httpVariables['selected'];
        if (! empty($selected)) {
            $filterWhereClause = $this->controller->getFilterSetting('filterWhereClause');
            $addWhere .= $this->replaceParametersInFilterWhereClauseQuery($filterWhereClause);
        } elseif (!empty($this->httpVariables['month'])) {
            $filterWhereClause = $this->controller->getFilterSetting('whereClause');
            $addWhere .= $this->replaceSpecialParametersInWhereClause($filterWhereClause);
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
        $selected = $this->httpVariables['selected'];
        if (! empty($selected)) {
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

        // Gets the month
        $month = $this->httpVariables['month'];
        if ($month === null) {
            $month = (new \DateTime())->format('Y-m');
        }

        // Sets the current month
        $currentMonth = \DateTime::createFromFormat('Y-m', $month);
        $currentMonthHeader = strftime('%B %Y', $currentMonth->getTimestamp());
        $currentMonthName = $currentMonth->format('F Y');

        // Sets the days header
        $daysHeader = [];
        for ($i = 0; $i < 7; $i ++) {
            $daysHeader[] = strftime('%a', strtotime('next Monday +' . $i . ' days'));
        }

        // Sets the weeks header
        $weeksHeader = [];
        for ($i = 0; $i < 6; $i ++) {
            $weeksHeader[] = (new \DateTime('last Monday of ' . $currentMonthName . ' -1 month ' . $i . ' week'))->format('W');
        }

        // Sets the days
        $values = [];
        $emptyDays = (new \DateTime('last Monday of ' . $currentMonthName . ' -1 month '))->diff(new \DateTime('first day of ' . $currentMonthName))->d;
        for ($i = 0; $i < $emptyDays; $i ++) {
            $values[] = [
                'active' => 0,
                'label' => (new \DateTime('last Monday of ' . $currentMonthName . ' -1 month ' . $i . 'day'))->format('d'),
                'class' => 'notInMonth'
            ];
        }

        $daysInMonth = (new \DateTime($currentMonthName))->format('t');
        $firstDayInMonth = (new \DateTime('first day of ' . $currentMonthName))->format('w');
        for ($i = 0; $i < $daysInMonth; $i ++) {
            if (($firstDayInMonth + $i - 1 + 7) % 7 >= 5) {
                $class = 'weekend';
            } else {
                $class = 'weekday';
            }
            if ($month . '-' . ($i + 1) == (new \DateTime('now '))->format('Y-m-j')) {
                $class .= ' today';
            }
            $values[] = [
                'active' => 0,
                'label' => $i + 1,
                'class' => $class,
                'title' => ''
            ];
        }

        for ($i = $emptyDays + $daysInMonth, $counter = 1; $i < 42; $i ++, $counter ++) {
            $values[] = [
                'active' => 0,
                'label' => $counter,
                'class' => 'notInMonth'
            ];
        }

        // Creates the query builder
        $queryBuilder = $this->createQueryBuilder();

        // Gets the rows
        $rows = $queryBuilder->execute()->fetchAll();

        // Sets the values
        foreach ($rows as $row) {
            $value = new \DateTime($row['Value']);
            $index = $emptyDays + $value->format('d') - 1;
            $values[$index]['active'] = $value->format('d');
            $values[$index]['title'] .= (empty($values[$index]['title']) ? '' : chr(13)) . $row['Title'];
        }

        // Assigns the variables
        $this->controller->getView()->assign('month', [
            'backward' => (new \DateTime('first day of ' . $currentMonthName . ' -1 month'))->format('Y-m'),
            'current' => (new \DateTime())->format('Y-m'),
            'active' => (new \DateTime('first day of ' . $currentMonthName))->format('Y-m'),
            'forward' => (new \DateTime('first day of ' . $currentMonthName . ' 1 month'))->format('Y-m')
        ]);
        $this->controller->getView()->assign('currentMonthHeader', $currentMonthHeader);
        $this->controller->getView()->assign('daysHeader', $daysHeader);
        $this->controller->getView()->assign('weeksHeader', $weeksHeader);
        $this->controller->getView()->assign('values', $values);
    }

    /**
     * Replaces special parameters in the where clause
     *
     * @param string $whereClause
     * @return string
     */
    protected function replaceSpecialParametersInWhereClause($whereClause): string
    {
        // Gets the month
        $month = $this->httpVariables['month'];
        if ($month === null) {
            $month = (new \DateTime())->format('Y-m');
        }
        $currentMonth = $month . '-01';
        $whereClause = str_replace('{currentMonth}', $currentMonth, $whereClause);

        return $whereClause;
    }
}
