<?php
namespace App\Commands;

require 'vendor\autoload.php';

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\File;

use LaravelZero\Framework\Components\Logo\FigletString as ZendLogo;
use \Nadar\PhpComposerReader;
use PHLAK\SemVer;

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

    protected $range_prefix_tokens = array('>', '<', '=', '^', '~');
    
    protected $range_suffix_tokens = array('*');

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'composer {file=composer.json}:The composer package manifest to audit.';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Audit Composer dependencies ';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->show_logo();
        if (!File::exists($this->argument('file')))
        {
            $this->error("The file " . $this->argument('file') . " does not exist");
            return;
        }
    
        $this->file = realpath($this->argument('file'));
        $reader = new PhpComposerReader\ComposerReader($this->file);
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
        foreach($this->packages as $package=>$constraint) 
        {
            $this->info($package . ' ' .$constraint . ' (' .$this->get_versions($constraint). ')');
        }        
    }

    protected function show_logo()
    {
        $l = new ZendLogo('auditphp', []);
        echo $l;
    }

    protected function get_packages($reader)
    {
        $section = new PhpComposerReader\RequireSection($reader);
        foreach($section as $package) {
            $this->packages[$package->name] = $package->constraint;
        }
    }

    protected function get_versions($constraint)
    {
        $length = strlen($constraint);
        $start = 0;
        $end = $length - 1;
        if (!in_array($constraint[$start], $this->range_prefix_tokens) && is_numeric($constraint[$start]) && $constraint[$end] != '*')
        {
            return new SemVer\Version($constraint);
        }
        elseif (in_array($constraint[$start], $this->range_prefix_tokens) && !in_array($constraint[$start + 1], $this->range_prefix_tokens) 
            && is_numeric($constraint[$start + 1]) && $constraint[$end] != '*')
        {
            $v = new SemVer\Version(substr($constraint, 1, $length - 1));
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
        elseif (in_array($constraint[$start], $this->range_prefix_tokens) && in_array($constraint[$start + 1], $this->range_prefix_tokens) 
            && $constraint[end] != '*')
        {
            $v = new SemVer\Version($substr($constraint,2, $length - 2));
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
        else if (!in_array($constraint[$start], $this->range_prefix_tokens) && is_numeric($constraint[$start]) && $constraint[$end] == '*')
        {
            return new SemVer\Version(str_replace('*', 0, $constraint));
        }
        else
        {
            $this->warn("Could not determine version operator.");
            return $constraint;
        }
    }
}
