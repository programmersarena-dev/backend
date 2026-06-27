<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class EmailVerificationController extends Controller
{
    /**
     * Handle the email verification confirmation.
     * * Executed directly from the user's email client.
     * Uses 'signed' validation natively via EmailVerificationRequest.
     *
     * @param  \Illuminate\Foundation\Auth\EmailVerificationRequest  $request
     * @param  string|int  $id
     * @param  string  $hash
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verify(EmailVerificationRequest $request, $id, $hash): RedirectResponse
    {
        // Automatically checks signature cryptographic integrity and marks user as verified
        $request->fulfill();

        // Safely fetch target client base domain mapping to prevent production caching crashes
        $frontendUrl = rtrim(Config::get('app.frontend_url', env('REACT_APP_URL')), '/');

        // Dynamic query-parameter redirection to update the React frontend state variables
        return redirect()->away("{$frontendUrl}/email/verify?id={$id}&hash={$hash}&status=verified");
    }

    /**
     * Resend the verification notification email.
     * * Triggered safely via a click action inside the active React app dashboard.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resend(Request $request): JsonResponse
    {
        // Automatically read incoming dynamic Accept-Language request headers
        $locale = $request->header('Accept-Language', 'tk');

        // Prevent redundant email handling or mail server tracking queue overhead
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'info',
                'message' => __('messages.email_already_verified', [], $locale)
            ], 200);
        }

        // Dispatch notification directly to your background asynchronous queue infrastructure
        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'status' => 'success',
            'message' => __('messages.verification_sent', [], $locale)
        ], 200);
    }
}
