<?php

/**
 * Provides access to the high-level functions of the API.
 */

namespace Academe\SugarRestApi;

class Controller
{
    // The CRM API object (injected).
    public $api = NULL;

    // Set the API reference.
    public function setApi(\Academe\SugarRestApi\Api\ApiAbstract $api)
    {
        $this->api =& $api;
    }

    // Int=ject the API if we have one already.
    public function __construct(\Academe\SugarRestApi\Api\ApiAbstract $api = NULL)
    {
        if (isset($api)) $this->api = $api;
    }

    // Return a new entry object.
    // We need to expand this to more of a factory so that the returned
    // class is relevant to the entity module and not simply generic.
    // $entry is an entry already fetched from the CRM via the API.
    // TODO: move this to an EntryProvider class.
    public function newEntry($module)
    {
        $Entry = new \Academe\SugarRestApi\Entry();

        // Give it access to this API.
        $Entry->setApi($this->api);

        // Set the module if not already set.
        $Entry->setModule($module);

        return $Entry;
    }

    // Return a new entry list object.
    // We need to expand this to more of a factory so that the returned
    // class is relevant to the entity module and not simply generic.
    // $entry is an entry already fetched from the CRM via the API.
    public function newEntryList($module_name)
    {
        $EntryList = new \Academe\SugarRestApi\EntryList();

        // Give it access to this API.
        $EntryList->setApi($this->api);

        // Set the module if not already set.
        $EntryList->setModule($module_name);

        return $EntryList;
    }
}
