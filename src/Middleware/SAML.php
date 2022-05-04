<?php

namespace OneLoginToolkit\Middleware;

use OneLoginToolkit\Helpers\SAMLAuth;
use Closure;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;

class SAML
{
    public function handle($request, Closure $next)
    {
        if (SAMLAuth::isLoggedIn()) {
            return $next($request);
        } else {
            if ($request->input('redirect')) {
                $redirect = $request->input('redirect');
            } else if ($request->input('RelayState')) {
                $redirect = $request->input('RelayState');
            } else {
                $redirect = null;
            }

            if ($redirect) {
                return redirect('/auth/saml?RelayState=' . $redirect);
            } else {
                return redirect('/auth/saml?RelayState=' . URL::current());
            }
        }
    }
}