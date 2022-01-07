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
use Fusio\Engine\Request\HttpRequest;
use Fusio\Engine\Request\RpcRequest;
use Fusio\Engine\RequestInterface;
use Symfony\Component\Process\Process;

/**
 * CliEngine
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class CliEngine extends ActionAbstract
{
    protected const TYPE_TEXT   = 'text/plain';
    protected const TYPE_JSON   = 'application/json';
    protected const TYPE_BINARY = 'application/octet-stream';

    protected ?string $command;
    protected ?string $type = null;
    protected ?string $env = null;
    protected ?string $cwd = null;
    protected ?int $timeout = null;

    public function __construct(?string $command = null)
    {
        $this->command = $command;
    }

    public function setCommand(?string $command): void
    {
        $this->command = $command;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function setEnv(?string $env): void
    {
        $this->env = $env;
    }

    public function setCwd(?string $cwd): void
    {
        $this->cwd = $cwd;
    }

    public function setTimeout(?int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        $env = $this->getEnvVariables($request);
        $cwd = !empty($this->cwd) ? $this->cwd : null;
        $timeout = !empty($this->timeout) ? (int) $this->timeout : null;

        $input = \json_encode($request->getPayload());

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

    private function getEnvVariables(RequestInterface $request): array
    {
        $env = [];
        if ($request instanceof HttpRequest) {
            $env = array_merge($env, $request->getUriFragments());
            $env = array_merge($env, $request->getParameters());
            $env = array_merge($env, $request->getHeaders());
        } elseif ($request instanceof RpcRequest) {
            $env = array_merge($env, $request->getArguments());
        }

        if (!empty($this->env)) {
            $config = [];
            parse_str($this->env, $config);
            $env = array_merge($env, $config);
        }

        $result = [];
        foreach ($env as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $key = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $key));
            if (is_scalar($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
