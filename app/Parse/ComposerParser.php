<?php
namespace App\Parse;
error_reporting(E_ALL ^ E_DEPRECATED);

use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\File;
use \Symfony\Component\Console\Formatter\OutputFormatterStyle;
use \Nadar\PhpComposerReader\ComposerReader;
use \Nadar\PhpComposerReader\RequireSection;

class ComposerParser implements Parse
{
    private $file = "";

    public function __construct($file) {
        $this->file = $file;
    }

    public function get_packages() : array {
        $reader = new ComposerReader($this->file);
        if (!$reader->canRead()) {
            echo "Could not read composer file " . $this->file . ".";
            return [];
        }
        $packages = $this->__get_packages($reader);

        if (count($packages) == 0)
        {
            echo "No packages found to audit.";
            return [];
        }

        $lock_file = dirname($this->file). DIRECTORY_SEPARATOR . 'composer.lock';
        if (File::exists($lock_file))
        {
            $packages = $this->get_lock_file_packages($lock_file, $packages);
        }
        else {
            echo "Did not find composer lock file found at " . $lock_file . '.' . ' Transitive package dependencies will not be audited.';
        }

        return $packages;
    }

    public function get_coordinates($packages) : array {
        $packages_versions = $this->get_packages_versions($packages);

        $coordinates = $this->__get_coordinates($packages_versions);

        return $coordinates;
    }

    protected function __get_packages($reader) : array {
        $section = new RequireSection($reader);
        $packages = [];
        foreach($section as $package) {
            $packages[$package->name] = $package->constraint;
        }
        return $packages;
    }

    protected function get_lock_file_packages($lock_file, $packages) : array {
        $orig_count = count($packages);
        $data = \json_decode(\file_get_contents($lock_file), true);
        $lfp = $data["packages"];
        foreach ($lfp as $p)  {
            $n = $p["name"];
            $v = $p["version"];
            if (!\array_key_exists($n, $packages))
            { 
                $packages[$n] = $v;
            }
            else if (array_key_exists($n, $packages) && $packages[$n] != $v)
            { 
                $packages[$n] = $v;
            }
        }
        $count = count($packages);

        return $packages;
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
                    echo "Did not determine version operator for constraint " . $constraint . ".";
                    return $v;

            }
        }
        else if (!in_array($constraint[$start], $range_prefix_tokens) && is_numeric($constraint[$start]) && $constraint[$end] == '*')
        {
            return new Version2(str_replace('*', 0, $constraint));
        }
        else
        {
            return $constraint;
        }
    }

    protected function get_packages_versions($packages)
    {
        $packages_versions = [];

        foreach($packages as $package=>$constraint) 
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
                // Need to log this, or something
            }
        }

        return $packages_versions;        
    }

    private function __get_coordinates($packages_versions)
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
