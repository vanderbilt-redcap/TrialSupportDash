<?php

namespace Vanderbilt\PassItOn;

require_once dirname(dirname(dirname(__DIR__))) . '/redcap_connect.php';

final class ScreeningLogTest extends \ExternalModules\ModuleBaseTest
{
	public function setUp() : void {
		parent::setUp();

		$pids = $this->getProjectsWithModuleEnabled();
		$_GET['pid'] = reset($pids);
		
		## Initialize module cached data here
		$this->module->dags =				json_decode(file_get_contents(__DIR__."/test_data/dags.json"));
		$this->module->project_ids =		json_decode(file_get_contents(__DIR__."/test_data/project_ids.json"));
		$this->module->event_ids =			json_decode(file_get_contents(__DIR__."/test_data/event_ids.json"));
		$this->module->screening_data =		json_decode(file_get_contents(__DIR__."/test_data/screening_data.json"));
	}
	
	public function testGetScreeningLogData_Site() {
		
	}
	
	public function testGetScreeningLogData_Aggregate() {
		
	}
}