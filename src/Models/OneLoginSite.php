<?php

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

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class OneLoginSite extends Model
{
    public $table = 'onelogin_site';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'site_name',
        'sp_entity_id',
        'sp_acs_url',
        'sp_acs_binding',
        'sp_slo_url',
        'sp_slo_binding',
        'sp_name_id_format',
        'sp_x509cert',
        'sp_private_key',
        'ip_entity_id',
        'ip_acs_url',
        'ip_acs_binding',
        'ip_slo_url',
        'ip_slo_binding',
        'ip_name_id_format',
        'ip_x509cert',
        'ip_private_key'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
//        'email_verified_at' => 'datetime',
    ];
}
