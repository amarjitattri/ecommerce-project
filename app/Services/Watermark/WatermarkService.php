<?php

namespace App\Services\Watermark;
use Image;
use Intervention\Image\Exception\NotReadableException;
use File;

class WatermarkService
{
    public static function processImage($imageName){
        if (empty($imageName)) {
            return false;
        }
        $product_thumbnail_path = config('wmo_website.cdn_path') . config('constant.product_thumbnail_path', '/storage/images/productimages/');
        if(empty(config('wmo_website.watermark'))){
           $watermark = config('wmo_website.cdn_path') . '/images/watermark-2020.png';   
        }else{
            $watermark = config('wmo_website.cdn_path') . config('constant.watermark_path', '/storage/images/Websites/watermark_logo/') . config('wmo_website.watermark');
        }
        
        $websiteId = config('wmo_website.website_id');
        $path = $product_thumbnail_path . rawurlencode($imageName);
        $filename = rawurldecode(basename($path));
        try {
            $image = Image::make($path);
            $width = $image->width();
            $height = $image->height();
            $watermark = Image::make($watermark);
            $image->width() > $image->height() ? $width = null : $height = null;
            $watermark->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
            });
            
            $image->insert($watermark, 'center');
            $dir = storage_path('app/public/' . $websiteId . '/images/productimages');
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true, true);
            }
            $image->save($dir . '/' . $filename);
            $path = asset('storage/' . $websiteId . '/images/productimages/'. $filename);
        } catch (\Exception $exception) {
            logger()->error($exception);
        }
        return $path;
    }
}

