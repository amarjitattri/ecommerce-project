<?php
namespace App\Services\Product;

use App\Jobs\StockEmailNotificationJob;
use App\Models\Catalog\Product\{
    ProductFlatStock,
    ProductStockIn
};
use App\Models\Order\ProductInventory;
use App\Repositories\Product\Interfaces\ProductRepositoryInterface;
use App\Models\Catalog\Promocode\Promocode;
use App\Models\SupplierOrder\SupplierOrder;
use App\Models\SupplierOrder\SupplierCustomerOrder;
use Carbon\Carbon;
use App\Models\Catalog\Product\StockLog;

class Stock
{

    private $__productRepo;
    public function __construct(ProductRepositoryInterface $productRepo)
    {
        $this->__productRepo = $productRepo;
    }

    public function updateStock($orderData)
    {
        $products = $this->__calculateQty($orderData['cart']);
        $productIds = array_keys($products);
        $pStocks = $this->__productRepo->checkStockByIds($productIds)->pluck('stock', 'product_id')->toArray();
        //update stock in product invertries table
        $emailNotificationData['products'] = [];
        $website_id = config('wmo_website.website_id');
        $franchiseId = config('wmo_website.website_franchise');
        $stockData= [];
        $now = Carbon::now()->toDateTimeString();
        
        foreach ($products as $id => $qty) {
            if (isset($pStocks[$id]) && $pStocks[$id] != 0) {
                $stock = $pStocks[$id] - $qty;
                if($pStocks[$id] >= $qty) {
                    ProductFlatStock::where([
                        'franchise_id' => $franchiseId,
                        'product_id' => $id,
                    ])->update(['stock' => $stock]);
                }

                $emailNotificationData['products'][] = [
                    'product_id' => $id,
                    'qty' => $qty,
                    'stock' => $stock
                ];
            } else {
                $emailNotificationData['products'][] = [
                    'product_id' => $id,
                    'qty' => $qty,
                    'stock' => null
                ];
            }
            if(isset($pStocks[$id]) && $pStocks[$id] ==$qty)
            {
                $stockData[] = [
                    'product_id' => $id,
                    'franchise_id' => $franchiseId,
                    'start_date' => $now,
                    'created_at'=> $now,
                    'updated_at'=> $now,
                ];
            }
        }
        if($stockData)
        {
            StockLog::insert($stockData);
        }
        //update inventory table
        $order = $orderData['order_data'];
        $this->updateStockInProductInventory($products, $pStocks, $order['platformorderid']);

        if ($emailNotificationData['products']) {
            $emailNotificationData['website_id'] = $website_id;
            $emailNotificationData['order_data'] = $orderData['order_data'];
            StockEmailNotificationJob::dispatch($emailNotificationData)->onQueue('frontend');
        }

    }

    private function __calculateQty($cart)
    {
        $products = [];
        foreach($cart['products'] as $product) {
            if ($product['type'] == config('constant.kit_product_type')) {
                foreach($product['product_kit_items'] as $kitProduct) {
                    if(array_key_exists($kitProduct['prdId'], $products)) {
                        $products[$kitProduct['prdId']] = ($products[$kitProduct['prdId']] + $kitProduct['quantity']) * $product['qty'];
                    } else {
                        $products[$kitProduct['prdId']] = $kitProduct['quantity'] * $product['qty'];
                    }
                }
            } else {
                if(array_key_exists($product['product_id'], $products)) {
                    $products[$product['product_id']] = $products[$product['product_id']] + $product['qty'];
                } else {
                    $products[$product['product_id']] = $product['qty'];
                }
            }

        }
        return $products;
    }

    public function updateStockInProductInventory($products, $pStocks, $orderId)
    {
        $franchise_id = config('wmo_website.website_franchise');
        $productIds = array_keys($products);
        $params = [
            'product_ids' => $productIds,
            'franchise_id' => $franchise_id
        ];
        $productsInventory = ProductInventory::getstockByIds($params)->get()->pluck('stock', 'product_id')->toArray();
        $productSuppliers = $this->__productRepo->getSupplierDetailsByIds($productIds)->pluck('supplier_id', 'product_id')->toArray();
        foreach ($products as $id => $qty) {
            if (isset($productsInventory[$id]) && $productsInventory[$id] != 0 && $pStocks[$id] >= $qty) {
                $stock = $productsInventory[$id] > $qty?$productsInventory[$id] - $qty:0;

                //need to change this logic after stock check logic implement
                ProductInventory::where([
                    'franchise_id' => $franchise_id,
                    'product_id' => $id,
                ])->update(['stock' => $stock]);
            }
            //update stock_out entry in productstockin table
            if(isset($productSuppliers[$id]) && $pStocks[$id] >= $qty) {
                ProductStockIn::updateStockOut($id, $productSuppliers[$id], $franchise_id, $qty, $orderId);
            }
        }

    }

    public function updatePromoCount($orderData)
    {
        $cart = $orderData['cart'];
        if (isset($cart['promotion']['promocode'])) {
            $promoCode = $cart['promotion']['promocode'];

            $params = [
                'promocode' => $promoCode,
                'website_id' => config('wmo_website.website_id'),
                'status' => 1
            ];
            $promocodeData = Promocode::getPromoByCode($params)->first();
            $promocodeData->total_usage++;
            $promocodeData->save();
        }
    }

    public function createStockCustomerOrder($orderData) {
        $order = $orderData['order_data'];
        $products = $this->__calculateQty($orderData['cart']);
        $productCodes = $this->getProductCodes($orderData['cart']);
        $productIds = array_keys($products);
        $productStocks = $this->__productRepo->checkStockByIds($productIds)->pluck('stock', 'product_id')->toArray();
        $productThresholds = $this->__productRepo->checkStockByIds($productIds)->pluck('threshold', 'product_id')->toArray();
        $productMasterPacks = $this->__productRepo->getFranchiseDetailsByIds($productIds)->pluck('masterpack', 'product_id')->toArray();
        $productSuppliers = $this->__productRepo->getSupplierDetailsByIds($productIds)->pluck('supplier_id', 'product_id')->toArray();
        $isWemotoSuppliers = $this->__productRepo->getSupplierDetailsByIds($productIds)->pluck('is_wemoto_uk', 'supplier_id')->toArray();
        $franchise_id = config('wmo_website.website_franchise');
        foreach($products as $id => $quantity) {
            if(isset($productCodes[$id]) && substr($productCodes[$id], 0, 3) == "SM-" && isset($productSuppliers[$id])) {
                $this->createCustomerOrder($id, false, $quantity, $order, $productSuppliers, 1, $isWemotoSuppliers);
            } elseif(isset($productSuppliers[$id]) && isset($productStocks[$id])) {
                $existSupplierPendingOrder = SupplierOrder::select('supplier_orders.id', 'supplier_orders.quantity')->where('supplier_orders.franchise_id', $franchise_id)->where('supplier_orders.supplier_id', $productSuppliers[$id])->where('supplier_orders.order_type', SupplierOrder::STOCKCUSTOMERORDERTYPE)->where('supplier_orders.product_id', $id)->where('supplier_orders.status', SupplierOrder::UNPROCESSED)->first();
                $existStockOrder = SupplierOrder::select('supplier_orders.id')->where('supplier_orders.franchise_id', $franchise_id)->where('supplier_orders.supplier_id', $productSuppliers[$id])->where('supplier_orders.order_type', SupplierOrder::STOCKCUSTOMERORDERTYPE)->where('supplier_orders.product_id', $id)->where('supplier_orders.status', SupplierOrder::UNPROCESSED)->leftJoin('supplier_customer_orders as sco', 'sco.supplier_order_id', '=', 'supplier_orders.id')->where('sco.type', 1)->first();
                $conditions1 = !$existStockOrder && isset($productThresholds[$id]) && isset($productMasterPacks[$id]);
                if($productStocks[$id] < $quantity) {
                    //generate customer order
                    $this->createCustomerOrder($id, $existSupplierPendingOrder, $quantity, $order, $productSuppliers, 0, $isWemotoSuppliers);
                } elseif($conditions1 && ($productStocks[$id] > $productThresholds[$id]) && (($productStocks[$id] - $quantity) < $productThresholds[$id])) {
                    //generate stock order
                    $this->createStockOrder($id, $existSupplierPendingOrder, $productMasterPacks[$id], $order, $productSuppliers, $isWemotoSuppliers);
                }
            }
        }
    }

    public function getProductCodes($cart) {
        $productCodes = array();
        foreach($cart['products'] as $product) {
            $productCodes[$product['product_id']] = $product['product_code'];
        }
        return $productCodes;
    }

    public function createStockOrder($productId, $existSupplierPendingOrder, $qty, $order, $productSuppliers, $isWemotoSuppliers) {
        $franchiseId = config('wmo_website.website_franchise');
        if(!empty($existSupplierPendingOrder)) {
            $pendingOrderId = SupplierOrder::updateOrder($existSupplierPendingOrder, $qty);
        } else {
            $orderProductsData = array(
                'franchise_id' => $franchiseId,
                'supplier_id' => isset($productSuppliers[$productId])?$productSuppliers[$productId]:0,
                'product_id' => $productId,
                'make' => '',
                'model' => '',
                'description' => '',
                'quantity' => $qty,
                'order_type' => SupplierOrder::STOCKCUSTOMERORDERTYPE,
                'is_wemoto_uk' => isset($isWemotoSuppliers[$productSuppliers[$productId]])?$isWemotoSuppliers[$productSuppliers[$productId]]:0
            );
            $supplierOrder = SupplierOrder::create($orderProductsData);
            $pendingOrderId = $supplierOrder->id;
        }
        $stockCustOrderProducts = array(
            'supplier_order_id' => $pendingOrderId,
            'order_id' => $order['platformorderid'],
            'quantity' => $qty,
            'order_number' => 'Stock',
            'type' => SupplierCustomerOrder::STOCKORDERTYPE
        );
        SupplierCustomerOrder::create($stockCustOrderProducts);
    }

    public function createCustomerOrder($productId, $existSupplierPendingOrder, $qty, $order, $productSuppliers, $selfManufactured, $isWemotoSuppliers) {
        $franchiseId = config('wmo_website.website_franchise');
        if(!empty($existSupplierPendingOrder)) {
            $pendingOrderId = SupplierOrder::updateOrder($existSupplierPendingOrder, $qty);
        } else {
            $orderProductsData = array(
                'quantity' => $qty,
                'self_manufactured' => $selfManufactured,
                'franchise_id' => $franchiseId,
                'supplier_id' => isset($productSuppliers[$productId])?$productSuppliers[$productId]:0,
                'product_id' => $productId,
                'make' => '',
                'model' => '',
                'description' => '',
                'order_type' => SupplierOrder::STOCKCUSTOMERORDERTYPE,
                'is_wemoto_uk' => isset($isWemotoSuppliers[$productSuppliers[$productId]])?$isWemotoSuppliers[$productSuppliers[$productId]]:0
            );
            $supplierOrder = SupplierOrder::create($orderProductsData);
            $pendingOrderId = $supplierOrder->id;
        }
        $stockCustOrderProducts = array(
            'supplier_order_id' => $pendingOrderId,
            'order_id' => $order['platformorderid'],
            'quantity' => $qty,
            'order_number' => $order['order_id'],
            'type' => SupplierCustomerOrder::CUSTOMERORDERTYPE
        );
        SupplierCustomerOrder::create($stockCustOrderProducts);
    }
}
