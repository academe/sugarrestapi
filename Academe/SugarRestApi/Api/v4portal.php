<?php

/**
 * v4_portal of the SugarCRM API
 * Ideally this class would be called v4_1, but PSR-0 treats underscores in a special way.
 * This example is a custom API entry point that allows authentication to be added to 
 * contacts, and so support a self-service portal application. The SugarCRM side of it will
 * be published later, but is fairly simple.
 */

namespace Academe\SugarRestApi\Api;

class v4portal extends v4
{
    public $version = 'v4_portal';
    public $path = '/custom';

    // Call the custom authenticate contact methond on SugarCRM.
    // This allows a conatct to log into the portal using their email address and
    // a password. The password is securely stored (using crypt()) on a custom field
    // of the Contacts module.

    public function portalAuthenticate(
        $email,
        $password,
        $primary_only = true
    )
    {
        $parameters = array(
            'email' => $email,
            'password_md5' => md5($password),
            'primary_only' => (bool) $primary_only,
        );

        return $this->apiPost('portal_authenticate', $parameters);
    }

    // Set a password for a contact on SugarCRM.
    // The custom SugarCRM module uses crypt() to ensure the password is securely encrypted.

    public function portalSetPassword(
        $contact_id,
        $password
    )
    {
        $parameters = array(
            'contact_id' => $contact_id,
            'password_md5' => md5($password),
        );

        return $this->apiPost('portal_set_password', $parameters);
    }

    // This API returns the number of contacts that have portal access allowed and 
    // have a specified email address.
    // To reguister as a new user, we require there to be no (0) contacts with the email address.
    // To recover a password we require there to be exactly one contact. The same applies for logging
    // logging in.
    // More than one matching contact will mean that password recovery cannot take place; the administrator
    // must remove one of the contacts or set them to have no portal access allowed.

    public function portalCountEmails(
        $email,
        $primary_only = true
    )
    {
        $parameters = array(
            'email' => $email,
            'primary_only' => (bool) $primary_only,
        );

        return $this->apiPost('portal_count_emails', $parameters);
    }

}
