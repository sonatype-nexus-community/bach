<?php
namespace App\Commands;
error_reporting(E_ALL ^ E_DEPRECATED);

use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\File;
use \Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Laminas\Text\Figlet\Figlet;
use App\CycloneDX\CycloneDX;
use App\IQ\IQClient;
use App\Parse\ComposerParser;

class IQ extends Command
{
    protected $styles = [];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'iq 
                            {--file= : The composer package manifest to audit.} 
                            {--application= : Your public application ID from Nexus IQ Server.}
                            {--host=http://localhost:8070 : Your Nexus IQ Servers base URL ex: "http://localhost:8070/"}
                            {--user=admin : Your user name for connecting to Nexus IQ Server ex: "admin".}
                            {--token=admin123 : Your token for connecting to Nexus IQ Server ex: "admin123".}';

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
        
        if (!File::exists($this->option('file')))
        {
            $this->error("The file " . $this->option('file') . " does not exist");
            return;
        }
    
        $file = realpath($this->option('file'));

        $parser = new ComposerParser($file);

        $packages = $parser->get_packages();

        $coordinates = $parser->get_coordinates($packages);

        $cyclonedx = new CycloneDX();

        $sbom = $cyclonedx->create_and_return_sbom($coordinates);

        $iq_client = new IQClient();

        $internal_id = $iq_client->get_internal_application_id($this->option('application'));

        $status_url = $iq_client->submit_sbom($sbom, $internal_id, 'develop');

        $response = $iq_client->poll_status_url($status_url);

        echo PHP_EOL;

        if ($response['policyAction'] == 'None') {
            $this->info('You have composed a masterpiece, no policy actions necessary, compose away!');
            $this->info('Report URL: ' . $response['reportHtmlUrl']);
        } elseif ($response['policyAction'] == 'Warning') {
            $this->warn('Your masterpiece is looking good, but you have some warnings to look at. Pause and reflect on these.');
            $this->warn('Report URL: ' . $response['reportHtmlUrl']);
        } elseif ($response['policyAction'] == 'Failure') {
            $this->error('Put down the wand, time to clean up some policy failures before you compose further!');
            $this->error('Report URL: ' . $response['reportHtmlUrl']);
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