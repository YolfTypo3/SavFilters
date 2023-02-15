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

namespace YolfTypo3\SavFilters\Parser;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\ClassNamingUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use YolfTypo3\SavLibraryMvc\Domain\Repository\DefaultRepository;

/**
 * Template Parser
 *
 * @package SavLibraryMvc
 */
class WhereClauseParser
{

    const WHERE_PATTERN = '/
        (?:(?P<logicalOperator>\s+ (?i:and|or)) \s+)? (?:(?P<negation>(?i:not)) \s+)? (?P<logicalOperand>
            (?P<between> (?:\w+ \s+ (?i:between) \s+  .*? \s+ (?i:and) \s+ .+))(?=(?P>logicalOperator)) |
            (?P>between) |
            .*?(?=(?P>logicalOperator)) |
            .+
        )
    /x';


    const EXPRESSION_PATTERN = '/
        (?P<leftOperand>.*?) \s* (?P<operator>=|!=|>=|<=|>|<|\s(?i:in|contains|like|between)\s) \s*  (?P<rightOperand>
                (?P<leftBetweenOperand>.*?) \s+ (?i:and) \s+ (?P<rightBetweenOperand>.+) |
                .+
        ) |
        (?P<marker>
            @ \d+ @
        ) |
        (?P<singleOperand>
            .+
        )
    /x';

    /**
     * @var array
     */
    protected $markers;

    /**
     * @var DefaultRepository
     */
    protected $repository;

    /**
     * @param RepositoryInterface $repository
     */
    public function injectRepository(RepositoryInterface $repository)
    {
        $this->repository = $repository;

    }

    /**
     * Processes the WHERE clause
     *
     * @param QueryInterface $query
     * @param string $clause
     *
     * @return ConstraintInterface|null
     */
    public function processWhereClause(QueryInterface $query, string $clause): ?ConstraintInterface
    {
        $this->markers = [];
        $index = 0;
        $match = [];
        while (preg_match('/\(([^(]*?)\)/', $clause, $match)) {
            $marker = '@' . $index ++ . '@';
            $this->markers[$marker] = $match[1];
            $clause = str_replace($match[0], $marker, $clause);
        }

        return $this->analyzeWhereClause($query, $clause);
    }

    /**
     * Analyzes the where clause
     *
     * @param QueryInterface $query
     * @param string $clause
     *
     * @return ConstraintInterface|null
     */
    protected function analyzeWhereClause(QueryInterface $query, string $clause): ?ConstraintInterface
    {
        // Splits the clause from the logical operators
        $matchesWhere = [];
        preg_match_all(self::WHERE_PATTERN, $clause, $matchesWhere);

        $operands = [];
        foreach ($matchesWhere[0] as $matchKey => $match) {

            $expression = trim($matchesWhere['logicalOperand'][$matchKey]);
            // Checks if a special processing is required
            $matchesSpecialExpression = [];
            if (preg_match('/^###(?P<method>isNotGroupMember|isGroupMember)(?P<marker>@\d+@):(?P<clause>.*?)###$/', $expression, $matchesSpecialExpression)) {
                $methodParameter = $this->markers[$matchesSpecialExpression['marker']];
                $methodName = $matchesSpecialExpression['method'];
                $clause = $matchesSpecialExpression['clause'];
                $clause = $matchesSpecialExpression['clause'];
                $specialOperand = ($this->$methodName($methodParameter) ? $clause : null);
                array_push($operands, $specialOperand);
            } else {
                // Splits the expression from the allowed operators
                $matchesExpression = [];
                preg_match_all(self::EXPRESSION_PATTERN, $expression, $matchesExpression);

                // Gets the operator
                $operator = trim($matchesExpression['operator'][0]);
                if (!empty($matchesExpression['marker'][0])) {
                    $operator = '@';
                }

                // Processes the operator
                if (! empty($operator)) {
                    $leftHandSideOperand = trim($matchesExpression['leftOperand'][0]);
                    $rightHandSideOperand = $matchesExpression['rightOperand'][0];

                    // Checks if a special processing is required
                    if ($leftHandSideOperand == '###group_list') {
                        $groupList = str_replace('###', '', $rightHandSideOperand);
                        $groupList = $this->processGroupList($query, $groupList, $operator);
                        $operator = 'groupList';
                    } elseif (! empty($rightHandSideOperand)) {
                        // Processes the right hand side operand
                        if ($rightHandSideOperand == '###user###') {
                            $rightHandSideOperand = $this->getUserId();
                        } elseif (strtolower($operator) == 'between') {
                            $betweenOperand[0] = $this->processRightHandSideOperand($leftHandSideOperand, $matchesExpression['leftBetweenOperand'][0]);
                            $betweenOperand[1] = $this->processRightHandSideOperand($leftHandSideOperand, $matchesExpression['rightBetweenOperand'][0]);
                        } else {
                            $rightHandSideOperand = $this->processRightHandSideOperand($leftHandSideOperand, $rightHandSideOperand);
                        }
                    }
                    switch ($operator) {
                        case '=':
                            array_push($operands, $query->equals($leftHandSideOperand, $rightHandSideOperand));
                            break;
                        case '!=':
                            array_push($operands, $query->logicalNot($query->equals($leftHandSideOperand, $rightHandSideOperand)));
                            break;
                        case '<':
                            array_push($operands, $query->lessThan($leftHandSideOperand, $rightHandSideOperand));
                            break;
                        case '<=':
                            array_push($operands, $query->lessThanOrEqual($leftHandSideOperand, $rightHandSideOperand));
                            break;
                        case '>':
                            array_push($operands, $query->greaterThan($leftHandSideOperand, $rightHandSideOperand));
                            break;
                        case '>=':
                            array_push($operands, $query->greaterThanOrEqual($leftHandSideOperand, $rightHandSideOperand));
                            break;
                        case 'like':
                        case 'LIKE':
                            array_push($operands, $query->like($leftHandSideOperand, $rightHandSideOperand));
                            break;
                        case 'in':
                        case 'IN':
                            array_push($operands, $query->in($leftHandSideOperand , [$rightHandSideOperand]));
                            break;
                        case 'contains':
                        case 'CONTAINS':
                            array_push($operands, $query->contains($leftHandSideOperand, $rightHandSideOperand));
                            break;
                        case 'between':
                        case 'BETWEEN':
                            array_push($operands, $query->between($leftHandSideOperand, $betweenOperand[0], $betweenOperand[1]));
                            break;
                        case '@':
                            // Marker is found, call recursively with the pattern
                            $markerId = $matchesExpression['marker'][0];
                            array_push($operands, $this->analyzeWhereClause($query, $this->markers[$markerId]));
                            break;
                        case 'groupList':
                            array_push($operands, $groupList);
                            break;
                        default:
                            throw new \Exception(sprintf(
                                'Operator "%s" is not allowed in expression.',
                                $operator
                                )
                            );
                    }
                } else {
                    // Checks if we have only one operand
                    $operand = $matchesExpression['singleOperand'][0];
                    if (!empty($operand) && empty($matchesExpression['singleOperand'][1])) {
                        $leftHandSideOperand = trim($operand);
                        array_push($operands, $query->equals($leftHandSideOperand, 1));
                    } else {
                        throw new \Exception(sprintf(
                            'Expression not allowed.'
                            )
                            );
                    }
                }
            }

            // Adds the logical not if needed
            if (! empty($matchesWhere['negation'][$matchKey])) {
                $rightHandSideLogicalOperand = array_pop($operands);
                $rightHandSideLogicalOperand = $query->logicalNot($query->equals($rightHandSideLogicalOperand, 1));
            }

            if ($matchKey > 0) {
                switch (trim($matchesWhere['logicalOperator'][$matchKey])) {
                    case 'or':
                    case 'OR':
                        $rightHandSideLogicalOperand = array_pop($operands);
                        $leftHandSideLogicalOperand = array_pop($operands);
                        array_push($operands, $query->logicalOr($leftHandSideLogicalOperand, $rightHandSideLogicalOperand));
                        break;
                    case 'and':
                    case 'AND':
                        $rightHandSideLogicalOperand = array_pop($operands);
                        $leftHandSideLogicalOperand = array_pop($operands);
                        array_push($operands, $query->logicalAnd($leftHandSideLogicalOperand, $rightHandSideLogicalOperand));
                        break;
                }
            }
        }

        return array_pop($operands);
    }

    /**
     * Processes the right and side operand
     *
     * @param string $propertyName
     * @param mixed $rightHandSideOperand
     *
     * @return mixed
     */
    protected function processRightHandSideOperand(string $propertyName, $rightHandSideOperand)
    {
        // Replaces the markers
        while (preg_match('/@\d+@/', $rightHandSideOperand, $match)) {
            $marker = $this->markers[$match[0]];
            $rightHandSideOperand = str_replace($match[0], '(' . $marker . ')', $rightHandSideOperand);
        }
        $localQuery = $this->repository->createQuery();
        $alias = GeneralUtility::underscoredToUpperCamelCase($propertyName);
        if (strpos($alias, '.') === false) {
            $localGetter = 'get' . $alias;
            $objectType = ClassNamingUtility::translateRepositoryNameToModelName(get_class($this->repository));
            if (method_exists($objectType, $localGetter)) {
                $result = $localQuery->statement('SELECT ' . $rightHandSideOperand . ' AS ' . $alias)->execute(true);
                $rightHandSideOperand = $result[0][$alias];
            } else {
                throw new \Exception(sprintf(
                    'Property "%s" is not defined in the repository.',
                    $propertyName
                    )
                );
            }
        }
        return $rightHandSideOperand;
    }

    /**
     * Processes grouplist
     *
     * @param QueryInterface $query
     * @param string $groupList
     * @param string $operator
     *
     * @return ConstraintInterface
     */
    protected function processGroupList(QueryInterface $query, string $groupList, string $operator): ConstraintInterface
    {
        $groups = explode(',', $groupList);

        $result = [];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('fe_groups')->createQueryBuilder();
        $queryBuilder->select('uid','title','subgroup')->from('fe_groups');
        $queryResult = $queryBuilder->execute();

        while ($row = $queryResult->fetch()) {
            if (in_array($row['title'], $groups)) {
                if (empty($row['subgroup'])) {
                    $groups = explode(',',$row['uid']);
                } else {
                    $groups = explode(',', $row['uid'] . ',' . $row['subgroup']);
                }
                foreach ($groups as $group ) {
                    if ($operator == '=') {
                        $result[] = $query->contains('usergroup', $group);
                    } else {
                        $result[] = $query->logicalNot($query->contains('usergroup', $group));
                    }
                }
            }
        }

        if ($operator == '=') {
            return $query->logicalAnd($result);
        } else {
            return $query->logicalOr($result);
        }
    }

}
