<?php

namespace Enercalcapi\Readings\Http\Controllers;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Enercalcapi\Readings\Traits\HttpStatusCodes;
use Exception;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValiDatesRequests;
use Illuminate\Http\Client\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

class RequestController extends BaseController
{
    use HttpStatusCodes;

    public $user;
    public $password;
    public $token;
    public $url;
    public $debug;
    public $token_storage;

    /**
     * Function __construct
     *
     * @param array $config
     *
     * @return void
     **/
    public function __construct()
    {
        $config              = app('config')->get('config');
        $this->user          = $config['ENERCALC_USER'];
        $this->password      = $config['ENERCALC_PASSWORD'];
        $this->token         = NULL;
        $this->token_storage = $config['ENERCALC_TOKEN_STORAGE'];
        $this->url           = $config['ENERCALC_URL'];
        $this->debug         = $config['ENERCALC_DEBUG'];
    }

    /**
     * @param string $reason
     * @param mixed $eans
     * @param mixed $date
     * @param mixed $date_to
     *
     * @return Json
     **/
    public function ReadingRequest(string $reason, $eans, $date, $date_to = NULL)
    {
        $access_token = $this->GetAccessToken();

        if ($access_token) {
            $url                = $this->GetRequestUrl($reason);
            /*$date               = $this->GetRequestDate( $reason, $date, $date_to );
            $eans               = $this->GetRequestEanArray( $eans );
            dd( $access_token, $url, $date, $eans );
            $response           = $this->RequestData( $access_token, $url, $date, $eans );
            $validated_response = $this->ValideResponse( $response );
            dd( $validated_response );
            return $validated_response;*/
            dd(__LINE__, $url);
        } else {
            abort(401);
        }
        dd(__LINE__, $access_token, Cache::get('access_token'));
    }

    /**
     * Recieve url, user and password from config file
     * 
     * @return Json|void
     * 
     **/
    protected function GetAccessToken(): ?string
    {
        return Cache::remember('access_token', $this->token_storage, function () {
            $response = Http::withOptions([
                'verify' => (config('app.env') == 'production'),
            ])
                ->acceptJson()
                ->post($this->url . 'auth/login', [
                    'email' => $this->user . '1',
                    'password' => $this->password,
                ]); // ->json()

            if ($this->ValideResponse($response)) {
                //true
                dd($response);
                // Return access_token
                //return $response; //['data']['access_token']
            } else {
                return null;
            }
        });
    }

    /**
     * @param string $reason
     * 
     * @return string|void
     **/
    public function GetRequestUrl(string $reason): ?string
    {
        switch ($reason) {
            case 'interval':
                $extension = 'interval';
                break;
            case 'day':
                $extension = 'day';
                break;
            case 'month':
                $extension = 'month';
                break;
            default:
                abort(406);
                break;
        }

        return $this->url . $extension;
    }

    /**
     * @param string $reason
     * @param mixed $date
     * @param mixed|null $date_to
     * 
     * @return array
     **/
    private function GetRequestDate(string $reason, $date, $date_to): ?array
    {

        $date   = $this->ValiDate($reason, $date);

        if ($date_to !== null) {
            $date_to    = $this->ValiDate($reason, $date_to);
            return array(
                'reading_date_from' => $date,
                'reading_date_to'   => $date_to,
            );
        } else {
            return array(
                'reading_date' => $date,
            );
        }
    }

    /**
     * @param string $reason
     * @param mixed $date
     * 
     * @return string|void
     **/
    private function ValiDate(string $reason, $date): ?string
    {
        try {
            $parsed_date = Carbon::parse($date);
            return $this->FormatDate($reason, $parsed_date);
        } catch (InvalidFormatException $e) {
            // Invalide input
            abort(406);
        }
    }

    /**
     * @param string $reason
     * @param carbon $date
     * 
     * @return string
     **/
    private function FormatDate(string $reason, carbon $date): ?string
    {
        if ($reason == 'month') {
            return $date->format('Y-m');
        } else {
            return $date->format('Y-m-d');
        }
    }

    /**
     * @param mixed $eans
     * 
     * @return array
     **/
    private function GetRequestEanArray($eans): ?array
    {
        if (is_array($eans)) {
            foreach ($eans as $ean) {
                $this->ValiDatean18($ean);
            }
            return array('connection_eans' => $eans);
        } else {
            $this->ValiDatean18($eans);
            return array('connection_ean' => $eans);
        }
    }

    /**
     * @param string $ean
     * 
     * @return bool|void
     **/
    private function ValiDatean18(string $ean): ?bool
    {
        $check = EAN::isEAN18($ean);
        return ($check ? $check : abort(406));
    }

    /**
     * @param string $access_token
     * @param string $url
     * @param array $date
     * @param array $eans
     * 
     * @return object
     **/
    public function RequestData(string $access_token, string $url, array $date, array $eans): object
    {
        $date = array_merge($date, $eans);

        return Http::withToken($access_token)
            ->acceptJson()
            ->post(
                $url,
                [
                    $date,
                    $eans,
                ]
            )->json();
    }

    /**
     * @param object $response
     * 
     * @return bool|void
     */
    private function ValideResponse(object $response): ?bool
    {
        $statusCode = self::getHttpStatusCode($response);

        if ($statusCode >= 200 && $statusCode < 300) {
            return true;
        } else {
            return null;
        }
    }
}
