<?php

$loader = new \Twig\Loader\FilesystemLoader(__DIR__."/templates");
$twig = new \Twig\Environment($loader);

$template = $twig->load("dashboard.twig");

/** @var $module \Vanderbilt\PassItOn\PassItOn */
$allSitesData = $module->getAllSitesData();
$mySitesData = $module->getMySiteData();
$authorized = $module->user->authorized;

echo $template->render(["allSites" => $allSitesData,"mySite" => $mySitesData, "authorized" => $authorized]);