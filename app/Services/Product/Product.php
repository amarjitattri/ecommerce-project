<?php
namespace App\Services\Product;

use App\Models\Catalog\Product\ProductImage;
use App\Models\Catalog\Product\Product as ProductModel;
use App\Models\Catalog\Product\ProductAttribute;
use App\Models\Catalog\{
    AttributeSetAssociation
};
use App\Http\Traits\ProductServiceTrait;
use App\Repositories\Product\Interfaces\ProductPriceRepositoryInterface;
use App\Services\Product\Contracts\ProductContracts;
use App\Repositories\Product\Interfaces\ProductRepositoryInterface;
use App\Models\Catalog\Product\ProductGroup\ProductGroupDocument;

use DB;
use Auth;
use App\Models\MyAccount\UserWishlist;
use App\Services\Watermark\WatermarkService;

class Product implements ProductContracts
{
    use ProductServiceTrait;
    private $__productPriceRepo;
    private $__productRepo;
    private $__productId;
    private $__rrp;
    private $__finalRRP;
    private $__productImages;
    private $__productDocs;
    private $__productSpecs;
    private $__primaryImage;
    private $__primaryImageLink;
    private $__frontendCode;
    private $__frontendCodeLabel;

    public function __construct(ProductPriceRepositoryInterface $productPriceRepo, ProductRepositoryInterface $productRepo)
    {
        $this->__productPriceRepo = $productPriceRepo;
        $this->__productRepo = $productRepo;
    }
    public function getProductByCode($productCode)
    {
        return $this->transformProduct($productCode);
    }
    public function getAllProductByCode($productCode, $bikeModelId, $modelyear)
    {
        return $this->transformAllProductData($productCode, $bikeModelId, $modelyear);
    }

    public function setPrice()
    {
        $params = ['product_id' => $this->__productId, 'website_id' => config('wmo_website.website_id')];
        $productPrice = $this->__productPriceRepo->getPrice($params);
        $this->__rrp = !empty($productPrice) ? round($productPrice->price, 2) : 0; //findal
        $this->__finalRRP = !empty($productPrice) ? round($productPrice->final_price, 2) : 0; //price
    }

    public function transformProduct($productCode)
    {
        $productData = $this->__productRepo->findProductByCode($productCode);
        //get product code & product label
        $adminId = (!empty(session()->get('local_shop_user'))) ? session()->get('local_shop_user') : ((!empty(session()->get('trade_local_shop_user')))  ? session()->get('trade_local_shop_user') : "");
        $productLabels = ProductModel::codeLabels($productData, $adminId);
        $this->__frontendCode =  $productLabels['product_code'];
        $this->__frontendCodeLabel= $productLabels['product_code_label'];

        $productImages = isset($productData->productimages)?$this->getProductImages($productData->productimages):'';
        $this->__primaryImage = !empty($productImages)?$productImages['primaryImage']:'';
        $this->__primaryImageLink = !empty($productImages)?$productImages['primaryImageLink']:'';
        $this->__productImages = !empty($productImages)?$productImages['images']:'';
        $this->__productDocs = !empty($productImages)?$productImages['document']:'';
        $this->__productId = isset($productData->id)?$productData->id:'';

        $this->setPrice();

        $variantAttributes = array();
        if (($productData->type == 2 || $productData->type == 7) && $productData->productvariantattributes) {
            foreach ($productData->productvariantattributes as $value) {
                $variantAttributes[] = array(
                    'attribute_id' => $value->attribute_id,
                    'attribute_name' => $value->attribute->label,
                    'attribute_value' => $value->attribute_value_id
                );
            }
        }
        $productKit = array();
        //product shipping or collection type
        $collectionItem = (isset($productData->price->collection_item) && $productData->price->collection_item == 1) ? 1 : 0;
        $homeDelivery = (isset($productData->price->collection_item) && $productData->price->collection_item == 1) ? 0 : 1;
        return [
            'product_id' => $productData->id,
            'product_code' => $productData->code,
            'productgroup_id' => $productData->productgroup_id,
            'type' => $productData->type,
            'category_id' => $productData->category_id,
            'cat_url' => $productData->productCategory->url ?? null,
            'brand_id' => $productData->brand_id,
            'primary_image_name' => $this->__primaryImage,
            'primary_image_link' => $this->__primaryImageLink,
            'price' => $this->__rrp,
            'final_price' => $this->__finalRRP,
            'brand_name' => $productData->brands->name ?? null,
            'variant_attributes' => $variantAttributes,
            'product_kit_single' => $productKit,
            'attributeset_id' => $productData->attributeset_id,
            'full_description'=> $productData->fulldescription->description ?? null,
            'product_images' => $this->__productImages,
            'product_docs' => $this->__productDocs,
            'home_delivery' => $homeDelivery,
            'collection_item' => $collectionItem,
            'variantcontainer' => $productData['variantcontainer'],
            'frontend_code'=>$this->__frontendCode,
            'frontend_code_label'=>$this->__frontendCodeLabel,
            'weight'=>$productData->price->weight,
            'is_dangerous' => $productData->price->is_dangerous,
        ];
    }

    public function transformAllProductData($productCode, $bikeModelId, $modelyear)
    {
        $product = $this->__productRepo->findAllProductByCode($productCode)->first();
        if ($product->type == ProductModel::INDIVIDUAL_PRODUCT_TYPE) {
            //Individual product type
            $productDetails = $this->__productRepo->findAllProductByCode($productCode)
                                        ->basicDetails()
                                        ->first();
        } elseif ($product->type == ProductModel::KIT_PRODUCT_TYPE) {
            //kit product type
            $productDetails = $this->__productRepo->findKitProduct(['code'=>$productCode,'website_id'=> config('wmo_website.website_id')]);
        } elseif ($product->type == ProductModel::CONTAINER_PRODUCT_TYPE || $product->type == ProductModel::VARIANT_PRODUCT_TYPE) {

            //Container product type
            $productDetails = $this->__productRepo->findProductVariant(['code'=>$productCode]);
        }
        
        //get product details
        $productData = $this->productDetails(['type'=>$product->type,'productDetails'=>$productDetails,'productCode'=>$productCode,'modelyear'=>$modelyear,'bikeModelId'=>$bikeModelId]);

        // checkIfWishListMarked
        $websiteId = config('wmo_website.website_id');
        $userId = Auth::check() ? Auth::user()->id : null;
        $userWishList = UserWishlist::select('id')->where(['website_id' =>$websiteId,'user_id'=>$userId,'product_id'=>$productCode])->first();
        $userWishListId = 0;
        if (isset($userWishList->id)) {
            $userWishListId = $userWishList->id;
        }

        $this->setPrice();
        //product shipping or collection type
       
        return [
            'userWishListId' => $userWishListId,
            'product_id' => $productData['product_id'],
            'parent_id'=> !empty($productData['parent_id'])?$productData['parent_id']:$productData['product_id'],
            'code' => $productData['code'],
            'status' => $productData['status'],
            'customer_description' => $productData['customer_description'],
            'customer_notes' => $productData['customer_notes'],
            'system_notes' => $productData['system_notes'],
            'supplier_part_number'=> $productData['supplier_part_number'],
            'product_code'=>$productData['product_code'],
            'product_code_label'=>$productData['product_code_label'],
            'type' => $productData['type'],
            'category_id' => $productData['category_id'],
            'product_discount' => $productData['product_discount'],
            'brand_id' => $productData['brand_id'],
            'primary_image_name' => $this->__primaryImage,
            'primary_image_link' => $this->__primaryImageLink,
            'rrp' => $this->__rrp,
            'final_rrp' => $this->__finalRRP,
            'brand_name' => $productData['brands_name'] ?? null,
            'variant_attributes' => $productData['variant_attributes'],
            'product_kit' => $productData['product_kit'],
            'attributeset_id' => $productData['attributeset_id'],
            'full_description'=> $productData['fulldescription'] ?? null,
            'full_description_title'=> $productData['fulldescriptiontitle'] ?? null,
            'home_delivery' => '',
            'collection_item' => $productData['collection_item'],
            'product_images' => $this->__productImages,
            'product_docs' => $this->__productDocs,
            'product_specs'=>$this->__productSpecs,
            'admin_id'=>$productData['admin_id'],
            'stock'=>$productData['stock'],
            'eta' => $productData['eta'],
            'label_color'=>$productData['label_color'],
            'stock_label' => $productData['stock_label'],
            'available' => $productData['available'],
            'productgroup_id' => $productData['productgroup_id'],
            'application_list' => $productData['application_list'],
        ];
    }

    public function productDetails($product)
    {
        $productData = ProductModel::getVariantProductDetails($product['productDetails'], ['website_id'=>config('wmo_website.website_id'),'code'=>$product['productCode'],'year'=>$product['modelyear'],'bikeModelId'=>$product['bikeModelId']], []);
        $productImage=collect();
        if ($product['type'] == ProductModel::CONTAINER_PRODUCT_TYPE) {
            $this->getVariantDocs($productData, $product);
        } elseif ($product['type'] != ProductModel::KIT_PRODUCT_TYPE) {
            $productImage= $this->__productRepo->findProductAllImages($product['productCode']);
            $productImages = (!$productImage->isEmpty())?$this->getProductImages($productImage):'';

            //product group
            $productGroupdocs = $this->getProductGroupDocs($product['productDetails']['productGroupDocuments']);
            //document

            $document[$product['productCode']] = !empty($productImages['document'])?$productImages['document']:$productGroupdocs['document'];
            
            $this->__productDocs = array_filter($document);
            //specification
            $specification[$product['productCode']] =$this->getProductSpecifications($product['productDetails']['id'], $product['productDetails']['attributeset_id']);
            $this->__productSpecs = array_filter($specification);
            $this->__primaryImage = !empty($productImages)?$productImages['primaryImage']:'';
            $this->__primaryImageLink = !empty($productImages)?$productImages['primaryImageLink']:'';
            $this->__productImages = !empty($productImages)?$productImages['images']:'';
        } else {
            $this->getKitDocs($productData, $product);
        }
        $this->__productId = $product['productDetails']['id'];
        return $productData;
    }
    public function getVariantDocs($productData, $product)
    {
        $document=$specification=$primaryImage=$primaryImageLink=$productAllImages=[];

        $productChild = ProductModel::select('id', 'code', 'productgroup_id', 'attributeset_id')
                            ->where('code', 'like', $product['productCode'].'%')
                            ->where('status', ProductModel::ACTIVE_STATUS)
                            ->with(['productGroupDocuments' => function ($rs) {
                                // for language translation
                                $rs->select('productgroup_documents.*')->languageJoin();
                            },
                            'productImages'=>function ($q) {
                                $q->select('product_images.name', 'product_images.type', 'product_id', 'is_document', 'mime_type', 'image_link', 'website_id')
                                ->where('website_id', config('wmo_website.website_id'))->orWhereNull('website_id')
                                ->languageJoin();
                            }
                            ])
                            ->get('id', 'code');
        if (!empty($productChild)) {
            foreach ($productChild as $productDetails) {
                $productGroupDocs='';
                $productAllImages=[];

                $productImages = (!empty($productDetails->productImages))?$this->getProductImages($productDetails->productImages):'';

                //images
                $primaryImage = !empty($productImages)?$productImages['primaryImage']:'';
                $primaryImageLink = !empty($productImages)?$productImages['primaryImageLink']:'';
                $productAllImages = !empty($productImages)?$productImages['images']:'';

                //productGroup docs
                $productGroupDocs= $this->getProductGroupDocs($productDetails->productGroupDocuments);

                //document
                $document[$productDetails['code']] = !empty($productImages['document'])?$productImages['document']:$productGroupDocs['document'];

                //specification
                $specification[$product['productCode']] =$this->getProductSpecifications($productDetails['id'], $productDetails['attributeset_id']);
            }
        }
        $this->__productDocs = array_filter($document);
        $this->__productSpecs = array_filter($specification);
        $this->__primaryImage = $primaryImage;
        $this->__primaryImageLink = $primaryImageLink;
        $this->__productImages = $productAllImages;
    }
    public function getKitDocs($productData, $product)
    {
        $kitdocument=$kitspecification=$kitprimaryImage=$kitprimaryImageLink=$kitproductAllImages=[];

        $kitproductImage= $this->__productRepo->findProductAllImages($product['productCode']);
        $productImageskit = (!$kitproductImage->isEmpty())?$this->getProductImages($kitproductImage):'';
        //kit product group docs
        $productGroupDocs=$this->getProductGroupDocs($product['productDetails']['productGroupDocuments']);

        //container primary Image
        $kitprimaryImage = !empty($productImageskit)?$productImageskit['primaryImage']:'';
        $kitprimaryImageLink = !empty($productImageskit)?$productImageskit['primaryImageLink']:'';
        $kitproductAllImages = !empty($productImageskit)?$productImageskit['images']:'';

        //container kit document
        $kitdocument[$product['productCode']] = !empty($productImageskit['document'])?$productImageskit['document']:$productGroupDocs['document'];

        //container kit specification
        $kitspecification[$product['productCode']] =$this->getProductSpecifications($product['productDetails']['id'], $product['productDetails']['attributeset_id']);

        if (!empty($productData['product_kit']['kitProductIds'])) {
            $kitIds = array_filter($productData['product_kit']['kitProductIds']);
            foreach ($kitIds as $value) {
                $productGroupDocs='';
                $kitproductAllImages=[];
                $kitproductDetails = ProductModel::select('id', 'code', 'productgroup_id', 'attributeset_id')
                            ->with(['productGroupDocuments' => function ($q) {
                                // select() is required for languagejoin
                                $q->select('productgroup_documents.*')->languageJoin();
                            },
                            'productImages'=>function ($q) {
                                $q->select('product_images.name', 'product_images.type', 'product_id', 'is_document', 'mime_type', 'image_link', 'website_id')
                                ->where('website_id', config('wmo_website.website_id'))->orWhereNull('website_id')
                                ->languageJoin();
                            }
                            ])
                            ->where('id', $value)
                            ->first();
                $productImageskit = (!empty($kitproductDetails->productImages))?$this->getProductImages($kitproductDetails->productImages):'';

                //images
                $kitprimaryImage = !empty($productImageskit)?$productImageskit['primaryImage']:'';
                $kitprimaryImageLink = !empty($productImageskit)?$productImageskit['primaryImageLink']:'';
                $kitproductAllImages = !empty($productImageskit)?$productImageskit['images']:'';

                //productGroup docs
                $productGroupDocs= $this->getProductGroupDocs($kitproductDetails->productGroupDocuments);

                //document
                $kitdocument[$kitproductDetails['code']] = !empty($productImageskit['document'])?$productImageskit['document']:$productGroupDocs['document'];


                //specification
                $kitspecification[$kitproductDetails['code']] =$this->getProductSpecifications($kitproductDetails['id'], $kitproductDetails['attributeset_id']);
            }
        }
        $this->__productDocs = array_filter($kitdocument);
        $this->__productSpecs = array_filter($kitspecification);
        $this->__primaryImage = $kitprimaryImage;
        $this->__primaryImageLink = $kitprimaryImageLink;
        $this->__productImages = $kitproductAllImages;
    }
    public function checkStockByIds($productIds)
    {
        return $this->__productRepo->checkStockByIds($productIds);
    }

    public function checkEtaByIds($productIds)
    {
        return $this->__productRepo->checkEtaByIds($productIds);
    }

    public function checkCollectionItemByIds($productIds)
    {
        return $this->__productRepo->checkCollectionItemByIds($productIds);
    }

    public function getProductImages($productImages)
    {
        $document =  $images =[];
        $primaryImage = $primaryImageLink ='';

        foreach ($productImages as $value) {
            if ($value->is_document == ProductImage::DOCUMENT_YES && $value['website_id'] == config('wmo_website.website_id')) {
                $document[] = [
                    'name'=>$value->name,
                    'mime_type'=>$value->mime_type,
                    'type'=>$value->type,
                    'product_type'=>'product'
                ];
            } elseif ($value->is_document == ProductImage::PRIMARY_IMAGE_TYPE) {
                $primaryImage =  $value->name;
                $primaryImageLink = $value->image_link;
            } else {
                $images[] = [
                    'name'=>$value->name,
                    'product_id'=>$value->product_id,
                    'type'=>$value->type,
                    'product_type'=>'product' ];
            }
        }

        return compact('document', 'images', 'primaryImage', 'primaryImageLink');
    }


    public function relatedProducts($productIds, $page='')
    {
        if ($page =='cart') {
            $producKeys = array_keys($productIds);
            $productIds= $this->__productRepo->findProductIds($producKeys);
        }
        $list = $this->__productRepo->relatedProductsList($productIds);

        if (!empty($list)) {
            foreach ($list as $val) {
                if (!empty($val['images']['name'])) {
                    WatermarkService::processImage($val['images']['name']);
                }
            }
        }
        return $list;
    }

    public function checkPriceByIds($productIds)
    {
        return $this->__productRepo->checkPriceByIds($productIds);
    }

    public function getProductSpecifications($productIds, $attributesetId)
    {
        $final=[];
        $attributeSetAssociation = AttributeSetAssociation::getProductAttributeset($attributesetId);

        $productAttributesData = ProductAttribute::with(['attribute' => function ($q) {
            $q->select('attributes.*')->languageJoin();
        }])->where('product_id', $productIds)->get()->toArray();
        $unitLists = app('CommonHelper')->getUnitList();

        if ($attributeSetAssociation->count()>0) {
            foreach ($attributeSetAssociation as $attributSets_id => $productAttribute) {
                if ($productAttribute->attribute->status == 1 && $productAttribute->show_on_front ==1) {
                    $data= $this->getSpeicificationOptions($productAttribute, $productAttributesData, $attributSets_id, $unitLists);

                    $final[]=$data;
                }
            }
        }

        return array_filter($final);
    }
}
