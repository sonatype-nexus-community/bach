# Bach
Dependency vulnerability auditor for PHP

## Install

```
git clone https://github.com/OSSIndex/bach.git
cd bach
composer install
```


## Help

```
> php auditphp

  
                   _ _ _         _           
    /\            | (_) |       | |          
   /  \  _   _  __| |_| |_ _ __ | |__  _ __  
  / /\ \| | | |/ _` | | __| '_ \| '_ \| '_ \ 
 / ____ \ |_| | (_| | | |_| |_) | | | | |_) |
/_/    \_\__,_|\__,_|_|\__| .__/|_| |_| .__/ 
                          | |         | |    
                          |_|         |_|    

  unreleased

  USAGE: auditphp <command> [options] [arguments]

  composer     Audit Composer dependencies. Enter the path to composer.json after the command.
  pear         Audit PEAR dependencies
  tinker       Interact with your application

  app:build    Build a single file executable
  app:install  Install optional components
  app:rename   Set the application name

  make:command Create a new command
```

## Example usage

```
> php auditphp composer composer.json
                 _ _ _         _           
                | (_) |       | |          
  __ _ _   _  __| |_| |_ _ __ | |__  _ __  
 / _` | | | |/ _` | | __| '_ \| '_ \| '_ \ 
| (_| | |_| | (_| | | |_| |_) | | | | |_) |
 \__,_|\__,_|\__,_|_|\__| .__/|_| |_| .__/ 
                        | |         | |    
                        |_|         |_|    

Parsed 9 packages from /Users/twoducks/git.vor/bach/composer.json:
php ^7.1.3 (7.2.0)
eloquent/composer-config-reader ^2.1 (2.2.0)
guzzlehttp/guzzle ~6.0 (6.1.0)
hoa/console ^3.17 (3.18.0)
intonate/tinker-zero ^1.0 (1.1.0)
laravel-zero/framework 5.8.* (5.8.0)
nadar/php-composer-reader ^1.2 (1.3.0)
phlak/semver ^2.0 (2.1.0)
zendframework/zend-text ^2.7 (2.8.0)

Audit results:
==============
PACKAGE: php@7.2.0 DESC: Library that defines common interfaces for aspect-oriented programming in PHP. VULN: No
PACKAGE: eloquent/composer-config-reader@2.2.0 DESC: A light-weight component for reading Composer configuration files. VULN: No
PACKAGE: guzzlehttp/guzzle@6.1.0 DESC: Guzzle is a PHP HTTP client library and framework for building RESTful web service clients VULN: Yes
  id:43847300-2ff3-48ed-8df3-8d215627064c
  title:[CVE-2016-5385]  Improper Access Control
  description:PHP through 7.0.8 does not attempt to address RFC 3875 section 4.1.18 namespace conflicts and therefore does not protect applications from the presence of untrusted client data in the HTTP_PROXY environment variable, which might allow remote attackers to redirect an application's outbound HTTP traffic to an arbitrary proxy server via a crafted Proxy header in an HTTP request, as demonstrated by (1) an application that makes a getenv('HTTP_PROXY') call or (2) a CGI configuration of PHP, aka an "httpoxy" issue.
  cvssScore:8.1
  cvssVector:CVSS:3.0/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:H/A:H
  cve:CVE-2016-5385
  reference:https://ossindex.sonatype.org/vuln/43847300-2ff3-48ed-8df3-8d215627064c
PACKAGE: hoa/console@3.18.0 DESC: The Hoa\Console library. VULN: No
PACKAGE: intonate/tinker-zero@1.1.0  VULN: No
PACKAGE: laravel-zero/framework@5.8.0  VULN: No
PACKAGE: nadar/php-composer-reader@1.3.0  VULN: No
PACKAGE: phlak/semver@2.1.0  VULN: No
PACKAGE: zendframework/zend-text@2.8.0 DESC:  VULN: No
```
