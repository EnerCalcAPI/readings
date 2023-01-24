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

    public $accessToken;
    public $refreshToken;
    public $expiresIn;

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
        $this->accessToken  = null;
        $this->refreshToken = null;
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
            return $this->accessToken;
        }

        $this->login();

        return $this->accessToken;
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
                $this->accessToken = $accessToken;
            }

            $refreshToken = Arr::get($response, 'data.refresh_token');
            if ($refreshToken !== null) {
                $this->refreshToken = $refreshToken;
            }

            $expiresIn = Arr::get($response, 'data.expires_in');
            if ($expiresIn !== null) {
                $this->expiresIn = $expiresIn;
            }
        }

        Cache::put('readingService.access_token', $this->accessToken, ($this->expiresIn - 5));
        Cache::put('readingService.refresh_token', $this->refreshToken);
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
                'token_type' => 'Bearer',
                'refresh_token' => $refreshToken,
            ])
            ->json();
        //dd('refreshToken', __line__, $response);

        $this->validateStatusResponse($response);

        if (Arr::get($response, 'data')) {
            $accessToken = Arr::get($response, 'data.access_token');
            if ($accessToken && $accessToken !== null) {
                $this->accessToken = $accessToken;
            }

            $refreshToken = Arr::get($response, 'data.refresh_token');
            if ($refreshToken && $refreshToken !== null) {
                $this->refreshToken = $refreshToken;
            }

            $tokenStorage = Arr::get($response, 'data.expires_in');
            if ($tokenStorage && $tokenStorage !== null) {
                $this->expiresIn = $accessToken;
            }
        }

        Cache::put('readingService.access_token', $this->accessToken, ($this->expiresIn - 5));
        Cache::put('readingService.refresh_token', $this->refreshToken);
    }

    /**
     * Function validateStatusResponse()
     *
     * @param array $response
     *
     * @return ?Exception
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
     * @param mixed $date
     *
     * @return Carbon
     */
    public function validateDate($date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date;
        } else {
            return Carbon::createFromFormat('Y-m-d\TH:i:s.uP', $date);
        }
    }

    /**
     * datesToString() function
     *
     * @param mixed $dateFrom
     * @param mixed $dateTo
     *
     * @return array|Exception
     */
    public function datesToString($dateFrom, $dateTo): array
    {
        $manipulatedDateFrom = $this->validateDate($dateFrom);
        $manipulatedDateTo = $this->validateDate($dateTo);

        if ($manipulatedDateFrom->gte($manipulatedDateTo)) {
            throw new Exception('Found TO date before FROM date!');
        }

        return [
            'reading_date_from' => $manipulatedDateFrom->utc()->toJSON(),
            'reading_date_to' => $manipulatedDateTo->utc()->toJSON(),
        ];
    }

    /**
     * Function eanArrayToString()
     *
     * @param array $ean_array
     *
     * @return array
     */
    public function eanArrayToString(array $eanArray): array
    {
        foreach ($eanArray as $ean) {
            if (!EAN::isEAN18($ean)) {
                throw new Exception('Found invalid EAN-code: ' . $ean . '!');
            }
        }

        return ['connection_eans' => $eanArray];
    }

    /**
     * Function RequestP4Data()
     *
     * @param string $reason
     * @param array $eans
     * @param mixed $dateFrom
     * @param mixed $dateTo
     *
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
