# SugarRestApi.php

_**2013-04-25 Please note - these examples are out-of-date as I've restructured the classes somewhat. 
I've tried to implement a factory to return entries and lists of entries, but it's all WIP at the moment. 
For example, I've moved from Resty to Guzzle, but we have a middle transport abstract to standardise 
the transport layer, so other HTTP libraries could be used if required.**_

_**2013-05-10 The factory class is going, and being absorbed into the API class. Some of the functions
in the transport controller are also going into the API. This should make the whole thing a lot easier to
use.**_

Minimal use is now something like this:

    $v4_api = new Academe\SugarRestApi\Api\v4();
    $login_status = $v4_api->setDomain('www.example.com')
        ->login('username', 'password');
        
    // If the login_status is true, then you can call up SugarCRM API methods directory.
    // e.g. Get the ID of your user logged into Sugar.
    $my_sugar_user_id = $v4_api->getUserId();
    
    // Once logged in, you can get the session details as a string:
    $session_json = $v4_api->getSession();
    
    // That can be saved in the user's browsing session, or on an application-wide basis, so that
    // the number of login sessions on sugar can be kept in check.
    // The next page would resuse that session like this:
    
    $login_status = $v4_api
        ->putSession($session_json)
        ->login('username', 'password');
        
    // Or pass $session_jason into constructor when creating v4().
    // If that session already exists and is still valid on the SugarCRM application, then it will be
    // used. If the session is no longer valid, then the user will be automatically logged into a new
    // session, so there is no additional action to perform.

    // Fetch a contact entry. An "entry" in SugarCRM parlance is an entiry or table. It is actually
    // a number of tables joined as a 1:1 relationship, but the API hides that from us.
    
    $contact = $v4_api->newEntry('Contacts')->fetchEntry('d9876655-fe36-f7dd-a085-510a7afc8e75');
    
A simple library, using the Guzzle REST library (others can be used if desired) to
handle the API for SugarCRM.

The SugarCRM API is described as a "rest" API, but leaves a lot to be desired. Nearly all requests
use POST, regardless of what they do, and there is just one entry point for everything, so
the request type - the verb - (PUT, POST, GET etc) does not have any meaning. The API
version is also in the URL rather than in the HTTP header.

This is work-in-progress, with a long list of TODOs. I've developing it in conjunction with some 
new projects, but also trying to learn new design patterns that are frequently used but new to me. 
If you spot a better way to do something here, just shout - feedback much appreciated.

tl;dr: it's a bit tatty around the edges, but should be fully functional because I am using it in
production, and should make working with a SugarCRM API *very* much easier than it otherwise would be, 
and perhaps even *enjoyable*. I'm trying to do things the right way, to make this library as portable
as I can.

Oh, and why would you want to use this library? To integrate a custom web application with a SugarCRM
application, so you can expose what you like as a custom "portal".

## Loading with composer

This, in your main composer.json will load the package into your project via composer:

    {
        "repositories": [
            {
                "type": "vcs",
                "url": "https://github.com/academe/sugarrestapi"
            }
        ],
        "require": {
            "php": ">=5.3.0",
            "academe/sugarrestapi": "dev-master"
        }
    }

Merge those sections into your existing composer.json then issue `php composer.phar update`

## Example Use

This example ignores caching of the SugarCRM session, which can be shared between any number 
of pages and requests once a connection (a login) is done. It also assumes a PSR-0 autoloader 
is installed and set up. Installing this library through composer will do that, or just load 
one of your own.

    // Get accounts whose names start with "Acad". Include the names and IDs of all their contacts.
    
    // Create a factory.
    $version = 4;
    $factory = new \Academe\SugarRestApi\Factory();
    
    // Create a transport connection object and use that to create an API object.
    $transport = new \Academe\SugarRestApi\Transport\ControllerGuzzle('my.sugarcrm.site.domain');
    
    // We can use the many low-level API methods in the API object, but we will only use login in this example.
    $sugar_api = $Factory->newApi($version)->setTransport($transport);
    
    // Now log in to the CRM. Check the result is true.
    $login_status = $sugar_api->login('username', 'password');
    
    // Get the data from SugarCRM.
    $accounts = $factory
        // Return a list of accounts.
        ->newEntryList('Accounts')
        
        // Names must start with "Acad"
        ->setQuery('name LIKE \'Acad%\'')
        
        // Set some fields we want to get back.
        ->setFieldList('id', 'name', 'description')
        
        // We also want contacts for these accounts - name and ID will do.
        ->setLinkFields('accounts_contacts' => array('id', 'first_name', 'last_name'))
        
        // Fetch teh first page of results (up to 20 records).
        // This method populates the EntryList with Entries, one page at a time.
        ->fetchPage();

    // The $accounts will contain matching records, as an EntryList object containing an array of
    // Entry objects.
    
    // Subsequent calls to $accounts->fetchPage() will return subsequent pages of records.
    
    // This will return ALL records fetched so far, as an array of arrays.
    // Any linked linked contacts will be listed in the "_relationships" element.
    $entry_data = $accounts->getFields();
    
    // There is now interator support.
    // Even before the first fetchPage(), you can now set up the EntryList object for accounts,
    // with the appropriate query settings, then jump straight into a loop. The iterator will
    // fetch a page of entries at a time internally, each time it runs out of cached entries.
    // You don't need to wait for it to fetch all records, and you don't need to fetch one
    // record at a time (though you can if you set the page size to 1).
    
    foreach($accounts as $id => $account) {
        echo " Account ID $id is called '" . $account->name . '" ';
    }
    
    // You can start the query again, and it will iterate over the cached entries and not
    // fetch them again. If you change the query details, then the next loop will start
    // afresh with a new set of entries.
    
    // If you know there is only one entry, you can pull it out of the array by itself:
    
    $account = $accounts->firstEntry();
    
    // This is also useful for many-to-one relationships. For example, you can pull out
    // the account name of a contact like this:
    
    $contacts = $factory
        // Return a list of contacts (if which there will be one).
        ->newEntryList('Contacts')
        
        // Get the account name. Note we will use "account" as an alias for the full
        // relationship name.
        ->setLinkFields('accounts_contacts:account' => array('id', 'name'))
        
        // Fetch a contact of a known ID.
        ->fetchEntries(array($contact_id));
        
    // The contact's name.
    echo $contacts->firstEntry()->first_name;
    
    // The contact's account name.
    echo $contacts->firstEntry()->getRelationshipFields('account')->firstEntry()->name;
    
    // The same thing will be possible simpler still (some coding still to be done):
    $account_name = $factory
        ->newEntry('Contacts')
        ->setLinkFields('accounts_contacts:account' => array('id', 'name'))
        ->fetchEntry($contact_id)
        ->getRelationshipFields('account')
        ->firstEntry()
        ->name;


The setQuery() method simply injects SQL diectly into the WHERE clause of the query run on the CRM.
It is not parsed or processed in any way to protect the CRM database. You also need to know the raw
database column and table names to use it. It's nasty, it's very insecure (I've seen mail client 
archiving plugins using it, and getting quoting and escaping totally wrong, resulting in server-side
SQL errors), but it's all we have to put any kind of conditions on the query. Ideally I would like to 
replace this inserted string with a query object of some sort, supporting bind-variables and handling
all that stuff, and also being able to parse the query enough to recognise when there are issues with
string quoting or calling functions in a query.

There is now an Entry class used for a SugarCRM entry - a single record from a module. It can be
used like this:

    ...
    // If we know the ID, this will fetch the entry and return it as an object:
    $contact = $factory->newEntry('Contacts')
        ->setFieldlist('first_name', 'last_name', 'title')
        ->get('11420eb6-ce89-e467-596a-50b7892b8b10');

    // Add a suffix to the title of the contact.
    $Contact->title .= ' [MARKED]';
    
    // Save it back to the CRM.
    $Contact->save();
    
or if an entry has been fetched from another API, as a list for example:
    
    // Get the first 10 contacts.
    $list = $sugarApi->getEntryList('Contacts', '', '', 0, array(), '', 10, false, false);
    $entries = array();
    // Put the contacts into the entries array as Entry objects.
    foreach($list['entry_list'] as $entry) {
        $entries[] = $sugarApi->newEntry('Contacts')
            ->setEntry($entry);
    }

Each entry can be modified and saved back to the CRM as required. All the entries reference the
API object that created them. This should save some memory, ensure sessions are opened and closed
in one shared place, and I/O and errors will be recorded in one place (so be aware each action on
an entry that uses the API will overwrite the shared API object).

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
* Create some objects for the resources, i.e. contacts, accounts, etc. instead of just dealing 
with big lumps of array data. This could lead on to persistent objects; fetch a contact object, 
update it, ask it to save itself. A good start will be classes for generic entities (modules) 
and relationships. These can then be extended with more specific classes if needed. A factory 
method could handle that so you only need to know the module name and not the name of the 
entity class you get back. [The Entry and EntryList objects make a good start on that.]
* Add PSR3 compatible logging.
* Some cacheing would be good. Cacheing of the CRM version and capabilities, inluding the 
fields and structure of the modules and relationships.
* Take a better look at how the provider classes are injected into the Entry and EntryList 
objects. There are some good examples here: 
https://github.com/cartalyst/sentry/blob/master/src/Cartalyst/Sentry/Sentry.php
* Allow EntryList to operate as an iterator, so you can loop over Entries that is has 
downloaded, but ALSO have it auto-fetch pages of new Entries as necessary.
* We may need to write a SugarCRM module that handles user authentication for CRM contacts. 
For a "self-service portal" contacts need to be authenitcated. This would require putting 
authentication details against a contact (username, encrypted password, portal access enabled for 
contact etc) and exposing it through the REST API (e.g. to set/reset password, authenticate a user).
* A refactor is needed to get the method names more consistent. Some methods return objects and 
some return arrays of data. Both are useful, but do need to be more clearly separated.

