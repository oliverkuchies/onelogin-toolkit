<?php
use OneLoginToolkit\Constants;

if (!defined('BASE_ROUTE')) {
    define("BASE_ROUTE", 'auth/saml');
}

if (!defined('METADATA_ROUTE')) {
    define("METADATA_ROUTE", '/metadata');
}
if (!defined('CONSUME_ROUTE')){
    define("CONSUME_ROUTE", '/consume');
}
if (!defined('LOGOUT_ROUTE')) {
    define("LOGOUT_ROUTE", '/logout');
}

// Enable debug mode (to print errors)
$debug = false;

$sso_binding = env('ONELOGIN_SSO_BINDING', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect');
$slo_binding = env('ONELOGIN_SLO_BINDING', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect');

$onelogin_domain = env('ONELOGIN_DOMAIN', null);
$api_key = env('ONELOGIN_API_KEY', null);

return [
    // If 'strict' is True, then the PHP Toolkit will reject unsigned
    // or unencrypted messages if it expects them signed or encrypted
    // Also will reject the messages if not strictly follow the SAML
    // standard: Destination, NameId, Conditions ... are validated too.
    'strict' => true,
    'metadata_route' => METADATA_ROUTE,
    'consume_route' => CONSUME_ROUTE,
    'logout_route' => LOGOUT_ROUTE,
    'trusted_relay_prefixes' => ['example.com'],
    'debug' => $debug,
    'api_key' => $api_key,
    'baseurl' => env('APP_URL'),
    'sp' => array(
        'entityId' => METADATA_ROUTE,
        'assertionConsumerService' => array(
            'url' => CONSUME_ROUTE,
            'binding' => $sso_binding,
        ),
        'singleLogoutService' => array(
            'url' => LOGOUT_ROUTE,
            'binding' => $slo_binding,
        ),
        'NameIDFormat' => Constants::NAMEID_EMAIL_ADDRESS,
        'x509cert' => '',
        'privateKey' => '',
    ),

    'idp' => array(
        'singleSignOnService' => array(
            'binding' => $sso_binding,
        ),
        'singleLogoutService' => array(
            'binding' => $slo_binding,
        ),
        'NameIDFormat' => Constants::NAMEID_EMAIL_ADDRESS,
    ),
];