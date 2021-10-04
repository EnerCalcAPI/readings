<?php

namespace Enercalcapi\Readings\Http\Controllers;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
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
    /**
     * HTTP Constants
     */
    const HTTP_RESPONSE_OK                              = 200;
    const HTTP_RESPONSE_CREATED                         = 201;
    const HTTP_RESPONSE_ACCEPTED                        = 202;
    const HTTP_RESPONSE_NON_AUTHORITATIVE_INFORMATION   = 203;
    const HTTP_RESPONSE_NO_CONTENT                      = 204;
    const HTTP_RESPONSE_RESET_CONTENT                   = 205;
    const HTTP_RESPONSE_PARTIAL_CONTENT                 = 206;
    
    const HTTP_RESPONSE_UNKNOWN                         = 400;
    const HTTP_RESPONSE_UNAUTHORIZED                    = 401;
    const HTTP_RESPONSE_PAYMENT_REQUIRED                = 402;
    const HTTP_RESPONSE_FORBIDDEN                       = 403;
    const HTTP_RESPONSE_NOT_FOUND                       = 404;
    const HTTP_RESPONSE_METHOD_NOT_ALLOWED              = 405;
    const HTTP_RESPONSE_NOT_ACCEPTED                    = 406;
    const HTTP_RESPONSE_REQUEST_TIMEOUT                 = 408;
    const HTTP_RESPONSE_PAYLOAD_TOO_LARGE               = 413;
    const HTTP_RESPONSE_EXPECTATION_FAILED              = 417;
    const HTTP_RESPONSE_I_AM_A_TEAPOT                   = 418;
    const HTTP_RESPONSE_UPGRADE_REQUIRED                = 426;
    const HTTP_RESPONSE_TOO_MANY_REQUESTS               = 429;
    const HTTP_RESPONSE_UNAVAILABLE_FOR_LEGAL_REASONS   = 451;

    const HTTP_RESPONSE_INTERNAL_SERVER_ERROR           = 500;
    const HTTP_RESPONSE_SERVICE_UNAVAILABLE             = 503;
    const HTTP_RESPONSE_GATEWAY_TIMEOUT                 = 504;
    const HTTP_RESPONSE_HTTP_VERSION_NOT_SUPPORTED      = 505;
    
    public $user;
    public $password;
    public $token;
    public $url;
    public $debug;
    public $token_storage;

    /**
     * @param array $config
     *
     * @return void
     **/
    public function __construct()
    {
        $config              = app('config')->get('config' );
        $this->user          = $config[ 'ENERCALC_USER' ];
        $this->password      = $config[ 'ENERCALC_PASSWORD' ];
        $this->token         = NULL;
        $this->token_storage = $config[ 'ENERCALC_TOKEN_STORAGE' ];
        $this->url           = $config[ 'ENERCALC_URL' ];
        $this->debug         = $config[ 'ENERCALC_DEBUG' ];
    }

    /**
     * @param string $reason
     * @param mixed $eans
     * @param mixed $date
     * @param mixed $date_to
     * 
     * @return Json
     **/
    public function ReadingRequest( string $reason, $eans, $date, $date_to = NULL ){

        $access_token       = $this->GetAccessToken();
        /*$url                = $this->GetRequestUrl( $reason );
        $date               = $this->GetRequestDate( $reason, $date, $date_to );
        $eans               = $this->GetRequestEanArray( $eans );
        dd( $access_token, $url, $date, $eans );
        $response           = $this->RequestData( $access_token, $url, $date, $eans );
        $validated_response = $this->ValidateResponse( $response );
        dd( $validated_response );
        return $validated_response;*/
    }

    /**
     * Recieve url, user and password from config file
     * 
     * @return Json
     **/
    public function GetAccessToken() : ?string{
        return Cache::remember( 'access_token', $this->token_storage, function(){
            try{
                $response = Http::withOptions([
                    'verify' => ( config('app.env') == 'production' ),
                    'http_errors' => true,
                ])
                ->acceptJson()
                ->post( $this->url . 'api/v1/auth/login', [
                    'email' => $this->user . '1',
                    'password' => $this->password,
                ])->json();
                dd( $response );
                return $response['data']['access_token'];
            } catch ( ClientException $exception ) {
                $this->ValidateResponse( $exception );
            } catch ( ServerException $exception ) {
                $this->ValidateResponse( $exception );
            } catch ( BadResponseException $exception ) {
                $this->ValidateResponse( $exception );
            }
            
        });
    }

    /**
     * @param string $reason
     * 
     * @return string|void
     **/
    public function GetRequestUrl( string $reason ) : ?string{
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
                abort( 406 );
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
    private function GetRequestDate( string $reason, $date, $date_to ) : ?array{

        $date   = $this->ValiDate( $reason, $date );

        if( $date_to !== NULL ){
            $date_to    = $this->ValiDate( $reason, $date_to );
            return array(
                'reading_date_from' => $date, 
                'reading_date_to'   => $date_to,
            );
        } else {
            return array(
                'reading_date' => $date
            );
        }
    }

    /**
     * @param string $reason
     * @param mixed $date
     * 
     * @return string|void
     **/
    private function ValiDate( string $reason, $date ) : ?string {
        try{
            $parsed_date = Carbon::parse($date);
            return $this->FormatDate( $reason, $parsed_date );
        } catch ( InvalidFormatException $e) {
            // Invalide input
            abort( 406 );
        }
    }

    /**
     * @param string $reason
     * @param carbon $date
     * 
     * @return string
     **/
    private function FormatDate( string $reason, carbon $date ) : ?string{
        if( $reason == 'month' ){
            return $date->format( 'Y-m' );
        } else {
            return $date->format( 'Y-m-d' );
        }
    }

    /**
     * @param mixed $eans
     * 
     * @return array
     **/
    private function GetRequestEanArray( $eans ) : ?array{
        if( is_array($eans) ){
            foreach( $eans as $ean ){
                $this->ValiDatean18( $ean );
            }
            return array( 'connection_eans' => $eans);
        } else {
            $this->ValiDatean18( $eans );
            return array( 'connection_ean' => $eans );
        }
    }

    /**
     * @param string $ean
     * 
     * @return bool|void
     **/
    private function ValiDatean18( string $ean ) : ?bool{
        $check = EAN::isEAN18( $ean );
        return ( $check ? $check : abort( 406 ) );
    }

    /**
     * @param string $access_token
     * @param string $url
     * @param array $date
     * @param array $eans
     * 
     * @return object
     **/
    public function RequestData( string $access_token, string $url, array $date, array $eans ) : object{
        $date = array_merge($date, $eans);

        return Http::withToken( $access_token )
        ->acceptJson()
        ->post( $url, [
                $date,
                $eans
            ]
        )->json();
    }

    /**
     * @param object $response
     * 
     * @return object|void
     */
    private function ValidateResponse( object $exception ) : ?object{
        
        $error_codes    = $this->KnownPossibleErrorCodes();
        $code           = $exception->getResponse()->getStatusCode();
        $message        = json_decode($exception->getResponse()->getBody(), true);

        if( array_key_exists( $code, $error_codes ) ){
            switch ($code) {
                // 200
                case 200: $text = 'OK'; break;
                case 201: $text = 'Created'; break;
                case 202: $text = 'Accepted'; break;
                case 203: $text = 'Non-Authoritative Information'; break;
                case 204: $text = 'No Content'; break;
                case 205: $text = 'Reset Content'; break;
                case 206: $text = 'Partial Content'; break;
                // 400
                case 400: $text = 'Bad Request'; break;
                case 401: $text = 'Unauthorized'; break;
                case 402: $text = 'Payment Required'; break;
                case 403: $text = 'Forbidden'; break;
                case 404: $text = 'Not Found'; break;
                case 405: $text = 'Method Not Allowed'; break;
                case 406: $text = 'Not Acceptable'; break;
                case 407: $text = 'Proxy Authentication Required'; break;
                case 408: $text = 'Request Time-out'; break;
                case 413: $text = 'Request Entity Too Large'; break;
                case 417: $text = 'Request-URI Too Large'; break;
                case 418: $text = 'Unsupported Media Type'; break;
                case 426: $text = 'Upgrade required'; break;
                case 429: $text = 'Too many requests'; break;
                case 451: $text = 'Unavailable for legal reasons'; break;
                // 500
                case 500: $text = 'Internal Server Error'; break;
                case 501: $text = 'Not Implemented'; break;
                case 502: $text = 'Bad Gateway'; break;
                case 503: $text = 'Service Unavailable'; break;
                case 504: $text = 'Gateway Time-out'; break;
                case 505: $text = 'HTTP Version not supported'; break;
                default:
                    exit('Unknown http status code "' . $code . '"');
                break;
            }
            //dd($code, $text);
            return View::make("errors.api")
                        ->with( 'code', $code )
                        ->with( 'mssg', $message )
                        ->with( 'text', $text );
                        
        } else {
            dd(' not in array ');
            return $exception;
        }

        dd( $exception->getResponse()->getStatusCode(), 'hi' );
        array_key_exists( $exception->getResponse()->getStatusCode(), $this->KnownPossibleErrorCodes() );
    }

    /**
     * @return array
     **/
    private function KnownPossibleErrorCodes() : array{
        return [
            200 => 'Response OK',
            201 => 'Response CREATED',
            202 => 'Response ACCEPTED',
            203 => 'Response NON AUTHORITATIVE INFORMATION',
            204 => 'Response NO CONTENT',
            205 => 'Response RESET CONTENT',
            206 => 'Response PARTIAL CONTENT',

            400 => 'Bad Request.',
            401 => 'Unauthorized.',
            402 => 'Payment required',
            403 => 'Forbidden.',
            404 => 'Page not found',
            405 => 'Method not allowed',
            406 => 'Invalid input.',
            408 => 'Request timeout.',
            413 => 'Payload too large.',
            417 => 'Expectation failed.',
            418 => 'I\'m a teapot.',
            426 => 'Upgrade required.',
            429 => 'Too many requests.',
            451 => 'Unavailable for legal reasons.',

            500 => 'Internal server error',
            501 => 'Not implemented',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
            504 => 'Gateway timeout',
            505 => 'HTTP version not supported',

            999 => 'Unknown error! Please contact `dev@enercalc.nl`.',
        ];
    }
}