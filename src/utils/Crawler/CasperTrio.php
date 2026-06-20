<?php

namespace Utils\Crawler;

// vendor/autoload: stackoverflow.com/questions/41209349/requirevendor-autoload-php-failed-to-open-stream
require 'vendor/autoload.php';

// The PHP Object Casper is defined in:
// vendor/phpcasperjs/phpcasperjs/src/Casper.php
use Browser\Casper;

// Class extensions: https://www.w3schools.com/php/keyword_extends.asp

class CasperTrio extends Casper {
    /**
     *  @param string $selector
     *  @param string $input
     *  @param boolean $reset
     */
    public function sendKeys($selector, $string, $reset=false)
        {
            $jsonData = json_encode($string);

            $fragment = <<<FRAGMENT
    casper.then(function () {
                this.sendKeys('$selector', $jsonData, { reset: $reset });
    });

    FRAGMENT;

            $this->script .= $fragment;

            return $this;
        }

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
}

