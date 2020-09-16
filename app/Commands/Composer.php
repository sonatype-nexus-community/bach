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
    
        $file = realpath($this->argument('file'));

        $parser = new ComposerParser($file);

        $packages = $parser->get_packages();

        $coordinates = $parser->get_coordinates($packages);
        
        $ossindex = new OSSIndex();
        $response = $ossindex->get_vulns($coordinates);

        if(count($response) == 0) {
            $this->error("Did not receieve any data from OSS Index API.");
            return 1;
        }
        else {
            $audit = new AuditText();

            $vulns = $audit->audit_results($packages, $response, $this->output);

            if ($vulns > 0) {
                return 1;
            }
            else {
                return 0;
            }
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
}
