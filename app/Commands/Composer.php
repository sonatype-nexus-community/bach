<?php
namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Components\Logo\FigletString as ZendLogo;
use \Symfony\Component\Console\Formatter\OutputFormatterStyle;
use \Nadar\PhpComposerReader\ComposerReader;
use \Nadar\PhpComposerReader\RequireSection;
use PHLAK\SemVer\Version2;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class Composer extends Command
{
    /**
     * The Composer package manifest to audit
     */
    protected $file;

    /**
     * The packages specified as requirements
     */
    protected $packages = [];

    protected $packages_versions = [];

    protected $coordinates = array("coordinates" => []);

    protected $vulnerabilities = [];

    protected $styles = [];

    public function __construct()
    {
        parent::__construct();
        $this->styles = array
        (
            'red' => new OutputFormatterStyle('red', null, ['bold']) //white text on red background
        );
    }

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'composer {file}:The composer package manifest to audit.';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Audit Composer dependencies. Enter the path to composer.json after the command.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        foreach($this->styles as $key => $value)
        {
            $this->output->getFormatter()->setStyle($key, $value);
        }
        $this->show_logo();
        if (!File::exists($this->argument('file')))
        {
            $this->error("The file " . $this->argument('file') . " does not exist");
            return;
        }
    
        $this->file = realpath($this->argument('file'));
        $reader = new ComposerReader($this->file);
        if (!$reader->canRead()) {
            $this->error("Could not read composer file " . $this->argument('file') ."." );
            return;
        }
                    
        $this->get_packages($reader);
        $this->info("Parsed " . \count($this->packages) . " packages from " . $this->file . ":");
        if (count($this->packages) == 0)
        {
            $this->error("No packages found to audit.");
            return;
        }
        $this->get_packages_versions();
        $this->get_coordinates();
        $this->get_vulns();
        if(count($this->vulnerabilities) == 0)
        {
            $this->error("Did not receieve any data from OSS Index API.");
            return;
        }
        else
        {
            $this->info("");
            $this->info("Audit results:");
            $this->info("==============");
            foreach($this->vulnerabilities as $v)
            {
                if (!array_key_exists("coordinates", $v))
                {
                    continue;
                }
                $p = "PACKAGE: " . str_replace("pkg:composer/", "", $v['coordinates']);
                $d = array_key_exists("description", $v) ? "DESC: " . $v['description'] : "";
                $is_vulnerable = array_key_exists("vulnerabilities", $v) ? (count($v['vulnerabilities']) > 0 ? true: false) : false;
                $is_vulnerable_text = "VULN: " . ($is_vulnerable ? "Yes" : "No");
                $this->info($p . " " . $d . " " . $is_vulnerable_text);
                if ($is_vulnerable)
                {
                    foreach($v["vulnerabilities"] as $vuln)
                    {
                        foreach($vuln as $key => $value)
                        {
                            $this->info("  " . $key . ":".$value);
                        }
                    }
                }                
            }
        }
    }

    protected function show_logo()
    {
        $l = new ZendLogo('auditphp', []);
        echo $l;
    }

    protected function get_packages($reader)
    {
        $section = new RequireSection($reader);
        foreach($section as $package) {
            $this->packages[$package->name] = $package->constraint;
        }
    }

    protected function get_version($constraint)
    {
        $range_prefix_tokens = array('>', '<', '=', '^', '~');
        
        $length = strlen($constraint);
        $start = 0;
        $end = $length - 1;
        if (!in_array($constraint[$start], $range_prefix_tokens) && is_numeric($constraint[$start]) && $constraint[$end] != '*')
        {
            return new Version2($constraint);
        }
        elseif (in_array($constraint[$start], $range_prefix_tokens) && !in_array($constraint[$start + 1], $range_prefix_tokens) 
            && is_numeric($constraint[$start + 1]) && $constraint[$end] != '*')
        {
            $v = new Version2(substr($constraint, 1, $length - 1));
            switch($constraint[$start])
            {
                case '=':
                    return $v;
                case '<':
                    return $v->decrement();
                case '>':
                case '^':
                case '~':
                    return $v->incrementMinor();
                default:
                    $this->warn("Did not determine version operator for constraint ". $constraint . ".");
                    return $v;
            }
        }
        elseif (in_array($constraint[$start], $range_prefix_tokens) && in_array($constraint[$start + 1], $range_prefix_tokens) 
            && $constraint[end] != '*')
        {
            $v = new Version2($substr($constraint,2, $length - 2));
            switch($constraint[$start].$constraint[$start + 1])
            {
                case '>=':
                case '<=':
                    return $v;
                default:
                    $this->warn("Did not determine version operator for constraint ". $constraint . ".");
                    return $v;

            }
        }
        else if (!in_array($constraint[$start], $range_prefix_tokens) && is_numeric($constraint[$start]) && $constraint[$end] == '*')
        {
            return new Version2(str_replace('*', 0, $constraint));
        }
        else
        {
            $this->warn("Could not determine version operator.");
            return $constraint;
        }
    }

    protected function get_packages_versions()
    {
        foreach($this->packages as $package=>$constraint) 
        {
            $v = $this->get_version($constraint);
            $this->info($package . ' ' .$constraint . ' (' .$v. ')');
            $this->packages_versions[$package] = $v;
        }        
    }

    protected function get_coordinates()
    {
        $pkgs = [];
        foreach($this->packages_versions as $package=>$version) 
        {
            array_push($this->coordinates["coordinates"], "pkg:composer/" . $package . "@".$version);
        }
        
    }

    protected function get_vulns()
    {
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => 'https://ossindex.sonatype.org/api/',
            // You can set any number of default request options.
            'timeout'  => 100.0,
        ]);
        
        try
        {
            $response = $client->post('v3/component-report', [
                RequestOptions::JSON => $this->coordinates
            ]);
            $code = $response->getStatusCode();
            if ($code != 200)
            {
                $this->error("HTTP request did not return 200 OK: ".$code . ".");
                return;
    
            }
            else
            {
                $this->vulnerabilities = \json_decode($response->getBody(), true);
                return;
            }    
        }
        catch (Exception $e)
        {
            $this->error("Exception thrown making HTTP request: ".$e->getMessage() . ".");
            return;
        }
    }

    protected function console_write($string, $style = null, $verbosity = null)
    {
        $styled = $style ? "<$style>$string</$style>" : $string;

        $this->output->write($styled, false, $this->parseVerbosity($verbosity));
    }

}
