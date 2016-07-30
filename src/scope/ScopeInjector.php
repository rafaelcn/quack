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
namespace QuackCompiler\Scope;

use \QuackCompiler\Ast\Expr;
use \QuackCompiler\Ast\Stmt;

class ScopeInjector
{
    private $ast;
    private $global_scope;

    public function __construct($ast, $global_scope)
    {
        $this->ast = $ast;
        $this->global_scope = $global_scope;
    }

    public function process()
    {
        $this->inject($this->ast, $this->global_scope);
        return $this->ast;
    }

    private function inject(&$node, Scope &$parent_scope)
    {
        // Each symbol will be stored here
        $scope = new Scope;
        $scope->parent = $parent_scope;

        // Recursive case, list of statements
        if (is_array($node)) {
            // We'll walk through the statements (and futurely, over the expressions)
            // and extract the local symbols, also, binding the parent scope reference
            foreach ($node as $stmt) {
                if ($stmt instanceof Stmt\LetStmt || $stmt instanceof Stmt\ConstStmt) {
                    foreach ($stmt->definitions as $def) {
                        // For each definition, check if it exists in the table of symbol
                        // definitions. If, so, it is an error, because the variable
                        // is being defined twice
                        if ($scope->symbolInScope($def[0])) {
                            throw new ScopeError([
                                'begin'   => $stmt->begin,
                                'end'     => $stmt->end,
                                'message' => "Symbol `" . BEGIN_GREEN . $def[0] . END_GREEN . BEGIN_RED . "' declared twice"
                            ]);
                        }

                        $scope->insert($def[0], ['initialized' => null !== $def[1]]);
                    }
                }

                $this->inject($stmt, $parent_scope);
            }

            // TODO: Remove latter. Clear values from AST for better visibility
            foreach ($node as $stmt) {
                unset($stmt->begin);
                unset($stmt->end);
            }

            // Temp. Hold in an object later
            $node['scope'] = $scope;
        } else {
            $node->scope = $scope;
        }

        // Deal with all sort of blocks
        if ($node instanceof Stmt\BlockStmt) {
            $this->inject($node->stmt_list, $node->scope);
        }
    }
}