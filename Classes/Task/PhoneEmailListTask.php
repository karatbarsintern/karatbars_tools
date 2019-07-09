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

#use Karatbars\KaratbarsTools\Domain\Index\IndexService;
#use Karatbars\KaratbarsTools\Domain\Site\Site;
#use Karatbars\KaratbarsTools\System\Environment\CliEnvironment;
use Karatbars\KaratbarsTools\System\Logging\KaratbarsToolsLogManager;
use Karatbars\KaratbarsTools\Util;
#use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Resource\Filter\FileExtensionFilter;
#use TYPO3\CMS\Core\Utility\File;
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
     * Fileadmin Source Location(Folder) for the CSV file underlying all phone lists
     * Attention: Use leading and trailing slash
     */
    const KBT_PEL_REGEX_IN_FOLDER = DIRECTORY_SEPARATOR . 'Departments' . DIRECTORY_SEPARATOR . 'Human_Resource' . DIRECTORY_SEPARATOR . 'Phone_Email_List' . DIRECTORY_SEPARATOR;

    /**
     * Fileadmin Target Location(Folder) for the CSV file rendered by this task based on KBT_PEL_REGEX_IN_FOLDER . KBT_PEL_REGEX_IN_FILE
     * Attention: Use leading and trailing slash
     */
    const KBT_PEL_REGEX_OUT_FOLDER = DIRECTORY_SEPARATOR . 'Main' . DIRECTORY_SEPARATOR . 'Phone_Email_List' . DIRECTORY_SEPARATOR;

    /**
     * Fileadmin Source Location(Filename) for the CSV file underlying all phone lists
     * Attention: define regex here that this task is able to find and verify the file by regex name
     */
    const KBT_PEL_REGEX_IN_FILE = '^PhoneEmailList\.in\.csv$';

    /**
     * CSV Data delimiter for the CSV file underlying all phone lists and the rendered CVS files
     */
    const KBT_PEL_CSV_DELIMITER = ';';

    /**
     * CSV Data enclosure for the CSV file underlying all phone lists and the rendered CVS files
     */
    const KBT_PEL_CSV_ENCLOSURE = '"';

    /**
     * @var \Karatbars\KaratbarsTools\System\Logging\KaratbarsToolsLogManager
     */
    protected $logger = null;

    /**
     * @var \TYPO3\CMS\Core\Resource\Folder
     */
    protected $inFolder;

    /**
     * @var \TYPO3\CMS\Core\Resource\Folder
     */
    protected $outFolder;

    /**
     * @var \TYPO3\CMS\Core\Resource\File
     */
    protected $inFile;

    /**
     * @var int
     */
    protected $phoneEmailListFile;

    /**
     * @var array
     */
    protected $phoneEmailListEntries = [];

    /**
     * @var int
     */
    protected $phoneEmailListCount = 0;

    /**
     * @var int
     */
    protected $phoneEmailListFinishedCount = 0;

    /**
     * @var int
     */
    protected $uniqueIterator = 0;

    /**
     * @var array
     */
    protected $conf_outFiles = [];

    /**
     * @var array
     */
    protected $currentOutConf;

     private function initLogger(){
        $this->logger = GeneralUtility::makeInstance(KaratbarsToolsLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
    }
    /**
     * @param bool|string $type
     * @return array|mixed
     */
    private function getConfOutFiles( $type = false )
    {
        $conf = Util::getKaratbarsToolsConfiguration()->getOutFilesConfig();

        foreach( $conf as $key => $value ){
            $this->conf_outFiles[$value['name']] = [
                'fileName' => $value['fileName'],
                'sorting' => $value['sorting.']
            ];
        }

        $returnValue = $this->conf_outFiles;

        if( $type !== false && isset($this->conf_outFiles[$type]) ){
            $returnValue = $this->conf_outFiles[$type];
        }
        return $returnValue;
    }

    /**
     * @return bool Returns TRUE on success.
     * @throws \Exception
     */
    public function execute()
    {
        $this->initLogger();
        $this->logger->log(
            KaratbarsToolsLogManager::INFO,
            __FUNCTION__,
            ['function'=>__FUNCTION__, 'line'=>__LINE__]
        );

        $returnValue = false;
        $returnValueOutFiles = [];

        if ( $this->setInFolder() && $this->setInFile() && $this->setOutFolder() ){
            $this->setPhoneEmailListEntries( $this->csvToArray() );

            foreach( $this->getConfOutFiles() as $type => $outConf ){
                $this->setCurrentOutConf( $outConf );
                if ( $this->beautifyData() === true ) {
                    $csvOutFile = PATH_site . $this->getOutFolder()->getPublicUrl() . $this->getCurrentOutConf('fileName');
                    $returnValueOutFiles[$type] = $this->createCsv( $csvOutFile );
                }
            }
        }

        if( !in_array ( false , $returnValueOutFiles )){
            $returnValue = true;
        }

        $this->logger->log(
            KaratbarsToolsLogManager::INFO,
            __FUNCTION__,
            ['returnValue'=> $returnValue, 'function'=>__FUNCTION__, 'line'=>__LINE__]
        );

        return $returnValue;
    }

    /**
     * Gets the indexing progress.
     *
     * @return float Indexing progress as a two decimal precision float. f.e. 44.87
     * @throws \Exception
     */
    public function getProgress()
    {
        $this->initLogger();
        $this->logger->log(
            KaratbarsToolsLogManager::INFO,
            __FUNCTION__,
            ['function'=>__FUNCTION__, 'line'=>__LINE__]
        );

        $finished = 0;
        $returnValue = 0.00;

        if ( $this->setInFolder() && $this->setInFile() && $this->setOutFolder() ){
            $this->setPhoneEmailListEntries($this->csvToArray());
            $returnValue = true;
            $count = intval( count($this->getPhoneEmailListEntries('in')) );

            $i = 0;
            foreach( $this->getConfOutFiles() as $type => $outConf ){
                ++$i;
                $fullPathOutFileName = PATH_site . $this->getOutFolder()->getPublicUrl() . $outConf['fileName'];
                $finished+= intval(exec("wc -l '$fullPathOutFileName'"));
            }

            if ( $count > 0 ){
                $returnValue = ( 100 / ($count * $i) ) * $finished;
            }
        }

        $this->logger->log(
            KaratbarsToolsLogManager::INFO,
            __FUNCTION__,
            ['returnValue'=> $returnValue, 'inCount'=> ($count * $i), 'outCount'=> $finished, 'function'=>__FUNCTION__, 'line'=>__LINE__]
        );

        return $returnValue;
    }

    /**
     * @return bool
     */
    private function beautifyData(){
        $returnValue = false;
        $this->resetPhoneEmailListEntries( $type = "out" );
        foreach( $this->getPhoneEmailListEntries( "in" ) as $lineIn => $lineDataIn ){
            try {
                $lineDataOut = [
                    'Department' => $this->renderDataDepartment($lineDataIn),
                    'Name' => $this->renderDataName($lineDataIn),
                    'First Name' => $this->renderDataFirstName($lineDataIn),
                    'Phone' => $this->renderDataPhone($lineDataIn),
                    'E-Mail' => $this->renderDataEmail($lineDataIn),
                    'Comment' => $this->renderDataComment($lineDataIn)
                ];

                if( $lineDataOut['E-Mail'] !== false ){
                    $this->addPhoneEmailListLine($lineDataOut, $type = "out" );
                    $this->setPhoneEmailListFinishedCount( $this->getPhoneEmailListFinishedCount() + 1 );
                    $returnValue = true;
                }

            } catch ( \Exception $e ){
                $this->logger->log(
                    KaratbarsToolsLogManager::ERROR,
                    __FUNCTION__,
                    ['message'=>$e->getMessage(), 'function'=>__FUNCTION__, 'line'=>__LINE__]
                );
                $returnValue = false;
            }
        }

        $this->logger->log(
            KaratbarsToolsLogManager::INFO,
            __FUNCTION__,
            ['returnValue'=> $returnValue, 'function'=>__FUNCTION__, 'line'=>__LINE__]
        );
        return $returnValue;
    }

    /**
     * @param $csvOutFile
     * @return bool
     */
    private function createCsv( $csvOutFile ){
        $returnValue = false;
        $fp = NULL;
        $success = false;

        $csvOutFileBak = PATH_site . $this->getInFolder()->getPublicUrl() . uniqid('.',true) . "." . basename( $csvOutFile );

        if( file_exists( $csvOutFile )  ){
            copy( $csvOutFile, $csvOutFileBak );
        }

        try {
            $fp = fopen($csvOutFile , 'w');
            $tmpAr = $this->getPhoneEmailListEntries( "out" );
            ksort ($tmpAr );

            foreach ( $tmpAr as $key => $entry ) {
                if (
                    !(
                        !$entry['Department'] &&
                        !$entry['First Name'] &&
                        !$entry['Name'] &&
                        !$entry['Phone'] &&
                        !$entry['E-Mail'] &&
                        !$entry['Comment']
                    )
                ) {
                    fputcsv(
                        $fp,
                        $this->sortData( $entry ),
                        $this::KBT_PEL_CSV_DELIMITER,
                        $this::KBT_PEL_CSV_ENCLOSURE
                    );

                    $success = true;
                }
            }
            fclose($fp);
        } catch ( \Exception $e ){
            $this->logger->log(
                KaratbarsToolsLogManager::ERROR,
                __FUNCTION__,
                ['message'=> $e->getMessage(), 'function'=>__FUNCTION__, 'line'=>__LINE__]
            );
        }

        if( $success ){
            $returnValue = true;
            if( file_exists($csvOutFileBak) ){
                unlink( $csvOutFileBak );
            }
        } else {
            if( file_exists($csvOutFileBak) ){
                if( copy( $csvOutFileBak, $csvOutFile ) ){
                    unlink( $csvOutFileBak );
                }
            }
        }

        $this->logger->log(
            KaratbarsToolsLogManager::INFO,
            __FUNCTION__,
            ['returnValue'=> $returnValue, 'csvOutFile'=> $csvOutFile, 'function'=>__FUNCTION__, 'line'=>__LINE__]
        );
        return $returnValue;
    }

    /**
     * @return array|bool
     */
    private function csvToArray()
    {
        $header = NULL;
        $returnValue = array();

        $csvInFile = PATH_site . $this->getInFolder()->getPublicUrl() . $this->getInFile()->getName();

        if( !file_exists($csvInFile) || !is_readable($csvInFile) ){
            return false;
        }

        if (($handle = fopen($csvInFile, 'r')) !== false)
        {
            while (($row = fgetcsv($handle, 1000, $this::KBT_PEL_CSV_DELIMITER)) !== false)
            {
                if(!$header)
                    $header = $row;

                else
                    $returnValue[] = array_combine($header, $row);
            }
            fclose($handle);
        }
        $this->setPhoneEmailListCount( count($returnValue) );

        $this->logger->log(
            KaratbarsToolsLogManager::INFO,
            __FUNCTION__,
            ['returnValue'=> $returnValue, 'csvInFile'=> $csvInFile, 'function'=>__FUNCTION__, 'line'=>__LINE__]
        );

        return $returnValue;
    }

    /**
     * @param $haystack
     * @return array
     */
    private function sortData( $haystack ){
        $sortedArTmp = array_replace(array_flip($this->getCurrentOutConf()['sorting']), $haystack);
        // determine not required keys
        foreach( $sortedArTmp as $tmpKey => $tmpValue ){
            if( !array_key_exists($tmpKey, $haystack)) unset($sortedArTmp[$tmpKey]);
        }

        return $sortedArTmp;
    }

    /**
     * @param $data
     * @return string
     */
    private function renderDataDepartment( $data ){
        $arTmp = [];
        if (isset($data['Department']) && $data['Department'] ) $arTmp['Department'] = str_replace( "\n", ",", $data['Department']);
        if (isset($data['Area']) && $data['Area'] ) $arTmp['Area'] = str_replace( "\n", ",", $data['Area']);;
        if (isset($data['Position']) && $data['Position'] ) $arTmp['Position'] = str_replace( "\n", ",", $data['Position']);

        return implode(", ", $this->sortData( $arTmp ) );
    }

    /**
     * @param $data
     * @return string
     */
    private function renderDataName( $data ){
        $arTmp = [];
        if (isset($data['Name']) && $data['Name'] ) $arTmp[] = str_replace( "\n", ",", $data['Name']);
        return implode(",", $arTmp );
    }

    /**
     * @param $data
     * @return string
     */
    private function renderDataFirstName( $data ){
        $arTmp = [];
        if (isset($data['First Name']) && $data['First Name'] ) $arTmp[] = str_replace( "\n", ",", $data['First Name']);
        return implode(",", $arTmp );
    }

    /**
     * @param $data
     * @return string
     */
    private function renderDataComment( $data ){
        $arTmp = [];
        if (isset($data['Language']) && $data['Language'] ) $arTmp[] = str_replace( "\n", ",", $data['Language']);
        if (isset($data['Comment']) && $data['Comment'] ) $arTmp[] = str_replace( "\n", ",", $data['Comment']);
        return implode(",", $arTmp );
    }

    /**
     * @param $data
     * @return string
     */
    private function renderDataPhone( $data ){

        $data['Phone'] = str_replace(' ', '', $data['Phone']);
        $data['Phone'] = str_replace( "\n", ",", $data['Phone']);
        $data['Phone'] = preg_match( "#^[-+0-9()\/ ]{4,}$#", $data['Phone']) ? $data['Phone'] : "";

        $data['Direct Dialing'] = str_replace(' ', '', $data['Direct Dialing']);
        $data['Direct Dialing'] = str_replace( "\n", ",", $data['Direct Dialing']);

        $directDialingFlag = false;
        $arTmp = [];
        foreach( explode(",", $data['Direct Dialing']) as $directDialing ){
            if( is_numeric(trim($directDialing)) && $data['Phone'] ){
                $directDialingFlag = true;
                $arTmp[] = implode("-", [ $data['Phone'], $directDialing ] );
            }
        }

        $returnValue = implode(", ", $arTmp );

        if( $directDialingFlag === false ){
            $returnValue = $data['Phone'];
        }

        return $returnValue;
    }

    /**
     * @param $data
     * @return string
     */
    private function renderDataEmail( $data ){
        $arTmp = [];

        $data['E-Mail'] = str_replace( "\n", ",", $data['E-Mail']);
        $data['E-Mail'] = strtolower( str_replace( " ", "", $data['E-Mail']) );
        foreach( explode(",", $data['E-Mail']) as $eMail ){
            if( trim($eMail) != "" && GeneralUtility::validEmail(trim($eMail)) ){
                $arTmp[] = trim($eMail);
            }
        }

        $data['E-Mail-Collection'] = str_replace( "\n", ",", $data['E-Mail-Collection']);
        $data['E-Mail-Collection'] = strtolower( str_replace( " ", "", $data['E-Mail-Collection']) );
        foreach( explode(",", $data['E-Mail-Collection']) as $eMail ){
            if( trim($eMail) != "" && GeneralUtility::validEmail(trim($eMail)) ){
                $arTmp[] = trim($eMail);
            }
        }

        return count($arTmp) > 0 ? implode(", ", $arTmp ) : false;
    }

    /**
     * @param Folder $folder
     * @param $extensionList
     * @return \TYPO3\CMS\Core\Resource\File[]
     */
    protected function getFolderContent(Folder $folder, $extensionList)
    {
        if ($extensionList !== '') {
            /** @var FileExtensionFilter $filter */
            $filter = GeneralUtility::makeInstance(FileExtensionFilter::class);
            $filter->setAllowedFileExtensions($extensionList);
            $folder->setFileAndFolderNameFilters([[$filter, 'filterFileList']]);
        }
        return $folder->getFiles();
    }

    /**
     * @return mixed
     */
    public function getPhoneEmailListFile()
    {
        return $this->phoneEmailListFile;
    }

    /**
     * @param int $limit
     */
    public function setPhoneEmailListFile($limit)
    {
        $this->phoneEmailListFile = $limit;
    }

    /**
     * @return array
     */
    private function getPhoneEmailListEntries( $type = null )
    {
        $returnValue = $this->phoneEmailListEntries;
        if( $type && array_key_exists( $type, $this->phoneEmailListEntries ) ){
            $returnValue = $this->phoneEmailListEntries[$type];
        }
        return $returnValue;
    }

    /**
     * @param $phoneEmailListEntries
     * @param string $type
     */
    private function setPhoneEmailListEntries($phoneEmailListEntries, $type = "in" )
    {
        $this->phoneEmailListEntries[$type] = $phoneEmailListEntries;
    }

    /**
     * @param string $type
     */
    private function resetPhoneEmailListEntries($type = "in" )
    {
        $this->phoneEmailListEntries[$type] = [];
    }


    /**
     * @param $phoneEmailListLine
     * @param string $type
     */
    private function addPhoneEmailListLine($phoneEmailListLine, $type = "in" )
    {
        $sortingKey = $this->getUniqueIterator();

        foreach ( $this->getCurrentOutConf()['sorting'] as $sortKey ){
            $sortingKey = strtolower( $phoneEmailListLine[$sortKey] . "-" . $sortingKey );
        }

        $this->phoneEmailListEntries[$type][$sortingKey] = $phoneEmailListLine;
        ksort($this->phoneEmailListEntries[$type]);
    }

    /**
     * @return int
     */
    public function getPhoneEmailListCount()
    {
        return $this->phoneEmailListCount;
    }

    /**
     * @param int $phoneEmailListCount
     */
    public function setPhoneEmailListCount($phoneEmailListCount)
    {
        $this->phoneEmailListCount = $phoneEmailListCount;
    }

    /**
     * @return int
     */
    public function getPhoneEmailListFinishedCount()
    {
        return $this->phoneEmailListFinishedCount;
    }

    /**
     * @param int $phoneEmailListFinishedCount
     */
    public function setPhoneEmailListFinishedCount($phoneEmailListFinishedCount)
    {
        $this->phoneEmailListFinishedCount = $phoneEmailListFinishedCount;
    }

    /**
     * @return mixed
     */
    public function getInFolder()
    {
        return $this->inFolder;
    }

    /**
     * @return bool
     */
    public function setInFolder()
    {
        $returnValue = false;
        try {
            $resourceFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();
            $defaultStorage = $resourceFactory->getDefaultStorage();
            $this->inFolder = $defaultStorage->getFolder($this::KBT_PEL_REGEX_IN_FOLDER);
            $returnValue = true;
        } catch ( \Exception $e ) {
            $this->logger->log(
                KaratbarsToolsLogManager::ERROR,
                __FUNCTION__,
                ['message'=> $e->getMessage(), 'function'=>__FUNCTION__, 'line'=>__LINE__]
            );
        }

        return $returnValue;
    }

    /**
     * @return mixed
     */
    public function getOutFolder()
    {
        return $this->outFolder;
    }

    /**
     * @return bool
     */
    public function setOutFolder()
    {
        $returnValue = false;
        try {
            $resourceFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();
            $defaultStorage = $resourceFactory->getDefaultStorage();
            $this->outFolder = $defaultStorage->getFolder($this::KBT_PEL_REGEX_OUT_FOLDER);
            $returnValue = true;
        } catch ( \Exception $e ) {
            $this->logger->log(
                KaratbarsToolsLogManager::ERROR,
                __FUNCTION__,
                ['message'=> $e->getMessage(), 'function'=>__FUNCTION__, 'line'=>__LINE__]
            );
        }

        return $returnValue;
    }

    /**
     * @return mixed
     */
    public function getInFile()
    {
        return $this->inFile;
    }

    /**
     * @return bool
     */
    public function setInFile()
    {
        $returnValue = false;
        $files = [];
        try {
            $files = $this->getFolderContent( $this->getInFolder(), "csv" );
            $returnValue = true;
        } catch ( \Exception $e ) {
            $this->logger->log(
                KaratbarsToolsLogManager::ERROR,
                __FUNCTION__,
                ['message'=> $e->getMessage(), 'function'=>__FUNCTION__, 'line'=>__LINE__]
            );
        }

        foreach( $files as $key => $file ){
            $returnValue = false;
            if( preg_match( '#' . $this::KBT_PEL_REGEX_IN_FILE . '#', $file->getName() ) && $file->getStorage()->isWritable() ){
                $this->inFile = $file;
                $returnValue = true;
                break;
            }
        }

        return $returnValue;
    }

    /**
     * @return int
     */
    public function getUniqueIterator()
    {
        $this->uniqueIterator += 1;
        return $this->uniqueIterator;
    }

    /**
     *
     */
    public function resetUniqueIterator()
    {
        $this->uniqueIterator = 0;
    }

    /**
     * @param bool $key
     * @return mixed
     */
    public function getCurrentOutConf( $key = false )
    {
        $returnValue = $this->currentOutConf;
        if( $key !== false && isset($this->currentOutConf[$key]) ){
            $returnValue = $this->currentOutConf[$key];
        }
        return $returnValue;
    }

    /**
     * @param mixed $currentOutConf
     */
    public function setCurrentOutConf($currentOutConf)
    {
        $this->currentOutConf = $currentOutConf;
    }

    /**
     * Use this function for further debugging only
     * The messages are logged into /tmp/<% date( "Ymd" ) %>.log
     *
     * @param string $message
     * @param array $info keys are "file", "line", "function"
     * @return bool
     */
    public function loggit( $message = "no Message", $info = [] ){

        $text = isset($info['file']) && is_file($info['file']) ? "" . basename($info['file']) . ": ": "";
        $text.= isset($info['line']) && is_numeric($info['line']) ? "@line " . (Integer)$info['line'] . ": ": "";
        $text.= isset($info['function']) ? "(function  " . $info['function'] . "): " : "";
        $text.= " " . $message;

        if ( $fp = fopen('/tmp/' . date( "Ymd" ) . '.log', 'a+') ){
            fwrite($fp, date( "Y-m-d H:i:s" ) . ": " . $text . "\n" );
            fclose($fp);
            return true;
        } else return false;
    }
}
