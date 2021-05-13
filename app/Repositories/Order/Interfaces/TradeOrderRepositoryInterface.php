<?php
namespace App\Repositories\Order\Interfaces;

interface TradeOrderRepositoryInterface
{
    public function setChecoutDetails();

    public function isPayLater();
    
    public function canPayLater();
}
