<?php

$tfn = 'kwutils.php';
if (file_exists($tfn)) require_once('kwutils.php');
else require_once('/opt/kwynn/' . $tfn); unset($tfn);

class isAWSCmds { // see hu(man) message just below, and see
    // https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/instance-identity-documents.html
    
    const huMsg   = <<<CHMONE
  If the tests results are non-blank, these are indicators that the server is on Amazon Web Services.  This helps you further assess chrony accuracy.
CHMONE;
     
    const upfx    = 'http://169.254.169.254/latest/';
    const upfxmd  = self::upfx . 'meta-data/';
    const upfxcr  = self::upfx . '/dynamic/instance-identity/';
    const awspkf  = 'AWSPubKey_2020_01_1.txt';
    const razre   = '/^([a-z]{2}-[a-z]{4,30}-\d)[a-z]$/';
    const amire   = '/^(ami-)[0-9a-f]{8}/';
    const iddocnm = 'document';
    
    public static function gsc($cin, $pin = self::upfxmd) { return $pin . $cin;   }
    
    public static function gen($cmd, $regex, $tin = '') {

	try {
	    if ($tin) $txt = $tin;
	    else $txt = file_get_contents($cmd);
	    preg_match( $regex , $txt, $matches);
	    kwas(isset($matches[1]));
	    
	    if (self::testStatus() === 'showPrivate') $ri = 0;
	    else $ri = 1;
	    
	    return $matches[$ri];
	} catch (Exception $ex) { } return '';		
    }
        
    private static function doTests1() {
	
	if (!isAWS() && !self::testStatus()) return [];
	
	$iam = '  "InstanceProfileArn" : "arn:aws:iam::123456789012:instance-profile/abcd-ef"';

	$a = [
	    ['/sys/hypervisor/uuid' , '/^(ec2)[a-f0-9-]{33}/', 'ec2223bc-f551-9e51-1d63-123456789abc'],
	    [isAWSCmds::gsc('iam/info'), '/  "InstanceProfileArn" : "(arn:aws:iam::)\d{12}/', $iam],
	    /* [isAWSCmds::gsc('ami-id')  , self::amire, 'ami-12345678'], // redundant, but I leave them for reference
	    [isAWSCmds::gsc('placement/availability-zone')  , self::razre, 'us-east-1x'] */
	];

	$r = [];


	foreach($a as $i) {
	    if (!isKwDev() || isAWS() || !self::testStatus()) $i[2] = '';
	    $t['cmd'] = '$ ' . $i[0];
	    $t['regex'] = $i[1];
	    $t['result'] = isAWSCmds::gen($i[0], $i[1], $i[2]);
	    $r[] = $t;
	}

	return $r;
}

public static function doTests() {
    
    $tn = 'isAWSCmds';
    $fs[] = [$tn, 'doTests1'];
    $fs[] = [$tn, 'crypto'];
    
    foreach($fs as $i=>$fv) $ra[] = $fs[$i]();
    
    $r2['isAWSTests'] = $ra;

    $r2['isAWSReadme'] = trim(self::huMsg);
    return $r2;    
}

private static function setTestA(&$a, $f, $re, $v) {
    if (!$re) return;
    kwas(preg_match($re, $v, $matches),"AWS $f field fails kwas 3");
    $r = [];
    $r['field']  = $f;
    $r['regex'] = $re;
    $r['result'] = 'passed regex';
    if (isset($matches[1])) $r['result'] = $matches[1];
    $a[] = $r;

    $x = 2;
}

public static function awsIDDoc() {
    
    if (!isAWS() && time() > strtotime('2020-01-17 03:00') && PHP_SAPI !== 'cli') return '';
    
    $lp = '/tmp/aid/awsIDDoc.txt';
    if (!isAWS() && file_exists($lp)) $txt = file_get_contents($lp); 
    else $txt = file_get_contents(self::upfxcr . self::iddocnm);
    
    $ra = json_decode($txt, 1);
    
    $iddts = [];
    
    $cks = ['accountId'		=> '/^\d{12}/',
	    'availabilityZone'	=> self::razre,
	    'imageId'           => self::amire,
	    'instanceId'        => '/^(i-)[a-z0-9]{8}/',
	    'pendingTime'       => '',
	    'instanceType'	=> '/^[a-z0-9]{2,10}\.[a-z0-9]{1,10}$/'];
    foreach($cks as $key => $re) {
	kwas(isset($ra[$key]), "AWS $key field fails kwas 1");
	$s = $ra[$key];
	kwas(is_string($s) && strlen($s) >= 5, "AWS $key field fails kwas 2");
	self::setTestA($iddts, $key, $re, $s);
	
    }
    
    $s = $ra['pendingTime'];
    $ts = strtotime($s);
    
    kwas($ts && $ts > 1106013719 /* 2005 */ && $ts < time() + 96000,'AWS pendingTime / time of last (re)start fails constraints');
    
    return ['txt' => $txt, 'rawarr' => $ra, 'publicRes' => $iddts];
}

public static function crypto() {
    
    exec('mktemp -d', $tda, $out); kwas($out === 0 && isset($tda[0]), 'mktemp failed');
    $td = $tda[0] . '/'; kwas(is_string($td) && trim(strlen($td)) > 12, 'mktemp string failed');
    
    $fs = [ 
	[__DIR__ . '/', self::awspkf],
	[self::upfxcr, 'pkcs7',  "-----BEGIN PKCS7-----\n", "\n-----END PKCS7-----\n"],
	// [self::upfxcr, 'document'] // redundant
    ];

    foreach($fs as $f) {
	if ($f[0] === self::upfxcr && !isAWS()) continue;
	$txt = file_get_contents($f[0] . $f[1]); kwas($txt, 'crypto file read failed');
	
	if (isset($f[2]) && isset($f[3])) {
	    $txt = $f[2] . $txt . $f[3];
	}
	
	$res = file_put_contents($td . $f[1], $txt); kwas($res, 'crypto file put failed');
	
    }
    
    $docr = self::awsIDDoc();
    
    if (isset($docr['txt'])) file_put_contents($td . self::iddocnm, $docr['txt']);
    
    
    // openssl smime -verify -in $PKCS7 -inform PEM -content $DOCUMENT -certfile AWSpubkey -noverify > /dev/null
    
    $c  = '';
    $c .= 'openssl smime -verify -in ';
    $c .= $td . $fs[1][1];
    $c .= ' -inform PEM -content ';
    $c .= $td . self::iddocnm;
    $c .= ' -certfile ';
    $c .= self::awspkf;
    $c .= ' -noverify ';
    $c .= ' 2>&1 1> /dev/null';
     
    $re = '/^(Verification successful)\s*$/';    
    $fr['cmd'] = $c;
    $fr['regex'] = $re;   
    $fr['result'] = '';
    
    $fr2 = [];
    
    if (isset($docr['publicRes'])) $fr2['fieldTests'] = $docr['publicRes'];
    $fr2['cryptoCmd'] = $fr;
    
    if (!isAWS())     return ['iddoc' => $fr2];

    $sres = shell_exec($c);
 
    preg_match($re, $sres, $matches);
    kwas(isset($matches[1]), 'AWS crypto match failed');

    $fr2['cryptoCmd']['result'] = $matches[1];
        
    return ['iddoc' => $fr2];
}

public static function testStatus() {
    
    global $argv;
    global $argc;
    
    if (PHP_SAPI !== 'cli') return false;
    if ($argc < 2 || $argv[1] !== 'test') return false;
    if ($argc > 2 && $argv[2] === 'showPrivate') return 'showPrivate';
    return 'test';    
}
}

if (isAWSCmds::testStatus()) {
    $r = isAWSCmds::doTests();
    var_dump($r);
    unset($r);
}
