<?php
namespace Vanderbilt\PassItOn;

class PassItOn extends \ExternalModules\AbstractExternalModule {
	// authentication
	private $forbidden_roles = ["1042", "1045", "1049", "1051", "1050"];
	
	// cached getter functions
	public function getCurrentUser() {
		if (!property_exists($this->currentUser)) {
			$this->currentUser = constant("USERID");
		}
		
		return $this->currentUser;
	}
	
	public function getProjectIDs() {
		if (!property_exists($this->projectIDs)) {
			$projectIDs = new \stdClass();
			$projectIDs->edc = $this->getProjectId();
			$projectIDs->uad = $this->getProjectSetting('user_access_project');
			$projectIDs->screening = $this->getProjectSetting('screening_project');
			$this->projectIDs = $projectIDs;
		}
		
		return $this->projectIDs;
	}
	
	public function getUserDAGList() {
		/*
			this function returns an object whose keys are [user_name] values from records in the UAD project
				and whose associated values are unique DAG names from the user's record's [dag_group_name] field
			
			returns object: {
				"[user_name]": "[dag_group_name]",	// examples:
				"user_name_1": "001_dag",
				"diff_user_2": "002_other_dag",
				...
			}
		*/
		
		if (!property_exists($this->userDAGList)) {
			// determine UAD project ID
			if (empty($uad_pid = $this->getProjectIDs()->uad))
				return false;

			// get records
			$params = [
				"project_id" => (int) $uad_pid,
				"return_format" => 'json',
				"fields" => [
					"user_name", "dag_group_name"
				]
			];
			$data = json_decode(\REDCap::getData($params));

			// build list
			$list = new \stdClass();
			foreach ($data as $record) {
				if (!empty($record->user_name)) {
					$user = $record->user_name;
					$list->$user = $record->dag_group_name;
				}
			}
			
			$this->userDAGList = $list;
		}
		return $this->userDAGList;
	}
	
	public function getProjectDAGs() {
		if (!property_exists($this->projectDags)) {
			$projectDags = new \stdClass();
			
			// get EDC unique DAG names
			global $Proj;
			$projectDags->edc = $Proj->getUniqueGroupNames();
			
			// get screening project DAGs
			$screening_pid = $this->getProjectSetting('screening_project');
			if (empty($screening_pid))
				return false;	// throw exception?
			$screening_project = new \Project($screening_pid);
			if (!($screening_project instanceof \Project))
				return false;
			$projectDags->screening = $screening_project->getUniqueGroupNames();
			
			// cache
			$this->projectDags = $projectDags;
		}
		
		return $this->projectDags;
	}
	
	public function getAllowedUsers() {
		if (!property_exists($this->allowedUsers)) {
			// get and store list of allowed users
			if (empty($uad_pid = $this->getProjectIDs()->uad))
				return false;
			
			$params = [
				"project_id" => $uad_pid,
				"return_format" => 'json',
				"fields" => ["record_id", "user_name", "role_ext_2", "dashboard"]
			];
			$records = json_decode(\REDCap::getData($params));
			
			$allowedUsers = [];
			foreach ($records as $record) {
				$role_val = $record->role_ext_2;
				if (!empty($role_val) and !in_array($role_val, $this->forbidden_roles) and $record->dashboard == '1')
					$allowedUsers[] = $record->user_name;
			}
			$this->allowedUsers = $allowedUsers;
		}
		
		return $this->allowedUsers;
		/* example return value
			array [
				[0] => "some_user_abc",
				[1] => "another_user"
			]
		*/
	}
	
	// uncached functions
	public function getGroupIDsByDAGName($unique_dag_name) {
		// this function attempts to find the group_id for the given unique dag name
		$group_ids = new \stdClass();
		
		// get DAGs for EDC and screening project
		$projectDags = $this->getProjectDAGs();
		
		foreach ($projectDags->edc as $group_id => $unique_name) {
			if ($unique_dag_name == $unique_name) {
				$group_ids->edc_group_id = $group_id;
				break;
			}
		}
		foreach ($projectDags->screening as $group_id => $unique_name) {
			if ($unique_dag_name == $unique_name) {
				$group_ids->screening_group_id = $group_id;
				break;
			}
		}
		
		return $group_ids;
		/* example result for getGroupIDsByDAGName($unique_dag_name='001_vanderbilt'):
			{
				"edc_group_id": 210,
				"screening_group_id": 312
			}
			
			example result for getGroupIDsByDAGName($unique_dag_name='not_a_valid_dag_name'):
			{} (empty object)
		*/
	}
	
	public function getRecordsByDAGName($unique_dag_name, $edc_params=null, $screening_params=null) {
		// gets records from EDC and screening project
		$group_ids = $this->getGroupIDsByDAGName($unique_dag_name);
		
		if (empty($group_ids))
			return false;
		if (!empty($group_ids->edc_group_id))
			$edc_gid = $group_ids->edc_group_id;
		if (!empty($group_ids->screening_group_id))
			$screening_gid = $group_ids->screening_group_id;
		
		$edc_pid = $this->getProjectIds()->edc;
		$screening_pid = $this->getProjectIds()->screening;
		if (empty($screening_pid))
			return false;	// throw exception?
		
		$records = new \stdClass();
		
		// overwrite pid/groups from given params and fetch EDC records
		if (!empty($edc_gid)) {
			if (empty($edc_params)) {
				$edc_params = [];
			}
			$edc_params['project_id'] = $edc_pid;
			$edc_params['groups'] = $edc_gid;
			
			$edc_records = \REDCap::getData($edc_params);
			if ($edc_params['return_format'] == 'json')
				$edc_records = json_decode($edc_records);
			
			$records->edc = $edc_records;
		}
		
		// fetch screening records
		if (!empty($screening_gid)) {
			if (empty($screening_params)) {
				$screening_params = [];
			}
			$screening_params['project_id'] = $screening_pid;
			$screening_params['groups'] = $screening_gid;
			
			$screening_records = \REDCap::getData($screening_params);
			if ($screening_params['return_format'] == 'json')
				$screening_records = json_decode($screening_records);
			
			$records->screening = $screening_records;
		}
		
		if (empty($records)) {
			return false;
		} else {
			return $records;
		}
	}
	
	public function isCurrentUserAllowed() {
		if (SUPER_USER)
			return true;
		
		if (in_array($this->getCurrentUser(), $this->getAllowedUsers())) {
			return true;
		} else {
			return false;
		}
	}
	
	public function getDisplayNameByGroupID($group_id) {
		// get display name given a DAG's group_id
		$dag_display_name = \REDCap::getGroupNames(false, $group_id);
		if (strpos($dag_display_name, "-") !== false) {		// remove numeric prefix and hyphen
			$pieces = explode("-", $dag_display_name);
			if (count($pieces) >= 2)
				$dag_display_name = trim($pieces[1]);
		}
		return $dag_display_name;
	}
	
	// My Site Metrics
	public function getMySiteMetricsData($unique_dag_name) {
		$mySiteData = new \stdClass();
		
		$group_ids = $this->getGroupIDsByDAGName($unique_dag_name);
		if (empty($group_ids->edc_group_id))
			return false;
		
		// get display name for DAG (mySiteData->site_name)
		$mySiteData->site_name = $this->getDisplayNameByGroupID($group_ids->edc_group_id);
		
		// get records from EDC and screening projects
		$params = [
			"events" => ["screening_arm_1", "event_1_arm_1"],
			"fields" => ["screening_id", "screen_id", "record_id", "sex", "race_ethnicity", "dos", "enroll_yn"]
		];
		$mySiteData->records = $this->getRecordsByDAGName($unique_dag_name, $params, $params);
		
		return $mySiteData;
	}
	
	public function tabulateMySiteMetricsRows($mySiteData) {
		// tabulate rows
		$mySiteData->rows = [];
		foreach ($mySiteData->records->edc as $rid => $record) {
			// get past event id
			$record = reset($record);
			
			// find matching record in screening records
			$screening_record = null;
			foreach ($mySiteData->records->screening as $rid2 => $record2) {
				$candidate = reset($record2);
				if ($candidate['screening_id'] == $rid) {
					$screening_record = $candidate;
					break;
				}
			}
			if (!$screening_record)		// didn't find a matching record in screening project
				continue;
			
			// have $record (from EDC project) and $screening_record (from screening project), proceed to tabulate My Site Metrics row
			$row = [];
			$row['id'] = $screening_record['screening_id'];
			$row['sex'] = $this->getFieldValueLabel('sex', $record['sex']);
			$row['race'] = $this->getFieldValueLabel('race_ethnicity', $record['race_ethnicity']);
			$row['screened'] = !empty($screening_record['dos']) ? "X" : "";
			$row['enrolled'] = !empty($screening_record['enroll_yn']) ? "X" : "";
			$mySiteData->rows[] = $row;
		}
	}
	
	// All Sites Summary
	public function getAllSitesSummaryData() {
		$data = new \stdClass();
		
		// get DAGs from both projects
		$projects_dags = $this->getProjectsDAGs();
		if (!isset($projects_dags->edc) or empty($projects_dags->edc))
			return false;
		
		// run metrics data pull for each site
		$data->sites = [];
		foreach ($projects_dags->edc as $group_id => $unique_dag) {
			$site_data = $this->getMySiteMetricsData($unique_dag);
			if (!empty($site_data) and !empty($site_data->rows))
				$data->sites[$unique_dag] = $site_data;
		}
		
		// summarize pulled site-level data for table display rows
		$data->summaryRows = [];
		$data->siteRows = [];
		foreach ($data->sites as $dag => $site) {
			/*
				iterate over records for each site
				tabulate total and per-site enrolled #, transfused #, date of first enrollment, date of most recent enrollment
					
			*/
		}
		
		return $data;
	}
	
	// hooks
	public function redcap_module_link_check_display($pid, $link) {
		if ($link['name'] == "PassItOn Dashboard" and $this->isCurrentUserAllowed() == false)
			return false;
		return $link;
	}
	
	// general/utility
	function getFieldValueLabel($field, $value) {
		global $Proj;
		if (empty($value) or empty($Proj->metadata[$field]))
			return false;
		
		$enum = $Proj->metadata[$field]["element_enum"];
		preg_match_all("/$value, ([^\\\]+)/", $enum, $matches);
		if (isset($matches[1][0])) {
			return $matches[1][0];
		}
		
		return false;
	}
}
