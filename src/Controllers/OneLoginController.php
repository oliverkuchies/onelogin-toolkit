<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use OneLoginToolkit\Helpers\SAMLAuth;

class OneLoginController extends Controller
{
    public static function consume(Request $request, $site_name) {
        return SAMLAuth::consumeSAMLResponse($request, $site_name, function() use ($site_name) {
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