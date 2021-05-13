<?php

namespace App\Services\Checkout;

use App\Helpers\Currency;
use App\Models\Carriage;
use App\Models\CMS\Website\CarriageEtaCharge;
use App\Models\CMS\Website\CarriageInternationalEtaCharge;
use App\Models\CMS\Website\CarriageRule;
use App\Models\CMS\Website\CarriageValue;
use App\Models\ZonePostcode;
use Carbon\Carbon;

class DeliveryMethod
{
    public static function getMethods($basket)
    {
        $singleShipment = [];
        $multipleShipments = [];
        $collectionShipment = [];
        $dispatch = [
            'from' => 1,
            'to' => 1,
        ];

        if (!empty($basket['products'])) {
            foreach ($basket['products'] as $product) {
                $productDetails['product_id'] = $product['product_id'];
                $productDetails['product_code'] = $product['product_code'];
                $productDetails['product_name'] = $product['product_name'];
                $productDetails['vechile_name'] = $product['vechile_name'];
                $productDetails['brand_name'] = $product['brand_name'];
                $productDetails['frontend_code'] = $product['frontend_code'];
                $productDetails['frontend_code_label'] = $product['frontend_code_label'];
                $productDetails['qty'] = $product['qty'];
                $productDetails['price'] = $product['price'];
                $productDetails['eta'] = $product['eta'];
                $productDetails['item_total'] = $product['item_total'];
                $productDetails['product_kit_items'] = $product['product_kit_items'] ?? null;
                // weight default to 0 as special product dont have weight
                $productDetails['weight'] = isset($product['weight']) ? $product['weight'] : null;
                // is_dangerous default to 0 as special product dont have is_dangerous
                $productDetails['is_dangerous'] = $product['is_dangerous'] ?? 0;
                $qtyAvailable = (isset($product['stock']) && $product['stock'] >= $product['qty']);
                if (!$qtyAvailable && $product['eta'] > $dispatch['to']) {
                    $dispatch['to'] = $product['eta'];
                }
                if (!$qtyAvailable && $product['eta'] < $dispatch['from']) {
                    $dispatch['from'] = $product['eta'];
                }
                if (empty($product['collection_item']) || isTradeSite()) {
                    $singleShipment['dispatch'] = $dispatch;
                    $singleShipment['products'][] = $productDetails;

                    if (is_null($productDetails['weight'])) {
                        $singleShipment['skip_weight_rule'] = true;
                    }
                    $singleShipment['weight'] = !empty($singleShipment['weight']) ? $singleShipment['weight'] + ($productDetails['weight'] * $productDetails['qty']) : ($productDetails['weight'] * $productDetails['qty']);
                    $singleShipment['price'] = !empty($singleShipment['price']) ? $singleShipment['price'] + ($productDetails['price'] * $productDetails['qty']) : ($productDetails['price'] * $productDetails['qty']);

                    $singleShipment['is_dangerous'] = isset($singleShipment['is_dangerous']) ? $singleShipment['is_dangerous'] : 0;
                    $singleShipment['is_dangerous'] = !empty($productDetails['is_dangerous']) ? $productDetails['is_dangerous'] : $singleShipment['is_dangerous'];
                    $multipleShipments = static::getMultipleShipments($product, $productDetails, $multipleShipments);
                } else {
                    $collectionShipment['dispatch'] = $dispatch;
                    $collectionShipment['products'][] = $productDetails;

                    if (is_null($productDetails['weight'])) {
                        $collectionShipment['skip_weight_rule'] = true;
                    }
                    $collectionShipment['weight'] = !empty($collectionShipment['weight']) ? $collectionShipment['weight'] + ($productDetails['weight'] * $productDetails['qty']) : ($productDetails['weight'] * $productDetails['qty']);
                    $collectionShipment['price'] = !empty($collectionShipment['price']) ? $collectionShipment['price'] + ($productDetails['price'] * $productDetails['qty']) : ($productDetails['price'] * $productDetails['qty']);
                    $collectionShipment['is_dangerous'] = isset($collectionShipment['is_dangerous']) ? $collectionShipment['is_dangerous'] : 0;
                    $collectionShipment['is_dangerous'] = !empty($productDetails['is_dangerous']) ? $productDetails['is_dangerous'] : $collectionShipment['is_dangerous'];
                }
            }
            if (count($multipleShipments) < 2) {
                $multipleShipments = [];
            }
        }

        // working hour to minus cutoff hours
        $cutoffHour = config('wmo_website.franchise.cutoff_time') ?? 0;

        @date_default_timezone_set(config('wmo_website.timezone'));
        $currentHour = date('H');
        if ($currentHour > $cutoffHour) {
            // if dispatch day is today add one day to dispatch day
            if (isset($singleShipment['dispatch']['to']) && $singleShipment['dispatch']['to'] == 1) {
                $singleShipment['dispatch']['to']++;
            }
            if (isset($multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['dispatch']['to']) && $multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['dispatch']['to'] == 1) {
                $multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['dispatch']['to']++;
            }
            if (isset($multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['dispatch']['to']) && $multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['dispatch']['to'] == 1) {
                $multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['dispatch']['to']++;
            }
        }

        $holidays = config('wmo_website.holidays');
        if (isset($singleShipment['dispatch']['to'])) {
            $singleShipment['dispatch']['to'] = DeliveryMethod::getAvailableDate($singleShipment['dispatch']['to'] - 1, $holidays);
        }
        if (isset($multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['dispatch']['to'])) {
            $multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['dispatch']['to'] = DeliveryMethod::getAvailableDate($multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['dispatch']['to'] - 1, $holidays);
        }
        if (isset($multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['dispatch']['to'])) {
            $multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['dispatch']['to'] = DeliveryMethod::getAvailableDate($multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['dispatch']['to'] - 1, $holidays);
        }

        return [
            config('constant.checkout.SHIPMENT_TOGETHER_TYPE') => $singleShipment,
            config('constant.checkout.SHIPMENT_SEPARATE_TYPE') => $multipleShipments,
            config('constant.checkout.SHIPMENT_COLLECTION_TYPE') => $collectionShipment,
        ];
    }

    public static function getMultipleShipments($product, $productDetails, $multipleShipments = [])
    {
        $firstDispatch = $multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['dispatch'] ?? [
            'from' => 1,
            'to' => 1,
        ];
        $secondDispatch = $multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['dispatch'] ?? [
            'from' => 1,
            'to' => 1,
        ];

        $qtyAvailable = (isset($product['stock']) && $product['stock'] >= $product['qty']);
        if ($qtyAvailable || $product['eta'] <= config('constant.checkout.FIRST_SHIPMENT_MAX_ETA')) {
            if (!$qtyAvailable && $product['eta'] > $firstDispatch['to']) {
                $firstDispatch['to'] = $product['eta'];
            }
            if (!$qtyAvailable && $product['eta'] < $firstDispatch['from']) {
                $firstDispatch['from'] = $product['eta'];
            }
            $multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['dispatch'] = $firstDispatch;
            $multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['products'][] = $productDetails;

            if (is_null($productDetails['weight'])) {
                $multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['skip_weight_rule'] = true;
            }
            $multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['weight'] = !empty($multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['weight']) ? $multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['weight'] + ($productDetails['weight'] * $productDetails['qty']) : ($productDetails['weight'] * $productDetails['qty']);
            $multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['price'] = !empty($multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['price']) ? $multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['price'] + ($productDetails['price'] * $productDetails['qty']) : ($productDetails['price'] * $productDetails['qty']);

            $multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['is_dangerous'] = isset($multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['is_dangerous']) ? $multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['is_dangerous'] : 0;
            $multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['is_dangerous'] = !empty($productDetails['is_dangerous']) ? $productDetails['is_dangerous'] : $multipleShipments[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['is_dangerous'];
        } else {
            if ($product['eta'] > $secondDispatch['to']) {
                $secondDispatch['to'] = $product['eta'];
            }
            if ($product['eta'] < $secondDispatch['from']) {
                $secondDispatch['from'] = $product['eta'];
            }
            $multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['dispatch'] = $secondDispatch;
            $multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['products'][] = $productDetails;

            if (is_null($productDetails['weight'])) {
                $multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['skip_weight_rule'] = true;
            }
            $multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['weight'] = !empty($multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['weight']) ? $multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['weight'] + ($productDetails['weight'] * $productDetails['qty']) : ($productDetails['weight'] * $productDetails['qty']);
            $multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['price'] = !empty($multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['price']) ? $multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['price'] + ($productDetails['price'] * $productDetails['qty']) : ($productDetails['price'] * $productDetails['qty']);

            $multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['is_dangerous'] = isset($multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['is_dangerous']) ? $multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['is_dangerous'] : 0;
            $multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['is_dangerous'] = !empty($productDetails['is_dangerous']) ? $productDetails['is_dangerous'] : $multipleShipments[config('constant.checkout.SECOND_SHIPMENT_TYPE')]['is_dangerous'];
        }
        return $multipleShipments;
    }

    public static function getCarriages($checkoutSession, $deliveryMethods, $collection)
    {
        if (empty($checkoutSession['user_addresses']) || !empty($collection)) {
            return [
                'carriages' => collect([]),
                'seperateCarriages' => [
                    config('constant.checkout.FIRST_SHIPMENT_TYPE') => collect([]),
                    config('constant.checkout.SECOND_SHIPMENT_TYPE') => collect([])
                ]
            ];
        }
        $shippingCountryId = $checkoutSession['user_addresses']['shipping']['country_id'] ?? null;
        $postcode = $checkoutSession['user_addresses']['shipping']['postcode'] ?? null;

        $holidays = config('wmo_website.holidays');

        $postcode = substr($postcode, 0, 3);
        $zone = ZonePostcode::select('zone_id')->join('zones as z', 'z.id', 'zone_postcodes.zone_id')->where('z.country_id', $shippingCountryId)->whereRaw('LEFT(postcode,3) = "' . $postcode . '"')->first();
        $zoneId = $zone->zone_id ?? null;

        $carriages = config('wmo_website.carriage_rules');

        foreach ($carriages as $ruleId => &$carriageRule) {
            if ($carriageRule['rule_type'] == CarriageRule::DOMESTIC_TYPE) {
                foreach ($carriageRule['carriage_eta_charges'] as $key => $carriageEtaCharges) {
                    $inZone = is_null($carriageEtaCharges['postcode_id']) && $carriageEtaCharges['zone_id'] == $zoneId;
                    $postcodeMatched = !is_null($carriageEtaCharges['postcode_id']) && $carriageEtaCharges['postcode_id'] == $postcode;
                    if (!$inZone && !$postcodeMatched) {
                        unset($carriageRule['carriage_eta_charges'][$key]);
                    }
                }
                if (empty($carriageRule['carriage_eta_charges'])) {
                    unset($carriages[$ruleId]);
                }else{
                    $carriageRule['carriage_eta_charges'] = array_values($carriageRule['carriage_eta_charges'])[0];
                }
            }else{
                $matchedZones = [];
                foreach ($carriageRule['carriage_international_zones'] as $key => $zone) {
                    $locations = explode(',', $zone['location_id']);
                    if (!in_array($shippingCountryId, $locations)) {
                        unset($carriageRule['carriage_international_zones'][$key]);
                    }else{
                        $matchedZones[] = $zone['id'];
                    }
                }
                foreach ($carriageRule['carriage_international_eta_charges'] as $key => $eta) {
                    if (!in_array($eta['zone_id'], $matchedZones)) {
                        unset($carriageRule['carriage_international_eta_charges'][$key]);
                    }
                }
                if (empty($carriageRule['carriage_international_eta_charges'])) {
                    unset($carriages[$ruleId]);
                }else{
                    $carriageRule['carriage_eta_charges'] = array_values($carriageRule['carriage_international_eta_charges'])[0];
                }
            }
        }
        unset($carriageRule);

        $carriagesShipment1 = $carriages;
        $carriagesShipment2 = $carriages;

        if (!empty($deliveryMethods[config('constant.checkout.SHIPMENT_TOGETHER_TYPE')])) {
            $shipment = $deliveryMethods[config('constant.checkout.SHIPMENT_TOGETHER_TYPE')];
            $carriages = DeliveryMethod::filterCarriages($carriages, $holidays, $shipment);
        } else {
            $carriages = [];
        }

        if (!empty($deliveryMethods[config('constant.checkout.SHIPMENT_SEPARATE_TYPE')][config('constant.checkout.FIRST_SHIPMENT_TYPE')])) {
            $shipment1 = $deliveryMethods[config('constant.checkout.SHIPMENT_SEPARATE_TYPE')][config('constant.checkout.FIRST_SHIPMENT_TYPE')];
            $carriagesShipment1 = DeliveryMethod::filterCarriages($carriagesShipment1, $holidays, $shipment1);
        } else {
            $carriagesShipment1 = [];
        }

        if (!empty($deliveryMethods[config('constant.checkout.SHIPMENT_SEPARATE_TYPE')][config('constant.checkout.SECOND_SHIPMENT_TYPE')])) {
            $shipment2 = $deliveryMethods[config('constant.checkout.SHIPMENT_SEPARATE_TYPE')][config('constant.checkout.SECOND_SHIPMENT_TYPE')];
            $carriagesShipment2 = DeliveryMethod::filterCarriages($carriagesShipment2, $holidays, $shipment2);
        } else {
            $carriagesShipment2 = [];
        }
        return [
            'carriages' => $carriages,
            'seperateCarriages' => [
                config('constant.checkout.FIRST_SHIPMENT_TYPE') => $carriagesShipment1,
                config('constant.checkout.SECOND_SHIPMENT_TYPE') => $carriagesShipment2
            ]
        ];
    }

    // unset selected carriage from session if not available
    public static function validateSessionValues($checkoutSession, $data, $request)
    {
        $carriages = $data['carriages'];
        $seperateCarriages = $data['seperateCarriages'];
        $selectedDeliveryMethod = $checkoutSession['delivery_method'] ?? [];
        if (!empty($selectedDeliveryMethod['shipment_type']) && $selectedDeliveryMethod['shipment_type'] == config('constant.checkout.SHIPMENT_TOGETHER_TYPE') && !isset($carriages[$selectedDeliveryMethod['carriage_id']])) {
            $request->session()->forget('checkout.delivery_method');
            $request->session()->put('checkout.stage', config('constant.checkout.ADDRESS_STAGE'));
        } elseif(!empty($selectedDeliveryMethod['shipment_type']) && $selectedDeliveryMethod['shipment_type'] == config('constant.checkout.SHIPMENT_SEPARATE_TYPE')) {
            foreach ($selectedDeliveryMethod['shipments'] as $shipment_num => $item) {
                if (!isset($seperateCarriages[$shipment_num][$item['carriage_id']])) {
                    $request->session()->forget('checkout.delivery_method');
                    $request->session()->put('checkout.stage', config('constant.checkout.ADDRESS_STAGE'));
                }
            }
        }
    }

    public static function filterCarriages($carriageRules, $holidays, $shipment)
    {
        // process data for frontend
        foreach ($carriageRules as $ruleId => &$carriageRule) {
            // filter carriages which dont support dangerous goods
            if (!$carriageRule['dangerous'] && $shipment['is_dangerous']) {
                unset($carriageRules[$ruleId]);
                continue;
            }
            if (!empty($carriageRule['days_applicalbe'])) {
                // filter if carriage is applicable for specefic days
                @date_default_timezone_set(config('wmo_website.timezone'));
                $currentDay = date('N');
                if (!empty($carriageRule['cutoff_time']) && date('H') > $carriageRule['cutoff_time']) {
                    // if customer places order after cutoff, So a message needs to be    shown   that you miss cutoff time for today
                    $currentDay++;
                    if (($carriageRule['rule_type'] == CarriageRule::DOMESTIC_TYPE) && $carriageRule['exact_eta'] == 1) {
                        // increase ETA accordingly
                        $carriageRule['carriage_eta_charges']['eta_to'] = $carriageRule['carriage_eta_charges']['eta_to'] + 1;
                    }
                }
                $daysApplicable = explode(',', $carriageRule['days_applicalbe']);
                if (!in_array($currentDay, $daysApplicable)) {
                    unset($carriageRules[$ruleId]);
                    continue;
                }
            }
            // we will pass rule if it falls under any one value condition and with any one weight condition
            // "or" condition in between value/weight sub conditiions
            // "AND" condittion between value, weight
            $valuePassed = 1;
            $weightPassed = 1;
            foreach ($carriageRule['carriage_values'] as $carriageValue) {
                // value_type: value=>1 ,weight=>2
                // type: Range=>1,above=>2,below=>3
                if ($carriageValue['value_type'] == CarriageValue::VALUE_TYPE) {
                    $shipmentPrice = Currency::convertToPrimeCurrency($shipment['price']);
                    $inRange = ($carriageValue['type'] == CarriageValue::RANGE && ($shipmentPrice >= $carriageValue['from'] && $shipmentPrice <= $carriageValue['to']));
                    $isAbove = ($carriageValue['type'] == CarriageValue::ABOVE && $shipmentPrice >= $carriageValue['from']);
                    $isBelow = ($carriageValue['type'] == CarriageValue::BELOW && $shipmentPrice <= $carriageValue['from']);
                    if (!($inRange || $isAbove || $isBelow)) {
                        $valuePassed = 0;
                    }
                } elseif ($carriageValue['value_type'] == CarriageValue::WEIGHT_TYPE && empty($shipment['skip_weight_rule'])) {
                    $inRange = ($carriageValue['type'] == CarriageValue::RANGE && ($shipment['weight'] >= $carriageValue['from'] && $shipment['weight'] <= $carriageValue['to']));
                    $isAbove = ($carriageValue['type'] == CarriageValue::ABOVE && $shipment['weight'] >= $carriageValue['from']);
                    $isBelow = ($carriageValue['type'] == CarriageValue::BELOW && $shipment['weight'] <= $carriageValue['from']);
                    if (!($inRange || $isAbove || $isBelow)) {
                        $weightPassed = 0;
                    }
                }
            }

            if (!$valuePassed || !$weightPassed) {
                unset($carriageRules[$ruleId]);
            }

            $finalEta = [];
            if ($carriageRule['rule_type'] == CarriageRule::DOMESTIC_TYPE) {
                //  ETA calculations
                if (empty($carriageRule['carriage_eta_charges']['postcode_id'])) {
                    $finalEta = [
                        'eta_to' => $carriageRule['carriage_eta_charges']['eta_to'],
                        'eta_from' => $carriageRule['carriage_eta_charges']['eta_from'],
                        'charges' => $carriageRule['carriage_eta_charges']['charges'],
                    ];
                } else {
                    // if specific to postcode then add base charges to the charges for postcode
                    $finalEta = [
                        'eta_to' => $carriageRule['carriage_eta_charges']['eta_to'] + $carriageRule['carriage_eta_charges']['base_eta_to'],
                        'eta_from' => $carriageRule['carriage_eta_charges']['eta_from'] + $carriageRule['carriage_eta_charges']['base_eta_from'],
                        'charges' => $carriageRule['carriage_eta_charges']['charges'] + $carriageRule['carriage_eta_charges']['base_charge'],
                    ];
                }

                // eta calculation in case of exact eta
                if ($carriageRule['exact_eta'] == 1 && !empty($carriageRule['working_days'])) {
                    // if delivery date is on non working day then show nearest working date
                    $tentativeDate = Carbon::now();
                    // subtracted coz taking 1 as today so 1 eta will be (1-1)0 and will be dispatched today
                    $finalDays = ($shipment['dispatch']['to'] - 1) + ($carriageRule['carriage_eta_charges']['eta_to'] - 1);
                    $tentativeDate->addDays($finalDays);
                    $dispatchDate = $tentativeDate->format('Y-m-d');
                    // subtracct one day to see next target including dispatchDate
                    $dispatchDate = date('Y-m-d', strtotime($dispatchDate . '- 1 days'));
                    $availableDates = DeliveryMethod::getShipperDate($dispatchDate, $holidays, $carriageRule);
                    if (empty($availableDates)) {
                        unset($carriageRules[$ruleId]);
                        continue;
                    }
                    // assign nearest date
                    $finalEta['eta_to'] = Carbon::parse($availableDates[0])->format('d-m-Y');
                }
            } else {
                if ($shipment['weight'] <= $carriageRule['carriage_eta_charges']['base_charge_weight']) {
                    $finalEta = [
                        'eta_to' => $carriageRule['carriage_eta_charges']['eta_to'],
                        'eta_from' => $carriageRule['carriage_eta_charges']['eta_from'],
                        'charges' => $carriageRule['carriage_eta_charges']['base_charge_price'],
                    ];
                } else {
                    $overWeight = $shipment['weight'] - $carriageRule['carriage_eta_charges']['base_charge_weight'];
                    $multiplier = ceil($overWeight / $carriageRule['carriage_eta_charges']['additional_charge_weight']);
                    $additionalCharge = $multiplier * $carriageRule['carriage_eta_charges']['additional_charge_price'];
                    $finalEta = [
                        'eta_to' => $carriageRule['carriage_eta_charges']['eta_to'],
                        'eta_from' => $carriageRule['carriage_eta_charges']['eta_from'],
                        'charges' => $carriageRule['carriage_eta_charges']['base_charge_price'] + $additionalCharge,
                    ];
                }
            }
            $carriageRule['carriage_eta_charges']['final_eta'] = $finalEta;
        }
        unset($carriageRule);
        $obj = new static;
        return $obj->convertToBaseCurrency($carriageRules);
    }

    public static function getEtaChargeByIds($params)
    {
        $charges = CarriageEtaCharge::GetEtaChargeByIds($params)->get();
        // process data for frontend
        foreach ($charges as $charge) {
            if (empty($charge->postcode_id)) {
                $charge->final_charge = $charge->charges;
            } else {
                // if specific to postcode then add base charges to the charges for postcode
                $charge->final_charge = $charge->charges + $charge->base_charges;
            }
        }
        return $charges;
    }

    public static function getInternationalCharges($shipments)
    {
        $internationalCharges = 0;
        foreach ($shipments as $shipment) {
            $charge = CarriageInternationalEtaCharge::where('id',$shipment['eta_charge_id'])->first();
            if (isset($charge['base_charge_weight'])) {
                if ($shipment['shipment_weight'] <= $charge['base_charge_weight']) {
                    $internationalCharges = $internationalCharges + $charge['base_charge_price'];
                } else {
                    $overWeight = $shipment['shipment_weight'] - $charge['base_charge_weight'];
                    $multiplier = ceil($overWeight / $charge['additional_charge_weight']);
                    $additionalCharge = $multiplier * $charge['additional_charge_price'];
                    $internationalCharges = $internationalCharges + ($charge['base_charge_price'] + $additionalCharge);
                }
            }
        }
        return $internationalCharges;
    }

    public static function getShipperDate($targetDate, $holidays, $carriageRule)
    {
        $workingDays = explode(',', $carriageRule['working_days']);
        $daysOfWeek = config('constant.daysOfWeek');
        $availableDates = [];
        $loop = 0;
        while (empty($availableDates) && $loop < 5) {
            //  eta on next nearest working day
            foreach ($workingDays as $day) {
                $target = $daysOfWeek[$day];
                $availableDates[] = date('Y-m-d', strtotime("next $target", strtotime($targetDate)));
            }
            $prevAvailableDates = $availableDates;
            if ($carriageRule['holiday'] == 1 && !empty($holidays)) {
                // remove holidays also
                $availableDates = array_diff($availableDates, $holidays);
            }
            if (empty($availableDates)) {
                //  sort dates desc
                usort($prevAvailableDates, function ($a, $b) {
                    return strtotime($b) - strtotime($a);
                });
                // change delivery date to last previously available dates
                $targetDate = $prevAvailableDates[0];
            }
            $loop++;
        }
        //  sort dates
        usort($availableDates, function ($a, $b) {
            return strtotime($a) - strtotime($b);
        });
        return $availableDates;
    }

    public static function getAvailableDate($to, $holidays)
    {
        $dispatchDate = date('Y-m-d', strtotime(date('Y-m-d') . " + $to days"));
        // subtracct one day to see next target including deliveryDate
        $dispatchDate = date('Y-m-d', strtotime($dispatchDate . '- 1 days'));
        $daysOfWeek = config('constant.daysOfWeek');
        $workingDays = config('wmo_website.franchise.working_days')
        ? explode(',', config('wmo_website.franchise.working_days')) : [];
        $availableDates = [];
        $loop = 0;
        while (empty($availableDates) && $loop < 5) {
            //  dispatch on next nearest working day
            foreach ($workingDays as $day) {
                $target = $daysOfWeek[$day];
                $availableDates[] = date('Y-m-d', strtotime("next $target", strtotime($dispatchDate)));
            }
            // do not dispatch on holiday
            $prevAvailableDates = $availableDates;
            if (!empty($holidays)) {
                // remove holidays also
                $availableDates = array_diff($availableDates, $holidays);
            }
            if (empty($availableDates)) {
                //  sort dates desc
                usort($prevAvailableDates, function ($a, $b) {
                    return strtotime($b) - strtotime($a);
                });
                // change delivery date to last previously available dates
                $dispatchDate = $prevAvailableDates[0] ?? '';
            }
            $loop++;
        }
        //  sort dates
        usort($availableDates, function ($a, $b) {
            return strtotime($a) - strtotime($b);
        });
        // assign nearest date
        $dispatchDate = $availableDates[0] ?? '';
        $date = Carbon::parse($dispatchDate);
        $now = Carbon::now()->format('Y-m-d');
        $diff = $date->diffInDays($now);
        return $diff + 1;
    }

    public static function goToAddressStage()
    {
        if (session()->get('checkout.stage') > config('constant.checkout.ADDRESS_STAGE')) {
            session()->put('checkout.stage', config('constant.checkout.ADDRESS_STAGE'));
        }
    }

    /**
     * convert carraiges rule charges to base currency
     */
    public function convertToBaseCurrency($carriageRules)
    {
        return array_map(function ($carriageRule) {
            $carriageRule['carriage_eta_charges']['final_eta']['charges'] = Currency::convertToBaseCurrency($carriageRule['carriage_eta_charges']['final_eta']['charges']);
            return $carriageRule;
        }, $carriageRules);
    }

}
