<?php
namespace Karatbars\KaratbarsTools\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Oliver Kurzer <oliver.kurzer@karatbars.com>
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

#use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
#use ApacheSolrForTypo3\Solr\Domain\Site\Site;
#use ApacheSolrForTypo3\Solr\System\Environment\CliEnvironment;
#use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\ProgressProviderInterface;

/**
 * A worker indexing the items in the index queue. Needs to be set up as one
 * task per root page.
 *
 * @author Oliver Kurzer <oliver.kurzer@karatbars.com>
 */
class PhoneEmailListTask extends AbstractKaratbarsToolsTask implements ProgressProviderInterface
{
    /**
     * @var int
     */
    protected $documentsToIndexLimit;

    /**
     * Works through the indexing queue and indexes the queued items into Solr.
     *
     * @return bool Returns TRUE on success, FALSE if no items were indexed or none were found.
     */
    public function execute()
    {
        if ( $fp = fopen('/tmp/data.txt', 'a+') ){
            fwrite($fp, date( "Y-m-d H:i:s" ) . ": " . __FILE__ . " @ line " . __LINE__ . "\n" );
            fclose($fp);
            return true;
        } else return false;
    }

    /**
     * @return mixed
     */
    public function getDocumentsToIndexLimit()
    {
        return $this->documentsToIndexLimit;
    }

    /**
     * @param int $limit
     */
    public function setDocumentsToIndexLimit($limit)
    {
        $this->documentsToIndexLimit = $limit;
    }

    /**
     * Gets the indexing progress.
     *
     * @return float Indexing progress as a two decimal precision float. f.e. 44.87
     */
    public function getProgress()
    {
        return 50.00;
    }


}
