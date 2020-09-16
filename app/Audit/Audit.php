<?php
namespace App\Audit;

interface Audit
{
    public function audit_results($packages, $vulnerabilities, $output) : int;
}
