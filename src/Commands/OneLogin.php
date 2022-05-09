<?php


namespace OneLoginToolkit\Commands;

use App\Models\OneLoginSite;
use App\Models\User;
use Illuminate\Console\Command;
use OneLoginToolkit\Constants;

class OneLogin extends Command
{
    const ONELOGIN_CONNECTOR_PATH = '/api/2/connectors';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onelogin:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an app with configuration required to run OneLogin.';

    /**
     * Execute the console command.
     *
     **/
    public function handle()
    {

        $base_route = env('APP_URL');
        $entity_id = config('onelogin.sp.entityId');
        $sp_acs_url = config('onelogin.sp.assertionConsumerService.url');
        $sp_slo_url = config('onelogin.sp.singleLogoutService.url');
        $name_id_format = config('onelogin.sp.NameIDFormat');
        $sp_slo_binding = config('onelogin.sp.singleLogoutService.binding');

        $site_name = $this->ask('What is the name of your new OneLogin app?');

	$base_url = $base_route . '/' . Constants::BASE_ROUTE . '/' . urlencode($site_name);

        $service_provider_entity_id =  $base_url . '/' .  $entity_id;
        $service_provider_acs_url =    $base_url . '/' .  $sp_acs_url;
        $service_provider_slo_url =    $base_url . '/' .  $sp_slo_url;

        $service_provider_slo_binding = $sp_slo_binding;
        $service_provider_name_id_format = $name_id_format;
	$service_provider_acs_binding = config('onelogin.sp.assertionConsumerService.binding');

        $identity_provider_entity_id = $this->ask(
            'Please enter your identity provider entity ID / Issuer URL.'
        );

        $identity_provider_acl_url = $this->ask(
            'Please enter your identity provider SSO URL'
        );

        $identity_provider_acs_binding = $this->ask(
          'Please enter your identity provider SSO binding of choice?',
            config('onelogin.idp.singleSignOnService.binding')
        );

        $identity_provider_slo_url = $this->ask(
            'Please enter your identity provider SLO URL.',
        );

        $identity_provider_slo_binding = $this->ask(
           'Please enter your identity provider SLO binding of choice.',
            config('onelogin.idp.singleLogoutService.binding')
        );

        $identity_provider_name_id_format = $this->ask(
            'Please enter your identity provider name id format.',
            config('onelogin.idp.NameIDFormat')
        );

        $identity_provider_x09_certificate = $this->ask(
            'Please enter your identity provider x509 certificate location. (Using storage/app as root)'
        );


        $service_provider_x09_certificate = $this->ask(
            "Please enter your service provider x509 certificate location. (Using storage/app as root)",
        );

        OneLoginSite::create([
            'site_name' => $site_name,
            'sp_entity_id' => $service_provider_entity_id,
            'sp_acs_url' => $service_provider_acs_url,
            'sp_acs_binding' => $service_provider_acs_binding,
            'sp_slo_url' => $service_provider_slo_url,
            'sp_slo_binding' => $service_provider_slo_binding,
            'sp_name_id_format' => $service_provider_name_id_format,
            'sp_x509cert' => $service_provider_x09_certificate,

            'ip_entity_id' => $identity_provider_entity_id,
            'ip_acs_url' => trim($identity_provider_acl_url, ' '),
            'ip_acs_binding' => $identity_provider_acs_binding,
            'ip_slo_url' => trim($identity_provider_slo_url, ' '),
            'ip_slo_binding' => $identity_provider_slo_binding,
            'ip_name_id_format' => $identity_provider_name_id_format,
            'ip_x509cert' => $identity_provider_x09_certificate,
        ]);

    }
}