<?php

namespace Axute\Process {


    interface HandlerInterface {

        /**
         * @param string $errorString
         * @param Process $process (use close function to close the process)
         * @return Process
         */
        public function processReadStdErr(string $errorString, Process $process): Process;

        /**
         * @param string $outputString
         * @param Process $process  (use close function to close the process)
         * @return Process
         */
        public function processReadStdOut(string $outputString, Process $process): Process;

        /**
         * @param Process $process
         * @return string|null (null to close the StdIn resource)
         */
        public function processWriteStdIn(Process $process):?string;

    }
}