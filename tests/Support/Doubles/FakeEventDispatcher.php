<?php

declare(strict_types=1);

namespace MambaAi\Tests\Support\Doubles;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Critique : `PromptBuilder` lit `$event->messages` / `$event->options`
 * juste après le `dispatch()`. Le dispatcher DOIT renvoyer la même
 * instance d'événement, pour qu'une modification par un listener (ex:
 * `$event->messages = ...`) soit visible côté appelant.
 */
final class FakeEventDispatcher implements EventDispatcherInterface
{
    /** @var array<class-string, array<callable>> */
    public array $listeners = [];

    /** @var object[] */
    public array $dispatched = [];

    public function dispatch(object $event): object
    {
        $this->dispatched[] = $event;
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            $listener($event);
        }

        return $event;
    }

    /**
     * @param class-string $eventClass
     */
    public function on(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }
}
