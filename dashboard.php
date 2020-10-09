<?php

$loader = new \Twig\Loader\FilesystemLoader(__DIR__."/templates");
$twig = new \Twig\Environment($loader);

$template = $twig->load("dashboard.twig");

$renderData = ["total" => ["enrolled" => 150,"transfused" => 100],
	"siteSummary" => [
		["site_name" => "Vanderbilt","enrolled" => 20,"transfused" => 10,"first_enrolled" => "9/1/2020","last_enrolled" => "10/1/2020"]
	]];

echo $template->render($renderData);