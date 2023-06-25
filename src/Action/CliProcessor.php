<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2023 Christoph Kappestein <christoph.kappestein@gmail.com>
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
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use PSX\Http\Environment\HttpResponseInterface;
use Symfony\Component\Process\Process;

/**
 * CliProcessor
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://fusio-project.org
 */
class CliProcessor extends ActionAbstract
{
    private const TYPE_TEXT   = 'text/plain';
    private const TYPE_JSON   = 'application/json';
    private const TYPE_BINARY = 'application/octet-stream';

    public function getName(): string
    {
        return 'CLI-Processor';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $command = $configuration->get('command');
        if (empty($command)) {
            throw new ConfigurationException('No command configured');
        }

        $type = $configuration->get('type');
        $env = $configuration->get('env');
        $cwd = $configuration->get('cwd');

        $timeout = $configuration->get('timeout');
        if (!empty($timeout)) {
            $timeout = (int) $timeout;
        } else {
            $timeout = null;
        }

        $env = $this->getEnvVariables($request, $env);
        $input = \json_encode($request->getPayload());

        $process = Process::fromShellCommandline($command, $cwd, $env, $input, $timeout);
        $process->run();

        $httpCode = $process->isSuccessful() ? 200 : 500;
        $exitCode = $process->getExitCode();
        $output = $process->getOutput();

        if ($type === self::TYPE_JSON) {
            $data = [
                'exitCode' => $exitCode,
                'output' => \json_decode($output),
            ];
        } elseif ($type === self::TYPE_BINARY) {
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

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $options = [
            self::TYPE_TEXT   => self::TYPE_TEXT,
            self::TYPE_JSON   => self::TYPE_JSON,
            self::TYPE_BINARY => self::TYPE_BINARY,
        ];

        $builder->add($elementFactory->newInput('command', 'Command', 'text', 'The command to execute i.e. "echo foo"'));
        $builder->add($elementFactory->newSelect('type', 'Content-Type', $options, 'The content type which is produced by the command'));
        $builder->add($elementFactory->newInput('env', 'Env', 'text', 'Optional environment variables passed to the process i.e. "foo=bar&bar=foo"'));
        $builder->add($elementFactory->newInput('cwd', 'Cwd', 'text', 'Optional current working dir'));
        $builder->add($elementFactory->newInput('timeout', 'Timout', 'number', 'Optional maximum execution timeout'));
    }

    private function getEnvVariables(RequestInterface $request, ?string $userEnv): array
    {
        $env = $request->getArguments();
        if (!empty($userEnv)) {
            $config = [];
            parse_str($userEnv, $config);
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
