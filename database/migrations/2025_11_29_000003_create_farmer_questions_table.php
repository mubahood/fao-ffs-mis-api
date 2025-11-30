<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFarmerQuestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('farmer_questions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('content');
            $table->unsignedBigInteger('author_id');
            $table->string('author_name')->nullable();
            $table->string('author_location')->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('likes_count')->default(0);
            $table->enum('has_image', ['Yes', 'No'])->default('No');
            $table->text('image_url')->nullable();
            $table->enum('has_audio', ['Yes', 'No'])->default('No');
            $table->text('audio_url')->nullable();
            $table->enum('status', ['Open', 'Answered', 'Closed'])->default('Open');
            $table->timestamps();

            $table->index('author_id');
            $table->index('status');
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
        Schema::dropIfExists('farmer_questions');
    }
}
