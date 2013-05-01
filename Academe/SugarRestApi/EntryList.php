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

    public $order_by = null;

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

    // The 'link name fields' - an array of arrays of field names.
    public $link_name_fields = array();

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

    public function setOrderBy($order_by)
    {
        $this->order_by = $order_by;
        $this->clearResults();
        return $this;
    }

    // Set the list of relationships and the fields we want to get from the entry
    // at the end of each relationship.

    public function setLinkFields($link_name_fields)
    {
        $this->link_name_fields = $link_name_fields;
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

            // Throw in the relationships too.
            $result[$entry->id]['_relationships'] = $entry->getRelationshipFields();
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

        $entry_list = $this->api->getEntryList(
            $this->module,
            $this->query,
            $this->order_by,
            $this->offset,
            $this->fieldlist,
            $this->link_name_fields,
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

        // If there are relationship fields retrieved, then parse those first, as we
        // will be moving them to the individual entries as they are processed.
        // The structure of this goes pretty deep. For each entry retrieved there will
        // be an element in the relationship_list:
        //  []['link_list'][]['name'=>'relationship_name','records'=>['link_value'=>name/value-list-of-fields] ]

        $linked_data = $this->api->parseRelationshipList($entry_list);

        // Take each entry returned, and turn them into a separate entry class.
        if (!empty($entry_list['entry_list'])) {
            // Keep a count of the entries, as it is the only way to match them up to 
            // the relationship records.
            $entry_count = 0;

            foreach ($entry_list['entry_list'] as $entry) {
                $Entry = new $this->entry_classname($entry, $this->api);
                $Entry->setModule($this->module);

                // If there is relationshnip data for this entry, then add it to the object.
                if (!empty($linked_data[$entry_count])) {
                    $Entry->setRelationshipFields($linked_data[$entry_count]);
                }

                $this->entry_list[$Entry->id] = $Entry;

                $entry_count++;
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
