<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire')]
#[Description('Batalkan order pending yang sudah melewati batas waktu pembayaran')]
class ExpirePendingOrders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OrderService $orders): int
    {
        $count = $orders->expireOverdueOrders();

        $this->info("{$count} order ditandai expired.");

        return self::SUCCESS;
    }
}
