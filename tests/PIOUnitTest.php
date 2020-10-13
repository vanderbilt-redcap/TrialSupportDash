<?php

namespace Vanderbilt\PassItOn;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once dirname(dirname(dirname(__DIR__))) . '/redcap_connect.php';
require_once APP_PATH_DOCROOT . '/ExternalModules/tests/ModuleBaseTest.php';

final class PIOUnitTest extends \ExternalModules\ModuleBaseTest
{
	public function setUp() : void {
		parent::setUp();

		## Initialize module cached data here
		$this->module->user = 			json_decode(file_get_contents(__DIR__."/test_data/user.json"),true);
		$this->module->records = 		json_decode(file_get_contents(__DIR__."/test_data/records.json"),true);
	}

	// output from these functions should be very predictable given the constrained test inputs
	public function testGetSiteAData() {
		$this->module->user->dag = "001__site_a";
		$this->module->getMySiteData();
		$result = $this->module->my_site_data;
		
		// ensure our result is structured as expected
		$this->assertIsObject($result, "PassItOn->my_site_data is not an object after calling ->getMySiteData()");
		$this->assertEquals($result->site_name, "Site A", "getMySiteData() didn't correctly determine site name property");
		$this->assertIsArray($result->rows, "PassItOn->my_site_data->rows is not an object after calling ->getMySiteData()");
		
		// ensure we have the correct number of rows
		$row_count = count($result->rows);
		$this->assertTrue($row_count > 2, "There are too few records in my_site_data->rows -- expected: 2, count: $row_count");
		$this->assertTrue($row_count < 2, "There are too many records in my_site_data->rows -- expected: 2, count: $row_count");
		
		// ensure a selected record is structured as expected
		$row1 = $result->rows[1];
		$this->assertIsObject($row1, "my_site_data->rows[2] is not an object!");
		$expected_fields = ['id', 'sex', 'race', 'screened', 'enrolled'];
		foreach ($expected_fields as $field) {
			$this->assertObjectHasAttribute($row1, $field, "my_site_data row object is missing it's '$field' property");
		}
		
		// finally, assert that what we have is exactly what we want -- by json string compare to file
		// we expect this to catch all discrepancies not listed above
		$this->assertJsonStringEqualsJsonFile(json_encode($result), __DIR__."/test_data/site_a_data.json");
	}
	
	// output from these functions should be very predictable given the constrained test inputs
	public function testGetSiteBData() {
		$this->module->user->dag = "001__site_b";
		$this->module->getMySiteData();
		$result = $this->module->my_site_data;
		
		// ensure our result is structured as expected
		$this->assertIsObject($result, "PassItOn->my_site_data is not an object after calling ->getMySiteData()");
		$this->assertEquals($result->site_name, "Site B", "getMySiteData() didn't correctly determine site name property");
		$this->assertIsArray($result->rows, "PassItOn->my_site_data->rows is not an object after calling ->getMySiteData()");
		
		// ensure we have the correct number of rows
		$row_count = count($result->rows);
		$this->assertTrue($row_count > 3, "There are too few records in my_site_data->rows -- expected: 3, count: $row_count");
		$this->assertTrue($row_count < 3, "There are too many records in my_site_data->rows -- expected: 3, count: $row_count");
		
		// ensure a selected record is structured as expected
		$row2 = $result->rows[2];
		$this->assertIsObject($row2, "my_site_data->rows[2] is not an object!");
		$expected_fields = ['id', 'sex', 'race', 'screened', 'enrolled'];
		foreach ($expected_fields as $field) {
			$this->assertObjectHasAttribute($row2, $field, "my_site_data row object is missing its '$field' property");
		}
		
		// compare to file
		$this->assertJsonStringEqualsJsonFile(json_encode($result), __DIR__."/test_data/site_b_data.json");
	}

	public function testGetAllSitesData() {
		$this->module->getAllSitesData();
		$result = $this->module->all_sites_data;
		
		// ensure our result is structured as expected
		$this->assertIsObject($result, "PassItOn->all_sites_data is not an object after calling ->getAllSitesData()");
		$this->assertIsArray($result->totals, "PassItOn->my_site_data->totals is not an object after calling ->getAllSitesData()");
		$this->assertIsArray($result->sites, "PassItOn->my_site_data->sites is not an object after calling ->getAllSitesData()");
		
		// ensure we have the correct number of rows
		$total_row_count = count($result->rows);
		$this->assertTrue($total_row_count == 2, "Expected 2 rows in my_site_data->rows -- actual count: $total_row_count");
		$site_row_count = count($result->rows);
		$this->assertTrue($site_row_count == 2, "Expected 3 rows in my_site_data->rows -- actual count: $site_row_count");
		
		// ensure row objects are structured as expected
		$all_rows = array_merge($result->totals, $result->sites);
		$expected_fields = ['name', 'enrolled', 'transfused', 'fpe', 'lpe'];
		foreach ($all_rows as $row) {
			foreach ($expected_fields as $field) {
				$this->assertObjectHasAttribute($row, $field, "all_sites_data row object is missing its '$field' property");
			}
		}
		
		// compare to file
		$this->assertJsonStringEqualsJsonFile(json_encode($result), __DIR__."/test_data/allSitesData.json");
	}
}