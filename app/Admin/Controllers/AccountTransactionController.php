<?php

namespace App\Admin\Controllers;

use App\Models\AccountTransaction;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class AccountTransactionController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Account Transactions';

    /**
     * Get dynamic title based on URL
     */
    protected function title()
    {
        $url = request()->url();
        
        if (strpos($url, 'account-transactions-deposit') !== false) {
            return 'Deposits';
        } elseif (strpos($url, 'account-transactions-withdraw') !== false) {
            return 'Withdrawals';
        }
        
        return 'All Account Transactions';
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new AccountTransaction());
        
        // Detect transaction type from URL and filter accordingly
        $url = request()->url();
        
        if (strpos($url, 'account-transactions-deposit') !== false) {
            // Show only deposits (positive amounts)
            $grid->model()->where('amount', '>', 0)->orderBy('transaction_date', 'desc');
        } elseif (strpos($url, 'account-transactions-withdraw') !== false) {
            // Show only withdrawals (negative amounts)
            $grid->model()->where('amount', '<', 0)->orderBy('transaction_date', 'desc');
        } else {
            // Show all transactions
            $grid->model()->orderBy('transaction_date', 'desc');
        }
        $grid->disableExport();
        
        $grid->quickSearch('description')->placeholder('Search by description');
        
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            
            $filter->equal('user_id', 'User')
                ->select(User::pluck('name', 'id'));
            
            $filter->equal('source', 'Source')
                ->select([
                    'disbursement' => 'Disbursement',
                    'withdrawal' => 'Withdrawal',
                    'deposit' => 'Deposit',
                ]);
            
            $filter->between('transaction_date', 'Date')->date();
        });

        $grid->column('id', __('ID'))->sortable();
        
        $grid->column('user.name', __('User'))
            ->sortable();
        
        $grid->column('user.phone_number', __('Phone'));
        
        $grid->column('amount', __('Amount'))
            ->display(function ($amount) {
                $prefix = $amount >= 0 ? '+' : '';
                return $prefix . 'UGX ' . number_format($amount, 0);
            })
            ->sortable();
        
        $grid->column('source', __('Source'))
            ->label([
                'disbursement' => 'primary',
                'withdrawal' => 'danger',
                'deposit' => 'success',
            ])
            ->sortable();
        
        $grid->column('description', __('Description'))
            ->display(function ($desc) {
                return \Illuminate\Support\Str::limit($desc, 50);
            });
        
        $grid->column('transaction_date', __('Date'))
            ->display(function ($date) {
                return date('d M Y', strtotime($date));
            })
            ->sortable();
        
        $grid->column('creator.name', __('Created By'));
        
        $grid->column('created_at', __('Created'))
            ->display(function ($date) {
                return date('d M Y, H:i', strtotime($date));
            })
            ->sortable();

        $grid->actions(function ($actions) {
            $actions->disableEdit();
            $actions->disableView();
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
        $show = new Show(AccountTransaction::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('user.name', __('User'));
        $show->field('user.phone_number', __('Phone'));
        $show->field('user.email', __('Email'));
        
        $show->field('amount', __('Amount'))->as(function ($amount) {
            $prefix = $amount >= 0 ? '+' : '';
            return $prefix . 'UGX ' . number_format($amount, 0);
        });
        
        $show->field('source', __('Source'));
        $show->field('description', __('Description'));
        $show->field('transaction_date', __('Transaction Date'));
        
        $show->field('relatedDisbursement.project.title', __('Related Project'));
        
        $show->field('creator.name', __('Created By'));
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
        $form = new Form(new AccountTransaction());

        $form->select('user_id', __('User'))
            ->options(User::pluck('name', 'id'))
            ->rules('required');
        
        $form->decimal('amount', __('Amount (UGX)'))
            ->rules('required|numeric')
            ->help('Positive for deposit/disbursement, negative for withdrawal');
        
        $form->select('source', __('Source'))
            ->options([
                'deposit' => 'Deposit',
                'withdrawal' => 'Withdrawal',
                'disbursement' => 'Disbursement',
            ])
            ->rules('required');
        
        $form->textarea('description', __('Description'))
            ->rules('required')
            ->rows(3);
        
        $form->date('transaction_date', __('Transaction Date'))
            ->default(date('Y-m-d'))
            ->rules('required');
        
        $form->hidden('created_by_id')->default(auth()->id());

        $form->disableCreatingCheck();
        $form->disableReset();
        $form->disableViewCheck();

        return $form;
    }
}
