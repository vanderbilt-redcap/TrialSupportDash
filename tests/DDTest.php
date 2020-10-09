<?php

namespace Vanderbilt\PassItOn;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once dirname(dirname(dirname(__DIR__))) . '/redcap_connect.php';
require_once APP_PATH_DOCROOT . '/ExternalModules/tests/ModuleBaseTest.php';

final class DDTest extends \ExternalModules\ModuleBaseTest
{
	## Setup project context (Required for some REDCap functions)
	function setUp(): void {
		parent::setUp();

		if(!defined("PROJECT_ID")) {
			/** @var $module \Vanderbilt\PassItOn\PassItOn */
			$module = $this->module;

			$q = \ExternalModules\ExternalModules::getEnabledProjects($module->PREFIX);

			if($row = db_fetch_assoc($q)) {
				define("PROJECT_ID", $row["project_id"]);
				$_GET["pid"] = $row["project_id"];
			}
		}
	}

	function testDD(){
		/** @var $module \Vanderbilt\PassItOn\PassItOn */
		$module = $this->module;

		$q = \ExternalModules\ExternalModules::getEnabledProjects($module->PREFIX);

		while($row = db_fetch_assoc($q)) {
			$project_id = $row["project_id"];

			$screeningProject = $module->getProjectSetting("screening_project",$project_id);
			$uaProject = $module->getProjectSetting("user_access_project",$project_id);

			$this->assertNotEquals($screeningProject,NULL);
			$this->assertNotEquals($uaProject,NULL);

			$demoEvent = $module->getProjectSetting("demographics_event",$project_id);
			$transfusionEvent = $module->getProjectSetting("transfusion_event",$project_id);

			$this->assertNotEquals($demoEvent,NULL);
			$this->assertNotEquals($transfusionEvent,NULL);

			$demographicsEventFields = \REDCap::getValidFieldsByEvents($project_id,[$demoEvent]);
			$transfusionEventFields = \REDCap::getValidFieldsByEvents($project_id,[$transfusionEvent]);

			$this->assertContains("sex",$demographicsEventFields);
			$this->assertContains("race_ethnicity",$demographicsEventFields);

			## TODO Add additional fields
			## TODO Check fields on screening project too

			$this->assertContains("transfusion_given",$transfusionEventFields);
			$this->assertContains("transfusion_datetime",$transfusionEventFields);

//			$metadata = $module->getMetadata($project_id);
		}
	}

	function testEdcData() {
		/** @var $module \Vanderbilt\PassItOn\PassItOn */
		$module = $this->module;

		$q = \ExternalModules\ExternalModules::getEnabledProjects($module->PREFIX);

		while($row = db_fetch_assoc($q)) {
			$project_id = $row["project_id"];

			$_GET["pid"] = $project_id;
			global $Proj;

			$Proj = new \Project($project_id);

			$siteData = $module->getAllSitesSummaryData();

			foreach($siteData->sites as $dagName => $dagData) {
				$module->tabulateMySiteMetricsRows($dagData);
				$mergedRecords = array_map(function($data) {
					return $data["id"];
				},$dagData->rows);

				$edcRecords = array_keys($dagData->edc);

				$this->assertEquals($edcRecords,$mergedRecords);
			}
		}
	}
}