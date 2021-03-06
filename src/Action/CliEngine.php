<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2019 Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Fusio\Adapter\Cli\Action;

use Fusio\Engine\ActionAbstract;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use Symfony\Component\Process\Process;

/**
 * CliEngine
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class CliEngine extends ActionAbstract
{
    protected const TYPE_TEXT   = 'text/plain';
    protected const TYPE_JSON   = 'application/json';
    protected const TYPE_BINARY = 'application/octet-stream';

    /**
     * @var string
     */
    protected $command;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $env;

    /**
     * @var string
     */
    protected $cwd;

    /**
     * @var string
     */
    protected $timeout;

    public function __construct(?string $command = null)
    {
        $this->command = $command;
    }

    public function setCommand($command)
    {
        $this->command = $command;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function setEnv($env)
    {
        $this->env = $env;
    }

    public function setCwd($cwd)
    {
        $this->cwd = $cwd;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        $env = [];
        if (!empty($this->env)) {
            parse_str($this->env, $env);
        }

        $cwd = !empty($this->cwd) ? $this->cwd : null;
        $timeout = !empty($this->timeout) ? (int) $this->timeout : null;

        $input = \json_encode($request->getBody());

        $process = Process::fromShellCommandline($this->command, $cwd, $env, $input, $timeout);
        $process->run();

        $httpCode = $process->isSuccessful() ? 200 : 500;
        $exitCode = $process->getExitCode();
        $output = $process->getOutput();

        if ($this->type === self::TYPE_JSON) {
            $data = [
                'exitCode' => $exitCode,
                'output' => \json_decode($output),
            ];
        } elseif ($this->type === self::TYPE_BINARY) {
            $data = [
                'exitCode' => $exitCode,
                'output' => base64_encode($output),
            ];
        } else {
            $data = [
                'exitCode' => $exitCode,
                'output' => $output,
            ];
        }

        return $this->response->build(
            $httpCode,
            [],
            $data
        );
    }
}
