<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdvisoryPostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('advisory_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('title');
            $table->longText('content');
            $table->string('image')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('author_name')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('likes_count')->default(0);
            $table->string('language')->nullable();
            $table->enum('has_video', ['Yes', 'No'])->default('No');
            $table->text('video_url')->nullable();
            $table->enum('has_audio', ['Yes', 'No'])->default('No');
            $table->text('audio_url')->nullable();
            $table->enum('has_youtube_video', ['Yes', 'No'])->default('No');
            $table->text('youtube_video_url')->nullable();
            $table->enum('has_pdf', ['Yes', 'No'])->default('No');
            $table->text('pdf_url')->nullable();
            $table->text('tags')->nullable();
            $table->enum('status', ['Published', 'Draft', 'Archived'])->default('Draft');
            $table->enum('featured', ['Yes', 'No'])->default('No');
            $table->timestamps();

            $table->index('category_id');
            $table->index('author_id');
            $table->index('status');
            $table->index('featured');
            $table->index('published_at');
            $table->index('language');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('advisory_posts');
    }
}
