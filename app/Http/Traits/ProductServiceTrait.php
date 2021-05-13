<?php

namespace App\Http\Traits;


trait ProductServiceTrait{
    
    public function getProductGroupDocs($productImages) {
        $document =  $doc =[];
        if(!empty($productImages)){
        foreach($productImages as $value){

            if($value->type == 'document' ){
                $doc = [
                    'name'=>$value->document,
                    'type'=>$value->type,
                    'product_type'=>'product_group'
                ];
            }else{
                $doc = [
                    'name'=>$value->document_link,
                    'type'=>$value->type,
                    'product_id'=>$value->product_id,
                    'product_type'=>$value->type ];
            }
            $document[]=$doc;
        }
        }
        return compact('document');
    }
    public function Specifications($productSpecs) {
        $document = [];
        foreach($productSpecs as $value){

            if($value->type == ProductGroupDocument::DOCUMENT){
                $document[] = [
                    'name'=>$value->document,
                    'type'=>$value->type];
            }else{
                $document[] = [
                    'name'=>$value->document_link,
                    'type'=>$value->type];
            }
        }
        return $document;
    }
    public function getSpeicificationOptions($productAttribute,$productAttributesData,$attributSets_id,$unitLists){
        $data='';
        if($productAttribute->attribute->input_type == 'dropdown'  && !$productAttribute->attributeValues->isEmpty()){
            foreach($productAttribute->attributeValues as $values){
                if(isset($productAttributesData[$attributSets_id]['attribute_value_id']) && $productAttributesData[$attributSets_id]['attribute_value_id'] == $values->id){
                        $data = $productAttribute->attribute->label ;
                        $data .= isset($unitLists[$productAttribute->attribute->unit_id])?"(".$unitLists[$productAttribute->attribute->unit_id].") : ":" : ";
                        $data .= $values->value;
                }
                
            }
       
        }elseif($productAttribute->attribute->input_type == 'text' && !empty($productAttributesData[$attributSets_id]['attribute_value_id'])){
            $data = $productAttribute->attribute->label;
            $data .= isset($unitLists[$productAttribute->attribute->unit_id])?"(".$unitLists[$productAttribute->attribute->unit_id].") : ":isset($productAttributesData[$attributSets_id]['attribute_value_id'])?' : '.$productAttributesData[$attributSets_id]['attribute_value_id']:' : '.config('constant.view_empty_variable');
        }
        return $data;
    }

    public function modifyProductKit($productData)
    {
        $product = $productData->productKit->first();
        if (!empty($product->product->customer_description) ) {
            $desc = $product->product->customer_description;
        } else {
            $desc = !empty($product->product->productdescription) ? $product->product->productdescription->title : '';
        }

        return json_encode([
            [
                'prdId' => $product->product->id,
                'prdCode' => $product->product->code,
                'quantity' => $product->count,
                'description' => $desc,
                'descriptionType' => $product->type
            ]
        ]);
    }
    public function getCategory() {
        //
    }
    public function getBrand() {
        //
    }

}
