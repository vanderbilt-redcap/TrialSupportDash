<?php

define("SCREENING_LOG_DATA_AJAX_URL", $module->getUrl('ajax/getScreeningLogData.php'));
$loader = new \Twig\Loader\FilesystemLoader(__DIR__."/templates");
$twig = new \Twig\Environment($loader);

$template = $twig->load("dashboard.twig");

/** @var $module \Vanderbilt\PassItOn\PassItOn */
$allSitesData = $module->getAllSitesData();
$mySitesData = $module->getMySiteData();
$authorized = $module->user->authorized;

// prepare site names for Screening Log Report dropdown
$site_names = [];
foreach ($module->dags as $dag) {
	$site_names[] = $dag->display;
}

$screeningLogData = $module->getScreeningLogData();
// echo "<pre>" . json_encode($screeningLogData->rows, JSON_PRETTY_PRINT) . "</pre>";
// $exclusionData = $module->getExclusionReportData();
// $screenFailData = $module->getScreenFailData();

echo $template->render([
	"allSites" => $allSitesData,
	"mySite" => $mySitesData,
	"authorized" => $authorized,
	"site_names" => $site_names,
	"screeningLog" => $screeningLogData
	// "exclusion" => $exclusionData,
	// "screenFail" => $screenFailData,
]);