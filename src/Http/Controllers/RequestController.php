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
    public $tokenStorage;

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
        $this->token         = null;
        $this->tokenStorage  = $config['ENERCALC_TOKEN_STORAGE'];
        $this->url           = $config['ENERCALC_URL'];
        $this->debug         = $config['ENERCALC_DEBUG'];
    }

    /**
     * @param string $reason
     * @param mixed $eans
     * @param mixed $date
     * @param mixed $dateTo
     *
     * @return Json
     **/
    public function ReadingRequest(string $reason, $eans, $date, $dateTo = null)
    {
        $accessToken = $this->GetAccessToken();

        if ($accessToken) {
            $url                = $this->GetRequestUrl($reason);
            /*$date               = $this->GetRequestDate( $reason, $date, $dateTo );
            $eans               = $this->GetRequestEanArray( $eans );
            dd( $accessToken, $url, $date, $eans );
            $response           = $this->RequestData( $accessToken, $url, $date, $eans );
            $validated_response = $this->ValideResponse( $response );
            dd( $validated_response );
            return $validated_response;*/
            dd('ReadingRequest', __LINE__, $url);
        } else {
            abort(401);
        }
        dd('ReadingRequest', __LINE__, $accessToken, Cache::get('access_token'));
    }

    /**
     * Recieve url, user and password from config file
     * 
     * @return Json|void
     * 
     **/
    protected function GetAccessToken(): ?string
    {
        return Cache::remember('access_token', $this->tokenStorage, function () {
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
                dd('GetAccessToken', $response);
                // Return accessToken
                //return $response; //['data']['accessToken']
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
     * @param mixed|null $dateTo
     * 
     * @return array
     **/
    private function GetRequestDate(string $reason, $date, $dateTo): ?array
    {

        $date   = $this->ValiDate($reason, $date);

        if ($dateTo !== null) {
            $dateTo    = $this->ValiDate($reason, $dateTo);
            return [
                'reading_date_from' => $date,
                'reading_date_to'   => $dateTo,
            ];
        } else {
            return [
                'reading_date' => $date,
            ];
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
            $parsedDate = Carbon::parse($date);
            return $this->FormatDate($reason, $parsedDate);
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
            return ['connection_eans' => $eans];
        } else {
            $this->ValiDatean18($eans);
            return ['connection_ean' => $eans];
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
     * @param string $accessToken
     * @param string $url
     * @param array $date
     * @param array $eans
     * 
     * @return object
     **/
    public function RequestData(string $accessToken, string $url, array $date, array $eans): object
    {
        $date = array_merge($date, $eans);

        return Http::withToken($accessToken)
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
