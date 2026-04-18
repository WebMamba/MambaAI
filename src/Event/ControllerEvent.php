<?php

namespace MambaAi\Version_2\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ControllerEvent
{
    public function __construct(
        private Request $request,
        private ?Response $response = null
    ) {}

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }
}