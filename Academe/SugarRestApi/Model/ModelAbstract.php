<?php

/**
 * Abstract functions and properties shared by all classes modeling data structures on the CRM.
 */

namespace Academe\SugarRestApi\Model;

abstract class ModelAbstract implements ModelInterface{
    //abstract public function getSession();
    // The name of the module, e.g. "Contacts", "Accounts"..
    protected $module = null;

    // The API object.
    // This object is referenced from an API shared by all items.
    protected $api = null;

    // The unique ID for this entity item.
    // This will be set if the entity is retrieved from the database, or is new and
    // has just been saved.

    protected $id = null;

    // Set the module for this generic entry object.
    // The module can only be set once and not changed.

    public function setModule($module)
    {
        if (!isset($this->module)) $this->module = $module;
        return $this;
    }

    public function getModule()
    {
        return $this->module;
    }

    // Set the API reference.

    public function setApi(\Academe\SugarRestApi\Api\ApiAbstract $api)
    {
        $this->api =& $api;
        return $this;
    }

    public function getApi()
    {
        return $this->api;
    }

    public function __construct($api = null, $module = null)
    {
        if (isset($api)) {
            $this->setApi($api);
        }

        if (isset($module)) {
            $this->setModule($module);
        }
    }
}

