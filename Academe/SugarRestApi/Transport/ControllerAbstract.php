<?php

/**
 * Abstract for the rest controller we expect to see.
 * Put whatever controller you like behind the interface.
 */

namespace Academe\SugarRestApi\Transport;

abstract class ControllerAbstract
{
    // The Guzzle client.
    public $client = null;

    // The template and expanded URL of the REST entry point.
    public $entryPointTemplate = '{protocol}://{domain}{path}/service/v{version}/rest.php';
    public $entryPointUrl = '';

    // Placeholders for the REST entry point URL parts.
    // Use setTemplatePlaceholder() or the more specific methods
    // to set these, or created more template placeholders.
    public $entryPointPlaceholders = array(
        'protocol' => 'http',
        'domain' => '',
        'path' => '',
        'version' => '4',
    );

    // The text of any error messages after calling a rest resource.
    public $errorMessage = '';

    /**
    * Set a placeholder value for the entry point URL template.
    */

    // Set the domain in the constructor, as that will be the most common thing to change.
    public function __construct($domain = null)
    {
        if (isset($domain)) {
            $this->setDomain($domain);
        }
    }

    public function setTemplatePlaceholder($name, $value)
    {
        $this->entryPointPlaceholders[$name] = $value;

        // When setting any placeholder, discard the current expanded template.
        $this->entryPointUrl = '';
    }

    /**
    * Not sure if this is helpful, or the generic setTemplatePlaceholder() will do for all instances.
    * If useful, then I guess we need to expand protocol, path, version etc.
    */

    public function setProtocol($value)
    {
        return $this->setTemplatePlaceholder('protocol', $value);
    }
    public function setDomain($value)
    {
        return $this->setTemplatePlaceholder('domain', $value);
    }
    public function setPath($value)
    {
        return $this->setTemplatePlaceholder('path', $value);
    }

    /**
    * Set the REST entry point template, which could be a template with {placeholders}
    * or could be a raw URL.
    */

    public function setEntryPointTemplate($template)
    {
        $this->entryPointTemplate = $template;
        $this->entryPointUrl = '';
    }

    /**
    * Build the entry point URL from the template.
    * 'force' will rebuild unconditionally, otherwise it will not be
    * rebuilt if the URL already set.
    */
    public function buildEntryPoint($force = false)
    {
        // The URL has not yet been built or we are forcing a rebuild.
        if (empty($this->entryPointUrl) || $force) {
            $this->entryPointUrl = $this->entryPointTemplate;

            // Only do this if the URL has not already been set, i.e. still contains placeholders.
            if (strpos($this->entryPointUrl, '{') !== FALSE) {
                // Do placeholder substitutios.
                // We are not going to worry about the placeholders being recursive, as we know just
                // plain text is being passed in.
                foreach(array_keys($this->entryPointPlaceholders) as $sub) {
                    $this->entryPointUrl = str_replace('{'.$sub.'}', $this->entryPointPlaceholders[$sub], $this->entryPointUrl);
                }
            }
        }
    }

    /**
     * Reset the error message.
     */

    public function resetErrorMessage()
    {
        $this->errorMessage = '';
    }

    /**
     * get the error message.
     */

    public function getErrorMessage()
    {
        return($this->errorMessage);
    }
}

