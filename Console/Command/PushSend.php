<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */

namespace Buckaroo\Magento2\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * DEPRECATED: This command was removed in v2.0.x
 * 
 * This class exists only for backwards compatibility during upgrades from v1.5x.
 * When upgrading from v1.5x to v2.0x, generated Proxy classes may still reference this command.
 * After running setup:di:compile, this stub will be properly removed from the command list.
 * 
 * @deprecated since 2.0.0
 */
class PushSend extends Command
{
    /**
     * Configure the command (hidden from command list)
     */
    protected function configure()
    {
        $this->setName('buckaroo:push:send')
            ->setDescription('[DEPRECATED] This command has been removed in v2.0.x')
            ->setHidden(true); // Hide from command list
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<error>This command has been removed in Buckaroo v2.0.x</error>');
        $output->writeln('<info>It exists only for upgrade compatibility and will be removed in a future version.</info>');
        
        return Command::FAILURE;
    }
}

