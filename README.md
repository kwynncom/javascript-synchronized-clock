# javascript-synchronized-clock
A very accurate clock for display on web pages.

https://kwynn.com/t/9/12/sync/ - live version

It's JavaScript on the front end and PHP on the back end.  The JS polls the 
server (PHP) for time.  

There is a much more detailed readme.html in the repo root.

2020/12 recap - This does not work in Firefox mobile or perhaps any Safari.
It works with Chrome mobile.

I made a mistake in creating class variables that I didn't need.  I just 
want object variables, not static class variables.  Perhaps one day I'll fix this.

Several major mobile browsers do not support class variables.
