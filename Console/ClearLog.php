<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category  Mageplaza
 * @package   Mageplaza_DeleteOrders
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Console;

use Exception;
use Mageplaza\Smtp\Helper\Data as HelperData;
use Mageplaza\Smtp\Helper\ClearLog as HelperClearLog;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Delete
 *
 * @package Mageplaza\DeleteOrders\Console
 */
class ClearLog extends Command
{
    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * @var HelperClearLog
     */
    protected $_helperClearLog;


    /**
     * Delete constructor.
     *
     * @param HelperData $helperData
     * @param HelperClearLog $helperClearLog
     * @param null $name
     */
    public function __construct(
        HelperData $helperData,
        HelperClearLog $helperClearLog,
        $name = null
    ) {
        $this->_helperData     = $helperData;
        $this->_helperClearLog = $helperClearLog;

        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('smtp:clearlog')
            ->setDescription('Clear MagePlaza Smtp Log');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_helperClearLog->execute();
    }
}
