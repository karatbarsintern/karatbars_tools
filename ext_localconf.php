<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// Windows compatibility

if (!function_exists('strptime')) {
    require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('karatbars_tools') . 'Resources/Private/Php/strptime/strptime.php');
}

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// adding scheduler tasks

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['Karatbars\KaratbarsTools\Task\PhoneEmailListTask'] = [
    'extension' => $_EXTKEY,
    'title' => 'LLL:EXT:karatbars_tools/Resources/Private/Language/locallang.xlf:phoneemaillistworker_title',
    'description' => 'LLL:EXT:karatbars_tools/Resources/Private/Language/locallang.xlf:phoneemaillistworker_description'
    //'additionalFields' => \Karatbars\KaratbarsTools\Task\PhoneEmailListTaskAdditionalFieldProvider::class
];




$isComposerMode = defined('TYPO3_COMPOSER_MODE') && TYPO3_COMPOSER_MODE;
if(!$isComposerMode) {
    // we load the autoloader for our libraries
    $dir = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY);
    require $dir . '/Resources/Private/Php/ComposerLibraries/vendor/autoload.php';
}
