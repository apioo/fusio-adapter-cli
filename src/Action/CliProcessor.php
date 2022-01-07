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

use Fusio\Engine\ContextInterface;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use PSX\Http\Environment\HttpResponseInterface;

/**
 * CliProcessor
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class CliProcessor extends CliEngine
{
    public function getName(): string
    {
        return 'CLI-Processor';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $this->setCommand($configuration->get('command'));
        $this->setType($configuration->get('type'));
        $this->setEnv($configuration->get('env'));
        $this->setCwd($configuration->get('cwd'));
        $this->setTimeout($configuration->get('timeout'));

        return parent::handle($request, $configuration, $context);
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
}
