<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use PragmaRX\Google2FA\Google2FA;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $google2fa = new Google2FA;
        $totpSecret = $google2fa->generateSecretKey();

        $email = 'test@example.com';

        /** @var User $user */
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );

        $user->update([
            'two_factor_secret' => encrypt($totpSecret),
            'two_factor_enabled' => true,
        ]);

        if (!$user->ngnWallet()) {
            $ngnWallet = $user->createWallet('Nigerian Naira Wallet', 'NGN_MAIN', 'NGN');
            $ngnWallet->credit(100_000_000, 'Initial Deposit', 'Seed balance');
        }

        if (!$user->cnyWallet()) {
            $cnyWallet = $user->createWallet('Chinese Yuan Wallet', 'CNY_MAIN', 'CNY');
            $cnyWallet->credit(50_000, 'Initial Deposit', 'Seed balance');
        }

        $this->command->info('Test user seeded:');
        $this->command->table(
            ['Field', 'Value'],
            [
                ['Email', 'test@example.com'],
                ['Password', 'password'],
                ['2FA Secret', $totpSecret],
                ['NGN Balance', '1,000,000 NGN'],
                ['CNY Balance', '500 CNY'],
            ]
        );
    }
}
