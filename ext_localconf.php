<?php

if (!defined ('TYPO3_MODE')) die ('Access denied.');

// adding scheduler tasks
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['Karatbars\KaratbarsTools\Task\PhoneEmailListTask'] = [
    'extension' => $_EXTKEY,
    'title' => 'LLL:EXT:karatbars_tools/Resources/Private/Language/locallang.xlf:phoneemaillistworker_title',
    'description' => 'LLL:EXT:karatbars_tools/Resources/Private/Language/locallang.xlf:phoneemaillistworker_description'
    //'additionalFields' => \Karatbars\KaratbarsTools\Task\PhoneEmailListTaskAdditionalFieldProvider::class
];


if (!isset($GLOBALS['TYPO3_CONF_VARS']['LOG']['Karatbars']['KaratbarsTools']['writerConfiguration'])) {
    $context = \TYPO3\CMS\Core\Utility\GeneralUtility::getApplicationContext();
    if ($context->isProduction()) {
        $logLevel = \TYPO3\CMS\Core\Log\LogLevel::ERROR;
    } elseif ($context->isDevelopment()) {
        $logLevel = \TYPO3\CMS\Core\Log\LogLevel::DEBUG;
    } else {
        $logLevel = \TYPO3\CMS\Core\Log\LogLevel::INFO;
    }
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['Karatbars']['KaratbarsTools']['writerConfiguration'] = [
        $logLevel => [
            'TYPO3\\CMS\\Core\\Log\\Writer\\FileWriter' => [
                'logFile' => 'typo3temp/var/logs/karatbarstools.log'
            ]
        ],
    ];
}

