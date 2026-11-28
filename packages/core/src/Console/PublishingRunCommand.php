<?php

namespace Codeart\Core\Console;

use Codeart\Core\Publishing\Actions\PublishDue;
use Codeart\Core\Publishing\PreviewTokens;
use Illuminate\Console\Command;

class PublishingRunCommand extends Command
{
    protected $signature = 'publishing:run';

    protected $description = 'Publish scheduled content that is due and delete expired preview links (scheduled every minute)';

    public function handle(PublishDue $due, PreviewTokens $tokens): int
    {
        $count = $due();
        $tokens->prune();
        $this->components->info("Published {$count}.");

        return self::SUCCESS;
    }
}
