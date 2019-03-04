<?php

namespace App\Services\Util;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use ReflectionClass;
use Psr\Log\LogLevel;

class LogService
{
    private $logger;
    private $logName;

    const MAX_FILE_SIZE = 52428800;

    public function __construct()
    {
        $this->logName = date('Y-m-d') . '.log';
        $this->logger = new Logger('local');
    }

    public function getPath($group, $date)
    {
        return $group . '/' . $date . '.log';
    }

    public function getLevels()
    {
        return config('log.levels');
    }


    public function getGroups()
    {
        $groups = config('log.groups');
        foreach ($groups as $key => $group) {
            $groups[$key]['count'] = count(\Storage::disk('logs')->files($group['folder']));
        }
        return collect($groups);
    }

    public function getGroup($name)
    {
        return config('log.groups')[$name];
    }

    public function getLogs($group)
    {
        $paths = \Storage::disk('logs')->files($group['folder']);
        $logs = [];
        foreach ($paths as $path) {
            $logs[] = $this->getLog($path);
        }
        return collect($logs)->sortBy('timestamp')->reverse();
    }

    public function getLog($path)
    {
        $pathInfo = pathinfo($path);
        $meta = \Storage::disk('logs')->getMetaData($path);
        $log = array(
            'filename' => $pathInfo['filename'],
            'path' => storage_path('logs') . '/' . $meta['path'],
            'size' => $meta['size'],
            'timestamp' => $meta['timestamp'],
            'changed' => date('Y-m-d H:i:s', $meta['timestamp'])
        );
        return $log;
    }

    public function getRecords($path)
    {

        $file = \Storage::disk('logs');
        $file = $file->get($path);

        if (\Storage::disk('logs')->size($path) > self::MAX_FILE_SIZE) {
            return null;
        }
        $log = array();

        $log_levels = self::getLogLevels();

        $pattern = '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\].*/';


        preg_match_all($pattern, $file, $headings);

        if (!is_array($headings)) {
            return $log;
        }

        $log_data = preg_split($pattern, $file);

        if ($log_data[0] < 1) {
            array_shift($log_data);
        }
        $levels_config = config('log.levels');
        foreach ($headings as $h) {
            for ($i = 0, $j = count($h); $i < $j; $i++) {
                foreach ($log_levels as $level_key => $level_value) {
                    if (strpos(strtolower($h[$i]), '.' . $level_value)) {

                        preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*?\.' . $level_key . ': (.*?)( in .*?:[0-9]+)?$/',
                            $h[$i], $current);

                        if (!isset($current[2])) {
                            continue;
                        }
                        //if (str_replace('Symfony\Component\HttpKernel\Exception\NotFoundHttpException','',$current[2]) != $current[2]) continue;
                        $log[] = array(
                            'level' => $level_value,
                            'color' => $levels_config[$level_value]['color'],
                            'icon' => $levels_config[$level_value]['icon'],
                            'date' => $current[1],
                            'header' => $current[2],
                            'in_file' => isset($current[3]) ? $current[3] : null,
                            'stack' => preg_replace("/^\n*/", '', $log_data[$i])
                        );
                    }
                }
            }
        }
        return collect(array_reverse($log));
    }


    public function setGroup($name)
    {
        $groups = array_keys(config('log.groups'));
        if (!in_array($name, $groups)) {
            return $this;
        }
        $handles = [
            new StreamHandler(storage_path("/logs/$name/" . $this->logName))
        ];
        $this->logger->setHandlers($handles, Logger::INFO, false);
        return $this;
    }

    public function emergency($message, array $context = array())
    {
        $this->logger->emergency($message, $context);
    }

    public function alert($message, array $context = array())
    {
        $this->logger->alert($message, $context);
    }

    public function critical($message, array $context = array())
    {
        $this->logger->critical($message, $context);
    }

    public function error($message, array $context = array())
    {
        $this->logger->error($message, $context);
    }

    public function warning($message, array $context = array())
    {
        $this->logger->warning($message, $context);
    }

    public function notice($message, array $context = array())
    {
        $this->logger->notice($message, $context);
    }

    public function info($message, array $context = array())
    {
        $this->logger->info($message, $context);
    }

    public function debug($message, array $context = array())
    {
        $this->logger->debug($message, $context);
    }

    private static function getLogLevels()
    {
        $class = new ReflectionClass(new LogLevel);
        return $class->getConstants();
    }
}

?>
