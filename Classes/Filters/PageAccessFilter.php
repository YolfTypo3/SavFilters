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

namespace YolfTypo3\SavFilters\Filters;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use YolfTypo3\SavFilters\Controller\DefaultController;

/**
 * Page Access filter
 *
 * @package sav_filters
 */
class PageAccessFilter extends AbstractFilter
{

    // Page access type constants
    const CREATE_LINK_FOR_FE_USERS = 1;

    const CHECK_ACCESS_FE_USER = 2;

    const CREATE_ACCESS_CAPTCHA_EMAIL = 3;

    /**
     * Setter for addWhere
     *
     * @return void
     */
    protected function setAddWhereInSessionFilter()
    {}

    /**
     * Initialisation of the filter
     *
     * @return boolean (false if a problem occured)
     */
    protected function filterInitialisation(): bool
    {
        $pageAccessType = $this->controller->getFilterSetting('pageAccessType');
        switch ($pageAccessType) {
            // Changes the template if cpatcha is used
            case self::CREATE_ACCESS_CAPTCHA_EMAIL:
                // Gets the template file name
                $templateFileName = $this->controller->getFilterName() . 'WithCaptchaEmail.html';
                $template = $this->controller->getDefaultTemplateRootPath() . $templateFileName;

                $this->controller->getView()->setTemplatePathAndFilename($template);
                break;
        }

        return parent::filterInitialisation();
    }

    /**
     * Http variables processing
     *
     * @return void
     */
    protected function httpVariablesProcessing()
    {
        $pageAccessType = $this->controller->getFilterSetting('pageAccessType');

        switch ($pageAccessType) {
            case self::CHECK_ACCESS_FE_USER:
                $this->httpVariablesProcessingForCheckAccessFeUser();
                break;
            case self::CREATE_ACCESS_CAPTCHA_EMAIL:
                $this->httpVariablesProcessingForCreateAccessCaptchaEmail();
                break;
        }
    }

    /**
     * Processes the filter
     *
     * @return void
     */
    protected function filterProcessing()
    {
        $pageAccessType = $this->controller->getFilterSetting('pageAccessType');

        switch ($pageAccessType) {
            case self::CREATE_LINK_FOR_FE_USERS:
                $pageLink = $this->controller->getFilterSetting('pageLink');
                $key = $this->controller->getFilterSetting('key');
                $securityField = $this->controller->getFilterSetting('securityField');
                $securityTable = $this->controller->getFilterSetting('securityTable');
                $feUserUid = $this->getTypoScriptFrontendController()->fe_user->user['uid'];
                $additionalKey = '';

                if (! empty($securityField) && ! empty($securityTable)) {
                    // Creates the query builder
                    $queryBuilder = $this->getQueryBuilder($securityTable);
                    $queryBuilder->select($securityField)->where($queryBuilder->expr()
                        ->eq('fe_users.uid', $queryBuilder->createNamedParameter($feUserUid, \PDO::PARAM_INT)));
                    // Gets the additional key
                    $rows = $queryBuilder->execute()->fetchAll(\PDO::FETCH_BOTH);

                    $column = array_column($rows, 0);
                    $additionalKey = $column[0];
                }

                // Builds the key code
                $keyCode = md5($feUserUid . $key . $additionalKey);

                // Assigns the variables
                $this->controller->getView()->assign('pageLink', $pageLink);
                $this->controller->getView()->assign('keyCode', $keyCode);
                $this->controller->getView()->assign('additionalKey', $additionalKey);
                break;
        }
        $this->controller->getView()->assign('pageAccessType', $pageAccessType);
    }

    /**
     * Http variables processing for Checking Access to a FE User
     *
     * @throws \TYPO3\CMS\Core\Exception if the mandatory fields are empty
     * @return void
     */
    protected function httpVariablesProcessingForCheckAccessFeUser()
    {
        // Initializes the extension display
        $this->forceSetSessionFields = true;
        $this->setFieldInSessionFilter('formAction', 'noDisplay');

        // Gets the FE user uid
        $feUserUid = $this->getTypoScriptFrontendController()->fe_user->user['uid'];
        if (empty($feUserUid)) {
            DefaultController::addError('error.MustBeAuthenticated');
            return;
        }

        // Checks if a key is provided
        if (empty($this->httpVariables['key'])) {
            DefaultController::addError('error.forbiddenAccess');
            return;
        }

        // Gets the mandatory fields
        $securityField = $this->controller->getFilterSetting('securityField');
        $securityTable = $this->controller->getFilterSetting('securityTable');
        $key = $this->controller->getFilterSetting('key');
        if (empty($securityField) || empty($securityTable) || empty($key)) {
            throw new Exception('The key, the security field and the security table must not be empty');
        }

        // Creates the query builder
        $queryBuilder = $this->getQueryBuilder($securityTable);
        $queryBuilder->select('uid')
            ->add('select', 'MD5(CONCAT(cruser_id, \'' . $key . '\', ' . $securityField . ')) AS keyCode, uid')
            ->groupBy('keyCode', 'uid')
            ->having($queryBuilder->expr()
            ->eq('keyCode', $queryBuilder->createNamedParameter($this->httpVariables['key'], \PDO::PARAM_STR)));
        $rows = $queryBuilder->execute()->fetchAll();

        if (empty($rows)) {
            $match = [];
            preg_match('/(?P<From>\w+)(?P<InnerJoin>.*)/', $securityTable, $match);
            $fromClause = $match['From'];
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($fromClause);
            $values = [
                'cruser_id' => $feUserUid,
                'pid' => $this->getPageId(),
                'crdate' => time(),
                'tstamp' => time()
            ];
            if (! empty($securityField)) {
                $values[$securityField] = $this->httpVariables['key1'];
            }
            $connection->insert($fromClause, $values);
            $uid = (int) $connection->lastInsertId($fromClause);
        } elseif (! empty($rows[0]['keyCode'])) {
            $uid = $rows[0]['uid'];
        }

        $this->forceSetSessionFields = true;
        $this->setFieldInSessionFilter('formAction', 'form');
        $this->setFieldInSessionFilter('uid', $uid);
        $addWhere = $securityTable . '.uid=' . intval($uid);
        $this->setFieldInSessionFilter('addWhere', $this->buildFilterWhereClause($addWhere));
    }

    /**
     * Http variables processing for creating access from captcha and emailr
     *
     * @throws Exception if the mandatory fields are empty
     * @throws Exception if sr_freecap is not loaded
     * @return void
     */
    protected function httpVariablesProcessingForCreateAccessCaptchaEmail()
    {
        // Gets the mandatory fields
        $securityField = $this->controller->getFilterSetting('securityField');
        $securityTable = $this->controller->getFilterSetting('securityTable');
        $key = $this->controller->getFilterSetting('key');

        if (empty($securityField) || empty($securityTable) || empty($key)) {
            throw new Exception('The key, the security field and the security table must not be empty');
        }
        $this->forceSetSessionFields = true;
        $this->setFieldInSessionFilter('formAction', 'noDisplay');
        $this->controller->getView()->assign('displayForm', true);

        // Checks if the page is accessed via the email link
        if (! empty($this->httpVariables['key'])) {
            $queryBuilder = $this->getQueryBuilder($securityTable);
            $queryBuilder->select('uid')
                ->add('select', 'MD5(CONCAT(uid, \'' . $key . '\', ' . $securityField . ')) AS keyCode, uid')
                ->groupBy('keyCode', 'uid')
                ->having($queryBuilder->expr()
                ->eq('keyCode', $queryBuilder->createNamedParameter($this->httpVariables['key'], \PDO::PARAM_STR)));
            $rows = $queryBuilder->execute()->fetchAll();

            if (! empty($rows[0]['keyCode'])) {
                $this->controller->getView()->assign('displayForm', false);
                $this->setFieldInSessionFilter('formAction', 'form');
                $uid = $rows[0]['uid'];
                $this->setFieldInSessionFilter('uid', $uid);
                $addWhere = $securityTable . '.uid=' . intval($uid);
                $this->setFieldInSessionFilter('addWhere', $this->buildFilterWhereClause($addWhere));
            } else {
                DefaultController::addError('error.forbiddenAccess');
                $this->setFieldInSessionFilter('captchaValidated', false);
            }
        } elseif ($this->httpVariables['captchaValidated']) {
            // Checks if the captcha is validated
            $email = $this->httpVariables['email'];
            if (! empty($email)) {
                // Prepares the values to be inserted
                $values = [
                    'cruser_id' => $this->controller->getFilterSetting('cruserIdForCaptchaEmail'),
                    'pid' => $this->getPageId(),
                    'crdate' => time(),
                    'tstamp' => time(),
                    $securityField => $email
                ];

                // Creates the connection and inserts the values
                $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($securityTable);
                $connection->insert($securityTable, $values);
                $uid = $connection->lastInsertId($securityTable);

                // Prepares the email
                $arguments = [
                    $this->getControllerContentObject()->typoLink($this->getTypoScriptFrontendController()->page['title'], [
                        'parameter' => $this->getPageId(),
                        'additionalParams' => '&tx_savfilters_default[controller]=Default&tx_savfilters_default[key]=' . md5($uid . $key . $email),
                        'useCacheHash' => true,
                        'forceAbsoluteUrl' => true
                    ])
                ];

                $mailSender = $this->controller->getFilterSetting('emailSender');
                $mailReceiver = $email;

                $mailMessage = LocalizationUtility::translate('emailTemplate', 'sav_filters', $arguments);
                $mailSubject = LocalizationUtility::translate('emailSubject', 'sav_filters', [
                    $this->getTypoScriptFrontendController()->page['title']
                ]);
                try {
                    /** @var MailMessage $mail */
                    $mail = GeneralUtility::makeInstance(MailMessage::class);

                    /**
                     * @todo Will be removed in TYPO3 12
                     */
                    if (version_compare(GeneralUtility::makeInstance(Typo3Version::class)->getVersion(), '10.0', '<')) {
                        $mail->setSubject($mailSubject);
                        $mail->setFrom($mailSender);
                        $mail->setTo(explode(',', $mailReceiver));
                        $mail->setBody('<head><base href="' . GeneralUtility::getIndpEnv('TYPO3_SITE_URL') . '" /></head><html>' . nl2br($mailMessage) . '</html>', 'text/html');
                        $mail->addPart($mailMessage, 'text/plain');
                    } else {
                        $mail->subject($mailSubject);
                        $mail->from($mailSender);
                        $mail->to(...explode(',', $mailReceiver));
                        $mail->html(nl2br($mailMessage));
                        $mail->text($mailMessage);
                    }
                    $sentMail = $mail->send();


                } catch (\Exception $exception) {
                    $sentMail = false;
                    $this->controller->getView()->assign('captchaValidated', true);
                }
                // Checks if the mail was correctly sent
                if ($sentMail) {
                    DefaultController::addError('emailSent');
                    $this->controller->getView()->assign('displayForm', false);
                } else {
                    // Remove the record
                    GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($securityTable)->delete($securityTable, [
                        'uid' => (int) $uid
                    ], [
                        Connection::PARAM_INT
                    ]);
                    DefaultController::addError('emailNotSent');
                }
            } else {
                DefaultController::addError('error.emailMustNotbeEmpty');
                $this->controller->getView()->assign('captchaValidated', true);
            }
        } else {
            if (ExtensionManagementUtility::isLoaded('sr_freecap')) {
                $this->controller->getView()->assign('srFreecapLoaded', true);
                $captchaValidator = GeneralUtility::makeInstance(\SJBR\SrFreecap\Validation\Validator\CaptchaValidator::class);
                $validationResult = $captchaValidator->Validate($this->httpVariables['captchaResponse']);

                if (! $validationResult->hasErrors()) {
                    $this->controller->getView()->assign('captchaValidated', true);
                } else {
                    if (! empty($this->httpVariables['captchaResponse'])) {
                        DefaultController::addError('error.captchaNotValidated');
                    }
                }
            } else {
                $this->controller->getView()->assign('srFreecapLoaded', false);
                DefaultController::addError('error.srFreecapMustBeLoaded');
            }
        }
    }
}
