# OneLogin SAML Toolkit for Laravel
## Laravel version of the PHP-SAML Toolkit created by OneLogin

### Features 
- Create multiple tenants and authenticate users based on different application permissions.
- Use Laravel routes to authenticate users with the identity provider.
- Use php artisan onelogin:create to create different identity routes to use with different applications.

### Setup instructions
Use `composer require oliverkuchies/onelogin-toolkit` with your Laravel instance.

#### Add Service Provider to config/app.php
In the providers section of the configuration, add the following:

`OneLoginToolkit\OneLoginToolkitServiceProvider::class`

This will allow Laravel to register the necessary application files.

#### Publish package
Use `php artisan vendor:publish --provider=OneLoginToolkitServiceProvider` - this will publish the necessary files.
This includes: 
- A routes file to cater for the SAML authentication routes
- A config at `config/onelogin.php` which can be configured to your needs.
- A model at `app/Models/OneLoginSite.php`
- A controller to use for your consuming requests at `app/Controllers/OneLoginController.php`

You will need to disable CSRF at `app/Http/Middleware/VerifyCSRFToken.php` by adding the following line to `$except`:
`'/auth/saml/*'`.

#### Add Middleware

Add `'onelogin' =>  \App\Http\Middleware\SAML::class` as a middleware in app/Http/Kernel.php.

#### Passing app parameter to Middleware

When authenticating different apps, be sure to add the app parameter to the middleware.

i.e `Route::middleware('onelogin:my_app_name')->group(function() {`

This will allow you to authenticate multiple apps.

#### Create your first application! 
Use `php artisan onelogin:create` and follow the prompts.

Please place your certificates in `storage/app` in a folder of your choice, and be sure to secure them!

When following the prompts you can refer to this certificate relative to storage/app.

#### You're ready to go!
Congratulations! You're all setup. 

To test your application you can try accessing `/auth/saml/{yourappname}`. 

This will redirect you to OneLogin to authenticate your session.

### Other tips...

#### Security information

Please follow instructions at https://github.com/onelogin/php-saml to secure your requests accordingly.

To secure all requests, you can add trusted URLs in `config/onelogin.php`.

Once that is complete, you can use the following method to secure your requests.

`SAMLAuth::isTrustedPrefix(urldecode($url))`

This will return true if it is a trusted prefix.

#### OneLoginController

OneLoginController will be used to consume the authenticated requests within a closure. 
You can adjust the closure as required.

If you wish to pull parameters from the authenticated request, you will need to adjust your OneLogin settings as follows:

![image](https://i.imgur.com/GWDuS2b.png)