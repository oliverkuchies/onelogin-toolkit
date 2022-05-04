<?php
use OneLoginToolkit\Constants;

if (!defined('METADATA_ROUTE')) {
    define("METADATA_ROUTE", 'auth/saml/metadata');
}
if (!defined('CONSUME_ROUTE')){
    define("CONSUME_ROUTE", 'auth/saml/consume');
}
if (!defined('LOGOUT_ROUTE')) {
    define("LOGOUT_ROUTE", 'auth/saml/logout');
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
        'entityId' => env('APP_URL') . '/' . METADATA_ROUTE,
        'assertionConsumerService' => array(
            'url' => env('APP_URL') . '/' . CONSUME_ROUTE,
            'binding' => $sso_binding,
        ),
        'singleLogoutService' => array(
            'url' => env('APP_URL') . '/' . LOGOUT_ROUTE,
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