<?php

namespace App\Console\Commands;

use App\Models\Prompt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PrunePromptsCommand extends Command
{
    protected $signature = 'gueass:prune-prompts
                            {--days= : Idade mínima em dias (padrão: PROMPT_RETENTION_DAYS)}';

    protected $description = 'Remove prompts do histórico mais antigos que o período de retenção';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('privacy.prompt_retention_days', 90));

        if ($days < 1) {
            $this->error('O período de retenção precisa ser de pelo menos 1 dia.');

            return self::FAILURE;
        }

        $deleted = Prompt::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        Log::info('gueass:prune-prompts', [
            'deleted' => $deleted,
            'days' => $days,
        ]);

        $this->info("Removidos {$deleted} prompt(s) com mais de {$days} dia(s).");

        return self::SUCCESS;
    }
}
