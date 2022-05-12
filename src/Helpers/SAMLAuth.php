<?php


namespace OneLoginToolkit\Helpers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use OneLoginToolkit\Auth;
use OneLoginToolkit\Error;
use OneLoginToolkit\Utils;
use Illuminate\Http\Request;
use OneLoginToolkit\ValidationError;

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

    /**
     * Amount of retries
     * @var int
     */
    public static int $retry_count = 0;

    public function __construct() {
    }

    public static function getInstance() {
        return new self();
    }

    /**
     * Get amount of retries
     * @return int
     */
    public static function getRetryCount(): int
    {
        return self::$retry_count;
    }

    /**
     * Increment all the retries
     * @return void
     */
    public static function incrementRetries(): void
    {
        self::$retry_count += 1;
    }

    public static function isLoggedIn($site_name) {
        if (SAMLAuth::getSAMLUserdata($site_name) && !is_null(SAMLAuth::getSAMLUserdata($site_name)) && SAMLAUTH::getSAMLUserdata($site_name) !== 'NULL') {
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
     * @throws Error
     */
    public function requestLogin(Request $request, $site_name = null, $relay_state = null) {
        $auth = new Auth($site_name);

        if ($relay_state === null) {
            $relay_state = $request->get('RelayState');
        }

        $login = $auth->login($relay_state);

        if ($login) {
            SAMLAuth::setAuthRequestID($site_name, $auth->getLastRequestID());
            return $login;
        } else {
            throw new \Exception('An error occured when attempting to login');
        }
    }

    /**
     * @param SAMLResponse $saml_response
     * @throws Error
     * @throws ValidationError
     */
    public static function authenticate(SAMLResponse $saml_response) {
	$saml_response->createAuthRequest();
	$saml_response->processResponse();
	$saml_response->checkAuthErrors();
	$saml_response->save();
    }

    /**
     * Allow a user to consume the response from the identity provider
     *
     * @param SAMLResponse $saml_response
     * @param $callback
     * @return Application|RedirectResponse|Redirector
     * @throws Error
     * @throws ValidationError
     */
    public static function consumeSAMLResponse(SAMLResponse $saml_response, $callback): Redirector|RedirectResponse|Application
    {
        self::authenticate($saml_response);

        // Run the user's call back method after session data is saved.
        $callback();

        $self_url = Utils::getSelfURL();

        if (isset($relay_state) && $self_url != $relay_state) {
            // To avoid 'Open Redirect' attacks, before execute the
            // redirection confirm the value of $_POST['RelayState'] is a // trusted URL.
            if (SAMLAuth::isTrustedPrefix($relay_state)) {
                return redirect($relay_state);
            }

            throw new \Exception("OneLogin Redirect Request is not secure.");
        } else {
            // Redirect user back to APP_URL.
            return redirect($self_url);
        }
    }

    /**
     * Reset all user sessions back to their original state.
     * @return string|null
     * @throws Error
     */
    public function logout(Request $request, $site_name) {
        $auth = new Auth($site_name);

        $logout_attempts = 0;

        while (SAMLAuth::getSAMLUserdata($site_name) && $logout_attempts <= 3) {
            SAMLAuth::setSAMLUserData($site_name,null);
            SAMLAuth::setSAMLNameID($site_name,null);
            SAMLAuth::setSAMLNameIDFormat($site_name,null);
            SAMLAuth::setSAMLNameIDQualifier($site_name,null);
            SAMLAuth::setSAMLNameIdSpNameQualifier($site_name,null);
            SAMLAuth::setSAMLSessionIndex($site_name,null);

            $logout_attempts += 1;
        }

        if (SAMLAuth::getSAMLUserdata($site_name)) {
            throw new \Exception('Logout failed on app level.');
        }

        return $auth->logout($site_name,'/');
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

    public static function retryAuthentication(SAMLResponse $saml_response) {
	if (SAMLAuth::getRetryCount() < 4) {
	    SAMLAuth::incrementRetries();
	    SAMLAuth::authenticate(new SAMLResponse(
		$saml_response->getSiteName(),
		$saml_response->getRelayState(),
		$saml_response->getSAMLResponse()
	    ));
	}
    }

    public static function clearRequestID($site_name) {
        session()->remove($site_name . '-' . self::AUTH_REQUEST_ID);
    }

    public static function setAuthRequestID($site_name, $auth_request_id) {
        session()->put($site_name . '-' . self::AUTH_REQUEST_ID, $auth_request_id);
    }

    public static function setSAMLUserData($site_name, $saml_user_data) {
        session()->put($site_name . '-' . SAMLAuth::SAML_USER_DATA, $saml_user_data);
    }

    public static function setSAMLNameID($site_name, $saml_name_id) {
        session()->put($site_name . '-' . SAMLAuth::SAML_NAME_ID, $saml_name_id);
    }

    public static function setSAMLNameIDFormat($site_name, $saml_name_id_format) {
        session()->put($site_name . '-' . SAMLAuth::SAML_NAME_ID_FORMAT, $saml_name_id_format);
    }

    public static function setSAMLNameIDQualifier($site_name, $saml_name_id_qualifier) {
        session()->put($site_name . '-' . SAMLAuth::SAML_NAME_ID_QUALIFIER, $saml_name_id_qualifier);
    }

    public static function setSAMLNameIdSpNameQualifier($site_name, $saml_name_id_sp_name_qualifier) {
        session()->put($site_name . '-' . SAMLAuth::SAML_NAME_ID_SP_NAME_QUALIFIER, $saml_name_id_sp_name_qualifier);
    }

    public static function setSAMLSessionIndex($site_name, $saml_session_index) {
        session()->put($site_name . '-' . SAMLAuth::SAML_SESSION_INDEX, $saml_session_index);
    }

    public static function getAuthRequestID($site_name) {
        return session()->get($site_name . '-' . self::AUTH_REQUEST_ID);
    }

    public static function getSAMLUserdata($site_name) {
        return session()->get($site_name . '-' . self::SAML_USER_DATA);
    }

    public static function getSAMLNameID($site_name) {
        return session()->get($site_name . '-' . self::SAML_NAME_ID);
    }

    public static function getSAMLNameIDFormat($site_name) {
        return session()->get($site_name . '-' . self::SAML_NAME_ID_FORMAT);
    }

    public static function getSAMLNameIDQualifier($site_name) {
        return session()->get($site_name . '-' . self::SAML_NAME_ID_QUALIFIER);
    }

    public static function getSAMLNameIDSPNameQualifier($site_name) {
        return session()->get($site_name . '-' . self::SAML_NAME_ID_SP_NAME_QUALIFIER);
    }

    public static function getSAMLSessionIndex($site_name) {
        return session()->get($site_name . '-' . self::SAML_SESSION_INDEX);
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