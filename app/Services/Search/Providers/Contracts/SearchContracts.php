<?php
namespace App\Services\Search\Providers\Contracts;

interface SearchContracts {
    public function processSearch($search);
    public function getSuggestions($search);
    public function getmoreProducts($request);
    public function getmoreModels($request);
}