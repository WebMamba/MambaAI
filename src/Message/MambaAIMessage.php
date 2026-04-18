<?php

namespace MambaAi\Version_2\Message;

use Symfony\Component\HttpFoundation\Request;

class MambaAIMessage
{
    public readonly string $content;
    public readonly array $headers;
    public readonly array $server;

    public function __construct(Request $request)
    {
        $this->content = $request->getContent();
        $this->headers = $request->headers->all();
        $this->server = $request->server->all();
    }

    public function toRequest(): Request
    {
        $request = Request::create(
            '/',
            'POST',
            server: $this->server,
            content: $this->content,
        );

        foreach ($this->headers as $key => $values) {
            $request->headers->set($key, $values);
        }

        return $request;
    }
}