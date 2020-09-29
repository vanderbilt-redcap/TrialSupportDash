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
			   return $user_dag_name;
	   }
	}
	
}
