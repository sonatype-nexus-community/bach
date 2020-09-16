<?php
namespace App\Commands;
error_reporting(E_ALL ^ E_DEPRECATED);

use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\File;
use \Symfony\Component\Console\Formatter\OutputFormatterStyle;
use \Nadar\PhpComposerReader\ComposerReader;
use \Nadar\PhpComposerReader\RequireSection;
use Laminas\Text\Figlet\Figlet;
use App\Audit\AuditText;
use App\OSSIndex\OSSIndex;

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

    protected $styles = [];

    public function __construct()
    {
        parent::__construct();
        $this->styles = array
        (
            // 'red' => new OutputFormatterStyle('red', null, ['bold']) //white text on red background
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

        if (count($this->packages) == 0)
        {
            $this->warn("No packages found to audit.");
            return;
        }

        $this->lock_file = dirname($this->file). DIRECTORY_SEPARATOR . 'composer.lock';
        if (File::exists($this->lock_file))
        {
            $this->get_lock_file_packages($this->lock_file);
        }
        else {
            $this->warn("Did not find composer lock file found at ".$this->lock_file). '.' . ' Transitive package dependencies will not be audited.';
        }

        $packages_versions = $this->get_packages_versions();
        $coordinates = $this->get_coordinates($packages_versions);
        
        $ossindex = new OSSIndex();
        $vulnerabilities = $ossindex->get_vulns($coordinates);

        if(count($vulnerabilities) == 0) {
            $this->error("Did not receieve any data from OSS Index API.");
            return;
        }
        else {
            $audit = new AuditText();

            $audit->audit_results($this->packages, $vulnerabilities, $this->output);
        }
    }

    protected function show_logo()
    {
        $figlet = new Figlet();
        $figlet->setFont(dirname(__FILE__) . '/larry3d.flf');
        echo $figlet->render('Bach');

        $figlet->setFont(dirname(__FILE__) . '/pepper.flf');
        echo $figlet->render('By Sonatype & Friends');
    }

    protected function get_packages($reader)
    {
        $section = new RequireSection($reader);
        foreach($section as $package) {
            $this->packages[$package->name] = $package->constraint;
        }
    }

    protected function get_lock_file_packages($lock_file)
    {
        $orig_count = count($this->packages);
        $data = \json_decode(\file_get_contents($lock_file), true);
        $lfp = $data["packages"];
        foreach ($lfp as $p)  {
            $n = $p["name"];
            $v = $p["version"];
            if (!\array_key_exists($n, $this->packages))
            { 
                $this->packages[$n] = $v;
            }
            else if (array_key_exists($n, $this->packages) && $this->packages[$n] != $v)
            { 
                $this->packages[$n] = $v;
            }
        }
        $count = count($this->packages);
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
            //$this->warn("Could not determine version operator for constraint ".$constraint.".");
            return $constraint;
        }
    }

    protected function get_packages_versions()
    {
        $packages_versions = [];

        foreach($this->packages as $package=>$constraint) 
        {
            $c = $constraint;
            if (strpos($constraint, "||") !== false) {
                $c = trim(explode("||", $constraint)[0]);
            }
            else if (strpos($constraint, "|") !== false) {
                $c = trim(explode("|", $constraint)[0]);
            }
            $c = trim($c, "v");
            if ($c == '*')
            {
                $c = '0.1';
            }
            try {
                $v = $this->get_version($c);
                $packages_versions[$package] = $v;
            } catch (\Throwable $th) {
                //
            }
        }

        return $packages_versions;        
    }

    private function get_coordinates($packages_versions)
    {
        $pkgs = [];
        $coordinates["coordinates"] = [];
        foreach($packages_versions as $package=>$version) 
        {
            array_push($coordinates["coordinates"], "pkg:composer/" . $package . "@". $version);
        }
        return $coordinates;
    }
}
