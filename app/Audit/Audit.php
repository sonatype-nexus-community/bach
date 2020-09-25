<?php
namespace App\Audit;

interface Audit
{
    public function audit_results($response, $output) : int;
}
