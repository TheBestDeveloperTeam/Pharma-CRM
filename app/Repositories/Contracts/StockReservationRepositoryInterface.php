<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface StockReservationRepositoryInterface
{
    public function create(array $data): string;
    public function release(string $franchiseRef, string $reservationRef): bool;
    public function consume(string $franchiseRef, string $reservationRef): bool;
    public function getActiveForOrder(string $franchiseRef, string $orderRef): array;
    public function listForOrder(string $franchiseRef, string $orderRef): array;
}
