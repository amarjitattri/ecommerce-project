<?php
namespace App\Http\View\Composers;


use Illuminate\View\View;
use App\Repositories\Category\Interfaces\ShopAllCategoryRepositoryInterface;
class ShopAllComposer {

    public function __construct()
    {

    }
    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $categoryTree = app('CategoryHelper')->getCategoryTree();
        $view->with('categories', $categoryTree);
    }
}
