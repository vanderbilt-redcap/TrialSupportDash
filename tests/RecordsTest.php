<?php

namespace Vanderbilt\PassItOn;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once dirname(dirname(dirname(__DIR__))) . '/redcap_connect.php';
require_once APP_PATH_DOCROOT . '/ExternalModules/tests/ModuleBaseTest.php';

final class RecordsTest extends \ExternalModules\ModuleBaseTest
{
	## these static variables will hold values that we will compare our function results to
	// static $correct_site_a_data;

	public function setUp() : void {
		parent::setUp();

		## Initialize module cached data here
		$this->module->dags = 			json_decode(file_get_contents(__DIR__."/test_data/dags.json"),true);
		$this->module->project_ids = 			json_decode(file_get_contents(__DIR__."/test_data/project_ids.json"),true);
		$this->module->event_ids = 			json_decode(file_get_contents(__DIR__."/test_data/event_ids.json"),true);
		$this->module->edc_data = 			json_decode(file_get_contents(__DIR__."/test_data/edc_data.json"),true);
	}

	// output from these functions should be very predictable given the constrained test inputs
	public function testGetRecords() {
		$this->module->getRecords();
		$records = $this->module->records;
		
		// ensure our result is structured as expected
		$this->assertIsArray($result, "PassItOn->records is not an array after calling ->getRecords()");
		
		// ensure we have the correct number of rows
		$record_count = count($records);
		$this->assertTrue($record_count == 7, "Expected 7 records in ->records -- actual count: $record_count");
		
		// ensure each record is structured as expected
		$expected_properties = ['dag', 'demographics', 'transfusion'];
		$demographics_properties = ['record_id', 'sex', 'race_ethnicity', 'screen_date', 'randomization_date'];
		$transfusion_properties = ['transfusion_given'];
		foreach($records as $records_i => $record) {
			foreach ($expected_properties as $property) {
				$this->assertObjectHasAttribute($record, $property, "record #$records_i is missing its '$property' property");
			}
			// check demographics sub-object
			$this->assertIsObject($records->demographics, "Expected records->demographics to be an object");
			foreach ($demographics_properties as $property) {
				$this->assertObjectHasAttribute($record->demographics, $property, "record #$records_i is missing its record->demographics->$property property");
			}
			// check transfusion sub-object
			$this->assertIsObject($records->transfusion, "Expected records->transfusion to be an object");
			foreach ($transfusion_properties as $property) {
				$this->assertObjectHasAttribute($record->transfusion, $property, "record #$records_i is missing its record->transfusion->$property property");
			}
		}
		
		// compare to file for other discrepancies
		$this->assertJsonStringEqualsJsonFile(json_encode($records), __DIR__."/test_data/records.json");
	}
}