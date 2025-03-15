<?php

namespace Teleurban\SwiftAuth\Console\Commands;

use Illuminate\Console\Command;

class ExampleCommand extends Command
{
    protected $signature = 'example:command';

    protected $description = 'This is an example command';

    public function handle(): void
    {
        $this->info('This is an example command');
    }
}
