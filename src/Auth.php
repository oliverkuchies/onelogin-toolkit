<?php
/**
 * This file is part of php-saml.
 *
 * (c) OneLogin Inc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package OneLogin
 * @author  OneLogin Inc <saml-info@onelogin.com>
 * @author  Oliver Kucharzewski <oliver@olidev.com.au> (2022)
 * @license MIT https://github.com/onelogin/php-saml/blob/master/LICENSE
 * @link    https://github.com/onelogin/php-saml
 * @link    https://github.com/oliverkuchies/onelogin-toolkit
 */

namespace OneLoginToolkit;

use App\Models\OneLoginSite;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RobRichards\XMLSecLibs\XMLSecurityKey;

use Exception;

/**
 * Main class of OneLogin's PHP Toolkit
 */
class Auth
{
    /**
     * Settings data.
     *
     * @var Settings
     */
    private Settings $_settings;

    /**
     * User attributes data.
     *
     * @var array
     */
    private $_attributes = array();

    /**
     * User attributes data with FriendlyName index.
     *
     * @var array
     */
    private array $_attributesWithFriendlyName = array();

    /**
     * NameID
     *
     * @var string | null
     */
    private ?string $_nameid;

    /**
     * NameID Format
     *
     * @var string | null
     */
    private ?string $_nameidFormat;

    /**
     * NameID NameQualifier
     *
     * @var string | null
     */
    private ?string $_nameidNameQualifier;

    /**
     * NameID SP NameQualifier
     *
     * @var string | null
     */
    private ?string $_nameidSPNameQualifier;

    /**
     * If user is authenticated.
     *
     * @var bool
     */
    private bool $_authenticated = false;


    /**
     * SessionIndex. When the user is logged, this stored it
     * from the AuthnStatement of the SAML Response
     *
     * @var string | null
     */
    private ?string $_sessionIndex;

    /**
     * SessionNotOnOrAfter. When the user is logged, this stored it
     * from the AuthnStatement of the SAML Response
     *
     * @var int|null
     */
    private ?int $_sessionExpiration;

    /**
     * The ID of the last message processed
     *
     * @var string | null
     */
    private ?string $_lastMessageId;

    /**
     * The ID of the last assertion processed
     *
     * @var string | null
     */
    private ?string $_lastAssertionId;

    /**
     * The NotOnOrAfter value of the valid SubjectConfirmationData
     * node (if any) of the last assertion processed
     *
     * @var int
     */
    private int $_lastAssertionNotOnOrAfter;

    /**
     * If any error.
     *
     * @var array
     */
    private array $_errors = array();

    /**
     * Last error object.
     *
     * @var Error|null
     */
    private ?Error $_lastErrorException;

    /**
     * Last error.
     *
     * @var string|null
     */
    private ?string $_lastError;

    /**
     * Last AuthNRequest ID or LogoutRequest ID generated by this Service Provider
     *
     * @var string
     */
    private string $_lastRequestID;

    /**
     * The most recently-constructed/processed XML SAML request
     * (AuthNRequest, LogoutRequest)
     *
     * @var string
     */
    private string $_lastRequest;

    /**
     * The most recently-constructed/processed XML SAML response
     * (SAMLResponse, LogoutResponse). If the SAMLResponse was
     * encrypted, by default tries to return the decrypted XML
     *
     * @var string|\DomDocument|null
     */
    private string|null|\DomDocument $_lastResponse;

    /**
     * Initializes the SP SAML instance.
     *
     * @param string|null $site_name
     *
     * @throws Exception
     * @throws Error
     */
    public function __construct(?string $site_name)
    {
        $config = $this->generateMultiDimensionalConfig();
        if (isset($config[$site_name])) {
            $this->_settings = new Settings($config[$site_name]);
        } else {
            throw new Exception("Invalid site $site_name - please check onelogin_site table and make sure it exists.\n
            If you wish to create a new site, please use the php artisan onelogin:create command.
            ");
        }
    }

    /**
     * load certificate from given folder
     * @param $certificate
     * @return string
     */
    private function loadCertificate($certificate) {
        try {
            if ($storage = Storage::get($certificate)) {
                return $storage;
            }
        } catch (Exception $e) {
            throw new Exception ("Could not find given file in storage. File : $certificate");
        }

        throw new Exception ("Could not find given file in storage. File : $certificate");
    }

    /**
     * Generate multi dimensional config for all apps available.
     *
     * @return array
     */
    public function generateMultiDimensionalConfig(): array
    {
        $onelogin_sites = OneLoginSite::all();
        $config = [];

        foreach ($onelogin_sites as $onelogin_site) {
            $config[$onelogin_site->site_name] = [
                "sp" => [
                    "entityId" => $onelogin_site->sp_entity_id,
                    "assertionConsumerService" => [
                        "url" => $onelogin_site->sp_acs_url,
                        "binding" => $onelogin_site->sp_acs_binding
                    ],
                    "singleLogoutService" => [
                        "url" => $onelogin_site->sp_slo_url,
                        "binding" => $onelogin_site->sp_slo_binding
                    ],
                    "NameIDFormat" => $onelogin_site->sp_name_id_format,
                    "x509cert" => $this->loadCertificate($onelogin_site->sp_x509cert),
                    "privateKey" => "",
                ],
                "idp" => [
                    "entityId" => $onelogin_site->ip_entity_id,
                    "singleSignOnService" => [
                        "url" => $onelogin_site->ip_acs_url,
                        "binding" => $onelogin_site->ip_acs_binding
                    ],
                    "singleLogoutService" => [
                        "url" => $onelogin_site->ip_slo_url,
                        "responseUrl" => "",
                        "binding" => $onelogin_site->ip_slo_binding
                    ],
                    "NameIDFormat" => $onelogin_site->ip_name_id_format,
                    "x509cert" => $this->loadCertificate($onelogin_site->ip_x509cert),
                ]
            ];
        }

        return $config;

    }

    /**
     * Returns the settings info
     *
     * @return Settings The settings data.
     */
    public function getSettings(): Settings
    {
        return $this->_settings;
    }

    /**
     * Set the strict mode active/disable
     *
     * @param bool $value Strict parameter
     *
     * @throws Error
     */
    public function setStrict(bool $value)
    {
        if (!is_bool($value)) {
            throw new Error(
                'Invalid value passed to setStrict()',
                Error::SETTINGS_INVALID_SYNTAX
            );
        }

        $this->_settings->setStrict($value);
    }

    /**
     * Set schemas path
     *
     * @param string $path
     * @return $this
     */
    public function setSchemasPath(string $path)
    {
        $this->_paths['schemas'] = $path;
    }

    private function createResponse($samlResponse) {
	$response = new Response($this->_settings, $samlResponse);
	$this->_lastResponse = $response->getXMLDocument();

	return $response;
    }

    /**
     * Process the SAML Response sent by the IdP.
     *
     * @param string|null $requestId The ID of the AuthNRequest sent by this SP to the IdP
     *
     * @throws Error
     * @throws ValidationError
     */
    public function processResponse($requestId = null, $samlResponse = null, $attempts = 0)
    {
        $this->_errors = array();
        $this->_lastError = $this->_lastErrorException = null;
        $this->_errorReason = null;

        if (isset($samlResponse)) {
            // AuthnResponse -- HTTP_POST Binding
            $response = $this->createResponse($samlResponse);

            if ($response->isValid($requestId)) {
                $this->_attributes = $response->getAttributes();
                $this->_attributesWithFriendlyName = $response->getAttributesWithFriendlyName();
                $this->_nameid = $response->getNameId();
                $this->_nameidFormat = $response->getNameIdFormat();
                $this->_nameidNameQualifier = $response->getNameIdNameQualifier();
                $this->_nameidSPNameQualifier = $response->getNameIdSPNameQualifier();
                $this->_authenticated = true;
                $this->_sessionIndex = $response->getSessionIndex();
                $this->_sessionExpiration = $response->getSessionNotOnOrAfter();
                $this->_lastMessageId = $response->getId();
                $this->_lastAssertionId = $response->getAssertionId();
                $this->_lastAssertionNotOnOrAfter = $response->getAssertionNotOnOrAfter();
            } else {
		$this->_errors[] = 'Invalid Request ID: ' . $response->getError();
            }
        } else {
            $this->_errors[] = 'invalid_binding';
            throw new Error(
                'SAML Response not found, Only supported HTTP_POST Binding',
                Error::SAML_RESPONSE_NOT_FOUND
            );
        }

    }

    /**
     * Process the SAML Logout Response / Logout Request sent by the IdP.
     *
     * @param bool        $keepLocalSession             When false will destroy the local session, otherwise will keep it
     * @param string|null $requestId                    The ID of the LogoutRequest sent by this SP to the IdP
     * @param bool        $retrieveParametersFromServer True if we want to use parameters from $_SERVER to validate the signature
     * @param callable    $cbDeleteSession              Callback to be executed to delete session
     * @param bool        $stay                         True if we want to stay (returns the url string) False to redirect
     *
     * @return string|null
     *
     * @throws Error
     */
    public function processSLO($keepLocalSession = false, $requestId = null, $retrieveParametersFromServer = false, $cbDeleteSession = null, $stay = false): ?string
    {
        $this->_errors = array();
        $this->_lastError = $this->_lastErrorException = null;
        if (isset($_GET['SAMLResponse'])) {
            $logoutResponse = new LogoutResponse($this->_settings, $_GET['SAMLResponse']);
            $this->_lastResponse = $logoutResponse->getXML();
            if (!$logoutResponse->isValid($requestId, $retrieveParametersFromServer)) {
                $this->_errors[] = 'invalid_logout_response';
                $this->_lastErrorException = $logoutResponse->getErrorException();
                $this->_lastError = $logoutResponse->getError();

            } else if ($logoutResponse->getStatus() !== Constants::STATUS_SUCCESS) {
                $this->_errors[] = 'logout_not_success';
            } else {
                $this->_lastMessageId = $logoutResponse->id;
                if (!$keepLocalSession) {
                    if ($cbDeleteSession === null) {
                        Utils::deleteLocalSession();
                    } else {
                        call_user_func($cbDeleteSession);
                    }
                }
            }
        } else if (isset($_GET['SAMLRequest'])) {
            $logoutRequest = new LogoutRequest($this->_settings, $_GET['SAMLRequest']);
            $this->_lastRequest = $logoutRequest->getXML();
            if (!$logoutRequest->isValid($retrieveParametersFromServer)) {
                $this->_errors[] = 'invalid_logout_request';
                $this->_lastErrorException = $logoutRequest->getErrorException();
                $this->_lastError = $logoutRequest->getError();
            } else {
                if (!$keepLocalSession) {
                    if ($cbDeleteSession === null) {
                        Utils::deleteLocalSession();
                    } else {
                        call_user_func($cbDeleteSession);
                    }
                }
                $inResponseTo = $logoutRequest->id;
                $this->_lastMessageId = $logoutRequest->id;
                $responseBuilder = new LogoutResponse($this->_settings);
                $responseBuilder->build($inResponseTo);
                $this->_lastResponse = $responseBuilder->getXML();

                $logoutResponse = $responseBuilder->getResponse();

                $parameters = array('SAMLResponse' => $logoutResponse);
                if (isset($_GET['RelayState'])) {
                    $parameters['RelayState'] = $_GET['RelayState'];
                }

                $security = $this->_settings->getSecurityData();
                if (isset($security['logoutResponseSigned']) && $security['logoutResponseSigned']) {
                    $signature = $this->buildResponseSignature($logoutResponse, isset($parameters['RelayState'])? $parameters['RelayState']: null, $security['signatureAlgorithm']);
                    $parameters['SigAlg'] = $security['signatureAlgorithm'];
                    $parameters['Signature'] = $signature;
                }

                return $this->redirectTo($this->getSLOResponseUrl(), $parameters, $stay);
            }
        } else {
            $this->_errors[] = 'invalid_binding';
            throw new Error(
                'SAML LogoutRequest/LogoutResponse not found. Only supported HTTP_REDIRECT Binding',
                Error::SAML_LOGOUTMESSAGE_NOT_FOUND
            );
        }
    }

    /**
     * Redirects the user to the url past by parameter
     * or to the url that we defined in our SSO Request.
     *
     * @param string $url        The target URL to redirect the user.
     * @param array  $parameters Extra parameters to be passed as part of the url
     * @param bool   $stay       True if we want to stay (returns the url string) False to redirect
     *
     * @return string|null
     */
    public function redirectTo($url = '', array $parameters = array(), $stay = false): ?string
    {
        assert(is_string($url));

        if (empty($url) && isset($_REQUEST['RelayState'])) {
            $url = $_REQUEST['RelayState'];
        }

        return Utils::redirect($url, $parameters, $stay);
    }

    /**
     * Checks if the user is authenticated or not.
     *
     * @return bool  True if the user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->_authenticated;
    }

    /**
     * Returns the set of SAML attributes.
     *
     * @return array  Attributes of the user.
     */
    public function getAttributes(): array
    {
        return $this->_attributes;
    }


    /**
     * Returns the set of SAML attributes indexed by FriendlyName
     *
     * @return array  Attributes of the user.
     */
    public function getAttributesWithFriendlyName(): array
    {
        return $this->_attributesWithFriendlyName;
    }

    /**
     * Returns the nameID
     *
     * @return string  The nameID of the assertion
     */
    public function getNameId(): string
    {
        return $this->_nameid;
    }

    /**
     * Returns the nameID Format
     *
     * @return string  The nameID Format of the assertion
     */
    public function getNameIdFormat(): string
    {
        return $this->_nameidFormat;
    }

    /**
     * Returns the nameID NameQualifier
     *
     * @return string|null The nameID NameQualifier of the assertion
     */
    public function getNameIdNameQualifier(): string | null
    {
        return $this->_nameidNameQualifier;
    }

    /**
     * Returns the nameID SP NameQualifier
     *
     * @return string|null The nameID SP NameQualifier of the assertion
     */
    public function getNameIdSPNameQualifier(): string | null
    {
        return $this->_nameidSPNameQualifier;
    }

    /**
     * Returns the SessionIndex
     *
     * @return string|null  The SessionIndex of the assertion
     */
    public function getSessionIndex(): ?string
    {
        return $this->_sessionIndex;
    }

    /**
     * Returns the SessionNotOnOrAfter
     *
     * @return int|null  The SessionNotOnOrAfter of the assertion
     */
    public function getSessionExpiration(): ?int
    {
        return $this->_sessionExpiration;
    }

    /**
     * Returns if there were any error
     *
     * @return array  Errors
     */
    public function getErrors(): array
    {
        return $this->_errors;
    }

    /**
     * Returns the reason for the last error
     *
     * @return string|null  Error reason
     */
    public function getLastErrorReason(): ?string
    {
        return $this->_lastError;
    }


    /**
     * Returns the last error
     *
     * @return Exception|null Error
     */
    public function getLastErrorException(): Error|Exception|null
    {
        return $this->_lastErrorException;
    }

    /**
     * Returns the requested SAML attribute
     *
     * @param string $name The requested attribute of the user.
     *
     * @return array|null Requested SAML attribute ($name).
     */
    public function getAttribute(string $name)
    {
        assert(is_string($name));

        $value = null;
        if (isset($this->_attributes[$name])) {
            return $this->_attributes[$name];
        }
        return $value;
    }

    /**
     * Returns the requested SAML attribute indexed by FriendlyName
     *
     * @param string $friendlyName The requested attribute of the user.
     *
     * @return array|null Requested SAML attribute ($friendlyName).
     */
    public function getAttributeWithFriendlyName($friendlyName): ?array
    {
        assert(is_string($friendlyName));
        $value = null;
        if (isset($this->_attributesWithFriendlyName[$friendlyName])) {
            return $this->_attributesWithFriendlyName[$friendlyName];
        }
        return $value;
    }

    /**
     * Initiates the SSO process.
     *
     * @param string|null $returnTo        The target URL the user should be returned to after login.
     * @param array       $parameters      Extra parameters to be added to the GET
     * @param bool $forceAuthn      When true the AuthNRequest will set the ForceAuthn='true'
     * @param bool $isPassive       When true the AuthNRequest will set the Ispassive='true'
     * @param bool $stay            True if we want to stay (returns the url string) False to redirect
     * @param bool $setNameIdPolicy When true the AuthNRequest will set a nameIdPolicy element
     * @param string|null $nameIdValueReq  Indicates to the IdP the subject that should be authenticated
     *
     * @return string|null If $stay is True, it return a string with the SLO URL + LogoutRequest + parameters
     *
     * @throws Error
     */
    public function login(string $returnTo = null, array $parameters = array(), bool $forceAuthn = false, bool $isPassive = false, bool $stay = false, bool $setNameIdPolicy = true, string $nameIdValueReq = null): ?string
    {
        $authnRequest = $this->buildAuthnRequest($this->_settings, $forceAuthn, $isPassive, $setNameIdPolicy, $nameIdValueReq);

        $this->_lastRequest = $authnRequest->getXML();
        $this->_lastRequestID = $authnRequest->getId();

        $samlRequest = $authnRequest->getRequest();
        $parameters['SAMLRequest'] = $samlRequest;

        if (!empty($returnTo)) {
            $parameters['RelayState'] = $returnTo;
        } else {
            if (!str_contains(url()->previous(), "saml")) {
                $parameters['RelayState'] = URL::previous();
            } else {
                $parameters['RelayState'] = '';
            }

        }

        $security = $this->_settings->getSecurityData();
        if (isset($security['authnRequestsSigned']) && $security['authnRequestsSigned']) {
            $signature = $this->buildRequestSignature($samlRequest, $parameters['RelayState'], $security['signatureAlgorithm']);
            $parameters['SigAlg'] = $security['signatureAlgorithm'];
            $parameters['Signature'] = $signature;
        }

        return $this->redirectTo($this->getSSOurl(), $parameters, $stay);
    }

    /**
     * Initiates the SLO process.
     *
     * @param string|null $returnTo The target URL the user should be returned to after logout.
     * @return string|null If $stay is True, it return a string with the SLO URL + LogoutRequest + parameters
     *
     * @throws Error
     */
    public function logout($site_name, string $returnTo = null): ?string
    {
        $sloUrl = $this->getSLOurl();
        if (empty($sloUrl)) {
            throw new Error(
                'The IdP does not support Single Log Out',
                Error::SAML_SINGLE_LOGOUT_NOT_SUPPORTED
            );
        }

        if (empty($nameId) && !empty($this->_nameid)) {
            $nameId = $this->_nameid;
        }
        if (empty($nameIdFormat) && !empty($this->_nameidFormat)) {
            $nameIdFormat = $this->_nameidFormat;
        }

        $logoutRequest = new LogoutRequest($site_name, $this->_settings);

        $this->_lastRequest = $logoutRequest->getXML();
        $this->_lastRequestID = $logoutRequest->id;

        $samlRequest = $logoutRequest->getRequest();

        $parameters['SAMLRequest'] = $samlRequest;
        if (!empty($returnTo)) {
            $parameters['RelayState'] = $returnTo;
        } else {
            $parameters['RelayState'] = Utils::getSelfURLNoQuery();
        }

        $security = $this->_settings->getSecurityData();
        if (isset($security['logoutRequestSigned']) && $security['logoutRequestSigned']) {
            $signature = $this->buildRequestSignature($samlRequest, $parameters['RelayState'], $security['signatureAlgorithm']);
            $parameters['SigAlg'] = $security['signatureAlgorithm'];
            $parameters['Signature'] = $signature;
        }

        return $this->redirectTo($sloUrl, $parameters);
    }

   /**
     * Gets the IdP SSO url.
     *
     * @return string The url of the IdP Single Sign On Service
     */
    public function getSSOurl(): string
    {
        return $this->_settings->getIdPSSOUrl();
    }

    /**
     * Gets the IdP SLO url.
     *
     * @return string|null The url of the IdP Single Logout Service
     */
    public function getSLOurl(): ?string
    {
        return $this->_settings->getIdPSLOUrl();
    }

    /**
     * Gets the IdP SLO response url.
     *
     * @return string|null The response url of the IdP Single Logout Service
     */
    public function getSLOResponseUrl(): ?string
    {
        return $this->_settings->getIdPSLOResponseUrl();
    }


    /**
     * Gets the ID of the last AuthNRequest or LogoutRequest generated by the Service Provider.
     *
     * @return string The ID of the Request SAML message.
     */
    public function getLastRequestID(): string
    {
        return $this->_lastRequestID;
    }

    /**
     * Creates an AuthnRequest
     *
     * @param Settings $settings        Setting data
     * @param bool     $forceAuthn      When true the AuthNRequest will set the ForceAuthn='true'
     * @param bool     $isPassive       When true the AuthNRequest will set the Ispassive='true'
     * @param bool     $setNameIdPolicy When true the AuthNRequest will set a nameIdPolicy element
     * @param string   $nameIdValueReq  Indicates to the IdP the subject that should be authenticated
     *
     * @return AuthnRequest The AuthnRequest object
     */
    public function buildAuthnRequest($settings, $forceAuthn, $isPassive, $setNameIdPolicy, $nameIdValueReq = null): AuthnRequest
    {
        return new AuthnRequest($settings, $forceAuthn, $isPassive, $setNameIdPolicy, $nameIdValueReq);
    }

    /**
     * Generates the Signature for a SAML Request
     *
     * @param string $samlRequest   The SAML Request
     * @param string $relayState    The RelayState
     * @param string $signAlgorithm Signature algorithm method
     *
     * @return string A base64 encoded signature
     *
     * @throws Exception
     * @throws Error
     */
    public function buildRequestSignature($samlRequest, $relayState, $signAlgorithm = XMLSecurityKey::RSA_SHA256): string
    {
        return $this->buildMessageSignature($samlRequest, $relayState, $signAlgorithm, "SAMLRequest");
    }

    /**
     * Generates the Signature for a SAML Response
     *
     * @param string $samlResponse  The SAML Response
     * @param string $relayState    The RelayState
     * @param string $signAlgorithm Signature algorithm method
     *
     * @return string A base64 encoded signature
     *
     * @throws Exception
     * @throws Error
     */
    public function buildResponseSignature($samlResponse, $relayState, $signAlgorithm = XMLSecurityKey::RSA_SHA256): string
    {
        return $this->buildMessageSignature($samlResponse, $relayState, $signAlgorithm, "SAMLResponse");
    }

    /**
     * Generates the Signature for a SAML Message
     *
     * @param string $samlMessage   The SAML Message
     * @param string $relayState    The RelayState
     * @param string $signAlgorithm Signature algorithm method
     * @param string $type          "SAMLRequest" or "SAMLResponse"
     *
     * @return string A base64 encoded signature
     *
     * @throws Exception
     * @throws Error
     */
    private function buildMessageSignature($samlMessage, $relayState, $signAlgorithm = XMLSecurityKey::RSA_SHA256, $type = "SAMLRequest"): string
    {
        $key = $this->_settings->getSPkey();
        if (empty($key)) {
            if ($type == "SAMLRequest") {
                $errorMsg = "Trying to sign the SAML Request but can't load the SP private key";
            } else {
                $errorMsg = "Trying to sign the SAML Response but can't load the SP private key";
            }

            throw new Error($errorMsg, Error::PRIVATE_KEY_NOT_FOUND);
        }

        $objKey = new XMLSecurityKey($signAlgorithm, array('type' => 'private'));
        $objKey->loadKey($key, false);

        $security = $this->_settings->getSecurityData();
        if ($security['lowercaseUrlencoding']) {
            $msg = $type.'='.rawurlencode($samlMessage);
            if (isset($relayState)) {
                $msg .= '&RelayState='.rawurlencode($relayState);
            }
            $msg .= '&SigAlg=' . rawurlencode($signAlgorithm);
        } else {
            $msg = $type.'='.urlencode($samlMessage);
            if (isset($relayState)) {
                $msg .= '&RelayState='.urlencode($relayState);
            }
            $msg .= '&SigAlg=' . urlencode($signAlgorithm);
        }
        $signature = $objKey->signData($msg);
        return base64_encode($signature);
    }

    /**
     * @return string The ID of the last message processed
     */
    public function getLastMessageId(): string
    {
        return $this->_lastMessageId;
    }

    /**
     * @return string The ID of the last assertion processed
     */
    public function getLastAssertionId(): string
    {
        return $this->_lastAssertionId;
    }

    /**
     * @return int The NotOnOrAfter value of the valid
     *         SubjectConfirmationData node (if any)
     *         of the last assertion processed
     */
    public function getLastAssertionNotOnOrAfter(): int
    {
        return $this->_lastAssertionNotOnOrAfter;
    }

    /**
     * Returns the most recently-constructed/processed
     * XML SAML request (AuthNRequest, LogoutRequest)
     *
     * @return string|null The Request XML
     */
    public function getLastRequestXML(): ?string
    {
        return $this->_lastRequest;
    }

    /**
     * Returns the most recently-constructed/processed
     * XML SAML response (SAMLResponse, LogoutResponse).
     * If the SAMLResponse was encrypted, by default tries
     * to return the decrypted XML.
     *
     * @return string|null The Response XML
     */
    public function getLastResponseXML(): \DomDocument|string|null
    {
        $response = null;
        if (isset($this->_lastResponse)) {
            if (is_string($this->_lastResponse)) {
                $response = $this->_lastResponse;
            } else {
                $response = $this->_lastResponse->saveXML();
            }
        }

        return $response;
    }
}