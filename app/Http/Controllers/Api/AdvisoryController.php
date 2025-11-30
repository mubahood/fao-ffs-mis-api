<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdvisoryCategory;
use App\Models\AdvisoryPost;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdvisoryController extends Controller
{
    use ApiResponser;

    /**
     * Get all active advisory categories
     */
    public function getCategories(Request $request)
    {
        try {
            $categories = AdvisoryCategory::active()
                ->ordered()
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'description' => $category->description,
                        'image' => $category->image,
                        'icon' => $category->icon,
                        'order' => $category->order,
                        'posts_count' => $category->published_posts_count,
                    ];
                });

            return $this->success($categories, 'Categories retrieved successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get posts by category
     */
    public function getPostsByCategory(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category_id' => 'required|exists:advisory_categories,id',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first());
            }

            $perPage = $request->input('per_page', 20);
            
            $posts = AdvisoryPost::published()
                ->where('category_id', $request->category_id)
                ->orderBy('published_at', 'desc')
                ->paginate($perPage);

            $data = [
                'posts' => $posts->map(function ($post) {
                    return $this->formatPost($post);
                }),
                'pagination' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                    'has_more' => $posts->hasMorePages(),
                ],
            ];

            return $this->success($data, 'Posts retrieved successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get all published posts with optional filters
     */
    public function getPosts(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 20);
            $query = AdvisoryPost::published();

            // Filter by category
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Filter by language
            if ($request->filled('language')) {
                $query->where('language', $request->language);
            }

            // Search
            if ($request->filled('search')) {
                $query->search($request->search);
            }

            // Filter featured posts
            if ($request->filled('featured') && $request->featured == 'yes') {
                $query->featured();
            }

            $posts = $query->orderBy('published_at', 'desc')->paginate($perPage);

            $data = [
                'posts' => $posts->map(function ($post) {
                    return $this->formatPost($post);
                }),
                'pagination' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                    'has_more' => $posts->hasMorePages(),
                ],
            ];

            return $this->success($data, 'Posts retrieved successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get featured posts
     */
    public function getFeaturedPosts(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            
            $posts = AdvisoryPost::published()
                ->featured()
                ->orderBy('published_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($post) {
                    return $this->formatPost($post);
                });

            return $this->success($posts, 'Featured posts retrieved successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get single post details
     */
    public function getPost(Request $request, $id)
    {
        try {
            $post = AdvisoryPost::published()->find($id);

            if (!$post) {
                return $this->error('Post not found', 404);
            }

            // Increment view count
            $post->incrementViewCount();

            $data = $this->formatPost($post, true);

            return $this->success($data, 'Post retrieved successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Like a post
     */
    public function likePost(Request $request, $id)
    {
        try {
            $post = AdvisoryPost::published()->find($id);

            if (!$post) {
                return $this->error('Post not found', 404);
            }

            $post->incrementLikesCount();

            return $this->success([
                'likes_count' => $post->fresh()->likes_count
            ], 'Post liked successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Search posts
     */
    public function searchPosts(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:2',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first());
            }

            $perPage = $request->input('per_page', 20);
            
            $posts = AdvisoryPost::published()
                ->search($request->query)
                ->orderBy('published_at', 'desc')
                ->paginate($perPage);

            $data = [
                'posts' => $posts->map(function ($post) {
                    return $this->formatPost($post);
                }),
                'pagination' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                    'has_more' => $posts->hasMorePages(),
                ],
            ];

            return $this->success($data, 'Search results retrieved successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Format post data for API response
     */
    private function formatPost($post, $includeFullContent = false)
    {
        $data = [
            'id' => $post->id,
            'title' => $post->title,
            'content' => $includeFullContent ? $post->content : \Illuminate\Support\Str::limit($post->content, 200),
            'image' => $post->image,
            'category_id' => $post->category_id,
            'category_name' => $post->category ? $post->category->name : null,
            'author_name' => $post->author_name,
            'published_at' => $post->published_at ? $post->published_at->format('Y-m-d H:i:s') : null,
            'view_count' => $post->view_count,
            'likes_count' => $post->likes_count,
            'language' => $post->language,
            'tags' => $post->tags_array,
            'featured' => $post->featured === 'Yes',
            'has_video' => $post->has_video === 'Yes',
            'video_url' => $post->has_video === 'Yes' ? $post->video_url : null,
            'has_audio' => $post->has_audio === 'Yes',
            'audio_url' => $post->has_audio === 'Yes' ? $post->audio_url : null,
            'has_youtube_video' => $post->has_youtube_video === 'Yes',
            'youtube_video_url' => $post->has_youtube_video === 'Yes' ? $post->youtube_video_url : null,
            'has_pdf' => $post->has_pdf === 'Yes',
            'pdf_url' => $post->has_pdf === 'Yes' ? $post->pdf_url : null,
        ];

        return $data;
    }
}
