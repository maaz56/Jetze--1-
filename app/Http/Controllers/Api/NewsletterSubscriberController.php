<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;

class NewsletterSubscriberController extends Controller
{
    // GET /api/subscribers
    public function index(Request $request)
    {
        $query = NewsletterSubscriber::query()->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            });
        }

        $subscribers = $query->paginate(15);

        return response()->json([
            'data' => $subscribers->items(),
            'meta' => [
                'current_page' => $subscribers->currentPage(),
                'last_page' => $subscribers->lastPage(),
                'per_page' => $subscribers->perPage(),
                'total' => $subscribers->total(),
                'from' => $subscribers->firstItem(),
                'to' => $subscribers->lastItem(),
            ],
        ]);
    }

    // POST /api/subscribers
    public function store(Request $request)
    {
        if ($request->filled('website')) {
            return response()->json([
                'message' => 'Subscriber saved successfully'
            ], 200);
        }

        $validated = $request->validate([
            'email' => 'required|email|unique:newsletter_subscribers,email',
            'name' => 'nullable|string|max:255',
            'website' => 'nullable|prohibited',
        ]);

        unset($validated['website']);

        $subscriber = NewsletterSubscriber::create($validated);
        return response()->json($subscriber, 201);
    }

    // GET /api/subscribers/{id}
    public function show($id)
    {
        $subscriber = NewsletterSubscriber::findOrFail($id);
        return response()->json($subscriber);
    }

    // PUT/PATCH /api/subscribers/{id}
    public function update(Request $request, $id)
    {
        $subscriber = NewsletterSubscriber::findOrFail($id);
        
        $validated = $request->validate([
            'email' => 'email|unique:newsletter_subscribers,email,' . $id,
            'name' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        $subscriber->update($validated);
        return response()->json($subscriber);
    }

    // DELETE /api/subscribers/{id}
    public function destroy($id)
    {
        $subscriber = NewsletterSubscriber::findOrFail($id);
        $subscriber->delete();
        return response()->json(null, 204);
    }
}
