<?php

/**
 * @todo A general parameter validation framework to check data before it is passed on.
 * @todo Handle data as recources (e.g. User, Contact, Address, Note) rather than just 
 * as multidimensional arrays that need significant parsing.
 */

namespace Academe\SugarRestApi;

class SugarRestApi
{
    // The rest controller.
    // We will be using resty/resty for now, but I have no idea how generic that is,
    // and so how easily it can be swapped out for something else.
    // TODO: see if https://github.com/mnapoli/PHP-DI is of any use here.
    public $rest = NULL;

    // The URL of the REST entry point.
    public $entryPoint = '{protocol}://{domain}{path}/service/v{version}/rest.php';

    // Placeholders for the URL parts.
    public $protocol = 'http';
    public $domain = '';
    public $path = '';
    public $version = '4';

    // The username and password used to log in.
    // To be persisted in the session.
    public $authUsername = '';
    public $authPassword = '';
    public $authVersion = '1';

    // The current session ID and the user ID this corresponds to.
    // To be persisted in the session.
    public $sessionId;
    public $userId;

    // The name of this application making the requests.
    public $applicationName = 'Academe_SugarRestApi';

    // The input type (format of data sent to the API) and
    // response type (format of data coming back).
    // For this to be useful, the encoding and decoding must all be done
    // through overridable methods.
    public $apiInputType = 'JSON';
    public $apiResponseType = 'JSON';

    // Default headers and options for the REST controller.
    public $restHeaders = NULL;
    public $restOptions = NULL;

    // Details of any error message.
    public $error = array(
        'name' => '',
        'number' => '',
        'description' => '',
    );

    // Automatically log out when we close.
    // If true, an attempt is made to log out before the object is destroyed.
    // If false, the remote API session is kept open and can be used on the next page request.
    public $autologout = false;


    // Get data that should be persisted to
    // avoid having to log in afresh on each page request.
    public function getSession()
    {
        return json_encode(array(
            'authUsername' => $this->authUsername,
            // Don't store the password, as it could end up being scattered over
            // session tables and other storage.
            //$this->authPassword,
            'authVersion' => $this->authVersion,
            'sessionId' => $this->sessionId,
            'userId' => $this->userId,
            // Save the name of the REST class.
            'restClass' => get_class($this->rest),
        ));
    }

    // When conveting to a string for storage, return the object as a json structure.
    public function __toString()
    {
        return $this->getSession();
    }

    // Set the REST entry point URL
    public function setEntryPoint($url = NULL)
    {
        // If no URL is passed in then construct it from the parts we know about.
        if (!isset($url)) {
            // Only do this if the URL has not already been set, i.e. still contains placeholders.
            if (strpos($this->entryPoint, '{') !== FALSE) {
                // Do placeholder substitutios.
                // We are not going to worry about the placeholders being recursive, as we know just
                // plain text is being passed in.
                foreach(array('protocol', 'domain', 'path', 'version') as $sub) {
                    $this->entryPoint = str_replace('{'.$sub.'}', $this->$sub, $this->entryPoint);
                }
            }
        } else {
            $this->entryPoint = $url;
        }
    }

    // Allow persistent data to be restored from the session.
    public function __construct($session = '')
    {
        if (!empty($session)) {
            // TODO: is there a better way of masking decoding errors?
            $data = @json_decode($session, true);
            if (is_array($data)) {
                foreach($data as $name => $value) {
                    // Restore the REST class, if it can be instantiated.
                    if ($name == 'restClass' && class_exists($value)) {
                        $this->rest = new $value();
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

    // Setter/injecter for the rest controller.
    // Perhaps we need to be able to default to resty/resty here, assuming it is set as
    // a dependancy.
    public function setRest($restController)
    {
        $this->rest = $restController;
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

        // If the credentials hjave changed, then we need to log in as a different user,
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
        if (!isset($this->sessionId)) return false;

        // We have a session ID - test it.
        $userId = $this->getUserId();
        if ($userId = $this->userId) {
            return true;
        } else {
            // The user ID for the session does not match that stored,
            // or the user ID could not be retrived. The session is bad, so
            // clear it.
            $this->clearSession();

            return false;
        }
    }

    // Clear details of the current session, so the next call results in (or requires) a login first.
    public function clearSession($destroyCredentials = false)
    {
        $this->sessionId = null;
        $this->userId = null;

        if ($destroyCredentials) {
            $this-setAuth('', '');
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

        $payload = $this->callRest($postData, 'POST');

        // TODO: Here we check that the call succeeded, and raise exceptions as needed.
        $result = $this->extractPayload($payload);

        // Find any name/value lists and transform the, into key/value lists for convenience.
        // Note: we will probably not do this here any more. The entry object can transform as required.
        //$this->transformNameValueLists($result);

        return $result;
    }

    // Perform the REST call.
    // Override this method for other REST libraries.
    // TODO: GET, PUT etc.
    public function callRest($data, $method = 'POST')
    {
        // Make sure the entry point URL is set.
        $this->setEntryPoint();

        switch (strtoupper($method)) {
            case 'POST':
                return $this->rest->post($this->entryPoint, $data, $this->restHeaders, $this->restOptions);
                break;
        }
    }

    // Extract the return data from an API call.
    // This function is given whatever the result is of the REST call.
    // Override this method if a different REST library is used and the payload
    // is extracted in a different way.
    // Also detect errors in the return value.
    public function extractPayload($returnData)
    {
        // TODO: check that there is valid data first.
        // States could be: network error, not authenticated, ...?
        
        // The return data should be JSON encoded and in the body element.
        if (isset($returnData['body'])) {
            // TODO: if this is not decodable JSON?
            // CHECKME: do we want to decode to arraus or objects?
            // CHECKME: why isn't this automatically decoded already by Resty? It should be.
            // CHECKME: why does Resty always return "error"=>true? What does that mean?
            $decodeBody = json_decode($returnData['body'], true);

            // Errors are returned as a triplet of properties: name, number and description.
            // If we find these three, then assue an error has occurred and the method call was
            // not successful.
            if (
                count($decodeBody) == 3 
                && isset($decodeBody['name']) 
                && isset($decodeBody['number']) 
                && isset($decodeBody['description'])
            ) {
                $this->error = $decodeBody;
            } else {
                $this->error['number'] = '';
            }

            return $decodeBody;
        } else {
            // No body set.
            // TODO: what to do?
        }

        return null;
    }

    // Indicate whether the last method call was successful or not.
    // Returns true if successful.
    public function isSuccess()
    {
        return ($this->error['number'] == '');
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
            return $this->error;
        }
    }

    // Search the result for name_value_list elements and expand it into
    // key/value pairs in the element key_value_list.
    public function transformNameValueLists(&$data)
    {
        if (!is_array($data)) return;

        // Start walking the array.
        array_walk($data, array(&$this, 'transformNameValueListsCallback'));
    }

    private function transformNameValueListsCallback(&$value, $key)
    {
        // Only look at arrays - we don't care about leaf nodes.
        if (is_array($value)) {
            // If the array contains a name_value_list element, then add a key_value_list element after it.
            // Do not walk any further elements in that array. CHECKME: do we need to go any deeper?
            if (isset($value['name_value_list'])) {
                $value['key_value_list'] = array();
                foreach($value['name_value_list'] as $v) {
                    if (isset($v['name']) && isset($v['value'])) $value['key_value_list'][$v['name']] = $v['value'];
                }
            } else {
                // The array element does not have a name_value_list element, so we will
                // walk it to see if there are any at a deeper level.
                array_walk($value, array(&$this, 'transformNameValueListsCallback'));
            }
        }
    }

    // Return a new entity object.
    // We need to expand this to more of a factory so that the returned
    // class is relevant to the entity module and not simply generic.
    // $entry is an entry already fetched from the CRM via the API.
    public function newEntry($module)
    {
        $Entry = new Entry();

        // Give it access to this API.
        $Entry->setApi(&$this);

        // Set the module if not already set.
        $Entry->setModule($module);

        return $Entry;
    }
}
