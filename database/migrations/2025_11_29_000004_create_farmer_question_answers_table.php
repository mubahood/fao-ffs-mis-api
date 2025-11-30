<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFarmerQuestionAnswersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('farmer_question_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id');
            $table->longText('content');
            $table->unsignedBigInteger('author_id');
            $table->string('author_name')->nullable();
            $table->string('author_location')->nullable();
            $table->integer('likes_count')->default(0);
            $table->enum('has_image', ['Yes', 'No'])->default('No');
            $table->text('image_url')->nullable();
            $table->enum('has_audio', ['Yes', 'No'])->default('No');
            $table->text('audio_url')->nullable();
            $table->enum('has_video', ['Yes', 'No'])->default('No');
            $table->text('video_url')->nullable();
            $table->enum('has_youtube_video', ['Yes', 'No'])->default('No');
            $table->text('youtube_video_url')->nullable();
            $table->enum('has_pdf', ['Yes', 'No'])->default('No');
            $table->text('pdf_url')->nullable();
            $table->enum('is_approved', ['Yes', 'No'])->default('No');
            $table->enum('is_accepted', ['Yes', 'No'])->default('No');
            $table->enum('status', ['Published', 'Draft', 'Archived'])->default('Published');
            $table->timestamps();

            $table->index('question_id');
            $table->index('author_id');
            $table->index('status');
            $table->index('is_approved');
            $table->index('is_accepted');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('farmer_question_answers');
    }
}
