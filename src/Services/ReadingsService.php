<?php

declare(strict_types=1);

namespace Enercalcapi\Readings\Services;

use Enercalcapi\Readings\Http\Controllers\EAN;
use Enercalcapi\Readings\Traits\HttpStatusCodes;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ReadingsService
{
    use HttpStatusCodes;

    public $user;
    public $password;
    public $baseUrl;

    public $access_token;
    public $refresh_token;
    public $expires_in;

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
        $this->baseUrl       = $config['ENERCALC_URL'];
        $this->access_token  = null;
        $this->refresh_token = null;
    }

    /**
     * Recieve url, user and password from config file
     *
     * @return string
     **/
    public function getAccessToken(): string
    {   
        if (Cache::has('readingService.access_token')) {
            return Cache::get('readingService.access_token');
        }
        
        if (Cache::has('readingService.refresh_token')) {
            $this->refreshToken();
            return $this->access_token;
        }

        $this->login();

        return $this->access_token;
    }

    /**
     * Function login()
     *
     * @return void
     */
    public function login(): void
    {
        $response = Http::retry(3, 1500)
            ->withOptions([
                'verify' => true,
            ])
            ->acceptJson()
            ->post($this->baseUrl . '/auth/login', [
                'email' => $this->user,
                'password' => $this->password,
            ])->json();

        $this->validateStatusResponse($response);

        if (Arr::get($response, 'data')) {
            $accessToken = Arr::get($response, 'data.access_token');
            if ($accessToken !== null) {
                $this->access_token = $accessToken;
            }

            $refreshToken = Arr::get($response, 'data.refresh_token');
            if ($refreshToken !== null) {
                $this->refresh_token = $refreshToken;
            }

            $expiresIn = Arr::get($response, 'data.expires_in');
            if ($expiresIn !== null) {
                $this->expires_in = $expiresIn;
            }
        }

        Cache::put('readingService.access_token', $this->access_token, ($this->expires_in - 5));
        Cache::put('readingService.refresh_token', $this->refresh_token);
    }

    /**
     * RefreshToken() function
     *
     * @return void
     */
    public function refreshToken(): void
    {
        // /user voor info over de gebruiker
        $refreshToken = Cache::get('readingService.refresh_token');

        $response = Http::withOptions([
            'verify' => true,
            'debug' => true,
        ])
            ->acceptJson()
            ->post($this->baseUrl . '/auth/refresh-token', [
                'token_type' => "Bearer",
                'refresh_token' => $refreshToken,
            ])
            ->json();
        dd('refreshToken', __line__, $response);
        /* - - - - - - - - - - - - - - */

        $this->validateStatusResponse($response);

        if (Arr::get($response, 'data')) {
            $accessToken = Arr::get($response, 'data.access_token');
            if ($accessToken && $accessToken !== null) {
                $this->access_token = $accessToken;
            }

            $refreshToken = Arr::get($response, 'data.refresh_token');
            if ($refreshToken && $refreshToken !== null) {
                $this->refresh_token = $refreshToken;
            }

            $tokenStorage = Arr::get($response, 'data.expires_in');
            if ($tokenStorage && $tokenStorage !== null) {
                $this->expires_in = $accessToken;
            }
        }

        Cache::put('readingService.access_token', $this->access_token, ($this->expires_in - 5));
        Cache::put('readingService.refresh_token', $this->refresh_token);
    }

    /**
     * Function validateStatusResponse()
     *
     * @param object $response
     *
     * @return bool|Exception
     */
    public function validateStatusResponse(array $response)
    {
        $statusCode = self::getHttpStatusCode($response);

        if (!self::isHttpSuccess($statusCode)) {
            throw new Exception('Failed with status code: ' . $statusCode);
        }
    }

    /**
     * Function getP4RequestUrl()
     *
     * @param string $reason
     *
     * @return string|Exception
     **/
    public function getP4RequestUrl(string $reason): string
    {
        switch ($reason) {
            case 'hour':
            case 'day':
            case 'week':
            case 'month':
            case 'year':
                break;
            default:
                throw new Exception('Found invalid reason: ' . $reason . '!');
        }

        return $this->baseUrl . '/readings/' . $reason;
    }

    /**
     * ValidateDate() function
     *
     * @param Carbon $date
     * @return string|null
     */
    public function validateDate($date): string
    {
        if ($date instanceof Carbon) {
            return $date->utc()->toJSON();
        } else {
            return Carbon::createFromFormat('Y-m-d\TH:i:s.uP', $date)->utc()->toJSON();
        }
    }

    /**
     * DateArrayToString() function
     *
     * @param array $date_array
     * @return array|null
     */
    public function datesToString($dateFrom, $dateTo): array
    {
        $manipulatedDateFrom = $this->validateDate($dateFrom);
        $manipulatedDateTo = $this->validateDate($dateTo);

        return [
            'reading_date_from' => $manipulatedDateFrom,
            'reading_date_to' => $manipulatedDateTo,
        ];
        
        throw new Exception('Invalid size of date array!');
    }

    /**
     * Function eanArrayToString()
     *
     * @param array $ean_array
     * @return array|null
     */
    public function eanArrayToString(array $ean_array): array
    {
        foreach ($ean_array as $ean) {
            if (!EAN::isEAN18($ean)) {
                throw new Exception('Found invalid EAN-code: ' . $ean . '!');
            }
        }

        return ['connection_eans' => $ean_array];
    }

    /**
     * Function RequestP4Data()
     *
     * @param string $reason
     * @param array $eans
     * @param array $date
     * @return array
     */
    public function requestP4Data(string $reason, array $eans, $dateFrom, $dateTo): array
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->retry(3, 3000)
                ->acceptJson()
                ->post(
                    $this->getP4RequestUrl($reason),
                    array_merge(
                        $this->datesToString($dateFrom, $dateTo),
                        $this->eanArrayToString($eans)
                    )
                )->json();
            
            $this->validateStatusResponse($response);
            
            return $response;
        } catch (Exception $e) {
            throw new Exception('Failed to retrieve P4 data!');
        }
    }
}
