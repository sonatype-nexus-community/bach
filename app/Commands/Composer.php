<?php
namespace App\Commands;

error_reporting(E_ALL ^ E_DEPRECATED);

use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\File;
use \Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Laminas\Text\Figlet\Figlet;
use App\Audit\AuditText;
use App\OSSIndex\OSSIndex;
use App\CycloneDX\CycloneDX;
use App\Parse\ComposerParser;

class Composer extends Command
{
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
    public function handle() : int
    {
        foreach ($this->styles as $key => $value) {
            $this->output->getFormatter()->setStyle($key, $value);
        }

        $this->showLogo();

        if (!File::exists($this->argument('file'))) {
            $this->error("The file " . $this->argument('file') . " does not exist");
            return 1;
        }

        $file = realpath($this->argument('file'));

        $parser = new ComposerParser($file);

        $packages = $parser->getPackages();

        $coordinates = $parser->getCoordinates($packages);

        $ossindex = new OSSIndex();

        $response = $ossindex->getVulns($coordinates);

        if (count($response) == 0) {
            $this->error("Did not receive any data from OSS Index API.");
            return 1;
        } else {
            $audit = new AuditText();

            $vulns = $audit->auditResults($response, $this->output);

            if ($vulns > 0) {
                return 1;
            } else {
                return 0;
            }
        }
    }

    protected function showLogo()
    {
        $figlet = new Figlet();
        $figlet->setFont(dirname(__FILE__) . '/larry3d.flf');
        echo $figlet->render('Bach');

        $figlet->setFont(dirname(__FILE__) . '/pepper.flf');
        echo $figlet->render('By Sonatype & Friends');
    }
}
