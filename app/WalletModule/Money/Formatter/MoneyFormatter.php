<?php

namespace App\WalletModule\Money\Formatter;

use App\WalletModule\Money\Currency;
use App\WalletModule\Money\Money;

/**
 * Formats Money objects.
 *
 * @author Frederik Bosch <f.bosch@genkgo.nl>
 */
interface MoneyFormatter
{
    /**
     * Formats a Money object as string.
     *
     * @param Money $money
     *
     * @return string
     *
     * Exception\FormatterException
     */
    public function format(Money $money, Currency $currency);
}
