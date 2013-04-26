<?php

/**
 * Factory for the high-level classes of the API.
 */

namespace Academe\SugarRestApi;

class Factory
{
    // The CRM API object (injected).
    public $api = NULL;

    // Inject the API if we have one already.
    public function __construct(\Academe\SugarRestApi\Api\ApiAbstract $api = NULL)
    {
        if (isset($api)) $this->setApi($api);
    }

    // Set the API reference.
    public function setApi(\Academe\SugarRestApi\Api\ApiAbstract $api)
    {
        $this->api =& $api;
    }

    // Return a new entry object.
    public function newEntry($module)
    {
        $Entry = $this->buildEntry();

        // Give it access to this API.
        $Entry->setApi($this->api);

        // Set the module if not already set.
        $Entry->setModule($module);

        return $Entry;
    }

    public function buildEntry()
    {
        return new \Academe\SugarRestApi\Entry();
    }

    // Return a new entry list object.
    public function newEntryList($module_name)
    {
        $EntryList = $this->buildEntryList();

        // Give it access to this API.
        $EntryList->setApi($this->api);

        // Set the module if not already set.
        $EntryList->setModule($module_name);

        return $EntryList;
    }

    public function buildEntryList()
    {
        return new \Academe\SugarRestApi\EntryList();
    }
}
