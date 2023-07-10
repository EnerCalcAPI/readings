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

    public $clientKey;
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

        $this->clientKey     = $config['ENERCALC_CLIENT_KEY'];
        $this->user          = $config['ENERCALC_USER'];
        $this->password      = $config['ENERCALC_PASSWORD'];
        $this->baseUrl       = $config['ENERCALC_URL'];
        $this->accessToken   = null;
        $this->refreshToken  = null;
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
        $response = Http::withOptions([
                'verify' => true,
            ])
            ->acceptJson()
            ->post($this->baseUrl . '/auth/login', [
                'email' => $this->user,
                'password' => $this->password,
            ])->json();

        $this->validateStatusResponse($response);

        if (Arr::get($response, 'data', false)) {
            if ($accessToken = Arr::get($response, 'data.access_token', false)) {
                $this->accessToken = $accessToken;
            }

            if ($refreshToken = Arr::get($response, 'data.refresh_token', false)) {
                $this->refreshToken = $refreshToken;
            }

            if ($expiresIn = Arr::get($response, 'data.expires_in', false)) {
                $this->expiresIn = $expiresIn;
            }

            Cache::put('readingService.access_token', $this->accessToken, ($this->expiresIn - 5));
            Cache::put('readingService.refresh_token', $this->refreshToken);
        }
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
            $containsMetaData = Arr::get($response, 'meta');
            if ($containsMetaData) {
                throw new Exception(json_encode($containsMetaData));
            } else {
                throw new Exception('Failed with status code: ' . $statusCode . ' and does not contain meta information.');
            }
        }
    }

    /**
     * Function getRequestUrl()
     *
     * @param string $reason
     *
     * @return string|Exception
     **/
    public function getRequestUrl(string $reason): string
    {
        switch ($reason) {
            case 'hour':
            case 'day':
            case 'week':
            case 'month':
            case 'year':
            case 'announce':
            case 'max-date':
                return $this->baseUrl . '/readings/' . $reason;
            default:
                throw new Exception('Found invalid reason: ' . $reason . '!');
        }
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
     * Adds the client key to an array and returns it.
     *
     * @return array The array containing the client key.
     */
    public function addClientKey(): array
    {
        return ['client_key' => intval($this->clientKey)];
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
     * Function connectionsArrayToString()
     *
     * @param array $ean_array
     *
     * @return array
     */
    public function connectionsArrayToString(array $connections): array
    {
        foreach ($connections as $connection) {
            if (!is_array($connection)) {
                throw new Exception('No array found: ' . $connection . '!');
            }
            if (!array_key_exists('ean', $connection)) {
                throw new Exception('No key found EAN-code: ' . $connection . '!');
            }
            if (!EAN::isEAN18($connection['ean'])) {
                throw new Exception('Found invalid EAN-code: ' . $connection . '!');
            }
        }

        return ['connections' => $connections];
    }

    /**
     * Function RequestP4Data()
     *
     * @param string $reason
     * @param array $connections
     * @param mixed $dateFrom
     * @param mixed $dateTo
     *
     * @return array
     */
    public function requestP4Data(string $reason, array $connections, $dateFrom, $dateTo, array $options = []): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->acceptJson()
            ->post(
                $this->getRequestUrl($reason),
                array_merge_recursive(
                    $this->datesToString($dateFrom, $dateTo),
                    $this->addClientKey(),
                    $this->connectionsArrayToString($connections),
                    $options,
                )
            )->json();

        $this->validateStatusResponse($response);
        
        return $response;
    }

    public function requestDateOfLastData(string $reason, array $connections, array $options = []): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->acceptJson()
            ->post(
                $this->getRequestUrl($reason),
                array_merge_recursive(
                    $this->addClientKey(),
                    ['connections' => $connections],
                    $options,
                )
            )->json();

        $this->validateStatusResponse($response);
        
        return $response;
    }

    /**
     * Announces connection data.
     *
     * @param string $ean
     * @param string $type
     * @param Carbon $startDate
     * @param Carbon|null $endDate
     *
     * @return array
     */
    public function announceConnectionData(string $ean, int $clientId, string $type, Carbon $startDate, ?Carbon $endDate = null): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->acceptJson()
            ->post(
                $this->getRequestUrl('announce'),
                array_merge_recursive(
                    $this->addClientKey(),
                    [
                        'client_id' => $clientId,
                        'ean' => $ean,
                        'type' => $type,
                        'start_date' => $startDate->toJSON(),
                        'end_date' => $endDate ? $endDate->toJSON() : null,
                    ],
                )
            )->json();

        $this->validateStatusResponse($response);
        
        return $response;
    }
}
