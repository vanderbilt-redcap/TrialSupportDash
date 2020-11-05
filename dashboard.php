<?php

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