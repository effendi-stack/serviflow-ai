<?php

namespace App\Services\Invoicing;

class InvoiceCalculator
{
    public function total(float $laborCost, float $partsCost): float
    {
        return round($laborCost + $partsCost, 2);
    }
}
