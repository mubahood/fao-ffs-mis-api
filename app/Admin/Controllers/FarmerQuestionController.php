<?php

namespace App\Admin\Controllers;

use App\Models\FarmerQuestion;
use App\Models\User;
use App\Models\Location;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class FarmerQuestionController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Farmer Questions';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new FarmerQuestion());

        $grid->quickSearch('title', 'content', 'author_name')->placeholder('Search questions...');

        // Default ordering - newest first
        $grid->model()->orderBy('created_at', 'desc');

        // Disable creation - farmers create from mobile app
        $grid->disableCreateButton();

        // Filters
        $grid->filter(function($filter){
            $filter->disableIdFilter();
            
            $filter->equal('status', 'Status')->select([
                'Open' => 'Open',
                'Answered' => 'Answered',
                'Closed' => 'Closed',
            ]);
            
            $filter->equal('author_id', 'Asked By')->select(function() {
                return User::where('user_type', 'Customer')
                    ->orderBy('name')
                    ->pluck('name', 'id');
            });
            
            $filter->like('author_location', 'Location');
            
            $filter->between('created_at', 'Asked Date')->date();
        });

        // Columns
        $grid->column('id', 'ID')->sortable();
        
        $grid->column('image_url', 'Image')->display(function($url) {
            if ($this->has_image == 'Yes' && $url) {
                return '<img src="' . $url . '" style="width:60px;height:60px;object-fit:cover;border-radius:4px;">';
            }
            return '<i class="fa fa-question-circle text-muted" style="font-size:30px;"></i>';
        });
        
        $grid->column('title', 'Question')->display(function() {
            $html = '<strong style="font-size: 14px;">' . substr($this->title, 0, 60) . '</strong><br>';
            $html .= '<small class="text-muted">' . substr(strip_tags($this->content), 0, 80) . '...</small>';
            
            // Media badges
            $badges = [];
            if ($this->has_image == 'Yes') $badges[] = '<i class="fa fa-camera text-primary"></i>';
            if ($this->has_audio == 'Yes') $badges[] = '<i class="fa fa-volume-up text-success"></i>';
            
            if (!empty($badges)) {
                $html .= '<br>' . implode(' ', $badges);
            }
            
            return $html;
        })->sortable();
        
        $grid->column('author_name', 'Asked By')->display(function() {
            $html = '<strong>' . $this->author_name . '</strong>';
            if ($this->author_location) {
                $html .= '<br><small class="text-muted"><i class="fa fa-map-marker"></i> ' . $this->author_location . '</small>';
            }
            return $html;
        });
        
        $grid->column('answers_count', 'Answers')->display(function() {
            $total = $this->answers()->count();
            $approved = $this->answers()->where('is_approved', 'Yes')->count();
            $accepted = $this->answers()->where('is_accepted', 'Yes')->count();
            
            if ($total == 0) {
                return '<span class="text-muted">No answers</span>';
            }
            
            $html = '<span class="label label-success">' . $approved . '</span> approved';
            if ($accepted > 0) {
                $html .= '<br><span class="label label-primary"><i class="fa fa-check"></i> Accepted</span>';
            }
            
            return $html;
        });
        
        $grid->column('engagement', 'Engagement')->display(function() {
            return '<i class="fa fa-eye text-info"></i> ' . number_format($this->view_count) . ' &nbsp; ' .
                   '<i class="fa fa-heart text-danger"></i> ' . number_format($this->likes_count);
        });
        
        $grid->column('status', 'Status')->display(function() {
            $colors = [
                'Open' => 'warning',
                'Answered' => 'success',
                'Closed' => 'default',
            ];
            return '<span class="label label-' . ($colors[$this->status] ?? 'default') . '">' . $this->status . '</span>';
        })->sortable();
        
        $grid->column('created_at', 'Asked On')->display(function($date) {
            return \Carbon\Carbon::parse($date)->format('d M Y');
        })->sortable();

        // Custom actions
        $grid->actions(function ($actions) {
            $actions->disableDelete();
            
            // Add button to view answers
            $actions->append('<a href="' . admin_url('farmer-question-answers?question_id=' . $actions->row->id) . '" 
                class="btn btn-xs btn-primary" title="View Answers">
                <i class="fa fa-comments"></i> Answers (' . $actions->row->answers()->count() . ')
            </a>');
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
        $show = new Show(FarmerQuestion::findOrFail($id));
        
        $show->panel()->style('success')->title('Question Details');

        if ($show->model()->has_image == 'Yes' && $show->model()->image_url) {
            $show->field('image_url', 'Question Image')->image('', 400);
        }

        $show->divider('Question Content');
        $show->field('title', 'Title');
        $show->field('content', 'Full Question')->unescape();

        $show->divider('Asked By');
        $show->field('author_name', 'Farmer Name');
        $show->field('author.phone_number', 'Phone Number')->as(function($phone) {
            return $phone ? '<a href="tel:' . $phone . '">' . $phone . '</a>' : 'N/A';
        })->unescape();
        $show->field('author_location', 'Location');

        if ($show->model()->has_audio == 'Yes' && $show->model()->audio_url) {
            $show->divider('Audio Recording');
            $show->field('audio_url', 'Listen to Audio')->as(function($url) {
                return '<audio controls style="width:100%;"><source src="' . $url . '" type="audio/mpeg">Your browser does not support audio.</audio>';
            })->unescape();
        }

        $show->divider('Engagement & Status');
        $show->field('view_count', 'Total Views')->as(function($count) {
            return number_format($count);
        });
        $show->field('likes_count', 'Total Likes')->as(function($count) {
            return number_format($count);
        });
        $show->field('answers_count', 'Total Answers')->as(function() {
            return $this->answers()->count();
        });
        $show->field('approved_answers', 'Approved Answers')->as(function() {
            return $this->answers()->where('is_approved', 'Yes')->count();
        });
        $show->field('status', 'Status')->using([
            'Open' => '🟡 Open',
            'Answered' => '✅ Answered',
            'Closed' => '🔒 Closed',
        ]);

        $show->divider('System Information');
        $show->field('created_at', 'Asked Date')->as(function($date) {
            return \Carbon\Carbon::parse($date)->format('d M Y H:i');
        });
        $show->field('updated_at', 'Last Updated')->as(function($date) {
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
        $form = new Form(new FarmerQuestion());

        // Note: This form is mainly for editing/moderating questions
        // Farmers create questions from mobile app
        
        $form->divider('Question Status Management');
        
        $form->display('title', 'Question Title');
        $form->display('content', 'Question Content')->with(function($value) {
            return strip_tags($value);
        });
        
        $form->display('author_name', 'Asked By');
        $form->display('author_location', 'Location');
        
        $form->radio('status', 'Question Status')
            ->options([
                'Open' => 'Open - Accepting Answers',
                'Answered' => 'Answered - Has Approved Answers',
                'Closed' => 'Closed - No More Answers',
            ])
            ->required()
            ->help('Open: Question accepts new answers | Answered: Has approved answers | Closed: Locked, no new answers');

        $form->divider('Statistics (Read-Only)');
        
        $form->display('view_count', 'Total Views')->with(function($value) {
            return number_format($value);
        });
        
        $form->display('likes_count', 'Total Likes')->with(function($value) {
            return number_format($value);
        });
        
        $form->display('created_at', 'Asked On')->with(function($value) {
            return \Carbon\Carbon::parse($value)->format('d M Y H:i');
        });

        // Disable delete
        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
        });

        return $form;
    }
}
