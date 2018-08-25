<?php

use WHMCS\Database\Capsule;


/**
 * @group whmcs
 * Requires PHP 5.6.0
 */
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


/**
 * A WHMCS module for use by Jetpack hosting partners to provision Jetpack plans.
 * The module provides functionality for partner hosts to be able to save their
 * client id and secret to request an access token for provisioning plans.
 *
 * Plans available for provisioning include free, personal, premium and professional
 *
 * A host has options to either provision(Create == WHMCS equivalent functional term)
 * or Cancel(Terminate == WHMCS equivalent functional term) from the WHMCS client area.
 *
 * Host setup for custom fields is currently required in order to use the module.
 *
 */

/**
 * Jetpack Meta Data for WHMCS module.
 * @return array
 */
function jetpack_MetaData()
{
    return array(
        'DisplayName' => 'Jetpack by Automattic',
        'Description' => 'Use this module to provision Jetpack plans with your Jetpack hosting partner account',
        'APIVersion' => '1.1',
        'RequiresServer' => false,
    );
}


/**
 * Basic configuration options required for a partner to get
 * a Jetpack plan provisioned. Currently a partner client id
 * and secret are the only host partner options needed to get
 * an access token to provision a Jetpack plan
 * @return array
 */
function jetpack_ConfigOptions()
{
    return array(
        'Jetpack Partner Client ID' => array(
            'Type' => 'text',
            'Size' => '256',
        ),
        'Jetpack Partner Client Secret' => array(
            'Type' => 'text',
            'Size' => '256',
        )
    );
}


/**
 * Equivalent to /provision. Create a Jetpack plan using
 * a Jetpack Hosting partner account.
 *
 *
 * @param array $params
 * @return string 'success'
 * @throws Exception

 */
function jetpack_CreateAccount(array $params)
{
    try {
        validate_required_fields($params);

        $access_token = get_access_token($params);
        $provisioning_url = "https://public-api.wordpress.com/rest/v1.3/jpphp/provision";
        $request_data = array (
            'plan' => strtolower($params['customfields']['Plan']),
            'siteurl' => $params['customfields']['Site URL'],
            'local_user' => $params['customfields']['Local User'],
            'force_register' => true,
        );

        $response = make_api_request($provisioning_url, $access_token, $request_data);
        if ($response->success && $response->success == true) {
            if ($response->next_url) {
                save_provisioning_details($response->next_url, $params);
            } elseif (!$response->next_url && $response->auth_required) {
                save_provisioning_details($response->next_url, $params, true);
            }

            return 'success';
        }
    } catch (Exception $e) {
        logModuleCall('jetpack', __FUNCTION__, $params, $e->getMessage(), $e->getMessage());
        return $e->getMessage();
    }
}

/**
 * Equivalent to partner/cancel. Cancel a Jetpack plan using
 * using a Jetpack Hosting partner account.
 *
 * @param array $params
 * @return string
 * @throws Exception
 */
function jetpack_TerminateAccount(array $params)
{
    try {
        validate_required_fields($params);

        $access_token = get_access_token($params);
        $clean_url = str_replace('/', '::', $params['customfields']['Site URL']);
        $url = 'https://public-api.wordpress.com/rest/v1.3/jpphp/' . $clean_url . '/partner-cancel';
        $response = make_api_request($url, $access_token);
        if ($response->success == true) {
            return 'success';
        }
    } catch (Exception $e) {
        logModuleCall('jetpack', __FUNCTION__, $params, $e->getMessage(), $e->getMessage());
        return $e->getMessage();
    }
}

/**
 * Get a Jetpack partner access token using the client_id
 * and client secret stored when the product was created in
 * the WHMCS product settings.
 *
 *
 * @param $params
 * @return mixed
 * @throws Exception
 */
function get_access_token($params)
{

    $oauthURL = "https://public-api.wordpress.com/oauth2/token";

    $credentials = array (
        'client_id' => $params['configoption1'],
        'client_secret' => $params['configoption2'],
        'grant_type' => 'client_credentials',
        'scope' => 'jetpack-partner'
    );

    $response = make_api_request($oauthURL, null, $credentials);
    if (isset($response->access_token)) {
        return $response->access_token;
    } else {
        throw new Exception('There was an issue authorizing your partner account for provisioning. Please contact
        us for assistance');
    }
}


/**
 * Make an API request for authenticating and provisioning or
 * cancelling a Jetpack plan
 *
 * @param $url
 * @param $data
 * @param string $auth
 * @return mixed
 * @throws Exception
 */
function make_api_request($url, $auth = '', $data = [])
{
    if (isset($auth)) {
        $auth = "Authorization: Bearer " . $auth;
    }

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_HTTPHEADER => array($auth),
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_CUSTOMREQUEST => "POST"
    ));

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($http_code >= 400) {
        logModuleCall('jetpack', __FUNCTION__, $url, $data, $response);
        throw new Exception('Something went wrong while provisioning. Please temporarily enable Module logging 
        in the Utilities tab and try again for more information');
    } elseif (curl_error($curl)) {
        throw new Exception('Unable to connect: ' . curl_errno($curl) . ' - ' . curl_error($curl));
    } elseif (empty($response)) {
        throw new Exception('Empty response');
    }

    curl_close($curl);
    return json_decode($response);
}

/**
 * Save the next_url for Jetpack activation/setup to the
 * order for the client
 *
 * @param $url
 * @param $orderId
 */
function save_provisioning_details($url, $params, $pending = false)
{
    $jetpack_next_url_field = Capsule::table('tblcustomfields')
        ->where(array('fieldname' => 'jetpack_provisioning_details', 'type' => 'product'))->first();

    $details = '';
    if ($url) {
        $details = 'URL to Activate Jetpack: ' . $url;
    } elseif ($pending) {
        $details = 'The domain did not appear to resolve when provisioning was attempted however a Jetpack plan is 
        waiting for ' . $params['customfields']['Site URL'] . '. Once DNS resolves please connect the site via 
        the Jetpack Banner in the sites dashboard';
    }
    Capsule::table('tblcustomfieldsvalues')->where(array('fieldid' => $jetpack_next_url_field->id))->update(array(
         'relid' => $params['model']['orderId'], 'value' => $details));
}

/**
 * Validate that the module was correctly set up when the product was
 * created by the WHMCS user and that the required Fields/Options for
 * being able to provision a Jetpack plan are present. Fields validated are
 *  - Allowed Plans from Plan Custom Field
 *  - Required Custom Fields
 *  - Required Config Options
 *
 * @param array $params
 * @return bool
 * @throws Exception
 */
function validate_required_fields(array $params)
{
    $allowed_plans = array('free', 'personal', 'premium', 'professional');
    $required_custom_fields = array('Plan', 'Site URL', 'Local User');

    foreach ($required_custom_fields as $field) {
        if (!isset($params['customfields'][$field])) {
            throw new Exception('The module does not appear to be setup correctly. The required custom field '
                . $field . ' was not setup when the product was created.
				Please see the module documentation for more information');
        }
    }


    $jetpack_next_url_field = Capsule::table('tblcustomfields')
        ->where(array('fieldname' => 'jetpack_provisioning_details', 'type' => 'product'))->first();

    if (!$jetpack_next_url_field) {
        throw new Exception('The module does not appear to be setup correctly. 
        The jetpack_provisioning_details field is missing');
    }

    if (!in_array(strtolower($params['customfields']['Plan']), $allowed_plans)) {
        throw new Exception('The module does not appear to be setup correctly. ' .
            $params['customfields']['Plan'] . ' is not an allowed plan');
    }

    if (!isset($params['configoption1']) || !isset($params['configoption2'])) {
        throw new Exception('Your credentials for provisioning are not complete. Please see the module documentation
        for more information');
    }

    return true;
}
