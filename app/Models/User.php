<?php

namespace App\Models;

use App\WalletModule\Contracts\Walletable;
use App\WalletModule\Facades\Walletable as WalletableFacade;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret'])]
class User extends Authenticatable implements Walletable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
        ];
    }

    /**
     * Get all wallets belonging to this user.
     */
    public function wallets()
    {
        return $this->morphMany(config('walletable.models.wallet'), 'walletable');
    }

    /**
     * Get the user's NGN wallet.
     */
    public function ngnWallet(): ?Wallet
    {
        return $this->wallets()->where('currency', 'NGN')->first();
    }

    /**
     * Get the user's CNY wallet.
     */
    public function cnyWallet(): ?Wallet
    {
        return $this->wallets()->where('currency', 'CNY')->first();
    }

    /**
     * Create a wallet for this user.
     */
    public function createWallet(string $label, string $tag, string $currency): Wallet
    {
        return WalletableFacade::create($this, $label, $tag, $currency);
    }

    public function getOwnerName(): string
    {
        return $this->name;
    }

    public function getOwnerEmail(): string
    {
        return $this->email;
    }

    public function getOwnerID(): string
    {
        return (string) $this->getKey();
    }

    public function getOwnerMorphName(): string
    {
        return 'user';
    }
}
