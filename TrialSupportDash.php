<?php

namespace Vanderbilt\TrialSupportDash;

use stdClass;

// require_once(__DIR__ . "/TrialSupportDash.php");

// class TrialSupportDash extends \Vanderbilt\TrialSupportDash\TrialSupportDash
class TrialSupportDash extends \ExternalModules\AbstractExternalModule {
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
	public $personnel_roles = [
		'PI',
		'Primary Coordinator',
		'Pharmacist'
	];
	public $document_signoff_fields = [
		'cv' => 'cv_review_vcc',
		'doa' => 'doa_vcc_review',
		'license' => 'license_review_vcc',
		'fdf' => 'fin_dis_review_vcc',
		'hand_prof' => 'handwrite_review_vcc',
		'gcp' => 'gcp_review_vcc',
		'hsp' => 'citi_review_vcc',
		'training' => 'train_review_vcc'
	];
	public $personnel_form_complete_fields = [
		'cv_complete',
		'license_complete',
		'study_training_complete',
		'human_subjects_training_complete',
		'gcp_training_complete',
		'delegation_of_authority_doa_complete',
		'financial_disclosure_complete',
		'iata_training_complete',
		'handwriting_profile_complete'
	];
	
	private const MAX_FOLDER_NAME_LEN = 60;		// folder names truncated after 48 characters
	
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
	public function getEnrollmentChartData($site = null) {
		// determine earliest screened date (upon which weeks array will be based)
		$enroll_data = $this->getEDCData();
		$first_date = date("Y-m-d");
		$last_date = date("Y-m-d", 0);
		foreach ($enroll_data as $record) {
			$site_match_or_null = $site === null ? true : $record->redcap_data_access_group == $site;
			if (!empty($record->randomization_date) and $site_match_or_null) {
				if (strtotime($record->randomization_date) < strtotime($first_date))
					$first_date = date("Y-m-d", strtotime($record->randomization_date));
				if (strtotime($record->randomization_date) > strtotime($last_date))
					$last_date = date("Y-m-d", strtotime($record->randomization_date));
			}
		}
		if (strtotime($last_date) == 0)
			$last_date = $first_date;
		
		// determine date of Monday on or before first_date found
		$day_of_week = date("N", strtotime($first_date));
		$rewind_x_days = $day_of_week - 1;
		$first_monday = date("Y-m-d", strtotime("-$rewind_x_days days", strtotime($first_date)));
		
		// make report data object and rows
		$enrollment_chart_data = new \stdClass();
		$enrollment_chart_data->rows = [];
		$cumulative_enrolled = 0;
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
			foreach ($enroll_data as $record) {
				$ts_x = strtotime($record->randomization_date);
				$site_match_or_null = $site === null ? true : $record->redcap_data_access_group == $site;
				if ($ts_a <= $ts_x and $ts_x <= $ts_b and $site_match_or_null)
					$enrolled_this_week++;
			}
			$cumulative_enrolled += $enrolled_this_week;
			
			$row[1] = $enrolled_this_week;
			$row[2] = $cumulative_enrolled;
			
			$enrollment_chart_data->rows[] = $row;
			
			$iterations++;
			
			// see if the week row just created captures the last screened date
			// if so, break here
			$cutoff_timestamp = strtotime("+1 days", $ts_b);
			if ($cutoff_timestamp > strtotime($last_date) or $iterations > 999)
				break;
		}
		$enrollment_chart_data->rows[] = ["Grand Total", $cumulative_enrolled, $cumulative_enrolled];
		return $enrollment_chart_data;
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
	
	// RAAS additions
	public function getVCCSiteStartUpFieldList() {
		$regulatoryPID = $this->getProjectSetting('site_regulation_project');
		if (empty($regulatoryPID)) {
			throw new \Exception("The RAAS/NECTAR module couldn't get start-up fields because the 'TrialSupportDash Site Regulation Project ID' setting is not configured. Please configure the module by selecting a regulatory project.");
		}
		
		$reg_dd = json_decode(\REDCap::getDataDictionary($regulatoryPID, 'json'));
		if (empty($reg_dd)) {
			throw new \Exception("The RAAS/NECTAR module couldn't get start-up fields -- fatal error trying to decode the Data Dictionary (json) for the regulatory project (PID: " . $regulatoryPID . ")");
		}
		
		$field_names = [];
		foreach($reg_dd as $field_info) {
			$name = $field_info->field_name;
			if ($field_info->form_name == 'vcc_site_start_up') {
				$field_names[] = $name;
			}
		}
		
		return $field_names;
	}
	public function getSiteStartupData() {
		// return array of site objects, each with data used to build Site Activation tables
		$activation_fields = $this->getVCCSiteStartUpFieldList();
		if (empty($activation_fields)) {
			throw new \Exception("The RAAS/NECTAR module couldn't retrieve the list of fields in the VCC Site Start Up form (in the regulatory project)");
		}
		
		$regulatoryPID = $this->getProjectSetting('site_regulation_project');
		
		// add extra field(s) useful for site activation tables
		$activation_fields[] = 'record_id';
		$activation_fields[] = 'role';
		$activation_fields[] = 'site_number';
		
		// these fields are used for the site activation table cells information/statuses
		$activation_fields = array_merge($activation_fields, array_values($this->document_signoff_fields));
		
		// if stop is true for a record, don't select that record to be the role personnel for any role
		$activation_fields[] = 'stop';
		
		// add form complete fields so we get all instances (even if instances are full of empty field values)
		$activation_fields = array_merge($activation_fields, $this->personnel_form_complete_fields);
		
		$params = [
			"project_id" => $regulatoryPID,
			"return_format" => 'json',
			"fields" => $activation_fields,
			"exportAsLabels" => true
		];
		$data = json_decode(\REDCap::getData($params));
		if (empty($data)) {
			throw new \Exception("Couldn't retrieve site activation data from regulatory project.");
		}
		
		// separate data entries into sites[] and personnel[]
		$startup_data = new \stdClass();
		$startup_data->sites = [];
		$startup_data->personnel = [];
		foreach($data as $index => $entry) {
			if (strpos($entry->redcap_event_name, 'Site Documents') !== false) {
				$startup_data->sites[] = $entry;
			} elseif (strpos($entry->redcap_event_name, 'Personnel Documents') !== false) {
				$startup_data->personnel[] = $entry;
			}
		}
		unset($data);
		
		$this->processStartupPersonnelData($startup_data->personnel);
		$this->processStartupSiteData($startup_data->sites, $startup_data->personnel);
		
		return $startup_data;
	}
	public function processStartupPersonnelData(&$personnel_data) {
		foreach($personnel_data as &$data) {
			foreach($data as $key => $value) {
				if (empty($value))
					unset($data->$key);
			}
		}
		
		// array for complete personnel data objects
		$personnel = new \stdClass();
		
		// filter out older records if multiple exist for a given [role]
		// throw exception if can't determine a single record for a given role
		$candidates = [];
		$reg_pid = $this->getProjectSetting('site_regulation_project');
		$reg_project_event_table = \REDCap::getLogEventTable($reg_pid);
		$rid_field = $this->getRecordIdField($reg_pid);
		foreach($personnel_data as $i => $personnel_record_event) {
			if (empty($personnel_record_event->redcap_repeat_instrument)) {
				// [stop] is set if a person leaves the org or is no longer that acts as that role for the study(ies)
				if (!empty($personnel_record_event->stop)) {
					continue;
				}
				
				// create candidate objects, we're not sure which personnel record we're going to select to be the person for each role yet
				// we want to select personnel based on the record's creation date (recent records will get chosen over older records) and their [role] field value
				// we also want to make sure we select 1 and only 1 person for each role
				$candidate = new \stdClass();
				$rid = $personnel_record_event->$rid_field;
				$candidate->$rid_field = $rid;
				$candidate->role = $personnel_record_event->role;
				
				// try to determine when this record was created
				$result = $this->query("SELECT ts FROM $reg_project_event_table WHERE project_id = ? AND data_values LIKE ? AND description = 'Create record'",
					[$reg_pid,
					"%$rid_field = '$rid'%"]
				);
				$result_rows = [];
				while($row = $result->fetch_assoc()) {
					$result_rows[] = $row;
				}
				if (count($result_rows) > 1) {
					throw new \Exception("The RAAS/NECTAR module couldn't determine a single timestamp for when this record ($rid) was created!");
				}
				if (isset($result_rows[0])) {
					$candidate->create_ts = $result_rows[0]['ts'];
				}
				$candidates[] = $candidate;
			}
		}
		
		// now that we have creation timestamps and role info, select our personnel records (filter out others)
		foreach($this->personnel_roles as $i => $role) {
			$max_ts = 0;
			$count_role = 0;
			$selected_candidate = null;
			foreach($candidates as $j => $candidate) {
				if ($candidate->role == $role) {
					$count_role++;
					if (isset($candidate->create_ts)) {
						if ($candidate->create_ts > $max_ts) {
							$selected_candidate = $candidate;
						}
						$max_ts = max($candidate->create_ts, $max_ts);
					} elseif ($selected_candidate == null) {
						$selected_candidate = $candidate;
					}
				}
			}
			
			if ($max_ts == 0 and $count_role > 1) {
				// none of our personnel records have creation timestamps and there are more than one... so which one do we use? we can't determine
				throw new \Exception("The RAAS/NECTAR module couldn't determine which personnel record to use for role '$role' (most likely there are multiple personnel records with this role and the module can't determine when each were created)");
			}
			
			if (empty($selected_candidate)) {
				throw new \Exception("The RAAS/NECTAR module couldn't determine which personnel record to use for role '$role' -- most likely there are no records created with this [role] value.");
			}
			
			$role_name = strtolower(preg_replace('/[ ]+/', '_', $role));
			$personnel->$role_name = $selected_candidate;
		}
		
		// use all record-events in personnel data to fill in field info for candidates (matching on record id)
		foreach($personnel as $role => &$data) {
			$latest_instances = new \stdClass();	// holds max instance id found for repeating instances (we want to ignore older instances)
			
			// loop over instance info to record max instance id for each personnel form
			foreach($personnel_data as $i => $record_data) {
				if ($record_data->$rid_field == $data->$rid_field && isset($record_data->redcap_repeat_instrument)) {
					$form_name = $record_data->redcap_repeat_instrument;
					if (!isset($latest_instances->$form_name)) {
						$latest_instances->$form_name = $record_data->redcap_repeat_instance;
					} else {
						$latest_instances->$form_name = max($record_data->redcap_repeat_instance, $latest_instances->$form_name);
					}
				}
			}
			
			foreach($personnel_data as $i => $record_data) {
				if (
					// if it's the latest repeated instance for this form, or not a repeated form, and the record_id matches this personnel: copy properties
					((isset($latest_instances->{$record_data->redcap_repeat_instrument})
					&&
					$latest_instances->{$record_data->redcap_repeat_instrument} == $record_data->redcap_repeat_instance)
					||
					!isset($record_data->redcap_repeat_instrument))
					&&
					$record_data->$rid_field == $data->$rid_field
				) {
					foreach($record_data as $key => $value) {
						$data->$key = $value;
					}
				}
			}
		}
		
		$personnel_data = $personnel;
	}
	public function processStartupSiteData(&$sites, $personnel) {
		foreach($sites as &$site) {
			foreach($site as $key => $value) {
				if (empty($value))
					unset($site->$key);
			}
		}
		
		$reg_pid = $this->getProjectSetting('site_regulation_project');
		$reg_project = new \Project($reg_pid);
		$personnel_event_id = array_key_first($reg_project->events[2]['events']);
		$todays_date = new \DateTime(date("Y-m-d", time()));
		
		// calculate study admin cell values and classes
		foreach($sites as &$site) {
			foreach($this->personnel_roles as $role_name) {
				$role = str_replace(' ', '_', strtolower($role_name));
				$site->$role = [];
				$cells = &$site->$role;
				if (empty($personnel->$role)) {
					throw new \Exception ("The RAAS/NECTAR module couldn't determine which record to use for $role_name role information.");
				}
				
				foreach($this->document_signoff_fields as $data_field => $check_field) {
					// cbox value stored with suffix in personnel->role
					$check_field_prop = $check_field . "___1";
					
					// append prefixes where needed
					if ($role == 'primary_coordinator') {
						$db_data_field = 'ksp_' . $data_field;
					} elseif ($role == 'pharmacist') {
						$db_data_field = 'pharm_' . $data_field;
					} else {
						$db_data_field = $data_field;
					}
					
					if ($data_field == 'doa') {
						if ($role == "pi") {
							$db_data_field = 'doa_pi';
						} elseif ($role == "primary_coordinator") {
							$db_data_field = 'pi_ksp_doa';
						} elseif ($role == "pharmacist") {
							$db_data_field = 'pharm_doa_pi';
						}
					}
					
					$cells[$data_field] = [];
					$cells[$data_field]['value'] = $site->$db_data_field;
					if (empty($site->$db_data_field)) {
						$cells[$data_field]['class'] = 'signoff';
					} elseif ($site->$db_data_field == "Confirmed by VCC") {
						if ($personnel->$role->$check_field_prop == 'Checked') {
							// get most recent sign-off date (from most recent instance)
							$max_instance = $this->getMaxInstance($reg_pid, $personnel->$role->record_id, $personnel_event_id, $check_field);
							$history = $this->getDataHistoryLog($reg_pid, $personnel->$role->record_id, $personnel_event_id, $check_field, $max_instance);
							if (!empty($history)) {
								$cells[$data_field]['last_changed'] = substr(array_key_first($history), 0, -2);	// chop off last two digits -- timestamp was previously multiplied by 100
								$checked_date = new \DateTime(date("Y-m-d", $cells[$data_field]['last_changed']));
								$cells[$data_field]['value'] = $cells[$data_field]['value'] . " (" . $todays_date->diff($checked_date)->format("%a") . " days)";
								
								$cells[$data_field]['class'] = 'signoff green';
							} else {
								$cells[$data_field]['class'] = 'signoff red';
							}
						} else {
							$cells[$data_field]['class'] = 'signoff red';
						}
					} elseif ($site->$db_data_field == 'Initiated' || $site->$db_data_field == 'Awaiting Site Response') {
						$cells[$data_field]['class'] = 'signoff red';
					} else {
						$cells[$data_field]['class'] = 'signoff yellow';
					}
				}
			}
		}
		
		// add count of days between site engaged and site open for enrollment
		foreach($sites as $index => $site) {
			$site_start_ts = strtotime($site->site_engaged);
			$site_open_ts = strtotime($site->open_date);
			if ($site_start_ts && $site_open_ts) {
				$site_start_date = new \DateTime(date("Y-m-d", $site_start_ts));
				$site_open_date = new \DateTime(date("Y-m-d", $site_open_ts));
				$sites[$index]->start_to_finish_duration = $site_open_date->diff($site_start_date)->format("%a") . " days to site activation";
			}
		}
	}
	public function getDataHistoryLog($project_id, $record, $event_id, $field_name) {
		// copied closely from \Form::getDataHistoryLog but allows dev to provide $project_id to target other projects
		
		global $lang;
		
		$maxInstance = $this->getMaxInstance($project_id, $record, $event_id, $field_name);
		$instance = $maxInstance;
		
		$GLOBALS['Proj'] = $Proj = new \Project($project_id);
		$longitudinal = $Proj->longitudinal;
		$missingDataCodes = parseEnum($Proj->project['missing_data_codes']);
		
		// Set field values
		$field_type = $Proj->metadata[$field_name]['element_type'];
        $field_val_type = $Proj->metadata[$field_name]['element_validation_type'];

		// Version history enabled
        $version_history_enabled = ($field_type == 'file' && $field_val_type != 'signature' && \Files::fileUploadVersionHistoryEnabledProject($project_id));

		// Determine if a multiple choice field (do not include checkboxes because we'll used their native logging format for display)
		$isMC = ($Proj->isMultipleChoice($field_name) && $field_type != 'checkbox');
		if ($isMC) {
			$field_choices = parseEnum($Proj->metadata[$field_name]['element_enum']);
		}
		
		$hasFieldViewingRights = true;
		
		// Format the field_name with escaped underscores for the query
		$field_name_q = str_replace("_", "\\_", $field_name);
		
		// REPEATING FORMS/EVENTS: Check for "instance" number if the form is set to repeat
		$instanceSql = "";
		$isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($event_id, $Proj->metadata[$field_name]['form_name']);
		if ($isRepeatingFormOrEvent) {
			// Set $instance
			$instance = is_numeric($instance) ? (int)$instance : 1;
			if ($instance > 1) {
				$instanceSql = "and data_values like '[instance = $instance]%'";
			} else {
				$instanceSql = "and data_values not like '[instance = %'";
			}
		}
		
		// Default
		$time_value_array = array();
		$arm = isset($Proj->eventInfo[$event_id]) ? $Proj->eventInfo[$event_id]['arm_num'] : getArm();

		// Retrieve history and parse field data values to obtain value for specific field
		$sql = "SELECT user, timestamp(ts) as ts, data_values, description, change_reason, event 
                FROM ".\Logging::getLogEventTable($project_id)." WHERE project_id = " . $project_id . " and pk = '" . db_escape($record) . "'
				and (
				(
					(event_id = $event_id " . ($longitudinal ? "" : "or event_id is null") . ")
					and legacy = 0 $instanceSql
					and
					(
						(
							event in ('INSERT', 'UPDATE')
							and description in ('Create record', 'Update record', 'Update record (import)',
								'Create record (import)', 'Merge records', 'Update record (API)', 'Create record (API)',
								'Update record (DTS)', 'Update record (DDP)', 'Erase survey responses and start survey over',
								'Update survey response', 'Create survey response', 'Update record (Auto calculation)',
								'Update survey response (Auto calculation)', 'Delete all record data for single form',
								'Delete all record data for single event', 'Update record (API) (Auto calculation)')
							and (data_values like '%\\n{$field_name_q} = %' or data_values like '{$field_name_q} = %' 
								or data_values like '%\\n{$field_name_q}(%) = %' or data_values like '{$field_name_q}(%) = %')
						)
						or
						(event = 'DOC_DELETE' and data_values = '$field_name')
						or
						(event = 'DOC_UPLOAD' and (data_values like '%\\n{$field_name_q} = %' or data_values like '{$field_name_q} = %' 
													or data_values like '%\\n{$field_name_q}(%) = %' or data_values like '{$field_name_q}(%) = %'))
					)
				)
				or 
				(event = 'DELETE' and description like 'Delete record%' and (event_id is null or event_id in (".prep_implode(array_keys($Proj->events[$arm]['events'])).")))
				)
				order by log_event_id";
		$q = db_query($sql);
		// Loop through each row from log_event table. Each will become a row in the new table displayed.
        $version_num = 0;
        $this_version_num = "";
        $rows = array();
        $deleted_doc_ids = array();
        while ($row = db_fetch_assoc($q))
        {
            $rows[] = $row;
            // For File Version History for file upload fields, get doc_id all any that were deleted
            if ($version_history_enabled) {
                $value = html_entity_decode($row['data_values'], ENT_QUOTES);
                foreach (explode(",\n", $value) as $this_piece) {
                    $doc_id = \Form::dataHistoryMatchLogString($field_name, $field_type, $this_piece);
                    if (is_numeric($doc_id)) {
                        $doc_delete_time = Files::wasEdocDeleted($doc_id);
                        if ($doc_delete_time) {
                            $deleted_doc_ids[$doc_id] = $doc_delete_time;
                        }
                    }
                }
            }
        }
        // Loop through all rows
		foreach ($rows as $row)
		{
			// If the record was deleted in the past, then remove all activity before that point
			if ($row['event'] == 'DELETE') {
				$time_value_array = array();
                $version_num = 0;
				continue;
			}
			// Flag to denote if found match in this row
			$matchedThisRow = false;
			// Get timestamp
			$ts = $row['ts'];
			// Get username
			$user = $row['user'];
			// Decode values
			$value = html_entity_decode($row['data_values'], ENT_QUOTES);
            // Default return string
            $this_value = "";
            // Split each field into lines/array elements.
            // Loop to find the string match
            foreach (explode(",\n", $value) as $this_piece)
            {
                $isMissingCode = false;
                // Does this line match the logging format?
                $matched = \Form::dataHistoryMatchLogString($field_name, $field_type, $this_piece);
                if ($matched !== false || ($field_type == "file" && ($this_piece == $field_name || strpos($this_piece, "$field_name = ") === 0)))
                {
                    // Set flag that match was found
                    $matchedThisRow = true;
                    // File Upload fields
                    if ($field_type == "file")
                    {
						if (isset($missingDataCodes[$matched])) {
							// Set text
							$this_value = $matched;
							$doc_id = null;
							$this_version_num = "";
							$isMissingCode = true;
						} elseif ($matched === false || $matched == '') {
                            // For File Version History, don't show separate rows for deleted files
                            if ($version_history_enabled) continue 2;
                            // Deleted
                            $doc_id = null;
                            $this_version_num = "";
                            // Set text
                            $this_value = \RCView::span(array('style'=>'color:#A00000;'), $lang['docs_72']);
                        } elseif (is_numeric($matched)) {
                            // Uploaded
                            $doc_id = $matched;
                            $doc_name = \Files::getEdocName($doc_id);
                            $version_num++;
                            $this_version_num = $version_num;
                            // Set text
                            $this_value = \RCView::span(array('style'=>'color:green;'),
                                            $lang['data_import_tool_20']
                                            ). " - \"{$doc_name}\"";
                        }
                        break;
                    }
                    // Stop looping once we have the value (except for checkboxes)
                    elseif ($field_type != "checkbox")
                    {
                        $this_value = $matched;
                        break;
                    }
                    // Checkboxes may have multiple values, so append onto each other if another match occurs
                    else
                    {
                        $this_value .= $matched . "<br>";
                    }
                }
            }

            // If a multiple choice question, give label AND coding
            if ($isMC && $this_value != "")
            {
                if (isset($missingDataCodes[$this_value])) {
					$this_value = decode_filter_tags($missingDataCodes[$this_value]) . " ($this_value)";
                } else {
					$this_value = decode_filter_tags($field_choices[$this_value]) . " ($this_value)";
				}
            }

			// Add to array (if match was found in this row)
			if ($matchedThisRow) {			
				// If user does not have privileges to view field's form, redact data
				if (!$hasFieldViewingRights) {
					$this_value = "<code>".$lang['dataqueries_304']."</code>";
				} elseif ($field_type != "file") {
					$this_value = nl2br(htmlspecialchars(br2nl(label_decode($this_value)), ENT_QUOTES));
				}
				// Set array key as timestamp + extra digits for padding for simultaneous events
				$key = strtotime($ts)*100;
				// Ensure that we don't overwrite existing logged events
				while (isset($time_value_array[$key.""])) $key++;
				// Display missing data code?
				$returningMissingCode = (isset($missingDataCodes[$this_value]) && !\Form::hasActionTag("@NOMISSING", $Proj->metadata[$field_name]['misc']));
				// Add to array
				$time_value_array[$key.""] = array( 'ts'=>$ts, 'value'=>$this_value, 'user'=>$user, 'change_reason'=>nl2br($row['change_reason']),
                                                    'doc_version'=>$this_version_num, 'doc_id'=>(isset($doc_id) ? $doc_id : null),
                                                    'doc_deleted'=>(isset($doc_id) && isset($deleted_doc_ids[$doc_id]) ? $deleted_doc_ids[$doc_id] : ""),
                                                    'missing_data_code'=>($returningMissingCode ? $this_value : ''));
			}
		}
		// Sort by timestamp
		ksort($time_value_array);
		// Return data history log
		return $time_value_array;
	}
	public function getMaxInstance($project_id, $record, $event_id, $field_name) {
		$q = $this->createQuery();
		$q->add("SELECT MAX(instance) FROM redcap_data WHERE project_id = ? AND record = ? AND event_id = ? AND field_name = ?", [
			$project_id,
			$record,
			$event_id,
			$field_name
		]);
		$result = $q->execute();
		return $result->fetch_assoc()['MAX(instance)'];
	}
	
	// hooks
	public function redcap_module_link_check_display($pid, $link) {
		if ($link['name'] == 'TrialSupportDash Dashboard') {
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
	
	/* trial support methods/properties */
	
	public $record_fields = [
			'record_id',
			'redcap_data_access_group',
			'dag',
			'dag_name',
			'class',
	];
	
	public function __construct() {
		parent::__construct();

		define("CSS_PATH_1",$this->getUrl("css/style.css"));
		define("CSS_MAIN_ACTIVE", $this->getUrl("css/mainActive.css"));

		define("JS_PATH_1",$this->getUrl("js/dashboard.js"));
		define("LOGO_LINK", $this->getUrl("images/nectar_logo.png"));

		require_once(__DIR__."/vendor/autoload.php");
	}
	
	public function mergeRecordFields()
	{
		$configFields = $this->getSubSettings('record_fields');
		foreach ($configFields as $key => $field_name) {
			$configFields = array_values($field_name);
			$record_fields = array_merge($this->record_fields, $configFields);
		}

		return $record_fields;
	}

	public function getFieldLabelMapping($fieldName = false)
	{

		if (!isset($this->mappings)) {
			$this->mappings = [];
			foreach ($this->mergeRecordFields() as $thisField) {
				$choices = $this->getChoiceLabels($thisField);
				if ($choices && (count($choices) > 1 || reset($choices)) != "") {
					$this->mappings[$thisField] = $choices;
				}
			}
		}

		if ($fieldName) {

			if(isset($this->mappings[$fieldName])){
				return $this->mappings[$fieldName];
			}else{
				return false;
			}
		} else {
			return $this->mappings;
		}
	}

	public function getDAGs($project_id = false)
	{
		if (!isset($this->dags)) {
			if (!$project_id) {
				$project_id = $_GET['pid'];
			}

			// create global $Proj that REDCap class uses to generate DAG info
			$EDCProject = new \Project($project_id);
			$dags_unique = $EDCProject->getUniqueGroupNames();
			$dags_display = $EDCProject->getGroups();
			$regions = $this->getRegions($project_id);

			$dags = new \stdClass();
			foreach ($dags_unique as $group_id => $unique_name) {
				// get display name
				if (empty($display_name = $dags_display[$group_id]))
					$display_name = "";

				if (empty($regions_name = $regions[$group_id]))
					$regions_name = "";

				// add entry with unique and display name with group_id as key
				$dags->$group_id = new \stdClass();
				$dags->$group_id->unique = $unique_name;
				$dags->$group_id->display = $display_name;
				$dags->$group_id->region = $regions_name;

				unset($display_name);
			}

			$this->dags = $dags;
		}
		return $this->dags;
	}

	public function getRegions($project_id)
	{

		$region_array = [];


		//getting json text area from config
		$getJsonSetting = $this->getProjectSetting('json_text_dag');
		//change from json to array 
		$dag_array = json_decode($getJsonSetting, TRUE);
		//loop to set array to region and save at config
		foreach ($dag_array as $group_id => $unique_name) {
			$region_array[$group_id] = $unique_name['region'];
		}
		return $region_array;
	}


	public function setDagsSetting()
	{
		$this->getDAGs();

		$json_dags = $this->getDAGs();
		return $this->setProjectSetting('json_text_dag', json_encode($json_dags, JSON_PRETTY_PRINT));
	}

	public function getDagsSetting()
	{
		$dags_json = json_decode($this->getProjectSetting('json_text_dag'));

		$dag_region = [];
		foreach ($dags_json as $value) {
			$dag_region[] = $value->region;
		}
		return $dag_region;
	}

	public function Merge_Objects($object1, $object2)
	{
		$Obj1 = (array) $object1;
		$Obj2 = (array) $object2;
		$merged = array_merge_recursive($Obj1, $Obj2);
		return (object) $merged;
	}


	public function getEDCData($project_id = false)
	{
		if (!isset($this->edc_data) || !$this->edc_data) {
			if (!$project_id) {
				$project_id = $_GET['pid'];
			}
			// $this->getEventIDs();
			$fields = $this->mergeRecordFields();
		

			$params = [
				'project_id' => $project_id,
				'return_format' => 'json',
				'fields' => $fields,
				//'events' => $this->event_ids,
				'combine_checkbox_values' => true,
				'exportDataAccessGroups' => true,

			];
			$edc_data = json_decode(\REDCap::getData($params));
			$projectDags = $this->getDAGs($project_id);
			// add dag property to each based on its record_id		
			$checkboxRecords = [];
			foreach ($edc_data as $record) {

			


				if ($record->redcap_data_access_group == '') {
					$record->redcap_data_access_group = 'dcri_call_center';
				}


				foreach ($projectDags as $groupId => $thisDag) {
					if ($record->redcap_data_access_group == 'dcri_call_center') {
						$record->dag = '100000';
						$record->dag_name = 'DCRI Call Center';
						$record->class = $thisDag->region;
						break;
					}
					if ($thisDag->unique == $record->redcap_data_access_group) {
						$record->dag = $groupId;
						$record->dag_name = $thisDag->display;
						$record->class = $thisDag->region;
						break;
					}
				}
			}
			$this->edc_data = $edc_data;
		}
		return $this->edc_data;
	}



	public function getRecords($project_id = false)
	{

		if (!isset($this->records)) {
			if ($_GET['TESTING']) {
				$this->records = json_decode(file_get_contents(__DIR__ . "/tests/test_data/records.json"), true);

				return $this->records;
			}

			if (!$project_id) {
				$project_id = $_GET['pid'];
			}
			$this->getEDCData($project_id);
			
			$records = [];
			$temp_records_obj = new \stdClass();
			$label_params = [
				'project_id' => $project_id
			];

			$race = [];
			// iterate over edc_data, collating data into record objects
			foreach ($this->edc_data as $record_event) {
				
		

				// establish $record and $rid
				$rid = $record_event->record_id;
				if (!$record = $temp_records_obj->$rid) {
					$record = new \stdClass();

					// set empty fields
					foreach ($this->mergeRecordFields() as $field) {
						$record->$field = "";
					}

					$record->record_id = $rid;
					$temp_records_obj->$rid = $record;
					/// here is where it adds elements 
					
				}
				$race = [];
				// set non-empty fields
				foreach ($this->mergeRecordFields() as $field) {
					
					if(!empty($record_event->$field)){
						$labels = $this->getFieldLabelMapping($field);
						if ($labels) {
							if (\REDCap::getFieldType($field) == 'checkbox') {
								//seperate each value by comma
								$comma = explode(',', $record_event->$field);
								//loop each comma and match key with label
								foreach ($comma as $raw_key => $raw_value) {
									$comma[$raw_key] = $labels[$raw_value];
								}
							
								//each comma value has label and we seperate by comma
								$record_event->$field = implode(', ', $comma);

								//to checkbox field with label
								$record->$field = $record_event->$field;


							}else{
								$record->$field = $labels[$record_event->$field];

							}
							
						} else{
							$record->$field = $record_event->$field;
						}				

					}
					
				
				}


			}

			foreach ($temp_records_obj as $record) {
				if (!empty($record->redcap_data_access_group)){
					
					$records[] = $record;
				

				}
			}

			$this->records = $records;
		}
		return $this->records;
	}


	public function getAllSitesData()
	{
		if ($_GET['TESTING']) {
			return json_decode(file_get_contents(__DIR__ . "/tests/test_data/all_sites_data.json"), true);
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
				$site->class = $record->class;
			}

			// update using patient data
			if (!empty($record->drug_receive_pro)) {
				$data->totals[1]->enrolled++;
				$site->enrolled = $site->enrolled + 1;
			}
			$enroll_date = $record->randomization_time;
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

			if (!empty($record->randomization_time)) {
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
			function sortAllSitesData($a, $b)
			{
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
	
	public function getScreeningLogData($site = null) {
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
	
	public function getMySiteData()
	{
		if ($_GET['TESTING']) {
			return json_decode(file_get_contents(__DIR__ . "/tests/test_data/site_a_data.json"), true);
		}

		$this->getDAGs();
		$this->getUser();
		$this->getRecords();
		// $this->authorizeUser();


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
				// $row->race = $record->ethnic;
				
				$row->race = $record->race_eth;
		
				$row->enrolled = $record->drug_receive_pro;
				$row->treated = "";
				// convert transfusion_datetime from Y-m-d H:m to Y-m-d
				if (!empty($record->randomization_time))
					$row->treated = date("Y-m-d", strtotime($record->randomization_time));

				$site_data->rows[] = $row;
			}
		}

		// sort site level data
		if (!function_exists(__NAMESPACE__ . '\sortSiteData')) {
			function sortSiteData($a, $b)
			{
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

	public function getExclusionData($projectId = false)
	{

		if (!$projectId) {
			$projectId = $_GET['pid'];
		}

		//we are getting instrument name
		$instrument_name = "inclusionexclusion";
		//getting all fields in that instrument
		$inclusionexclusionFields = \REDCap::getFieldNames($instrument_name);

		$OnlyExclusion = [];
		//make array that have name exclusion_ 
		foreach ($inclusionexclusionFields as $field) {
			if (strpos($field, "exclusion_") !== false) {
				$OnlyExclusion[] = $field;
			}
		}
		//remove fist element do not need
		array_shift($OnlyExclusion);
		//remove last element do not need
		array_pop($OnlyExclusion);
		//get data just from that instrument
		$this->data = json_decode(\REDCap::getData([
			"project_id" => $projectId,
			"return_format" => "json",
			"fields" => $OnlyExclusion,
			"exportDataAccessGroups" => true,
		]));



		return $this->data;
	}

	//new function to get the key 
	public function getProjectSettingExclusion()
	{
		$exclusions = $this->getProjectSetting('exclusion_reason_field');

		$exclusion_field_key = [];

		foreach ($exclusions as $i => $exclusionArray) {


			foreach ($exclusionArray as $exclusion_field) {
				$exclusion_field_key[$exclusion_field] = $exclusionArray;
			}
		}
		return $exclusion_field_key;
	}




	public function getExclusionReportData()
	{
		if (!isset($this->exclusion_data)) {
			// create data object
			$exclusion_data = new \stdClass();
			$exclusion_data->rows = [];

			$exclusionCount = array();

			$data = $this->getExclusionData();
			$exclusionSetting = $this->getProjectSettingExclusion();
			// $test = $this->getFieldLabel($keys);
			foreach ($exclusionSetting as $i => $field_name) {
				$exclusionCount[$i] = 0;
			}

			foreach ($data as $record) {
				//change to array to compare
				$obj = get_object_vars($record);
				//looping through exclusion settings
				foreach ($exclusionSetting as $key => $value) {
					//if data equal 1 get all items and increment 
					if ($obj[$key] === '1') {
						$exclusionCount[$key]++;
					}
				}
			}


			foreach ($exclusionSetting as $field_name => $value) {
				//getting number after exclusion_
				$extract_exclusion_number = explode('exclusion_', $field_name);
				//getting that number ex 1, 2, 3
				$field_number = array_pop($extract_exclusion_number);
				//getting number after appendix_
				$extract_appendix_number = explode('appendix_', $field_number);
				//get that number ex 1_1, 1_2, 1_3
				$field_number = array_pop($extract_appendix_number);
				$exclusion_data->rows[] = [
					"#$field_number",
					$this->getFieldLabel($field_name),
					$exclusionCount[$field_name]
				];
			}

			$this->exclusion_data = $exclusion_data;
		}
		return $this->exclusion_data;
	}



			/* Style Functions */

	public function getCustomColors()
	{
		$color_settings = $this->getSubSettings('custom_accent_colors');


		foreach ($color_settings as $i => $customColor) {
			$color = new \stdClass();


			$color->site_name = $customColor['site_name'];
			$color->custom_logout = $customColor['custom_logout'];

			$color->header = $customColor['custom_header_color'];
			$color->bar = $customColor['custom_bar_color'];
			$color->secondaryBar = $customColor['custom_secondary_bar_color'];
			$color->text = $customColor['custom_text_color'];
			if ($color->text == "dark") {
				$color->text = "#000000";
			} elseif ($color->text == "light") {
				$color->text = "#ffffff";
			}
			$css_hex_color_pattern = "/#([[:xdigit:]]{3}){1,2}\b/";
			$input = "{$color->header}{$color->bar}{$color->secondaryBar}{$color->text}";
			if (!preg_match($css_hex_color_pattern, $input)) {
				// Defaults to these colors
				$color->header = "#eeeeee";
				$color->bar = "#055877";
				$color->secondaryBar = "#138085";
				$color->text = "#ffffff";
			}
			define("CUSTOM_SITE_NAME", $color->site_name);
			define("CUSTOM_LOGOUT", $color->custom_logout);
			define("LOGO_BACKGROUND_COLOR", $color->header);
			define("BAR_BACKGROUND_COLOR", $color->bar);
			define("SECONDARY_BAR_BACKGROUND_COLOR", $color->secondaryBar);
			define("TEXT_COLOR", $color->text);
		}
	}

	public function getCustomLogo()
	{
		$color_settings = $this->getSubSettings('custom_accent_colors');

		$stored_name = [];
		foreach ($color_settings as $i => $customLogo) {
			$logo = new \stdClass();

			//gets doc_id that is stored in redcap_edocs_metadata
			$logo->image = $customLogo['logo_upload'];

			//start query to pull latest doc_id
			$query = $this->createQuery();

			$query->add('
			select *
			from redcap_edocs_metadata 
			WHERE doc_id = ?', $logo->image);

			$result = $query->execute();

			while ($row = $result->fetch_assoc()) {
				//get latest image with base64_encode
				$imageData = base64_encode(file_get_contents(EDOC_PATH . $row['stored_name']));
				//use mime data to get src 
				$src = 'data: ' . $row['mime_type'] . ';base64,' . $imageData;
				//define constant called LOGO that is used on base.twig 
				define("LOGO", $src);
			}
		}
	}



	
}