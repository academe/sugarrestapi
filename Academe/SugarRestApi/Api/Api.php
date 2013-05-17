<?php

/**
 * @todo A general parameter validation framework to check data before it is passed on.
 * @todo Handle data as recources (e.g. User, Contact, Address, Note) rather than just 
 * as multidimensional arrays that need significant parsing.
 * TODO: move the higher-level stuff out to a series of controllers.
 * TODO: auto re-login if the session on the CRM end gets dropped or times out. We may need
 *      to implement some kind of watcher pattern so that the new connection can be cached
 *      as needed by the framework.
 */

namespace Academe\SugarRestApi\Api;

use Academe\SugarRestApi\Exception\InvalidArgumentException;

class Api extends ApiAbstract
{
    // The rest transport controller.
    // Will be a concrete instance of \Academe\SugarRestApi\Transport\ControllerAbstract
    public $transport = NULL;

    // The name of the class used to provide the REST transport functionality.
    public $transport_class_name = '\\Academe\\SugarRestApi\\Transport\\ControllerGuzzle';

    // Class names for various CRM objects and collections.
    // TODO: namespace these classes by the API version and physically move them.
    public $entrylist_classname = '\\Academe\\SugarRestApi\\Model\\EntryList';
    public $entry_classname = '\\Academe\\SugarRestApi\\Model\\Entry';
    public $module_classname = '\\Academe\\SugarRestApi\\Model\\Module';

    // Details that constructs the web service entry point URL.
    // 'url' is the final URL, constructed from 'template' with any of the remaining
    // field values substituted into it.

    public $transport_url_parts = array(
        'protocol' => 'http',
        'domain' => '',
        'path' => '',
        'version' => '',
        'template' => '{protocol}://{domain}{path}/service/{version}/rest.php',
        'url' => '',
    );

    // The username and password used to log in.
    // To be persisted in the session.
    // TOOD: wrap these up together (all five attributes).
    public $authUsername = '';
    public $authPassword = '';
    public $authVersion = '1';

    // The current Sugar session ID and the user ID this corresponds to.
    // To be persisted in the session.
    public $session_id;
    public $user_id;

    // The name of this application making the requests.
    public $applicationName = 'Academe_SugarRestApi';

    // The input type (format of data sent to the API) and
    // response type (format of data coming back).
    // For this to be useful, the encoding and decoding must all be done
    // through overridable methods.
    public $apiInputType = 'JSON';
    public $apiResponseType = 'JSON';

    // List of relationship names and aliases.
    public $relationship_aliases = array();

    // Details of any error message.
    public $sugarError = array(
        'name' => '',
        'number' => '',
        'description' => '',
    );

    // Automatically log out when we close.
    // If true, an attempt is made to log out before the object is destroyed.
    // If false, the remote API session is kept open and can be used on the next page request.
    public $autologout = false;


    // Set the transport classname.
    // If the transport class is already instantiated as a different classm, then purge
    // the existing transport object.

    public function setTransportClassName($class_name)
    {
        $transport_abstract_name = '\Academe\SugarRestApi\Transport\ControllerAbstract';

        // Must exist as a class.
        if (!class_exists($class_name)) {
            // Fatal exception.
            throw new InvalidArgumentException("Class '{$class_name}' does not exist");
        }

        // Class moust be a concrete class of the correct abstract.
        //$ref = new \ReflectionClass($class_name);
        if ( ! is_subclass_of($class_name, $transport_abstract_name)) {
            throw new InvalidArgumentException("Class '{$class_name}' does not extend $transport_abstract_name");
        }

        // Set the class name.
        $this->transport_class_name = $class_name;

        // If the class has already been instantiated and it has changed, then
        // destroy the old class.
        // This will affect only future API calls, and not any Entries that have 
        // already been fetched.
        if (isset($this->transport) && get_class($this->transport) != $class_name) {
            $this->transport = null;
        }

        return $this;
    }

    // Instantiate the transport object and set up all its parameters.
    // Do not instantiate it if it is already there, but do set up its
    // parameters each time, in case they have changed.

    public function initTransport()
    {
        // If the transport object is already instantiated, then
        // no need to do it again.
        if (!isset($this->transport)) {
            $this->setTransport(new $this->transport_class_name());
        }

        // Set up all its parameters...

        // Set the transport entry point URL.
        $this->buildEntryPoint();
        $this->transport->setEntryPointUrl($this->getTransportUrlField('url'));

        // Support chaining.
        return $this;
    }

    // Setter/injecter for the rest controller.
    // This should not normally be used externally; use initTransport instead.

    public function setTransport(\Academe\SugarRestApi\Transport\ControllerAbstract $rest_controller)
    {
        $this->transport = $rest_controller;
        return $this;
    }


    // *** DEPRECATED ***
    public function setEntryPoint()
    {
        $this->transport->buildEntryPoint();
    }

    public function setTransportUrlField($name, $value)
    {
        // The values all need to be strings; ignore any that are not.
        if (!isset($value) || !is_string($value)) return $this;

        // Set the field value.
        $this->transport_url_parts[$name] = $value;

        // When setting any placeholder that could be used to construct the URL,
        // discard the current expanded template.
        if ($name != 'url') {
            $this->setTransportUrlField('url', '');
        }

        return $this;
    }

    public function setTransportUrlFields($fields)
    {
        if (is_array($fields)) {
            $this->transport_url_parts = array_merge($this->transport_url_parts, $fields);
        }

        return $this;
    }

    public function getTransportUrlField($name = null)
    {
        if (!isset($name)) {
            return $this->transport_url_parts;
        } elseif (isset($this->transport_url_parts[$name])) {
            return $this->transport_url_parts[$name];
        } else {
            return '';
        }
    }

    public function getTransportUrlFields()
    {
        return $this->getTransportUrlField();
    }

    /**
    * Not sure if this is helpful, or the generic setTransportUrlField() will do for all instances.
    * If useful, then I guess we need to expand protocol, path, version etc.
    */

    public function setProtocol($value)
    {
        return $this->setTransportUrlField('protocol', $value);
    }
    public function setDomain($value)
    {
        return $this->setTransportUrlField('domain', $value);
    }
    public function setPath($value)
    {
        return $this->setTransportUrlField('path', $value);
    }
    public function setUrl($value)
    {
        return $this->setTransportUrlField('url', $value);
    }
    public function setTemplate($value)
    {
        return $this->setTransportUrlField('template', $value);
    }

    /**
    * Build the entry point URL from the template.
    * 'force' will rebuild unconditionally, otherwise it will not be
    * rebuilt if the URL already set.
    */

    public function buildEntryPoint($force = false)
    {
        // The URL has not yet been built or we are forcing a rebuild.
        if ($this->getTransportUrlField('url') == '' || $force) {
            $this->setTransportUrlField('url', $this->getTransportUrlField('template'));

            // Only do this if the URL has not already been set, i.e. still contains placeholders.
            if (strpos($this->getTransportUrlField('url'), '{') !== FALSE) {
                // Do placeholder substitutios.
                // We are not going to worry about the placeholders being recursive, as we know just
                // plain text is being passed in.
                foreach(array_keys($this->getTransportUrlField()) as $sub) {
                    if ($sub == 'url') continue;
                    $this->setTransportUrlField(
                        'url',
                        str_replace(
                            '{'.$sub.'}',
                            $this->getTransportUrlField($sub),
                            $this->getTransportUrlField('url')
                        )
                    );
                }
            }
        }
    }


    // Get data that should be persisted to
    // avoid having to log in afresh on each page request.
    // The transport details are now included, since the session is only
    // relevant for those details.
    // The password is never included for security, so will need to be provided
    // from another source on every page the session is used.
    public function getSession()
    {
        return json_encode(array(
            'authUsername' => $this->authUsername,
            // Don't store the password, as it could end up being scattered over
            // session tables and other storage.
            //$this->authPassword,
            'authVersion' => $this->authVersion,
            'sessionId' => $this->getSessionId(),
            'userId' => $this->user_id,
            // Save the name of the REST transport controller class.
            'restClass' => get_class($this->transport),
            // Transport details enabling us to get to the CRM.
            'transportDetails' => $this->getTransportUrlFields(),
        ));
    }

    // Put a saved session back into the API.
    // The session as a JSON string, returned from getSession().

    public function putSession($session)
    {
        // Extract the data.
        $data = @json_decode($session, true);

        if (is_array($data)) {
            if (isset($data['authUsername'])) $this->authUsername = $data['authUsername'];
            if (isset($data['authVersion'])) $this->authVersion = $data['authVersion'];
            if (isset($data['sessionId'])) $this->session_id = $data['sessionId'];
            if (isset($data['userId'])) $this->user_id = $data['userId'];
            if (isset($data['restClass'])) $this->setTransportClassName($data['restClass']);
            if (isset($data['transportDetails']) && is_array($data['transportDetails'])) {
                $this->setTransportUrlFields($data['transportDetails']);
            }
        }

        return $this;
    }

    // When conveting to a string for storage, return the object as a json structure.

    public function __toString()
    {
        return $this->getSession();
    }

    // Get the current CRM session ID.

    public function getSessionId()
    {
        return $this->session_id;
    }

    // Allow persistent data to be restored from the session.
    public function __construct($session = '')
    {
        // Move the API version number into the transport array.
        $this->setTransportUrlField('version', $this->version);

        // The path may also be set for some API versions, in particular for custom APIs.
        if (isset($this->path)) {
            $this->setTransportUrlField('path', $this->path);
        }

        if (!empty($session)) {
            // TODO: is there a better way of masking decoding errors?
            $data = @json_decode($session, true);
            if (is_array($data)) {
                foreach($data as $name => $value) {
                    // Restore the REST class, if it can be instantiated.
                    if ($name == 'restClass' && class_exists($value)) {
                        $this->transport = new $value();
                    } elseif (property_exists($this, $name)) {
                        $this->$name = $value;
                    }
                }
            }
        }
    }

    // Autologout, if required.

    public function __destruct()
    {
        if ($this->autologout) $this->logout;
    }

    // Set the username and password authentication credentials.
    // They can be passed in here or wait until logging in.
    // If the details are not the same as already stored, then make
    // sure the sesion is cleared.
    public function setAuth($username = NULL, $password = NULL, $version = NULL)
    {
        $detailsChanged = false;

        if (isset($username)) {
            if ($this->authUsername != $username) $detailsChanged = true;
            $this->authUsername = $username;
        }

        if (isset($password)) {
            //if ($this->authPassword = $password) $detailsChanged = true;
            $this->authPassword = $password;
        }

        if (isset($version)) $this->authVersion = $version;

        // If the credentials have changed, then we need to log in as a different user,
        // and so the current session should be cleared.
        if ($detailsChanged) {
            $this->clearSession();
        }
    }

    // Validate the current session.
    // If the session is not valid, then clear the session properties.
    // Returns true if the user is logged on, false otherwise.
    public function validateSession()
    {
        // If we have no session ID, then we are certain not to be logged on.
        if (!isset($this->session_id)) return false;

        // We have a session ID - test it. Get the user ID for the current session
        // from the CRM.
        $user_id = $this->getUserId();

        if ($user_id == $this->user_id) {
            return true;
        } else {
            // The user ID for the session does not match that stored,
            // or the user ID could not be retrieved. The session is bad, so
            // clear it.
            $this->clearSession();

            return false;
        }
    }

    // Clear details of the current session, so the next call results in (or requires) a login first.
    public function clearSession($destroyCredentials = false)
    {
        $this->session_id = null;
        $this->user_id = null;

        if ($destroyCredentials) {
            $this->setAuth('', '');
        }
    }

    // Make the REST POST call.
    // We aim to return the payload, decoded into an array.
    public function apiPost($method, $data = array())
    {
        // Wrap the data in a standard structure.
        $postData = array(
            'method' => $method,
            'input_type' => $this->apiInputType,
            'response_type' => $this->apiResponseType,
            'rest_data' => json_encode($data),
        );

        // Call the remote CRM.
        $payload = $this->callRest($postData, 'POST');

        // TODO: Here we check that the call succeeded, and raise exceptions as needed.

        $result = $this->parsePayload($payload);

        // Transform all name/value pair nodes in the data structure to key=>value elements.
        // Do them all, no matter where they are. From this point on, we do not have to 
        // worry about name/value pairs stuffed away in the returned data.

        $this->nameValuesToKeyValues($result);

        return $result;
    }

    // Perform the REST call.
    // Override this method for other REST libraries.
    // TODO: GET, PUT etc.
    public function callRest($data, $method = 'POST')
    {
        // Make sure the transport is instantiated and configured.
        $this->initTransport();

        switch (strtoupper($method)) {
            case 'POST':
                return $this->transport->post($data);
                break;
        }
    }

    // Parse the returned transport request data.
    // This is just a first stage, to check if valid data or an error were returned.
    // Making sense of the data is left to higher-level objects such as Entry and EntryList.
    // If there are errors in itm, then mark that. Recognising errors is a bit strange
    // with SugarCRM, and so may change with future API versions, so be aware of this.

    public function parsePayload($returnData)
    {
        // Check first if the transport controller has raised an error.
        if ($this->transport->getErrorMessage() <> '') {
            $this->sugarError = array(
                'name' => 'TransportError',
                'number' => 999,
                'description' => $this->transport->errorMessage,
            );
        }

        // The return data should already be expanded into a nested array by the transport class.
        // Most APIs return arrays, including when errors occur, but some return strings.
        if ($returnData && is_array($returnData)) {
            // Errors are returned as a triplet of properties: name, number and description.
            // If we find these three, then an error has occurred and the method call was
            // not successful. It's a bit of a fudge, but it's what we have to work with.
            if (
                count($returnData) == 3 
                && isset($returnData['name']) 
                && isset($returnData['number']) 
                && isset($returnData['description'])
            ) {
                $this->sugarError = $returnData;
            } else {
                $this->sugarError['number'] = '';
            }

            return $returnData;
        } elseif (is_string($returnData)) {
            // get_user_id() is an example that returns a string.
            return $returnData;
        }

        return null;
    }

    // Indicate whether the last method call was successful or not.
    // Returns true if successful.

    public function isSuccess()
    {
        return ($this->sugarError['number'] == '');
    }

    // Returns the error details, an array of name, number and description.

    public function error()
    {
        if ($this->isSuccess) {
            return array(
                'name' => '',
                'number' => '',
                'description' => '',
            );
        } else {
            return $this->sugarError;
        }
    }

    // Convert a name_value_list to a key/value array.
    // Maybe "Array" is a misnomer - KeyValue would be a better description.
    // Note: no longer used. Instead nameValuesToKeyValues() is applied to the full
    // data structure returned from the CRM at an early stage.
    // Kept around as a utility function.

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

    // Recursively go through a structured array returned from the CRM, and replace all 
    // name/value pairs with key=>value elements.
    // Look for fragments like this:
    //    '{id}' => array('name' => '{id}', 'value' => {value})
    // and replace them with this:
    //    '{id}' => {value}
    // Note {value} can be any data type. Do not - for now - recurse into values that happen 
    // to be arrays.
    // The array is modified in-situ by reference.

    public function nameValuesToKeyValues(&$array)
    {
        // Start the walk.
        array_walk($array, array(&$this, 'nameValuesToKeyValuesCallback'));

        // Return the processed array at the end.
        return $array;
    }

    protected function nameValuesToKeyValuesCallback(&$value, $key)
    {
        // Only interested in arrays.
        if (is_array($value)) {
            // Is this a name/value node?
            if (
                count($value) == 2
                && isset($value['name'])
                && (is_string($value['name']) || is_numeric($value['name']))
                && isset($value['value']) // Will this miss NULL values?
                && $key == $value['name']
            ) {
                // This is a name/value node, so reduce it by stripping out an array level.
                // The value may happen to be an aray, but don't recurse into it. We may need
                // to revisit that at a later date.
                $value = $value['value'];
            } else {
                // Not a name/value node - walk deeper.
                // TODO: for efficiency, only do this if $value contains elements that are also arrays.
                array_walk($value, array(&$this, 'nameValuesToKeyValuesCallback'));
            }
        }
    }

    // Convert a simple array of key=>value items into a name/value array
    // required by many of the SugarCRM API functions.
    // CHECKME: this seems to be mixing up several different things - name/value and ids?

    public function arrayToNameValues($array)
    {
        $nameValues = array();

        if (!empty($array) && is_array($array)) {
            foreach($array as $key => $value) {
                // This is the format SugarCRM expects it, as 'name/value' pairs.
                $nameValues[$key] = array('name' => $key, 'value' => $value);
            }
        }

        return $nameValues;
    }

    // Parse a relationship list returned from the API in an entry list to something 
    // more sensible.

    public function parseRelationshipList($entry_list)
    {
        $linked_data = array();

        if (!empty($entry_list['relationship_list'])) {
            foreach($entry_list['relationship_list'] as $master_sequence => $link_list_wrapper) {
                if (!empty($link_list_wrapper['link_list']) && is_array($link_list_wrapper['link_list'])) {
                    foreach($link_list_wrapper['link_list'] as $list_sequence => $list) {
                        if (!empty($list['records']) && is_array($list['records'])) {
                            $relationship_name = $list['name'];

                            foreach($list['records'] as $record) {
                                if (!empty($record['link_value']) && is_array($record['link_value'])) {
                                    // Now we have one single record from a related entity in name/value
                                    // pair format. We know which source entity it belongs to, we have
                                    // the relationship name, and we have field values at the end of that
                                    // relationship.
                                    $record_data = $record['link_value'];

                                    // Now put the record into the relationship structure, without all
                                    // the wrapper cruft of the source structure.
                                    // Use aliases if they exist.
                                    $alias = (
                                        isset($this->relationship_aliases[$relationship_name])
                                        ? $this->relationship_aliases[$relationship_name]
                                        : $relationship_name
                                    );

                                    $linked_data[$master_sequence][$alias][] = $record_data;
                                }
                            }
                        }
                    }
                } else {
                    // No 'link_list' in the wrapper wrapper here.
                    // The format of the linked list data varies depending on whether we are
                    // fetching for a single entry or multiple entries.
                    $master_sequence = 0;
                    foreach($link_list_wrapper as $list_sequence => $list) {
                        if (!empty($list['records']) && is_array($list['records'])) {
                            $relationship_name = $list['name'];
                            foreach($list['records'] as $record) {
                                // Now we have one single record from a related entity in name/value
                                // pair format. We know which source entity it belongs to, we have
                                // the relationship name, and we have field values at the end of that
                                // relationship.
                                $record_data = $record;

                                // Now put the record into the relationship structure, without all
                                // the wrapper cruft of the source structure.
                                // Use aliases if they exist.
                                $alias = (
                                    isset($this->relationship_aliases[$relationship_name])
                                    ? $this->relationship_aliases[$relationship_name]
                                    : $relationship_name
                                );
                                $linked_data[$master_sequence][$alias][] = $record_data;
                            }
                        }
                    }
                }
            }
        }

        return $linked_data;
    }

    // Convert a multi-select value to an array.
    // The value is a string in the form: ^item1^,^item2^,^...^

    public function multiSelectValueToArray($value)
    {
        // The string should start and end with a carrat.
        if (
            !is_string($value)
            || strlen($value) < 2
            || substr($value, 0, 1) != '^'
            || substr($value, -1) != '^'
        ) return $value;

        // String the first and last characters off.
        $value = substr($value, 1, -1);

        // Explode into an array.
        return explode('^,^', $value);
    }

    // Return a new entry object.

    public function newEntry($module)
    {
        $extra_args = func_get_args();
        array_shift($extra_args);

        return $this->newObject($module, $this->entry_classname);
    }

    // Return a new entry list object.

    public function newEntryList($module)
    {
        $extra_args = func_get_args();
        array_shift($extra_args);

        return $this->newObject($module, $this->entrylist_classname);
    }

    // Return a new module object.

    public function newModule($module)
    {
        $extra_args = func_get_args();
        array_shift($extra_args);

        return $this->newObject($module, $this->module_classname, $extra_args);
    }

    // Create a generic object.
    // constructor_args is an array of arguments that are passed into the constructor
    // of the class as separate argumens.

    protected function newObject($module, $class_name, $constructor_args = array())
    {
        // Create the object with an optional variable number of parameters using clever
        // reflection.

        $reflection = new \ReflectionClass($class_name);
        $object = $reflection->newInstanceArgs($constructor_args);

        // Give it access to this API.
        $object->setApi($this);

        // Set the module.
        $object->setModule($module);

        return $object;
    }
}

