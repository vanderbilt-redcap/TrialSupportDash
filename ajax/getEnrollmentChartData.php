<?php

$site_dag = trim($_POST['site_dag']);
header('Content-Type: application/json');
if (empty($site_dag)) {
	echo json_encode($module->getEnrollmentChartData(), JSON_UNESCAPED_SLASHES);
} else {
	echo json_encode($module->getEnrollmentChartData($site_dag), JSON_UNESCAPED_SLASHES);
}

