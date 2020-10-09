<?php
namespace Vanderbilt\PassItOn;

class PassItOn extends \ExternalModules\AbstractExternalModule {
	public $edc_data;
	public $screening_data;
	public $uad_data;

	// LOW LEVEL methods - not unit testable -- directly interface with database -- no business logic allowed
	public function getUser() {
		
	}
	public function getDAGs() {
		
	}
	public function getProjectIDs() {
		
	}
	public function getEventIDs() {
		
	}
	public function getEDCData($projectId = false) {
		if(!$this->edc_data) {
			if(!$projectId) {
				$projectId = $_GET['pid'];
			}

			$this->edc_data = \REDCap::getData([
				"project_id" => $projectId,
			]);
		}

		return $this->edc_data;
	}
	public function getScreeningData($projectId = false) {
		if(!$this->screening_data) {
			if(!$projectId) {
				$projectId = $_GET['pid'];
			}

			$screeningProject = $this->getProjectSetting("screening_project", $projectId);

			$this->screening_data = \REDCap::getData([
					"project_id" => $screeningProject,
			]);
		}

		return $this->screening_data;
	}
	public function getUADData($projectId = false) {
		if(!$this->screening_data) {
			if(!$projectId) {
				$projectId = $_GET['pid'];
			}

			$uadProject = $this->getProjectSetting("user_access_project", $projectId);

			$this->uad_data = \REDCap::getData([
					"project_id" => $uadProject,
			]);
		}

		return $this->uad_data;
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
