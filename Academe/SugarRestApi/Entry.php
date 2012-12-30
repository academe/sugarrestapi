<?php

/**
 * A generic entity, i.e. module item.
 * This class represents a single item.
 * To start we will just deal with the underlying data. Making dropdown lists available,
 * and providing relationship data will come next.
 */

namespace Academe\SugarRestApi;

class Entry
{
    // The name of the module, e.g. "Contacts", "Accounts"..
    public $_module = NULL;

    // The API object.
    // This object is referenced from an API shared by all items.
    public $_api;

    // The unique ID for this entity item.
    // This will be set if the entity is retrieved from the database, or is new and
    // has just been saved.
    public $_id;

    // The key=>value data for this entity.
    // This may not be all fields for the entity. That depends on what we
    // requested in the first place.
    // Do we actually want the fields to be properties of this object? If so,
    // the internal properties need to be name-spaced out of the way, e.g. using
    // an underscore prefix, assuming that is *never* used in real field names.
    // Or we can use magic methods to set and get the values, so they look like
    // properties.
    public $_fields;

    // List of fields we want to deal with. Leave null to do all fields.
    public $_fieldList = null;

    // If dirty, then data needs to be written to the database.
    public $_dirty = false;

    // Convert a name_value_list to a a key/value array.
    public function nameValueListToArray($nameValueList)
    {
        $array = array();

        foreach($nameValueList as $field) {
            if (isset($field['name']) && isset($field['value'])) {
                $array[$field['name']] = $field['value'];
            }
        }

        return $array;
    }

    // Set the object to an entry fetched from the CRM.
    // This will overwrite the everything.
    public function setEntry($entry)
    {
        if (empty($entry) || !is_array($entry)) return;

        // If the module has already been set, then the entry data most be for the same module.
        if (isset($this->_module) && $this->_module != $entry['module_name']) {
            // TODO: raise an error.
            return;
        }

        // Get the module name from the entry data, if we don't have it.
        if (!isset($this->_module)) $this->_module = $entry['module_name'];

        // Copy the ID to the ID property.
        $this->_id = (isset($entry['id']) ? $entry['id'] : null);

        // Copy the data - either key/value array or array of name/value pair arrays.
        if (isset($entry['key_value_list'])) {
            $this->_fields = $entry['key_value_list'];
        } elseif (isset($entry['name_value_list'])) {
            $this->_fields = $this->nameValueListToArray($entry['name_value_list']);
        } else {
            $this->_fields = array();
        }

        // Set state of object. It was fetched from the API, so is not dirty yet.
        $this->_dirty = false;
    }

    // Set the API reference.
    // CHECKME: is this reference pulled in correctly?
    public function setApi($api)
    {
        $this->_api =& $api;
    }

    // If creating a record from scratch, then this is done here.
    // This is a record not yet saved to the CRM.
    // Data is a key/value array.
    public function setRecord($module, $fields = array())
    {
        $this->_module = $module;
        $this->_dirty = true;
        $this->_fields = $fields;
    }

    // The constructor can be given data to initialise the entity.
    // This will be most useful when converting multiple retrieved record
    // data into multiple entity objects, e.g. after a search.
    // The data is either in a "name_value_list" set of nested arrays
    // or a "key_value_list" single array. The former will be converted
    // to the latter automatically.
    // Now: pass in the "entry" array from the API.
    public function __construct($entry = array())
    {
        if (!empty($entry) && is_array($entry)) {
            $this->setEntry($entry);
        }
    }

    // Set a field value.
    public function __set($name, $value)
    {
        $this->_fields[$name] = $value;

        // Mark the record as dirty.
        $this->_dirty = true;
    }

    // Get a field value.
    // Return NULL if the field is not set.
    public function __get($name)
    {
        return (isset($this->_fields[$name]) ? $this->_fields[$name] : NULL);
    }

    // Check if a field is set.
    public function __isset($name)
    {
        return isset($this->_fields[$name]);
    }

    // Unset a field.
    // CHECKME: should we remove this field from the fieldlist too? I suspect not.
    public function __unset($name)
    {
        if (isset($this->_fields[$name])) unset($this->_fields[$name]);

        // Mark the record as dirty.
        $this->_dirty = true;
    }

    // Save the entity.
    // Only save if marked as dirty.
    // TODO: maybe have a "force save" option.
    // On a successful save (which is most often, as there is virtually no validation
    // on fields through the API) then the new record will be returned to us.
    // TODO: maybe we only pass the fields that have changed, leaving the rest out?
    public function save()
    {
        // No action needed if the entry is not dirty.
        if (!$this->_dirty) return false;

        // TODO: Raise an error if we don't have a reference to the API object.
        if (!is_object($this->_api)) return false;

        // TODO: find out what to do with this.
        $trackView = false;

        // Do the save.
        $entry = $this->_api->setEntry($this->_module, $this->_fields, $trackView);

        // Save the updated fields in the object and mark as not dirty.
        // TODO: do we want to filter these fields according to fieldList?
        if (!$this->_api->isSuccess()) {
            $this->setEntry($entry);
        }

        return $this->_api->isSuccess();
    }

    // Set the module for this generic entry.
    // The module can only be set once and not changed.
    public function setModule($module)
    {
        if (!isset($this->_module)) $this->_module = $module;
    }

    // Get an entry by ID.
    // The module will already have been set.
    public function get($id)
    {
        // TODO: Raise an error if we don't have a reference to the API object.
        if (!is_object($this->_api)) return false;

        // TODO: find out what to do with this.
        $trackView = false;
        $linkNameFields = array();

        // Fetch the entry.
        $entry = $this->_api->getEntry($this->_module, $id, $this->_fields, $linkNameFields, $trackView);

        if ($this->_api->isSuccess() && !empty($entry['entry_list'])) {
            $this->setEntry(reset($entry['entry_list']));
        }

        return $this;
    }
}
