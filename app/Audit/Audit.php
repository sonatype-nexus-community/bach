<?php
namespace App\Audit;

interface Audit
{
    public function auditResults($response, $output) : int;
}
