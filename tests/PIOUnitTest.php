<?php

namespace Vanderbilt\RAAS_NECTAR;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once dirname(dirname(dirname(__DIR__))) . '/redcap_connect.php';
// require_once APP_PATH_DOCROOT . '/ExternalModules/tests/ModuleBaseTest.php';

final class PIOUnitTest extends \ExternalModules\ModuleBaseTest
{
    /** @var RAAS_NECTAR $module */
    public $module;

	public function setUp() : void {
		parent::setUp();
		
		$pids = $this->module->getProjectsWithModuleEnabled();
		$_GET['pid'] = reset($pids);
		
		## Initialize module cached data here
        $this->module->user = 			json_decode(file_get_contents(__DIR__."/test_data/user.json"));
		$this->module->dags = 			json_decode(file_get_contents(__DIR__."/test_data/dags.json"));
		$this->module->edc_data = 		json_decode(file_get_contents(__DIR__."/test_data/edc_data.json"));
		$this->module->mappings = 		json_decode(file_get_contents(__DIR__."/test_data/field_mappings.json"),true);
	}

	// output from these functions should be very predictable given the constrained test inputs
	public function testGetSiteAData() {
		$this->module->user->role_ext_2 = "1039";
		$this->module->user->dag_group_name = "001 - Site A";
		$this->module->getMySiteData();
		$result = $this->module->my_site_data;
		
		// ensure our result is structured as expected
		$this->assertIsObject($result, "RAAS_NECTAR->my_site_data is not an object after calling ->getMySiteData()");
		$this->assertEquals("001 - Site A", $result->site_name, "getMySiteData() didn't correctly determine site name property");
		$this->assertIsArray($result->rows, "RAAS_NECTAR->my_site_data->rows is not an object after calling ->getMySiteData()");

		// ensure we have the correct number of rows
		$row_count = count($result->rows);
		$this->assertTrue($row_count == 2, "Expected 2 records, found $row_count");
		
		// ensure a selected record is structured as expected
		$row1 = $result->rows[1];
		$this->assertIsObject($row1, "my_site_data->rows[2] is not an object!");
		$expected_fields = ['id', 'sex', 'race', 'treated', 'enrolled'];
		foreach ($expected_fields as $field) {
			$this->assertObjectHasAttribute($field, $row1, "my_site_data row object is missing it's '$field' property");
		}
		
		// finally, assert equality to catch all discrepancies not listed above
		$compare = json_decode(file_get_contents(__DIR__."/test_data/site_a_data.json"));
		$this->assertEquals($compare, $result);
	}
	
	// output from these functions should be very predictable given the constrained test inputs
	public function testGetSiteBData() {
		$this->module->user->role_ext_2 = "1044";
		$this->module->user->dag_group_name = "002 - Site B";
		$this->module->getMySiteData();
		$result = $this->module->my_site_data;

		// ensure our result is structured as expected
		$this->assertIsObject($result, "RAAS_NECTAR->my_site_data is not an object after calling ->getMySiteData()");
		$this->assertEquals("002 - Site B", $result->site_name, "getMySiteData() didn't correctly determine site name property");
		$this->assertIsArray($result->rows, "RAAS_NECTAR->my_site_data->rows is not an object after calling ->getMySiteData()");
		
		// ensure we have the correct number of rows (all 6 since we set user to have level 3 access role)
		$row_count = count($result->rows);
		$this->assertSame(3, $row_count, "Expected 3 records, found $row_count");
		
		// ensure a selected record is structured as expected
		$row2 = $result->rows[2];
		$this->assertIsObject($row2, "my_site_data->rows[2] is not an object!");
		$expected_fields = ['id', 'sex', 'race', 'treated', 'enrolled'];
		foreach ($expected_fields as $field) {
			$this->assertObjectHasAttribute($field, $row2, "my_site_data row object is missing its '$field' property");
		}
		
		// finally, assert equality to catch all discrepancies not listed above
		$compare = json_decode(file_get_contents(__DIR__."/test_data/site_b_data.json"));
		$this->assertEquals($compare, $result);
	}

	public function testMySiteSuperAccess() {
		$this->module->user->role_ext_2 = "1048";
		$this->module->getMySiteData();
		$result = $this->module->my_site_data;

		// ensure our result is structured as expected
		$this->assertIsObject($result, "RAAS_NECTAR->my_site_data is not an object after calling ->getMySiteData()");
		$this->assertIsArray($result->rows, "RAAS_NECTAR->my_site_data->rows is not an object after calling ->getMySiteData()");

		// ensure we have the correct number of rows (all 6 since we set user to have level 3 access role)
		$row_count = count($result->rows);
		$this->assertTrue($row_count == 6, "Expected 6 records, found $row_count");

		// ensure a selected record is structured as expected
		$row2 = $result->rows[2];
		$this->assertIsObject($row2, "my_site_data->rows[2] is not an object!");
		$expected_fields = ['id', 'site', 'sex', 'race', 'treated', 'enrolled'];
		foreach ($expected_fields as $field) {
			$this->assertObjectHasAttribute($field, $row2, "my_site_data row object is missing its '$field' property");
		}

		// finally, assert equality to catch all discrepancies not listed above
		$compare = json_decode(file_get_contents(__DIR__."/test_data/site_all_super_access.json"));
		$this->assertEquals($compare, $result);
	}

	public function testGetAllSitesData() {
		$this->module->getAllSitesData();
		$result = $this->module->all_sites_data;
		
		// ensure our result is structured as expected
		$this->assertIsObject($result, "RAAS_NECTAR->all_sites_data is not an object after calling ->getAllSitesData()");
		$this->assertIsArray($result->totals, "RAAS_NECTAR->my_site_data->totals is not an object after calling ->getAllSitesData()");
		$this->assertIsArray($result->sites, "RAAS_NECTAR->my_site_data->sites is not an object after calling ->getAllSitesData()");
		
		// ensure we have the correct number of rows
		$totals_row_count = count($result->totals);
		$this->assertTrue($totals_row_count == 2, "Expected 2 rows in my_site_data->rows -- actual count: $totals_row_count");
		$site_row_count = count($result->sites);
		$this->assertTrue($site_row_count == 3, "Expected 3 rows in my_site_data->rows -- actual count: $site_row_count");
		
		// ensure row objects are structured as expected
		$all_rows = array_merge($result->totals, $result->sites);
		$expected_fields = ['name', 'enrolled', 'treated', 'fpe', 'lpe'];
		foreach ($all_rows as $row) {
			foreach ($expected_fields as $field) {
				$this->assertObjectHasAttribute($field, $row, "all_sites_data row object is missing its '$field' property");
			}
		}
		
		// finally, assert equality to catch all discrepancies not listed above
		$compare = json_decode(file_get_contents(__DIR__."/test_data/all_sites_data.json"));
		$this->assertEquals($compare, $result);
	}
}