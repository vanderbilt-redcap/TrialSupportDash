<?php

define("SCREENING_LOG_DATA_AJAX_URL", $module->getUrl('ajax/getScreeningLogData.php'));
$loader = new \Twig\Loader\FilesystemLoader(__DIR__."/templates");
$twig = new \Twig\Environment($loader);

$template = $twig->load("dashboard.twig");

/** @var $module \Vanderbilt\RAAS_NECTAR\RAAS_NECTAR */
$allSitesData = $module->getAllSitesData();
$mySitesData = $module->getMySiteData();
$authorized = $module->user->authorized;

// prepare site names for Screening Log Report dropdown
$site_names = [];
if ($authorized == 3) {
	foreach ($module->dags as $dag) {
		$site_names[] = $dag->display;
	}
} else {
	$site_names[] = $module->user->dag_group_name;
}

$screeningLogData = $module->getScreeningLogData();
$exclusionData = $module->getExclusionReportData();
$screenFailData = $module->getScreenFailData();
$clipboardImageSource = $module->getUrl("images/clipboard.PNG");
$folderImageSource = $module->getUrl("images/folder.png");
$helpfulLinks = $module->getHelpfulLinks();
$helpfulLinkFolders = $module->getHelpfulLinkFolders();


echo $template->render([
	"allSites" => $allSitesData,
	"mySite" => $mySitesData,
	"authorized" => $authorized,
	"site_names" => $site_names,
	"screeningLog" => $screeningLogData,
	"exclusion" => $exclusionData,
	"screenFail" => $screenFailData,
	"helpfulLinks" => $helpfulLinks,
	"helpfulLinkFolders" => $helpfulLinkFolders,
	"clipboardImageSource" => $clipboardImageSource,
	"folderImageSource" => $folderImageSource
]);