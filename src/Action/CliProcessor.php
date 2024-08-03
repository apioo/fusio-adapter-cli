<?php
/*
 * Fusio is an open source API management platform which helps to create innovative API solutions.
 * For the current version and information visit <https://www.fusio-project.org/>
 *
 * Copyright 2015-2023 Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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
 * @license http://www.apache.org/licenses/LICENSE-2.0
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
        if ($userEnv !== null) {
            $config = [];
            parse_str($userEnv, $config);
            $env = array_merge($env, $config);
        }

        $result = [];
        foreach ($env as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $key = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', (string) $key));
            if (is_scalar($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
