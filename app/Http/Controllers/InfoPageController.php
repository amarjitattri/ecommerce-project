<?php

namespace App\Http\Controllers;

use App\Models\Catalog\Category;
use App\Models\CMS\InfoPage;
use App\Services\Product\FeaturedProduct;
use Illuminate\Http\Request;

class InfoPageController extends Controller
{
    private $__featuredProductService;

    public function __construct(FeaturedProduct $featuredProductService)
    {
        $this->__featuredProductService = $featuredProductService;
    }

    public function index()
    {
        $info_pages = InfoPage::getHeaderPages();
        return view('info_pages.index')->with(compact('info_pages'));
    }

    public function view($slug, Request $request)
    {
        $info_page = InfoPage::getData($slug);
        $page_categories = [];
        $product_slider = [];

        if ($info_page->show_categories == InfoPage::SHOW_CAT_YES) {
            $page_categories = Category::getCatsByPageId($info_page->id);
        }

        if ($info_page->show_products == InfoPage::SHOW_PRODUCT_YES) {
            $product_ids = $info_page->productIds->pluck('content_id');
            if ($product_ids) {
                $product_slider = $this->__featuredProductService->getfeaturedProducts($product_ids);
            }
        }

        $data['info_page'] = $info_page;
        $data['page_categories'] = $page_categories;
        $data['product_slider'] = $product_slider;

        return view('info_pages.show')->with($data);
    }

}
