<?php

namespace Vanderbilt\PassItOn;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once dirname(dirname(dirname(__DIR__))) . '/redcap_connect.php';
require_once APP_PATH_DOCROOT . '/ExternalModules/tests/ModuleBaseTest.php';

final class AuthorizationTest extends \ExternalModules\ModuleBaseTest
{
	public function setUp() : void {
		parent::setUp();

		## Initialize module cached data here
		$this->module->user = json_decode(file_get_contents(__DIR__."/test_data/user.json"),true);
	}

	public function testAuthorization() {
		// ensure user with proper requirements authorizes successfully
		$this->module->authorizeUser();
		$this->assertTrue($this->module->user->authorized, "failed to authorize user with valid credentials");
	}

	public function testSuperUser() {
		// ensure SUPER_USERs are authorized
		$super_user = new \stdClass();
		$super_user->super_user = true;
		
		// overwrite setUp user
		$this->module->user = $super_user;
		
		$this->module->authorizeUser();
		$this->assertTrue($this->module->user->authorized, "failed to authorize super user");
	}

	public function testFailRole() {
		// set the user's role to one that we know should be forbidden
		$this->module->user->role_ext_2 = '1042';
		
		$this->module->authorizeUser();
		$this->assertFalse($this->module->user->authorized, "authorized user with invalid [role_ext_2] role!");
	}

	public function testFailDashboard() {
		// set the user's dashboard value to something other than '1'
		$this->module->user->dashboard = '0';
		
		$this->module->authorizeUser();
		$this->assertFalse($this->module->user->authorized, "authorized user with invalid [dashboard] value!");
	}
}