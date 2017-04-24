<?php
/**
 * Created by PhpStorm.
 * Kerisy Framework
 *
 * PHP Version 7
 *
 * @author          kaihui.wang <hpuwang@gmail.com>
 * @copyright      (c) 2015 putao.com, Inc.
 * @package         kerisy/framework
 * @version         3.0.0
 */

namespace Kerisy\Foundation\Command\Httpd;

use Kerisy\Console\Input\InputInterface;
use Kerisy\Console\Input\InputOption;
use Kerisy\Console\Output\OutputInterface;
use Kerisy\Foundation\Command\Base;

class Restart extends Base
{
    protected function configure()
    {
        $this
            ->setName('httpd:restart')
            ->setDescription('restart the http server');
        $this->addOption('--daemonize', '-d', InputOption::VALUE_NONE, 'Is daemonize ?');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        HttpdBase::operate("restart", $output, $input);
    }
}