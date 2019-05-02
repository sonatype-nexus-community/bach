<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Storage;
use Illuminate\Support\Facades\File;

class Composer extends Command
{
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
        if (File::exists($this->argument('file')))
        {
            $this->info("File exists.");
        }
        else
        {
            $this->error('The file does not exist.');
        }
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
