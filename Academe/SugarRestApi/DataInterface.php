<?php

/**
 * A generic interface shared by all objects that model data on the CRM.
 * This should perhaps be an abstract, then we can include some of the
 * shared functions and properties, especially in relation to the API and
 * the module name, which these data objects all need to function.
 */

namespace Academe\SugarRestApi;

interface DataInterface
{
    public function setApi(\Academe\SugarRestApi\Api\ApiAbstract $api);
    public function setModule($module);
}

