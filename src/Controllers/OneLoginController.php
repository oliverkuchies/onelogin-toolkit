<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use OneLoginToolkit\Helpers\SAMLAuth;
use OneLoginToolkit\Helpers\SAMLResponse;

class OneLoginController extends Controller
{
    /**
     * @param Request $request
     * @param string $site_name
     * @throws \OneLoginToolkit\Error
     * @throws \OneLoginToolkit\ValidationError
     */
    public static function consume(Request $request, $site_name) {
	if (!$relay_state = $request->input('RelayState')) {
	    if (!$relay_state = $request->input('redirect')) {
	        $relay_state = env('APP_URL');
	    }
	}

	$saml_response = $request->input('SAMLResponse');

	$saml_response_obj = new SAMLResponse($site_name, $relay_state, $saml_response);
	$saml_response_obj->setRelayState($relay_state);
	$saml_response_obj->setSAMLResponse($saml_response);

        return SAMLAuth::consumeSAMLResponse($saml_response_obj, function() use ($site_name) {
            $attributes = SAMLAuth::getSAMLUserdata($site_name);
            if (!empty($attributes)) {
                $guid = null;
                $first_name = null;
                $last_name = null;
                $groups = null;
                foreach ($attributes as $attributeName => $attributeValues) {
                    foreach ($attributeValues as $attributeValue) {
                        switch ($attributeName) {
                            case 'Last Name' :
                                $last_name = $attributeValue;
                                break;
                            case 'First Name' :
                                $first_name = $attributeValue;
                                break;
                            case 'Groups' :
                                $groups = $attributeValue;
                                break;
                            case 'GUID' :
                                $guid = $attributeValue;
                                break;
                        }
                    }
                }
            }

            // Choose what to do with the attribute values.
        });
    }
}