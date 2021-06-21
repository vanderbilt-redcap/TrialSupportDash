<?php

$site_name = trim($_POST['site_name']);
if ($site_name == 'Choose institution') {
	echo json_encode($module->getScreeningLogData(), JSON_UNESCAPED_SLASHES);
} else {
	// convert from display to unique DAG name
	$module->getDAGs();
	foreach ($module->dags as $dag) {
		if ($dag->display == $site_name) {
			$site_name = $dag->unique;
			break;
		}
	}
	echo json_encode($module->getScreeningLogData($site_name), JSON_UNESCAPED_SLASHES);
}

