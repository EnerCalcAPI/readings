<?php

namespace Enercalcapi\Readings\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    public static $user;
    public static $password;
    public static $token;
    public static $url;
    public static $debug;
    public static $token_storage;

    public function __construct(array $config)
    {
        $config              = app('config')->get('config' );
        $this->user          = $config[ 'ENERCALC_USER' ];
        $this->password      = $config[ 'ENERCALC_PASSWORD' ];
        $this->token         = NULL;
        $this->token_storage = $config[ 'ENERCALC_TOKEN_STORAGE' ];
        $this->url           = $config[ 'ENERCALC_URL' ];
        $this->debug         = $config[ 'ENERCALC_DBUG' ];
    }

}