<?php

require_once('/opt/kwynn/kwutils.php');

class chrony_parse { // see "$ chronyc tracking" output and other doc at bottom

private static function getInternal() { 
    
    $res = shell_exec('chronyc tracking');
    
    // output will be an array with the left side of output as keys and right side as value
    
    $anl = explode("\n", $res); unset($res);
    $linec = count($anl);
    foreach($anl as $row) {
	$ac = explode(' : ', $row);
	if (!$ac || count($ac) !== 2) continue;
	if (   trim($ac[0]) &&  trim($ac[1]))
	    $a[trim($ac[0])] =  trim($ac[1]);
    }
    
    kwas(count($anl) - 1 === count($a) && count($a) === 13, 'array fail 1');
    
    return $a;
}
    
public static function get() {
    
    try {

    $a = self::getInternal();
    $ret = [];
        
    $r = $a['Reference ID'];
    preg_match('/([0-9A-Z]+) \(([^\)]+)\)/', $r, $matches);
    kwas(isset($matches[2]) && $matches[2] && is_string($matches[2]) && strlen(trim($matches[2])) > 5, 'rID regex fail');
    
	$rid = $matches[2];
    
    $st = $a['System time'];
    
    preg_match('/(^\d+\.\d+) seconds (\w+) of NTP time/', $st, $matches); unset($st); kwas(isset($matches[2]), 'regex fail offset'); 
    
    $s = $matches[1];

    if      ($matches[2] === 'fast') $sign = '+';
    else if ($matches[2] === 'slow') $sign = '-';
    else kwas(0, 'not fast or slow');

    
    $fs = $sign . sprintf('%0.3f', $s * 1000000);
    
    $b = [];
    
    $b['stus'] = $fs;
    $b['stusr'] = round($fs);
    $b['std'] = $fs . '&#181;s';
    
    if ($sign === '-') $mult = -1;
    else               $mult =  1;
    
    $b['stms']   = $s   * 1000;
    $b['asOfms'] =         intval(round(microtime(1) * 1000, 0));
    $b['sts']    = ($mult >= 0 ? '+' : '-') . $s;
    $b['stats']  = time();

    $ret['basic'] = $b;
    $ret['status'] = 'OK';


    $ret['timeServer'] = $rid;

    return $ret;
     
    }
    catch (Exception $ex) { 
	if (PHP_SAPI === 'cli') throw $ex;
	return false; 
    }
}
}
/* Regarding the Amazon (Web Services) Time Sync Service and 169.254.169.123, see
https://aws.amazon.com/blogs/aws/keeping-time-with-amazon-time-sync-service/

$ chronyc tracking
Reference ID    : A9FEA97B (169.254.169.123)
Stratum         : 4
Ref time (UTC)  : Fri Jan 17 03:22:28 2020
System time     : 0.000004590 seconds slow of NTP time
Last offset     : -0.000010681 seconds
RMS offset      : 0.000035949 seconds
Frequency       : 1.564 ppm slow
Residual freq   : -0.002 ppm
Skew            : 0.043 ppm
Root delay      : 0.000533058 seconds
Root dispersion : 0.001077776 seconds
Update interval : 1032.8 seconds
Leap status     : Normal
**************************
Note that I'm using chrony's names as short variable names above, such as rid for Reference ID and st for system time.

https://chrony.tuxfamily.org/

  */