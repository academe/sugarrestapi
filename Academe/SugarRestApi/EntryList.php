<?php

/**
 * A list of entries.
 * Supports various queries, and pagination.
 *
 * @todo method save() - save all entries.
 */

namespace Academe\SugarRestApi;

//use Academe\SugarRestApi\Api as Api;

class EntryList
{
    // The query we are running.
    public $query = null;

    // The module name
    public $module = null;

    // The current offset.
    public $offset = 0;

    // The page size.
    // TODO: we should be able to get the default page size for
    // the module from the CRM.
    public $pageSize = 20;

    // The CRM API object (injected).
    public $api;

    // The current list of entries.
    public $entry_list = array();

    // List of fields we are interested in.
    public $fieldlist = array();

    //
    public $entry_classname = '\\Academe\\SugarRestApi\\Entry';

    // Keeping track of a resultset.
    public $result_count = 0;
    public $total_count = 0;

    // Set the module for this generic entry object.
    // The module can only be set once and not changed.
    public function setModule($module)
    {
        // You cannot change the module once it has been set.
        if (!isset($this->module)) $this->module = $module;
    }

    // Set the API reference.
    // CHECKME: is this reference pulled in correctly?
    public function setApi(\Academe\SugarRestApi\Api\ApiAbstract $api)
    {
        $this->api =& $api;
    }

    // Set the fields we will be dealing with.
    // Pass in an array of field names or a parameter list.
    // e.g. ->setFieldlist('first_name', 'last_name')
    // This method will overwrite the complete list.
    public function setFieldlist()
    {
        $args = func_get_args();
        if (func_num_args() == 0) return $this;

        if (func_num_args() == 1 && is_array($args[0])) {
            $this->fieldlist = $args[0];
        } else {
            $this->fieldlist = $args;
        }

        // We must include the id, otherwise we can't update the entry.
        // We will rely on there being an ID for indexing too.
        if (!in_array('id', $this->fieldlist)) $this->fieldlist[] = 'id';

        return $this;
    }

    // Get the current fieldlist.
    public function getFieldlist()
    {
        return $this->fieldlist;
    }

    // Set the query string.
    // The query string can be toally constructed externally and passed in here,
    // but be aware no further validation is performed and SQL injection-type
    // errors can easily happen if variables are not properly escaped.
    // TODO: support bind variables here, perhaps using a syntax similar to PDO.
    // We will need to be able to pass in dates, timestamps, strings, numbers,
    // blobs etc and get them all validated and escaped appropriately.
    // e.g. something like:
    // setQuery("name = :name")->bindParam(':name', $myName, EntryList::PARAM_STR)
    // Or maybe PDO can do all this and supply a string with everything escaped and
    // terminated..?
    public function setQuery($query)
    {
        $this->query = $query;
        $this->clearResults();
        return $this;
    }

    // Clear the results we may have already selected.
    // This is required if the query is changed, so we can start again from the
    // first page.
    // TODO: check if the current retrieved data set is dirty, and if so, save it
    // if we have auto-save turned on.
    public function clearResults()
    {
        $this->offset = 0;
        $this->fieldlist = array();
        $this->result_count = 0;
        $this->total_count = 0;
    }

    // Get the fields and values (an array of entries with an array of fields for each).
    public function getFields()
    {
        $result = array();

        foreach($this->entry_list as $entry) {
            $result[$entry->id] = $entry->getFields();
        }

        return $result;
    }


    // Run the query and fetch records.
    // We will fetch up to a page of entries (records) into $entryList.
    // A counter will be kept so we know how far through the list we are, and each
    // fetchPage() will fetch the next page.
    // Changing any parameters of the query should reset the fetched record list,
    // so we start fetching from the first page again.
    public function fetchPage($count = 0)
    {
        // TODO: we need to check if we have already exhausted the current resultset, and
        // not attempt to fetch another page if we have.

        // TODO: check the query details are sufficient and the API and session is set up.
        // TODO: when we start a new resultset, i.e. when the query parameters are changed
        // and the current results discarded, we need to reset the counters.

        $entry_list = $this->api->getEntryList(
            $this->module,
            $this->query, //$query = NULL, 
            null, //$order = NULL, 
            $this->offset,
            $this->fieldlist,
            array(), //$linkNameFields
            (!empty($count) ? $count : $this->pageSize), // limit
            false, // deleted
            false // favourites
        );

        // Useful elements: result_count, total_count, offset, [entry_list]
        // TODO: check not an error in the API.
        if (is_array($entry_list)) {
            $this->result_count = $entry_list['result_count'];
            $this->total_count = $entry_list['total_count'];
            $this->offset = $entry_list['next_offset'];
        }

        // Take each entry returned, and turn them into a separate entry class.
        if (!empty($entry_list['entry_list'])) {
            foreach ($entry_list['entry_list'] as $entry) {
                // FIXME: this is baound a little tightly. What pattern would allow
                // The EntryList to product a list of Entries, without needing to know
                // what actual Entry class is? Got some homework to do :-)
                $Entry = new $this->entry_classname($entry, $this->api);
                $Entry->setModule($this->module);

                $this->entry_list[$Entry->id] = $Entry;
            }
        }

        return $this;
    }

    // Save any records that have been changed.
    public function save()
    {
        if (!empty($this->entry_list)) {
            // CHECKME: saving an entry must not create a copy of the object to do so, as it is mutating.
            foreach($this->entry_list as $entry) {
                if ($entry->isDirty()) $entry->save();
            }
        }
    }
}
