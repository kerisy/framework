<?php
/**
 * Kerisy Framework
 *
 * PHP Version 7
 *
 * @author          kaihui.wang <hpuwang@gmail.com>
 * @copyright      (c) 2015 putao.com, Inc.
 * @package         kerisy/framework
 * @version         3.0.0
 */

namespace Kerisy\Foundation\Command\Monitor;

use Kerisy\Config\Config;
use Kerisy\Monitor\MonitorSerialization;
use Kerisy\Monitor\MonitorServer;
use Kerisy\Support\Arr;
use Kerisy\Support\Dir;
use Kerisy\Support\ElapsedTime;
use Kerisy\Support\Log;

class MonitorBase
{

    public static function operate($cmd, $output, $input)
    {
        ElapsedTime::setStartTime(ElapsedTime::SYS_START);
        $root = Dir::formatPath(ROOT_PATH);
        $config = Config::get("server.monitor");
        $appName = Config::get("server.name");

        if (!$appName) {
            Log::sysinfo("server.name not config");
            exit(0);
        }

        if (!$config) {
            Log::sysinfo("monitor config not config");
            exit(0);
        }

        if (!isset($config['server'])) {
            Log::sysinfo("monitor.server config not config");
            exit(0);
        }

        if ($input->hasOption("daemonize")) {
            $daemonize = $input->getOption('daemonize');
            $config['server']['daemonize'] = $daemonize == 0 ? 0 : 1;
        }

        if (!isset($config['server']['host'])) {
            Log::sysinfo("monitor.server.host config not config");
            exit(0);
        }

        if (!isset($config['server']['port'])) {
            Log::sysinfo("monitor.server.port config not config");
            exit(0);
        }
        

        self::doOperate($cmd, $config, $root, $appName, $output);
    }


    public static function doOperate($command, array $config, $root, $appName, $output)
    {
        $defaultConfig = [
            'daemonize' => 0,
            //worker数量，推荐设置和cpu核数相等
            'worker_num' => 2,
            //reactor数量，推荐2
            'reactor_num' => 2,
            "dispatch_mode" => 2,
            'static_path' => $root . '/public',
            "gzip" => 4,
            "static_expire_time" => 86400,
            "task_worker_num" => 5,
            "task_fail_log" => "/tmp/task_fail_log",
            "task_retry_count" => 2,
            "serialization" => 1,
            "mem_reboot_rate" => 0,
            //以下配置直接复制，无需改动
            'open_length_check' => 1,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 4,
            'package_max_length' => 2000000,
        ];

        $config['server'] = Arr::merge($defaultConfig, $config['server']);

        if(isset($config['server']['log_file']) && !is_dir(dirname($config['server']['log_file']))){
            mkdir(dirname($config['server']['log_file']), "0777", true);
        }
        
        
        $serverName = $appName . "-monitor-master";
        exec("ps axu|grep " . $serverName . "$|awk '{print $2}'", $masterPidArr);
        $masterPid = $masterPidArr ? current($masterPidArr) : null;

        if ($command === 'start' && $masterPid) {
            Log::sysinfo("[$serverName] already running");
            return;
        }

        if ($command !== 'start' && $command !== 'restart' && !$masterPid) {
            Log::sysinfo("[$serverName] not run");
            return;
        }
        // execute command.
        switch ($command) {
            case 'status':
                if ($masterPid) {
                    Log::sysinfo("[$serverName] already running");
                } else {
                    Log::sysinfo("[$serverName] run");
                }
                break;
            case 'start':
                self::start($config, $appName);
                break;
            case 'stop':
                self::stop($appName);
                Log::sysinfo("[$serverName] stop success ");
                break;
            case 'restart':
                self::stop($appName);
                self::start($config, $appName);
                break;
            default :
                return "";
        }
    }


    protected static function stop($appName)
    {
        $killStr = $appName . "-monitor";
        exec("ps axu|grep " . $killStr . "|awk '{print $2}'|xargs kill -9", $masterPidArr);
    }

    protected static function start($config, $appName)
    {
        $swooleServer = new \swoole_server($config['server']['host'], $config['server']['port'], SWOOLE_PROCESS, SWOOLE_SOCK_UDP);
        $serialize = new MonitorSerialization($config['server']['serialization'], $config['server']['package_body_offset']);
        $performClass = Config::get("app.monitorReceive");
        if(!$performClass){
            Log::sysinfo("app.monitorReceive config not config");
            return ;
        }
        $obj = new MonitorServer($swooleServer, $serialize, $config, $performClass, $appName);
        $obj->start();
    }
}