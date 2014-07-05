<?php
/*
 * This file is part of the prooph/php-service-bus.
 * (c) Alexander Miertsch <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Date: 14.03.14 - 22:25
 */

namespace Prooph\ServiceBus\Initializer;

use Codeliner\ArrayReader\ArrayReader;
use Prooph\ServiceBus\LifeCycleEvent\InitializeEvent;
use Prooph\ServiceBus\Service\Definition;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;

/**
 * Class LocalSynchronousInitializer
 *
 * @package Prooph\ServiceBus\Initializer
 * @author Alexander Miertsch <contact@prooph.de>
 */
class LocalSynchronousInitializer implements ListenerAggregateInterface
{
    /**
     * @var array
     */
    protected $listeners = array();

    /**
     * @var array
     */
    protected $commandHandlers = array();

    /**
     * @var array
     */
    protected $eventHandlers = array();

    /**
     * @param mixed|string $aCommand
     * @param mixed $aCommandHandler
     */
    public function setCommandHandler($aCommand, $aCommandHandler)
    {
        if (is_object($aCommand)) {
            $aCommand = get_class($aCommand);
        }

        \Assert\that($aCommand)->notEmpty()->string();

        $this->commandHandlers[$aCommand] = $aCommandHandler;
    }

    /**
     * @param mixed|string $anEvent
     * @param mixed $anEventHandler
     */
    public function addEventHandler($anEvent, $anEventHandler)
    {
        if (is_object($anEvent)) {
            $anEvent = get_class($anEvent);
        }

        \Assert\that($anEvent)->notEmpty()->string();

        if (! isset($this->eventHandlers[$anEvent])) {
            $this->eventHandlers[$anEvent] = array();
        }

        $this->eventHandlers[$anEvent][] = $anEventHandler;
    }

    /**
     * Attach one or more listeners
     *
     * Implementors may add an optional $priority argument; the EventManager
     * implementation will pass this to the aggregate.
     *
     * @param EventManagerInterface $events
     *
     * @return void
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(InitializeEvent::NAME, array($this, 'initializeLocalServiceBus'));
    }

    /**
     * Detach all previously attached listeners
     *
     * @param EventManagerInterface $events
     *
     * @return void
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    /**
     * @param InitializeEvent $e
     */
    public function initializeLocalServiceBus(InitializeEvent $e)
    {
        $serviceBusManager = $e->getServiceBusManager();

        $serviceBusManager->setAllowOverride(true);

        if ($serviceBusManager->has('configuration')) {
            $configuration = $serviceBusManager->get('configuration');
        } else {
            $configuration = array();
        }

        if (!isset($configuration[Definition::CONFIG_ROOT])) {
            $configuration[Definition::CONFIG_ROOT] = array();
        }

        if (!isset($configuration[Definition::CONFIG_ROOT][Definition::COMMAND_BUS])) {
            $configuration[Definition::CONFIG_ROOT][Definition::COMMAND_BUS] = array();
        }

        if (!isset($configuration[Definition::CONFIG_ROOT][Definition::EVENT_BUS])) {
            $configuration[Definition::CONFIG_ROOT][Definition::EVENT_BUS] = array();
        }

        $serviceBusManager->setDefaultCommandBus('local-command-bus');
        $serviceBusManager->setDefaultEventBus('local-event-bus');

        $configReader = new ArrayReader($configuration);

        $commandMap = $configReader->arrayValue(
            str_replace(".", "\.", Definition::CONFIG_ROOT) . '.'
            . Definition::COMMAND_BUS . '.'
            . 'local-command-bus' . '.'
            . Definition::COMMAND_MAP
        );

        foreach ($this->commandHandlers as $commandName => $commandHandler) {
            $serviceBusManager->setService($commandName . '_local_handler', $commandHandler);
            $commandMap[$commandName] = $commandName . '_local_handler';
        }

        $configuration[Definition::CONFIG_ROOT][Definition::COMMAND_BUS]['local-command-bus'] = array(
            Definition::COMMAND_MAP        => $commandMap,
            Definition::QUEUE              => 'local-queue',
            Definition::MESSAGE_DISPATCHER => 'in_memory_message_dispatcher'
        );

        $eventMap = $configReader->arrayValue(
            str_replace(".", "\.", Definition::CONFIG_ROOT) . '.'
            . Definition::EVENT_BUS . '.'
            . 'local-event-bus' . '.'
            . Definition::EVENT_MAP
        );

        foreach ($this->eventHandlers as $eventName => $handlersOfEvent) {

            if (! isset($eventMap[$eventName])) {
                $eventMap[$eventName] = array();
            }

            foreach ($handlersOfEvent as $handlerIndex => $eventHandler) {
                $serviceBusManager->setService($eventName . '_local_handler_' . $handlerIndex, $eventHandler);
                $eventMap[$eventName][] = $eventName . '_local_handler_' . $handlerIndex;
            }
        }

        $configuration[Definition::CONFIG_ROOT][Definition::EVENT_BUS]['local-event-bus'] = array(
            Definition::EVENT_MAP => $eventMap,
            Definition::QUEUE              => 'local-queue',
            Definition::MESSAGE_DISPATCHER => 'in_memory_message_dispatcher'
        );

        $serviceBusManager->setService('configuration', $configuration);

        /* @var $messageDispatcher \Prooph\ServiceBus\Message\InMemoryMessageDispatcher */
        $messageDispatcher = $serviceBusManager->get(Definition::MESSAGE_DISPATCHER_LOADER)->get('in_memory_message_dispatcher');

        $commandReceiverLoader = $serviceBusManager->get(Definition::COMMAND_RECEIVER_LOADER);

        $eventReceiverLoader   = $serviceBusManager->get(Definition::EVENT_RECEIVER_LOADER);

        $queue = $serviceBusManager->get(Definition::QUEUE_LOADER)->get('local-queue');

        $messageDispatcher->registerCommandReceiverLoaderForQueue($queue, $commandReceiverLoader);
        $messageDispatcher->registerEventReceiverLoaderForQueue($queue, $eventReceiverLoader);

        $serviceBusManager->setAllowOverride(false);
    }
}
 