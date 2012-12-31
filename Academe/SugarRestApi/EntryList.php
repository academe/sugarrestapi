<?php

/**
 * A list of entries.
 * Supports various queries, and pagination.
 *
 * @todo method save() - save all entries.
 */

namespace Academe\SugarRestApi;

class EntryList
{
    // The query we are running.
    public $query = '';

    // The module name
    public $module = '';

    // The current offset.
    public $offset = 0;

    // The page size.
    // TODO: we should be able to get the default page size for
    // the module from the CRM.
    public $pageSize = 20;

    // The CRM API object (injected).
    public $api;

    // The current list of entries.
    public $entryList = array();

    // Set the module for this generic entry object.
    // The module can only be set once and not changed.
    public function setModule($module)
    {
        if (!isset($this->_module)) $this->_module = $module;
    }

    // Set the API reference.
    // CHECKME: is this reference pulled in correctly?
    public function setApi($api)
    {
        $this->api =& $api;
    }
}
