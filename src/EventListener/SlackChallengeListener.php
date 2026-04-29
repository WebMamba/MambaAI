<?php

declare(strict_types=1);

namespace MambaAi\EventListener;

use MambaAi\Event\ControllerEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class SlackChallengeListener
{
    public function __construct(
        private string $signingSecret,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->headers->has('X-Slack-Signature')) {
            return;
        }

        $timestamp = $request->headers->get('X-Slack-Request-Timestamp', '');
        $signature = $request->headers->get('X-Slack-Signature', '');

        if (abs(time() - (int) $timestamp) > 300) {
            $this->logger->warning('[Slack] Request rejected: timestamp too old', ['timestamp' => $timestamp]);
            $event->setResponse(new Response('Request too old.', Response::HTTP_UNAUTHORIZED));

            return;
        }

        $baseString = 'v0:'.$timestamp.':'.$request->getContent();
        $expected = 'v0='.hash_hmac('sha256', $baseString, $this->signingSecret);

        if (!hash_equals($expected, $signature)) {
            $this->logger->error('[Slack] Request rejected: invalid signature');
            $event->setResponse(new Response('Invalid signature.', Response::HTTP_UNAUTHORIZED));

            return;
        }

        $payload = json_decode($request->getContent(), true);

        if (($payload['type'] ?? '') === 'url_verification') {
            $this->logger->info('[Slack] URL verification challenge');
            $event->setResponse(new JsonResponse(['challenge' => $payload['challenge'] ?? '']));

            return;
        }

        $slackEvent = $payload['event'] ?? [];

        $isBotMessage = isset($slackEvent['bot_id'])
            || ($slackEvent['subtype'] ?? '') === 'bot_message'
            || !isset($slackEvent['user']);

        if ($isBotMessage) {
            $this->logger->debug('[Slack] Ignoring bot/non-user message', [
                'has_bot_id' => isset($slackEvent['bot_id']),
                'subtype' => $slackEvent['subtype'] ?? null,
                'has_user' => isset($slackEvent['user']),
            ]);
            $event->setResponse(new Response('', Response::HTTP_OK));

            return;
        }

        $this->logger->info('[Slack] Event accepted, dispatching to Messenger', [
            'event_type' => $slackEvent['type'] ?? 'unknown',
            'channel' => $slackEvent['channel'] ?? '',
        ]);
    }
}
