<?php

namespace Vanderbilt\PassItOn;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once dirname(dirname(dirname(__DIR__))) . '/redcap_connect.php';
require_once APP_PATH_DOCROOT . '/ExternalModules/tests/ModuleBaseTest.php';

final class PIOUnitTest extends \ExternalModules\ModuleBaseTest
{
	## these static variables will hold values that we will compare our function results to
	// static $correct_site_a_data;
	// static $correct_site_b_data;
	// static $correct_site_c_data;
	// static $correct_all_sites_data;
	// static $correctShowMySite;
	// static $correctShowAllSites;

	public function setUp() : void {
		parent::setUp();

		## Initialize module cached data here
		$this->module->user = 			json_decode(file_get_contents(__DIR__."/test_data/user.json"),true);
		// $this->module->dags = 			json_decode(file_get_contents(__DIR__."/test_data/dags.json"),true);
		// $this->module->project_ids = 	json_decode(file_get_contents(__DIR__."/test_data/project_ids.json"),true);
		// $this->module->event_ids = 		json_decode(file_get_contents(__DIR__."/test_data/event_ids.json"),true);
		$this->module->records = 		json_decode(file_get_contents(__DIR__."/test_data/records.json"),true);
	}

	// output from these functions should be very predictable given the constrained test inputs
	public function testGetMySiteData() {
		$this->module->user->dag = "001__site_a";
		$this->module->getMySiteData();
		$result = $this->module->my_site_data;
		
		// ensure our result is structured as expected
		$this->assertIsObject($result, "PassItOn->my_site_data is not an object after calling ->getMySiteData()");
		$this->assertEquals($result->site_name, "Site A", "getMySiteData() didn't correctly determine site name property");
		$this->assertIsObject($result->rows, "PassItOn->my_site_data->rows is not an object after calling ->getMySiteData()");
		
		// ensure we have the correct number of rows
		$row_count = count($result->rows);
		$this->assertTrue($row_count > 1, "There are too few records in my_site_data->rows -- expected: 2, count: $row_count");
		$this->assertTrue($row_count < 3, "There are too many records in my_site_data->rows -- expected: 2, count: $row_count");
		
		// ensure a selected record is structured as expected
		$row2 = $result->rows[2];
		$this->assertIsObject($row2, "my_site_data->rows[2] is not an object!");
		$expected_fields = ['id', 'sex', 'race', 'screened', 'enrolled'];
		foreach ($expected_fields as $field) {
			$this->assertObjectHasAttribute($row2, $field, "my_site_data row object is missing it's '$field' property");
		}
		
		// finally, assert that what we have is exactly what we want -- by json string compare to file
		// we expect this to catch all discrepancies not listed above
		$this->assertJsonStringEqualsJsonFile(json_encode($result), __DIR__."/test_data/site_a_data.json");
	}

	// public function testGetAllSitesData() {
		// $this->module->getAllSitesData();
		// $this->assertSame($this->module->all_sites_data, self::$correctAllSitesData);
	// }

	// public function testShowMySite() {
		// $this->module->my_site_data = self::$correctAllSitesData;
		
		// // capture emitted HTML from output buffer for comparison
		// ob_start();
		// $this->module->showMySite();
		// $html = ob_get_flush();
		
		// //compare
		// $this->assertSame($html, self::$correctShowMySite);
	// }

	// public function testShowAllSites() {
		// $this->module->all_sites_data = self::$correctAllSitesData;
		
		// // capture emitted HTML from output buffer for comparison
		// ob_start();
		// $this->module->showAllSites();
		// $html = ob_get_flush();
		
		// // compare
		// $this->assertSame($html, self::$correctShowAllSites);
	// }
}