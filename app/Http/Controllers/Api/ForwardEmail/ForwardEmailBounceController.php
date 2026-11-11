<?php

namespace App\Http\Controllers\Api\ForwardEmail;

use App\Actions\ProcessMailBounceAction;
use App\Http\Controllers\Controller;
use App\Models\OutboundMail;
use App\Support\BounceReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForwardEmailBounceController extends Controller
{
    public function __invoke(Request $request, ProcessMailBounceAction $process): JsonResponse
    {
        $recipient = (string) $request->input('recipient', '');

        if ($recipient === '') {
            return response()->json(['error' => 'Missing recipient.'], Response::HTTP_BAD_REQUEST);
        }

        $headers = $request->input('headers', []);
        $messageId = null;

        if (is_array($headers)) {
            $messageId = $headers['Message-ID']
                ?? $headers['Message-Id']
                ?? $headers['message-id']
                ?? null;
        }

        $category = data_get($request->all(), 'bounce.category');
        $action = data_get($request->all(), 'bounce.action');
        $bounceMessage = $request->input('message')
            ?? data_get($request->all(), 'bounce.message');

        $report = BounceReport::fromNormalized(
            recipient: $recipient,
            messageId: OutboundMail::normalizeMessageId(is_string($messageId) ? $messageId : null),
            category: is_string($category) ? $category : 'unknown',
            action: is_string($action) ? $action : null,
            message: is_string($bounceMessage) ? $bounceMessage : null,
            provider: 'forwardemail',
            providerMessageId: filled($request->input('email_id')) ? (string) $request->input('email_id') : null,
            bouncedAt: $request->input('bounced_at'),
        );

        $process->handle($report);

        return response()->json('ok', Response::HTTP_OK);
    }
}
