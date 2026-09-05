<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * Public "Contact us" form submission. Every message lands in the admin
 * inbox as unread and raises a bell notification so nothing sent by a
 * customer goes unnoticed.
 */
class ContactMessageController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:40',
            'message' => 'required|string|max:5000',
        ]);

        $contactMessage = ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'],
            'is_read' => false,
            'ip_address' => $request->ip(),
        ]);

        NotificationService::newContactMessage($contactMessage);

        return response()->json([
            'id' => $contactMessage->id,
            'message' => 'Thank you — your message has been received. We will get back to you soon.',
        ], 201);
    }
}
