<?php

namespace Vanderbilt\TrialSupportDash;

require_once(__DIR__ . "/RAAS_NECTAR.php");

class TrialSupportDash extends \Vanderbilt\TrialSupportDash\RAAS_NECTAR
{
	public $record_fields = [
			'record_id',
			'redcap_data_access_group',
			'dag',
			'dag_name',
			'class',
	];

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
			if (isset($this->mappings[$fieldName])) {

				return $this->mappings[$fieldName];
			} else {
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


	public function getEDCData($project_id = false)
	{
		if (!isset($this->edc_data) || !$this->edc_data) {
			if (!$project_id) {
				$project_id = $_GET['pid'];
			}
			$this->getEventIDs();
			$fields = $this->mergeRecordFields();
			$params = [
				'project_id' => $project_id,
				'fields' => 'race_eth'
			];



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
			$test = [];
			// add dag property to each based on its record_id			
			foreach ($edc_data as $record) {
				$removeComma = explode(",", $record->race_eth);
				
				$record->race_eth = $removeComma[0];
			
				foreach ($projectDags as $groupId => $thisDag) {
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


			// iterate over edc_data, collating data into record objects
			foreach ($this->edc_data as $record_event) {

				// print_array($record_event);

			

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

					
				}

				// set non-empty fields
				foreach ($this->mergeRecordFields() as $field) {
					
					if (!empty($record_event->$field)) {

						$labels = $this->getFieldLabelMapping($field);
						if ($labels) {
							$record->$field = $labels[$record_event->$field];
						} else {
							$record->$field = $record_event->$field;
						}

						## Special shortening for certain fields
						if ($field == "sex") {
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


	public function getAllSitesData()
	{
		if ($_GET['TESTING']) {
			return json_decode(file_get_contents(__DIR__ . "/tests/test_data/all_sites_data.json"), true);
		}
		$this->getDAGs();
		$this->getRecords();

		// print_array($this->getDAGs());

		//print_array($this->getDAGs());
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

	public function getMySiteData()
	{
		if ($_GET['TESTING']) {
			return json_decode(file_get_contents(__DIR__ . "/tests/test_data/site_a_data.json"), true);
		}

		$this->getDAGs();
		$this->getUser();
		$this->getRecords();
		$this->authorizeUser();


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
				$row->race = $record->ethnic . ' ' . $record->race_eth;
				$row->enrolled = $record->drug_receive_pro;
				//$row->race_eth = $record->race_eth;
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
