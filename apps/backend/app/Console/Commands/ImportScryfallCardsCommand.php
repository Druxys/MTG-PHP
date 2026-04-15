<?php

namespace App\Console\Commands;

use App\Services\ScryfallImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cards:import-scryfall {--count=100 : Nombre de nouvelles cartes à sauvegarder} {--delay=100 : Délai en millisecondes entre les appels API} {--max-fetches= : Limite maximale d\'appels à Scryfall}')]
#[Description('Importe des cartes aléatoires depuis Scryfall et les enregistre localement avec image chiffrée')]
class ImportScryfallCardsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ScryfallImportService $scryfallImportService): int
    {
        $targetCount = max(1, (int) $this->option('count'));
        $delayInMs = max(0, (int) $this->option('delay'));
        $maxFetches = $this->option('max-fetches');
        $maxFetches = $maxFetches === null ? $targetCount * 5 : max($targetCount, (int) $maxFetches);

        $savedCount = 0;
        $totalFetched = 0;
        $seenScryfallIds = [];

        $this->newLine();
        $this->info("Target: sauvegarder {$targetCount} nouvelles cartes");
        $this->line("Limite d'appels API: {$maxFetches}");

        while ($savedCount < $targetCount && $totalFetched < $maxFetches) {
            $this->line("Progression: {$savedCount}/{$targetCount} sauvegardées ({$totalFetched} fetch)");

            $card = $scryfallImportService->fetchUniqueRandomCard($seenScryfallIds);
            $totalFetched++;

            if ($card === null) {
                $this->warn('Fetch invalide (erreur API ou doublon déjà vu), nouvelle tentative...');
                $this->delay($delayInMs);

                continue;
            }

            $cardName = (string) ($card['name'] ?? 'Unknown card');
            $this->line("Tentative de sauvegarde: {$cardName}");

            $saved = $scryfallImportService->saveCard($card);

            if ($saved) {
                $savedCount++;
                $this->info("Carte sauvegardée {$savedCount}/{$targetCount}: {$cardName}");
            } else {
                $this->warn("Carte ignorée: {$cardName} (déjà existante ou données invalides)");
            }

            if ($totalFetched % 10 === 0) {
                $successRate = number_format(($savedCount / max(1, $totalFetched)) * 100, 1);
                $remaining = $targetCount - $savedCount;

                $this->newLine();
                $this->line('--- Progress Update ---');
                $this->line("Saved: {$savedCount}/{$targetCount} ({$successRate}% de réussite)");
                $this->line("Total API calls: {$totalFetched}");
                $this->line("Remaining: {$remaining}");
                $this->newLine();
            }

            $this->delay($delayInMs);
        }

        if ($savedCount < $targetCount && $totalFetched >= $maxFetches) {
            $this->warn("Limite d'appels atteinte ({$maxFetches}) avant d'atteindre l'objectif.");
        }

        $this->newLine();
        $this->info("Import terminé: {$savedCount} carte(s) sauvegardée(s) sur {$targetCount} visées.");

        return self::SUCCESS;
    }

    private function delay(int $delayInMs): void
    {
        if ($delayInMs > 0) {
            usleep($delayInMs * 1000);
        }
    }
}
