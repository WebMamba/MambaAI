<?php

declare(strict_types=1);

namespace MambaAi\Version_2\Controller;

use MambaAi\Version_2\Event\ControllerEvent;
use MambaAi\Version_2\Message\MambaAIMessage;
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
    ) {}

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
