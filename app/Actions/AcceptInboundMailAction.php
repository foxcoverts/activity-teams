<?php

namespace App\Actions;

use App\Models\InboundMail;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class AcceptInboundMailAction
{
    public function __construct(private ProcessInboundMailAction $process) {}

    /**
     * @throws InvalidArgumentException
     */
    public function handle(Request $request): InboundMail
    {
        $mail = new InboundMail([
            'body' => $request->getContent(),
        ]);

        $summary = InboundMail::summarizeFromPayload($mail->payload);
        $mail->fill($summary);

        try {
            $mail->calendar_method = $mail->calendar()?->method();
        } catch (Throwable) {
            // Parsing failures are recorded by the processor as Failed.
        }

        $mail->save();

        return ($this->process)($mail->fresh());
    }
}
