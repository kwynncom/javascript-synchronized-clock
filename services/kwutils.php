<?php // Kwynn Buess, 2020/01/16 9:40pm
/* This is a collection of code that is general enough that I use it in a number of projects. */

/* user agent, for when a server will ignore a request without a UA.  I am changing this 2020/01/16.  I'm moving towards releasing this file
 * to GitHub, so I should show myself to be properly open source fanatical. */
function kwua() { 
    return 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:71.0) Gecko/20100101 Firefox/71.0';
    // return 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.157 Safari/537.36'; 
}

/* The major purpose of this (below) is to make warnings and notices an error.  I have found it's best to "die" on warnings and uncaught exceptions. */
function kw_error_handler($errno, $errstr, $errfile, $errline) {
    echo "ERROR: ";
    echo pathinfo($errfile, PATHINFO_FILENAME) . '.';
    echo pathinfo($errfile, PATHINFO_EXTENSION);
    echo ' LINE: ' . $errline . ' - ' . $errstr . ' ' . $errfile;
    exit(37); // an arbitrary number, other than it should be non-zero to indicate failure
}
set_error_handler('kw_error_handler');


set_include_path(get_include_path() . PATH_SEPARATOR . '/opt/composer');
require_once('vendor/autoload.php');  
unset($__composer_autoload_files);
/* I do this unset, above, because often I am trying to keep a very clean set of active variables.  I have used this without harm since very 
roughly the (northern hemisphere) summer of 2019.  */

class kwmoncli extends MongoDB\Client {
    public function __construct() {
	parent::__construct('mongodb://127.0.0.1/', [], ['typeMap' => ['array' => 'array','document' => 'array', 'root' => 'array']]);
    }

    public function selectCollection     ($db, $coll, array $optionsINGORED_see_below = []) {
	return new kwcoll($this->getManager(), $db, $coll, ['typeMap' => ['array' => 'array','document' => 'array', 'root' => 'array']]); 
    }
}

class kwcoll extends MongoDB\Collection {
    public function upsert($q, $set) {
	return $this->updateOne($q, ['$set' => $set], ['upsert' => true]);
    }
}

class dao_generic { // dao = data access object, and a nod to east Asian Daoism.
    
    protected $dbname;
    protected $client;
    
    private   $seqcoll;
    private   $seqName;
    
    public function __construct($dbname) {
	$this->dbname = $dbname;
	$this->client = new kwmoncli();
    }
    
    // The name is your arbitrary name for the sequence, such as 'purchase_orders'
    protected function getSeq($name) { // when you want an ACID / atomic sequence: 1, 2, 3, 4, ....
	$this->seqName = $name;
	$this->seqcoll = $this->client->selectCollection($this->dbname, 'seqs');	
	$this->setSeq();
	$ret = $this->seqcoll->findOneAndUpdate([ '_id' => $this->seqName ], [ '$inc' => [ 'seq' => 1 ]]);
        return $ret['seq'];
    }
    
    private function setSeq() {
	$res = $this->seqcoll->findOne(['_id' => $this->seqName ]);
	if ($res) return;
	$this->seqcoll->insertOne(['_id' => $this->seqName, 'seq' => 1, 'initR' => date('r')]);
    }
}


/* Kwynn's assert.  It's similar to the PHP assert() except it throws an exception rather than dying.  I use this ALL THE TIME.  
  I'm sure there are 100s if not 1,000s of references to this in my code. */
function kwas($data = false, $msg = 'no message sent to kwas()', $var = null) {
    if (!isset($data) || !$data) throw new Exception($msg); 
/* The isset may not be necessary, but I'm not touching anything I've used this much and for this long. */
}

/* make sure any timestamps you're using make sense: make sure you haven't done something weird and such: make sure you don't have zero 
values or haven't rolled over bits; make sure your time isn't way in the future or past. Obviously both min and max are somewhat arbitrary, but 
this has served it's purpose since roughly (northern hemisphere) summer 2019. */
function strtotimeRecent($strorts, $alreadyTS = false) {
    static $min = 1561500882; // June 25, 2019, depending on your tz
    static $max = false;
    
    if (!$alreadyTS) $ts = strtotime($strorts); 
    else	     $ts = $strorts;
    
    kwas($ts && $ts >= $min, 'bad string to timestamp pass 1 = ' . $strorts);
    
    if (!$max) $max = time() + 87000; kwas($ts < $max, 'bad string to timestamp pass 2 = ' . $strorts);

    return $ts;
}

// I've found changing timezones to be oddly difficult, so this works:
function dateTZ($format, $ts, $tz) {
    
    $dateO = new DateTime();
    $dateO->setTimestamp($ts);
    $dateO->setTimezone(new DateTimeZone($tz));
    return $dateO->format($format);
}

// Get XML / HTML DOM object from the HTML.
function getDOMO($html) {
	$ddO = new DOMDocument;
	libxml_use_internal_errors(true); // Otherwise non-valid HTML will throw warnings and such.  
	$ddO->loadHTML($html);
	libxml_clear_errors();	
	return $ddO;
}

/* in case you are trying to indicate whether an HTML page has changed.  This is useful if you're doing a single page application.  You want to 
 * communicate from your server to a client whether a database entry or underlying file has changed.  As of January, 2020, this is not well 
 * tested, but I leave it. Also, I don't remember why I gave a default ts or return if it's my machine.  */
function kwTSHeaders($tsin = 1568685376, $etag = false) { // timestamp in; etag is an HTTP specified concept
    
    if (isKwDev()) return; // defined below
    
    if (!$etag) $etag = $tsin;
    
    $gmt = new DateTimeZone('Etc/GMT+0');
    $serverDTimeO = new DateTime("now", $gmt);
    $serverDTimeO->setTimestamp($tsin);
    $times = $serverDTimeO->format("D, d M Y H:i:s") . " GMT";
    
    header('Last-Modified: ' . $times);
    header('ETag: ' . '"' . $etag . '"');

    if ( 1 &&
    (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
	&& $_SERVER['HTTP_IF_MODIFIED_SINCE'] === $times)
	||
	(isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
	
	    trim($_SERVER['HTTP_IF_NONE_MATCH']) === $md5
		)
	) { 
    http_response_code(304); // 304 equals "not modified since last you checked" as I remember.
    exit(0);
    }  
}

/* Often it's very useful to know if this is my local / own / test computer.  I want to know without revealing my machine name, which 
 * might be security sensitive.  2020/01/16 9:38pm - a brand new version.  Let's hope it works. */
function isKwDev() {
   $path = '/opt/kwynn/';
   $name = 'i_am_kwynn_local_dev_201704_to_2020_01.txt';
   return file_exists($path . $name);
}

function sslOnly() { // make sure the page is SSL
    
    if (isKwDev()) return; // but don't force it if it's my machine and I don't have SSL set up.

    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
	header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
	exit(0);
    }
}

function startSSLSession() { // session as in a type of cookie
    if (session_id()) return;
    sslOnly();
    session_set_cookie_params(163456789); // over 5 years expiration
    session_start();
    $sid = session_id();
    kwas($sid && is_string($sid) && strlen($sid) > 5, 'startSSLSessionFail');
    return $sid;
}

function kwjae($o) { // JSON encode, echo, and exit
    header('application/json');
    echo json_encode($o);
    exit(0);
}

// ID whether you are in the Amazon Web Services cloud
// There are potential problems to the following, but it works for me for now.  
// see https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/identify_ec2_instances.html
// also search Google for how to tell whether you're in a virtual machine
function isAWS() { return file_exists('/sys/hypervisor/uuid'); }

/* For the record, the "old" version as of Jan 16, 2020:
// The following needs to be set up in 2 environments to work:
// The commands below only show that it is set up.  It's not precisely how to do so.
function isKwDev() {
    return getenv('KWYNN_201704_LOCAL') === 'yes';
    // for command line / CLI PHP: $ grep -i kwynn /etc/environment 
    // KWYNN_201704_LOCAL=yes
    // for blah.conf in Apache: 
    // /etc/apache2/sites-enabled$ cat sntp.conf
    // <VirtualHost *:80 sntp>
    // SetEnv KWYNN_201704_LOCAL yes
} */