<?php

namespace App\Models\Locale;

use Illuminate\Database\Eloquent\Model;

class LocaleDynamicContent extends Model
{

    const PRODUCT_DESCRIPTION = 1;
    const CUSTOMER_DESCRIPTION = 2;
    const CUSTOMER_NOTES = 3;
    const ATTRIBUTES = 4;
    const ATTRIBUTE_VALUE = 5;
    const DESCRIPTION_ASSOCIATION = 6;
    const VEHICLE_CATEGORY = 7;
    const PART_NOTES = 8;
    const INFO_PAGE_HEADERS = 9;

}
