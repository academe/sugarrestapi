<?php

/**
 * A generic entity, i.e. module item.
 * This class represents a single item.
 * To start we will just deal with the underlying data. Making dropdown lists available,
 * and providing relationship data will come next.
 *
 * @todo Some handy ways to handle timestamps.
 */

namespace Academe\SugarRestApi\Model;

use Academe\SugarRestApi\ApiInterface as ApiInterface;

class Entry extends ModelAbstract
{
    // The name of the module, e.g. "Contacts", "Accounts"..
    //protected $module = NULL;

    // The API object.
    // This object is referenced from an API shared by all items.
    //protected $api;

    // The unique ID for this entity item.
    // This will be set if the entity is retrieved from the database, or is new and
    // has just been saved.
    //protected $id;

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

    // true if the Entry exists in the CRM.
    // Note: not yet fully implemented.
    public $_exists = false;

    // Set the list of relationships and the fields we want to get from the entry
    // at the end of each relationship.
    // TODO: support aliases.

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

            $entry_list = new $this->api->entrylist_classname($this->api, $relationship);
            $this->_relationships[$alias] = $entry_list;
        }
        //$this->_relationships = $relationships;
    }

    // Return the records for a single relationship, or for all relationships.
    // The relationship name is the name as seen in SugarCRM Studio.
    // FIXME: each relationship is an EntryList, and so we need to go through
    // each to convert to an array, recursively.

    // ** DEPRECATED **
    // Use getRelationshipAsArray() instead
    public function getRelationshipFields($relationship_name = null)
    {
        return $this->getRelationshipAsArray($relationship_name);
    }

    // Get a single relationship as an array.
    // TODO: name this relationshipToArray() for a little more consistency.
    // Also similar renaming in EntryList would be good.

    public function getRelationshipAsArray($relationship_name = null)
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

    // Get all relationships, as arrays.

    public function getRelationshipsAsArray()
    {
        return $this->getRelationshipAsArray();
    }

    // Get a single relationship EntryList
    public function getRelationshipList($relationship_name = null)
    {
        if (!isset($relationship_name)) {
            return $this->_relationships;
        } else {
            if (isset($this->_relationships[$relationship_name])) {
                return $this->_relationships[$relationship_name];
            } else {
                return NULL;
            }
        }
    }

    // Set the object to an entry fetched from the CRM.
    // This will overwrite everything, even an unsaved dirty record.
    // TODO: deprecate and change to fill().
    // NOTE: don't forget to setEntryExists(true) if filling the object with data
    // that we know has come from the CRM. We cannot make any assumptions about that
    // here, even if the ID is set. This also leaves a question for setting "dirty" -
    // see below.

    public function setEntry($entry)
    {
        if (empty($entry) || !is_array($entry)) return;

        // If the module has already been set, then the entry data must be for the same module.
        if (!empty($this->module) && !empty($entry['module_name']) && $this->module != $entry['module_name']) {
            // TODO: raise an error.
            return;
        }

        // Get the module name from the entry data, if we don't have it, and it is known.
        // If we are turning a relationship dataset into an Entry List then we will not
        // know the project in that relationship, at least not without further lookups into
        // the CRM structure.
        if (!isset($this->module) && isset($entry['module_name'])) {
            $this->module = $entry['module_name'];
        }

        // Copy the ID to the ID property.
        $this->id = (isset($entry['id']) ? $entry['id'] : null);

        // Copy the data - either key/value array or array of name/value pair arrays.
        // Some APIs return an entry in a name_value_list and other APIs in an entry_list
        // element. We need to be able to handle these inconsistencies.
        //
        // FIXME: we can probably just pass entry through $api->nameValuesToKeyValues() and
        // remove all the name/value lists in one swoop.
        //

        //$this->api->nameValuesToKeyValues($entry);

        if (isset($entry['key_value_list'])) {
            $this->_fields = $entry['key_value_list'];
        } elseif (isset($entry['name_value_list'])) {
            $this->_fields = $entry['name_value_list'];
        } elseif (isset($entry['entry_list'])) {
            $this->_fields = $entry['entry_list'];
        } else {
            // Final fall-back - we have a key/value set of data.
            $this->_fields = $entry;
        }

        // Set state of object. It was just fetched from the CRM API, so is not dirty yet.
        // FIXME: can we make this assumption? Could we be filling a brand new Entry with
        // data from some other source?
        $this->_dirty = false;

        return $this;
    }

    // If creating a record from scratch, then multiple fields can be set here.
    // This is a record not yet saved to the CRM. By default the complete set of
    // fields will be overwritten, but can be merged instead by setting $overrite
    // to false.
    // Data is a key/value array.
    public function setFields($fields, $overwrite = true)
    {
        $this->_dirty = true;

        if ($overwrite) {
            $this->_fields = $fields;
        } else {
            $this->_fields = array_merge($this->_fields, $fields);
        }

        return $this;
    }

    // Set the value for a single field.
    public function setField($name, $value)
    {
        $this->_dirty = true;
        $this->_fields[$name] = $value;

        // Add this field to the fieldlist, if not already set.
        if (!in_array($name, $this->_fieldlist)) $this->_fieldlist[] = $name;
    }

    // Update the values of multiple fields.
    // Supply a key=>value array.
    // TODO: maybe change this to "fill()"?

    public function updateFields($fields)
    {
        return $this->setFields($fields, false);
    }

    // Get the fields and values (an array).
    // ** Deprecated **
    // Use getAsArray() instead.
    public function getFields()
    {
        return $this->getAsArray();
    }

    // Get the Entry properties as an array.
    // CHECKME: it would be great if we could catch get_object_vars() and feed it through
    // this method, but I cannot see a way to do that yet.
    // ** DEPRECATED ** use toArray() instead.
    public function getAsArray()
    {
        return $this->toArray();
    }
    public function toArray()
    {
        // Add in any relationship data if there is any.
        if (!empty($this->_relationships)) {
            return array_merge(
                $this->_fields,
                array('_relationships' => $this->getRelationshipAsArray())
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

    public function __construct($api = NULL, $entry = null)
    {
        if (isset($api)) {
            $this->setApi($api);
        }

        if (isset($entry)) {
            $this->setEntry($entry);
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
        if (!is_object($this->api)) return false;

        // TODO: raise an error if we haven't been told what module this is for.
        if (empty($this->module)) return;

        // TODO: find out what to do with this.
        $trackView = false;

        // Do the save to the CRM.
        $entry = $this->api->setEntry($this->module, $this->_fields, $trackView);

        // Save the updated fields in the object and mark as not dirty.
        if ($this->api->isSuccess()) {
            // We get back the saved fields that we sent, along with any validation that
            // may have been applied to them.
            $this->setEntry($entry);

            $this->_exists = true;
        }

        return $this->api->isSuccess();
    }

    // Fetch an entry from the CRM by ID.
    // The module will already have been set.
    // TODO: also support ->setField('id', '{id}')->get()
    public function fetchEntry($id)
    {
        // TODO: Raise an exception if we don't have a reference to the API object.
        if (!is_object($this->api)) return false;

        // TODO: find out what to do with this.
        $trackView = false;

        // Fetch the entry.
        $entry = $this->api->getEntry(
            $this->module,
            $id,
            $this->_fieldlist,
            $this->link_name_fields,
            $trackView
        );

        // Also get the relationships at this point, parse
        // them to a nicer structure, then add them using setRelationshipData()

        if ($this->api->isSuccess() && !empty($entry['entry_list'])) {
            // Parse any relationship data that has been returned.
            if (isset($entry['relationship_list'])) {
                if (!empty($entry['relationship_list'][0])) {
                    $this->setRelationshipFields($entry['relationship_list'][0]);
                }
            }

            // Move the fields to the field list.
            $this->setEntry(reset($entry['entry_list']));

            // Handle a missing or deleted Entry.
            // SugarCRM does not give is any indication that anything is wrong with this entry,
            // apart from the "deleted" flag and a "warning" message.
            if (isset($this->deleted) && $this->deleted == 1 && isset($this->warning)) {
                $this->_exists = false;
            } else {
                // 
                $this->_exists = true;
            }
        }

        return $this;
    }

    // Returns true if the Entry exists on the CRM.
    public function entryExists()
    {
        return $this->_exists;
    }

    // Set flag indication whether the entry exists in the CRM.
    public function setEntryExists($exists = true)
    {
        $this->_exists = $exists;
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
