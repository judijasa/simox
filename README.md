# SIMOExpress

## Esta aplicacion...
1. Extrae las ofertas de empleo reportadas en la plataforma SIMO del gobierno de Colombia.
2. Guarda las ofertas de empleo en una base de datos.
3. Ofrece un portal en linea para ofertas de empleo.

This application is comprised of three components: crawler, database and website.
[]: # 'This is a comment'
[]: # 'Data: Entity: Job offer snapshots, Attributes: page, job title, salary, etc.;'
[]: # '      Entity: Job offer, Attributes: job title, salary, etc.;...'
[]: # '      Entity: Static Data, Attributes: ..., etc.; ...'
[]: # 'Database: simo_express'
[]: # 'Database Management System (DBMS): MySQL (or MariaDB)'
[]: # 'Database Application Program: Internet database application (HTML + Apache + PHP/MySQL)'

## PHP CASPER CLASS
1.  Edit `vendor/phpcasperjs/phpcasperjs/src/Casper.php:sendKeys()` to allow setting
    of the boolean option `reset`, which is already defined in
    `vendor/jerome-breton/casperjs/modules/casper.js:sendKeys()`

    Code:

    ``{verbatim}

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
    ``

2.  Define sendKeysReset() and define fetchText() in
        `vendor/phpcasperjs/phpcasperjs/src/Casper.php:sendKeys()`

    Code:

    ``{verbatim}

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
    ``

    Other interesting functions to incorporate:
    [github.com/synackSA/.../Casper.php](https://github.com/synackSA/casperjs-php/blob/master/src/Casper.php)
    Basic usage:
    [github.com/synackSA/.../README.md](https://github.com/synackSA/casperjs-php)

3.  casperjs sendKeys() uses phantomjs sendEvent(). Useful references
    ### Documentation
    [PHANTOMJS sendEvent](https://phantomjs.org/api/webpage/method/send-event.html)
    ### Code
    [https://github.com/ariya/phantomjs/blob/master/src/webpage.cpp](https://github.com/ariya/phantomjs/blob/master/src/webpage.cpp)

## Database Design
Entities: Job Offer
Attributes:

```mermaid
flowchart TD;
source("Official Website") -->
start[n = 1, i = 1] -->
scrap[[Scrap from pages n to N]] -->
check{"(n = N) <br/> OR <br/> (i = last_attempt)?"} -- YES -->
post[Process Data]
check -- NO --> scrap
post -->
data[(My Database)] -->
report[Report activity summary] & myweb(My Unofficial Website)

note["<div style='text-align:left'>The overall workflow is in the file <b>main.sh</b>.<br/><br/>  To recover from connectivity crashes we run a conditional loop<br/> with an upper bound in the number of attempts.</div>"]-->
anothernote["<div style='text-align:left'>The actual scrapping takes place in the file <b>get_jobs.php</b>.<br/><br/>We use the Casper class from [1] to script navigate the web:<br/><br/><pre>$casper = Casper(#quot;simo.cnsc.gov.co/#ofertaEmpleo#quot;);</pre><br/>[1] github.com/alwex/php-casperjs: A PHP wrapper of the library CasperJS.</div>"]
style note fill:#FFFFE0,stroke:#333;
style anothernote fill:#FFFFE0,stroke:#333;
linkStyle 8 stroke-width:0px;
%% White: #FFFFFF

```
