<?php

/**
 * TODO: some properties need to persist from one page to another, to avoid
 * having to log in over and over. The sessionId is the main thing.
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
    public $entryPoint = '{protocol}://{site_domain}/service/v4/rest.php';

    // The username and password used to log in.
    // To be persisted in the session.
    public $authUsername = 'user';
    public $authPassword = 'password';
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


    // Get data that should be persisted to
    // avoid having to log in again on each page requst.
    public function getJsonData()
    {
        return json_encode(array(
            'authUsername' => $this->authUsername,
            //$this->authPassword,
            'authVersion' => $this->authVersion,
            'sessionId' => $this->sessionId,
            'userId' => $this->userId,
        ));
    }

    // Allow persistent data to be restored from the session.
    public function __construct($jsonData = '')
    {
        if (!empty($jsonData)) {
            // TODO: is there a better way of masking decoding errors?
            $data = @json_decode($jsonData, true);
            if (is_array($data)) {
                foreach($data as $name => $value) $this->$name = $value;
            }
        }
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

    // Log into the CRM.
    // TODO: if the sessionId is already set, then check we are logged in as the
    // correct user, so we don't need to log in again.
    public function login($username = NULL, $password = NULL)
    {
        // Save the username and password so we know if we are logging in as a different user.
        $this->setAuth($username, $password);

        // Check if we have a valid session. If we do then no further login is needed.
        // TODO: what should we return here? We are logged in, but have not gone to fetch user details.
        if ($this->validateSession()) return;

        $parameters = array(
            'user_auth' => array(
                'user_name' => $this->authUsername,
                'password' => md5($this->authPassword),
                'version' => $this->authVersion,
            ),
            'application_name' => $this->applicationName,
            'name_value_list' => array(),
        ); 

        // TODO: extract the session ID and user ID, if login was successful.
        // TODO: raise exception (if appropriate) if login not successful.
        $result = $this->apiPost('login', $parameters);

        $this->sessionId = $result['id'];
        $this->userId = $result['name_value_list']['user_id']['value'];

        // We probably want to return a state.
        return $result;
    }

    // Log out of the API.
    // TODO: if we have a session going, then log out of the remote API too, before
    // we discard all the session details locally.
    public function logout()
    {
        $this->clearSession(true);
    }

    // Get a list of fields for a module.
    public function getModuleFields($moduleName, $fieldList = array())
    {
        $parameters = array(
            'session' => $this->sessionId,
            'module_name' => $moduleName,
            'fields' => $fieldList,
        );

        return $this->apiPost('get_module_fields', $parameters);
    }

    // Get the current user ID, given the session.
    public function getUserId()
    {
        $parameters = array(
            'session' => $this->sessionId,
        );

        return $this->apiPost('get_user_id', $parameters);
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
            $this->authUsername = null;
            $this->authPassword = null;
        }
    }

    // Make the REST POST call.
    // We aim to return the payload, decoded into an array.
    public function apiPost($method, $data)
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
        return $this->extractPayload($payload);
    }

    // Perform the REST call.
    // Override this method for other REST libraries.
    // TODO: GET, PUT etc.
    public function callRest($data, $method = 'POST')
    {
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
    public function extractPayload($returnData)
    {
        // TODO: check that there is valid data first.
        // States could be: network error, not authenticated, ...?
        
        // The return data should be JSON encoded and in the body element.
        if (isset($returnData['body'])) {
            // TODO: if this is not decodable JSON?
            // CHECKME: do we want to decode to arraus or objects?
            $decodeBody = json_decode($returnData['body'], true);
            return $decodeBody;
        } else {
            // No body set.
            // TODO: what to do?
        }

        return null;
    }
}
