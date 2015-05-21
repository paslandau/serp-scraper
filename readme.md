#serp-scraper
[![Build Status](https://travis-ci.org/paslandau/serp-scraper.svg?branch=master)](https://travis-ci.org/paslandau/serp-scraper)

Library to query search engine results pages

##Description

Coming soon...

##Requirements

- PHP >= 5.5

##Installation

The recommended way to install serp-scraper is through [Composer](http://getcomposer.org/).

    curl -sS https://getcomposer.org/installer | php

Next, update your project's composer.json file to include serp-scraper:

    {
        "repositories": [ { "type": "composer", "url": "http://packages.myseosolution.de/"} ],
        "minimum-stability": "dev",
        "require": {
             "paslandau/serp-scraper": "dev-master"
        }
    }

After installing, you need to require Composer's autoloader:
```php

require 'vendor/autoload.php';
```