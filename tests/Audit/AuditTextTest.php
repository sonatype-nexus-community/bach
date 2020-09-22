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

        $stream_output = new StreamOutput(fopen($this->join_paths(dirname(__FILE__), "auditTextTestResults.txt"), 'a', false));
        $string_input = new StringInput("");
        $output = new OutputStyle($string_input, $stream_output);
        $audit = new AuditText();

        $audit->audit_results($response, $output);

        $this->assertEquals(filesize($this->join_paths(dirname(__FILE__), "auditTextTestResults.txt")), 442);
        unlink($this->join_paths(dirname(__FILE__), "auditTextTestResults.txt"));
    }

    private function join_paths(...$paths) {
        return preg_replace('~[/\\\\]+~', DIRECTORY_SEPARATOR, implode(DIRECTORY_SEPARATOR, $paths));
    }
}
