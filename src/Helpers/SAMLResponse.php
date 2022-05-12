<?php


namespace OneLoginToolkit\Helpers;


use OneLoginToolkit\Auth;

/**
 * Class SAMLResponse
 * @package OneLoginToolkit\Helpers
 */
class SAMLResponse
{
    protected Auth $auth_request;
    protected string $site_name;
    protected string $relay_state;
    protected string $saml_response;
    protected ?string $auth_request_id;
    protected static SAMLResponse $instance;

    public function __construct(string $site_name, string | null $relay_state, string $saml_response) {
	if (!$relay_state) {
	    $relay_state = config('onelogin.baseurl');
	}

	$this->setSiteName($site_name);
	$this->setRelayState($relay_state);
	$this->setSamlResponse($saml_response);
    }

    /**
     * @return string
     */
    public function getSiteName() : string
    {
	return $this->site_name;
    }

    /**
     * @return string
     */
    public function getRelayState(): string
    {
	return $this->relay_state;
    }

    /**
     * @param string $relay_state
     */
    public function setRelayState(string $relay_state): void
    {
	$this->relay_state = $relay_state;
    }

    /**
     * @param string $site_name
     */
    public function setSiteName(string $site_name): void
    {
	$this->site_name = $site_name;
    }

    /**
     * @param string $saml_response
     */
    public function setSamlResponse(string $saml_response): void
    {
	$this->saml_response = $saml_response;
    }

    /**
     * @return string
     */
    public function getSamlResponse(): string
    {
	return $this->saml_response;
    }

    /**
     * @param string|null $auth_request_id
     */
    public function setAuthRequestID(?string $auth_request_id): void
    {
	$this->auth_request_id = $auth_request_id;
    }

    /**
     * @return string
     */
    public function getAuthRequestID(): string
    {
	return $this->auth_request_id;
    }

    /**
     * @return Auth
     */
    public function getAuthRequest(): Auth
    {
	return $this->auth_request;
    }

    /**
     * @param Auth $auth_request
     */
    public function setAuthRequest(Auth $auth_request): void
    {
	$this->auth_request = $auth_request;
    }

    public function createAuthRequest() {
	$this->setAuthRequest(new Auth($this->getSiteName()));
    }

    /**
     * Process Authentication Response
     * @throws \OneLoginToolkit\Error
     * @throws \OneLoginToolkit\ValidationError
     */
    public function processResponse() {
	$auth_request_id = SAMLAuth::getAuthRequestID($this->getSiteName());

	if ($auth_request_id != null) {
	    $this->setAuthRequestID($auth_request_id);
	    $this->getAuthRequest()->processResponse(
		$this->getAuthRequestID(),
		$this->getSamlResponse()
	    );
	} else {

	    while (!SAMLAuth::getAuthRequestID($this->getSiteName())) {
		if (SAMLAuth::getRetryCount() >= 4) {
		    throw new \Exception("Failed to retrieve " . SAMLAuth::AUTH_REQUEST_ID);
		}

		SAMLAuth::retryAuthentication(
		    new self(
			$this->getSiteName(),
			$this->getRelayState(),
			$this->getSamlResponse()
		    )
		);
	    }
	}
    }


    /**
     * Save response data in a meaningful session
     */
    public function save() {
	$site_name = $this->getSiteName();
	$auth = $this->getAuthRequest();
	// Save all user session data associated with their request
	SAMLAuth::setSAMLUserData($site_name, $auth->getAttributes());
	SAMLAuth::setSAMLNameID($site_name, $auth->getNameId());
	SAMLAuth::setSAMLNameIDFormat($site_name, $auth->getNameIdFormat());
	SAMLAuth::setSAMLNameIDQualifier($site_name, $auth->getNameIdNameQualifier());
	SAMLAuth::setSAMLNameIdSpNameQualifier($site_name, $auth->getNameIdSpNameQualifier());
	SAMLAuth::setSAMLSessionIndex($site_name, $auth->getSessionIndex());
    }

    /**
     * @throws \OneLoginToolkit\Error
     * @throws \OneLoginToolkit\ValidationError
     */
    public function checkAuthErrors() {
	$auth_request = $this->getAuthRequest();

	if (!$auth_request->isAuthenticated()) {
	    $error_list = '';
	    if ($errors = $auth_request->getErrors()) {
		foreach ($errors as $error) {
		    $error_list .= $error . '\n';
		}

		/**
		 * Retry until successful
		 */
		while (!$auth_request->isAuthenticated()) {
		    if (SAMLAuth::getRetryCount() >= 4) {
			throw new \Exception($error_list);
		    }

		    SAMLAuth::retryAuthentication(
			new self(
			    $this->getSiteName(),
			    $this->getRelayState(),
			    $this->getSamlResponse()
			)
		    );
		}

	    }
	}
    }
}