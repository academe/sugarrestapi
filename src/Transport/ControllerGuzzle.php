<?php

/**
 *
 */

namespace Academe\SugarRestApi\Transport;

class ControllerGuzzle extends ControllerAbstract
{
    /**
     * Instantiate the Guzzle client object if it is not already set.
     */

    public function setClient()
    {
        // Instantiate the controller if necessary. 
        if (empty($this->client)) {
            // Build the URL.

            $this->buildEntryPoint();

            // Instantiate the client REST controller with the URL.

            $this->client = new \GuzzleHttp\Client([
                'base_uri' => $this->entryPointUrl,
            ]);
        }
    }

    /**
     * POST data.
     * SugarCRM RESTful API has just one entry point, so we do not need to concern
     * ourselves with the path at this stage.
     * The return value will be the data that comes back, in array form.
     */

    public function post($data)
    {
        // Note that there is no resource path for SugarCRM, beyond the root path that
        // is set elsewhere. This is here in case things change, and SugarCRM becomes a
        // little more resty.
        $path = '';

        // Instantiate the controller if necessary.
        $this->setClient();

        // Clear the error message.
        $this->resetErrorMessage();

        try {
            // Send the request for a resource and get the result back,
            // with the assumption that it is JSON.

            //$response = $request->send();
            $response = $this->client->request('POST', $path, [
                'form_params' => $data,
            ]);

            $body = $response->getBody();
            $result = json_decode((string)$body, true);

            if (json_last_error() != \JSON_ERROR_NONE) {
                // Failure to decode the response as JSON.
                $result = null;
                $this->errorMessage = $e->getMessage();
            }
        }
        catch (\GuzzleHttp\Exception\ClientException $e) {
            // 4xx
            $result = null;
            $this->errorMessage = $e->getMessage();
        }
        catch (\GuzzleHttp\Exception\ServerException $e) {
            // 5xx
            $result = null;
            $this->errorMessage = $e->getMessage();
        }

        // TODO: a catch-all would be useful.

        return $result;
    }

    /**
     * Set a placeholder name and value for inserting into the URL template.
     */

    public function setTemplatePlaceholder($name, $value)
    {
        // Reset an existing client, if it exists, since that client will
        // be locked onto the old URL..
        $this->client = NULL;

        // Do the normal stuff next.
        return parent::setTemplatePlaceholder($name, $value);
    }

    public function setEntryPointTemplate($template)
    {
        // Reset an existing client, if it exists, since that client will
        // be locked onto the old URL..
        $this->client = NULL;

        // Do the normal stuff next.
        return parent::setEntryPointTemplate($template);
    }

}

