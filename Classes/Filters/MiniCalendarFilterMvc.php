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
 * Alphabetic filter
 */
class MiniCalendarFilterMvc extends AbstractFilterMvc
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

        // Gets the month
        $month = self::getParameterFromFilterContext('month');
        if (empty($month)) {
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

        // Gets the rows in the selected month
        $fieldNameForDate = self::getFilterSetting('fieldNameForDate');
        $query = $repository->createQuery();
        $constraints = [];
        $constraints[] = $query->logicalAnd($query->greaterThanOrEqual($fieldNameForDate, new \DateTime('first day of ' . $currentMonthName)), $query->lessThan($fieldNameForDate, new \DateTime('first day of ' . $currentMonthName . ' 1 month')));
        $query = $query->matching($query->logicalOr($constraints));
        $rows = $query->execute();

        // Gets the getter for the date
        $getterForDate = 'get' . GeneralUtility::underscoredToUpperCamelCase($fieldNameForDate);
        if (! method_exists($modelClassName, $getterForDate)) {
            $this->addErrorMessage('error.unknownMethod', [
                $getterForDate . '()'
            ]);
            return;
        }

        // Checks if the type of the field is \DateTime
        if ($rows->count() > 0 && ! $rows[0]->_getProperty(GeneralUtility::underscoredToLowerCamelCase($fieldNameForDate)) instanceof \DateTime) {
            $this->addErrorMessage('error.typeMustBe', [
                $fieldNameForDate,
                '\DateTime'
            ]);
            return;
        }

        // Gets the getter for the title
        $fieldNameForTitle = self::getFilterSetting('fieldNameForTitle');
        $getterForTitle = 'get' . GeneralUtility::underscoredToUpperCamelCase($fieldNameForTitle);
        if (! method_exists($modelClassName, $getterForTitle)) {
            $this->addErrorMessage('error.unknownMethod', [
                $getterForTitle . '()'
            ]);
            return;
        }

        // Sets date from the rows
        foreach ($rows as $row) {
            $fieldValueForDate = $row->$getterForDate();
            if ($fieldValueForDate) {
                $index = $emptyDays + $fieldValueForDate->format('d') - 1;
                $values[$index]['active'] = $fieldValueForDate->format('d');
                $values[$index]['title'] .= (empty($values[$index]['title']) ? '' : chr(13)) . $row->$getterForTitle();
            }
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
        $month = self::getParameterFromFilterContext('month');

        // Builds the query constraints
        $fieldNameForDate = self::getFilterSetting('fieldNameForDate');
        $constraints = [];
        if ($month !== null) {
            if ($selected !== null) {
                $currentDate = $month . '-' . $selected;
                $constraints[] = $query->logicalAnd($query->greaterThanOrEqual($fieldNameForDate, new \DateTime($currentDate)), $query->lessThan($fieldNameForDate, new \DateTime($currentDate . ' 1 day')));
                return $query->logicalOr($constraints);
            } else {
                $currentMonthName = \DateTime::createFromFormat('Y-m', $month)->format('F Y');
                $constraints[] = $query->logicalAnd($query->greaterThanOrEqual($fieldNameForDate, new \DateTime('first day of ' . $currentMonthName)), $query->lessThan($fieldNameForDate, new \DateTime('first day of ' . $currentMonthName . ' 1 month')));
                return $query->logicalOr($constraints);
            }
        } else {
            return null;
        }
    }
}
?>