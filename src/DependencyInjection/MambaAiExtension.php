<?php

namespace MambaAi\Version_2\DependencyInjection;

use MambaAi\Version_2\AgentBuilderInterface;
use MambaAi\Version_2\AgentLoader;
use MambaAi\Version_2\AgentLoaderInterface;
use MambaAi\Version_2\AgentResolver;
use MambaAi\Version_2\AgentResolverInterface;
use MambaAi\Version_2\Channel\ChannelResolver;
use MambaAi\Version_2\Channel\ChannelResolverInterface;
use MambaAi\Version_2\Channel\SlackChannel;
use MambaAi\Version_2\Event\ControllerEvent;
use MambaAi\Version_2\EventListener\SlackChallengeListener;
use MambaAi\Version_2\FolderAgentBuilder;
use MambaAi\Version_2\StreamMapperInterface;
use MambaAi\Version_2\Prompt\SystemPromptPartInterface;
use MambaAi\Version_2\Prompt\UserPromptPartInterface;
use MambaAi\Version_2\PromptBuilder;
use MambaAi\Version_2\PromptBuilderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class MambaAiExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('mamba_ai.agents_dir', $config['agents_dir']);
        $container->setParameter('mamba_ai.default_platform', $config['default_platform']);
        $container->setParameter('mamba_ai.default_model', $config['default_model']);

        $definition = new Definition(FolderAgentBuilder::class, [
            '$platform' => new Reference($config['default_platform']),
            '$defaultModel' => $config['default_model'],
        ]);
        $container->setDefinition(FolderAgentBuilder::class, $definition);
        $container->setAlias(AgentBuilderInterface::class, FolderAgentBuilder::class)->setPublic(true);
        $container->setAlias(AgentLoaderInterface::class, AgentLoader::class)->setPublic(true);
        $container->setAlias(AgentResolverInterface::class, AgentResolver::class)->setPublic(true);
        $container->setAlias(PromptBuilderInterface::class, PromptBuilder::class)->setPublic(true);
        $container->setAlias(ChannelResolverInterface::class, ChannelResolver::class)->setPublic(true);

        $container->registerForAutoconfiguration(SystemPromptPartInterface::class)
            ->addTag('mamba_ai.system_prompt_part');
        $container->registerForAutoconfiguration(UserPromptPartInterface::class)
            ->addTag('mamba_ai.user_prompt_part');

        $slack = $config['slack'] ?? [];
        if (!empty($slack['bot_token'])) {
            $slackDef = new Definition(SlackChannel::class, [
                '$botToken' => $slack['bot_token'],
                '$httpClient' => new Reference('http_client'),
                '$logger' => new Reference('logger'),
            ]);
            $slackDef->addTag('mamba_ai.channel');
            $container->setDefinition(SlackChannel::class, $slackDef);

            $listenerDef = new Definition(SlackChallengeListener::class, [
                '$signingSecret' => $slack['signing_secret'],
                '$logger' => new Reference('logger'),
            ]);
            $listenerDef->addTag('kernel.event_listener', [
                'event' => ControllerEvent::class,
            ]);
            $container->setDefinition(SlackChallengeListener::class, $listenerDef);
        }
    }
}
