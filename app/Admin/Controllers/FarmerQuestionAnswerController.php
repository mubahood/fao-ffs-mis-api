<?php

namespace App\Admin\Controllers;

use App\Models\FarmerQuestionAnswer;
use App\Models\FarmerQuestion;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Facades\Admin;

class FarmerQuestionAnswerController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Farmer Question Answers';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new FarmerQuestionAnswer());

        $grid->quickSearch('content', 'author_name')->placeholder('Search answers...');

        // Default ordering - newest first
        $grid->model()->orderBy('created_at', 'desc');

        // Check if filtering by question
        if ($questionId = request('question_id')) {
            $grid->model()->where('question_id', $questionId);
            
            // Show question title in header
            $question = FarmerQuestion::find($questionId);
            if ($question) {
                $grid->header(function () use ($question) {
                    return '<div class="alert alert-info">
                        <h4><i class="fa fa-question-circle"></i> Question: ' . $question->title . '</h4>
                        <p>' . substr(strip_tags($question->content), 0, 200) . '...</p>
                        <a href="' . admin_url('farmer-questions/' . $question->id) . '" class="btn btn-sm btn-primary">
                            <i class="fa fa-eye"></i> View Full Question
                        </a>
                    </div>';
                });
            }
        }

        // Filters
        $grid->filter(function($filter){
            $filter->disableIdFilter();
            
            if (!request('question_id')) {
                $filter->equal('question_id', 'Question')->select(function() {
                    return FarmerQuestion::orderBy('created_at', 'desc')
                        ->limit(100)
                        ->pluck('title', 'id');
                });
            }
            
            $filter->equal('is_approved', 'Approval Status')->select([
                'Yes' => 'Approved',
                'No' => 'Pending',
            ]);
            
            $filter->equal('is_accepted', 'Accepted Answer')->select([
                'Yes' => 'Yes',
                'No' => 'No',
            ]);
            
            $filter->equal('status', 'Status')->select([
                'Published' => 'Published',
                'Draft' => 'Draft',
                'Archived' => 'Archived',
            ]);
            
            $filter->equal('author_id', 'Answered By')->select(function() {
                return User::orderBy('name')
                    ->pluck('name', 'id');
            });
            
            $filter->between('created_at', 'Answer Date')->date();
        });

        // Columns
        $grid->column('id', 'ID')->sortable();
        
        $grid->column('question.title', 'Question')->display(function() {
            if (!$this->question) return '<span class="text-muted">Question Deleted</span>';
            return '<small class="text-muted">' . substr($this->question->title, 0, 50) . '...</small>';
        });
        
        $grid->column('content', 'Answer')->display(function() {
            $html = '<div style="max-width:300px;">' . substr(strip_tags($this->content), 0, 100) . '...</div>';
            
            // Media badges
            $badges = [];
            if ($this->has_image == 'Yes') $badges[] = '<i class="fa fa-camera text-primary"></i>';
            if ($this->has_audio == 'Yes') $badges[] = '<i class="fa fa-volume-up text-success"></i>';
            if ($this->has_video == 'Yes') $badges[] = '<i class="fa fa-video-camera text-danger"></i>';
            if ($this->has_youtube_video == 'Yes') $badges[] = '<i class="fa fa-youtube text-danger"></i>';
            if ($this->has_pdf == 'Yes') $badges[] = '<i class="fa fa-file-pdf-o text-warning"></i>';
            
            if (!empty($badges)) {
                $html .= '<br>' . implode(' ', $badges);
            }
            
            return $html;
        });
        
        $grid->column('author_name', 'Answered By')->display(function() {
            $html = '<strong>' . $this->author_name . '</strong>';
            if ($this->author_location) {
                $html .= '<br><small class="text-muted"><i class="fa fa-map-marker"></i> ' . $this->author_location . '</small>';
            }
            return $html;
        });
        
        $grid->column('is_approved', 'Approved')->display(function() {
            return $this->is_approved == 'Yes' ? 
                '<span class="label label-success"><i class="fa fa-check"></i> Approved</span>' : 
                '<span class="label label-warning"><i class="fa fa-clock-o"></i> Pending</span>';
        })->sortable();
        
        $grid->column('is_accepted', 'Accepted')->display(function() {
            return $this->is_accepted == 'Yes' ? 
                '<span class="label label-primary"><i class="fa fa-star"></i> Accepted</span>' : 
                '';
        })->sortable();
        
        $grid->column('likes_count', 'Likes')->display(function() {
            return '<i class="fa fa-heart text-danger"></i> ' . number_format($this->likes_count);
        })->sortable();
        
        $grid->column('status', 'Status')->display(function() {
            $colors = [
                'Published' => 'success',
                'Draft' => 'warning',
                'Archived' => 'default',
            ];
            return '<span class="label label-' . ($colors[$this->status] ?? 'default') . '">' . $this->status . '</span>';
        })->sortable();
        
        $grid->column('created_at', 'Answered On')->display(function($date) {
            return \Carbon\Carbon::parse($date)->format('d M Y');
        })->sortable();

        // Custom actions for approval
        $grid->actions(function ($actions) {
            // Quick approve button
            if ($actions->row->is_approved == 'No') {
                $actions->append('<a href="' . admin_url('farmer-question-answers/' . $actions->row->id . '/approve') . '" 
                    class="btn btn-xs btn-success" title="Approve Answer">
                    <i class="fa fa-check"></i> Approve
                </a>');
            }
            
            // Quick accept button (only for approved answers)
            if ($actions->row->is_approved == 'Yes' && $actions->row->is_accepted == 'No') {
                $actions->append('<a href="' . admin_url('farmer-question-answers/' . $actions->row->id . '/accept') . '" 
                    class="btn btn-xs btn-primary" title="Mark as Accepted Answer">
                    <i class="fa fa-star"></i> Accept
                </a>');
            }
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
        $show = new Show(FarmerQuestionAnswer::findOrFail($id));
        
        $show->panel()->style('success')->title('Answer Details');

        $show->divider('Related Question');
        $show->field('question.title', 'Question Title');
        $show->field('question.content', 'Question Content')->unescape();

        $show->divider('Answer Content');
        $show->field('content', 'Answer')->unescape();

        // Show multimedia content
        if ($show->model()->has_image == 'Yes' && $show->model()->image_url) {
            $show->field('image_url', 'Image')->image('', 400);
        }
        
        if ($show->model()->has_audio == 'Yes' && $show->model()->audio_url) {
            $show->field('audio_url', 'Audio')->as(function($url) {
                return '<audio controls style="width:100%;"><source src="' . $url . '" type="audio/mpeg"></audio>';
            })->unescape();
        }
        
        if ($show->model()->has_video == 'Yes' && $show->model()->video_url) {
            $show->field('video_url', 'Video')->as(function($url) {
                return '<video controls style="max-width:100%;"><source src="' . $url . '" type="video/mp4"></video>';
            })->unescape();
        }
        
        if ($show->model()->has_youtube_video == 'Yes' && $show->model()->youtube_video_url) {
            $show->field('youtube_video_url', 'YouTube Video')->link();
        }
        
        if ($show->model()->has_pdf == 'Yes' && $show->model()->pdf_url) {
            $show->field('pdf_url', 'PDF Document')->link();
        }

        $show->divider('Answered By');
        $show->field('author_name', 'Name');
        $show->field('author.phone_number', 'Phone')->as(function($phone) {
            return $phone ? '<a href="tel:' . $phone . '">' . $phone . '</a>' : 'N/A';
        })->unescape();
        $show->field('author_location', 'Location');

        $show->divider('Moderation & Engagement');
        $show->field('is_approved', 'Approval Status')->using([
            'Yes' => '✅ Approved',
            'No' => '⏳ Pending Approval',
        ]);
        $show->field('is_accepted', 'Accepted Answer')->using([
            'Yes' => '⭐ Accepted by Question Author',
            'No' => 'Not Accepted',
        ]);
        $show->field('status', 'Publication Status')->using([
            'Published' => '✅ Published',
            'Draft' => '✏ Draft',
            'Archived' => '📦 Archived',
        ]);
        $show->field('likes_count', 'Total Likes')->as(function($count) {
            return number_format($count);
        });

        $show->divider('System Information');
        $show->field('created_at', 'Answered Date')->as(function($date) {
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
        $form = new Form(new FarmerQuestionAnswer());

        // Can create answers as admin
        if ($form->isCreating()) {
            $form->divider('Select Question');
            
            $form->select('question_id', 'Question')
                ->options(function() {
                    return FarmerQuestion::where('status', '!=', 'Closed')
                        ->orderBy('created_at', 'desc')
                        ->limit(100)
                        ->pluck('title', 'id');
                })
                ->rules('required')
                ->help('Select the question you are answering');
            
            $form->divider('Your Answer');
            
            $form->editor('content', 'Answer Content')
                ->rules('required')
                ->help('Provide a detailed and helpful answer');
            
            $form->divider('Multimedia (Optional)');
            
            $form->radio('has_image', 'Include Image?')
                ->options(['No' => 'No', 'Yes' => 'Yes'])
                ->default('No')
                ->when('Yes', function (Form $form) {
                    $form->image('image_url', 'Upload Image')
                        ->uniqueName()
                        ->move('advisory/answers/images')
                        ->help('Upload supporting image');
                });
            
            $form->radio('has_video', 'Include Video?')
                ->options(['No' => 'No', 'Yes' => 'Yes'])
                ->default('No')
                ->when('Yes', function (Form $form) {
                    $form->file('video_url', 'Upload Video')
                        ->uniqueName()
                        ->move('advisory/answers/videos')
                        ->help('Upload video (max 50MB)');
                });
            
            $form->radio('has_youtube_video', 'Include YouTube Video?')
                ->options(['No' => 'No', 'Yes' => 'Yes'])
                ->default('No')
                ->when('Yes', function (Form $form) {
                    $form->url('youtube_video_url', 'YouTube URL')
                        ->help('Paste YouTube video URL');
                });
            
            $form->radio('has_pdf', 'Include PDF?')
                ->options(['No' => 'No', 'Yes' => 'Yes'])
                ->default('No')
                ->when('Yes', function (Form $form) {
                    $form->file('pdf_url', 'Upload PDF')
                        ->uniqueName()
                        ->move('advisory/answers/pdfs')
                        ->help('Upload PDF document (max 10MB)');
                });
            
            // Set author automatically
            $form->hidden('author_id')->default(Admin::user()->id);
            $form->hidden('author_name')->default(Admin::user()->name);
            $form->hidden('is_approved')->default('Yes'); // Admin answers auto-approved
            $form->hidden('status')->default('Published');
            
        } else {
            // Editing existing answer
            $form->display('question.title', 'Question');
            $form->display('content', 'Answer Content')->with(function($value) {
                return strip_tags($value);
            });
            $form->display('author_name', 'Answered By');
            
            $form->divider('Moderation');
            
            $form->radio('is_approved', 'Approval Status')
                ->options(['No' => 'Pending', 'Yes' => 'Approved'])
                ->required()
                ->help('Approved answers are visible to all users');
            
            $form->radio('is_accepted', 'Accepted Answer')
                ->options(['No' => 'No', 'Yes' => 'Yes - Best Answer'])
                ->default('No')
                ->help('Only one answer can be marked as accepted per question');
            
            $form->radio('status', 'Publication Status')
                ->options([
                    'Published' => 'Published',
                    'Draft' => 'Draft',
                    'Archived' => 'Archived',
                ])
                ->required();
        }

        $form->saving(function (Form $form) {
            // Ensure author is set
            if ($form->isCreating() && !$form->author_id) {
                $form->author_id = Admin::user()->id;
                $form->author_name = Admin::user()->name;
            }
        });

        return $form;
    }

    /**
     * Quick approve an answer
     */
    public function approve($id)
    {
        $answer = FarmerQuestionAnswer::findOrFail($id);
        $answer->is_approved = 'Yes';
        $answer->status = 'Published';
        $answer->save();
        
        // Update question status
        $answer->question->updateStatus();
        
        admin_toastr('Answer approved successfully', 'success');
        return redirect()->back();
    }

    /**
     * Mark answer as accepted
     */
    public function accept($id)
    {
        $answer = FarmerQuestionAnswer::findOrFail($id);
        
        if ($answer->is_approved != 'Yes') {
            admin_toastr('Only approved answers can be marked as accepted', 'error');
            return redirect()->back();
        }
        
        $answer->markAsAccepted();
        
        admin_toastr('Answer marked as accepted', 'success');
        return redirect()->back();
    }
}
