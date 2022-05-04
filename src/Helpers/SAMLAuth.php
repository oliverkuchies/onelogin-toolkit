<?php


namespace OneLoginToolkit\Helpers;

use OneLoginToolkit\Auth;
use OneLoginToolkit\Utils;
use Illuminate\Http\Request;

/**
 * This file is part of onelogin-toolkit.
 *
 * (c) Oliver Kucharzewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package OneLogin Toolkit
 * @author  Oliver Kucharzewski <oliver@olidev.com.au> (2022)
 * @license MIT  https://github.com/oliverkuchies/onelogin-toolkit/php-saml/blob/master/LICENSE
 * @link    https://github.com/oliverkuchies/onelogin-toolkit
 */

class SAMLAuth
{
    const SAML_USER_DATA = 'SAML_USER_DATA';
    const SAML_NAME_ID = 'SAML_NAME_ID';
    const SAML_NAME_ID_FORMAT = 'SAML_NAME_ID_FORMAT';
    const SAML_NAME_ID_QUALIFIER = 'SAML_NAME_ID_QUALIFIER';
    const SAML_NAME_ID_SP_NAME_QUALIFIER = 'SAML_NAME_ID_SP_NAME_QUALIFIER';
    const SAML_SESSION_INDEX = 'SAML_SESSION_INDEX';
    const AUTH_REQUEST_ID = 'AuthNRequestID';
    const SESSION_ID = 'SESSION_ID';

    public function __construct() {
    }

    public static function getInstance() {
        return new self();
    }

    public static function isLoggedIn() {
        if (SAMLAuth::getSAMLUserdata() && !is_null(SAMLAuth::getSAMLUserdata()) && SAMLAUTH::getSAMLUserdata() !== 'NULL') {
            return true;
        }

        return false;
    }

    /**
     * Allow a user to login to the IDP
     *
     * @param Request $request
     * @param null $site_name
     * @param null $relay_state
     * @return string
     * @throws \OneLoginToolkit\Error
     */
    public function requestLogin(Request $request, $site_name = null, $relay_state = null) {
        $auth = new Auth($site_name);

        if ($relay_state === null) {
            $relay_state = $request->get('RelayState');
        }

        $login = $auth->login($relay_state);

        if ($login) {
            SAMLAuth::setAuthRequestID($auth->getLastRequestID());
            return $login;
        } else {
            throw new \Exception('An error occured when attempting to login');
        }
    }

    /**
     * Allow a user to consume the response from the identity provider
     *
     * @param Request $request
     * @param $site_name
     * @param $callback
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \OneLoginToolkit\Error
     * @throws \OneLoginToolkit\ValidationError
     */
    public static function consumeSAMLResponse(Request $request, $site_name, $callback) {
        $auth = new Auth($site_name);

        $relay_state = $request->input('RelayState');
        $saml_response = $request->input('SAMLResponse');

        $auth_request_id = SAMLAuth::getAuthRequestID();

        $auth->processResponse($auth_request_id, $saml_response);

        if (!$auth->isAuthenticated()) {
            $error_list = '';
            if ($auth->getErrors()) {
                foreach ($auth->getErrors() as $error) {
                    $error_list .= $error . '\n';
                }

                throw new \Exception($error_list);
            }
        }

        // Save all user session data associated with their request
        SAMLAuth::setSAMLUserData($auth->getAttributes());
        SAMLAuth::setSAMLNameID($auth->getNameId());
        SAMLAuth::setSAMLNameIDFormat($auth->getNameIdFormat());
        SAMLAuth::setSAMLNameIDQualifier($auth->getNameIdNameQualifier());
        SAMLAuth::setSAMLNameIdSpNameQualifier($auth->getNameIdSpNameQualifier());
        SAMLAuth::setSAMLSessionIndex($auth->getSessionIndex());

        // Run the user's call back method after session data is saved.
        $callback();

        $self_url = Utils::getSelfURL();

        if (isset($relay_state) && $self_url != $relay_state) {
            // To avoid 'Open Redirect' attacks, before execute the
            // redirection confirm the value of $_POST['RelayState'] is a // trusted URL.
            if (SAMLAuth::isTrustedPrefix($relay_state)) {
                return redirect($relay_state);
            } else {
                throw new \Exception("OneLogin Redirect Request is not secure.");
            }
        } else {
            // Redirect user back to APP_URL.
            return redirect($self_url);
        }
    }

    /**
     * Reset all user sessions back to their original state.
     * @return string|null
     * @throws \OneLoginToolkit\Error
     */
    public function logout(Request $request, $site_name) {
        $auth = new Auth($site_name);

        $logout_attempts = 0;

        while (SAMLAuth::getSAMLUserdata() && $logout_attempts <= 3) {
            SAMLAuth::setSAMLUserData(null);
            SAMLAuth::setSAMLNameID(null);
            SAMLAuth::setSAMLNameIDFormat(null);
            SAMLAuth::setSAMLNameIDQualifier(null);
            SAMLAuth::setSAMLNameIdSpNameQualifier(null);
            SAMLAuth::setSAMLSessionIndex(null);

            $logout_attempts += 1;
        }

        if (SAMLAuth::getSAMLUserdata()) {
            throw new \Exception('Logout failed on app level.');
        }

        return $auth->logout('/');
    }

    public function metadata(Request $request, $site_name) {
        try {
            $auth = new Auth($site_name);
            $settings = $auth->getSettings();
            $metadata = $settings->getSPMetadata();
            $errors = $settings->validateMetadata($metadata);
            if (empty($errors)) {
                header('Content-Type: text/xml');
                echo $metadata;
            } else {
                throw new \Exception(
                    'Invalid SP metadata: '.implode(', ', $errors),
                    'Invalid SP metadata'
                );
            }
        } catch (\Exception $e) {
            echo "An error occured within Metadata:: " . $e->getMessage();
        }
    }

    public static function clearRequestID() {
        session()->remove(self::AUTH_REQUEST_ID);
    }

    public static function setAuthRequestID($auth_request_id) {
        session()->put(self::AUTH_REQUEST_ID, $auth_request_id);
    }

    public static function setSAMLUserData($saml_user_data) {
        session()->put(SAMLAuth::SAML_USER_DATA, $saml_user_data);
    }

    public static function setSAMLNameID($saml_name_id) {
        session()->put(SAMLAuth::SAML_NAME_ID, $saml_name_id);
    }

    public static function setSAMLNameIDFormat($saml_name_id_format) {
        session()->put(SAMLAuth::SAML_NAME_ID_FORMAT, $saml_name_id_format);
    }

    public static function setSAMLNameIDQualifier($saml_name_id_qualifier) {
        session()->put(SAMLAuth::SAML_NAME_ID_QUALIFIER, $saml_name_id_qualifier);
    }

    public static function setSAMLNameIdSpNameQualifier($saml_name_id_sp_name_qualifier) {
        session()->put(SAMLAuth::SAML_NAME_ID_SP_NAME_QUALIFIER, $saml_name_id_sp_name_qualifier);
    }

    public static function setSAMLSessionIndex($saml_session_index) {
        session()->put(SAMLAuth::SAML_SESSION_INDEX, $saml_session_index);
    }

    public static function getAuthRequestID() {
        return session()->get(self::AUTH_REQUEST_ID);
    }

    public static function getSAMLUserdata() {
        return session()->get(self::SAML_USER_DATA);
    }

    public static function getSAMLNameID() {
        return session()->get(self::SAML_NAME_ID);
    }

    public static function getSAMLNameIDFormat() {
        return session()->get(self::SAML_NAME_ID_FORMAT);
    }

    public static function getSAMLNameIDQualifier() {
        return session()->get(self::SAML_NAME_ID_QUALIFIER);
    }

    public static function getSAMLNameIDSPNameQualifier() {
        return session()->get(self::SAML_NAME_ID_SP_NAME_QUALIFIER);
    }

    public static function getSAMLSessionIndex() {
        return session()->get(self::SAML_SESSION_INDEX);
    }

    public static function isTrustedPrefix($url) {
        foreach (self::getTrustedURLPrefixes() as $trusted_prefix) {
            if (substr($url, 0, strlen($trusted_prefix)) == $trusted_prefix) {
                return true;
            }
        }

        return false;
    }

    public static function getTrustedURLPrefixes() {
        return config('onelogin.trusted_relay_prefixes');
    }
}
