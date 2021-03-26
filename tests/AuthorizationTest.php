<?php

namespace Vanderbilt\RAAS_NECTAR;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once dirname(dirname(dirname(__DIR__))) . '/redcap_connect.php';
// require_once APP_PATH_DOCROOT . '/ExternalModules/tests/ModuleBaseTest.php';

final class AuthorizationTest extends \ExternalModules\ModuleBaseTest
{
	public function setUp() : void {
		parent::setUp();
		
		$pids = $this->getProjectsWithModuleEnabled();
		$_GET['pid'] = reset($pids);
		
		## Initialize module cached data here
		$this->module->user = json_decode(file_get_contents(__DIR__."/test_data/user.json"));
	}
	
	public function testAuthorizationNoRecord() {
		$this->module->user = new \stdClass();
		$this->module->authorizeUser();
		$this->assertSame(false, $this->module->user->authorized, "authorized empty object user (no UAD record)");
	}

	public function testAuthorizationFailDashboard() {
		$this->module->user->dashboard = "";
		$this->module->authorizeUser();
		$this->assertSame(false, $this->module->user->authorized, "authorized user with empty [dashboard] value");
	}

	public function testAuthorizationFailRole() {
		$this->module->user->role_ext_2 = "";
		$this->module->authorizeUser();
		$this->assertSame(false, $this->module->user->authorized, "authorized user with invalid [role_ext_2] value");
	}

	public function testAuthorizationValid_1() {
		$this->module->user->role_ext_2 = "1042";
		$this->module->authorizeUser();
		$this->assertSame('1', $this->module->user->authorized, "authorized user with invalid credentials");
	}

	public function testAuthorizationValid_2() {
		$this->module->user->role_ext_2 = "1039";
		$this->module->authorizeUser();
		$this->assertSame('2', $this->module->user->authorized, "authorized user with invalid credentials");
	}

	public function testAuthorizationValid_3() {
		$this->module->user->role_ext_2 = "1028";
		$this->module->authorizeUser();
		$this->assertSame('3', $this->module->user->authorized, "authorized user with invalid credentials");
	}
}