<?php
namespace App\Audit;
error_reporting(E_ALL ^ E_DEPRECATED);

interface Audit
{
    public function audit_results($packages, $vulnerabilities, $output);
}
