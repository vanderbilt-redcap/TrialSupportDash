<?php

namespace Vanderbilt\PassItOn;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once dirname(dirname(dirname(__DIR__))) . '/redcap_connect.php';
require_once APP_PATH_DOCROOT . '/ExternalModules/tests/ModuleBaseTest.php';

final class UnitTest extends \ExternalModules\ModuleBaseTest
{
	static $screeningData = [
		"1" => [
			"100" => [
				"screening_id" => "1",
				"dos" => "2020-01-01",
				"enroll_yn" => "1"
			]
		]
	];

	static $edcData = [
		"1" => [
			"100" => [
				"screening_id" => "1",
				"sex" => "1",
				"demographics" => "1"
			]
		]
	];

	static $combinedData = [
		[
			"id" => "1",
			"sex" => "1",
			"race" => "1",
			"screened" => "2020-01-01",
			"enrolled" => "1"
		]
	];

	static $edcDags = [

	];

	static $screeningDags = [

	];

	static $testDag = "001__vanderbilt";

	public function setUp() : void {
		parent::setUp();

		## Setup module cached date here
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