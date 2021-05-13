<?php
namespace Vanderbilt\TrialSupportDash;

require_once(__DIR__."/RAAS_NECTAR.php");

class TrialSupportDash extends \Vanderbilt\TrialSupportDash\RAAS_NECTAR
{
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

}