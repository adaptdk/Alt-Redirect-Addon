<?php

namespace AltDesign\AltRedirect\Commands;

use AltDesign\AltRedirect\Models\Redirect;
use Illuminate\Console\Command;

class ClearOldUris extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redirect:clear-old-uris';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the static array of old uris used to create redirects';
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
		Redirect::$oldUris = [];
		self::info('Old uris are cleared');
    }
}
