<?php
namespace Karatbars\KaratbarsTools\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

#use ApacheSolrForTypo3\Solr\Backend\SiteSelectorField;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
#use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Additional field provider for the index queue worker task
 *
 * @author Oliver Kurzer <oliver.kuruer@karatbars.com>
 */
class PhoneEmailListTaskAdditionalFieldProvider implements AdditionalFieldProviderInterface
{

    /**
     * SiteRepository
     *
     * @var SiteRepository
     */
    #protected $siteRepository;

    public function __construct()
    {
        #$this->siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
    }

    /**
     * Used to define fields to provide the TYPO3 site to index and number of
     * items to index per run when adding or editing a task.
     *
     * @param array $taskInfo reference to the array containing the info used in the add/edit form
     * @param AbstractTask $task when editing, reference to the current task object. Null when adding.
     * @param SchedulerModuleController $schedulerModule : reference to the calling object (Scheduler's BE module)
     * @return array Array containing all the information pertaining to the additional fields
     *                    The array is multidimensional, keyed to the task class name and each field's id
     *                    For each field it provides an associative sub-array with the following:
     */
    public function getAdditionalFields(
        array &$taskInfo,
        $task,
        SchedulerModuleController $schedulerModule
    ) {
        /** @var $task PhoneEmailListTask */
        $additionalFields = [];

        if (!$this->isTaskInstanceofPhoneEmailListTask($task)) {
            return $additionalFields;
        }

        if ($schedulerModule->CMD === 'add') {
            $taskInfo['documentsToIndexLimit'] = 50;
        }

        if ($schedulerModule->CMD === 'edit') {
            $taskInfo['documentsToIndexLimit'] = $task->getDocumentsToIndexLimit();
        }


        $additionalFields['documentsToIndexLimit'] = [
            'code' => '<input type="number" class="form-control" name="tx_scheduler[documentsToIndexLimit]" value="' . htmlspecialchars($taskInfo['documentsToIndexLimit']) . '" />',
            'label' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:indexqueueworker_field_documentsToIndexLimit',
            'cshKey' => '',
            'cshLabel' => ''
        ];

        return $additionalFields;
    }

    /**
     * Checks any additional data that is relevant to this task. If the task
     * class is not relevant, the method is expected to return TRUE
     *
     * @param array $submittedData reference to the array containing the data submitted by the user
     * @param SchedulerModuleController $schedulerModule reference to the calling object (Scheduler's BE module)
     * @return bool True if validation was ok (or selected class is not relevant), FALSE otherwise
     */
    public function validateAdditionalFields(
        array &$submittedData,
        SchedulerModuleController $schedulerModule
    ) {
        $result = false;
        // escape limit
        $submittedData['documentsToIndexLimit'] = intval($submittedData['documentsToIndexLimit']);

        return $result;
    }

    /**
     * Saves any additional input into the current task object if the task
     * class matches.
     *
     * @param array $submittedData array containing the data submitted by the user
     * @param AbstractTask $task reference to the current task object
     */
    public function saveAdditionalFields(
        array $submittedData,
        AbstractTask $task
    ) {
        if (!$this->isTaskInstanceofPhoneEmailListTask($task)) {
            return;
        }

        $task->setDocumentsToIndexLimit($submittedData['documentsToIndexLimit']);
    }

    /**
     * Check that a task is an instance of PhoneEmailListTask
     *
     * @param AbstractTask $task
     * @return boolean
     * @throws \LogicException
     */
    protected function isTaskInstanceofPhoneEmailListTask($task)
    {
        if ((!is_null($task)) && (!($task instanceof PhoneEmailListTask))) {
            throw new \LogicException(
                '$task must be an instance of PhoneEmailListTask, '
                .'other instances are not supported.', 1487499811
            );
        }
        return true;
    }
}
