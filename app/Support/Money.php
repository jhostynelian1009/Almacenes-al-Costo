<?php

namespace App\Support;

final class Money
{
    public static function format(string|float|int $amount): string
    {
        return number_format((float) $amount, 2, '.', ',');
    }

    public static function multiply(string|float|int $unitPrice, int $quantity): string
    {
        return bcmul((string) $unitPrice, (string) $quantity, 2);
    }

    public static function add(string|float|int ...$amounts): string
    {
        $total = '0.00';

        foreach ($amounts as $amount) {
            $total = bcadd($total, (string) $amount, 2);
        }

        return $total;
    }

    public static function normalize(string|float|int $amount): string
    {
        return bcadd((string) $amount, '0', 2);
    }
}
