<?php

if (!defined ('TYPO3_MODE')) die ('Access denied.');



// adding scheduler tasks
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['Karatbars\KaratbarsTools\Task\PhoneEmailListTask'] = [
    'extension' => $_EXTKEY,
    'title' => 'LLL:EXT:karatbars_tools/Resources/Private/Language/locallang.xlf:indexqueueworker_title',
    'description' => 'LLL:EXT:karatbars_tools/Resources/Private/Language/locallang.xlf:indexqueueworker_description',
    'additionalFields' => \Karatbars\KaratbarsTools\Task\PhoneEmailListTaskAdditionalFieldProvider::class
];

