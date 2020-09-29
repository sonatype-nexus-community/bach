<?php
namespace App\CycloneDX\Generator;

use XMLWriter;

class Generator 
{
    private $xml_ns = "http://cyclonedx.org/schema/bom/1.1";
    private $xml_ns_v = "http://cyclonedx.org/schema/ext/vulnerability/1.0";

    public function __construct() {
    }

    public function generate_sbom($coordinates) {
        return $this->__generate_sbom($coordinates);
    }

    private function __generate_sbom($coordinates) {

        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->setIndent(false);
        $writer->startDocument('1.0', 'utf-8');
        $writer->startElementNs(null, "bom", $this->xml_ns);
        $writer->startAttribute('version');
        $writer->text('1');
        $writer->endAttribute();
        $writer->startElement('components');

        foreach($coordinates['coordinates'] as $coordinate) {
            $this->__write_component($writer, $coordinate);
        }

        $writer->endElement(); // components
        $writer->endElement(); // bom
        $writer->endDocument();

        return $writer->outputMemory();
    }

    private function __write_component(XMLWriter $xml_writer, $coordinate) {
        $exploded_coordinate = explode('@', $coordinate);
        $version = $exploded_coordinate[1];
        $name_group = explode('pkg:composer/', $exploded_coordinate[0]);
        $exploded_name_group = explode('/', $name_group[1]);

        $xml_writer->startElement('component');
        $xml_writer->startAttribute('type');
        $xml_writer->text('library');
        $xml_writer->endAttribute();

        if (array_key_exists('1', $exploded_name_group)) {
            $xml_writer->startElement('group');
            $xml_writer->text($exploded_name_group[0]);
            $xml_writer->endElement();

            $xml_writer->startElement('name');
            $xml_writer->text($exploded_name_group[1]);
            $xml_writer->endElement();
        }
        else {
            $xml_writer->startElement('name');
            $xml_writer->text($exploded_name_group[0]);
            $xml_writer->endElement();
        }

        $xml_writer->startElement('version');
        $xml_writer->text($version);
        $xml_writer->endElement();

        $xml_writer->startElement('purl');
        $xml_writer->text($coordinate);
        $xml_writer->endElement();
        
        $xml_writer->endElement();
    }
}
