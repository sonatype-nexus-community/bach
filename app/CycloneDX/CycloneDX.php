<?php
namespace App\CycloneDX;

use App\CycloneDX\Generator\Generator;

class CycloneDX
{
    private $generator;

    public function __construct()
    {
        $this->generator = new Generator();
    }

    public function createAndReturnSbom($coordinates)
    {
        return $this->generator->generateSbom($coordinates);
    }
}
