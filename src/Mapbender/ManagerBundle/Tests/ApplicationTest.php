<?php
namespace Mapbender\ManagerBundle\Tests;

use Mapbender\CoreBundle\Tests\TestBase;
use Mapbender\ManagerBundle\Component\ExportHandler;
use Symfony\Component\Yaml\Parser;

/**
 * Class ApplicationTest
 *
 * @package Mapbender\ManagerBundle\Tests
 */
class ApplicationTest extends TestBase
{
    protected static $exportFileSrc;
    protected static $hasExportFile;

    /**
     * @beforeClass
     */
    public static function setUpExportFile()
    {
        $path                = "../tests/data/export.json";
        self::$hasExportFile = file_exists($path);
        if (self::$hasExportFile) {
            self::$exportFileSrc = realpath($path);
        }
    }

    /**
     * @return string
     */
    protected function getJsonExport()
    {
        return file_get_contents(self::$exportFileSrc);
    }

    public function testJsonDecodeArray()
    {
        if(!self::$hasExportFile){
            return;
        }
        $var = json_decode($this->getJsonExport(), true);
    }

    public function testExport()
    {

        if(!self::$hasExportFile){
            return;
        }
        $exportHandler = new ExportHandler(self::$container);
    }

    public function testJsonDecodeObject()
    {

        if(!self::$hasExportFile){
            return;
        }
        $var = json_decode($this->getJsonExport());
    }

    public function testSymfonyParserDecodeObject()
    {

        if(!self::$hasExportFile){
            return;
        }
        $yaml = new Parser();
        $var  = $yaml->parse($this->getJsonExport());
    }


}
