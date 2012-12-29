# SugarRestApi.php

A simple library, using the resty/resty REST library (others can be used if desired) to
handle the API for SugarCRM.

The SugarCRM is called a "rest" API, although it hardly counts as one. Nearly all requests
use POST, regardless of what they do, and there is just one entry point for everything, so
the request type (PUT, POST, GET etc) does not declare the action that is required. The API
version is in the URL rather than in the HTTP header.

This is work-in-progress, with a long list of TODOs.

## Example Use

    require 'vendor/autoload.php';
    $sessionData = ... // persistence data retrieved from the session
    $sugarApi = new Academe\SugarRestApi\v4($sessionData);
    
    // The REST class may have been restored with the persistence sessionData.
    if (!isset($sugarApi->rest)) {
        $rest = new Resty();
        $sugarApi->setRest($rest);
    }
    
    // In most cases only the domain is needed for the entry point to be constructed.
    $sugarApi->domain = 'example.com';
    
    // The path and protocol can normally be left as default.
    //$sugarApi->path = 'path-to-my-crm';
    //$sugarApi->protocol = 'https';
    
    // A login will only be done if the persisted session is no longer
    // valid for any reason, or we are logging in as a different user.
    // The result will be true or false, indicating you are now, or were
    // already, logged in.
    // or not.
    $loginSuccess = $sugarApi->login('User Name', 'password');
    
    $ContactsModuleFields = $sugarApi->getModuleFields('Contacts');
    
    if (!$sugarApi->isSuccess()) {
        $errorDetails = $sugarApi->error();
    }
    
    $sessionData = $sugarApi->getSession();
    // or
    $sessionData = (string)$sugarApi;
    // Now store $sessionData in the session so we can pull it in on the next page.

## TODOs

* Example to show handling of persistence of session and user IDs needed (memcached or APC, 
perhaps) to avoid logging afresh for each page request. It works, but just needs better examples 
and tests.
* Exception handling. Like any APIs, there are many faults at many levels, from errors 
raised by the API, to HTTP faults and the SugarCRM server going missing. It could be that 
exceptions are not the way to go for this kind of library.
* Some example code and tests (it is very easy to use).
* Try out some kind of DI for the "Resty" rest object.
* DI may be useful for persisting the API session details in the local application session.
* Proper phpdoc comment blocks.
* Move everything in the "classes" directory down a level. It kind of ended up here while 
getting to grips with how composer works, and following general conventions other people follow.
* Create some objects for the resources, i.e. contacts, accounts, etc. instead of just dealing 
with big lumps of array data. This could lead on to persistent objects; fetch a contact object, 
update it, ask it to save itself. A good start will be classes for generic entities (modules) 
and relationships. These can then be extended with more specific classes if needed. A factory 
method could handle that so you only need to know the module name and not the name of the 
entity class you get back.
* Add PSR3 compatible logging.


