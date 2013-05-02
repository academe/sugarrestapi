<?php

/**
 * A list of entries.
 * Supports various queries, and pagination.
 *
 * @todo method save() - save all entries.
 */

namespace Academe\SugarRestApi;

//use Academe\SugarRestApi\Api as Api;

class EntryList implements \Countable, \Iterator
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

    // The class for a single Entry.
    public $entry_classname = '\\Academe\\SugarRestApi\\Entry';

    // Keeping track of a resultset.
    // The number of Entries in the last fetch.
    public $result_count = 0;

    // The total number of entries that match the query.
    public $total_count = 0;

    // Set to true if the current list of entries is completely fetched.
    public $list_complete = false;

    /**
     * Methods to support Countable and Iterator.
     * These support foreach():
     *  rewind(), next(), current(), key(), valid()
     * It would be nice to support other array functions too, such as reset($arr), next($arr),
     * end($arr) etc. but this will do for now.
     * Note we are not handling exceptions in here yet.
     */

    // This is the total count of records, regardless of whether we have fetched them
    // all from the CRM yet.
    public function count()
    {
        // If we have not started fetching from the CRM yet, then we won't have a count, so
        // fetch the first page. This gives us a starting point.
        if (!$this->fetchIsComplete() && $this->getTotalCount() == 0) {
            $this->fetchPage();
        }

        return $this->getTotalCount();
    }

    // Initialise the loop: fetch the first page, if necessary, and reset the array pointer.
    public function rewind()
    {
        // Get at least one page of data to start things off.
        if (!$this->fetchIsComplete() && $this->getTotalCount() == 0) {
            $this->fetchPage();
        }

        // Reset our internal pointer on the result array to the start.
        reset($this->entry_list);
    }

    // Move the pointer on for the array.
    // We don't need to worry about going off the end of the array of so-far-fetched entries
    // at this point, as it is handled by valid().
    public function next()
    {
        next($this->entry_list);
    }

    // The current element is returned.
    public function current() {
        return current($this->entry_list);
    }

    // The key of the current entry is returned.
    // Keys will always be the ID, and we force a fetch of IDs in every entry.
    public function key() {
        return current($this->entry_list)->id;
    }

    // If we have gone past the end of the array, and there are no more entries to
    // fetch from the CRM, then current() will return false.
    public function valid() {
        // If we have reached the end of the array, but the list is not marked as
        // complete, then fetch another page of entries.
        if (current($this->entry_list) === false && !$this->fetchIsComplete()) {
            $this->fetchPage();
        }

        return (current($this->entry_list) !== false);
    }


    // Return iterator
    /*
    public function getIterator() {
        return new ArrayIterator($this->entry_list);
    }
    */

    // Set the number of entries that a page consists of.

    public function setPageSize($page_size)
    {
        if (is_numeric($page_size) && $page_size >= 1) {
            $this->pageSize = $page_size;
        }

        return $this;
    }

    // Set the module for this generic entry object.
    // The module can only be set once and not changed.
    public function setModule($module)
    {
        // You cannot change the module once it has been set.
        if (!isset($this->module)) $this->module = $module;

        return $this;
    }

    // Return the cound of Entries that match the current query.
    // Will only be populated after the first fetchPage()

    public function getTotalCount()
    {
        return $this->total_count;
    }

    // Return the number of Entries fetched in the last fetchPage().
    // It will either be $this->pageSize, or a lower figure.

    public function getResultCount()
    {
        return $this->result_count;
    }

    // Return the number of records that have been fetched from the CRM so far.
    // By continual fetching, this should max out at the getTotalCount() figure, but
    // that does not account for records that are continually being edited in the CRM;
    // while fetching pages, matching entries may have been added or removed, or updated
    // so they no longer match.

    public function getFetchCount()
    {
        return count($this->entry_list);
    }

    // Returns true if the current list is complete.
    public function fetchIsComplete()
    {
        return $this->list_complete;
    }

    // Set the API reference.
    // CHECKME: is this reference pulled in correctly?
    public function setApi(\Academe\SugarRestApi\Api\ApiAbstract $api)
    {
        $this->api = $api;
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
        // Extract any aliases for the relationship names.
        // This are expressed as "relationship-name:alias" pairs.
        foreach($link_name_fields as $key => $value) {
            if (strpos($key, ':') !== false) {
                list($name, $alias) = explode(':', $key, 2);
                $link_name_fields[$name] = $value;
                unset($link_name_fields[$key]);
                $this->api->relationship_aliases[$name] = $alias;
            }
        }

        $this->link_name_fields = $link_name_fields;

        // We probably don't need to clear the results, because we are not
        // changing the query parameters.
        //$this->clearResults();

        return $this;
    }

    // Clear the results we have already selected.
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
        $this->list_complete = false;

        // Clear the data. We may want to save changes before we do.
        $this->entry_list = array();
    }

    // Get the fields and values, i.e. the fetched data.
    // You get an array of entries with an array of fields for each.
    // You can call getFields() for each individual entry object, or pass in
    // the Entry ID here.
    public function getFields($id = null)
    {
        $result = array();

        if (isset($id)) {
            if (isset($this->entry_list[$id])) {
                $result = $this->entry_list[$id]->getFields();

                // Throw in the relationships too.
                $result['_relationships'] = $this->entry_list[$id]->getRelationshipFields();
            }
        } else {
            foreach($this->entry_list as $entry) {
                $result[$entry->id] = $entry->getFields();

                // Throw in the relationships too.
                $result[$entry->id]['_relationships'] = $entry->getRelationshipFields();
            }
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
        // TODO: check the query details are sufficient and the API and session is set up.

        // The limit to the number of entries we are asking for.
        $limit = (!empty($count) ? $count : $this->pageSize);

        $entry_list = $this->api->getEntryList(
            $this->module,
            $this->query,
            $this->order_by,
            $this->offset,
            $this->fieldlist,
            $this->link_name_fields,
            $limit,
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

        // The set is complete if we have been returned fewer than the number of entries
        // that fit in page quantity that was requested.
        if ($this->result_count < $limit) $this->list_complete = true;

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

    // Fetch all rows that match the query.
    // We will do it a page at a time, so set the page size higher if you want to do
    // it in fewer API calls.
    // The limit is a safety-net and is the maximum number of entries to fetch.

    public function fetchAll($limit = null)
    {
        $count = null;

        while (!$this->fetchIsComplete()) {
            if ($limit > 0) {
                if ($this->getFetchCount() >= $limit) return $this;
                if (($limit - $this->getFetchCount()) < $this->pageSize) {
                    // We have less than a page to go before we reach the limit, so 
                    // set the next fetch count a little smaller.
                    $count = $limit - $this->getFetchCount();
                }
            }

            $this->fetchPage($count);
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

    // Return a single Entry object.

    public function getEntry($id)
    {
        if (isset($this->entry_list[$id])) {
            return $this->entry_list[$id];
        } else {
            return null;
        }
    }

    // Remove a single entry from the fetched list.
    // Note: This does not delete an entry from the CRM.
    // Use this to discard the odd entry after fetching, where the subtleties of selection
    // did not allow you to filter the entries properly to start with.

    public function removeEntry($id)
    {
        if (isset($this->entry_list[$id])) {
            unset($this->entry_list[$id]);
            return true;
        } else {
            return false;
        }
    }
}
