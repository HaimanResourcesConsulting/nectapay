<?php

namespace HRC\NectaPay\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class WebhookEarlyExitException extends RuntimeException
{
    public function __construct(private JsonResponse $response)
    {
        parent::__construct('Webhook early exit');
    }

    public function getResponse(): JsonResponse
    {
        return $this->response;
    }
}
