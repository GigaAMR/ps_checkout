<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace PrestaShop\Module\PrestashopCheckout\CommandBus;

use League\Tactician\CommandBus;
use League\Tactician\Handler\CommandHandlerMiddleware;
use League\Tactician\Handler\CommandNameExtractor\ClassNameExtractor;
use League\Tactician\Handler\MethodNameInflector\HandleInflector;
use League\Tactician\Logger\Formatter\ClassPropertiesFormatter;
use League\Tactician\Logger\LoggerMiddleware;
use Ps_checkout;
use Psr\Log\LoggerInterface;

class TacticianCommandBusFactory
{
    /**
     * @var Ps_checkout
     */
    private $module;

    /**
     * @var array
     */
    private $commandToHandlerMap;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Ps_checkout $module
     * @param LoggerInterface $logger
     * @param array $commandToHandlerMap
     */
    public function __construct(Ps_checkout $module, LoggerInterface $logger, array $commandToHandlerMap)
    {
        $this->module = $module;
        $this->logger = $logger;
        $this->commandToHandlerMap = $commandToHandlerMap;
    }

    /**
     * @return CommandBus
     */
    public function create()
    {
        $commandHandlerMiddleware = new CommandHandlerMiddleware(
            new ClassNameExtractor(),
            new TacticianContainerLocator(
                $this->module,
                $this->commandToHandlerMap
            ),
            new HandleInflector()
        );

        return new CommandBus([
            $commandHandlerMiddleware,
            new LoggerMiddleware(
                new ClassPropertiesFormatter(),
                $this->logger
            ),
        ]);
    }
}
