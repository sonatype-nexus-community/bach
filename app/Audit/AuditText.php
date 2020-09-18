<?php
namespace App\Audit;

use \Symfony\Component\Console\Helper\Table;
use \Symfony\Component\Console\Helper\TableCell;
use \Symfony\Component\Console\Helper\TableStyle;
use \Symfony\Component\Console\Helper\TableSeparator;
use Codedungeon\PHPCliColors\Color;

class AuditText implements Audit
{
    public function audit_results($packages, $response, $output) : int {
        $output->text("\n" . "Vulnerable Packages" . "\n");
        $vulnerableDependencies = 0;

        foreach($response as $r)
        {
            if (!array_key_exists("coordinates", $r))
            {
                continue;
            }
            $is_vulnerable = array_key_exists("vulnerabilities", $r) ? (count($r['vulnerabilities']) > 0 ? true: false) : false;
            if ($is_vulnerable) {
                $vulnerableDependencies++;
                $p = "Package: " . $r['coordinates'];
                $d = array_key_exists("description", $r) ? "Description: " . $r['description'] : "";
                echo Color::LIGHT_WHITE, $p, Color::RESET, PHP_EOL;
                echo $d . "\n" . "Scan status: " . count($r['vulnerabilities']) . " vulnerabilities found." . "\n";
                foreach($r["vulnerabilities"] as $vuln) {
                    $this->output_vuln_table($vuln, $output);
                }
            }           
        }
        $this->output_summary_table($packages, $vulnerableDependencies, $output);

        return $vulnerableDependencies;
    }

    private function output_summary_table($packages, $vulnerableDependencies, $output) {
        $table = new Table($output);

        $table->setStyle('box-double');

        $table->setHeaders([
            [new TableCell('Summary', ['colspan' => 2])],
        ]);
        $table->addRow(['Audited Dependencies', count($packages)]);
        $table->addRow(['Vulnerable Dependencies', $vulnerableDependencies]);
        $table->render();
    }

    private function output_vuln_table($vuln, $output) {
        $this->get_severity_title($vuln['cvssScore'], "[" . $this->get_severity($vuln['cvssScore']) . " Threat] " . $vuln['title']);

        $table = new Table($output);

        $tableStyle = new TableStyle();

        $tableStyle
            ->setBorderFormat($this->get_severity_table_color($vuln['cvssScore']));

        $table->setStyle($tableStyle);

        $table->addRow(["ID", $vuln['id']]);
        $table->addRow(["Title", $vuln['title']]);
        $table->addRow(["Description", $vuln['description']]);
        $table->addRow(["CVSS Score", $vuln['cvssScore'] . " - " . $this->get_severity($vuln['cvssScore'])]);
        $table->addRow(["CVSS Vector", $vuln['cvssVector']]);
        if (array_key_exists('cve', $vuln)) {
            $table->addRow(["CVE", $vuln['cve']]);
        } else {
            $table->addRow(["CWE", $vuln['cwe']]);
        }

        $table->addRow(["Reference", $vuln['reference']]);

        $table->setColumnMaxWidth(0, 15);
        $table->setColumnMaxWidth(1, 100);

        $table->render();
    }

    protected function get_severity($score) {
        $float_score = (float) $score;
        switch (true) {
            case ($float_score >= 9):
                return "Critical";
            break;
            case ($float_score >= 7 && $float_score < 9):
                return "High";
            break;
            case ($float_score >= 4 && $float_score < 7):
                return "Medium";
            break;
            default:
                return "Low";
        }
    }

    protected function get_severity_table_color($score) {
        $float_score = (float) $score;
        switch (true) {
            case ($float_score >= 9):
                return "<fg=red;options=bold> %s </>";
            break;
            case ($float_score >= 7 && $float_score < 9):
                return "<fg=red> %s </>";
            break;
            case ($float_score >= 4 && $float_score < 7):
                return "<fg=yellow> %s </>";
            break;
            default:
                return "<fg=green> %s </>";
        }
    }

    protected function get_severity_title($score, $text) {
        $float_score = (float) $score;
        switch (true) {
            case ($float_score >= 9):
                echo "\t", Color::LIGHT_RED, $text, Color::RESET, PHP_EOL;
            break;
            case ($float_score >= 7 && $float_score < 9):
                echo "\t", Color::LIGHT_ORANGE, $text, Color::RESET, PHP_EOL;
            break;
            case ($float_score >= 4 && $float_score < 7):
                echo "\t", Color::LIGHT_YELLOW, $text, Color::RESET, PHP_EOL;
            break;
            default:
                echo "\t", Color::LIGHT_GREEN, $text, Color::RESET, PHP_EOL;
        }
    }
}
