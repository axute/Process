<?php

namespace Axute\Process {


    class Registry {

        public static $processes = array();

        protected static function cleanup(array $pids_unset = array()): void {
            foreach (self::$processes as $pid => $process) {
                if (($process instanceof Process) === false && !\in_array($pid, $pids_unset, true)) {
                    $pids_unset[] = $pid;
                }
            }
            foreach ($pids_unset as $pid) {
                unset(self::$processes[$pid]);
            }
        }

        public static function getPids(): array {
            self::cleanup();

            return array_keys(self::$processes);
        }

        public static function add(Process $process): Process {
            self::$processes[$process->getPid()] = $process;

            return $process;
        }

        public static function registerShutdown(): void {
            register_shutdown_function([__CLASS__, 'shutdownAll']);
        }

        public static function shutdownAll(): bool {
            $pids_unset = array();
            foreach (self::$processes as $pid => $process) {
                if ($process instanceof Process) {
                    try {
                        if ($process->close(9)) {
                            $pids_unset[] = $pid;
                        }
                    }
                    catch (\Exception $e) {
                    }
                }
            }
            self::cleanup($pids_unset);

            return (\count(self::$processes) === 0);
        }
    }
}