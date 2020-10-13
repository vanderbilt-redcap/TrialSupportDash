<?php
namespace Vanderbilt\PassItOn;

class PassItOn extends \ExternalModules\AbstractExternalModule {
	// LOW LEVEL methods
	public function getProjectIDs() {
		if (!property_exists($this->project_ids)) {
			$project_ids = new \stdClass();
			$project_ids->edc = $this->getProjectId();
			$project_ids->uad = $this->getProjectSetting('user_access_project');
			$this->project_ids = $project_ids;
		}
		
		return $this->project_ids;
	}
	public function getEventIDs() {
		if (!property_exists($this->event_ids)) {
			$event_ids = new \stdClass();
			$event_ids->demographics = $this->getProjectSetting('demographics_event');
			$event_ids->transfusion = $this->getProjectSetting('transfusion_event');
			$event_ids->screening = $this->getProjectSetting('screening_event');
			$this->event_ids = $event_ids;
		}
		
		return $this->event_ids;
	}
	public function getDAGs() {
		if (!property_exists($this->dags)) {
			$this->getProjectIDs();
			
			// create global $Proj that REDCap class uses to generate DAG info
			global $Proj;
			$Proj = new \Project($this->project_ids->edc);
			$dags_unique = \REDCap::getGroupNames(true);
			$dags_display = \REDCap::getGroupNames();
			$dags = new \stdClass();
			foreach ($dags_unique as $group_id => $unique_name) {
				// get display name
				if (empty($display_name = $dags_display[$group_id]))
					$display_name = "";
				
				// add entry with unique and display name with group_id as key
				$dags->$group_id = new \stdClass();
				$dags->$group_id->unique = $unique_name;
				$dags->$group_id->display = $display_name;
				
				unset($display_name);
			}
			
			$this->dags = $dags;
		}
		
		return $this->dags;
	}
	public function getUADData() {
		if (!property_exists($this->uad_data)) {
			$this->getProjectIDs();
			
			$params = [
				'project_id' => $this->project_ids->uad,
				'return_format' => 'json',
				'fields' => [
					'record_id',
					'first_name',
					'last_name',
					'role_ext_2',
					'dashboard',
					'user_name',
					'dag_group_name'
				]
			];
			$uad_data = \REDCap::getData($params);
			$this->uad_data = $uad_data;
		}
		
		return $this->uad_data;
	}
	public function getEDCData() {
		if (!property_exists($this->edc_data)) {
			$this->getProjectIDs();
			$this->getEventIDs();
			
			$params = [
				'project_id' => $this->project_ids->edc,
				'return_format' => 'json',
				'fields' => [
					'record_id',
					'transfusion_given',
					'randomization_date',
					'sex',
					'race_ethnicity',
					'screen_date'
				],
				'events' => (array) $this->event_ids
			];
			$edc_data = \REDCap::getData($params);
			$this->edc_data = $edc_data;
		}
		
		return $this->edc_data;
	}
	public function getUser() {
		
	}
	
	// HIGHER LEVEL methods
	public function authorizeUser() {
		
	}
	public function getRecords() {
		
	}
	public function getMySiteData() {
		
	}
	public function getAllSitesData() {
		
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
