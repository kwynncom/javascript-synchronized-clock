// execution begins at bottom
// I wrote a detailed readme file for doc.  I am Kwynn Buess of kwynn.com.

if (typeof byid !== 'function') { function byid(id) { return document.getElementById(id); } }

class kwclsySI { // Kwynn clock sync (or synced clock) safe invoke
constructor() {
    const testClassString = 'class kwclsySITest { doesPublicWork; }';
    const idjs = 'kwClockSynced.js';
    const thisSubPath = 'js/';
	
    try { 
		
	Function(testClassString)();
	
	const jsele = byid(idjs);
	const src = thisSubPath + idjs;
	jsele.src = src;

	jsele.onload = function() {
	    window.onload = function() {
		new kwclsyOntick(); 
	    }
	}   
	
	return;
	
    } catch (err) { console.info('synced clock will not work: ' + err.message); }

    // here we have fallen through to a non-synced clock
    window.onload = function() {  
	const idtcl  = 'kwTheClock';
	const ele = byid(idtcl);    
	if (!ele) {
	    console.info('no element ' + idtcl + ' exists, so nothing to do');
	    return;
	}

	if (ele && ele.dataset && ele.dataset.allow_unsynced != true) {
	    console.info('the allow_unsynced (clock) data / dataset attribute was not set');
	    return;
	}

	kwsynclDisplayTick(new Date().getTime(), ele);
	kwUnscyncedTick(ele);
    }
}
}

new kwclsySI(); // execution begins here
