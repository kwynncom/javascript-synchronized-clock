if (typeof byid !== 'function') { function byid(id) { return document.getElementById(id); } }

var KWCLSY_TESTMODE = 0;

class kwclsyOntick { // execution in this file starts here - this is the controller class
    
    doticko = 0;    // dotick object
    synccnt = 15;   // sync count (number of time polls to make to the time server)
    theoff = false; // the time offset or the correction or error between client and server
    dispo;	    // display / output object
    networko;	    // network object -- actually does the poll
    icorro;	    // internal error object -- accounts for slower cell phones getting behind the clock tick
     
    constructor() {
	this.dispo = new kwclsyDetailedDisplay();
	
	this.dispo.initTick();
	
	this.icorro = new kwclsyInternalCorrectionO();	     // internal error tracking object
	this.dispo.setCBO(this);		     // give the display this callback object for resync purposes
	this.doticko = new kwclsyDoTickC(this); // the clock tick itself object / class
	this.networko = new kwclsyNet(this.synccnt, this, this.dispo); // object that does polling
	this.networko.doSync(); // start polling the server
    }
    
    resync(reset)    { this.networko.doSync(reset); }
    doOnSync(offset) { this.theoff = offset; }
    
    // corrected time, local (uncorrected) time, from-tick internal error.  I cycle the internal error around because the next correction is based on 
    // the previous correction
    doOnTick(corrTime, localTime, ticorr) {
	let   ats  = this.dispo.ditick(corrTime, localTime, ticorr, this.theoff); // display a tick; ats is after-display timestamp
	// ats accounts for processing between the calculated clock tick and actual display, or internal correction (icorr)
	
	const next = this.doticko.doTick(this.theoff, this.icorro.get());  // calc next tick, which invokes this function again
	this.dispo.prep(next); // cache the next tick's string / output
	
	if (KWCLSY_TESTMODE) ats = this.dispo.getATS();
	
	this.icorro.put(corrTime, ats, ticorr, this.theoff);
    }
}

class kwclsyDoTickC { // calculates and does the next tick
    pinterval = 1000; // permanent interval or "tick" period; 1000 ms = 1s.  Note that JS does time natively in ms
    mini = 250;       // under this calculated interval, skip to the next second
    onto;             // on tick object

    constructor(o) { 
	if (!o) return; // I also invoke this from the disp object just to get the interval
	this.onto = o;	 
	this.doTick(false, 0); // do first tick with no sync data yet
    }
    
    // calculates and executes the setTimeout interval until the "top of the second" -- n seconds and 0 ms
    // on slower phones, the difference between the "tick" and the display of the tick is long enough to correct for an internal error
    doTick(ecorr, icorr) {  // external and internal correction
	
	const lnow = new Date().getTime(); // local now / time
	let cnow = false;                  // corrected time
        if (ecorr !== false) cnow = lnow + ecorr; 
	const permi = this.pinterval; // This makes the following a bit more readable
	let cmpnow = lnow;            // comparistaton "now" -- which time to use for tick
	if (cnow) cmpnow = cnow;
	let dint = permi - (cmpnow % permi); // the displayed interval
	if (dint < this.mini) dint += permi;
	
	let aint = dint - icorr;	    // fire the next tick corrected for internal delay / error

	let cnowDi = false;		    // corrected time to be displayed
	if (cnow !== false) cnowDi = cnow + dint; // this is what will be displayed after the tick executes--the next second
	
	const self = this;
	setTimeout(function() {	
	    // similarly the next local (uncorrected) time is lnow + aint
	    self.onto.doOnTick(cnowDi, lnow + aint, icorr); 
	}, aint);
	
	return cnowDi; // as a matter of cacheing, return the next second to be displayed
    }
    
    getInterval() { return this.pinterval; }
}

class kwclsyNet { // does the network operations -- polling and gets chrony info
    
    disp = false; // display object
    estato;	  // external (server) statistics object
    sycmax;	  // number of polls to do
    cbo;	  // callback object (the "controller")

    syc = 0;         // sync count - polls done
    sysetc = 0;      // number of sets of syc done
    
    constructor(sycin, cboin, disp) {
	this.disp = disp;
	this.sycmax = sycin;
	this.cbo = cboin;
	this.estato =  new kwclsyStats();
    }
    
    doSync(reset) {
	this.getChronyInfo(reset);
	this.poll();
    }
    
    getChronyInfo(reset) {
	
	if (reset) this.doReset();
	
	const xhr = new XMLHttpRequest(); 
	xhr.open('GET', 'services/getChrony.php');
	let self = this;
	xhr.onreadystatechange = function() {
	    if (this.readyState !== 4) return;
	    let chronyInfo = false;
	    if (this.status === 200) chronyInfo = JSON.parse(xhr.response);
	    self.disp.showChronyInfo(chronyInfo); // display chrony info
	}
	
	xhr.send();
    }
    
    poll() {
	const xhr = new XMLHttpRequest(); 
	xhr.open('GET', 'services/getTimeSimple.php');
	const self = this;
	xhr.onreadystatechange = function() {
	    if (this.readyState !== 4 || this.status !== 200) return;
	    const tsa = new Date().getTime(); // timestamp after receipt
	    const tss = self.srvTimeToCliTime(xhr.response); // timestamp server
	    const thecorr = ((tss - tsb) + (tss - tsa )) / 2; // a version of the SNTP offset calculation
	    self.estato.put(thecorr, tss);
	    self.disp.showStatInfo(self.estato.getAll()); // display stats
	    
	    // keep polling until you hit the set max number of polls
	    if (++self.syc < (self.sycmax * (self.sysetc + 1)) ) self.poll();
	    else {
		self.sysetc++;
		self.cbo.doOnSync(self.estato.getOff(), self.disp); // now we're ready to display a corrected clock
	    }
	}
	const tsb = new Date().getTime(); // timestamp before poll
	xhr.send();
    }

    doReset() {
	this.syc = this.sysetc = 0;
	this.estato.reset();
    }
    
    srvTimeToCliTime(st) { // server emits 1579551951.9446 s which becomes 1579551951945 ms
	const  ct = Math.round(st * 1000);
	return ct;
    }    
}

class kwclsyStats {
    
    aaa = []; // average array.  I made it aaa for distinctiveness
    aai = 0;  // iterator
    theavg = 0; // the average offset
    sd = 9999;  // standard deviation, start with something "absurd."
    latestTS = 0; // timestamp keeping track of how old the info is

    reset() {
	this.aai = 0;
	this.aaa = [];
	this.theavg = 0;
	this.sd = 9999;
	this.latestTS = 0;	
    }    

    put(xi, ts, maxi) { // x subscript i for the standard dev calc

	this.latestTS = ts;
	
	const aai = this.aai++;
	if (maxi) this.aaa[aai % maxi] = xi; // rotate the "buffer" / limit how much we're keeping
	else      this.aaa[aai]        = xi; 
	let sum = 0;
	
	let aaal = this.aaa.length;
	for (let i=0; i < aaal; i++) sum += this.aaa[i];
	const avg = sum / aaal;
	this.theavg = avg;
	let sdsum = 0;
	for (let i=0; i < aaal; i++) sdsum += Math.pow(this.aaa[i] - avg, 2);
	
	if (this.aai >= 2) this.sd = Math.sqrt(sdsum /  (aaal - 1));
	else               this.sd = avg;

    }
    getOff()  { return Math.round(this.theavg); }
    getAll()  {
	const rob = {}; // return object
	rob.i   = this.aai;
	rob.avg = this.theavg;
	rob.sd  = this.sd;
	rob.ls  = this.latestTS;
	return rob;
    }
}

class kwclsyInternalCorrectionO {

    istato;    // internal statistics
    icorr = 0;
    
    constructor() { this.istato = new kwclsyStats(); }
    
    put(ct, af, pcorr, ecorr) { // corrected time, after-display time, previous correction, external (network) correction
	
	if (!af) return; // I believe it's only in test mode that this would be unset
	
	const max = 75;  // In very early versions I had bad experiences with trying to over-correct
	
	let newc = pcorr + ((af + ecorr ) - ct); // new correction
	if (newc < 0) newc = 0;
	if (newc > max) newc = max;
	
	this.istato.put(newc, af, 30); // limit to 30 data points; otherwise the correction would take too long
	this.icorr = this.istato.getOff();
    }
    
    get () { return this.icorr;  }
}

class kwclsyDetailedDisplay {

    ci1     = byid('ci1');
    avg     = byid('nativeError');
    sdd     = byid('sdd');
    spolls  = byid('spings');
    corrCl  = byid('kwTheClock');
    natCl   = byid('nativeClock');
    adCl    = byid('adClock');
    ciate   = byid('ciat');
    lsate   = byid('lsat');
    resybe  = byid('resyb');
    resybre = byid('resybr');
    accuse  = byid('accus');
    icorre   = byid('icorr');
    iicorre  = byid('iicorr');
    beatpe  = byid('beatPar');
    beates  = [];
    bmax    = 1;
    bdiv;
    lsat;
    ciat;
    status = 0;
    sycbo;
    ats = 0;
    cache = {};
    dipinterval;
    showMS  = false;
    lus = false;
        
    prep(nt) {
	if (!nt) return;
	this.cache[nt] = this.tick2(nt, false, 'corr');
    }
    
    constructor() {
	const ticko = new kwclsyDoTickC();
	this.dipinterval = ticko.getInterval();
	this.setResetHandlers();
	this.setBeats();
	this.prepDi();
    }
    
    setResetHandlers() {
	var self = this;
	const more = byid('resyb' );
	const rest = byid('resybr');
	if (more) more.onclick = function() { self.resync(0); }
	if (rest) rest.onclick = function() { self.resync(1); }
    }
            
    setCBO(o) { this.sycbo = o;  }
        
    showChronyInfo(cin) {
	
	// dif() is defined below -- "display if element exists"
	if (!cin || !cin.status || cin.status !== 'OK') { dif(this.ci1, 'Chrony info not received from server, so time reliability is uknown.'); return; }
	const basic = cin.basic;
	this.ciat = basic.asOfms;
	this.ciacc(basic.stms, basic.stus);
    }
    
    ciacc(ms, us) {
	
	let stdi;
	
	if (Math.abs(ms) < 1) stdi = '< 1';
	else stdi = roundTo(ms, 1);
	
	dif(this.ci1, stdi);
	dif(this.accuse, us);
	
    }
    
    showStatInfo(sin) {
	dif(this.avg, Math.round(sin.avg));
	dif(this.sdd, Math.round(sin.sd ));
	this.lsat = sin.ls;
	dif(this.spolls, sin.i);
    }
    
    getATS() { return this.ats; }
    
    ditick(corts, nowts, icorr, ecorr) {
	if (KWCLSY_TESTMODE) this.ditickt(corts, nowts, icorr, ecorr);
	else return   this.ditickr(corts, nowts, icorr, ecorr);
    }
    
    ditickt(corts, nowts, icorr, ecorr) {

	 if (this.ats) this.ats += this.dipinterval;

	    	const self = this;
	setTimeout(function () {
	   self.ditickr(corts, nowts, icorr, ecorr);    
	}, 10);
    }
    
    doLUS() { return this.corrCl && this.corrCl.dataset && this.corrCl.dataset.local_until_sync != false;  }
    
    ditickr(corts, nowts, icorr, ecorr) {
	    
	let chit = false;
	let ats = false;
	
	if (corts && this.cache[corts]) {
	    this.beat();
	    dif(this.corrCl, this.cache[corts]);
	    ats = new Date().getTime();
	    chit = true;
	}
	
	if (this.status === 0) {
	    if (this.resybe ) this.resybe .style.visibility = 'visible';
	    if (this.resybre) this.resybre.style.visibility = 'visible';
	    this.status = 1;
	}

	if      (corts && !chit)         ats = this.tick2(corts, this.corrCl, 'corr');
	else if (!corts && this.lus)           this.tick2(nowts, this.corrCl);
	
	if (1)           this.tick2(nowts, this.natCl);
	if (ats)         this.tick2(ats + ecorr, this.adCl);
		
	if (corts) {
	    if (this.ciat) 
	    dif(this.ciate, Math.round((corts - this.ciat) / 1000));
	    dif(this.lsate, Math.round((corts - this.lsat) / 1000));
	    dif(this.icorre, icorr);
	}
	
	this.ats = ats;
	
	if (corts && ats) dif(this.iicorre, ats + ecorr - corts);
	
	return ats;
    }
    
    initTick() { 
	if (this.lus) {
	    this.tick2(new Date().getTime(), this.corrCl);  } 
    }
    
    setBeatButtons() {
	const es = document.querySelectorAll('[data-kwclsy=beatChEle]');
	const self = this;
	es.forEach(function(e) {
	    e.onclick = function() { self.beatInp(this.value); }
	});
	
    }
    
    setBeats() {
	
	this.setBeatButtons();
	
	for (let i=0; i < 8; i++) this.beates[i]  = byid('beat' + i);
	this.setBI();
    }
    
    beat(iin) {
	
	if (this.bmax < 1) return;
	
	if (!iin) iin = 0;
	
	if (!this.beats || !this.beats[iin]) return;
	
	this.beates[iin].style.visibility = 'visible';
	const self = this;
	
	setTimeout(function() { 
	    self.beates[iin].style.visibility = 'hidden';
	    if (iin < self.bmax - 1) self.beat(iin + 1);
	}, self.bdiv);
    }
    
    beatInp(newb) {
	if (newb >= 1) {
	    this.beatpe.style.display = 'block';
	} else {
	    this.beatpe.style.display = 'none';
	}
	
	this.bmax = newb;
	
	this.setBI();
    }
    
    setBI() {
	if (this.bmax > 1) this.bdiv = this.dipinterval / this.bmax;
	else		   this.bdiv = 250;	
    }
    
    resync(reset) {
	this.resybe.style.visibility = 'hidden';	
	this.status = 0;
	this.sycbo.resync(reset);
	
    }
    
    prepDi() {
	if (this.corrCl &&
	    this.corrCl.dataset &&
	    this.corrCl.dataset.show_ms == true) this.showMS = true;
            // note that you need the   == true because '0' is considered true as a non-empty string
	    
	this.lus = this.doLUS();
    }
    
    tick2(tsin, ele, type) {
	
	if (ele && type === 'corr') this.beat();
	const dir = kwsynclDisplayTick(tsin, ele, type, this.showMS);
	if (ele && type === 'corr') {
	    const adts = new Date().getTime();
	    return adts;
	}
	else  if (type === 'corr')   return dir;
    }
} 

function roundTo(val, digits) {
    if (!digits) digits = 0;

    const pow = Math.pow(10, digits);
    const mul = val * pow;
    const rnd = Math.round(mul);
    const ret = rnd / pow;

    return ret;
}

if (typeof dif !== 'function') { // display if element exists
    function dif(ele, v) {
	if (!ele || (!ele.innerHTML && ele.innerHTML !== '')) return;
	ele.innerHTML = v;
    }
}
