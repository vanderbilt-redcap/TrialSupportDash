<?php
namespace Vanderbilt\PassItOn;

class PassItOn extends \ExternalModules\AbstractExternalModule {
	public $edcData;
	public $screeningData;
	public $uadData;

	// LOW LEVEL methods - not unit testable -- directly interface with database -- no business logic allowed
	public function getUser() {
		
	}
	public function getDAGs() {
		
	}
	public function getProjectIDs() {
		
	}
	public function getEventIDs() {
		
	}
	public function getEdcData($projectId = false) {
		if(!$projectId) {
			$projectId = $_GET['pid'];
		}

		$this->edcData = \REDCap::getData([
			"project_id" => $projectId,
		]);
	}
	public function getScreeningData($projectId = false) {
		if(!$projectId) {
			$projectId = $_GET['pid'];
		}

		$screeningProject = $this->getProjectSetting("screening_project", $projectId);

		$this->screeningData = \REDCap::getData([
				"project_id" => $screeningProject,
		]);
	}
	public function getUadData($projectId = false) {
		if(!$projectId) {
			$projectId = $_GET['pid'];
		}

		$uadProject = $this->getProjectSetting("user_access_project", $projectId);

		$this->uadData = \REDCap::getData([
				"project_id" => $uadProject,
		]);
	}

	// HIGHER LEVEL methods -- unit testable -- do NOT interface with external data sources (db)
	public function authorizeUser() {
		
	}
	public function getRecords() {
		
	}
	public function getMySiteData() {
		
	}
	public function getAllSitesData() {
		
	}
	public function showMySite() {
		
	}
	public function showAllSites() {
		
	}
	
	// hooks
	public function redcap_module_link_check_display($pid, $link) {
		$this->getUser();
		$this->authorizeUser();
		if ($this->user->authorized !== true)
			return false;
		return $link;
	}
}
