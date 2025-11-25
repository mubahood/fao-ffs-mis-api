<?php

namespace App\Admin\Controllers;

use App\Models\ImportTask;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Support\Facades\Auth;

class ImportTaskController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'User Import Tasks';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new ImportTask());

        $grid->model()->orderBy('id', 'desc');

        $grid->column('id', __('ID'))->sortable();
        
        $grid->column('task_name', __('Task Name'))->limit(30);
        
        $grid->column('type', __('Type'))->label([
            'user_data' => 'success',
        ]);

        $grid->column('file_path', __('CSV File'))
            ->display(function ($path) {
                $filename = basename($path);
                return "<a href='/storage/$path' target='_blank'><i class='fa fa-file-excel-o'></i> $filename</a>";
            });

        $grid->column('status', __('Status'))->display(function ($status) {
            $colors = [
                'pending' => 'warning',
                'processing' => 'info',
                'completed' => 'success',
                'failed' => 'danger',
            ];
            return "<span class='label label-{$colors[$status]}'>" . ucfirst($status) . "</span>";
        });

        $grid->column('total_rows', __('Total'))->sortable();
        $grid->column('imported_rows', __('Imported'))->sortable();
        $grid->column('failed_rows', __('Failed'))->sortable();

        $grid->column('initiated_by', __('Initiated By'))
            ->display(function ($id) {
                $user = \Encore\Admin\Auth\Database\Administrator::find($id);
                return $user ? $user->name : 'N/A';
            });

        $grid->column('created_at', __('Created'))
            ->display(function ($date) {
                return date('Y-m-d H:i', strtotime($date));
            })->sortable();

        // Action buttons
        $grid->actions(function ($actions) {
            $actions->disableView();
            $row = $actions->row;
            
            // Add validate button for pending tasks
            if ($row->status === 'pending') {
                $validateUrl = route('import.validate', $row->id);
                $actions->append('<a href="' . $validateUrl . '" target="_blank" class="btn btn-xs btn-info"><i class="fa fa-check-circle"></i> Validate</a>');
            }

            // Add import button for validated pending tasks
            if ($row->status === 'pending' && $row->total_rows > 0) {
                $importUrl = route('import.process', $row->id);
                $actions->append('<a href="' . $importUrl . '" target="_blank" class="btn btn-xs btn-success"><i class="fa fa-upload"></i> Start Import</a>');
            }

            // Disable delete for processing tasks
            if ($row->status === 'processing') {
                $actions->disableDelete();
            }
        });

        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->like('task_name', 'Task Name');
            $filter->equal('status', 'Status')->select([
                'pending' => 'Pending',
                'processing' => 'Processing',
                'completed' => 'Completed',
                'failed' => 'Failed',
            ]);
            $filter->between('created_at', 'Created Date')->datetime();
        });

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
        $show = new Show(ImportTask::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('task_name', __('Task Name'));
        $show->field('type', __('Type'));
        $show->field('file_path', __('File Path'));
        $show->field('status', __('Status'));
        $show->field('message', __('Message'));
        $show->field('mapping', __('Column Mapping'))->json();
        $show->field('total_rows', __('Total Rows'));
        $show->field('imported_rows', __('Imported Rows'));
        $show->field('failed_rows', __('Failed Rows'));
        $show->field('created_at', __('Created At'));
        $show->field('updated_at', __('Updated At'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new ImportTask());

        $form->text('task_name', __('Task Name'))
            ->rules('required')
            ->placeholder('e.g., Import Users - January 2025')
            ->help('Give this import task a descriptive name');

        $form->select('type', __('Import Type'))
            ->options(['user_data' => 'User Data'])
            ->default('user_data')
            ->rules('required')
            ->readonly();

        $form->file('file_path', __('CSV File'))
            ->rules('required|mimes:csv,txt')
            ->move('imports/' . date('Y/m'))
            ->uniqueName()
            ->help('Upload a CSV file containing user data');

        $form->divider('Column Mapping');
        
        $form->html('<p class="help-block">Map the CSV columns to user fields. Select the column letter (A, B, C, etc.) where each field is located in your CSV.</p>');

        $columns = [];
        for ($i = 65; $i <= 90; $i++) {
            $letter = chr($i);
            $columns[$letter] = "Column $letter";
        }

        $form->select('mapping[name_column]', __('Name Column'))
            ->options($columns)
            ->rules('required')
            ->help('Required: Column containing full names');

        $form->select('mapping[phone_column]', __('Phone Number Column'))
            ->options($columns)
            ->rules('required')
            ->help('Required: Column containing phone numbers');

        $form->select('mapping[group_column]', __('Group Name Column'))
            ->options($columns)
            ->rules('required')
            ->help('Required: Column containing group/FFS names');

        $form->select('mapping[gender_column]', __('Gender Column'))
            ->options($columns)
            ->help('Optional: Column containing gender (Male/Female)');

        $form->select('mapping[email_column]', __('Email Column'))
            ->options($columns)
            ->help('Optional: Column containing email addresses');

        $form->select('mapping[role_column]', __('Role Column'))
            ->options($columns)
            ->help('Optional: Column containing user roles');

        $form->hidden('status')->default('pending');
        $form->hidden('initiated_by')->value(Auth::guard('admin')->id());

        $form->saving(function (Form $form) {
            $form->initiated_by = Auth::guard('admin')->id();
            $form->status = 'pending';
        });

        $form->tools(function (Form\Tools $tools) {
            $tools->disableView();
        });

        return $form;
    }
}
