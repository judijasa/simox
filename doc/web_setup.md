## Web service configuration
In production, you serve your site using a global system service
such as apache or ngnix. You have to configure httpd.conf
(apache: /etc/httpd/conf/httpd.conf, nginx: /etc/nginx/nginx.conf) as
```conf
DocumentRoot "/home/user/my-project/public"
<Directory "/home/user/my-project/public">
    Require all granted
</Directory>
```

This is (relatively) safe because the browser physically cannot look "backward"
into your root directory.

```php
<?php
// index.php can still access the private folder securely from the server side:
require_blank_page_or_file("../src/Database.php");
?>
```

## Local endpoints

- From prod server (prod test) use apache/nginx endpoint:
```
http://localhost/web-projects/scraping_SIMO/index.php
```

- From dev machine (dev test) use PHP Built-in Server:

At repo root dir, execute (-t to target index.php relative location)
```bash
php -S localhost:8000 -t public
```
Open your browser and navigate to
```
http://localhost:8000
```
Ctrl + C in terminal to shut down the server.

