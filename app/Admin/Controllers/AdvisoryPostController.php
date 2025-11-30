<?php

namespace App\Admin\Controllers;

use App\Models\AdvisoryPost;
use App\Models\AdvisoryCategory;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Facades\Admin;
use Carbon\Carbon;

class AdvisoryPostController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Advisory Articles';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new AdvisoryPost());

        $grid->quickSearch('title', 'content', 'tags')->placeholder('Search articles by title, content or tags...');

        // Default ordering - newest first
        $grid->model()->orderBy('created_at', 'desc');

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->equal('category_id', 'Category')->select(function () {
                return AdvisoryCategory::where('status', 'Active')
                    ->orderBy('order')
                    ->pluck('name', 'id');
            });

            $filter->equal('status', 'Status')->select([
                'Published' => 'Published',
                'Draft' => 'Draft',
                'Archived' => 'Archived',
            ]);

            $filter->equal('featured', 'Featured')->select([
                'Yes' => 'Yes',
                'No' => 'No',
            ]);

            $filter->equal('language', 'Language')->select([
                'English' => 'English',
                'Luganda' => 'Luganda',
                'Ateso' => 'Ateso',
                'Karamojong' => 'Karamojong',
            ]);

            $filter->equal('author_id', 'Author')->select(function () {
                return User::where('user_type', 'Admin')
                    ->orderBy('name')
                    ->pluck('name', 'id');
            });

            $filter->between('published_at', 'Published Date')->date();
        });

        // Columns
        $grid->column('id', 'ID')->sortable();

        $grid->column('image', 'Image')->image('', 60, 60);

        $grid->column('title', 'Article Title')->display(function () {
            $html = '<strong style="font-size: 14px;">' . substr($this->title, 0, 60) . '</strong>';

            // Media badges
            $badges = [];
            if ($this->has_video == 'Yes') $badges[] = '<i class="fa fa-video-camera text-danger"></i>';
            if ($this->has_audio == 'Yes') $badges[] = '<i class="fa fa-volume-up text-success"></i>';
            if ($this->has_youtube_video == 'Yes') $badges[] = '<i class="fa fa-youtube text-danger"></i>';
            if ($this->has_pdf == 'Yes') $badges[] = '<i class="fa fa-file-pdf-o text-warning"></i>';

            if (!empty($badges)) {
                $html .= '<br>' . implode(' ', $badges);
            }

            return $html;
        })->sortable();

        $grid->column('category.name', 'Category')->label('primary');

        $grid->column('author_name', 'Author');

        $grid->column('engagement', 'Engagement')->display(function () {
            return '<i class="fa fa-eye text-info"></i> ' . number_format($this->view_count) . ' &nbsp; ' .
                '<i class="fa fa-heart text-danger"></i> ' . number_format($this->likes_count);
        });

        $grid->column('featured', 'Featured')->display(function () {
            return $this->featured == 'Yes' ?
                '<span class="label label-warning"><i class="fa fa-star"></i> Featured</span>' :
                '';
        })->sortable();

        $grid->column('status', 'Status')->display(function () {
            $colors = [
                'Published' => 'success',
                'Draft' => 'warning',
                'Archived' => 'default',
            ];
            return '<span class="label label-' . ($colors[$this->status] ?? 'default') . '">' . $this->status . '</span>';
        })->sortable();

        $grid->column('published_at', 'Published')->display(function ($date) {
            return $date ? \Carbon\Carbon::parse($date)->format('d M Y') : 'Not Published';
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
        $show = new Show(AdvisoryPost::findOrFail($id));

        $show->panel()->style('success')->title('Article Details');

        $show->field('image', 'Featured Image')->image('', 400);

        $show->divider('Article Content');
        $show->field('title', 'Title');
        $show->field('category.name', 'Category');
        $show->field('content', 'Content')->unescape();

        $show->divider('Multimedia Content');

        $show->field('has_video', 'Has Video')->using(['Yes' => '✓ Yes', 'No' => '✗ No']);
        if ($show->model()->has_video == 'Yes') {
            $show->field('video_url', 'Video URL')->link();
        }

        $show->field('has_audio', 'Has Audio')->using(['Yes' => '✓ Yes', 'No' => '✗ No']);
        if ($show->model()->has_audio == 'Yes') {
            $show->field('audio_url', 'Audio URL')->link();
        }

        $show->field('has_youtube_video', 'Has YouTube Video')->using(['Yes' => '✓ Yes', 'No' => '✗ No']);
        if ($show->model()->has_youtube_video == 'Yes') {
            $show->field('youtube_video_url', 'YouTube URL')->link();
        }

        $show->field('has_pdf', 'Has PDF')->using(['Yes' => '✓ Yes', 'No' => '✗ No']);
        if ($show->model()->has_pdf == 'Yes') {
            $show->field('pdf_url', 'PDF URL')->link();
        }

        $show->divider('Engagement & Metadata');
        $show->field('view_count', 'Total Views')->as(function ($count) {
            return number_format($count);
        });
        $show->field('likes_count', 'Total Likes')->as(function ($count) {
            return number_format($count);
        });
        $show->field('language', 'Language');
        $show->field('tags', 'Tags')->as(function ($tags) {
            if (empty($tags)) return 'No tags';
            $tagArray = explode(',', $tags);
            $html = '';
            foreach ($tagArray as $tag) {
                $html .= '<span class="label label-default">' . trim($tag) . '</span> ';
            }
            return $html;
        })->unescape();

        $show->divider('Publication Details');
        $show->field('status', 'Status')->using([
            'Published' => '✓ Published',
            'Draft' => '✏ Draft',
            'Archived' => '📦 Archived',
        ]);
        $show->field('featured', 'Featured')->using(['Yes' => '⭐ Yes', 'No' => 'No']);
        $show->field('author_name', 'Author');
        $show->field('published_at', 'Published Date')->as(function ($date) {
            return $date ? \Carbon\Carbon::parse($date)->format('d M Y H:i') : 'Not Published';
        });

        $show->divider('System Information');
        $show->field('created_at', 'Created')->as(function ($date) {
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
        $form = new Form(new AdvisoryPost());

        $form->divider('Article Details');

        $form->select('category_id', 'Category')
            ->options(function () {
                return AdvisoryCategory::where('status', 'Active')
                    ->orderBy('order')
                    ->pluck('name', 'id');
            })
            ->rules('required')
            ->help('Select the category for this article');

        $form->text('title', 'Article Title')
            ->rules('required|string|max:255')
            ->help('Enter a clear and engaging title');

        $form->quill('content', 'Article Content')
            ->rules('required')
            ->help('Write the full article content. You can format text, add lists, etc.');


        $form->select('language', 'Language')
            ->options([
                'English' => 'English',
                'Luganda' => 'Luganda',
                'Ateso' => 'Ateso',
                'Karamojong' => 'Karamojong',
            ])
            ->default('English');

        $form->text('tags', 'Tags')
            ->placeholder('farming, maize, pests')
            ->help('Comma-separated tags for search and filtering');


        $form->divider('Featured Image');

        $form->image('image', 'Featured Image')
            ->uniqueName()
            ->help('Upload main article image (recommended: 1200x800px)');

        $form->divider('Multimedia Content (Optional)');

        $form->radio('has_video', 'Include Video?')
            ->options(['No' => 'No', 'Yes' => 'Yes'])
            ->default('No')
            ->when('Yes', function (Form $form) {
                $form->file('video_url', 'Upload Video')
                    ->uniqueName() 
                    ->help('Upload video file (MP4 recommended, max 50MB)');
            });

        $form->radio('has_audio', 'Include Audio?')
            ->options(['No' => 'No', 'Yes' => 'Yes'])
            ->default('No')
            ->when('Yes', function (Form $form) {
                $form->file('audio_url', 'Upload Audio')
                    ->uniqueName() 
                    ->help('Upload audio file (MP3 recommended, max 20MB)');
            });

        $form->radio('has_youtube_video', 'Include YouTube Video?')
            ->options(['No' => 'No', 'Yes' => 'Yes'])
            ->default('No')
            ->when('Yes', function (Form $form) {
                $form->url('youtube_video_url', 'YouTube Video URL')
                    ->placeholder('https://www.youtube.com/watch?v=...')
                    ->help('Paste the full YouTube video URL');
            });

        $form->radio('has_pdf', 'Include PDF Document?')
            ->options(['No' => 'No', 'Yes' => 'Yes'])
            ->default('No')
            ->when('Yes', function (Form $form) {
                $form->file('pdf_url', 'Upload PDF')
                    ->uniqueName() 
                    ->help('Upload PDF document (max 10MB)');
            });

        $form->divider('Publication Settings');


        $form->radio('status', 'Status')
            ->options([
                'Draft' => 'Draft',
                'Published' => 'Published',
                'Archived' => 'Archived',
            ])
            ->default('Draft')
            ->required()
            ->help('Draft = Not visible to users');

        $form->radio('featured', 'Featured Article?')
            ->options(['No' => 'No', 'Yes' => 'Yes'])
            ->default('No')
            ->help('Featured articles appear prominently');

        $form->datetime('published_at', 'Publish Date')
            ->default(Carbon::now())
            ->help('Schedule when article becomes visible');

        // Set author automatically
        $form->hidden('author_id')->default(Admin::user()->id);
        $form->hidden('author_name')->default(Admin::user()->name);

        $form->saving(function (Form $form) {
            // Ensure author is set
            if (!$form->author_id) {
                $form->author_id = Admin::user()->id;
                $form->author_name = Admin::user()->name;
            }

            // Auto-publish if status is Published and no publish date
            if ($form->status == 'Published' && !$form->published_at) {
                $form->published_at = Carbon::now();
            }
        });

        return $form;
    }
}
