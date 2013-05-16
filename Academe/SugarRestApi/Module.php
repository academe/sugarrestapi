<?php

/**
 * Class to handle and fetch details about a SugarCRM module.
 */

namespace Academe\SugarRestApi;

use Academe\SugarRestApi\ApiInterface as ApiInterface;

class Module extends DataAbstract
{
    // The result of API call getModuleFields(), parsed to remove name/value pair lists.
    public $module_fields = NULL;
    public $link_list = NULL;

    // Get the field details.
    // CHECKME: would it be a benefit to make each field into a class?

    public function getFields()
    {
        // Fetch the details from the CRM.
        $this->fetchFields();

        // It is not going to change, so return the same as last time.
        return $this->module_fields;
    }

    public function getLinkFields()
    {
        // Fetch the details from the CRM.
        $this->fetchFields();

        // It is not going to change, so return the same as last time.
        return $this->link_fields;
    }

    // Get the field list from the CRM.

    protected function fetchFields()
    {
        // If already got these details, no need to continue.
        if (isset($this->module_fields)) return true;

        // Get the module fields from the CRM.
        $data = $this->api->getModuleFields($this->module);

        // If the fetch has worked, we will have an array of fields and links.
        if (is_array($data)) {
            if (isset($data['module_fields'])) {
                $this->module_fields = $data['module_fields'];

                // Sift through the fields and look for options lists, and convert
                // them into key/value arrays.
                foreach($this->module_fields as $field_name => $field_detail) {
                    if (!empty($field_detail['options']) && is_array($field_detail['options'])) {
                        $this->module_fields[$field_name]['options'] =
                            $this->api->nameValueListToKeyValueArray($field_detail['options']);
                    }
                }
            }

            if (isset($data['link_fields'])) {
                $this->link_fields = $data['link_fields'];
            }
        } else {
            // Not got what we expected from tha CRM.
            return false;
        }
    }
}


