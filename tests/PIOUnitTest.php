<?php

namespace Vanderbilt\PassItOn;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once dirname(dirname(dirname(__DIR__))) . '/redcap_connect.php';
require_once APP_PATH_DOCROOT . '/ExternalModules/tests/ModuleBaseTest.php';

final class TestPassItOn extends \ExternalModules\ModuleBaseTest
{
	## these static variables will hold values that we will compare our function results to
	static $correctMySiteData;
	static $correctAllSitesData;
	static $correctShowMySite;
	static $correctShowAllSites;

	public function setUp() : void {
		parent::setUp();

		## Initialize module cached data here
		$this->module->user = 			json_decode(file_get_contents(__DIR__."/test_data/user.json"),true);
		$this->module->dags = 			json_decode(file_get_contents(__DIR__."/test_data/dags.json"),true);
		$this->module->project_ids = 	json_decode(file_get_contents(__DIR__."/test_data/project_ids.json"),true);
		$this->module->event_ids = 		json_decode(file_get_contents(__DIR__."/test_data/event_ids.json"),true);
		$this->module->records = 		json_decode(file_get_contents(__DIR__."/test_data/records.json"),true);
		
		self::$correctMySiteData = 		json_decode(file_get_contents(__DIR__."/test_data/mySiteData.json"),true);
		self::$correctAllSitesData = 	json_decode(file_get_contents(__DIR__."/test_data/allSitesData.json"),true);
		self::$correctShowMySite = 		json_decode(file_get_contents(__DIR__."/test_data/showMySite.html"),true);
		self::$correctShowAllSites = 	json_decode(file_get_contents(__DIR__."/test_data/showAllSites.html"),true);
	}

	// output from these functions should be very predictable given the constrained test inputs
	public function testGetMySiteData() {
		$this->module->getMySiteData();
		$this->assertSame($this->module->my_site_data, self::$correctMySiteData);
	}

	public function testGetAllSitesData() {
		$this->module->getAllSitesData();
		$this->assertSame($this->module->all_sites_data, self::$correctAllSitesData);
	}

	public function testShowMySite() {
		$this->module->my_site_data = self::$correctAllSitesData;
		
		// capture emitted HTML from output buffer for comparison
		ob_start();
		$this->module->showMySite();
		$html = ob_get_flush();
		
		//compare
		$this->assertSame($html, self::$correctShowMySite);
	}

	public function testShowAllSites() {
		$this->module->all_sites_data = self::$correctAllSitesData;
		
		// capture emitted HTML from output buffer for comparison
		ob_start();
		$this->module->showAllSites();
		$html = ob_get_flush();
		
		// compare
		$this->assertSame($html, self::$correctShowAllSites);
	}
}