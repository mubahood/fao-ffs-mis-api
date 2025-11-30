<?php

namespace App\Admin\Controllers;

use App\Models\AdvisoryCategory;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Facades\Admin;

class AdvisoryCategoryController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Advisory Categories';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new AdvisoryCategory());

        $grid->quickSearch('name', 'description')->placeholder('Search category name or description...');

        // Default ordering
        $grid->model()->orderBy('order', 'asc');

        // Disable batch deletion
        $grid->actions(function ($actions) {
            $actions->disableDelete();
        });

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->equal('status', 'Status')->select([
                'Active' => 'Active',
                'Inactive' => 'Inactive',
            ]);

            $filter->equal('created_by_id', 'Created By')->select(function () {
                return User::where('user_type', 'Admin')
                    ->orderBy('name')
                    ->pluck('name', 'id');
            });
        });

        // Columns
        $grid->column('id', 'ID')->sortable();

        $grid->column('image', 'Image')->image('', 60, 60);

        $grid->column('name', 'Category Name')->display(function () {
            $html = '<strong style="font-size: 14px;">' . $this->name . '</strong>';
            if ($this->icon) {
                $html = '<i class="fa ' . $this->icon . ' text-primary"></i> ' . $html;
            }
            if ($this->description) {
                $html .= '<br><small class="text-muted">' . substr($this->description, 0, 80) . '...</small>';
            }
            return $html;
        })->sortable();

        $grid->column('posts_count', 'Articles')->display(function () {
            $count = $this->posts()->count();
            $published = $this->posts()->where('status', 'Published')->count();

            if ($count == 0) {
                return '<span class="text-muted">0 articles</span>';
            }

            return '<span class="label label-success">' . $published . '</span> / ' .
                '<span class="label label-default">' . $count . '</span> total';
        });

        $grid->column('order', 'Display Order')->sortable()->editable();

        $grid->column('status', 'Status')->display(function () {
            return $this->status == 'Active' ?
                '<span class="label label-success">Active</span>' :
                '<span class="label label-default">Inactive</span>';
        })->sortable();

        $grid->column('createdBy.name', 'Created By');

        $grid->column('created_at', 'Created')->display(function ($date) {
            return \Carbon\Carbon::parse($date)->format('d M Y');
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
        $show = new Show(AdvisoryCategory::findOrFail($id));

        $show->panel()->style('success')->title('Category Details');

        $show->field('image', 'Category Image')->image('', 200, 200);
        $show->field('icon', 'Icon Class')->as(function ($icon) {
            return $icon ? '<i class="fa ' . $icon . '" style="font-size: 24px;"></i> ' . $icon : 'No icon';
        })->unescape();

        $show->divider('Category Information');
        $show->field('name', 'Category Name');
        $show->field('description', 'Description');
        $show->field('order', 'Display Order');
        $show->field('status', 'Status')->using([
            'Active' => '✓ Active',
            'Inactive' => '✗ Inactive',
        ]);

        $show->divider('Statistics');
        $show->field('posts_count', 'Total Articles')->as(function () {
            return $this->posts()->count();
        });
        $show->field('published_posts_count', 'Published Articles')->as(function () {
            return $this->posts()->where('status', 'Published')->count();
        });

        $show->divider('System Information');
        $show->field('createdBy.name', 'Created By');
        $show->field('created_at', 'Created Date')->as(function ($date) {
            return \Carbon\Carbon::parse($date)->format('d M Y H:i');
        });
        $show->field('updated_at', 'Last Updated')->as(function ($date) {
            return \Carbon\Carbon::parse($date)->format('d M Y H:i');
        });

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new AdvisoryCategory());

        $form->divider('Category Information');

        $form->text('name', 'Category Name')
            ->rules('required|string|max:255')
            ->help('Enter a clear and descriptive category name');

        $form->textarea('description', 'Description')
            ->rows(3)
            ->help('Brief description of this category');

        $form->number('order', 'Display Order')
            ->default(100)
            ->min(0)
            ->help('Lower numbers appear first');

        $form->radio('status', 'Status')
            ->options(['Active' => 'Active', 'Inactive' => 'Inactive'])
            ->default('Active')
            ->required();
        $form->divider('Visual Assets');

        $form->image('image', 'Category Image')
            ->uniqueName()
            ->help('Upload a representative image for this category (recommended: 800x600px)');

        $form->text('icon', 'Icon Class')
            ->placeholder('e.g., fa-leaf, fa-tractor, fa-flask')
            ->help('Font Awesome icon class (without "fa-" prefix). See: fontawesome.com/icons');

        // Set created_by_id automatically
        $form->hidden('created_by_id')->default(Admin::user()->id);

        $form->saving(function (Form $form) {
            // Ensure created_by_id is set
            if (!$form->created_by_id) {
                $form->created_by_id = Admin::user()->id;
            }
        });

        return $form;
    }
}
