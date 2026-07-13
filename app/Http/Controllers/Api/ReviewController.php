<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    /**
     * Get all approved reviews for public display
     */
    public function approved(): JsonResponse
    {
        $reviews = Review::where('is_approved', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($reviews);
    }

    /**
     * Submit a new review (public)
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('New review submission request received', $request->all());

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'rating' => 'required|integer|min:1|max:5',
            'message' => 'required|string|min:10|max:1000',
        ], [
            'name.required' => 'Please enter your name.',
            'rating.required' => 'Please give a rating.',
            'rating.min' => 'Rating must be at least 1 star.',
            'rating.max' => 'Rating must be at most 5 stars.',
            'message.required' => 'Please enter your review message.',
            'message.min' => 'Your review must be at least 10 characters long.',
        ]);

        $review = new Review();
        $review->name = $request->name;
        $review->email = $request->email;
        $review->rating = $request->rating;
        $review->message = $request->message;
        $review->is_approved = false; // Requires admin approval
        $review->save();

        return response()->json([
            'message' => 'Thank you! Your review has been submitted for admin approval.',
            'data' => $review
        ], 201);
    }

    /**
     * List all reviews for admin panel (approved & pending)
     */
    public function index(Request $request): JsonResponse
    {
        Log::info('Admin fetching all reviews', $request->all());
        
        $query = Review::query()->latest();

        if ($request->has('search') && !empty($request->search)) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('message', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('status') && !empty($request->status)) {
            if ($request->status === 'approved') {
                $query->where('is_approved', true);
            } elseif ($request->status === 'pending') {
                $query->where('is_approved', false);
            }
        }

        $reviews = $query->paginate(15);

        return response()->json([
            'data' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'from' => $reviews->firstItem(),
                'to' => $reviews->lastItem(),
            ]
        ]);
    }

    /**
     * Approve or toggle approval status of a review (admin)
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        $request->validate([
            'is_approved' => 'required|boolean'
        ]);

        $review->is_approved = $request->is_approved;
        $review->approved_at = $request->is_approved ? now() : null;
        $review->save();

        return response()->json([
            'message' => $review->is_approved ? 'Review approved successfully' : 'Review un-approved successfully',
            'data' => $review
        ]);
    }

    /**
     * Delete a review (admin)
     */
    public function delete($id): JsonResponse
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        $review->delete();

        return response()->json(['message' => 'Review deleted successfully']);
    }
}
