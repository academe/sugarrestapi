<?php

/**
 * A generic entity, i.e. module item.
 * This class represents a single item.
 * To start we will just deal with the underlying data. Making dropdown lists available,
 * and providing relationship data will come next.
 *
 * @todo Some handy ways to handle timestamps.
 */

namespace Academe\SugarRestApi;

use Academe\SugarRestApi\ApiInterface as ApiInterface;

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
    public $_fieldlist = array();

    // If dirty, then data needs to be written to the database.
    public $_dirty = false;

    // The 'link name fields' - an array of arrays of field names.
    public $link_name_fields = array();

    // Relationship data retrieved from the CRM.
    // This will be an array of EntryLists, each containing Entries for each linked table.
    public $_relationships = array();

    //
    public $x = '\\Academe\\SugarRestApi\\EntryList';

    // Set the list of relationships and the fields we want to get from the entry
    // at the end of each relationship.
    // TODO: support aliases.

    public function setLinkFields($link_name_fields)
    {
        $this->link_name_fields = $link_name_fields;
        return $this;
    }

    // Set the relationship data for the entry.
    // This is a read-only set of data, and is not used to update the entry when it is saved.
    // Here we will be passed data for the relationships. This will be array data that 
    // is sent by the API. We actually want to store those relationships each as an EntryList
    // full of Entries. 
    // FIXME: We are going to need to know the classname for the EntryList in order to create
    // one.

    public function setRelationshipFields($relationships)
    {
        foreach($relationships as $alias => $relationship) {
            // The alias is the relationship name alias (which may be the relationship name
            // if no alias was provided).
            // FIXME: where do we get this class name from?
            $entry_list = new \Academe\SugarRestApi\EntryList($relationship, $this->api);
            $this->_relationships[$alias] = $entry_list;
        }
        //$this->_relationships = $relationships;
    }

    // Return the records for a single relationship, or for all relationships.
    // The relationship name is the name as seen in SugarCRM Studio.

    public function getRelationshipFields($relationship_name = null)
    {
        if (!isset($relationship_name)) {
            return $this->_relationships;
        } else {
            if (isset($this->_relationships[$relationship_name])) {
                return $this->_relationships[$relationship_name];
            } else {
                return array();
            }
        }
    }


    // Convert a name_value_list to a key/value array.
    // At may be worth moving this to the SugarRestApi API class.

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
    // This will overwrite everything, even an unsaved dirty record.

    public function setEntry($entry)
    {
        if (empty($entry) || !is_array($entry)) return;

        // If the module has already been set, then the entry data must be for the same module.
        if (!empty($this->_module) && !empty($entry['module_name']) && $this->_module != $entry['module_name']) {
            // TODO: raise an error.
            return;
        }

        // Get the module name from the entry data, if we don't have it, and it is known.
        // If we are turning a relationship dataset into an Entry List then we will not
        // know the project in that relationship, at least not without further lookups into
        // the CRM structure.
        if (!isset($this->_module) && isset($entry['module_name'])) {
            $this->_module = $entry['module_name'];
        }

        // Copy the ID to the ID property.
        $this->_id = (isset($entry['id']) ? $entry['id'] : null);

        // Copy the data - either key/value array or array of name/value pair arrays.
        // Some APIs return an entry in a name_value_list and other APIs in an entry_list
        // element. We need to be able to handle these inconsistencies.
        if (isset($entry['key_value_list'])) {
            $this->_fields = $entry['key_value_list'];
        } elseif (isset($entry['name_value_list'])) {
            $this->_fields = $this->nameValueListToArray($entry['name_value_list']);
        } elseif (isset($entry['entry_list'])) {
            $this->_fields = $this->nameValueListToArray($entry['entry_list']);
        } else {
            // Final fall-back - we have a key/value set of data.
            $this->_fields = $entry;
        }

        // Set state of object. It was just fetched from the CRM API, so is not dirty yet.
        $this->_dirty = false;

        return $this;
    }

    // Set the API reference.
    // CHECKME: is this reference pulled in correctly?

    public function setApi(\Academe\SugarRestApi\Api\ApiAbstract $api)
    {
        $this->_api =& $api;
        return $this;
    }

    // If creating a record from scratch, then multiple fields can be set here.
    // This is a record not yet saved to the CRM. By default the complete set of
    // fields will be overwritten, but can be merged instead by setting $overrite
    // to false.
    // Data is a key/value array.
    public function setFields($fields = array(), $overwrite = true)
    {
        $this->_dirty = true;

        if ($overwrite) {
            $this->_fields = $fields;
        } else {
            $this->_fields = array_merge($this->_fields, $fields);
        }
    }

    // Set the value for a single field.
    public function setField($name, $value)
    {
        $this->_dirty = true;
        $this->_fields[$name] = $value;

        // Add this field to the fieldlist, if not already set.
        if (!in_array($name, $this->_fieldlist)) $this->_fieldlist[] = $name;
    }

    // Get the fields and values (an array).
    public function getFields()
    {
        // Add in any relationship data if there is any.
        if (!empty($this->_relationships)) {
            return array_merge(
                $this->_fields,
                array('_relationships' => $this->getRelationshipFields())
            );
        } else {
            return $this->_fields;
        }
    }

    // The constructor can be given data to initialise the entity.
    // This will be most useful when converting multiple retrieved record
    // data into multiple entity objects, e.g. after a search.
    // The data is either in a "name_value_list" set of nested arrays
    // or a "key_value_list" single array. The former will be converted
    // to the latter automatically.
    // Now: pass in the "entry" array from the API.
    public function __construct($entry = array(), $api = NULL)
    {
        if (!empty($entry) && is_array($entry)) {
            $this->setEntry($entry);
        }

        if (isset($api)) {
            $this->setApi($api);
        }
    }

    // Set a field value.
    // TODO: record which fields have been updated.
    public function __set($name, $value)
    {
        $this->setField($name, $value);

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

        // TODO: raise an error if we haven't been told what module this is for.
        if (empty($this->_module)) return;

        // TODO: find out what to do with this.
        $trackView = false;

        // Do the save.
        $entry = $this->_api->setEntry($this->_module, $this->_fields, $trackView);

        // Save the updated fields in the object and mark as not dirty.
        if ($this->_api->isSuccess()) {
            // We get back the saved fields that we sent, along with any validation that
            // may have been applied to them.
            $this->setEntry($entry);
        }

        return $this->_api->isSuccess();
    }

    // Set the module for this generic entry object.
    // The module can only be set once and not changed.
    public function setModule($module)
    {
        if (!isset($this->_module)) $this->_module = $module;
        return $this;
    }

    // Fetch an entry from the CRM by ID.
    // The module will already have been set.
    // TODO: also support ->setField('id', '{id}')->get()
    public function fetchEntry($id)
    {
        // TODO: Raise an exception if we don't have a reference to the API object.
        if (!is_object($this->_api)) return false;

        // TODO: find out what to do with this.
        $trackView = false;

        // Fetch the entry.
        $entry = $this->_api->getEntry(
            $this->_module,
            $id,
            $this->_fieldlist,
            $this->link_name_fields,
            $trackView
        );

        // TODO: also get the relationships at this point, pasre
        // them to a nicer structure, then add them using setRelationshipData()

        if ($this->_api->isSuccess() && !empty($entry['entry_list'])) {
            // Parse any relationship data that has been returned.
            $linked_data = $this->api->parseRelationshipList($entry['entry_list']);

            if (!empty($linked_data[0])) {
                $this->setRelationshipFields($linked_data[0]);
            }

            $this->setEntry(reset($entry['entry_list']));
        }

        return $this;
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
            $this->_fieldlist = $args[0];
        } else {
            $this->_fieldlist = $args;
        }

        // We must include the id, otherwise we can't update the entry.
        if (!in_array('id', $this->_fieldlist)) $this->_fieldlist[] = 'id';

        return $this;
    }

    // Get the current fieldlist.
    public function getFieldlist()
    {
        return $this->_fieldlist;
    }

    // Tells us whether this entry is dirty, i.e. needs to be saved
    public function isDirty()
    {
        return $this->_dirty;
    }
}
