<?php

namespace App\Admin\Controllers;

use App\Models\Image;
use App\Models\Product;
use App\Models\User;
use App\Models\Utils;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Support\Facades\Auth;

class ProductController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Products';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Product());

        $grid->actions(function ($actions) {
            $actions->disableView();
        });
        $grid->disableExport();

        $grid->quickSearch('name')->placeholder('Search by name');

        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->like('name', 'Product Name');
            $cats = \App\Models\ProductCategory::all();
            $filter->equal('category', 'Category')->select(
                $cats->pluck('category', 'id')
            );
            $filter->between('price_1', 'Selling Price (UGX)');
            $filter->between('created_at', 'Created Date')->datetime();
        });
        $grid->model()->orderBy('id', 'desc');

        $grid->column('feature_photo', __('Photo'))
            ->image('', 50, 50)
            ->sortable();

        $grid->column('id', __('ID'))->sortable();
        
        $grid->column('name', __('Product Name'))->sortable()
            ->editable();
        
        $grid->column('price_1', __('Selling Price (UGX)'))
            ->sortable()
            ->editable();

        $grid->column('category', __('Category'))
            ->display(function ($category) {
                $c = \App\Models\ProductCategory::find($category);
                return $c ? $c->category : 'Deleted';
            })
            ->sortable();

        $grid->column('user', __('Seller'))
            ->display(function ($user) {
                $u = \App\Models\User::find($user);
                return $u ? $u->name : 'Deleted';
            })
            ->sortable();

        $grid->column('status', __('Status'))
            ->editable('select', ['Active' => 'Active', 'Inactive' => 'Inactive'])
            ->filter([
                'Active' => 'Active',
                'Inactive' => 'Inactive',
            ])->sortable();

        $grid->column('created_at', __('Created'))
            ->display(function ($created_at) {
                return date('Y-m-d H:i:s', strtotime($created_at));
            })->sortable();

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Product::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('name', __('Product Name'));
        $show->field('description', __('Description'));
        $show->field('price_1', __('Selling Price (UGX)'));
        $show->field('feature_photo', __('Feature Photo'));
        $show->field('category', __('Category'));
        $show->field('user', __('User'));
        $show->field('status', __('Status'));
        $show->field('created_at', __('Created At'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Product());
        
        // Hidden fields with auto-generated values
        $form->hidden('local_id')->value(Utils::get_unique_text());
        $form->hidden('currency')->default('UGX');
        $form->hidden('has_colors')->default('No');
        $form->hidden('has_sizes')->default('No');
        $form->hidden('home_section_1')->default('No');
        $form->hidden('home_section_2')->default('No');
        $form->hidden('home_section_3')->default('No');

        // Essential fields only
        $form->text('name', __('Product Name'))
            ->rules('required')
            ->placeholder('e.g., Maize Seeds, Cow, Tractor')
            ->help('Enter the name of the agricultural product');

        $form->text('price_1', __('Selling Price (UGX)'))
            ->rules('required|numeric|min:0')
            ->placeholder('e.g., 50000')
            ->help('Enter the selling price in Uganda Shillings');

        $cats = \App\Models\ProductCategory::all();
        $form->select('category', __('Category'))
            ->options($cats->pluck('category', 'id'))
            ->rules('required')
            ->help('Select the product category');

        $form->quill('description', __('Description'))
            ->rules('required')
            ->help('Describe the product in detail');

        $form->image('feature_photo', __('Product Photo'))
            ->rules('required')
            ->help('Upload the main product image');

        $form->divider('Additional Photos (Optional)');
        
        $form->hasMany('images', 'More Images', function (Form\NestedForm $form) {
            $u = Auth::user();
            $form->image('src', 'Image')->uniqueName();
            $form->hidden('administrator_id')->value($u->id);
        });

        // Set default values on save
        $form->saving(function (Form $form) {
            if (!$form->status) {
                $form->status = 'Active';
            }
            // Auto-set price_2 same as price_1
            $form->price_2 = $form->price_1;
        });

        return $form;
    }
}
