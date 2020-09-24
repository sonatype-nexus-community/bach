<?php

use PHPUnit\Framework\TestCase;
use App\Audit\AuditText;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Input\StringInput;

class AuditTextTest extends TestCase
{
    public function testAuditTextAuditResults()
    {
        $file = file_get_contents($this->join_paths(dirname(__FILE__), "ossindexresponse.txt"));
        $response = json_decode($file, true);

        $tmp_file_name = tempnam(dirname(__FILE__), "auditTextTest");

        $stream_output = new StreamOutput(fopen($tmp_file_name, 'a', false));
        $string_input = new StringInput("");
        $output = new OutputStyle($string_input, $stream_output);
        $audit = new AuditText();

        $audit->audit_results($response, $output);

        $this->assertEquals(filesize($tmp_file_name), 4793);
        unlink($tmp_file_name);
    }
}
