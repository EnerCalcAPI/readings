# Readings

[![Latest Version](https://img.shields.io/badge/release-v0.0.1-blue?style=plastic)](https://github.com/EnerCalcAPI/readings/releases) [![Github](https://img.shields.io/github/issues/EnerCalcAPI/readings?style=plastic)](https://github.com/EnerCalcAPI/readings/issues) [![License](https://img.shields.io/github/license/EnerCalcAPI/readings?style=plastic)](https://opensource.org/licenses/MIT)

## About the EnerCalc Readings API.
The [documentation](https://api.enercalc.nl/docs/) provides you all the information needed to get up and running!

Make sure you read the documentation if you aren't familiar with Laravel or packages.

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

| Requirement | Version |
| --- | --- |
| Laravel | ^8 |
| PHP | ^7.4 |
| Guzzle | ^7 |

### File .env

Add the following to your `.env` file :
| Option | | Value |
| --- | --- | --- |
| Required | | |
| ENERCALC_USER | = | [ USERNAME ] |
| ENERCALC_PASSWORD | = | [ PASSWORD ] |
| ENERCALC_URL | = | [ URL ] |
| ENERCALC_DEBUG | = | [ true OR false ] |
| Optional | | |
| ENERCALC_TOKEN_STORAGE | = | [ TIME IN SECONDS ] |

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
