# Dots Mesh Server

This package is responsible for storing and delivering your profiles and groups data. You can easily install it on your server or alternatively start using the [SaaS version](https://hosting.dotsmesh.com/) the Dots Mesh team offers.

## Requirements
- A web server (Apache, NGINX, etc.)
- PHP 7.2+
- A domain starting with "dotsmesh." (dotsmesh.example.com)
- SSL/TLS certificate

## How to install

[The Dots Mesh installer](https://about.dotsmesh.com/self-host/) is the recommended way to create your own host and join the platform. It will guide you through all the requirements and will install everything needed. There is an auto-update option, so you'll always use the latest stable version of the software.

### Custom installation

Youn can [download the latest release as a PHAR file](https://github.com/dotsmesh/dotsmesh-server-php/releases) and run the server this way. Create the index.php with the following content and configure it properly:
```php
<?php

define('DOTSMESH_SERVER_DATA_DIR', 'path/to/data/dir'); // The directory where the data will be stored.
define('DOTSMESH_SERVER_LOGS_DIR', 'path/to/logs/dir'); // The directory where the logs will be stored.
define('DOTSMESH_SERVER_HOSTS', ['example.com']); // A list of hosts supported by the server.

require 'dotsmesh-server-php-x.x.x.phar';
```

There is a [ZIP file](https://github.com/dotsmesh/dotsmesh-server-php/releases) option too. Just extract the content to a directory and point the index.php file to it.
```php
<?php

define('DOTSMESH_SERVER_DATA_DIR', 'path/to/data/dir'); // The directory where the data will be stored.
define('DOTSMESH_SERVER_LOGS_DIR', 'path/to/logs/dir'); // The directory where the logs will be stored.
define('DOTSMESH_SERVER_HOSTS', ['example.com']); // A list of hosts supported by the server.

require 'dotsmesh-server-php-x.x.x/app/index.php';
```

## License

The Dots Mesh Server is licensed under the GPL v3 license. See the [license file](https://github.com/dotsmesh/dotsmesh-server-php/blob/master/LICENSE) for more information.

## Contributions

The Dots Mesh platform is a community effort. Feel free to join and help us build a truly open social platform for everyone.
