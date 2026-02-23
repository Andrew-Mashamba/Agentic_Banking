<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Banking seeders run in dependency order.
     */
    public function run(): void
    {
        $this->command->info('Seeding banking data...');

        $this->call([
            SettingsSeeder::class,
            BankingUsersSeeder::class,
            BankingAccountsSeeder::class,
            BankingKycSeeder::class,
            BankingTrustedDevicesSeeder::class,
            BankingBeneficiariesSeeder::class,
            BankingCorporateSeeder::class,
            BankingTransfersSeeder::class,
            BankingCardlessWithdrawalsSeeder::class,
            BankingCardsSeeder::class,
            BankingLoanProductsSeeder::class,
            BankingLoansSeeder::class,
            BankingFixedDepositsSeeder::class,
            BankingInvestmentProductsSeeder::class,
            BankingInvestmentsSeeder::class,
            BankingWalletSeeder::class,
            BankingNotificationPreferencesSeeder::class,
            BankingServiceRequestsSeeder::class,
            BankingSupportSeeder::class,
            BankingBranchesSeeder::class,
            BankingSecuritySeeder::class,
            BankingTradeSeeder::class,
        ]);

        $this->command->info('Banking seed completed.');
        $this->command->info('Login: admin@agenticbanking.com / manager@agenticbanking.com / john@example.com — password: password');
    }
}
