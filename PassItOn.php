<?php
namespace Vanderbilt\PassItOn;

class PassItOn extends \ExternalModules\AbstractExternalModule {
	public $edc_data;
	public $uad_data;
	private $access_tier_by_role = [
		'1042' => '1',		//	Safety Reviewer/DSMB
		'1045' => '1',		//	Medical Monitor
		'1028' => '3',		//	Principal Investigator (National)
		'1039' => '2',		//	Principal Investigator (Site)
		'1046' => '3',		//	Study Manager (National)
		'1044' => '2',		//	Study Manager (Site)
		'1043' => '3',		//	Statistical Team
		'1041' => '2',		//	Site Study Team
		'1040' => '2',		//	Sub-Investigator (Site)
		'1047' => '3',		//	Financial Team/Administration
		'1048' => '3',		//	Study Leadership/Steering Committee
		'1049' => '1',		//	IDS
		'1050' => '1',		//	Blood Bank
		'1051' => '1',		//	Lab Personnel
		'1030' => '1'		//	Other
	];
	public $record_fields = [
		'record_id',
		'dag',
		'sex',
		'race_ethnicity',
		'screen_date',
		'randomization_date',
		'transfusion_given'
	];

	public function __construct() {
		parent::__construct();

		define("CSS_PATH_1",$this->getUrl("css/style.css"));
		define("JS_PATH_1",$this->getUrl("js/dashboard.js"));
		define("LOGO_LINK", $this->getUrl("images/passItOnLogo.png"));

		require_once(__DIR__."/vendor/autoload.php");
	}
	
	// LOW LEVEL methods
	public function getProjectIDs() {
		if (!isset($this->project_ids)) {
			$project_ids = new \stdClass();
			$project_ids->edc = $this->getProjectId();
			$project_ids->uad = $this->getProjectSetting('user_access_project');
			$this->project_ids = $project_ids;
		}
		
		return $this->project_ids;
	}

	public function getEventIDs() {
		if (!isset($this->event_ids)) {
			$event_ids = new \stdClass();
			$event_ids->demographics = $this->getProjectSetting('demographics_event');
			$event_ids->transfusion = $this->getProjectSetting('transfusion_event');
			$event_ids->screening = $this->getProjectSetting('screening_event');
			$this->event_ids = $event_ids;
		}
		
		return $this->event_ids;
	}

	public function getDAGs() {
		if (!isset($this->dags)) {
			$this->getProjectIDs();
			
			// create global $Proj that REDCap class uses to generate DAG info
			$EDCProject = new \Project($this->project_ids->edc);
			$dags_unique = $EDCProject->getUniqueGroupNames();
			$dags_display = $EDCProject->getGroups();
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
		if (!isset($this->uad_data)) {
			$this->getProjectIDs();
			
			if (!empty($this->project_ids->uad)) {
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
				$uad_data = json_decode(\REDCap::getData($params));
			}
			$this->uad_data = $uad_data;
		}
		
		return $this->uad_data;
	}

	public function getEDCData() {
		if (!isset($this->edc_data)) {
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
				'events' => (array) $this->event_ids,
                'exportDataAccessGroups' => true
			];
			$edc_data = json_decode(\REDCap::getData($params));
			
			$this->edc_data = $edc_data;
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

	public function getUser() {
		if (!isset($this->user)) {
			$this->getUADData();
			foreach ($this->uad_data as $record) {
				if ($record->user_name === constant("USERID")) {
					unset($record->redcap_repeat_instrument);
					unset($record->redcap_repeat_instance);
					$this->user = $record;
					define("PIO_USER_DISPLAY_NAME",$record->first_name." ".$record->last_name);
				}
			}
		}
		
		return $this->user;
	}
	
	// HIGHER LEVEL methods
	public function authorizeUser() {
		/*
			there are multiple tiers of dashboard access
			user->authorized == false
				no dashboard access
			user->authorized == '1'
				user can access dashboard
				user can see all sites data
				user cannot see my site data
			user->authorized == '2'
				user can access dashboard
				user can see all sites data
				user can my site data -- results limited to records with DAG that matches user's DAG
			user->authorized == '3'
				user can access dashboard
				user can see all sites data
				user can see my site data -- including all patient rows from all sites
		*/
		$this->getUser();

		if (empty($this->user->dashboard)) {
			$this->user->authorized = false;
			return;
		}
		
		if (!empty($access_level = $this->access_tier_by_role[$this->user->role_ext_2])) {
			$this->user->authorized = $access_level;
		} else {
			$this->user->authorized = false;
		}
	}
	public function getRecords() {
		if (!isset($this->records)) {
			if($_GET['TESTING']) {
				$this->records = json_decode(file_get_contents(__DIR__."/tests/test_data/records.json"),true);
				
				return $this->records;
			}
			$this->getEDCData();
			
			$records = [];
			$temp_records_obj = new \stdClass();
			$labeled_fields = ['sex', 'race_ethnicity'];
			$label_params = [
				'project_id' => $this->project_ids->edc
			];
			
			// iterate over edc_data, collating data into record objects
			foreach ($this->edc_data as $record_event) {
				// establish $record and $rid
				$rid = $record_event->record_id;
				if (!$record = $temp_records_obj->$rid) {
					$record = new \stdClass();
					
					// set empty fields
					foreach ($this->record_fields as $field) {
						$record->$field = "";
					}
					
					$record->record_id = $rid;
					$temp_records_obj->$rid = $record;
				}
				
				// set non-empty fields
				foreach ($this->record_fields as $field) {
					if (!empty($record_event->$field)) {
						if (in_array($field, $labeled_fields)) {
							$label_params['field_name'] = $field;
							$label_params['record_id'] = $rid;
							$label_params['value'] = $record_event->$field;
							$record->$field = $this->getChoiceLabel($label_params);
							
							if ($field == 'sex') {
								$record->$field = substr($record->$field, 0, 1);
							}
						} else {
							$record->$field = $record_event->$field;
						}
					}
				}
			}
			
			foreach ($temp_records_obj as $record) {
				if (!empty($record->redcap_data_access_group))
					$records[] = $record;
			}
			
			$this->records = $records;
		}
		
		return $this->records;
	}
	public function getMySiteData() {
		if($_GET['TESTING']) {
			return json_decode(file_get_contents(__DIR__."/tests/test_data/site_a_data.json"),true);
		}
		
		$this->getDAGs();
		$this->getUser();
		$this->getRecords();
		$this->authorizeUser();
		
		if ($this->user->authorized == false or $this->user->authorized == '1') {
			$this->my_site_data = false;
			return $this->my_site_data;
		}
		
		$site_data = new \stdClass();
		$site_data->site_name = "";
		$site_data->rows = [];
		
		// get dag and site_name
		$user_dag = $this->user->dag_group_name;
		$site_data->site_name = $user_dag;
		
		// determine group id
		foreach ($this->dags as $gid => $dag) {
			if ($dag->display == $user_dag)
				$group_id = $gid;
		}
		
		// add record rows
		foreach ($this->records as $record) {
			if (($this->user->authorized == '2' and $record->redcap_data_access_group == $group_id) or $this->user->authorized == '3') {
				$row = new \stdClass();
				$row->id = $record->record_id;
				$row->sex = $record->sex;
				$row->race = $record->race_ethnicity;
				$row->screened = $record->screen_date;
				$row->enrolled = $record->randomization_date;
				$site_data->rows[] = $row;
			}
		}
		
		// sort site level data
		if (!function_exists(__NAMESPACE__ . '\sortSiteData')) {
			function sortSiteData($a, $b) {
				if ($a->enrolled == $b->enrolled)
					return 0;
				return $a->enrolled > $b->enrolled ? -1 : 1;
			}
		}
		uasort($site_data->rows, __NAMESPACE__ . '\sortSiteData');
		
		// return
		$this->my_site_data = $site_data;
		return json_decode(json_encode($this->my_site_data), true);
	}
	public function getAllSitesData() {
		if($_GET['TESTING']) {
			return json_decode(file_get_contents(__DIR__."/tests/test_data/all_sites_data.json"),true);
		}
		$this->getDAGs();
		$this->getRecords();
		
		$data = new \stdClass();
		$data->totals = json_decode('[
			{
				"name": "Target",
				"enrolled": 1000,
				"transfused": 500,
				"fpe": "-",
				"lpe": "-"
			},
			{
				"name": "Current Enrolled",
				"enrolled": 0,
				"transfused": 0,
				"fpe": "-",
				"lpe": "-"
			}
		]');
		$data->sites = [];
		
		// create temporary sites container
		$sites = new \stdClass();
		foreach ($this->records as $record) {
			if (!$patient_dag = $record->redcap_data_access_group)
				continue;
			
			// get or make site object
			if (!$site = $sites->$patient_dag) {
				$sites->$patient_dag = new \stdClass();
				$site = $sites->$patient_dag;
				$site->name = $this->getDAGSiteName($this->dags->$patient_dag->unique);
				$site->enrolled = 0;
				$site->transfused = 0;
				$site->fpe = '-';
				$site->lpe = '-';
			}
			
			// update using patient data
			$enroll_date = $record->randomization_date;
			if (!empty($enroll_date)) {
				$data->totals[1]->enrolled++;
				$site->enrolled = $site->enrolled + 1;
				
				if ($site->fpe == '-') {
					$site->fpe = $enroll_date;
				} else {
					if (strtotime($site->fpe) > strtotime($enroll_date))
						$site->fpe = $enroll_date;
				}
				if ($site->lpe == '-') {
					$site->lpe = $enroll_date;
				} else {
					if (strtotime($site->lpe) < strtotime($enroll_date))
						$site->lpe = $enroll_date;
				}
			}
			
			if (!empty($record->transfusion_given)) {
				$data->totals[1]->transfused++;
				$site->transfused = $site->transfused + 1;
			}
		}
		
		// site objects updated with patient data, dump into $data->sites
		// effectively removing keys and keeping values in array
		foreach ($sites as $site) {
			$data->sites[] = $site;
		}
		
		// sort all sites, FPE ascending
		if (!function_exists(__NAMESPACE__ . '\sortAllSitesData')) {
			function sortAllSitesData($a, $b) {
				if ($a->fpe == $b->fpe)
					return 0;
				if ($a->fpe == '-')
					return 1;
				if ($b->fpe == '-')
					return -1;
				return $a->fpe < $b->fpe ? -1 : 1;
			}
		}
		uasort($data->sites, __NAMESPACE__ . '\sortAllSitesData');
		
		// return
		$this->all_sites_data = $data;
		return json_decode(json_encode($this->all_sites_data), true);
	}
	
	// utility
	public function getDAGSiteName($dag_unique_name="") {
		$this->getDAGs();
		foreach ($this->dags as $dag) {
			if ($dag->unique == $dag_unique_name) {
				if (strpos($dag->display, " - ") != false) {
					$dag_name_pieces = explode(" - ", $dag->display);
					return trim($dag_name_pieces[1]);
				} else {
					return $dag->display;
				}
			}
		}
	}
	
	// hooks
	public function redcap_module_link_check_display($pid, $link) {
		if ($link['name'] == 'PassItOn Dashboard') {
			$this->getUser();
			$this->authorizeUser();
			if ($this->user->authorized === false) {
				return false;
			} else {
				return $link;
			}
		} else {
			return $link;
		}
	}
}
