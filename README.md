# Readings

[![Latest Version](https://img.shields.io/badge/release-v0.0.1-blue?style=plastic)](https://github.com/EnerCalcAPI/readings/releases) [![Github](https://img.shields.io/github/issues/EnerCalcAPI/readings?style=plastic)](https://github.com/EnerCalcAPI/readings/issues) [![Github](https://img.shields.io/github/license/EnerCalcAPI/readings?style=plastic)](https://opensource.org/licenses/MIT)

## About the EnerCalc Readings API.
Nullam ut massa luctus sem cursus tempor ut facilisis risus. Nullam convallis id nunc id cursus. Nullam pharetra dignissim tortor id sodales. Duis finibus libero quis mi facilisis faucibus. Nam pretium dolor elementum ipsum luctus, eget suscipit dolor sagittis.

## Getting started

### Installation
Run the following command :

```bash
composer require EnerCalcAPI/readings
```

Add the service provider to ``` config/app.php ``` under ``` providers ``` :

```bash
'providers' => [
    Enercalcapi\Readings\EnercalcReadingsApiServiceProvider::class,
],
```

### Requirements

[![Guzzle ^7](https://img.shields.io/badge/guzzle-%5E7-blue?style=plastic)](https://github.com/guzzle/guzzle)
[![Laravel ^8](https://img.shields.io/badge/laravel-%5E8-blue?style=plastic)](https://laravel.com/)
[![Php ^7.4](https://img.shields.io/badge/php-%5E7.4-blue?style=plastic)](https://www.php.net/downloads.php#v7.4.22)

### .env file

Add the following to your `.env` file :
```bash
ENERCALC_USER       = [USERNAME]
ENERCALC_PASSWORD   = [PASSWORD]
ENERCALC_URL        = [URL]
ENERCALC_DEBUG      = [false]
```

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
