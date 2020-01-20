if (typeof dif !== 'function') {
    function dif(ele, v) {
	if (!ele || (!ele.innerHTML && ele.innerHTML !== '')) return;
	ele.innerHTML = v;
    }
}

function kwsynclDisplayTick(tsin, ele, type, showMS) { // see credit to Aaron Farrar in readme
   
    const displayDateO = new Date(tsin);
    let    h = displayDateO.getHours(); 
    let    m = displayDateO.getMinutes(); 
    let    s = displayDateO.getSeconds();
    const ms = displayDateO.getMilliseconds();
    if      (h === 0 ) h  = 12;
    else if (h   > 12) h -= 12;
    m = (m < 10) ? '0' + m : m;
    s = (s < 10) ? '0' + s : s;
    let st = h + ':' + m + ':';
    if (type === 'corr') st += '<span class="corrs">';
    st += s;
    if (type === 'corr') st += '</span>';
    if  (showMS)         st += '.' + (ms + '').padStart(3,'0');

    if (ele) 	dif(ele, st);

    return st;
}  

function kwUnscyncedTick(ele) {
    const lnow = new Date().getTime();
    const permi = 1000;
    let dint = permi - (lnow % permi);
    if (dint < 250) dint += 1000;

    setTimeout(function() { 
	kwsynclDisplayTick(lnow + dint, ele);
	kwUnscyncedTick(ele); 
    }, dint);
}