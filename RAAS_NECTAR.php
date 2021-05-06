<?php
namespace Vanderbilt\TrialSupportDash;

class RAAS_NECTAR extends \ExternalModules\AbstractExternalModule {
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
		'redcap_data_access_group',
        'dag',
        'dag_name',
		'sex',
		'race_ethnicity',
		'transfusion_datetime',
		'randomization_date',
		'randomization',
		'transfusion_given'
	];
	
	protected const MAX_FOLDER_NAME_LEN = 60;		// folder names truncated after 48 characters

	public function __construct() {
		parent::__construct();

		define("CSS_PATH_1",$this->getUrl("css/style.css"));
		define("JS_PATH_1",$this->getUrl("js/dashboard.js"));
		define("LOGO_LINK", $this->getUrl("images/passItOnLogo.png"));

		require_once(__DIR__."/vendor/autoload.php");
	}
	
	// LOW LEVEL methods
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

	public function getDAGs($project_id = false) {
		if (!isset($this->dags)) {
		    if(!$project_id) {
		        $project_id = $_GET['pid'];
            }
			
			// create global $Proj that REDCap class uses to generate DAG info
			$EDCProject = new \Project($project_id);
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

	public function getFieldLabelMapping($fieldName = false) {
		if(!isset($this->mappings)) {
			$this->mappings = [];
			foreach($this->record_fields as $thisField) {
				$choices = $this->getChoiceLabels($thisField);

				if($choices && (count($choices) > 1 || reset($choices)) != "") {
					$this->mappings[$thisField] = $choices;
				}
			}
		}

		if($fieldName) {
			if(isset($this->mappings[$fieldName])) {
				return $this->mappings[$fieldName];
			}
			else {
				return false;
			}
		}
		else {
			return $this->mappings;
		}
	}

	public function getUADData($project_id = false) {
		if (!isset($this->uad_data)) {
		    if(!$project_id) {
		        $project_id = $_GET['pid'];
            }
		    $uadProject = $this->getProjectSetting("user_access_project",$project_id);
			
			if (!empty($uadProject)) {
				$params = [
					'project_id' => $uadProject,
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

	public function getEDCData($project_id = false) {
		if (!isset($this->edc_data) || !$this->edc_data) {
            if(!$project_id) {
                $project_id = $_GET['pid'];
            }
			$this->getEventIDs();
			
			$params = [
				'project_id' => $project_id,
				'return_format' => 'json',
				'fields' => $this->record_fields,
				'events' => (array) $this->event_ids,
                'exportDataAccessGroups' => true
			];
			$edc_data = json_decode(\REDCap::getData($params));

			$projectDags = $this->getDAGs($project_id);

			// add dag property to each based on its record_id
			foreach ($edc_data as $record) {
				foreach($projectDags as $groupId => $thisDag) {
				    if($thisDag->unique == $record->redcap_data_access_group) {
                        $record->dag = $groupId;
                        $record->dag_name = $thisDag->display;
                        break;
                    }
                }
			}

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

            $this->screening_data = json_decode(\REDCap::getData([
                "project_id" => $screeningProject,
				"return_format" => "json",
                'exportDataAccessGroups' => true
            ]));
        }

        return $this->screening_data;
    }

	public function getUser() {
		if (!isset($this->user)) {
		    $this->user = false;

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

		// if ($this->user === true || empty($this->user->dashboard)) {
			$this->user->authorized = true;
			return;
		// }
		
		if (!empty($access_level = $this->access_tier_by_role[$this->user->role_ext_2])) {
			$this->user->authorized = $access_level;
		} else {
			$this->user->authorized = false;
		}
	}
	public function getRecords($project_id = false) {
		if (!isset($this->records)) {
			if($_GET['TESTING']) {
				$this->records = json_decode(file_get_contents(__DIR__."/tests/test_data/records.json"),true);
				
				return $this->records;
			}

            if(!$project_id) {
                $project_id = $_GET['pid'];
            }
			$this->getEDCData($project_id);
			
			$records = [];
			$temp_records_obj = new \stdClass();
			$label_params = [
				'project_id' => $project_id
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
						$labels = $this->getFieldLabelMapping($field);

						if($labels) {
							$record->$field = $labels[$record_event->$field];
						}
						else {
							$record->$field = $record_event->$field;
						}

						## Special shortening for certain fields
						if($field == "sex") {
							$record->$field = substr($record->$field, 0, 1);
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
		$site_data->site_name = $this->user->dag_group_name;

		// add record rows
		foreach ($this->records as $record) {
			if (($this->user->authorized == '2' and $record->dag_name == $this->user->dag_group_name) or $this->user->authorized == '3') {
				$row = new \stdClass();
				$row->id = $record->record_id;
				if ($this->user->authorized == '3') {
					$row->site = $record->dag_name;
				}
				$row->sex = $record->sex;
				$row->race = $record->race_ethnicity;
				$row->enrolled = $record->randomization_date;
				$row->treated = "";
				// convert transfusion_datetime from Y-m-d H:m to Y-m-d
				if (!empty($record->transfusion_datetime))
					$row->treated = date("Y-m-d", strtotime($record->transfusion_datetime));

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
				"treated": 1000,
				"fpe": "-",
				"lpe": "-"
			},
			{
				"name": "Current Enrolled",
				"enrolled": 0,
				"treated": 0,
				"fpe": "-",
				"lpe": "-"
			}
		]');
		$data->sites = [];
		
		// create temporary sites container
		$sites = new \stdClass();
		foreach ($this->records as $record) {
			if (!$patient_dag = $record->dag)
				continue;

			// get or make site object
			if (!$site = $sites->$patient_dag) {
				$sites->$patient_dag = new \stdClass();
				$site = $sites->$patient_dag;
				$site->name = $record->dag_name;
				$site->enrolled = 0;
				$site->treated = 0;
				$site->fpe = '-';
				$site->lpe = '-';
			}
			
			// update using patient data
			if (!empty($record->randomization)) {
				$data->totals[1]->enrolled++;
				$site->enrolled = $site->enrolled + 1;
			}

			$enroll_date = $record->randomization_date;
			if (!empty($enroll_date)) {
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
				$data->totals[1]->treated++;
				$site->treated = $site->treated + 1;
			}
		}
		
		// site objects updated with patient data, dump into $data->sites
		// effectively removing keys and keeping values in array
		foreach ($sites as $site) {
			$data->sites[] = $site;
		}
		
		// sort all sites, enrolled ascending
		if (!function_exists(__NAMESPACE__ . '\sortAllSitesData')) {
			function sortAllSitesData($a, $b) {
				if ($a->enrolled == $b->enrolled)
					return 0;
				return $a->enrolled < $b->enrolled ? 1 : -1;
			}
		}
		uasort($data->sites, __NAMESPACE__ . '\sortAllSitesData');
		
		// return
		$this->all_sites_data = $data;
		return json_decode(json_encode($this->all_sites_data), true);
	}
	public function getScreeningLogData($site = null) {
		// // currently not caching this function
		// if (!isset($this->screening_log_data)) {
			
		// }
		
		// return $this->screening_log_data;
		
		// determine earliest screened date (upon which weeks array will be based)
		$screening_data = $this->getScreeningData();
		$first_date = date("Y-m-d");
		$last_date = date("Y-m-d", 0);
		foreach ($screening_data as $record) {
			$site_match_or_null = $site === null ? true : $record->redcap_data_access_group == $site;
			if (!empty($record->dos) and $site_match_or_null) {
				if (strtotime($record->dos) < strtotime($first_date))
					$first_date = date("Y-m-d", strtotime($record->dos));
				if (strtotime($record->dos) > strtotime($last_date))
					$last_date = date("Y-m-d", strtotime($record->dos));
			}
		}
		if (strtotime($last_date) == 0)
			$last_date = $first_date;
		
		// determine date of Monday on or before first_date found
		$day_of_week = date("N", strtotime($first_date));
		$rewind_x_days = $day_of_week - 1;
		$first_monday = date("Y-m-d", strtotime("-$rewind_x_days days", strtotime($first_date)));
		
		// make report data object and rows
		$screening_log_data = new \stdClass();
		$screening_log_data->rows = [];
		$total_screened = 0;
		$iterations = 0;
		while (true) {
			$screened_this_week = 0;
			
			// determine week boundary dates
			$day_offset1 = ($iterations) * 7;
			$day_offset2 = $day_offset1 + 4;
			$date1 = date("Y-m-d", strtotime("+$day_offset1 days", strtotime($first_monday)));
			$date2 = date("Y-m-d", strtotime("+$day_offset2 days", strtotime($first_monday)));
			
			$row = [];
			$row[0] = date("n/j", strtotime($date1)) . "-" . date("n/j", strtotime($date2));
			$row[0] = str_replace("\\", "", $row[0]);
			
			// count records that were screened this week
			$ts_a = strtotime($date1);
			$ts_b = strtotime("+24 hours", strtotime($date2));
			// echo "\$date1, \$date2, \$ts_a, \$ts_b: $date1, $date2, $ts_a, $ts_b\n";
			foreach ($screening_data as $record) {
				$ts_x = strtotime($record->dos);
				$site_match_or_null = $site === null ? true : $record->redcap_data_access_group == $site;
				if ($ts_a <= $ts_x and $ts_x <= $ts_b and $site_match_or_null)
					$screened_this_week++;
			}
			$total_screened += $screened_this_week;
			
			$row[1] = $screened_this_week;
			$row[2] = $total_screened;
			
			$screening_log_data->rows[] = $row;
			
			$iterations++;
			
			// see if the week row just created captures the last screened date
			// if so, break here
			$cutoff_timestamp = strtotime("+1 days", $ts_b);
			if ($cutoff_timestamp > strtotime($last_date) or $iterations > 999)
				break;
		}
		$screening_log_data->rows[] = ["Grand Total", $total_screened, $total_screened];
		return $screening_log_data;
	}
	public function getExclusionReportData() {
		if (!isset($this->exclusion_data)) {
			// create data object
			$exclusion_data = new \stdClass();
			$exclusion_data->rows = [];
			
			// get labels, init exclusion counts
			$screening_pid = $this->getProjectSetting('screening_project');
			$labels = $this->getChoiceLabels("exclude_primary_reason", $screening_pid);
			$exclusion_counts = [];
			foreach ($labels as $i => $label) {
				$exclusion_counts[$i] = 0;
			}
			
			// iterate through screening records, summing exclusion reasons
			$screening_data = $this->getScreeningData();
			foreach ($screening_data as $record) {
				if (!empty($record->exclude_primary_reason) and isset($exclusion_counts[$record->exclude_primary_reason]))
					$exclusion_counts[$record->exclude_primary_reason]++;
			}
			
			// add rows to data object
			foreach ($labels as $i => $label) {
				$exclusion_data->rows[] = [
					"#$i",
					$label,
					$exclusion_counts[$i]
				];
			}
			$this->exclusion_data = $exclusion_data;
		}
		return $this->exclusion_data;
	}
	public function getScreenFailData() {
		if (!isset($this->screen_fail_data)) {
			$screen_fail_data = new \stdClass();
			$screen_fail_data->rows = [];
			$labels = $this->getChoiceLabels("not_enrolled_reason", $this->getProjectSetting('screening_project'));
			$screen_fail_counts = [];
			foreach ($labels as $i => $label) {
				$screen_fail_counts[$i] = 0;
			}
			$screening_data = $this->getScreeningData();
			foreach ($screening_data as $record) {
				if (!empty($record->not_enrolled_reason) and isset($screen_fail_counts[$record->not_enrolled_reason]))
					$screen_fail_counts[$record->not_enrolled_reason]++;
			}
			foreach ($labels as $i => $label) {
				$screen_fail_data->rows[] = [
					"#$i",
					$label,
					$screen_fail_counts[$i]
				];
			}
			$this->screen_fail_data = $screen_fail_data;
		}
		
		return $this->screen_fail_data;
	}
	public function getHelpfulLinks() {
		$link_settings = $this->getSubSettings('helpful_links_folders');
		$links = [];
		
		foreach($link_settings as $i => $folder) {
			foreach($folder['helpful_links'] as $link_info) {
				// skip links with missing URL
				if (empty($link_info['link_url'])) {
					continue;
				}
				
				$link = new \stdClass();
				$link->url = $link_info['link_url'];
				
				// prepend http protocol text if missing to avoid pathing to ExternalModules/...
				if (strpos($link->url, "http") === false) {
					$link->url = "http://" . $link->url;
				}
				
				if (empty($link_info['link_display'])) {
					$link->display = $link->url;
				} else {
					$link->display = $link_info['link_display'];
				}
				
				$link->folder_index = $i;
				
				$links[] = $link;
			}
		}
		
		return $links;
	}
	public function getHelpfulLinkFolders() {
		$link_settings = $this->getSubSettings('helpful_links_folders');
		
		$folders = [];
		foreach($link_settings as $i => $folder_info) {
			$folder = new \stdClass();
			
			$folder->name = $folder_info['helpful_links_folder_text'];
			if (empty($folder->name)) {
				$folder->name = "Folder " . ($i + 1);
			} elseif (strlen($folder->name) > $this::MAX_FOLDER_NAME_LEN) {
				$folder->name = substr($folder->name, 0, $this::MAX_FOLDER_NAME_LEN) . "...";
			}
			
			$folder->color = $folder_info['helpful_links_folder_color'];
			$css_hex_color_pattern = "/#([[:xdigit:]]{3}){1,2}\b/";
			if (!preg_match($css_hex_color_pattern, $folder->color)) {
				// Ensures folders have a valid color
				$folder->color = "#edebb4";
			}
			
			// if $folder_info['helpful_links'] not array, throw exception
			
			$folder->linkCount = count($folder_info['helpful_links']);
			if (!is_numeric($folder->linkCount)) {
				$folder->linkCount = 0;
			}
			
			$folders[] = $folder;
		}
		
		return $folders;
	}
	
	// hooks
	public function redcap_module_link_check_display($pid, $link) {
		if ($link['name'] == 'RAAS_NECTAR Dashboard') {
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