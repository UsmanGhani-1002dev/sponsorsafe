<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Enquiries and training: website contact messages (and AI chat leads), new first. */
class EnquiryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Ops/Enquiries', [
            'base' => '/'.config('sponsorsafe.ops_path'),
            'enquiries' => Enquiry::with('handler')->orderByRaw("status = 'handled'")->latest()->limit(200)->get()->map(fn (Enquiry $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'email' => $e->email,
                'phone' => $e->phone,
                'topic' => $e->source === 'ai_chat' ? 'From AI chat' : $e->topic,
                'message' => $e->message,
                'date' => $e->created_at->format('j M Y, H:i'),
                'handled' => $e->status === Enquiry::HANDLED ? 'Handled '.$e->handled_at?->format('j M Y').' by '.($e->handler?->name ?? '—') : null,
            ]),
        ]);
    }

    public function handle(int $enquiry): RedirectResponse
    {
        $e = Enquiry::findOrFail($enquiry);
        if ($e->status !== Enquiry::HANDLED) {
            $e->update(['status' => Enquiry::HANDLED, 'handled_by' => auth('ops')->id(), 'handled_at' => now()]);
            Audit::log('ops.enquiry_handled', $e);
        }

        return back()->with('success', "{$e->name}'s enquiry marked as handled.");
    }
}
