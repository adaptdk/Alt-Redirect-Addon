<?php

namespace AltDesign\AltRedirect\Commands;

use AltDesign\AltRedirect\Models\Redirect;
use Illuminate\Console\Command;

class ClearOldSlugs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redirect:clear-old-slugs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the static array of old slugs used to create redirects';
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
		Redirect::$oldSlugs = [];
		self::info('Old slugs are cleared');
    }
}
