<?php

namespace App\Admin\Controllers;

use App\Models\User;
use App\Models\AccountTransaction;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Support\Facades\DB;

class FinancialAccountsController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Financial Accounts';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new User());
        
        // Only show users who are customers (members)
        $grid->model()
            ->where('user_type', 'Customer')
            ->select([
                'users.id',
                'users.name',
                'users.first_name',
                'users.last_name',
                'users.phone_number',
                'users.member_code',
                'users.status',
                DB::raw('COALESCE(SUM(CASE WHEN account_transactions.amount > 0 THEN account_transactions.amount ELSE 0 END), 0) as total_deposits'),
                DB::raw('COALESCE(SUM(CASE WHEN account_transactions.amount < 0 THEN ABS(account_transactions.amount) ELSE 0 END), 0) as total_withdrawals'),
                DB::raw('COALESCE(SUM(account_transactions.amount), 0) as balance')
            ])
            ->leftJoin('account_transactions', 'users.id', '=', 'account_transactions.user_id')
            ->groupBy('users.id', 'users.name', 'users.first_name', 'users.last_name', 'users.phone_number', 'users.member_code', 'users.status')
            ->orderBy('balance', 'desc');
        
        $grid->disableBatchActions();
        $grid->disableCreateButton();
        $grid->disableExport();
        
        $grid->actions(function ($actions) {
            $actions->disableEdit();
            $actions->disableDelete();
        });
        
        // Quick search
        $grid->quickSearch('name', 'phone_number', 'member_code')->placeholder('Search by name, phone, or member code');
        
        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            
            $filter->like('name', 'Member Name');
            $filter->like('phone_number', 'Phone Number');
            $filter->like('member_code', 'Member Code');
            
            $filter->where(function ($query) {
                $query->havingRaw('balance > ?', [$this->input]);
            }, 'Balance Greater Than')->decimal();
            
            $filter->where(function ($query) {
                $query->havingRaw('balance < ?', [$this->input]);
            }, 'Balance Less Than')->decimal();
        });

        // Columns
        $grid->column('member_code', 'Code')
            ->label('primary')
            ->copyable()
            ->sortable();
        
        $grid->column('name', 'Member Name')
            ->display(function() {
                $firstName = $this->first_name ?? '';
                $lastName = $this->last_name ?? '';
                $fullName = trim($firstName . ' ' . $lastName) ?: $this->name;
                return "<strong>$fullName</strong>";
            })
            ->sortable();
        
        $grid->column('phone_number', 'Phone')
            ->sortable();
        
        $grid->column('total_deposits', 'Total Deposits')
            ->display(function ($amount) {
                return '<span style="color: #28a745; font-weight: bold;">UGX ' . number_format($amount, 0) . '</span>';
            })
            ->sortable();
        
        $grid->column('total_withdrawals', 'Total Withdrawals')
            ->display(function ($amount) {
                return '<span style="color: #dc3545; font-weight: bold;">UGX ' . number_format($amount, 0) . '</span>';
            })
            ->sortable();
        
        $grid->column('balance', 'Balance')
            ->display(function ($balance) {
                $color = $balance >= 0 ? '#28a745' : '#dc3545';
                $prefix = $balance >= 0 ? '+' : '';
                return '<span style="color: ' . $color . '; font-weight: bold; font-size: 14px;">' . $prefix . 'UGX ' . number_format($balance, 0) . '</span>';
            })
            ->sortable();
        
        $grid->column('status', 'Status')
            ->label([
                'Active' => 'success',
                'Inactive' => 'default',
                'Pending' => 'warning',
            ])
            ->sortable();

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
        $show = new Show(User::findOrFail($id));
        
        $show->panel()->style('primary')->title('Financial Account Details');

        // Member Information
        $show->divider('Member Information');
        $show->field('member_code', 'Member Code')->label('primary');
        $show->field('name', 'Full Name');
        $show->field('phone_number', 'Phone Number');
        
        // Financial Summary
        $show->divider('Financial Summary');
        
        $show->field('financial_summary', 'Account Summary')->as(function () {
            $deposits = AccountTransaction::where('user_id', $this->id)
                ->where('amount', '>', 0)
                ->sum('amount');
            
            $withdrawals = AccountTransaction::where('user_id', $this->id)
                ->where('amount', '<', 0)
                ->sum('amount');
            
            $balance = AccountTransaction::where('user_id', $this->id)
                ->sum('amount');
            
            $transactionCount = AccountTransaction::where('user_id', $this->id)->count();
            
            return "
                <div style='background: #f8f9fa; padding: 20px; border-radius: 5px;'>
                    <div style='margin-bottom: 15px;'>
                        <strong>Total Deposits:</strong> 
                        <span style='color: #28a745; font-size: 16px;'>UGX " . number_format($deposits, 0) . "</span>
                    </div>
                    <div style='margin-bottom: 15px;'>
                        <strong>Total Withdrawals:</strong> 
                        <span style='color: #dc3545; font-size: 16px;'>UGX " . number_format(abs($withdrawals), 0) . "</span>
                    </div>
                    <div style='margin-bottom: 15px; padding-top: 10px; border-top: 2px solid #dee2e6;'>
                        <strong>Current Balance:</strong> 
                        <span style='color: " . ($balance >= 0 ? '#28a745' : '#dc3545') . "; font-size: 18px; font-weight: bold;'>UGX " . number_format($balance, 0) . "</span>
                    </div>
                    <div style='padding-top: 10px; border-top: 1px solid #dee2e6;'>
                        <strong>Total Transactions:</strong> 
                        <span>" . $transactionCount . "</span>
                    </div>
                </div>
            ";
        });
        
        // Recent Transactions
        $show->divider('Recent Transactions');
        
        $show->field('recent_transactions', 'Last 10 Transactions')->as(function () {
            $transactions = AccountTransaction::where('user_id', $this->id)
                ->orderBy('transaction_date', 'desc')
                ->limit(10)
                ->get();
            
            if ($transactions->isEmpty()) {
                return '<p style="color: #999;">No transactions found</p>';
            }
            
            $html = '<table class="table table-bordered" style="width: 100%;">
                <thead>
                    <tr style="background: #05179F; color: white;">
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Source</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($transactions as $transaction) {
                $color = $transaction->amount >= 0 ? '#28a745' : '#dc3545';
                $prefix = $transaction->amount >= 0 ? '+' : '';
                
                $html .= '<tr>
                    <td>' . date('d M Y', strtotime($transaction->transaction_date)) . '</td>
                    <td style="color: ' . $color . '; font-weight: bold;">' . $prefix . 'UGX ' . number_format($transaction->amount, 0) . '</td>
                    <td>' . ucfirst($transaction->source) . '</td>
                    <td>' . $transaction->description . '</td>
                </tr>';
            }
            
            $html .= '</tbody></table>';
            
            return $html;
        });

        $show->panel()->tools(function ($tools) {
            $tools->disableEdit();
            $tools->disableDelete();
        });

        return $show;
    }
}
