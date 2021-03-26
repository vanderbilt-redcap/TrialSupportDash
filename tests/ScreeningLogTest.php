<?php

namespace Vanderbilt\RAAS_NECTAR;

require_once dirname(dirname(dirname(__DIR__))) . '/redcap_connect.php';

final class ScreeningLogTest extends \ExternalModules\ModuleBaseTest
{
	public function setUp() : void {
		parent::setUp();

		$pids = $this->getProjectsWithModuleEnabled();
		$_GET['pid'] = reset($pids);
		
		## Initialize module cached data here
		$this->module->dags =				json_decode(file_get_contents(__DIR__."/test_data/dags.json"));
		$this->module->screening_data =		json_decode(file_get_contents(__DIR__."/test_data/screening_data.json"));
	}
	
	public function testGetScreeningLogData_AllSites() {
		// compare aggregate results
		$result = $this->module->getScreeningLogData();
		$compare = json_decode(file_get_contents(__DIR__."/test_data/screening_log_data.json"));
		$this->assertEquals($compare, $result);
	}
	
	public function testGetScreeningLogData_SiteE() {
		// compare some site-level results
		$result = $this->module->getScreeningLogData("005__site_e");
		$compare = json_decode(file_get_contents(__DIR__."/test_data/screening_log_data__site_e.json"));
		$this->assertEquals($compare, $result);
	}
	
	public function testGetExclusionData() {
		$result = $this->module->getExclusionReportData();
		$compare = json_decode(file_get_contents(__DIR__."/test_data/exclusion_data.json"));
		$this->assertEquals($compare, $result);
	}
	
	public function testGetScreeningFailData() {
		$result = $this->module->getScreenFailData();
		$compare = json_decode(file_get_contents(__DIR__."/test_data/screening_fail_data.json"));
		$this->assertEquals($compare, $result);
	}
}