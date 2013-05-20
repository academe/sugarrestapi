<?php

/**
 * v4 of the SugarCRM API
 * This would extend v3, if a v3 existed. I'll leave that for others to implement
 * if it is desired.
 * FIXME: this should be V4, not v4.
 */

namespace Academe\SugarRestApi\Api;

class v4 extends \Academe\SugarRestApi\Api\Api
{
    public $version = 'v4';

    // Log into the CRM.
    // If the session_id is already set, then check we are logged in as the
    // correct user, so we don't need to log in again.
    // Returns true if successful.
    public function login($username = NULL, $password = NULL)
    {
        // Save the username and password so we know if we are logging in as a different user.
        // If the details have changed, then the session will be cleared.
        $this->setAuth($username, $password);

        // Check if we have a valid session. If we do then no further login is needed.
        if ($this->validateSession()) return true;

        $parameters = array(
            'user_auth' => array(
                'user_name' => $this->authUsername,
                'password' => md5($this->authPassword),
                'version' => $this->authVersion,
            ),
            'application_name' => $this->applicationName,
            'name_value_list' => array(),
        ); 

        // Attempt to log in.
        $result = $this->apiPost('login', $parameters);

        if ($this->isSuccess()) {
            // Extract the session ID and user ID.
            $this->session_id = $result['id'];
            $this->user_id = $result['name_value_list']['user_id']['value'];
            return true;
        } else {
            return false;
        }
    }

    // Log out of the API.
    // If we have a session going, then log out of the remote API too, before
    // we discard all the session details locally.
    public function logout()
    {
        // If the session is open to the CRM, then log out of that.
        $session_id = $this->getSessionId();
        if (isset($session_id)) {
            $parameters = array(
                'session' => $session_id,
            );
            $this->apiPost('logout', $parameters);
        }

        $this->clearSession();
    }

    // Get a list of fields for a module.
    // These are the basic fields and the link fields.
    // The drop-down fields include a full list of options.
    // The "required" flag is included, but not much else with respect
    // to validation (no field lengths, for example).

    public function getModuleFields($moduleName, $fieldList = array())
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'module_name' => $moduleName,
            'fields' => $fieldList,
        );

        return $this->apiPost('get_module_fields', $parameters);
    }

    // Get the current CRM user ID, given the session.

    public function getUserId()
    {
        $parameters = array(
            'session' => $this->getSessionId(),
        );

        return $this->apiPost('get_user_id', $parameters);
    }

    // Retrieve a list of SugarBean based on provided IDs.
    // This API will not work with report module.
    // Each SugarBean will inckude an array of name/value pairs in the array 'name_value_list', but
    // not as an associative array. It may be helpful to convert this to an associative
    // array for each bean returned. The key/value pair structure is great for other languages that
    // don't have associatived arrays, such as C#, and converts easily into dictionary structures.
    // But that is not so easy to handle in PHP.
    // A supplied ID that does not mnatch a Sugarbean that the user can access, will return with
    // a "warning" name/value pair explaining why.
    // TODO: this warning should perhaps be detected and put into the error log, or the record should
    // simply be discarded.
    // Defaults to all fields if none supplied.

    public function getEntries(
        $moduleName,
        $ids,
        $selectFields = array(),
        $linkNameFields = array()
    )
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'module_name' => $moduleName,
            'ids' => (is_string($ids) ? explode(',', $ids) : $ids),
            'select_fields' => (is_string($selectFields) ? explode(',', $selectFields) : $selectFields),
            'link_name_to_fields_array' => $this->arrayToNameValues($linkNameFields),
        );

        return $this->apiPost('get_entries', $parameters);
    }

    // Retrieve a list of beans.
    // This is the primary method for getting list of SugarBeans from Sugar.
    // The query parameter is a fragment of WHERE-clause, so to use it you need
    // intimate knowledge of what the query is that SugarCRM will construct. It's
    // pretty crap really, for an API.

    public function getEntryList(
        $moduleName,
        $query = NULL,
        $order = NULL,
        $offset = 0,
        $fields = array(),
        $linkNameFields = array(),
        $limit = NULL,
        $deleted = false,
        $favourites = false
    )
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'module_name' => $moduleName,
            'query' => $query,
            'order_by' => $order,
            'offset' => $offset,
            'select_fields' => $fields,
            'link_name_to_fields_array' => $this->arrayToNameValues($linkNameFields),
            'max_results' => $limit,
            'deleted' => $deleted,
            'favorites' => $favourites,
        );

        $result = $this->apiPost('get_entry_list', $parameters);
        return $result;
    }

    // Retrieve the layout metadata for a given modules given a specific types and views.
    // Types include: default, wireless
    // Views include: edit, detail, list, subpanel
    // Each parameter can be passed as an array of values, or a single value string, or a
    // CSV string (with no spaces).
    // The "a_" prefeix means "this is an array".

    public function getModuleLayout(
        $moduleNames,
        $types = 'default',
        $views = 'detail',
        $aclCheck = true,
        $md5 = false
    )
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'a_module_names' => (is_string($moduleNames) ? explode(',', $moduleNames) : $moduleNames),
            'a_type' => (is_string($types) ? explode(',', $types) : $types),
            'a_view' => (is_string($views) ? explode(',', $views) : $views),
            'acl_check' => $aclCheck,
            'md5' => $md5
        );

        return $this->apiPost('get_module_layout', $parameters);
    }

    // Search modules.
    // At least one module must be supplied.
    // Supported modules are Accounts, Bug Tracker, Cases, Contacts, Leads, Opportunities, Project, ProjectTask, Quotes.

    public function searchByModule(
        $searchString,
        $moduleNames,
        $offset = 0,
        $limit = NULL,
        $assignedUserId = NULL,
        $fields = array(),
        $unifiedSearchOnly = true,
        $favourites = false
    )
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'search_string' => $searchString,
            'modules' => (is_string($moduleNames) ? array($moduleNames) : $moduleNames),
            'offset' => $offset,
            'nax_results' => $limit,
            'assigned_user_id' => $assignedUserId,
            'select_fields' => $fields,
            'unified_search_only' => $unifiedSearchOnly,
            'favorites' => $favourites,
        );

        return $this->apiPost('search_by_module', $parameters);
    }

    // Get OAuth request token
    public function oauthRequestToken()
    {
        return $this->apiPost('oauth_request_token');
    }

    // Get OAuth access token
    public function oauthAccessToken()
    {
        $parameters = array(
            'session' => $this->getSessionId(),
        );

        return $this->apiPost('oauth_access', $parameters);
    }

    // Get next job from the queue
    public function jobQueueNext($clientId)
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'clientid ' => $clientId,
        );

        return $this->apiPost('job_queue_next', $parameters);
    }

    // Run cleanup and schedule.
    public function jobQueueCycle($clientId)
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'clientid ' => $clientId,
        );

        return $this->apiPost('job_queue_cycle', $parameters);
    }

    // Run job from queue.
    public function jobQueueRun($jobId, $clientId)
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'jobid ' => $jobId,
            'clientid ' => $clientId,
        );

        return $this->apiPost('job_queue_run', $parameters);
    }

    // Retrieve a single SugarBean based on ID.
    public function getEntry(
        $moduleName,
        $id,
        $fields = array(),
        $linkNameFields = array(),
        $trackView = false
    )
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'module_name' => $moduleName,
            'id' => $id,
            'select_fields' => $fields,
            'link_name_to_fields_array' =>  $this->arrayToNameValues($linkNameFields),
            'track_view' => $trackView,
        );

        return $this->apiPost('get_entry', $parameters);
    }

    // Retrieve the md5 hash of the vardef entries for a particular module.

    public function getModuleFieldsMd5($moduleName)
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'module_name' => $moduleName,
        );

        return $this->apiPost('get_module_fields_md5', $parameters);
    }

    // Retrieve the md5 hash of a layout metadata for a given module given a specific type and view.
    // Types include: default, wireless
    // Views include: edit, detail, list, subpanel.
    // Unlike API get_module_layout, the parameters are not prefixed "a_", but these are still
    // arrays.

    public function getModuleLayoutMd5(
        $moduleNames,
        $types = 'default',
        $views = 'detail',
        $aclCheck = true
    )
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'module_name' => (is_string($moduleNames) ? explode(',', $moduleNames) : $moduleNames),
            'type' => (is_string($types) ? explode(',', $types) : $types),
            'view' => (is_string($views) ? explode(',', $views) : $views),
            'acl_check' => $aclCheck,
        );

        return $this->apiPost('get_module_layout_md5', $parameters);
    }

    // Update or create a single SugarBean.
    // The data needs to be passed in as name/value list, so pass a key/value array
    // through $api->arrayToNameValues() before passing it in here.

    public function setEntry($moduleName, $data, $trackView = false)
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'module_name' => $moduleName,
            'name_value_list' => $data,
            'track_view' => $trackView,
        );

        return $this->apiPost('set_entry', $parameters);
    }

    // Retrieve the list of available modules on the system available to the currently logged in user.
    // filter is all, default or mobile
    // For each module we get the key, the label and an ACL. The ACL is an array of arrays, each
    // leaf node being an action/access pair (e.g. edit/true, delete/false). These would be more
    // useful converted to key/value arrays.

    public function getAvailableModules($filter = 'all', $moduleNames = array())
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'filter' => $filter,
            'modules' => (is_string($moduleNames) ? array($moduleNames) : $moduleNames),
        );

        return $this->apiPost('get_available_modules', $parameters);
    }

    // Get labels and text in the current module for the given module(s).
    // If md5 is true, then only the md5 of the translations will be returned.
    // The md5 parameter really should not be here; the md5 should really be a 
    // completely separate API function, like it is for getModuleLayout vs
    // getModuleLayoutMd5 (although getModuleLayout does have a legacy md5 parameter).

    public function getLanguageDefinition(
        $moduleNames,
        $md5 = false
    )
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'modules' => (is_string($moduleNames) ? explode(',', $moduleNames) : $moduleNames),
            'MD5' => $md5,
        );

        return $this->apiPost('get_language_definition', $parameters);
    }

    // Get server information.
    // Only returns flavor (e.g. "CE"), version (e.g. "6.5.11") and gmt_time (e.g. "2013-05-15 13:16:55")
    // It does not provide the database engine and version, which would have been
    // really useful when constructing query conditions (e.g. to know how to escape a quote (') in a 
    // string).

    public function getServerInfo()
    {
        return $this->apiPost('get_server_info');
    }

    // Retrieve a list of recently viewed records by a module.
    // Documentation on this one is not clear. Multiple modules can be supplied, but
    // the returned results will vary depending on the order of the modules in the array.
    // A single array is returned, each element being a small array identifying the module,
    // name, entry id and date mofdified.
    // A usecase for this would be as a part of a dashboard page.

    public function getLastViewed(
        $moduleNames
    )
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'module_names' => (is_string($moduleNames) ? explode(',', $moduleNames) : $moduleNames),
        );

        return $this->apiPost('get_last_viewed', $parameters);
    }

    // Retrieve a list of upcoming activities including Calls, Meetings, Tasks and Opportunities.
    // A usecase for this would be as a part of a dashboard page.

    public function getUpcomingActivities()
    {
        $parameters = array(
            'session' => $this->getSessionId(),
        );

        return $this->apiPost('get_upcoming_activities', $parameters);
    }

    // Retrieve a collection of beans that are related to the specified bean and optionally return
    // relationship data for those related beans.
    // Note API v4_1 extends this function.
    //
    // @param $linkFieldName string A single linkfield name, as identified in get_module_fields
    // @param $relatedFields array The names of the fields you want to return from the linked entries.
    // @param $relatedModuleLinkNameFields array A list of relationships to follow on the selected entries, and the fields to return for those entries.
    //
    // Returns an array of two elements: 'entry_list' and 'relationship_list'. Each of those
    // elements are numerically indexed, and match 1:1 (e.g. element 3 of the entry_list has a
    // corresponding element 3 of the relationship_list).
    //
    // Requesting for module "Contacts", contact ID "123", linked field "accounts" should return
    // in the entry_list, the [single] Account that the contact is linked to. The related fields
    // can be supplied to include any of the fields in those accounts.
    // The related module link name fields should take the references one more step beyond
    // that into a third layer of [multiple] related modules. This are specified as an array of these:
    // 'link-field-name' => array('field1', 'field2', ...)

    public function getRelationships(
        $moduleName,
        $beanId,
        $linkFieldName,
        $relatedModuleQuery = '',
        $relatedFields = array('id', 'name'),
        $relatedModuleLinkNameFields = array(),
        $deleted = false,
        $orderBy = '',
        $limit = false
    )
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'module_name' => $moduleName,
            'module_id' => $beanId,
            'link_field_name' => $linkFieldName,
            'related_module_query' => $relatedModuleQuery,
            'related_fields' => $relatedFields,
            'related_module_link_name_to_fields_array' => $this->arrayToNameValues($relatedModuleLinkNameFields),
            'deleted' => $deleted,
            'order_by' => $orderBy,
            'limit' => $limit,
        );

        //var_dump($this->apiPost('get_relationships', $parameters));
        return $this->apiPost('get_relationships', $parameters);
    }

    // Set a single relationship between two beans. The items are related by module name and id.
    // To remove the links instead of adding them, set $delete to 1.
    // Relationship links are added and removed in a cumulative manner, i.e. only those
    // relatedIds supplied will be touched.

    public function setRelationship(
        $moduleName,
        $beanId,
        $linkFieldName,
        $relatedIds,
        $data,
        $delete = 0
    )
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'module_name' => $moduleName,
            'module_id' => $beanId,
            'link_field_name' => $linkFieldName,
            'related_ids' => $relatedIds,
            'name_value_list' => $data,
            'delete' => $delete,
        );

        return $this->apiPost('set_relationship', $parameters);
    }

    // Set a single relationship between two beans. The items are related by module name and id.
    // @todo Description is wrong.

    public function setRelationships(
        $moduleNames,
        $beanIds,
        $linkFieldNames,
        $relatedIds,
        $data,
        $delete = array()
    )
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'module_names' => (is_string($moduleNames) ? array($moduleNames) : $moduleNames),
            'module_ids' => (is_string($beanIds) ? array($beanIds) : $beanIds),
            'link_field_names' => $linkFieldNames,
            'related_ids' => $relatedIds,
            'name_value_lists' => $data,
            'delete_array' => $delete,
        );

        return $this->apiPost('set_relationships', $parameters);
    }

    // Update or create a list of SugarBeans.

    public function updateEntries($moduleName, $data)
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'module_name' => $moduleName,
            'name_value_lists' => $this->arrayToNameValues($data),
        );

        return $this->apiPost('update_entries', $parameters);
    }

    // Perform a seamless login. This is used internally during the sync process.
    // This sounnds interesting. No idea what it is, what with there being no documentation.

    public function seamlessLogin()
    {
        return $this->apiPost('seamless_login');
    }

    // Add or replace the attachment on a Note.
    // Optionally you can set the relationship of this note to Accounts/Contacts and so on by 
    // setting related_module_id, related_module_name.
    // The binary file content is passed in.
    // TODO: a variation on this where a local file URL or an open stream can be passed in,
    // would cut down on the memory needed to encode the file. We are kind of stuck with this
    // in-memory manipulation of the file content, because that is what the API transport
    // requires (so far as I know).
    //
    // TODO: it should in theory be possible to stream a file diectly from disk to the 
    // REST service, avoiding the need to hold it all in memory. A look at Guzzle may offer
    // some clues on how this can be done.

    public function setNoteAttachment(
        $id_or_note,
        $filename = '',
        $file = '',
        $moduleId = '',
        $moduleName = ''
    )
    {
        if (is_array($id_or_note)) {
            $note = $id_or_note;
        } else {
            $note = array(
                'id' => $id,
                'filename' => $filename,
                'file' => base64_encode($file),
                'related_module_id' => $moduleId,
                'related_module_name' => $moduleName,
            );
        }

        $parameters = array(
            'session' => $this->getSessionId(),
            'note' => $note,
        );

        return $this->apiPost('set_note_attachment', $parameters);
    }

    // Retrieve an attachment from a note.

    public function getNoteAttachment($id)
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'id' => $id,
        );

        $result = $this->apiPost('get_note_attachment', $parameters);

        // Decode the file to its binary format.
        // This is all in-memoery stuff, so fingers crossed that it is going to work.
        if (isset($result['note_attachment']['file'])) {
            $result['note_attachment']['file'] = base64_decode($result['note_attachment']['file']);
        }

        return $result;
    }

    // Sets a new revision for this document.
    // The file content needs to be base64_encoded.

    public function setDocumentRevision($id_or_revision, $documentName = '', $revision = '', $filename = '', $fileContent = '')
    {
        if (is_array($id_or_revision)) {
            $revision = $id_or_revision;
        } else {
            $revision = array(
                'id' => $id_or_revision,
                'document_name' => $documentName,
                'revision' => $revision,
                'filename' => $filename,
                'file' => $fileContent,
            );
        }

        $parameters = array(
            'session' => $this->getSessionId(),
            'document_revision' => $revision,
        );

        return $this->apiPost('set_document_revision', $parameters);
    }

    // This method is used as a result of the .htaccess lock down on the cache directory.
    // It will allow a properly authenticated user to download a document that they have
    // proper rights to download.
    // The document must be base64_decoded once it is retreived.
    // It is not clear where we get the document revision ID from, or whether this just
    // applies to the Documents module, or extended to any module that supports the
    // uploaded of documents.

    public function getDocumentRevision($id)
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'id' => $id,
        );

        return $this->apiPost('get_document_revision', $parameters);
    }

    // Once we have successfuly done a mail merge on a campaign, we need to notify
    // Sugar of the targets and the campaign_id for tracking purposes.

    public function setCampaignMerge($targets, $campaignId)
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'targets' => $targets,
            'campaign_id' => $campaignId,
        );

        return $this->apiPost('set_campaign_merge', $parameters);
    }

    public function getEntriesCount($moduleName, $query, $deleted = false)
    {
        $parameters = array(
            'session' => $this->getSessionId(),
            'module_name' => $moduleName,
            'query' => $query,
            'deleted' => $deleted,
        );

        return $this->apiPost('get_entries_count', $parameters);
    }
}
