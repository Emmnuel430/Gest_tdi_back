<?php

namespace App\Console\Commands;

use App\Actions\HandlePrayerRequest;
use App\Actions\HandleTsedaka;
use App\Models\Transaction;
use App\Models\Tsedaka;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReconcilePaystackDonations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paystack:reconcile-donations {--perPage=50 : Nombre de transactions à vérifier}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interroge l\'API Paystack pour synchroniser les dons (Tsedaka) qui n\'auraient pas été enregistrés en base';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Début de la réconciliation des dons Paystack...");

        $secret = config('services.paystack.secret_key');
        if (!$secret) {
            $this->error("Clé secrète Paystack manquante (services.paystack.secret_key).");
            return 1;
        }

        $perPage = (int) $this->option('perPage');

        try {
            $response = Http::withToken($secret)
                ->get("https://api.paystack.co/transaction", [
                    'status' => 'success',
                    'perPage' => $perPage,
                ]);

            if (!$response->successful()) {
                $this->error("Échec de communication avec Paystack API: " . $response->body());
                return 1;
            }

            $transactions = $response->json('data') ?? [];
            $this->info(count($transactions) . " transactions récupérées depuis Paystack.");

            $syncedCount = 0;
            $alreadyCount = 0;

            foreach ($transactions as $tx) {
                $metadata = $tx['metadata'] ?? [];
                $type = $metadata['type'] ?? null;

                if (!in_array($type, ['tsedaka', 'prayer-request'], true)) {
                    continue;
                }

                $reference = $tx['reference'];

                // Vérifier si déjà en base
                $exists = Transaction::where('reference', $reference)->exists()
                    || ($type === 'tsedaka' && Tsedaka::where('reference', $reference)->exists());

                if ($exists) {
                    $alreadyCount++;
                    continue;
                }

                // Synchronisation de la transaction manquante (Tsedaka ou Demande de prière)
                DB::transaction(function () use ($tx, $metadata, $reference, $type) {
                    $transaction = Transaction::create([
                        'reference' => $reference,
                        'amount' => $tx['amount'] / 100,
                        'currency' => $tx['currency'] ?? 'XOF',
                        'status' => 'success',
                        'nom' => trim(($metadata['nom'] ?? '') . ' ' . ($metadata['prenom'] ?? '')) ?: 'Client N/A',
                        'email' => $tx['customer']['email'] ?? null,
                        'numero' => $metadata['numero'] ?? null,
                        'type' => $type,
                    ]);

                    if ($type === 'tsedaka') {
                        app(HandleTsedaka::class)->execute($transaction, $metadata);
                    } elseif ($type === 'prayer-request') {
                        app(HandlePrayerRequest::class)->execute($transaction, $metadata);
                    }
                });

                $syncedCount++;
                $this->line("✅ Transaction ({$type}) synchronisée : Réf [{$reference}] - Montant : " . ($tx['amount'] / 100) . " XOF");
            }

            $this->info("Réconciliation terminée avec succès !");
            $this->table(
                ['Statut', 'Nombre'],
                [
                    ['Dons déjà enregistrés', $alreadyCount],
                    ['Nouveaux dons synchronisés', $syncedCount],
                ]
            );

            return 0;

        } catch (\Throwable $e) {
            $this->error("Erreur durant la réconciliation: " . $e->getMessage());
            Log::error("Erreur ReconcilePaystackDonations: " . $e->getMessage());
            return 1;
        }
    }
}
