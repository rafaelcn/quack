<?php
/**
 * Quack Compiler and toolkit
 * Copyright (C) 2016 Marcelo Camargo <marcelocamargo@linuxmail.org> and
 * CONTRIBUTORS.
 *
 * This file is part of Quack.
 *
 * Quack is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Quack is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Quack.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace QuackCompiler\Ast\Stmt;

use \QuackCompiler\Ast\Stmt\BlockStmt;
use \QuackCompiler\Intl\Localization;
use \QuackCompiler\Parser\Parser;
use \QuackCompiler\Scope\Scope;
use \QuackCompiler\Types\NativeQuackType;
use \QuackCompiler\Types\TypeError;

class IfStmt extends Stmt
{
    public $condition;
    public $body;
    public $elif;
    public $else;

    public function __construct($condition, $body, $elif, $else)
    {
        $this->condition = $condition;
        $this->body = $body;
        $this->elif = $elif;
        $this->else = $else;
    }

    public function format(Parser $parser)
    {
        $source = 'if ';
        $source .= $this->condition->format($parser);
        $source .= PHP_EOL;
        $parser->openScope();
        foreach ($this->body as $stmt) {
            $source .= $parser->indent();
            $source .= $stmt->format($parser);
        }
        $parser->closeScope();

        foreach ($this->elif as $elif) {
            $source .= $elif->format($parser);
        }

        if (null !== $this->else) {
            $source .= $parser->indent();
            $source .= 'else';
            $source .= PHP_EOL;
            $parser->openScope();
            foreach ($this->else as $stmt) {
                $source .= $parser->indent();
                $source .= $stmt->format($parser);
            }
            $parser->closeScope();
        }

        $source .= $parser->indent();
        $source .= 'end';
        $source .= PHP_EOL;

        return $source;
    }

    public function injectScope($parent_scope)
    {
        $this->condition->injectScope($parent_scope);

        // Bind scope for body of `if'
        $body_scope = new Scope($parent_scope);
        foreach ($this->body as $stmt) {
            $stmt->injectScope($body_scope);
        }

        // Bind scope for every elif. This class is just a
        // bridge for that
        foreach ($this->elif as $elif) {
            $elif->injectScope($parent_scope);
        }

        // If we have `else', bind in depth
        if (null !== $this->else) {
            $else_scope = new Scope($parent_scope);

            foreach ($this->else as $stmt) {
                $stmt->injectScope($else_scope);
            }
        }
    }

    public function runTypeChecker()
    {
        $condition_type = $this->condition->getType();
        if (!$condition_type->isBoolean()) {
            throw new TypeError(Localization::message('TYP140', [$condition_type]));
        }

        foreach ($this->body as $stmt) {
            $stmt->runTypeChecker();
        }

        foreach ($this->elif as $elif) {
            $elif->runTypeChecker();
        }

        if (null !== $this->else) {
            $this->else->runTypeChecker();
        }
    }
}
