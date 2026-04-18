<?php

namespace MambaAi\Version_2\EventListener;

use MambaAi\Version_2\Event\ControllerEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class SlackChallengeListener
{
    public function __construct(private string $signingSecret) {}

    public function __invoke(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->headers->has('X-Slack-Signature')) {
            return;
        }

        // Verify HMAC signature while headers are still available (before Messenger dispatch)
        $timestamp = $request->headers->get('X-Slack-Request-Timestamp', '');
        $signature = $request->headers->get('X-Slack-Signature', '');

        if (abs(time() - (int) $timestamp) > 300) {
            $event->setResponse(new Response('Request too old.', Response::HTTP_UNAUTHORIZED));
            return;
        }

        $baseString = 'v0:'.$timestamp.':'.$request->getContent();
        $expected = 'v0='.hash_hmac('sha256', $baseString, $this->signingSecret);

        if (!hash_equals($expected, $signature)) {
            $event->setResponse(new Response('Invalid signature.', Response::HTTP_UNAUTHORIZED));
            return;
        }

        $payload = json_decode($request->getContent(), true);

        if (($payload['type'] ?? '') === 'url_verification') {
            $event->setResponse(new JsonResponse(['challenge' => $payload['challenge'] ?? '']));
            return;
        }

        // Ignore messages sent by the bot itself to prevent infinite loops
        $slackEvent = $payload['event'] ?? [];
        if (isset($slackEvent['bot_id']) || ($slackEvent['subtype'] ?? '') === 'bot_message') {
            $event->setResponse(new Response('', Response::HTTP_OK));
            return;
        }
    }
}
