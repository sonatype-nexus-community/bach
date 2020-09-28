<?php
namespace App\CycloneDX;

use App\CycloneDX\Generator\Generator;

class CycloneDX 
{
    private $generator;

    public function __construct() {
        $this->generator = new Generator();
    }

    public function create_and_return_sbom($coordinates) {
        return $this->generator->generate_sbom($coordinates);
    }
}
