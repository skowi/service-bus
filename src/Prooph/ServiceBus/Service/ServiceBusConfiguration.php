<?php
/*
 * This file is part of the prooph/php-service-bus.
 * (c) Alexander Miertsch <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Date: 09.03.14 - 22:44
 */

namespace Prooph\ServiceBus\Service;

use Codeliner\ArrayReader\ArrayReader;
use Prooph\ServiceBus\Command\CommandFactoryInterface;
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ConfigInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManager;

class ServiceBusConfiguration implements ConfigInterface
{
    /**
     * @var array
     */
    protected $configuration = array(
        Definition::CONFIG_ROOT => array(
            Definition::COMMAND_BUS => array(),
            Definition::EVENT_BUS => array(),
        )
    );

    /**
     * @var array
     */
    protected $services = array();

    /**
     * @var array
     */
    protected $eventHandlers   = array();

    /**
     * @param null|array $aConfiguration
     */
    public function __construct(array $aConfiguration = null)
    {
        if (is_array($aConfiguration)) {
            $this->setConfiguration($aConfiguration);
        }
    }

    /**
     * Configure service manager
     *
     * @param ServiceManager $serviceManager
     * @return void
     */
    public function configureServiceManager(ServiceManager $serviceManager)
    {
        $serviceManager->setService('configuration', $this->configuration);

        $serviceManager->setAllowOverride(true);

        if (count($this->services)) {
            foreach ($this->services as $alias => $service) {
                $serviceManager->setService($alias, $service);
            }
        }

        $serviceBusManagerServicesConfig = new Config($this->configuration[Definition::CONFIG_ROOT]);

        $serviceBusManagerServicesConfig->configureServiceManager($serviceManager);

        if (isset($this->configuration[Definition::CONFIG_ROOT][Definition::DEFAULT_COMMAND_BUS])) {
            $serviceManager->setDefaultCommandBus(
                $this->configuration[Definition::CONFIG_ROOT][Definition::DEFAULT_COMMAND_BUS]
            );
        }

        if (isset($this->configuration[Definition::CONFIG_ROOT][Definition::DEFAULT_EVENT_BUS])) {
            $serviceManager->setDefaultEventBus(
                $this->configuration[Definition::CONFIG_ROOT][Definition::DEFAULT_EVENT_BUS]
            );
        }

        $serviceManager->setAllowOverride(false);
    }

    /**
     * @param array $configuration
     */
    public function setConfiguration(array $configuration)
    {
        if (! array_key_exists(Definition::CONFIG_ROOT, $configuration)) {
            $configuration = array(Definition::CONFIG_ROOT => $configuration);
        }

        $this->configuration = $configuration;
    }

    /**
     * @param array $configuration
     */
    public function setCommandBusConfiguration(array $configuration)
    {
        $this->configuration[Definition::CONFIG_ROOT][Definition::COMMAND_BUS] = $configuration;
    }

    /**
     * @param array $invokeStrategies
     */
    public function setCommandHandlerInvokeStrategies(array $invokeStrategies)
    {
        $this->configuration[Definition::CONFIG_ROOT][Definition::COMMAND_HANDLER_INVOKE_STRATEGIES] = $invokeStrategies;
    }

    /**
     * @param $commandBus
     * @param array $commandMap
     */
    public function setCommandMap($commandBus, array $commandMap)
    {
        $this->configuration[Definition::CONFIG_ROOT]
            [Definition::COMMAND_BUS]
            [$commandBus]
            [Definition::COMMAND_MAP] = $commandMap;
    }

    /**
     * @param mixed      $aliasOrCommandHandler
     * @param null|mixed $commandHandler
     */
    public function addCommandHandler($aliasOrCommandHandler, $commandHandler = null)
    {
        if (is_null($commandHandler)) {
            $commandHandler = $aliasOrCommandHandler;
            $aliasOrCommandHandler = get_class($commandHandler);
        }

        $this->services[$aliasOrCommandHandler] = $commandHandler;
    }

    /**
     * @param mixed      $aliasOrEventHandler
     * @param null|mixed $eventHandler
     */
    public function addEventHandler($aliasOrEventHandler, $eventHandler = null)
    {
        if (is_null($eventHandler)) {
            $eventHandler = $aliasOrEventHandler;
            $aliasOrEventHandler = get_class($eventHandler);
        }

        $this->services[$aliasOrEventHandler] = $eventHandler;
    }

    /**
     * @param CommandFactoryInterface $commandFactory
     */
    public function setCommandFactory(CommandFactoryInterface $commandFactory)
    {
        $this->commandFactory = $commandFactory;
    }
}
 