<?php

namespace Vanderbilt\PassItOn;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once dirname(dirname(dirname(__DIR__))) . '/redcap_connect.php';
require_once APP_PATH_DOCROOT . '/ExternalModules/tests/ModuleBaseTest.php';

final class REDCapTest extends \ExternalModules\ModuleBaseTest
{
	function testGetDags(){
		/** @var $module \Vanderbilt\PassItOn\PassItOn */
		$module = $this->module;

		$q = \ExternalModules\ExternalModules::getEnabledProjects($module->PREFIX);

		if($row = db_fetch_assoc($q)) {
			## Ensure that Project class exists and that it has a method named getUniqueGroupNames
			$projectId = $row['project_id'];

			$this->assertTrue(class_exists("Project"));

			global $Proj;

			$Proj = new \Project($projectId);
			$_GET['pid'] = $projectId;

			$this->assertNotNull($Proj);
			$this->assertTrue(method_exists($Proj,"getUniqueGroupNames"));

			## Ensure expected format of array return with numeric keys and string values
			$dagNames = $Proj->getUniqueGroupNames();

			$this->assertIsArray($dagNames);

			$groupIds = array_keys($dagNames);

			## Ensure return format
			$this->assertIsNumeric(reset($groupIds));
			$this->assertIsString(reset($dagNames));


			## Test getProjectDAGs function
			$projectDags = $module->getProjectDAGs();

			$this->assertNotFalse($projectDags);
			$this->assertEquals($dagNames,$projectDags->edc);
		}
	}
}