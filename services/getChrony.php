<?php

require_once('chrony.php');
require_once('isAWS.php');

try {
    header('Content-Type: application/json');
    $rc = chrony_parse::get();
    kwas(isset($rc['status']) && $rc['status'] === 'OK', 'no chrony info');
    $ra = [];
    try {  $ra = isAWSCmds::doTests(); } catch (Exception $exdt) {}
    $r = array_merge($rc, $ra);
        
    if (PHP_SAPI === 'cli') var_dump($r);
    else echo json_encode($r);
    exit(0);

} catch (Exception $e) {}

http_response_code(503);
exit(503);