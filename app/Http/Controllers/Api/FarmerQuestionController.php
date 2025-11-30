<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmerQuestion;
use App\Models\FarmerQuestionAnswer;
use App\Models\Utils;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class FarmerQuestionController extends Controller
{
    use ApiResponser;

    /**
     * Get all questions with filters
     */
    public function getQuestions(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 20);
            $query = FarmerQuestion::query();

            // Filter by status
            if ($request->filled('status')) {
                if ($request->status === 'open') {
                    $query->open();
                } elseif ($request->status === 'answered') {
                    $query->answered();
                }
            }

            // Search
            if ($request->filled('search')) {
                $query->search($request->search);
            }

            // Order by most recent
            $query->orderBy('created_at', 'desc');

            $questions = $query->paginate($perPage);

            $data = [
                'questions' => $questions->map(function ($question) {
                    return $this->formatQuestion($question);
                }),
                'pagination' => [
                    'current_page' => $questions->currentPage(),
                    'last_page' => $questions->lastPage(),
                    'per_page' => $questions->perPage(),
                    'total' => $questions->total(),
                    'has_more' => $questions->hasMorePages(),
                ],
            ];

            return $this->success($data, 'Questions retrieved successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get my questions
     */
    public function getMyQuestions(Request $request)
    {
        try {
            $userId = $request->user;

            if (!$userId) {
                return $this->error('User not authenticated', 401);
            }

            $perPage = $request->input('per_page', 20);
            
            $questions = FarmerQuestion::where('author_id', $userId)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $data = [
                'questions' => $questions->map(function ($question) {
                    return $this->formatQuestion($question);
                }),
                'pagination' => [
                    'current_page' => $questions->currentPage(),
                    'last_page' => $questions->lastPage(),
                    'per_page' => $questions->perPage(),
                    'total' => $questions->total(),
                    'has_more' => $questions->hasMorePages(),
                ],
            ];

            return $this->success($data, 'My questions retrieved successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get single question with answers
     */
    public function getQuestion(Request $request, $id)
    {
        try {
            $question = FarmerQuestion::find($id);

            if (!$question) {
                return $this->error('Question not found', 404);
            }

            // Increment view count
            $question->incrementViewCount();

            $data = $this->formatQuestion($question, true);

            // Add answers
            $answers = $question->approvedAnswers()
                ->orderBy('is_accepted', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($answer) {
                    return $this->formatAnswer($answer);
                });

            $data['answers'] = $answers;
            $data['answers_count'] = $answers->count();

            return $this->success($data, 'Question retrieved successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Post a new question
     */
    public function postQuestion(Request $request)
    {
        try {
            $userId = $request->user;

            if (!$userId) {
                return $this->error('User not authenticated', 401);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|min:10|max:255',
                'content' => 'required|string|min:20',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
                'audio' => 'nullable|file|max:20480',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first());
            }

            // Custom audio file extension validation
            if ($request->hasFile('audio')) {
                $audioExt = strtolower($request->file('audio')->getClientOriginalExtension());
                $allowedAudioExts = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'oga', 'webm', '3gp', 'flac', 'mp4', 'mpeg'];
                if (!in_array($audioExt, $allowedAudioExts)) {
                    return $this->error('Audio file must have extension: ' . implode(', ', $allowedAudioExts));
                }
            }

            $data = [
                'title' => $request->title,
                'content' => $request->content,
                'author_id' => $userId,
                'status' => 'Open',
            ];

            // Handle image upload
            if ($request->hasFile('image')) {
                $file_name = Utils::uploadMedia($request->file('image'), ['jpeg', 'jpg', 'png'], 5);
                if ($file_name) {
                    $data['has_image'] = 'Yes';
                    $data['image_url'] = $file_name;
                }
            }

            // Handle audio upload
            if ($request->hasFile('audio')) {
                $file_name = Utils::uploadMedia($request->file('audio'), ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'oga', 'webm', '3gp', 'flac'], 20);
                if ($file_name) {
                    $data['has_audio'] = 'Yes';
                    $data['audio_url'] = $file_name;
                }
            }

            $question = FarmerQuestion::create($data);

            return $this->success(
                $this->formatQuestion($question),
                'Question posted successfully. It will be visible to others once approved.'
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Post an answer to a question
     */
    public function postAnswer(Request $request, $questionId)
    {
        try {
            $userId = $request->user;

            if (!$userId) {
                return $this->error('User not authenticated', 401);
            }

            $question = FarmerQuestion::find($questionId);

            if (!$question) {
                return $this->error('Question not found', 404);
            }

            if ($question->status === 'Closed') {
                return $this->error('This question is closed and no longer accepting answers');
            }

            $validator = Validator::make($request->all(), [
                'content' => 'required|string|min:20',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
                'audio' => 'nullable|file|max:20480',
                'video' => 'nullable|file|max:51200',
                'youtube_video_url' => 'nullable|url',
                'pdf' => 'nullable|file|mimes:pdf|max:10240',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first());
            }

            // Custom audio file extension validation
            if ($request->hasFile('audio')) {
                $audioExt = strtolower($request->file('audio')->getClientOriginalExtension());
                $allowedAudioExts = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'oga', 'webm', '3gp', 'flac', 'mp4', 'mpeg'];
                if (!in_array($audioExt, $allowedAudioExts)) {
                    return $this->error('Audio file must have extension: ' . implode(', ', $allowedAudioExts));
                }
            }

            // Custom video file extension validation
            if ($request->hasFile('video')) {
                $videoExt = strtolower($request->file('video')->getClientOriginalExtension());
                $allowedVideoExts = ['mp4', 'mov', 'avi', '3gp', 'mkv', 'webm', 'flv'];
                if (!in_array($videoExt, $allowedVideoExts)) {
                    return $this->error('Video file must have extension: ' . implode(', ', $allowedVideoExts));
                }
            }

            $data = [
                'question_id' => $questionId,
                'content' => $request->content,
                'author_id' => $userId,
                'is_approved' => 'No', // Requires admin approval
                'status' => 'Draft',
            ];

            // Handle image upload
            if ($request->hasFile('image')) {
                $file_name = Utils::uploadMedia($request->file('image'), ['jpeg', 'jpg', 'png'], 5);
                if ($file_name) {
                    $data['has_image'] = 'Yes';
                    $data['image_url'] = $file_name;
                }
            }

            // Handle audio upload
            if ($request->hasFile('audio')) {
                $file_name = Utils::uploadMedia($request->file('audio'), ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'oga', 'webm', '3gp', 'flac'], 20);
                if ($file_name) {
                    $data['has_audio'] = 'Yes';
                    $data['audio_url'] = $file_name;
                }
            }

            // Handle video upload
            if ($request->hasFile('video')) {
                $file_name = Utils::uploadMedia($request->file('video'), ['mp4', 'mov', 'avi', '3gp', 'mkv', 'webm'], 50);
                if ($file_name) {
                    $data['has_video'] = 'Yes';
                    $data['video_url'] = $file_name;
                }
            }

            // Handle YouTube URL
            if ($request->filled('youtube_video_url')) {
                $data['has_youtube_video'] = 'Yes';
                $data['youtube_video_url'] = $request->youtube_video_url;
            }

            // Handle PDF upload
            if ($request->hasFile('pdf')) {
                $file_name = Utils::uploadMedia($request->file('pdf'), ['pdf'], 10);
                if ($file_name) {
                    $data['has_pdf'] = 'Yes';
                    $data['pdf_url'] = $file_name;
                }
            }

            $answer = FarmerQuestionAnswer::create($data);

            return $this->success(
                $this->formatAnswer($answer),
                'Answer posted successfully. It will be visible once approved by admin.'
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Like a question
     */
    public function likeQuestion(Request $request, $id)
    {
        try {
            $question = FarmerQuestion::find($id);

            if (!$question) {
                return $this->error('Question not found', 404);
            }

            $question->incrementLikesCount();

            return $this->success([
                'likes_count' => $question->fresh()->likes_count
            ], 'Question liked successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Like an answer
     */
    public function likeAnswer(Request $request, $answerId)
    {
        try {
            $answer = FarmerQuestionAnswer::find($answerId);

            if (!$answer) {
                return $this->error('Answer not found', 404);
            }

            $answer->incrementLikesCount();

            return $this->success([
                'likes_count' => $answer->fresh()->likes_count
            ], 'Answer liked successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Format question data
     */
    private function formatQuestion($question, $includeFullContent = false)
    {
        return [
            'id' => $question->id,
            'title' => $question->title,
            'content' => $includeFullContent ? $question->content : \Illuminate\Support\Str::limit($question->content, 200),
            'author_name' => $question->author_name,
            'author_location' => $question->author_location,
            'status' => $question->status,
            'view_count' => $question->view_count,
            'likes_count' => $question->likes_count,
            'answers_count' => $question->answers_count,
            'has_image' => $question->has_image === 'Yes',
            'image_url' => $question->has_image === 'Yes' ? 'images/' . $question->image_url : null,
            'has_audio' => $question->has_audio === 'Yes',
            'audio_url' => $question->has_audio === 'Yes' ? 'images/' . $question->audio_url : null,
            'created_at' => $question->created_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Format answer data
     */
    private function formatAnswer($answer)
    {
        return [
            'id' => $answer->id,
            'content' => $answer->content,
            'author_name' => $answer->author_name,
            'author_location' => $answer->author_location,
            'likes_count' => $answer->likes_count,
            'is_accepted' => $answer->is_accepted === 'Yes',
            'has_image' => $answer->has_image === 'Yes',
            'image_url' => $answer->has_image === 'Yes' ? 'images/' . $answer->image_url : null,
            'has_audio' => $answer->has_audio === 'Yes',
            'audio_url' => $answer->has_audio === 'Yes' ? 'images/' . $answer->audio_url : null,
            'has_video' => $answer->has_video === 'Yes',
            'video_url' => $answer->has_video === 'Yes' ? 'images/' . $answer->video_url : null,
            'has_youtube_video' => $answer->has_youtube_video === 'Yes',
            'youtube_video_url' => $answer->has_youtube_video === 'Yes' ? $answer->youtube_video_url : null,
            'has_pdf' => $answer->has_pdf === 'Yes',
            'pdf_url' => $answer->has_pdf === 'Yes' ? 'images/' . $answer->pdf_url : null,
            'created_at' => $answer->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
