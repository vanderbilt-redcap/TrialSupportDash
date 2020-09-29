<?php
// available via
// http://localhost/redcap/redcap_v10.2.1/ExternalModules/?prefix=PassItOn&page=test&pid=54
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
echo "<pre>";

$dag = $module->getCurrentUserDAGName();
print_r($dag);

echo "</pre>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>