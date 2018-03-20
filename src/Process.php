<?php

namespace Axute\Process {


    final class Process implements HandlerInterface {
        private const DEFAULT_ENCODING = 'de_DE.UTF-8';

        private const DESCRIPTORSPEC = [
            self::STD_IN  => [self::I_PIPE, 'r'], // stdin is a pipe that the child will read from
            self::STD_OUT => [self::I_PIPE, 'w'], // stdout is a pipe that the child will write to
            self::STD_ERR => [self::I_PIPE, 'w'] // stderr is a pipe that the child will write to
        ];

        private const I_EXITCODE = 'exitcode';

        private const I_PID = 'pid';

        private const I_PIPE = 'pipe';

        private const STD_ERR = 2;

        private const STD_IN = 0;

        private const STD_OUT = 1;

        private $arguments;

        private $encoding = self::DEFAULT_ENCODING;

        private $env = [];

        private $executable;

        /** @var int|null */
        private $exitcode;

        private $pid;

        /** @var resource[] */
        private $pipes = [];

        /** @var resource */
        private $process;

        /** @var  HandlerInterface */
        private $processHandler;

        private $stdIn;

        private $stderr = '';

        private $stdout = '';

        private $workingDirectory;

        private function __construct(string $executable, array $arguments, string $workingDirectory, ?HandlerInterface $processHandler = null) {
            $this->setWorkingDirectory($workingDirectory)
                 ->setProcessHandler($processHandler)
                 ->setExecutable($executable)
                 ->setArguments($arguments);
        }

        public function addEnvs(array $array): Process {
            foreach ($array as $k => $v) {
                $this->env[$k] = $v;
            }

            return $this;
        }

        public function close(?int $signal = null): bool {
            $this->exitcode = $this->getProcessInformation(self::I_EXITCODE);
            foreach ($this->pipes as $pipe) {
                if (\is_resource($pipe)) {
                    \fclose($pipe);
                }
            }
            if (\is_resource($this->process)) {
                return \proc_terminate($this->process, $signal);
            }

            return true;
        }

        public static function create(string $executable, array $arguments = [], string $workingDirectory, ?HandlerInterface $processHandler = null): Process {

            return new Process($executable, $arguments, rtrim($workingDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $processHandler);
        }

        public function getArguments(): array {
            return $this->arguments;
        }

        public function getCommand(): string {
            $arguments = $this->getArguments();
            \setlocale(LC_CTYPE, $this->getEncoding());
            foreach ($arguments as &$argument) {
                $argument = \escapeshellarg($argument);
            }
            unset($argument);
            \array_unshift($arguments, \escapeshellcmd($this->getExecutable()));

            return implode(' ', $arguments);
        }

        public function getEncoding(): string {
            return $this->encoding;
        }

        public function getEnv(): array {
            return $this->env;
        }

        public function getExecutable(): string {
            return $this->executable;
        }

        public function getExitcode(): ?int {
            return $this->exitcode;
        }

        public function getPid(): int {
            if ($this->pid === null) {
                $this->pid = (int)$this->getProcessInformation(self::I_PID);
            }

            return $this->pid;
        }

        public function getProcessHandler(): HandlerInterface {
            return $this->processHandler;
        }

        public function getProcessInformation(string $key, $fallback = null) {
            return proc_get_status($this->process)[$key] ?? $fallback;
        }

        public function getStderr(): string {
            return $this->stderr;
        }

        public function getStdout(): string {
            return $this->stdout;
        }

        public function getWorkingDirectory(): string {
            return $this->workingDirectory;
        }

        public function processReadStdErr(string $errorString, Process $process): Process {
            $this->stderr .= $errorString;

            return $process;
        }

        public function processReadStdOut(string $outputString, Process $process): Process {
            $this->stdout .= $outputString;

            return $process;
        }

        public function processWriteStdIn(Process $process): ?string {
            $return = $this->stdIn;
            $this->stdIn = null;

            return $return;
        }

        public function run(): Process {
            $this->setEnv('LANG', $this->getEncoding());
            $this->stdout = $this->stderr = '';
            $this->pipes = [];
            $this->process = \proc_open($this->getCommand(), self::DESCRIPTORSPEC, $this->pipes, $this->getWorkingDirectory(), $this->getEnv());
            Registry::add($this);
            \stream_set_blocking($this->pipes[self::STD_OUT], 0);
            \stream_set_blocking($this->pipes[self::STD_ERR], 0);
            $this->workPipes()->close();

            return $this;
        }

        public function setArguments(array $arguments): Process {
            $this->arguments = $arguments;

            return $this;
        }

        public function setEncoding(string $encoding): Process {
            $this->encoding = $encoding;

            return $this;
        }

        public function setEnv(string $key, string $value): Process {
            return $this->addEnvs([$key => $value]);
        }

        public function setExecutable(string $executable): Process {
            $this->executable = $executable;

            return $this;
        }

        public function setProcessHandler(?HandlerInterface $processHandler): Process {
            $this->processHandler = $processHandler ?? $this;

            return $this;
        }

        public function setStdIn(string $string): Process {
            $this->stdIn = $string;

            return $this;
        }

        public function setWorkingDirectory(string $workingDirectory): Process {
            $this->workingDirectory = $workingDirectory;

            return $this;
        }

        private function workPipes(): Process {
            $processHandler = $this->getProcessHandler();
            $opens = array(self::STD_IN => true, self::STD_OUT => true, self::STD_ERR => true);
            while (\in_array(true, $opens, true)) {
                foreach ([self::STD_ERR, self::STD_OUT, self::STD_IN] as $id) {
                    $opens[$id] = \is_resource($this->pipes[$id]);
                    if ($opens[$id] === false) {
                        continue;
                    }
                    if ($id === self::STD_IN) {
                        $s = $processHandler->processWriteStdIn($this);
                        if (\is_string($s) && $s !== '') {
                            \fwrite($this->pipes[self::STD_IN], $s);
                            continue;
                        }

                        if ($s === null) {
                            \fclose($this->pipes[self::STD_IN]);
                        }
                        continue;
                    }

                    if (\feof($this->pipes[$id])) {
                        \fclose($this->pipes[$id]);
                        continue;
                    }
                    \call_user_func($id === self::STD_OUT ? [$processHandler, 'processReadStdOut'] : [$processHandler, 'processReadStdErr'], \fgets($this->pipes[$id]), $this);
                }
            }

            return $this;
        }

    }
}