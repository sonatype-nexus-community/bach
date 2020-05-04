<?php
namespace App\Commands;

use Illuminate\Support\Facades\File;
use \Symfony\Component\Console\Formatter\OutputFormatterStyle;
use \Symfony\Component\Console\Helper\ProgressBar;
use LaravelZero\Framework\Commands\Command;
use LaravelZero\Framework\Components\Logo\FigletString as ZendLogo;
use \Nadar\PhpComposerReader\ComposerReader;
use \Nadar\PhpComposerReader\RequireSection;
use PHLAK\SemVer\Version2;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Kodus\Cache\FileCache;
use ComposerInternal\UUID;

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

    protected $cache;

    protected $cached_vulnerabilities = array();

    protected $vulnerabilities = [];

    /**
     * Return the user's home directory.
     */
    protected function get_home() {
        // Cannot use $_SERVER superglobal since that's empty during UnitUnishTestCase
        // getenv('HOME') isn't set on Windows and generates a Notice.
        $home = getenv('HOME');
        if (!empty($home)) {
        // home should never end with a trailing slash.
        $home = rtrim($home, '/');
        }
        elseif (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
        // home on windows
        $home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
        // If HOMEPATH is a root directory the path can end with a slash. Make sure
        // that doesn't happen.
        $home = rtrim($home, '\\/');
        }
        return empty($home) ? NULL : $home;
    }

    const CACHE_DEFAULT_EXPIRATION = 86400;
    const CACHE_DIR_MODE = 0775;
    const CACHE_FILE_MODE = 0664;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'composer {file} 
                            {--server= : (Optional) IQ Server URL to report components and vulnerabilities to.}
                            {--appid= : (Optional) IQ Server application id. Default is the Composer file directory name.}
                            {--iquser= : (Optional) IQ Server user to authenticate with.} 
                            {--iqpass= : (Optional) IQ Serve password to authenticate with.}
                            {--ossuser= : (Optional) OSS Index user to authenticate with.} 
                            {--osspass= : (Optional) Password for OSS Index user to authenticate with.}';

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
        $this->show_logo();
        if (!File::exists($this->argument('file')))
        {
            $this->error("The file " . $this->argument('file') . " does not exist");
            return;
        }
        $cachePath = $this->get_home() . DIRECTORY_SEPARATOR . ".cache";
        File::isDirectory($cachePath) or File::makeDirectory($cachePath, 0777, true, true);
        $this->cache = new FileCache($cachePath, self::CACHE_DEFAULT_EXPIRATION, self::CACHE_DIR_MODE, self::CACHE_FILE_MODE);
        $this->info("Cache directory is $cachePath.");
        $this->cache->cleanExpired();
        
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
            $this->warn("No packages found to audit.");
            return;
        }

        $this->lock_file = dirname($this->file). DIRECTORY_SEPARATOR . 'composer.lock';
        if (File::exists($this->lock_file))
        {
            $this->info("Composer lock file found at ".$this->lock_file). '.';
            $this->get_lock_file_packages($this->lock_file);
        }
        else {
            $this->warn("Did not find composer lock file found at ".$this->lock_file). '.' . ' Transitive package dependencies will not be audited.';
        }            
        $this->info(count($this->packages) . " total packages to audit.");
        $this->get_packages_versions();
        $this->get_coordinates();
        $this->get_vulns();
        if(count($this->vulnerabilities) == 0)
        {
            $this->error("Did not receieve any vulnerability data from OSS Index API.");
            return;
        }
        else
        {
            $this->print_results();
            if ($this->option('server') != "" && $this->option('iquser') != "" && $this->option('iqpass') != "") {
                $this->report_vulns();
            }
            return;
        }
    }

    protected function show_logo()
    {
        $l = new ZendLogo('Bach', []);
        $this->info($l);
        $this->info(app('git.version'));
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

            if (\array_key_exists("require", $p))
            {
                foreach ($p["require"] as $rn => $rv) {
                    if (!\array_key_exists($rn, $this->packages))
                    { 
                        $this->packages[$rn] = $rv;
                    }
                    else if (array_key_exists($rn, $this->packages) && $this->packages[$rn] != $rv)
                    { 
                        $this->packages[$rn] = $rv;
                    }
                }
            }

            if (\array_key_exists("require-dev", $p))
            {
                foreach ($p["require-dev"] as $rn => $rv) {
                    if (!\array_key_exists($rn, $this->packages))
                    { 
                        $this->packages[$rn] = $rv;
                    }
                    else if (array_key_exists($rn, $this->packages) && $this->packages[$rn] != $rv)
                    { 
                        $this->packages[$rn] = $rv;
                    }
                }
            }
        }
        $count = count($this->packages);
        if ($count > $orig_count)
        {
            $this->info("Added ".($count - $orig_count). " packages from Composer lock file at ".$lock_file.".");
        }
    }

    protected function get_version($package, $constraint)
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
                    $this->warn("Could not determine version operator for $package constraint $constraint. Exact version match will be used.");
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
                    $this->warn("Could not determine version operator for $package constraint $constraint. Exact version match will be used.");
                    return $v;

            }
        }
        else if (!in_array($constraint[$start], $range_prefix_tokens) && is_numeric($constraint[$start]) && $constraint[$end] == '*')
        {
            return new Version2(str_replace('*', 0, $constraint));
        }
        else
        {
            $this->warn("Could not determine version operator for package $package constraint $constraint. Exact match will be used.");
            return $constraint;
        }
    }

    protected function get_packages_versions()
    {
        $this->info("");
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
                $v = $this->get_version($package, $c);
                $this->info($package . ' ' .$constraint . ' (' .$v. ')', 'v');
                $this->packages_versions[$package] = $v;
            } catch (\Throwable $th) {
                if (strpos($c, 'dev-') === 0) {
                    $this->warn("The constraint $c is a VCS branch. Only exact version matches are possible.");
                }
                else {
                    $this->error("Could not determine version for package ".$package . " using constraint (".$c.")". ": ".$th->getMessage().". Package will be skipped.");
                }
            }
        }        
    }

    protected function get_coordinates()
    {
        foreach($this->packages_versions as $package=>$version) 
        {
            $key = \base64_encode("pkg:composer/" . $package . "@".$version);
            if ($this->cache->has($key))
            {
                $this->cached_vulnerabilities += array("pkg:composer/" . $package . "@".$version => json_decode($this->cache->get($key)));
            }
            else 
            {
                array_push($this->coordinates["coordinates"], "pkg:composer/" . $package . "@".$version);
            }
        }
    }

    protected function get_vulns()
    {
        $this->info("");
        $c = count($this->cached_vulnerabilities);
        $qc = count($this->coordinates["coordinates"]);
        $this->vulnerabilities = $this->cached_vulnerabilities;
        $this->info("Vulnerability data for $c packages is cached.");
        if (count($this->coordinates["coordinates"]) == 0)
        {
            return;
        }
        $this->info("Querying OSS Index for $qc vulnerabilities...");
        $auth = array();
        if ($this->option('user') != "" && $this->option('pass') != "")
        {
            $u = $this->option('ossuser');
            $p = $this->option('osspass');
            $this->info("Using authentication for user $u.");
            $auth = [$this->option('user'), $this->option('pass')];
        }
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => 'https://ossindex.sonatype.org/api/',
            // You can set any number of default request options.
            'timeout'  => 100.0,
            'auth' => $auth
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
                $r = json_decode($response->getBody(), true);
                foreach ($r as $v) {
                    $this->cache->set(\base64_encode($v['coordinates']), \json_encode($v));
                }
                $this->vulnerabilities += $r;
                return;
            }    
        }
        catch (Exception $e)
        {
            $this->error("Exception thrown making HTTP request: ".$e->getMessage() . ".");
            return;
        }
    }

    protected function print_results() {
        $this->info("");
        $this->info("Audit results:");
        $this->info("==============");
        foreach($this->vulnerabilities as $ov)
        {
            $v = (array) $ov;
            if (!array_key_exists("coordinates", $v))
            {
                continue;
            }
            $p = "PACKAGE: " . str_replace("pkg:composer/", "", $v['coordinates']);
            $d = array_key_exists("description", $v) ? "DESC: " . $v['description'] : "";
            $is_vulnerable = array_key_exists("vulnerabilities", $v) ? (count($v['vulnerabilities']) > 0 ? true: false) : false;
            $is_vulnerable_text = "VULN: " . ($is_vulnerable ? "Yes" : "No");
            if(!$is_vulnerable) {
                $this->info($p . " " . $d . " " . $is_vulnerable_text, 'v');
            }
            else {
                $this->error($p . " " . $d . " " . $is_vulnerable_text);
                foreach($v["vulnerabilities"] as $vuln)
                {
                    foreach($vuln as $key => $value)
                    {
                        $this->info("  " . $key . ": ".$value);
                    }
                }
            }                
        }
    }

    protected function report_vulns() {
        $this->info("");
        $server =  $this->option('server');
        $u =  $this->option('iquser');
        $p =  $this->option('iquser');
        $appid = $this->option('appid') ? $this->option('appid') : \basename(\dirname($this->file));
        $this->info("Using IQ Server at $server with user $u for app id $appid...");
        $auth = [$this->option('iquser'), $this->option('iqpass')];
        $internal_appid = "";
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => $server,
            // You can set any number of default request options.
            'timeout'  => 100.0,
            'auth' => $auth
        ]);
        
        try
        {
            $response = $client->get("/api/v2/applications?publicId=$appid");
            $code = $response->getStatusCode();
            if ($code != 200)
            {
                $this->error("HTTP request did not return 200 OK: ".$code . ".");
                return;
    
            }
            else
            {
                $body = $response->getBody();
                $r = json_decode($body, true);
                $this->info("JSON response: $body", 'v');
                $apps = $r["applications"];
                if (count($apps) == 0)
                {
                    $this->error("Invalid IQ Server application id: $appid");
                    return;
                }
                $internal_appid = $apps[0]["id"];
                $this->info("IQ Server internal app id is $internal_appid.", 'v');
            }    
        }
        catch (Exception $e)
        {
            $this->error("Exception thrown making HTTP request: ".$e->getMessage() . ".");
            return;
        }
        
        $uuid = \ComposerInternal\UUID::v4();
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<bom xmlns=\"http://cyclonedx.org/schema/bom/1.1\" version=\"1\" serialNumber=\"urn:uuid:$uuid\"\nxmlns:v=\"http://cyclonedx.org/schema/ext/vulnerability/1.0\">\n\t<components>";
        $total = 0;
        $reported = 0;
        foreach($this->vulnerabilities as $ov)
        {
            $v = (array) $ov;
            if (!array_key_exists("coordinates", $v))
            {
                continue;
            }
            $total++;
            $is_vulnerable = array_key_exists("vulnerabilities", $v) ? (count($v['vulnerabilities']) > 0 ? true: false) : false;
            $purl = $v['coordinates'];
            $p = \explode("@", str_replace("pkg:composer/", "", $purl));
            $n = explode("/", $p[0]);
            $name = count($n) == 2 ? $n[1] : $n[0];
            $group = count($n) == 2 ? $n[0] : "";
            $version = $p[1];
            $xml .= "\t\t<component type =\"library\">\n";
            $xml .= "\t\t\t<group>$group</group>\n";
            $xml .= "\t\t\t<name>$name</name>\n";
            $xml .= "\t\t\t<version>$version</version>\n";
            $xml .= "\t\t\t<purl>$purl</purl>\n";
            if ($is_vulnerable)
            {
                $xml .= "\t\t\t<v:vulnerabilities>\n";
                foreach ($v["vulnerabilities"] as $vulnerability) {
                    if (!\array_key_exists("cve", $vulnerability))
                    {
                        continue;
                    }
                    else {
                        $vid = array_key_exists("cve", $vulnerability) ? $vulnerability->cve : $vulnerability -> cwe;
                        $xml .= "\t\t\t\t<v:vulnerability ref=\"$purl\">\n";
                        $xml .= "\t\t\t\t\t<v:id>$vid</v:id>\n";
                        $xml .= "\t\t\t\t</v:vulnerability>\n";
                        $reported++;
                    }
                }
                $xml .= "\t\t\t</v:vulnerabilities>\n";
            }
            
            $xml .= "\t\t</component>\n";
        }
        $xml .= "\t</components>\n</bom>";
        $this->info("Software BOM:\n$xml", 'v');
        
        try
        {
            $options = [
                'headers' => [
                    'Content-Type' => 'application/xml; charset=UTF8',
                ],
                'body' => $xml,
            ];
            $response = $client->post("/api/v2/scan/applications/$internal_appid/sources/bach?stageId=develop", $options);
            $code = $response->getStatusCode();
            if ($code != 202)
            {
                $this->error("HTTP request did not return 202: ".$code . ".");
                return;
    
            }
            else
            {
                $body = $response->getBody();
                $r = json_decode($body, true);
                $this->info("JSON response: $body", 'v');
                $statusUrl = $r["statusUrl"];
                $this->info("$total total components, $reported vulnerabilities reported.");
                $this->info("IQ Server report status is at $server"."/". $statusUrl.".");
            }    
        }
        catch (Exception $e)
        {
            $this->error("Exception thrown making HTTP request: ".$e->getMessage() . ".");
            return;
        }

    }
}
