<?php
namespace App\Audit;

interface Audit
{
    public function audit_results($packages, $response, $output) : int;
}
