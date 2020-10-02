<?php
namespace Vanderbilt\PassItOn;

class PassItOn extends \ExternalModules\AbstractExternalModule {
	// authentication
	private $forbidden_roles = ["1042", "1045", "1049", "1051", "1050"];
	
	public function getCurrentUserDAGName() {
		// this function looks through [user_name]s in user_access_project for USERID match
		// when it finds record matching user, return dag_group_name if non-empty

		// determine current user's [user_name]
		$current_user_name = constant(USERID);
		if (empty($current_user_name))
			return false;

		// get user_access_project pid from project settings
		$uad_pid = $this->getProjectSetting('user_access_project');
		if (empty($uad_pid))
			return false;   // throw exception?

		// get other project records
		$params = [
			"project_id" => (int) $uad_pid,
			"return_format" => 'json',
			"fields" => [
				"user_name", "dag_group_name"
			]
		];
		$data = json_decode(\REDCap::getData($params));

		// get and return [dag_group_name] from record whose [user_name] matches $current_user_na
		foreach ($data as $record) {
			if ($current_user_name === $record->user_name) {
				$user_dag_name = $record->dag_group_name;
				break;
			}
		}

		if (empty($user_dag_name)) {
			return false;
		} else {
			return $user_dag_name;	// example return value "001_vanderbilt"
		}
	}
	
	public function getGroupIDsByDAGName($unique_dag_name) {
		// this function attempts to find the group_id for the given unique dag name
		$group_ids = [];
		
		// initialize Project objects
		global $Proj;
		$edc_project = $Proj;
		$screening_pid = $this->getProjectSetting('screening_project');
		if (empty($screening_pid))
			return false;	// throw exception?
		$screening_project = new \Project($screening_pid);
		
		$edc_dag_names = $edc_project->getUniqueGroupNames();
		foreach ($edc_dag_names as $group_id => $unique_name) {
			if ($unique_dag_name == $unique_name) {
				$group_ids['edc_group_id'] = $group_id;
				break;
			}
		}
		
		$screening_dag_names = $screening_project->getUniqueGroupNames();
		foreach ($screening_dag_names as $group_id => $unique_name) {
			if ($unique_dag_name == $unique_name) {
				$group_ids['screening_group_id'] = $group_id;
				break;
			}
		}
		
		return $group_ids;
		/* example result for getGroupIDsByDAGName($unique_dag_name='001_vanderbilt'):
			[
				[edc_group_id] = 210
				[screening_group_id] = 312
			]
			
			example result for getGroupIDsByDAGName($unique_dag_name='not_a_valid_dag_name'):
			[] (empty array)
		*/
	}
	
	public function getRecordsByDAGName($unique_dag_name, $edc_params=null, $screening_params=null) {
		// gets records from EDC and screening project
		$group_ids = $this->getGroupIDsByDAGName($unique_dag_name);
		
		if (empty($group_ids))
			return false;
		if (!empty($group_ids['edc_group_id']))
			$edc_gid = $group_ids['edc_group_id'];
		if (!empty($group_ids['screening_group_id']))
			$screening_gid = $group_ids['screening_group_id'];
		
		$edc_pid = $this->getProjectId();
		$screening_pid = $this->getProjectSetting('screening_project');
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
	
	public function getAllowedUserList() {
		// get user_access_project pid from project settings
		$uad_pid = $this->getProjectSetting('user_access_project');
		if (empty($uad_pid))
			return false;   // throw exception?
		
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
		
		return $allowedUsers;
		/* example return value
			array( [0] => "user_2", [1] => "user_name_5" )
		*/
	}
	
	public function isCurrentUserAllowed() {
		if (SUPER_USER)
			return true;
		
		$current_user = constant("USERID");
		if (empty($current_user))
			return false;
		
		if (empty($this->allowed_users))
			$this->allowed_users = $this->getAllowedUserList();
		
		$allowed_users = $this->getAllowedUserList();
		if (in_array($current_user, $allowed_users)) {
			return true;
		} else {
			return false;
		}
	}
	
	// My Site Metrics
	public function getMySiteMetricsData() {
		$mySiteData = new \stdClass();
		$current_user_dag = $this->getCurrentUserDAGName();
		$group_ids = $this->getGroupIDsByDAGName($current_user_dag);
		
		// get display name for DAG (mySiteData->site_name)
		$dag_display_name = \REDCap::getGroupNames(false, $group_ids['edc_group_id']);
		if (strpos($dag_display_name, "-") !== false) {		// remove numeric prefix and hyphen
			$pieces = explode("-", $dag_display_name);
			if (count($pieces) >= 2)
				$dag_display_name = trim($pieces[1]);
		}
		$mySiteData->site_name = $dag_display_name;
		
		// get records from EDC and screening projects
		$params = [
			"events" => ["screening_arm_1", "event_1_arm_1"],
			"fields" => ["screening_id", "record_id", "sex", "race_ethnicity", "dos", "enroll_yn"]
		];
		$mySiteData->records = $this->getRecordsByDAGName($current_user_dag, $params, $params);
		
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
			$row['name'] = "Example Patient";
			$row['sex'] = $this->getFieldValueLabel('sex', $record['sex']);
			$row['race'] = $this->getFieldValueLabel('race_ethnicity', $record['race_ethnicity']);
			$row['screened'] = !empty($screening_record['dos']) ? "X" : "";
			$row['enrolled'] = !empty($screening_record['enroll_yn']) ? "X" : "";
			$mySiteData->rows[] = $row;
		}
		
		return $mySiteData;
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
