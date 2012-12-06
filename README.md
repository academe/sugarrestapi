# SugarRestApi.php

A simple library, using the resty/resty REST library (others can be used if desired) to
handle the API for SugarCRM.

This is work-in-progress, with a long list of TODOs.

## Example Use

    require 'vendor/autoload.php';
    $jsonData = /persistence data retrieved from the session/
    $sugarApi = new Academe\SugarRestApi\SugarRestApi($jsonData);
    
    $rest = new Resty();
    $sugarApi->setRest($rest);
    
    $sugarApi->entryPoint = 'http://example.com/service/v4/rest.php';
    
    // A login will only be done if the persisted session is no longer
    // valid for any reason, or we are logging in as a different user.
    $sugarApi->login('User Name', 'password');
    
    $ContactsModuleFields = $sugarApi->getModuleFields('Contacts');
    
    $jsonData = $sugarApi->getJsonData();
    // Now store $jsonData in the session so we can pull it in on the next page.

## TODOs

* Many more methods to support.
* Example to show handling of persistence of session and user IDs needed (memcached or APC, 
perhaps) to avoid logging afresh for each page request.
* Exception handling. Like any APIs, there are many faults at many levels, from errors 
raised by the API, to HTTP faults and the SugarCRM server going missing.
* Some example code and tests (it is very easy to use).
* Try out some kind of DI for the "Resty" rest object.
* DI may be useful for persisting the API session details in the local application session.


