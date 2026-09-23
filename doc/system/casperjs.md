# PHP Casper class

Scraping used to be the original approach to fetch data from the SIMO website.
It has been superseded by the use of the API endpoint. A minor role is still
kept to showcase the use of crawling with Casper.

`Utils\Crawler\CasperTrio` (from the `judijasa/php-daas-framework` composer
package, used by `src/scripts/indexer/helpers.php`) is a subclass of
`vendor/phpcasperjs/phpcasperjs/src/Casper.php:Casper`. It overrides and defines
new methods. To use this subclass, after downloading the vendor libraries, the
`judijasa/php-daas-framework` composer plugin edits
`vendor/phpcasperjs/phpcasperjs/src/Casper.php:Casper`, replacing
`private $script` with `protected $script` automatically on every
`composer install`/`composer update`.

An alternative is to edit `vendor/phpcasperjs/phpcasperjs/src/Casper.php:sendKeys()`
to allow setting of the boolean option `reset`, which is already defined in
`vendor/jerome-breton/casperjs/modules/casper.js:sendKeys()`:

```php
/**
 *  @param string $selector
 *  @param string $input
 *  @param boolean $reset
 */
public function sendKeys($selector, $input, $reset=false)
    {
        $jsonData = json_encode($input);

        $fragment = <<<FRAGMENT
casper.then(function () {
            this.sendKeys('$selector', $jsonData, { reset: $reset });
});

FRAGMENT;

        $this->script .= $fragment;

        return $this;
    }
```

And define `vendor/phpcasperjs/phpcasperjs/src/Casper.php:fetchText()`:

```php
/**
 *  @param string $selector
 */
public function fetchText($selector)
    {
        $fragment = <<<FRAGMENT
casper.then(function () {
            this.echo(this.fetchText('$selector'));
});

FRAGMENT;

        $this->script .= $fragment;

        return $this;
    }
```

### Notes

1. There are other useful functions in PHP/CasperJS. See the links below.
   Code: https://github.com/synackSA/casperjs-php/blob/master/src/Casper.php
   Basic usage: https://github.com/synackSA/casperjs-php

2. casperjs' `sendKeys()` uses phantomjs' `sendEvent()`. Useful references:
   Documentation: https://phantomjs.org/api/webpage/method/send-event.html
   Code: https://github.com/ariya/phantomjs/blob/master/src/webpage.cpp

3. Another important section of code is
   `vendor/jerome-breton/casperjs/modules/clientutils.js:setField`, used in
   casperjs' `sendKeys()` method.

---

## Scratch notes

NOTE: When in trouble with (...)->find() use php string manipulation alternatives.

Building Your Own Web Scraper with CasperJS (not our case)
    dzone.com/articles/building-your-own-web-scraper-in-nodejs

How to execute PHP files
    In your browser:
        localhost/simo-express/myfile.php
    the address
        localhost/simo-express/
    redirects by default to
        localhost/simo-express/index.php
    You can also exec php from the command line:
        php index.php

CASPER-PHP (WITH EXAMPLES)
    github.com/alwex/php-casperjs/issues/25
    github.com/synackSA/casperjs-php

    docs.casperjs.org/en/latest/quickstart.html#a-minimal-scraping-script
    (Only for casperjs, not for phpcasperjs)
    Please take a look at the documentation for
    'then' method as it waits for earlier steps
    to be finished before running the next step. This
    is important to make sure your asserts work fine.

GENERAL
    www.alanmbarr.com/blog/scrape-dynamic-sites-phantomjs-php/

ACRÓNIMOS INSTITUCIONALES
    SIMO: Sistema de Apoyo para la Igualdad, el Mérito y la Oportunidad
    VRM: Verificación de Requisitos Mínimos

WEBSITES TO SCRAP
    simo-ppal.cnsc.gov.co/#ofertaEmpleo
    www.cnsc.gov.co/
    [universidades]

INFO ON SIMO WEB ARCHITECTURE
    simo.cnsc.gov.co/cnscwiki/doku.php?id=ea:tobe:aplicaciones:sigeca:arquitectura

ISSUES:
    Scraping a gridvew with paging
    www.codeproject.com/Questions/716286/Screenscraping-a-gridview-with-paging

SIMPLE_HTML_DOM GET DYNAMIC CONTENT
    stackoverflow.com/questions/39921426/simple-html-dom-get-dynamic-content-loaded-with-js
