<?php

namespace App\Http\Controllers\Api\ForwardEmail;

use App\Actions\AcceptInboundMailAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class ForwardEmailInboundController extends Controller
{
    public function __invoke(Request $request, AcceptInboundMailAction $accept): JsonResponse
    {
        try {
            $accept->handle($request);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json('ok', Response::HTTP_OK);
    }
}
