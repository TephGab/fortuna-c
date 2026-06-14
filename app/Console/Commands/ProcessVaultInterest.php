<?php
// app/Console/Commands/ProcessVaultInterest.php

namespace App\Console\Commands;

use App\Models\Vault;
use App\Services\Vault\VaultService;
use App\Helpers\MoneyHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessVaultInterest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vaults:process-interest 
                            {--force : Force calculation even if already done today}
                            {--vault= : Process only a specific vault ID (for testing)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate daily interest for all locked vaults and process matured vaults';

    /**
     * Execute the console command.
     */
    public function handle(VaultService $vaultService)
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('     VAULT INTEREST PROCESSING - ' . now()->format('Y-m-d H:i:s'));
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        // Check if specific vault is requested
        if ($this->option('vault')) {
            $vaultId = $this->option('vault');
            $this->info("🎯 Processing specific vault ID: {$vaultId}");
            $this->newLine();
            
            $vault = Vault::find($vaultId);
            if (!$vault) {
                $this->error("❌ Vault with ID {$vaultId} not found!");
                return Command::FAILURE;
            }
            
            $this->processSingleVault($vaultService, $vault);
        } else {
            // Process all vaults
            $interestResults = $this->processDailyInterest($vaultService);
            $this->newLine();
            
            $maturityResults = $this->processMaturedVaults($vaultService);
            $this->newLine();
            
            $this->displaySummary($interestResults, $maturityResults);
        }
        
        return Command::SUCCESS;
    }

    /**
     * Process a single vault (for testing)
     */
    private function processSingleVault(VaultService $vaultService, Vault $vault): void
    {
        $this->info("📊 Vault Details:");
        $this->line("   • ID: {$vault->id}");
        $this->line("   • Name: {$vault->name}");
        $this->line("   • Type: {$vault->type}");
        $this->line("   • Status: {$vault->status}");
        $this->line("   • Balance: " . MoneyHelper::format($vault->balance, $vault->currency_code));
        $this->line("   • Interest Rate: {$vault->interest_rate}%");
        $this->newLine();
        
        // Calculate interest
        $balanceBefore = $vault->balance;
        $vaultService->calculateInterest($vault);
        $vault->refresh();
        $interestEarned = $vault->balance - $balanceBefore;
        
        $this->info("💰 Interest Calculation Result:");
        $this->line("   • Balance before: " . MoneyHelper::format($balanceBefore, $vault->currency_code));
        $this->line("   • Interest earned: " . MoneyHelper::format($interestEarned, $vault->currency_code));
        $this->line("   • Balance after: " . $vault->formatted_balance);
        
        // Check if vault is matured
        if ($vault->matures_at && $vault->matures_at <= now() && $vault->status === Vault::STATUS_LOCKED) {
            $this->newLine();
            $this->info("🎉 Vault has reached maturity date!");
            $vault->update([
                'status' => Vault::STATUS_MATURED,
                'withdrawable_balance' => $vault->balance,
            ]);
            $this->line("   • Status updated to: MATURED");
        }
    }

    /**
     * Process daily interest for all active locked vaults
     */
    private function processDailyInterest(VaultService $vaultService): array
    {
        // Get all vaults that earn interest
        $vaults = Vault::where('type', '!=', 'flexible')
            ->whereNotIn('status', [Vault::STATUS_CLOSED, Vault::STATUS_MATURED])
            ->where('balance', '>', 0)
            ->get();

        if ($vaults->isEmpty()) {
            $this->warn('⚠️  No active vaults found that need interest calculation.');
            return ['total' => 0, 'processed' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];
        }

        $this->info("📈 Found {$vaults->count()} vaults to process");
        $this->newLine();

        $processed = 0;
        $skipped = 0;
        $errors = 0;
        $details = [];

        $bar = $this->output->createProgressBar($vaults->count());
        $bar->start();

        foreach ($vaults as $vault) {
            $cacheKey = 'vault_interest_calculation_' . $vault->id . '_' . now()->format('Y-m-d');
            
            // Skip if already calculated today (unless force flag is used)
            if (!$this->option('force') && cache()->has($cacheKey)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            try {
                $balanceBefore = $vault->balance;
                $vaultService->calculateInterest($vault);
                $vault->refresh();
                $interestEarned = $vault->balance - $balanceBefore;
                
                if ($interestEarned > 0) {
                    $details[] = [
                        'vault_id' => $vault->id,
                        'vault_name' => $vault->name,
                        'interest_rate' => $vault->interest_rate,
                        'balance_before' => MoneyHelper::format($balanceBefore, $vault->currency_code),
                        'interest_earned' => MoneyHelper::format($interestEarned, $vault->currency_code),
                        'balance_after' => $vault->formatted_balance,
                    ];
                    $processed++;
                }
                
            } catch (\Exception $e) {
                $errors++;
                $this->error("\n❌ Vault ID {$vault->id}: " . $e->getMessage());
                Log::error('Vault interest calculation failed', [
                    'vault_id' => $vault->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if (!empty($details) && $this->option('verbose')) {
            $this->table(
                ['Vault ID', 'Vault Name', 'Rate', 'Balance Before', 'Interest', 'Balance After'],
                $details
            );
        }

        return [
            'total' => $vaults->count(),
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
            'details' => $details,
        ];
    }

    /**
     * Process vaults that have reached their maturity date
     */
    private function processMaturedVaults(VaultService $vaultService): array
    {
        $maturedVaults = Vault::where('type', '!=', 'flexible')
            ->where('status', Vault::STATUS_LOCKED)
            ->where('matures_at', '<=', now())
            ->get();

        if ($maturedVaults->isEmpty()) {
            $this->warn('⚠️  No vaults have reached maturity today.');
            return ['total' => 0, 'processed' => 0, 'errors' => 0, 'details' => []];
        }

        $this->info("🎉 Found {$maturedVaults->count()} vaults that have matured");
        $this->newLine();

        $processed = 0;
        $errors = 0;
        $details = [];

        $bar = $this->output->createProgressBar($maturedVaults->count());
        $bar->start();

        foreach ($maturedVaults as $vault) {
            try {
                $vaultService->calculateInterest($vault);
                $vault->refresh();
                
                $finalBalance = $vault->formatted_balance;
                $totalInterest = $vault->formatted_interest_earned;
                
                $vault->update([
                    'status' => Vault::STATUS_MATURED,
                    'withdrawable_balance' => $vault->balance,
                ]);
                
                DB::table('vault_transactions')->insert([
                    'vault_id' => $vault->id,
                    'user_id' => $vault->user_id,
                    'type' => 'maturity',
                    'amount' => 0,
                    'balance_after' => $vault->balance,
                    'currency' => $vault->currency_code,
                    'description' => 'Vault matured - funds are now available for withdrawal',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $details[] = [
                    'vault_id' => $vault->id,
                    'vault_name' => $vault->name,
                    'lock_days' => $vault->type_config['lock_days'],
                    'final_balance' => $finalBalance,
                    'total_interest' => $totalInterest,
                ];
                
                $processed++;
                
            } catch (\Exception $e) {
                $errors++;
                $this->error("\n❌ Failed to process matured vault ID {$vault->id}: " . $e->getMessage());
                Log::error('Matured vault processing failed', [
                    'vault_id' => $vault->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        return [
            'total' => $maturedVaults->count(),
            'processed' => $processed,
            'errors' => $errors,
            'details' => $details,
        ];
    }

    /**
     * Display the final summary
     */
    private function displaySummary(array $interestResults, array $maturityResults): void
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('                     PROCESSING SUMMARY');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        $this->info('📈 INTEREST CALCULATION:');
        $this->line("   • Total vaults checked:    {$interestResults['total']}");
        $this->line("   • Interest added:          {$interestResults['processed']}");
        $this->line("   • Already processed today: {$interestResults['skipped']}");
        $this->line("   • Errors:                  {$interestResults['errors']}");
        
        $this->newLine();
        
        $this->info('🎉 MATURITY PROCESSING:');
        $this->line("   • Vaults matured:          {$maturityResults['processed']}");
        $this->line("   • Errors:                  {$maturityResults['errors']}");
        
        $this->newLine();
        
        $totalInterest = collect($interestResults['details'])->sum(function ($item) {
            preg_match('/[\d.]+/', str_replace('$', '', $item['interest_earned'] ?? '0'), $matches);
            return floatval($matches[0] ?? 0);
        });
        
        if ($totalInterest > 0) {
            $this->info("💰 TOTAL INTEREST ADDED TODAY: $" . number_format($totalInterest, 2));
        }
        
        $this->newLine();
        $this->info('✅ Processing completed at: ' . now()->format('Y-m-d H:i:s'));
        $this->info('═══════════════════════════════════════════════════════════');
        
        Log::info('Daily vault interest processing completed', [
            'interest_processed' => $interestResults['processed'],
            'interest_errors' => $interestResults['errors'],
            'maturity_processed' => $maturityResults['processed'],
            'maturity_errors' => $maturityResults['errors'],
            'total_interest_added' => $totalInterest,
        ]);
    }
}