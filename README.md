# SugarRestApi.php

A simple library, using the resty/resty REST library (others can be used if desired) to
handle the API for SugarCRM.

This is work-in-progress, with a long list of TODOs.

## TODOs

* Many more methods to support.
* Example to show handling of persistence of session and user IDs needed (memcached or APC, 
perhaps) to avoid logging afresh for each page request.
* Exception handling. Like any APIs, there are many faults at many levels, from errors 
raised by the API, to HTTP faults and the SugarCRM server going missing.
* Some example code and tests (it is very easy to use).


