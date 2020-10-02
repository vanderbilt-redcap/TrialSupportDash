<?php

echo("PassItOn Dashboard");
echo "<br>";
echo "<h4>My Site Metrics</h4>";
echo "<br>";
$data = $module->getMySiteMetricsData();
echo ("PassItOn->getMySiteMetricsData()->site_name : <br><pre>" .print_r($data->site_name, true) . "<br></pre>");
echo ("PassItOn->getMySiteMetricsData()->rows : <br><pre>" .print_r($data->rows, true) . "<br></pre>");
?>