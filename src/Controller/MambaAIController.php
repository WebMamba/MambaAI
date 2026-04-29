<?php

declare(strict_types=1);

namespace MambaAi\Controller;

use MambaAi\Event\ControllerEvent;
use MambaAi\Message\MambaAIMessage;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class MambaAIController
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/mamba-ai')]
    public function index(Request $request): Response
    {
        $event = $this->eventDispatcher->dispatch(new ControllerEvent($request));
        $response = $event->getResponse();

        if ($response) {
            return $response;
        }

        $this->messageBus->dispatch(new MambaAIMessage($request));

        return new Response(null, Response::HTTP_OK);
    }
}
