<?php
namespace Vanderbilt\PassItOn;

class PassItOn extends \ExternalModules\AbstractExternalModule {
	
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
}
