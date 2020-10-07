<?php

namespace Vanderbilt\PassItOn;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once dirname(dirname(dirname(__DIR__))) . '/redcap_connect.php';
require_once APP_PATH_DOCROOT . '/ExternalModules/tests/ModuleBaseTest.php';

final class UnitTest extends \ExternalModules\ModuleBaseTest
{
	static $screeningData = false;
	static $edcData = false;
	static $combinedData = false;
	static $edcDags = false;
	static $screeningDags = false;

	static $testDag = "001__vanderbilt";

	public function setUp() : void {
		parent::setUp();

		## Setup module cached date here
		self::$screeningData = json_decode(file_get_contents(__DIR__."/test_data/screening_data.json"),true);
		self::$edcData = json_decode(file_get_contents(__DIR__."/test_data/edc_data.json"),true);
		self::$combinedData = json_decode(file_get_contents(__DIR__."/test_data/combined_data.json"),true);
		self::$edcDags = json_decode(file_get_contents(__DIR__."/test_data/edc_dags.json"),true);
		self::$screeningDags = json_decode(file_get_contents(__DIR__."/test_data/screening_dags.json"),true);
	}

	function testTabulateMySiteMetricsRows() {
		$siteData = new \stdClass();
		$siteData->records = new \stdClass();

		$siteData->records->edc = self::$edcData;
		$siteData->records->screening = self::$screeningData;

		$this->module->tabulateMySiteMetricsRows($siteData);

		$this->assertEquals($siteData->rows, self::$combinedData);
	}

//	function testGetDagRecords() {
//
//	}
}