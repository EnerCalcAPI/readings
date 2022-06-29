<?php

declare(strict_types=1);

namespace Enercalcapi\Readings\Services;

use Enercalcapi\Readings\Http\Controllers\EAN;
use Enercalcapi\Readings\Traits\HttpStatusCodes;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ReadingsService
{
    use HttpStatusCodes;

    public $user;
    public $password;
    public $access_token;
    public $refresh_token;
    public $url;
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
        $this->access_token  = null;
        $this->refresh_token = null;
        $this->token_storage = null;
        $this->url           = $config['ENERCALC_URL'];
    }

    /**
     * Recieve url, user and password from config file
     *
     * @return string
     **/
    protected function getAccessToken(): string
    {
        $this->access_token = Cache::get('access_token');
        
        if (!$this->access_token) {
            /*
            if (Cache::has('refresh_token')) {
                $this->refreshAccessToken();
            } else {*/
                dd('getAccessToken', $this->requestAccessToken());
            //}
        }

        return $this->access_token;
    }

    /**
     * Function requestAccessToken()
     *
     * @return void
     */
    protected function requestAccessToken(): void
    {
        try {
            $response = Http::retry(3, 1500)
                ->withOptions([
                    'verify' => (config('app.env') == 'production'),
                ])
                ->acceptJson()
                ->post($this->url . 'auth/login', [
                    'email' => $this->user,
                    'password' => $this->password,
                ])->json();

            $this->validateAccessTokenResponse($response);

            $this->access_token = $response['data']['access_token'];
            $this->refresh_token = $response['data']['refresh_token'];
            $this->token_storage = $response['data']['expires_in'];

            Cache::put('access_token', $this->access_token, ($this->token_storage - 3600));
            Cache::put('refresh_token', $this->refresh_token, ($this->token_storage - 60));
        } catch (Exception $exception) {
            dd('requestAccessToken', __LINE__, $exception);
            throw new Exception($exception);
        }
    }

    /**
     * RefreshAccessToken() function @TODO
     *
     * @return void
     */
    protected function refreshAccessToken(): void
    {
        try {
            // use Cache::pull() te get value from cache and delete it afterwards
            $response = Http::withToken(Cache::get('refresh_token'))
                ->post($this->url . 'auth/refresh-token', [
                    'refresh_token' => Cache::get('refresh_token'),
                ])
                ->json();
            dd('refreshAccessToken', __LINE__, $response);

            $response = Http::withOptions([
                'verify' => (config('app.env') == 'production'),
            ])
                ->acceptJson()
                ->post($this->url . 'auth/refresh-token', [
                    'token_type' => "Bearer",
                    'refresh_token' => Cache::get('refresh_token'),
                ])
                ->json();
            dd('refreshAccessToken', __line__, $response);
            /* - - - - - - - - - - - - - - */

            $this->validateAccessTokenResponse($response);

            $this->access_token = $response['data']['access_token'];
            $this->refresh_token = $response['data']['refresh_token'];
            $this->token_storage = $response['data']['expires_in'];

            Cache::put('access_token', $this->access_token, ($this->token_storage - 3600));
            Cache::put('refresh_token', $this->refresh_token, ($this->token_storage - 60));
        } catch (Exception $exception) {
            dd('refreshAccessToken', __line__, $exception);
        }
    }

    /**
     * Function validateAccessTokenResponse()
     *
     * @param object $response
     *
     * @return bool|Exception
     */
    protected function validateAccessTokenResponse(array $response): ?bool
    {
        $statusCode = self::getHttpStatusCode($response);

        if (self::isHttpSuccess($statusCode)) {
            return true;
        } else {
            throw new Exception((string) $statusCode);
        }
    }

    /**
     * Function getP4RequestUrl()
     *
     * @param string $reason
     *
     * @return string|Exception
     **/
    protected function getP4RequestUrl(string $reason): ?string
    {
        switch ($reason) {
            case 'interval':
            case 'day':
            case 'month':
                break;
            default:
                dd('getP4RequestUrl', __LINE__);
                throw new Exception('Found invalide reason: ' . $reason . '!');
        }

        return $this->url . 'readings/' . $reason;
    }

    /**
     * ValidateDate() function
     *
     * @param Carbon $date
     * @return string|null
     */
    protected function validateDate(Carbon $date): ?string
    {
        try {
            $date = Carbon::parse($date);
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            dd('validateDate', __LINE__);
            throw new Exception('Found Invalide date in date array');
        }
    }

    /**
     * DateArrayToString() function
     *
     * @param array $date_array
     * @return array|null
     */
    protected function dateArrayToString(array $date_array): ?array
    {
        $size = count($date_array);

        if ($size == 1) {
            return array(
                'reading_date' => $this->validateDate($date_array[0]),
            );
        } elseif ($size == 2) {
            return array(
                'reading_date_from' => $this->validateDate($date_array[0]),
                'reading_date_to' => $this->validateDate($date_array[1]),
            );
        } else {
            dd('dateArrayToString', __LINE__);
            throw new Exception('Invalide size of date array!');
        }
    }

    /**
     * Function eanArrayToString()
     *
     * @param array $ean_array
     * @return array|null
     */
    protected function eanArrayToString(array $ean_array): ?array
    {
        foreach ($ean_array as $ean) {
            if (!EAN::isEAN18($ean)) {
                dd('eanArrayToString', __LINE__);
                throw new Exception('Found invalide EAN-code in ean array!');
            }
        }
        return array('connection_eans' => $ean_array);
    }

    /**
     * Undocumented function
     *
     * @param string $reason
     * @param array $eans
     * @return void
     */
    public function requestMeterData(string $reason, array $eans)
    {
        return Http::withToken($this->getAccessToken())
            ->acceptJson()
            ->post(
                $this->getMeterRequestUrl($reason),
                $this->eanArrayToString($eans),
            )->json();
    }

    /**
     * Function RequestP4Data()
     *
     * @param string $reason
     * @param array $eans
     * @param array $date
     * @return array
     */
    public function requestP4Data(string $reason, array $eans, array $date): array
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->acceptJson()
                ->post(
                    $this->GetP4RequestUrl($reason),
                    array_merge(
                        $this->dateArrayToString($date),
                        $this->eanArrayToString($eans)
                    )
                )->json();

            $this->validateAccessTokenResponse($response);

            return $response;
        } catch (Exception $e) {
            dd('requestP4Data', __LINE__);
            throw new Exception((string) $e);
        }
    }
}
