<?php

/**
 * Factory for the high-level classes of the API.
 * CHECKME: is this a "provider"? Need to do some reading up.
 */

namespace Academe\SugarRestApi;

class Factory
{
    // The CRM API object (injected).
    public $api = NULL;

    // Template for the API class of a given version.
    public $api_class_template = 'Academe\\SugarRestApi\\Api\\v{version}';

    public $entrylist_classname = '\\Academe\\SugarRestApi\\EntryList';
    public $entry_classname = '\\Academe\\SugarRestApi\\Entry';

    // Inject the API if we have one already.
    public function __construct(\Academe\SugarRestApi\Api\ApiAbstract $api = NULL)
    {
        if (isset($api)) $this->setApi($api);
    }

    // Set the API reference.
    public function setApi(\Academe\SugarRestApi\Api\ApiAbstract $api)
    {
        $this->api = $api;
    }

    // Return a new entry object.
    public function newEntry($module)
    {
        $Entry = new $this->entry_classname();

        // Give it access to this API.
        $Entry->setApi($this->api);

        // Set the module if not already set.
        $Entry->setModule($module);

        return $Entry;
    }

    // Return a new entry list object.
    public function newEntryList($module_name)
    {
        // Create the new EntryList object.
        $EntryList = new $this->entrylist_classname();

        // Tell the object the name of the Entry class to use.
        $EntryList->entry_classname = $this->entry_classname;

        // Give it access to this API.
        $EntryList->setApi($this->api);

        // Set the module if not already set.
        $EntryList->setModule($module_name);

        return $EntryList;
    }

    // Set and return a new API object.
    public function newApi($version = 4, $transport = NULL)
    {
        $api_name = str_replace('{version}', $version, $this->api_class_template);
        $this->api = new $api_name();

        if (isset($transport)) {
            $this->api->setTransport($transport);
        }

        return $this->api;
    }
}
