<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebsiteConnection;
use App\Services\LeadIntakeService;
use App\Support\MobileNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * POST /api/v1/leads — a website's server sends one sign-up.
 * The reply never says whether the student already existed.
 */
class LeadController extends Controller
{
    public function __invoke(Request $request, LeadIntakeService $intake): JsonResponse
    {
        /** @var WebsiteConnection $conn */
        $conn = $request->attributes->get('website_connection');

        $v = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'mobile' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'course' => ['nullable', 'string', 'max:120'],
            'exam' => ['nullable', 'string', 'max:120'],
            'city_state' => ['nullable', 'string', 'max:120'],
            'parent_mobile' => ['nullable', 'string', 'max:20'],
            'source_page' => ['nullable', 'string', 'max:255'],
            'consent' => ['accepted'],
            'hp' => ['nullable', 'max:0'], // hidden spam trap: real people leave it empty
        ], [
            'consent.accepted' => 'The consent tick box must be ticked.',
        ]);

        if ($v->errors()->has('hp')) {
            // Pretend success so spam bots learn nothing; store nothing.
            return response()->json(['status' => 'received'], 202);
        }
        $v->after(function ($v) use ($request) {
            if (! MobileNumber::normalize($request->input('mobile'))) {
                $v->errors()->add('mobile', 'Please enter a valid 10-digit mobile number.');
            }
            if ($request->filled('parent_mobile') && ! MobileNumber::normalize($request->input('parent_mobile'))) {
                $v->errors()->add('parent_mobile', 'Please enter a valid 10-digit parent mobile number.');
            }
        });
        $data = $v->validate();

        try {
            $intake->intake($data, $conn->source, ['channel' => 'website', 'website_connection_id' => $conn->id]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $conn->forceFill(['last_used_at' => now()])->saveQuietly();

        return response()->json(['status' => 'received'], 201);
    }
}
