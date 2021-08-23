<p align="center">
    <img src="https://github.com/sonatype-nexus-community/bach/blob/main/docs/images/Bach@2x.png" width="350"/>
</p>

<p align="center">
    <a href="https://circleci.com/gh/sonatype-nexus-community/bach"><img src="https://circleci.com/gh/sonatype-nexus-community/bach.svg?style=shield" alt="Circle CI Build Status"></img></a>
</p>

# Bach

Dependency vulnerability auditor for PHP

## Install

```
git clone https://github.com/sonatype-nexus-community/bach.git
cd bach
composer install
```

## Help

```
> php bach

  Bach  unreleased

  USAGE: bach <command> [options] [arguments]

  composer Audit Composer dependencies. Enter the path to composer.json after the command.
  iq       Audit Composer dependencies. Enter the path to composer.json after the command.
  pear     Audit PEAR dependencies
```

## Example usage

```
> php bach composer composer.json
 ____                         __
/\  _`\                      /\ \
\ \ \L\ \     __       ___   \ \ \___
 \ \  _ <'  /'__`\    /'___\  \ \  _ `\
  \ \ \L\ \/\ \L\.\_ /\ \__/   \ \ \ \ \
   \ \____/\ \__/.\_\\ \____\   \ \_\ \_\
    \/___/  \/__/\/_/ \/____/    \/_/\/_/


  _       _                          _   _
 /_)     /_` _  _  _ _/_     _  _   (/  /_` _ . _  _   _/  _
/_) /_/ ._/ /_// //_|/  /_/ /_//_' (_X /   / / /_'/ //_/ _\
    _/                  _/ /


Vulnerable Packages

Package: pkg:composer/league/flysystem@1.1.3
Description: Filesystem abstraction: Many filesystems, one API.
Scan status: 1 vulnerabilities found.
	[Medium Threat] CWE-367: Time-of-check Time-of-use (TOCTOU) Race Condition
 +-------------+------------------------------------------------------------------------------------------------------+
 |  ID           |  e28105bf-e92b-4e5b-8598-df88daf5a30c                                                                  |
 |  Title        |  CWE-367: Time-of-check Time-of-use (TOCTOU) Race Condition                                            |
 |  Description  |  The software checks the state of a resource before using that resource, but the resource's state can  |
 |               |  change between the check and the use in a way that invalidates the results of the check. This can ca  |
 |               |  use the software to perform invalid actions when the resource is in an unexpected state.              |
 |  CVSS Score   |  6.5 - Medium                                                                                          |
 |  CVSS Vector  |  CVSS:3.0/AV:L/AC:H/PR:L/UI:N/S:U/C:H/I:H/A:L                                                          |
 |  CWE          |  CWE-367                                                                                               |
 |  Reference    |  https://ossindex.sonatype.org/vulnerability/e28105bf-e92b-4e5b-8598-df88daf5a30c?component-type=comp  |
 |               |  oser&component-name=league%2Fflysystem&utm_source=guzzlehttp&utm_medium=integration&utm_content=6.3.  |
 |               |  3                                                                                                     |
 +-------------+------------------------------------------------------------------------------------------------------+
╔═════════════════════════╤═════╗
║ Summary                       ║
╠═════════════════════════╪═════╣
║ Audited Dependencies    │ 71  ║
║ Vulnerable Dependencies │ 1   ║
╚═════════════════════════╧═════╝
```

## Development notes

* PHP version - **7.4+ required**

  On macos, while `php` was already installed, 
    ```
    $ php --version
    PHP 7.3.11 (cli) (built: Jun  5 2020 23:50:40) ( NTS )
    Copyright (c) 1997-2018 The PHP Group
    Zend Engine v3.3.11, Copyright (c) 1998-2018 Zend Technologies
    ```
    we need a newer version of `php`: at least 7.4. To install this, I ran the following commands:
    ```
    brew update
    brew install php
    ```
    This installed `php 7.4` into: `/usr/local/Cellar/php/7.4.11`. In order to ensure this new version of
    php would be found before the macos pre-installed version, I prepended the new php `bin` folder to my path via:
    ```
    export PATH=/usr/local/Cellar/php/7.4.11/bin:$PATH
    ``` 
    Ensure the intended version will be used by running:
    ```
    $ php --version
    PHP 7.4.11 (cli) (built: Oct  1 2020 23:30:54) ( NTS )
    Copyright (c) The PHP Group
    Zend Engine v3.4.0, Copyright (c) Zend Technologies
        with Zend OPcache v7.4.11, Copyright (c), by Zend Technologies
    ```
  
* Composer

    I also had to do a one time install of [composer](https://getcomposer.org) using [brew](https://brew.sh) on macos:
    ```
    brew install composer
    ``` 
    
    After running `composer install`, I could run unit tests using:
    ```
    vendor/bin/phpunit tests
    ```
    I'm not sure it is actually needed, but while updating `brew` and things, I ran into a case that
    needed access to write to my local `bin` folders, and had to follow these steps to temporarily 
    disable `csrutil`. see: https://www.imore.com/how-turn-system-integrity-protection-macos.
    Be sure to undo such changes if you need 'em.
  
* You can cleanup `composer.lock` (remove stale dependencies from the `composer.lock` file)
using the command:
  ```
  composer update
  ```

* If you need to add new dependencies, I found the following commands would
ensure the new dependency was installed and available to unit tests, etc:
  ```
  composer update
  composer install
  vendor/bin/phpunit tests
  ``` 
