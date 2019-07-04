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
use Karatbars\KaratbarsTools\System\Logging\KaratbarsToolsLogManager;
use TYPO3\CMS\Core\Resource\FileInterface;
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
     *
     */
    const KBT_PEL_REGEX_IN_FOLDER = '/Departments/Human_Resource/Phone_Email_List/';

    /**
     * 
     */
    const KBT_PEL_REGEX_OUT_FOLDER = '/Main/Phone_Email_List/';

    /**
     *
     */
    const KBT_PEL_REGEX_IN_FILE = '^PhoneEmailList\.in\.csv$';

    /**
     *
     */
    const KBT_PEL_CSV_DELIMITER = ';';

    /**
     *
     */
    const KBT_PEL_CSV_ENCLOSURE = '"';

    /**
     * @var \Karatbars\KaratbarsTools\System\Logging\KaratbarsToolsLogManager
     */
    protected $logger = null;

    /**
     * @var
     */
    protected $inFolder;

    /**
     * @var
     */
    protected $outFolder;
    /**
     * @var
     */
    protected $inFile;

    /**
     * @var array
     */
    protected $outFileHeader = array("", "Name", "First Name", "Phone(s)", "E-Mail(s)", "Bemerkung" );

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
     * @var
     */
    protected $currentOutConf;

    /**
     * @var
     */
    protected $sortableKey;

    /**
     * @param bool|string $type
     * @return array|mixed
     */
    private function getConfOutFiles( $type = false )
    {
        $this->conf_outFiles = [
            "Name" => [
                "fileName" => "PhoneEmailList.byName.csv",
                "sorting" => [ 'Name', 'First Name' ],
            ],
            "Department" => [
                "fileName" => "PhoneEmailList.byDepartment.csv",
                "sorting" => [ 'Department', 'Area', 'Position' ],
            ],
            "Position" => [
                "fileName" => "PhoneEmailList.byPosition.csv",
                "sorting" => [ 'Position', 'Department', 'Area' ],
            ],
        ];
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

        $this->logger = GeneralUtility::makeInstance(KaratbarsToolsLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);

        echo "XXXX" . var_export( $this->logger, 1);

        $this->logger->log(
            KaratbarsToolsLogManager::ERROR,
            "Message",
            array(1,2,3,4,5,6)
        );

        exit();
        $returnValue = false;
        $this->loggit( "START... ", ['function'=>__FUNCTION__, 'line'=>__LINE__] );
        if ( $this->setInFolder() && $this->setInFile() && $this->setOutFolder() ){
            $this->setPhoneEmailListEntries( $this->csvToArray() );
            
            $returnValueOutFiles = [];
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

        $this->loggit( "RETURN VALUE: " . var_export($returnValue,1), ['function'=>__FUNCTION__, 'line'=>__LINE__] );

        #$this->loggit( "ALL=" . var_export($this->getPhoneEmailListEntries(), 1), ['function'=>__FUNCTION__, 'line'=>__LINE__] );
        $this->loggit( " ...STOP (" . var_export($returnValue, 1) . ")", ['function'=>__FUNCTION__, 'line'=>__LINE__] );

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
                $this->addPhoneEmailListLine($lineDataOut, $type = "out" );
                $this->setPhoneEmailListFinishedCount( $this->getPhoneEmailListFinishedCount() + 1 );
                $returnValue = true;
            } catch (Exception $e ){
                $this->loggit( $e->getMessage(), ['function'=>__FUNCTION__, 'line'=>__LINE__] );
                $returnValue = false;
            }
        }
        $this->loggit( var_export($returnValue, 1), ['function'=>__FUNCTION__, 'line'=>__LINE__] );
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
            #$this->loggit( "FOUND: Backup " . $csvOutFile . " to " . $csvOutFileBak, ['function'=>__FUNCTION__, 'line'=>__LINE__] );
            copy( $csvOutFile, $csvOutFileBak );
        }

        $this->loggit( "WORK ON " . $csvOutFile, ['function'=>__FUNCTION__, 'line'=>__LINE__] );

        try {
            $fp = fopen($csvOutFile , 'w');
            #fputcsv($fp, array("", "Name", "First Name", "Phone(s)", "E-Mail(s)", "Bemerkung" ), $this::KBT_PEL_CSV_DELIMITER, $this::KBT_PEL_CSV_ENCLOSURE);
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
                    ) &&
                    fputcsv($fp, $entry, $this::KBT_PEL_CSV_DELIMITER, $this::KBT_PEL_CSV_ENCLOSURE)
                ) {
                    $success = true;
                }
            }
            fclose($fp);
        } catch (Exception $e ){
            $this->loggit( " ((" . var_export( $fp, 1 ) . ")) " . $e->getMessage(), ['function'=>__FUNCTION__, 'line'=>__LINE__] );
        }

        $this->loggit( "\$success: " . var_export($success, 1), ['function'=>__FUNCTION__, 'line'=>__LINE__] );
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

        #$this->loggit( var_export($returnValue, 1), ['function'=>__FUNCTION__, 'line'=>__LINE__] );
        return $returnValue;
    }

    /**
     * @return array|bool
     */
    private function csvToArray()
    {
        $filename = PATH_site . $this->getInFolder()->getPublicUrl() . $this->getInFile()->getName();
        $this->loggit( $filename, ['function'=>__FUNCTION__, 'line'=>__LINE__] );

        if( !file_exists($filename) || !is_readable($filename) ){
            return false;
        }

        $header = NULL;
        $data = array();
        if (($handle = fopen($filename, 'r')) !== false)
        {
            while (($row = fgetcsv($handle, 1000, $this::KBT_PEL_CSV_DELIMITER)) !== false)
            {
                if(!$header)
                    $header = $row;

                else
                    $data[] = array_combine($header, $row);
            }
            fclose($handle);
        }
        $this->setPhoneEmailListCount( count($data) );
        return $data;
    }

    /**
     * @param $data
     * @return string
     */
    private function renderDataDepartment( $data ){
        $arTmp = [];
        if (isset($data['Department']) && $data['Department'] ) $arTmp[] = str_replace( "\n", ",", $data['Department']);
        if (isset($data['Area']) && $data['Area'] ) $arTmp[] = str_replace( "\n", ",", $data['Area']);;
        if (isset($data['Position']) && $data['Position'] ) $arTmp[] = str_replace( "\n", ",", $data['Position']);
        return implode(",", $arTmp );
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
        foreach( explode(",", $data['E-Mail']) as $eMail ){
            if( GeneralUtility::validEmail(trim($eMail)) ){
                $arTmp[] = trim($eMail);
            }
        }

        $data['E-Mail-Collection'] = str_replace( "\n", ",", $data['E-Mail-Collection']);
        foreach( explode(",", $data['E-Mail-Collection']) as $eMail ){
            if( GeneralUtility::validEmail(trim($eMail)) ){
                $arTmp[] = trim($eMail);
            }
        }

        return implode(", ", $arTmp );
    }

    /**
     * @param Folder $folder
     * @param string $extensionList
     *
     * @return FileInterface[]|Folder[]
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
     * Gets the indexing progress.
     *
     * @return float Indexing progress as a two decimal precision float. f.e. 44.87
     * @throws \Exception
     */
    public function getProgress()
    {
        $count = 20;
        $finished = 12;
        $returnValue = 0.00;

        //ToDo: use the config instead here... see also the execute function, see below...
        return 10.00;

        $this->loggit( "START PROGRESS... ", ['function'=>__FUNCTION__, 'line'=>__LINE__] );
        if ( $this->setInFolder() && $this->setInFile() ) {
            $this->setPhoneEmailListEntries($this->csvToArray());
            $returnValue = true;

            //ToDo: use the config instead here... see also the execute function
            $fullPathOutFileName = PATH_site . $this->getOutFolder()->getPublicUrl() . "PhoneEmailList.csv";

            $count = count($this->getPhoneEmailListEntries('in'));
            $finished = intval(exec("wc -l '$fullPathOutFileName'"));

            $this->loggit("IN COUNT: " . $count, ['function' => __FUNCTION__, 'line' => __LINE__]);
            $this->loggit("OUT COUNT: " . $finished, ['function' => __FUNCTION__, 'line' => __LINE__]);

            if ( $count > 0 ){
                $returnValue = ( 100 / $count ) * $finished;
            }

        }

        return $returnValue;
    }

    /**
     * @param string $type
     * @param bool $key
     * @return bool|mixed
     */
    private function getPhoneEmailListEntry( $type = "in", $key = false )
    {
        $returnValue = false;
        if( $type && array_key_exists( $type, $this->phoneEmailListEntries ) && array_key_exists( $key, $this->phoneEmailListEntries[$type] ) ){
            $returnValue = $this->phoneEmailListEntries[$type]['key'];
        }
        return $returnValue;
    }

    /**
     * @param $entry
     * @param string $type
     * @param bool $key
     * @return bool
     */
    private function setPhoneEmailListEntry($entry, $type = "in", $key = false )
    {
        $returnValue = false;
        if( $type && array_key_exists( $type, $this->phoneEmailListEntries ) && array_key_exists( $key, $this->phoneEmailListEntries[$type] ) ){
            $this->phoneEmailListEntries[$type]['key'] = $entry;
        }
        return $returnValue;
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
        #echo "<br>" . $sortingKey;
        #exit();
        #$this->loggit( "ADDDDD: " . $sortableKey, ['function'=>__FUNCTION__, 'line'=>__LINE__] );
        #$this->loggit( "SORT: " . var_export($this->getConfOutFiles(),1), ['function'=>__FUNCTION__, 'line'=>__LINE__] );
        #$this->loggit( "SORT: " . var_export($this->getSortableKey(),1), ['function'=>__FUNCTION__, 'line'=>__LINE__] );
        #exit();


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
     * @return $this|bool
     * @throws \Exception
     */
    public function setInFolder()
    {
        $returnValue = false;
        try {
            $resourceFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();
            $defaultStorage = $resourceFactory->getDefaultStorage();
            $this->inFolder = $defaultStorage->getFolder($this::KBT_PEL_REGEX_IN_FOLDER);
            $returnValue = true;
        } catch (Exception $e ) {
            $this->loggit( $e->getMessage(), ['function'=>__FUNCTION__, 'line'=>__LINE__] );
        }
        $this->loggit( var_export($returnValue,1), ['function'=>__FUNCTION__, 'line'=>__LINE__] );
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
     * @return $this|bool
     * @throws \Exception
     */
    public function setOutFolder()
    {
        $returnValue = false;
        try {
            $resourceFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();
            $defaultStorage = $resourceFactory->getDefaultStorage();
            $this->outFolder = $defaultStorage->getFolder($this::KBT_PEL_REGEX_OUT_FOLDER);
            $returnValue = true;
        } catch (Exception $e ) {
            $this->loggit( $e->getMessage(), ['function'=>__FUNCTION__, 'line'=>__LINE__] );
        }
        $this->loggit( var_export($returnValue,1), ['function'=>__FUNCTION__, 'line'=>__LINE__] );
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
     * @return $this|bool
     */
    public function setInFile()
    {
        $returnValue = false;
        $files = [];
        try {
            $files = $this->getFolderContent( $this->getInFolder(), "csv" );
            $returnValue = true;
        } catch (Exception $e ) {
            $this->loggit( $e->getMessage(), ['function'=>__FUNCTION__, 'line'=>__LINE__] );
        }

        foreach( $files as $key => $file ){
            $returnValue = false;
            if( preg_match( '#' . $this::KBT_PEL_REGEX_IN_FILE . '#', $file->getName() ) && $file->getStorage()->isWritable() ){
                $this->inFile = $file;
                $returnValue = true;
                break;
            }
        }

        $this->loggit( var_export($returnValue,1), ['function'=>__FUNCTION__, 'line'=>__LINE__] );
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
     * @return array
     */
    public function getOutFileHeader()
    {
        return $this->outFileHeader;
    }

    /**
     * @return mixed
     */
    public function getSortableKey()
    {
        return $this->sortableKey;
    }

    /**
     * @param mixed $sortableKey
     */
    public function setSortableKey($sortableKey)
    {
        $this->sortableKey = $sortableKey;
    }

    /**
     * @param mixed $sortableKey
     */
    public function setSortableKeyByConf( $conf )
    {

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
     * @param string $message
     * @param array $info keys are "file", "line", "function"
     * @return bool
     */
    public function loggit( $message = "no Message", $info = [] ){

        $text = isset($info['file']) && is_file($info['file']) ? "" . basename($info['file']) . ": ": "";
        $text.= isset($info['line']) && is_numeric($info['line']) ? "@line " . (Integer)$info['line'] . ": ": "";
        $text.= isset($info['function']) ? "(function  " . $info['function'] . "): " : "";
        $text.= " " . $message;

        if ( $fp = fopen('/tmp/data.txt', 'a+') ){
            fwrite($fp, date( "Y-m-d H:i:s" ) . ": " . $text . "\n" );
            fclose($fp);
            return true;
        } else return false;
    }
}
