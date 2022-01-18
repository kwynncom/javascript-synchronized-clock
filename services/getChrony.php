<?php

require_once('chrony.php');

try {
    header('Content-Type: application/json');
    $rc = chrony_parse::get();
    kwas(isset($rc['status']) && $rc['status'] === 'OK', 'no chrony info');
    $r = $rc;
        
    if (PHP_SAPI === 'cli') var_dump($r);
    else echo json_encode($r);
    exit(0);

} catch (Exception $e) {}

http_response_code(503);
exit(503);