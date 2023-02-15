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
 * The TYPO3 project - inspiring people to share
 */

namespace YolfTypo3\SavFilters\Hooks;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Hook to display verbose information about default plugin in Web>Page module
 */
class PageLayoutView
{

    /**
     * Extension list type
     *
     * @var string
     */
    const LIST_TYPE = 'savfilters_default';

    /**
     * Root path for the language
     *
     * @var string
     */
    const LANGUAGE_ROOT_PATH = 'LLL:EXT:sav_filters/Resources/Private/Language/';

    /**
     * Flexform information
     *
     * @var array
     */
    protected $flexformData = [];

    /**
     * Returns information about this extension
     *
     * @param array $params
     *            Parameters to the hook
     * @return string Information about the plugin
     */
    public function getExtensionInformation(array $params): string
    {
        $result = $params['pObj']->linkEditContent('<strong>' . $this->getLanguageService()
            ->sL(self::LANGUAGE_ROOT_PATH . 'locallang_db.xlf:' . 'tt_content.list_type_pi1') . '</strong><br>', $params['row']);

        if ($params['row']['list_type'] == self::LIST_TYPE) {
            // Gets the flexform data
            $this->flexformData = GeneralUtility::xml2array($params['row']['pi_flexform']);
            // Gets the filter type
            $type = $this->getFieldFromFlexform('settings.flexform.type');
            // Gets the library type
            $libraryType = $this->getFieldFromFlexform('settings.flexform.libraryType');
            $libraryType = ($libraryType ? $libraryType : '0');

            $result .= $this->getLanguageService()->sL(self::LANGUAGE_ROOT_PATH . 'locallang.xlf:' . 'flexform.type.' . $type) . ' (' . $this->getLanguageService()->sL(self::LANGUAGE_ROOT_PATH . 'locallang.xlf:' . 'flexform.libraryType.' . $libraryType) . ')';
        }
        return $result;
    }

    /**
     * Gets the field value from flexform configuration,
     * including checks if flexform configuration is available
     *
     * @param string $key
     *            name of the key
     * @param string $sheet
     *            name of the sheet
     * @return string|null if nothing found, value if found
     */
    protected function getFieldFromFlexform(string $key, string $sheet = 'sDEF'): ?string
    {
        $flexform = $this->flexformData;
        if (isset($flexform['data'])) {
            $flexform = $flexform['data'];
            if (is_array($flexform) && is_array($flexform[$sheet]) && is_array($flexform[$sheet]['lDEF']) && is_array($flexform[$sheet]['lDEF'][$key]) && isset($flexform[$sheet]['lDEF'][$key]['vDEF'])) {
                return $flexform[$sheet]['lDEF'][$key]['vDEF'];
            }
        }

        return null;
    }

    /**
     * Returns the language service instance
     *
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}