<?php

namespace Vanderbilt\PassItOn;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once dirname(dirname(dirname(__DIR__))) . '/redcap_connect.php';
require_once APP_PATH_DOCROOT . '/ExternalModules/tests/ModuleBaseTest.php';

final class DDTest extends \ExternalModules\ModuleBaseTest
{
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
		}
	}
}