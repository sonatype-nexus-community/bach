<?php

use PHPUnit\Framework\TestCase;
use App\CycloneDX\CycloneDX;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Input\StringInput;

class CycloneDXTest extends TestCase
{
    public function testCycloneDXGetSBOM()
    {
        $cyclonedx = new CycloneDX();

        $coordinates = [];
        $list_of_coordinates = [];
        array_push($list_of_coordinates, "pkg:composer/thing/name@0.0.0", "pkg:composer/thing/anothername@1.0.0");
        $coordinates['coordinates'] = $list_of_coordinates;

        $sbom = $cyclonedx->createAndReturnSbom($coordinates);

        $this->assertXmlStringEqualsXmlString($sbom, '<?xml version="1.0" encoding="UTF-8"?><bom version="1" xmlns="http://cyclonedx.org/schema/bom/1.1"><components><component type="library"><group>thing</group><name>name</name><version>0.0.0</version><purl>pkg:composer/thing/name@0.0.0</purl></component><component type="library"><group>thing</group><name>anothername</name><version>1.0.0</version><purl>pkg:composer/thing/anothername@1.0.0</purl></component></components></bom>');
    }

    private function join_paths(...$paths) {
        return preg_replace('~[/\\\\]+~', DIRECTORY_SEPARATOR, implode(DIRECTORY_SEPARATOR, $paths));
    }
}
