<?php

/**
 * v4_portal of the SugarCRM API
 * Ideally this class would be called v4_1, but PSR-0 treats underscores in a special way.
 */

namespace Academe\SugarRestApi\Api;

class v4portal extends v4
{
    public $version = 'v4_portal';
    public $path = '/custom';

    // Call the custom authenticate contact methond on SugarCRM.

    public function portalAuthenticate(
        $email,
        $password,
        $primary_only = true
    )
    {
        $parameters = array(
            'email' => $email,
            'password_md5' => md5($password),
            'primary_only' => $primary_only,
        );

        return $this->apiPost('portal_authenticate', $parameters);
    }

    // Set a password for a contact on SugarCRM.

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
}
