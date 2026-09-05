<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

/**
 * Inbox for messages submitted through the storefront's "Contact" page.
 * Opening a message marks it read automatically (standard inbox UX); admins
 * can also flip it back to unread, or delete it outright.
 */
class ContactMessageController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'all');

        $query = ContactMessage::query();

        if ($status === 'unread') {
            $query->unread();
        } elseif ($status === 'read') {
            $query->read();
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $messages = $query->latest()->paginate(20)->withQueryString();

        // One aggregate query instead of three separate COUNT(*) round trips
        // for the status tabs.
        $tally = ContactMessage::selectRaw('COUNT(*) as all_count, SUM(is_read = 0) as unread_count, SUM(is_read = 1) as read_count')->first();
        $counts = [
            'unread' => (int) $tally->unread_count,
            'read' => (int) $tally->read_count,
            'all' => (int) $tally->all_count,
        ];

        return view('admin.contact-messages.index', compact('messages', 'counts', 'status'));
    }

    public function show(string $id)
    {
        $message = ContactMessage::findOrFail($id);

        if (! $message->is_read) {
            $message->update(['is_read' => true]);
        }

        return view('admin.contact-messages.show', compact('message'));
    }

    public function toggleRead(string $id)
    {
        $message = ContactMessage::findOrFail($id);
        $message->update(['is_read' => ! $message->is_read]);

        return redirect()->back()->with(
            'success',
            $message->is_read ? 'Marked as read.' : 'Marked as unread.'
        );
    }

    public function destroy(string $id)
    {
        ContactMessage::findOrFail($id)->delete();

        return redirect()->route('admin.contact-messages.index')->with('success', 'Message deleted.');
    }
}
