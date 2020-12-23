End to End Test Projects
=============

In addition to automated unit tests (using PHPUnit), this directory provides sample projects to test Bach in full user conditions.

## Modes

On each sample project, Bach has to be tested in its 2 modes:
- OSSIndex: `bach composer [project]/composer.json`
- Nexus Lifecycle: `bach iq --application=sandbox-application --host=http://localhost:8070/ --stage=develop --user=admin --token=admin123 --file [project]/composer.json`

## Projects

Each provided test project is used for specific conditions:
- `clean/`: a project with no known vulnerabilities in declared dependencies
- `sample/`: a project with known vulnerabilities of every severity (Critical, High, Medium, Low)

~~ composer.json only vs composer.json+composer.lock?
~~ TODO PEAR?
