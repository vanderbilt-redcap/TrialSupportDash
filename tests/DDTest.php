<?php

namespace Vanderbilt\RAAS_NECTAR;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once dirname(dirname(dirname(__DIR__))) . '/redcap_connect.php';
// require_once APP_PATH_DOCROOT . '/ExternalModules/tests/ModuleBaseTest.php';

final class DDTest extends \ExternalModules\ModuleBaseTest
{
	## Setup project context (Required for some REDCap functions)
	function setUp(): void {
		parent::setUp();

		if(!defined("PROJECT_ID")) {
			/** @var $module \Vanderbilt\RAAS_NECTAR\RAAS_NECTAR */
			$module = $this->module;

			$q = \ExternalModules\ExternalModules::getEnabledProjects($module->PREFIX);

			if($row = db_fetch_assoc($q)) {
				define("PROJECT_ID", $row["project_id"]);
				$_GET["pid"] = $row["project_id"];
			}
		}
	}

	function testDD(){
		/** @var $module \Vanderbilt\TrialSupportDash\TrialSupportDash */
		$module = $this->module;

		$projects = $module->getProjectsWithModuleEnabled();

		foreach($projects as $project_id) {
			$useScreening = $module->getProjectSetting("use_screening",$project_id);
			
			if($useScreening) {
				$screeningProject = $module->getProjectSetting("screening_project",$project_id);
				$uaProject = $module->getProjectSetting("user_access_project",$project_id);
	
				$this->assertNotEquals($screeningProject,NULL);
				$this->assertNotEquals($uaProject,NULL);
	
				$demoEvent = $module->getProjectSetting("demographics_event",$project_id);
				$transfusionEvent = $module->getProjectSetting("transfusion_event",$project_id);
				$screeningEvent = $module->getProjectSetting("screening_event",$project_id);
	
				$this->assertNotEquals($demoEvent,NULL);
				$this->assertNotEquals($transfusionEvent,NULL);
	
				$demographicsEventFields = \REDCap::getValidFieldsByEvents($project_id,[$demoEvent]);
				$transfusionEventFields = \REDCap::getValidFieldsByEvents($project_id,[$transfusionEvent]);
				$screeningEventFields = \REDCap::getValidFieldsByEvents($project_id,[$screeningEvent]);
	
				$this->assertContains("sex",$demographicsEventFields);
				$this->assertContains("race_ethnicity",$demographicsEventFields);
				$this->assertContains("screen_date",$demographicsEventFields);
	
				$this->assertContains("randomization_date",$screeningEventFields);
	
				$this->assertContains("transfusion_given",$transfusionEventFields);
				$this->assertContains("transfusion_datetime",$transfusionEventFields);
	
	//			$metadata = $module->getMetadata($project_id);
			}
		}
	}

//	function testEdcData() {
//		/** @var $module \Vanderbilt\RAAS_NECTAR\RAAS_NECTAR */
//		$module = $this->module;

//		$q = \ExternalModules\ExternalModules::getEnabledProjects($module->PREFIX);
//
//		while($row = db_fetch_assoc($q)) {
//			$project_id = $row["project_id"];
//
//			$edcData = $module->getEDCData($project_id);
//			$screeningData = $module->getScreeningData($project_id);
//
//			foreach($edcData as $recordDetails) {
//			    $recordId = $recordDetails->record_id;
//
//			    $this->assertArrayHasKey($recordId,$screeningData);
//			}
//
//			## Remove data from cache for future tests
//			$module->edc_data = false;
//			$module->screening_data = false;
//		}
//	}
}