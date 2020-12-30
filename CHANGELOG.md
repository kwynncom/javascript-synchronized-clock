2020/12/30 1:37am EST

I got rid of the local kwutils.php
That file is now part of https://github.com/kwynncom/kwynn-php-general-utils

I also got rid of references to that file.  I also had to get rid of 
references to /sys/hypervisor/uuid  That is no longer any help in 
determing AWS.

Amazingly the crypto AWS test now works again both on the web and CLI.  Amazing 
that the fix was that simple.

This must be almost exactly a year later.  I was somewhat surprised to remember 
that kwutils.php started with this project.  
