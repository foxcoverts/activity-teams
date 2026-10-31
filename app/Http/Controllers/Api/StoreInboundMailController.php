<?php

namespace App\Http\Controllers\Api;

use App\Actions\ProcessInboundMailAction;
use App\Http\Controllers\Controller;
use App\Models\InboundMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class StoreInboundMailController extends Controller
{
    public function __invoke(Request $request, ProcessInboundMailAction $process): JsonResponse
    {
        try {
            $mail = new InboundMail([
                'body' => $request->getContent(),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $summary = InboundMail::summarizeFromPayload($mail->payload);
        $mail->fill($summary);

        try {
            $mail->calendar_method = $mail->calendar()?->method();
        } catch (Throwable) {
            // Parsing failures are recorded by the processor as Failed.
        }

        $mail->save();

        $process($mail->fresh());

        return response()->json('ok', Response::HTTP_OK);
    }
}
