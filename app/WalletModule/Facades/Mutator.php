<?php

namespace App\WalletModule\Facades;

use App\WalletModule\Internals\Mutation\MutatorManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\WalletModule\Internals\Mutation\MutatorManager mutator(string $mutation, $mutators, int $priority = 0)
 * @method static array getMutators(string $mutation)
 * @method static \App\WalletModule\Internals\Mutation\MutationInterface mutate(\App\WalletModule\Internals\Mutation\MutationInterface $mutation)
 * @method static array mutateBatch(array $mutations)
 */
class Mutator extends Facade
{
    protected static function getFacadeAccessor()
    {
        return MutatorManager::class;
    }
}
