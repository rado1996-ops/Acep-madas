<?php

/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */
namespace MailPoetVendor\Doctrine\ORM\Query;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Doctrine\ORM\Query;
use MailPoetVendor\Doctrine\ORM\Mapping\ClassMetadata;
/**
 * An LL(*) recursive-descent parser for the context-free grammar of the Doctrine Query Language.
 * Parses a DQL query, reports any errors in it, and generates an AST.
 *
 * @since   2.0
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 * @author  Janne Vanhala <jpvanhal@cc.hut.fi>
 * @author  Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
class Parser
{
    /**
     * READ-ONLY: Maps BUILT-IN string function names to AST class names.
     *
     * @var array
     */
    private static $_STRING_FUNCTIONS = array('concat' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\ConcatFunction', 'substring' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\SubstringFunction', 'trim' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\TrimFunction', 'lower' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\LowerFunction', 'upper' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\UpperFunction', 'identity' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\IdentityFunction');
    /**
     * READ-ONLY: Maps BUILT-IN numeric function names to AST class names.
     *
     * @var array
     */
    private static $_NUMERIC_FUNCTIONS = array('length' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\LengthFunction', 'locate' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\LocateFunction', 'abs' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\AbsFunction', 'sqrt' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\SqrtFunction', 'mod' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\ModFunction', 'size' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\SizeFunction', 'date_diff' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\DateDiffFunction', 'bit_and' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\BitAndFunction', 'bit_or' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\BitOrFunction');
    /**
     * READ-ONLY: Maps BUILT-IN datetime function names to AST class names.
     *
     * @var array
     */
    private static $_DATETIME_FUNCTIONS = array('current_date' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\CurrentDateFunction', 'current_time' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\CurrentTimeFunction', 'current_timestamp' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\CurrentTimestampFunction', 'date_add' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\DateAddFunction', 'date_sub' => 'MailPoetVendor\\Doctrine\\ORM\\Query\\AST\\Functions\\DateSubFunction');
    /*
     * Expressions that were encountered during parsing of identifiers and expressions
     * and still need to be validated.
     */
    /**
     * @var array
     */
    private $deferredIdentificationVariables = array();
    /**
     * @var array
     */
    private $deferredPartialObjectExpressions = array();
    /**
     * @var array
     */
    private $deferredPathExpressions = array();
    /**
     * @var array
     */
    private $deferredResultVariables = array();
    /**
     * @var array
     */
    private $deferredNewObjectExpressions = array();
    /**
     * The lexer.
     *
     * @var \MailPoetVendor\Doctrine\ORM\Query\Lexer
     */
    private $lexer;
    /**
     * The parser result.
     *
     * @var \MailPoetVendor\Doctrine\ORM\Query\ParserResult
     */
    private $parserResult;
    /**
     * The EntityManager.
     *
     * @var \MailPoetVendor\Doctrine\ORM\EntityManager
     */
    private $em;
    /**
     * The Query to parse.
     *
     * @var Query
     */
    private $query;
    /**
     * Map of declared query components in the parsed query.
     *
     * @var array
     */
    private $queryComponents = array();
    /**
     * Keeps the nesting level of defined ResultVariables.
     *
     * @var integer
     */
    private $nestingLevel = 0;
    /**
     * Any additional custom tree walkers that modify the AST.
     *
     * @var array
     */
    private $customTreeWalkers = array();
    /**
     * The custom last tree walker, if any, that is responsible for producing the output.
     *
     * @var TreeWalker
     */
    private $customOutputWalker;
    /**
     * @var array
     */
    private $identVariableExpressions = array();
    /**
     * Checks if a function is internally defined. Used to prevent overwriting
     * of built-in functions through user-defined functions.
     *
     * @param string $functionName
     *
     * @return bool
     */
    public static function isInternalFunction($functionName)
    {
        $functionName = \strtolower($functionName);
        return isset(self::$_STRING_FUNCTIONS[$functionName]) || isset(self::$_DATETIME_FUNCTIONS[$functionName]) || isset(self::$_NUMERIC_FUNCTIONS[$functionName]);
    }
    /**
     * Creates a new query parser object.
     *
     * @param Query $query The Query to parse.
     */
    public function __construct(\MailPoetVendor\Doctrine\ORM\Query $query)
    {
        $this->query = $query;
        $this->em = $query->getEntityManager();
        $this->lexer = new \MailPoetVendor\Doctrine\ORM\Query\Lexer($query->getDql());
        $this->parserResult = new \MailPoetVendor\Doctrine\ORM\Query\ParserResult();
    }
    /**
     * Sets a custom tree walker that produces output.
     * This tree walker will be run last over the AST, after any other walkers.
     *
     * @param string $className
     *
     * @return void
     */
    public function setCustomOutputTreeWalker($className)
    {
        $this->customOutputWalker = $className;
    }
    /**
     * Adds a custom tree walker for modifying the AST.
     *
     * @param string $className
     *
     * @return void
     */
    public function addCustomTreeWalker($className)
    {
        $this->customTreeWalkers[] = $className;
    }
    /**
     * Gets the lexer used by the parser.
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\Lexer
     */
    public function getLexer()
    {
        return $this->lexer;
    }
    /**
     * Gets the ParserResult that is being filled with information during parsing.
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\ParserResult
     */
    public function getParserResult()
    {
        return $this->parserResult;
    }
    /**
     * Gets the EntityManager used by the parser.
     *
     * @return \MailPoetVendor\Doctrine\ORM\EntityManager
     */
    public function getEntityManager()
    {
        return $this->em;
    }
    /**
     * Parses and builds AST for the given Query.
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\SelectStatement |
     *         \MailPoetVendor\Doctrine\ORM\Query\AST\UpdateStatement |
     *         \MailPoetVendor\Doctrine\ORM\Query\AST\DeleteStatement
     */
    public function getAST()
    {
        // Parse & build AST
        $AST = $this->QueryLanguage();
        // Process any deferred validations of some nodes in the AST.
        // This also allows post-processing of the AST for modification purposes.
        $this->processDeferredIdentificationVariables();
        if ($this->deferredPartialObjectExpressions) {
            $this->processDeferredPartialObjectExpressions();
        }
        if ($this->deferredPathExpressions) {
            $this->processDeferredPathExpressions($AST);
        }
        if ($this->deferredResultVariables) {
            $this->processDeferredResultVariables();
        }
        if ($this->deferredNewObjectExpressions) {
            $this->processDeferredNewObjectExpressions($AST);
        }
        $this->processRootEntityAliasSelected();
        // TODO: Is there a way to remove this? It may impact the mixed hydration resultset a lot!
        $this->fixIdentificationVariableOrder($AST);
        return $AST;
    }
    /**
     * Attempts to match the given token with the current lookahead token.
     *
     * If they match, updates the lookahead token; otherwise raises a syntax
     * error.
     *
     * @param int $token The token type.
     *
     * @return void
     *
     * @throws QueryException If the tokens don't match.
     */
    public function match($token)
    {
        $lookaheadType = $this->lexer->lookahead['type'];
        // short-circuit on first condition, usually types match
        if ($lookaheadType !== $token && $token !== \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER && $lookaheadType <= \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER) {
            $this->syntaxError($this->lexer->getLiteral($token));
        }
        $this->lexer->moveNext();
    }
    /**
     * Frees this parser, enabling it to be reused.
     *
     * @param boolean $deep     Whether to clean peek and reset errors.
     * @param integer $position Position to reset.
     *
     * @return void
     */
    public function free($deep = \false, $position = 0)
    {
        // WARNING! Use this method with care. It resets the scanner!
        $this->lexer->resetPosition($position);
        // Deep = true cleans peek and also any previously defined errors
        if ($deep) {
            $this->lexer->resetPeek();
        }
        $this->lexer->token = null;
        $this->lexer->lookahead = null;
    }
    /**
     * Parses a query string.
     *
     * @return ParserResult
     */
    public function parse()
    {
        $AST = $this->getAST();
        if (($customWalkers = $this->query->getHint(\MailPoetVendor\Doctrine\ORM\Query::HINT_CUSTOM_TREE_WALKERS)) !== \false) {
            $this->customTreeWalkers = $customWalkers;
        }
        if (($customOutputWalker = $this->query->getHint(\MailPoetVendor\Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER)) !== \false) {
            $this->customOutputWalker = $customOutputWalker;
        }
        // Run any custom tree walkers over the AST
        if ($this->customTreeWalkers) {
            $treeWalkerChain = new \MailPoetVendor\Doctrine\ORM\Query\TreeWalkerChain($this->query, $this->parserResult, $this->queryComponents);
            foreach ($this->customTreeWalkers as $walker) {
                $treeWalkerChain->addTreeWalker($walker);
            }
            switch (\true) {
                case $AST instanceof \MailPoetVendor\Doctrine\ORM\Query\AST\UpdateStatement:
                    $treeWalkerChain->walkUpdateStatement($AST);
                    break;
                case $AST instanceof \MailPoetVendor\Doctrine\ORM\Query\AST\DeleteStatement:
                    $treeWalkerChain->walkDeleteStatement($AST);
                    break;
                case $AST instanceof \MailPoetVendor\Doctrine\ORM\Query\AST\SelectStatement:
                default:
                    $treeWalkerChain->walkSelectStatement($AST);
            }
            $this->queryComponents = $treeWalkerChain->getQueryComponents();
        }
        $outputWalkerClass = $this->customOutputWalker ?: __NAMESPACE__ . '\\SqlWalker';
        $outputWalker = new $outputWalkerClass($this->query, $this->parserResult, $this->queryComponents);
        // Assign an SQL executor to the parser result
        $this->parserResult->setSqlExecutor($outputWalker->getExecutor($AST));
        return $this->parserResult;
    }
    /**
     * Fixes order of identification variables.
     *
     * They have to appear in the select clause in the same order as the
     * declarations (from ... x join ... y join ... z ...) appear in the query
     * as the hydration process relies on that order for proper operation.
     *
     * @param AST\SelectStatement|AST\DeleteStatement|AST\UpdateStatement $AST
     *
     * @return void
     */
    private function fixIdentificationVariableOrder($AST)
    {
        if (\count($this->identVariableExpressions) <= 1) {
            return;
        }
        foreach ($this->queryComponents as $dqlAlias => $qComp) {
            if (!isset($this->identVariableExpressions[$dqlAlias])) {
                continue;
            }
            $expr = $this->identVariableExpressions[$dqlAlias];
            $key = \array_search($expr, $AST->selectClause->selectExpressions);
            unset($AST->selectClause->selectExpressions[$key]);
            $AST->selectClause->selectExpressions[] = $expr;
        }
    }
    /**
     * Generates a new syntax error.
     *
     * @param string      $expected Expected string.
     * @param array|null  $token    Got token.
     *
     * @return void
     *
     * @throws \MailPoetVendor\Doctrine\ORM\Query\QueryException
     */
    public function syntaxError($expected = '', $token = null)
    {
        if ($token === null) {
            $token = $this->lexer->lookahead;
        }
        $tokenPos = isset($token['position']) ? $token['position'] : '-1';
        $message = "line 0, col {$tokenPos}: Error: ";
        $message .= $expected !== '' ? "Expected {$expected}, got " : 'Unexpected ';
        $message .= $this->lexer->lookahead === null ? 'end of string.' : "'{$token['value']}'";
        throw \MailPoetVendor\Doctrine\ORM\Query\QueryException::syntaxError($message, \MailPoetVendor\Doctrine\ORM\Query\QueryException::dqlError($this->query->getDQL()));
    }
    /**
     * Generates a new semantical error.
     *
     * @param string     $message Optional message.
     * @param array|null $token   Optional token.
     *
     * @return void
     *
     * @throws \MailPoetVendor\Doctrine\ORM\Query\QueryException
     */
    public function semanticalError($message = '', $token = null)
    {
        if ($token === null) {
            $token = $this->lexer->lookahead;
        }
        // Minimum exposed chars ahead of token
        $distance = 12;
        // Find a position of a final word to display in error string
        $dql = $this->query->getDql();
        $length = \strlen($dql);
        $pos = $token['position'] + $distance;
        $pos = \strpos($dql, ' ', $length > $pos ? $pos : $length);
        $length = $pos !== \false ? $pos - $token['position'] : $distance;
        $tokenPos = isset($token['position']) && $token['position'] > 0 ? $token['position'] : '-1';
        $tokenStr = \substr($dql, $token['position'], $length);
        // Building informative message
        $message = 'line 0, col ' . $tokenPos . " near '" . $tokenStr . "': Error: " . $message;
        throw \MailPoetVendor\Doctrine\ORM\Query\QueryException::semanticalError($message, \MailPoetVendor\Doctrine\ORM\Query\QueryException::dqlError($this->query->getDQL()));
    }
    /**
     * Peeks beyond the matched closing parenthesis and returns the first token after that one.
     *
     * @param boolean $resetPeek Reset peek after finding the closing parenthesis.
     *
     * @return array
     */
    private function peekBeyondClosingParenthesis($resetPeek = \true)
    {
        $token = $this->lexer->peek();
        $numUnmatched = 1;
        while ($numUnmatched > 0 && $token !== null) {
            switch ($token['type']) {
                case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS:
                    ++$numUnmatched;
                    break;
                case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS:
                    --$numUnmatched;
                    break;
                default:
            }
            $token = $this->lexer->peek();
        }
        if ($resetPeek) {
            $this->lexer->resetPeek();
        }
        return $token;
    }
    /**
     * Checks if the given token indicates a mathematical operator.
     *
     * @param array $token
     *
     * @return boolean TRUE if the token is a mathematical operator, FALSE otherwise.
     */
    private function isMathOperator($token)
    {
        return \in_array($token['type'], array(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_PLUS, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MINUS, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DIVIDE, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MULTIPLY));
    }
    /**
     * Checks if the next-next (after lookahead) token starts a function.
     *
     * @return boolean TRUE if the next-next tokens start a function, FALSE otherwise.
     */
    private function isFunction()
    {
        $lookaheadType = $this->lexer->lookahead['type'];
        $peek = $this->lexer->peek();
        $this->lexer->resetPeek();
        return $lookaheadType >= \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER && $peek['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS;
    }
    /**
     * Checks whether the given token type indicates an aggregate function.
     *
     * @param int $tokenType
     *
     * @return boolean TRUE if the token type is an aggregate function, FALSE otherwise.
     */
    private function isAggregateFunction($tokenType)
    {
        return \in_array($tokenType, array(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AVG, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MIN, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MAX, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_SUM, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COUNT));
    }
    /**
     * Checks whether the current lookahead token of the lexer has the type T_ALL, T_ANY or T_SOME.
     *
     * @return boolean
     */
    private function isNextAllAnySome()
    {
        return \in_array($this->lexer->lookahead['type'], array(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_ALL, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_ANY, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_SOME));
    }
    /**
     * Validates that the given <tt>IdentificationVariable</tt> is semantically correct.
     * It must exist in query components list.
     *
     * @return void
     */
    private function processDeferredIdentificationVariables()
    {
        foreach ($this->deferredIdentificationVariables as $deferredItem) {
            $identVariable = $deferredItem['expression'];
            // Check if IdentificationVariable exists in queryComponents
            if (!isset($this->queryComponents[$identVariable])) {
                $this->semanticalError("'{$identVariable}' is not defined.", $deferredItem['token']);
            }
            $qComp = $this->queryComponents[$identVariable];
            // Check if queryComponent points to an AbstractSchemaName or a ResultVariable
            if (!isset($qComp['metadata'])) {
                $this->semanticalError("'{$identVariable}' does not point to a Class.", $deferredItem['token']);
            }
            // Validate if identification variable nesting level is lower or equal than the current one
            if ($qComp['nestingLevel'] > $deferredItem['nestingLevel']) {
                $this->semanticalError("'{$identVariable}' is used outside the scope of its declaration.", $deferredItem['token']);
            }
        }
    }
    /**
     * Validates that the given <tt>NewObjectExpression</tt>.
     *
     * @param \MailPoetVendor\Doctrine\ORM\Query\AST\SelectClause $AST
     *
     * @return void
     */
    private function processDeferredNewObjectExpressions($AST)
    {
        foreach ($this->deferredNewObjectExpressions as $deferredItem) {
            $expression = $deferredItem['expression'];
            $token = $deferredItem['token'];
            $className = $expression->className;
            $args = $expression->args;
            $fromClassName = isset($AST->fromClause->identificationVariableDeclarations[0]->rangeVariableDeclaration->abstractSchemaName) ? $AST->fromClause->identificationVariableDeclarations[0]->rangeVariableDeclaration->abstractSchemaName : null;
            // If the namespace is not given then assumes the first FROM entity namespace
            if (\strpos($className, '\\') === \false && !\class_exists($className) && \strpos($fromClassName, '\\') !== \false) {
                $namespace = \substr($fromClassName, 0, \strrpos($fromClassName, '\\'));
                $fqcn = $namespace . '\\' . $className;
                if (\class_exists($fqcn)) {
                    $expression->className = $fqcn;
                    $className = $fqcn;
                }
            }
            if (!\class_exists($className)) {
                $this->semanticalError(\sprintf('Class "%s" is not defined.', $className), $token);
            }
            $class = new \ReflectionClass($className);
            if (!$class->isInstantiable()) {
                $this->semanticalError(\sprintf('Class "%s" can not be instantiated.', $className), $token);
            }
            if ($class->getConstructor() === null) {
                $this->semanticalError(\sprintf('Class "%s" has not a valid constructor.', $className), $token);
            }
            if ($class->getConstructor()->getNumberOfRequiredParameters() > \count($args)) {
                $this->semanticalError(\sprintf('Number of arguments does not match with "%s" constructor declaration.', $className), $token);
            }
        }
    }
    /**
     * Validates that the given <tt>PartialObjectExpression</tt> is semantically correct.
     * It must exist in query components list.
     *
     * @return void
     */
    private function processDeferredPartialObjectExpressions()
    {
        foreach ($this->deferredPartialObjectExpressions as $deferredItem) {
            $expr = $deferredItem['expression'];
            $class = $this->queryComponents[$expr->identificationVariable]['metadata'];
            foreach ($expr->partialFieldSet as $field) {
                if (isset($class->fieldMappings[$field])) {
                    continue;
                }
                if (isset($class->associationMappings[$field]) && $class->associationMappings[$field]['isOwningSide'] && $class->associationMappings[$field]['type'] & \MailPoetVendor\Doctrine\ORM\Mapping\ClassMetadata::TO_ONE) {
                    continue;
                }
                $this->semanticalError("There is no mapped field named '{$field}' on class " . $class->name . ".", $deferredItem['token']);
            }
            if (\array_intersect($class->identifier, $expr->partialFieldSet) != $class->identifier) {
                $this->semanticalError("The partial field selection of class " . $class->name . " must contain the identifier.", $deferredItem['token']);
            }
        }
    }
    /**
     * Validates that the given <tt>ResultVariable</tt> is semantically correct.
     * It must exist in query components list.
     *
     * @return void
     */
    private function processDeferredResultVariables()
    {
        foreach ($this->deferredResultVariables as $deferredItem) {
            $resultVariable = $deferredItem['expression'];
            // Check if ResultVariable exists in queryComponents
            if (!isset($this->queryComponents[$resultVariable])) {
                $this->semanticalError("'{$resultVariable}' is not defined.", $deferredItem['token']);
            }
            $qComp = $this->queryComponents[$resultVariable];
            // Check if queryComponent points to an AbstractSchemaName or a ResultVariable
            if (!isset($qComp['resultVariable'])) {
                $this->semanticalError("'{$resultVariable}' does not point to a ResultVariable.", $deferredItem['token']);
            }
            // Validate if identification variable nesting level is lower or equal than the current one
            if ($qComp['nestingLevel'] > $deferredItem['nestingLevel']) {
                $this->semanticalError("'{$resultVariable}' is used outside the scope of its declaration.", $deferredItem['token']);
            }
        }
    }
    /**
     * Validates that the given <tt>PathExpression</tt> is semantically correct for grammar rules:
     *
     * AssociationPathExpression             ::= CollectionValuedPathExpression | SingleValuedAssociationPathExpression
     * SingleValuedPathExpression            ::= StateFieldPathExpression | SingleValuedAssociationPathExpression
     * StateFieldPathExpression              ::= IdentificationVariable "." StateField
     * SingleValuedAssociationPathExpression ::= IdentificationVariable "." SingleValuedAssociationField
     * CollectionValuedPathExpression        ::= IdentificationVariable "." CollectionValuedAssociationField
     *
     * @param mixed $AST
     *
     * @return void
     */
    private function processDeferredPathExpressions($AST)
    {
        foreach ($this->deferredPathExpressions as $deferredItem) {
            $pathExpression = $deferredItem['expression'];
            $qComp = $this->queryComponents[$pathExpression->identificationVariable];
            $class = $qComp['metadata'];
            if (($field = $pathExpression->field) === null) {
                $field = $pathExpression->field = $class->identifier[0];
            }
            // Check if field or association exists
            if (!isset($class->associationMappings[$field]) && !isset($class->fieldMappings[$field])) {
                $this->semanticalError('Class ' . $class->name . ' has no field or association named ' . $field, $deferredItem['token']);
            }
            $fieldType = \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression::TYPE_STATE_FIELD;
            if (isset($class->associationMappings[$field])) {
                $assoc = $class->associationMappings[$field];
                $fieldType = $assoc['type'] & \MailPoetVendor\Doctrine\ORM\Mapping\ClassMetadata::TO_ONE ? \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression::TYPE_SINGLE_VALUED_ASSOCIATION : \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression::TYPE_COLLECTION_VALUED_ASSOCIATION;
            }
            // Validate if PathExpression is one of the expected types
            $expectedType = $pathExpression->expectedType;
            if (!($expectedType & $fieldType)) {
                // We need to recognize which was expected type(s)
                $expectedStringTypes = array();
                // Validate state field type
                if ($expectedType & \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression::TYPE_STATE_FIELD) {
                    $expectedStringTypes[] = 'StateFieldPathExpression';
                }
                // Validate single valued association (*-to-one)
                if ($expectedType & \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression::TYPE_SINGLE_VALUED_ASSOCIATION) {
                    $expectedStringTypes[] = 'SingleValuedAssociationField';
                }
                // Validate single valued association (*-to-many)
                if ($expectedType & \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression::TYPE_COLLECTION_VALUED_ASSOCIATION) {
                    $expectedStringTypes[] = 'CollectionValuedAssociationField';
                }
                // Build the error message
                $semanticalError = 'Invalid PathExpression. ';
                $semanticalError .= \count($expectedStringTypes) == 1 ? 'Must be a ' . $expectedStringTypes[0] . '.' : \implode(' or ', $expectedStringTypes) . ' expected.';
                $this->semanticalError($semanticalError, $deferredItem['token']);
            }
            // We need to force the type in PathExpression
            $pathExpression->type = $fieldType;
        }
    }
    /**
     * @return void
     */
    private function processRootEntityAliasSelected()
    {
        if (!\count($this->identVariableExpressions)) {
            return;
        }
        $foundRootEntity = \false;
        foreach ($this->identVariableExpressions as $dqlAlias => $expr) {
            if (isset($this->queryComponents[$dqlAlias]) && $this->queryComponents[$dqlAlias]['parent'] === null) {
                $foundRootEntity = \true;
            }
        }
        if (!$foundRootEntity) {
            $this->semanticalError('Cannot select entity through identification variables without choosing at least one root entity alias.');
        }
    }
    /**
     * QueryLanguage ::= SelectStatement | UpdateStatement | DeleteStatement
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\SelectStatement |
     *         \MailPoetVendor\Doctrine\ORM\Query\AST\UpdateStatement |
     *         \MailPoetVendor\Doctrine\ORM\Query\AST\DeleteStatement
     */
    public function QueryLanguage()
    {
        $this->lexer->moveNext();
        switch ($this->lexer->lookahead['type']) {
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_SELECT:
                $statement = $this->SelectStatement();
                break;
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_UPDATE:
                $statement = $this->UpdateStatement();
                break;
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DELETE:
                $statement = $this->DeleteStatement();
                break;
            default:
                $this->syntaxError('SELECT, UPDATE or DELETE');
                break;
        }
        // Check for end of string
        if ($this->lexer->lookahead !== null) {
            $this->syntaxError('end of string');
        }
        return $statement;
    }
    /**
     * SelectStatement ::= SelectClause FromClause [WhereClause] [GroupByClause] [HavingClause] [OrderByClause]
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\SelectStatement
     */
    public function SelectStatement()
    {
        $selectStatement = new \MailPoetVendor\Doctrine\ORM\Query\AST\SelectStatement($this->SelectClause(), $this->FromClause());
        $selectStatement->whereClause = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_WHERE) ? $this->WhereClause() : null;
        $selectStatement->groupByClause = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_GROUP) ? $this->GroupByClause() : null;
        $selectStatement->havingClause = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_HAVING) ? $this->HavingClause() : null;
        $selectStatement->orderByClause = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_ORDER) ? $this->OrderByClause() : null;
        return $selectStatement;
    }
    /**
     * UpdateStatement ::= UpdateClause [WhereClause]
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\UpdateStatement
     */
    public function UpdateStatement()
    {
        $updateStatement = new \MailPoetVendor\Doctrine\ORM\Query\AST\UpdateStatement($this->UpdateClause());
        $updateStatement->whereClause = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_WHERE) ? $this->WhereClause() : null;
        return $updateStatement;
    }
    /**
     * DeleteStatement ::= DeleteClause [WhereClause]
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\DeleteStatement
     */
    public function DeleteStatement()
    {
        $deleteStatement = new \MailPoetVendor\Doctrine\ORM\Query\AST\DeleteStatement($this->DeleteClause());
        $deleteStatement->whereClause = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_WHERE) ? $this->WhereClause() : null;
        return $deleteStatement;
    }
    /**
     * IdentificationVariable ::= identifier
     *
     * @return string
     */
    public function IdentificationVariable()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER);
        $identVariable = $this->lexer->token['value'];
        $this->deferredIdentificationVariables[] = array('expression' => $identVariable, 'nestingLevel' => $this->nestingLevel, 'token' => $this->lexer->token);
        return $identVariable;
    }
    /**
     * AliasIdentificationVariable = identifier
     *
     * @return string
     */
    public function AliasIdentificationVariable()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER);
        $aliasIdentVariable = $this->lexer->token['value'];
        $exists = isset($this->queryComponents[$aliasIdentVariable]);
        if ($exists) {
            $this->semanticalError("'{$aliasIdentVariable}' is already defined.", $this->lexer->token);
        }
        return $aliasIdentVariable;
    }
    /**
     * AbstractSchemaName ::= identifier
     *
     * @return string
     */
    public function AbstractSchemaName()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER);
        $schemaName = \ltrim($this->lexer->token['value'], '\\');
        if (\strrpos($schemaName, ':') !== \false) {
            list($namespaceAlias, $simpleClassName) = \explode(':', $schemaName);
            $schemaName = $this->em->getConfiguration()->getEntityNamespace($namespaceAlias) . '\\' . $simpleClassName;
        }
        $exists = \class_exists($schemaName, \true) || \interface_exists($schemaName, \true);
        if (!$exists) {
            $this->semanticalError("Class '{$schemaName}' is not defined.", $this->lexer->token);
        }
        return $schemaName;
    }
    /**
     * AliasResultVariable ::= identifier
     *
     * @return string
     */
    public function AliasResultVariable()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER);
        $resultVariable = $this->lexer->token['value'];
        $exists = isset($this->queryComponents[$resultVariable]);
        if ($exists) {
            $this->semanticalError("'{$resultVariable}' is already defined.", $this->lexer->token);
        }
        return $resultVariable;
    }
    /**
     * ResultVariable ::= identifier
     *
     * @return string
     */
    public function ResultVariable()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER);
        $resultVariable = $this->lexer->token['value'];
        // Defer ResultVariable validation
        $this->deferredResultVariables[] = array('expression' => $resultVariable, 'nestingLevel' => $this->nestingLevel, 'token' => $this->lexer->token);
        return $resultVariable;
    }
    /**
     * JoinAssociationPathExpression ::= IdentificationVariable "." (CollectionValuedAssociationField | SingleValuedAssociationField)
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\JoinAssociationPathExpression
     */
    public function JoinAssociationPathExpression()
    {
        $identVariable = $this->IdentificationVariable();
        if (!isset($this->queryComponents[$identVariable])) {
            $this->semanticalError('Identification Variable ' . $identVariable . ' used in join path expression but was not defined before.');
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DOT);
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER);
        $field = $this->lexer->token['value'];
        // Validate association field
        $qComp = $this->queryComponents[$identVariable];
        $class = $qComp['metadata'];
        if (!$class->hasAssociation($field)) {
            $this->semanticalError('Class ' . $class->name . ' has no association named ' . $field);
        }
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\JoinAssociationPathExpression($identVariable, $field);
    }
    /**
     * Parses an arbitrary path expression and defers semantical validation
     * based on expected types.
     *
     * PathExpression ::= IdentificationVariable {"." identifier}*
     *
     * @param integer $expectedTypes
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression
     */
    public function PathExpression($expectedTypes)
    {
        $identVariable = $this->IdentificationVariable();
        $field = null;
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DOT)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DOT);
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER);
            $field = $this->lexer->token['value'];
            while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DOT)) {
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DOT);
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER);
                $field .= '.' . $this->lexer->token['value'];
            }
        }
        // Creating AST node
        $pathExpr = new \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression($expectedTypes, $identVariable, $field);
        // Defer PathExpression validation if requested to be deferred
        $this->deferredPathExpressions[] = array('expression' => $pathExpr, 'nestingLevel' => $this->nestingLevel, 'token' => $this->lexer->token);
        return $pathExpr;
    }
    /**
     * AssociationPathExpression ::= CollectionValuedPathExpression | SingleValuedAssociationPathExpression
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression
     */
    public function AssociationPathExpression()
    {
        return $this->PathExpression(\MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression::TYPE_SINGLE_VALUED_ASSOCIATION | \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression::TYPE_COLLECTION_VALUED_ASSOCIATION);
    }
    /**
     * SingleValuedPathExpression ::= StateFieldPathExpression | SingleValuedAssociationPathExpression
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression
     */
    public function SingleValuedPathExpression()
    {
        return $this->PathExpression(\MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression::TYPE_STATE_FIELD | \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression::TYPE_SINGLE_VALUED_ASSOCIATION);
    }
    /**
     * StateFieldPathExpression ::= IdentificationVariable "." StateField
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression
     */
    public function StateFieldPathExpression()
    {
        return $this->PathExpression(\MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression::TYPE_STATE_FIELD);
    }
    /**
     * SingleValuedAssociationPathExpression ::= IdentificationVariable "." SingleValuedAssociationField
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression
     */
    public function SingleValuedAssociationPathExpression()
    {
        return $this->PathExpression(\MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression::TYPE_SINGLE_VALUED_ASSOCIATION);
    }
    /**
     * CollectionValuedPathExpression ::= IdentificationVariable "." CollectionValuedAssociationField
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression
     */
    public function CollectionValuedPathExpression()
    {
        return $this->PathExpression(\MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression::TYPE_COLLECTION_VALUED_ASSOCIATION);
    }
    /**
     * SelectClause ::= "SELECT" ["DISTINCT"] SelectExpression {"," SelectExpression}
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\SelectClause
     */
    public function SelectClause()
    {
        $isDistinct = \false;
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_SELECT);
        // Check for DISTINCT
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DISTINCT)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DISTINCT);
            $isDistinct = \true;
        }
        // Process SelectExpressions (1..N)
        $selectExpressions = array();
        $selectExpressions[] = $this->SelectExpression();
        while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA);
            $selectExpressions[] = $this->SelectExpression();
        }
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\SelectClause($selectExpressions, $isDistinct);
    }
    /**
     * SimpleSelectClause ::= "SELECT" ["DISTINCT"] SimpleSelectExpression
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleSelectClause
     */
    public function SimpleSelectClause()
    {
        $isDistinct = \false;
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_SELECT);
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DISTINCT)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DISTINCT);
            $isDistinct = \true;
        }
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleSelectClause($this->SimpleSelectExpression(), $isDistinct);
    }
    /**
     * UpdateClause ::= "UPDATE" AbstractSchemaName ["AS"] AliasIdentificationVariable "SET" UpdateItem {"," UpdateItem}*
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\UpdateClause
     */
    public function UpdateClause()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_UPDATE);
        $token = $this->lexer->lookahead;
        $abstractSchemaName = $this->AbstractSchemaName();
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AS)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AS);
        }
        $aliasIdentificationVariable = $this->AliasIdentificationVariable();
        $class = $this->em->getClassMetadata($abstractSchemaName);
        // Building queryComponent
        $queryComponent = array('metadata' => $class, 'parent' => null, 'relation' => null, 'map' => null, 'nestingLevel' => $this->nestingLevel, 'token' => $token);
        $this->queryComponents[$aliasIdentificationVariable] = $queryComponent;
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_SET);
        $updateItems = array();
        $updateItems[] = $this->UpdateItem();
        while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA);
            $updateItems[] = $this->UpdateItem();
        }
        $updateClause = new \MailPoetVendor\Doctrine\ORM\Query\AST\UpdateClause($abstractSchemaName, $updateItems);
        $updateClause->aliasIdentificationVariable = $aliasIdentificationVariable;
        return $updateClause;
    }
    /**
     * DeleteClause ::= "DELETE" ["FROM"] AbstractSchemaName ["AS"] AliasIdentificationVariable
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\DeleteClause
     */
    public function DeleteClause()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DELETE);
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_FROM)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_FROM);
        }
        $token = $this->lexer->lookahead;
        $deleteClause = new \MailPoetVendor\Doctrine\ORM\Query\AST\DeleteClause($this->AbstractSchemaName());
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AS)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AS);
        }
        $aliasIdentificationVariable = $this->AliasIdentificationVariable();
        $deleteClause->aliasIdentificationVariable = $aliasIdentificationVariable;
        $class = $this->em->getClassMetadata($deleteClause->abstractSchemaName);
        // Building queryComponent
        $queryComponent = array('metadata' => $class, 'parent' => null, 'relation' => null, 'map' => null, 'nestingLevel' => $this->nestingLevel, 'token' => $token);
        $this->queryComponents[$aliasIdentificationVariable] = $queryComponent;
        return $deleteClause;
    }
    /**
     * FromClause ::= "FROM" IdentificationVariableDeclaration {"," IdentificationVariableDeclaration}*
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\FromClause
     */
    public function FromClause()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_FROM);
        $identificationVariableDeclarations = array();
        $identificationVariableDeclarations[] = $this->IdentificationVariableDeclaration();
        while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA);
            $identificationVariableDeclarations[] = $this->IdentificationVariableDeclaration();
        }
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\FromClause($identificationVariableDeclarations);
    }
    /**
     * SubselectFromClause ::= "FROM" SubselectIdentificationVariableDeclaration {"," SubselectIdentificationVariableDeclaration}*
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\SubselectFromClause
     */
    public function SubselectFromClause()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_FROM);
        $identificationVariables = array();
        $identificationVariables[] = $this->SubselectIdentificationVariableDeclaration();
        while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA);
            $identificationVariables[] = $this->SubselectIdentificationVariableDeclaration();
        }
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\SubselectFromClause($identificationVariables);
    }
    /**
     * WhereClause ::= "WHERE" ConditionalExpression
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\WhereClause
     */
    public function WhereClause()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_WHERE);
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\WhereClause($this->ConditionalExpression());
    }
    /**
     * HavingClause ::= "HAVING" ConditionalExpression
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\HavingClause
     */
    public function HavingClause()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_HAVING);
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\HavingClause($this->ConditionalExpression());
    }
    /**
     * GroupByClause ::= "GROUP" "BY" GroupByItem {"," GroupByItem}*
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\GroupByClause
     */
    public function GroupByClause()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_GROUP);
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_BY);
        $groupByItems = array($this->GroupByItem());
        while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA);
            $groupByItems[] = $this->GroupByItem();
        }
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\GroupByClause($groupByItems);
    }
    /**
     * OrderByClause ::= "ORDER" "BY" OrderByItem {"," OrderByItem}*
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\OrderByClause
     */
    public function OrderByClause()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_ORDER);
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_BY);
        $orderByItems = array();
        $orderByItems[] = $this->OrderByItem();
        while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA);
            $orderByItems[] = $this->OrderByItem();
        }
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\OrderByClause($orderByItems);
    }
    /**
     * Subselect ::= SimpleSelectClause SubselectFromClause [WhereClause] [GroupByClause] [HavingClause] [OrderByClause]
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\Subselect
     */
    public function Subselect()
    {
        // Increase query nesting level
        $this->nestingLevel++;
        $subselect = new \MailPoetVendor\Doctrine\ORM\Query\AST\Subselect($this->SimpleSelectClause(), $this->SubselectFromClause());
        $subselect->whereClause = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_WHERE) ? $this->WhereClause() : null;
        $subselect->groupByClause = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_GROUP) ? $this->GroupByClause() : null;
        $subselect->havingClause = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_HAVING) ? $this->HavingClause() : null;
        $subselect->orderByClause = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_ORDER) ? $this->OrderByClause() : null;
        // Decrease query nesting level
        $this->nestingLevel--;
        return $subselect;
    }
    /**
     * UpdateItem ::= SingleValuedPathExpression "=" NewValue
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\UpdateItem
     */
    public function UpdateItem()
    {
        $pathExpr = $this->SingleValuedPathExpression();
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_EQUALS);
        $updateItem = new \MailPoetVendor\Doctrine\ORM\Query\AST\UpdateItem($pathExpr, $this->NewValue());
        return $updateItem;
    }
    /**
     * GroupByItem ::= IdentificationVariable | ResultVariable | SingleValuedPathExpression
     *
     * @return string | \MailPoetVendor\Doctrine\ORM\Query\AST\PathExpression
     */
    public function GroupByItem()
    {
        // We need to check if we are in a IdentificationVariable or SingleValuedPathExpression
        $glimpse = $this->lexer->glimpse();
        if ($glimpse['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DOT) {
            return $this->SingleValuedPathExpression();
        }
        // Still need to decide between IdentificationVariable or ResultVariable
        $lookaheadValue = $this->lexer->lookahead['value'];
        if (!isset($this->queryComponents[$lookaheadValue])) {
            $this->semanticalError('Cannot group by undefined identification or result variable.');
        }
        return isset($this->queryComponents[$lookaheadValue]['metadata']) ? $this->IdentificationVariable() : $this->ResultVariable();
    }
    /**
     * OrderByItem ::= (
     *      SimpleArithmeticExpression | SingleValuedPathExpression |
     *      ScalarExpression | ResultVariable | FunctionDeclaration
     * ) ["ASC" | "DESC"]
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\OrderByItem
     */
    public function OrderByItem()
    {
        $this->lexer->peek();
        // lookahead => '.'
        $this->lexer->peek();
        // lookahead => token after '.'
        $peek = $this->lexer->peek();
        // lookahead => token after the token after the '.'
        $this->lexer->resetPeek();
        $glimpse = $this->lexer->glimpse();
        switch (\true) {
            case $this->isFunction($peek):
                $expr = $this->FunctionDeclaration();
                break;
            case $this->isMathOperator($peek):
                $expr = $this->SimpleArithmeticExpression();
                break;
            case $glimpse['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DOT:
                $expr = $this->SingleValuedPathExpression();
                break;
            case $this->lexer->peek() && $this->isMathOperator($this->peekBeyondClosingParenthesis()):
                $expr = $this->ScalarExpression();
                break;
            default:
                $expr = $this->ResultVariable();
                break;
        }
        $type = 'ASC';
        $item = new \MailPoetVendor\Doctrine\ORM\Query\AST\OrderByItem($expr);
        switch (\true) {
            case $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DESC):
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DESC);
                $type = 'DESC';
                break;
            case $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_ASC):
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_ASC);
                break;
            default:
        }
        $item->type = $type;
        return $item;
    }
    /**
     * NewValue ::= SimpleArithmeticExpression | StringPrimary | DatetimePrimary | BooleanPrimary |
     *      EnumPrimary | SimpleEntityExpression | "NULL"
     *
     * NOTE: Since it is not possible to correctly recognize individual types, here is the full
     * grammar that needs to be supported:
     *
     * NewValue ::= SimpleArithmeticExpression | "NULL"
     *
     * SimpleArithmeticExpression covers all *Primary grammar rules and also SimpleEntityExpression
     *
     * @return AST\ArithmeticExpression
     */
    public function NewValue()
    {
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NULL)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NULL);
            return null;
        }
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INPUT_PARAMETER)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INPUT_PARAMETER);
            return new \MailPoetVendor\Doctrine\ORM\Query\AST\InputParameter($this->lexer->token['value']);
        }
        return $this->ArithmeticExpression();
    }
    /**
     * IdentificationVariableDeclaration ::= RangeVariableDeclaration [IndexBy] {Join}*
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\IdentificationVariableDeclaration
     */
    public function IdentificationVariableDeclaration()
    {
        $joins = array();
        $rangeVariableDeclaration = $this->RangeVariableDeclaration();
        $indexBy = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INDEX) ? $this->IndexBy() : null;
        $rangeVariableDeclaration->isRoot = \true;
        while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_LEFT) || $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INNER) || $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_JOIN)) {
            $joins[] = $this->Join();
        }
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\IdentificationVariableDeclaration($rangeVariableDeclaration, $indexBy, $joins);
    }
    /**
     *
     * SubselectIdentificationVariableDeclaration ::= IdentificationVariableDeclaration
     *
     * {Internal note: WARNING: Solution is harder than a bare implementation.
     * Desired EBNF support:
     *
     * SubselectIdentificationVariableDeclaration ::= IdentificationVariableDeclaration | (AssociationPathExpression ["AS"] AliasIdentificationVariable)
     *
     * It demands that entire SQL generation to become programmatical. This is
     * needed because association based subselect requires "WHERE" conditional
     * expressions to be injected, but there is no scope to do that. Only scope
     * accessible is "FROM", prohibiting an easy implementation without larger
     * changes.}
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\SubselectIdentificationVariableDeclaration |
     *         \MailPoetVendor\Doctrine\ORM\Query\AST\IdentificationVariableDeclaration
     */
    public function SubselectIdentificationVariableDeclaration()
    {
        /*
        NOT YET IMPLEMENTED!
        
        $glimpse = $this->lexer->glimpse();
        
        if ($glimpse['type'] == Lexer::T_DOT) {
            $associationPathExpression = $this->AssociationPathExpression();
        
            if ($this->lexer->isNextToken(Lexer::T_AS)) {
                $this->match(Lexer::T_AS);
            }
        
            $aliasIdentificationVariable = $this->AliasIdentificationVariable();
            $identificationVariable      = $associationPathExpression->identificationVariable;
            $field                       = $associationPathExpression->associationField;
        
            $class       = $this->queryComponents[$identificationVariable]['metadata'];
            $targetClass = $this->em->getClassMetadata($class->associationMappings[$field]['targetEntity']);
        
            // Building queryComponent
            $joinQueryComponent = array(
                'metadata'     => $targetClass,
                'parent'       => $identificationVariable,
                'relation'     => $class->getAssociationMapping($field),
                'map'          => null,
                'nestingLevel' => $this->nestingLevel,
                'token'        => $this->lexer->lookahead
            );
        
            $this->queryComponents[$aliasIdentificationVariable] = $joinQueryComponent;
        
            return new AST\SubselectIdentificationVariableDeclaration(
                $associationPathExpression, $aliasIdentificationVariable
            );
        }
        */
        return $this->IdentificationVariableDeclaration();
    }
    /**
     * Join ::= ["LEFT" ["OUTER"] | "INNER"] "JOIN"
     *          (JoinAssociationDeclaration | RangeVariableDeclaration)
     *          ["WITH" ConditionalExpression]
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\Join
     */
    public function Join()
    {
        // Check Join type
        $joinType = \MailPoetVendor\Doctrine\ORM\Query\AST\Join::JOIN_TYPE_INNER;
        switch (\true) {
            case $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_LEFT):
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_LEFT);
                $joinType = \MailPoetVendor\Doctrine\ORM\Query\AST\Join::JOIN_TYPE_LEFT;
                // Possible LEFT OUTER join
                if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OUTER)) {
                    $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OUTER);
                    $joinType = \MailPoetVendor\Doctrine\ORM\Query\AST\Join::JOIN_TYPE_LEFTOUTER;
                }
                break;
            case $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INNER):
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INNER);
                break;
            default:
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_JOIN);
        $next = $this->lexer->glimpse();
        $joinDeclaration = $next['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DOT ? $this->JoinAssociationDeclaration() : $this->RangeVariableDeclaration();
        $adhocConditions = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_WITH);
        $join = new \MailPoetVendor\Doctrine\ORM\Query\AST\Join($joinType, $joinDeclaration);
        // Describe non-root join declaration
        if ($joinDeclaration instanceof \MailPoetVendor\Doctrine\ORM\Query\AST\RangeVariableDeclaration) {
            $joinDeclaration->isRoot = \false;
            $adhocConditions = \true;
        }
        // Check for ad-hoc Join conditions
        if ($adhocConditions) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_WITH);
            $join->conditionalExpression = $this->ConditionalExpression();
        }
        return $join;
    }
    /**
     * RangeVariableDeclaration ::= AbstractSchemaName ["AS"] AliasIdentificationVariable
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\RangeVariableDeclaration
     */
    public function RangeVariableDeclaration()
    {
        $abstractSchemaName = $this->AbstractSchemaName();
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AS)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AS);
        }
        $token = $this->lexer->lookahead;
        $aliasIdentificationVariable = $this->AliasIdentificationVariable();
        $classMetadata = $this->em->getClassMetadata($abstractSchemaName);
        // Building queryComponent
        $queryComponent = array('metadata' => $classMetadata, 'parent' => null, 'relation' => null, 'map' => null, 'nestingLevel' => $this->nestingLevel, 'token' => $token);
        $this->queryComponents[$aliasIdentificationVariable] = $queryComponent;
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\RangeVariableDeclaration($abstractSchemaName, $aliasIdentificationVariable);
    }
    /**
     * JoinAssociationDeclaration ::= JoinAssociationPathExpression ["AS"] AliasIdentificationVariable [IndexBy]
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\JoinAssociationPathExpression
     */
    public function JoinAssociationDeclaration()
    {
        $joinAssociationPathExpression = $this->JoinAssociationPathExpression();
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AS)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AS);
        }
        $aliasIdentificationVariable = $this->AliasIdentificationVariable();
        $indexBy = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INDEX) ? $this->IndexBy() : null;
        $identificationVariable = $joinAssociationPathExpression->identificationVariable;
        $field = $joinAssociationPathExpression->associationField;
        $class = $this->queryComponents[$identificationVariable]['metadata'];
        $targetClass = $this->em->getClassMetadata($class->associationMappings[$field]['targetEntity']);
        // Building queryComponent
        $joinQueryComponent = array('metadata' => $targetClass, 'parent' => $joinAssociationPathExpression->identificationVariable, 'relation' => $class->getAssociationMapping($field), 'map' => null, 'nestingLevel' => $this->nestingLevel, 'token' => $this->lexer->lookahead);
        $this->queryComponents[$aliasIdentificationVariable] = $joinQueryComponent;
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\JoinAssociationDeclaration($joinAssociationPathExpression, $aliasIdentificationVariable, $indexBy);
    }
    /**
     * PartialObjectExpression ::= "PARTIAL" IdentificationVariable "." PartialFieldSet
     * PartialFieldSet ::= "{" SimpleStateField {"," SimpleStateField}* "}"
     *
     * @return array
     */
    public function PartialObjectExpression()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_PARTIAL);
        $partialFieldSet = array();
        $identificationVariable = $this->IdentificationVariable();
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DOT);
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_CURLY_BRACE);
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER);
        $partialFieldSet[] = $this->lexer->token['value'];
        while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA);
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER);
            $field = $this->lexer->token['value'];
            while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DOT)) {
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DOT);
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER);
                $field .= '.' . $this->lexer->token['value'];
            }
            $partialFieldSet[] = $field;
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_CURLY_BRACE);
        $partialObjectExpression = new \MailPoetVendor\Doctrine\ORM\Query\AST\PartialObjectExpression($identificationVariable, $partialFieldSet);
        // Defer PartialObjectExpression validation
        $this->deferredPartialObjectExpressions[] = array('expression' => $partialObjectExpression, 'nestingLevel' => $this->nestingLevel, 'token' => $this->lexer->token);
        return $partialObjectExpression;
    }
    /**
     * NewObjectExpression ::= "NEW" IdentificationVariable "(" NewObjectArg {"," NewObjectArg}* ")"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\NewObjectExpression
     */
    public function NewObjectExpression()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NEW);
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER);
        $token = $this->lexer->token;
        $className = $token['value'];
        if (\strrpos($className, ':') !== \false) {
            list($namespaceAlias, $simpleClassName) = \explode(':', $className);
            $className = $this->em->getConfiguration()->getEntityNamespace($namespaceAlias) . '\\' . $simpleClassName;
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
        $args[] = $this->NewObjectArg();
        while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA);
            $args[] = $this->NewObjectArg();
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
        $expression = new \MailPoetVendor\Doctrine\ORM\Query\AST\NewObjectExpression($className, $args);
        // Defer NewObjectExpression validation
        $this->deferredNewObjectExpressions[] = array('token' => $token, 'expression' => $expression, 'nestingLevel' => $this->nestingLevel);
        return $expression;
    }
    /**
     * NewObjectArg ::= ScalarExpression | "(" Subselect ")"
     *
     * @return mixed
     */
    public function NewObjectArg()
    {
        $token = $this->lexer->lookahead;
        $peek = $this->lexer->glimpse();
        if ($token['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS && $peek['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_SELECT) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
            $expression = $this->Subselect();
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
            return $expression;
        }
        return $this->ScalarExpression();
    }
    /**
     * IndexBy ::= "INDEX" "BY" StateFieldPathExpression
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\IndexBy
     */
    public function IndexBy()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INDEX);
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_BY);
        $pathExpr = $this->StateFieldPathExpression();
        // Add the INDEX BY info to the query component
        $this->queryComponents[$pathExpr->identificationVariable]['map'] = $pathExpr->field;
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\IndexBy($pathExpr);
    }
    /**
     * ScalarExpression ::= SimpleArithmeticExpression | StringPrimary | DateTimePrimary |
     *                      StateFieldPathExpression | BooleanPrimary | CaseExpression |
     *                      InstanceOfExpression
     *
     * @return mixed One of the possible expressions or subexpressions.
     */
    public function ScalarExpression()
    {
        $lookahead = $this->lexer->lookahead['type'];
        $peek = $this->lexer->glimpse();
        switch (\true) {
            case $lookahead === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INTEGER:
            case $lookahead === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_FLOAT:
            // SimpleArithmeticExpression : (- u.value ) or ( + u.value )  or ( - 1 ) or ( + 1 )
            case $lookahead === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MINUS:
            case $lookahead === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_PLUS:
                return $this->SimpleArithmeticExpression();
            case $lookahead === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_STRING:
                return $this->StringPrimary();
            case $lookahead === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_TRUE:
            case $lookahead === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_FALSE:
                $this->match($lookahead);
                return new \MailPoetVendor\Doctrine\ORM\Query\AST\Literal(\MailPoetVendor\Doctrine\ORM\Query\AST\Literal::BOOLEAN, $this->lexer->token['value']);
            case $lookahead === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INPUT_PARAMETER:
                switch (\true) {
                    case $this->isMathOperator($peek):
                        // :param + u.value
                        return $this->SimpleArithmeticExpression();
                    default:
                        return $this->InputParameter();
                }
            case $lookahead === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CASE:
            case $lookahead === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COALESCE:
            case $lookahead === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NULLIF:
                // Since NULLIF and COALESCE can be identified as a function,
                // we need to check these before checking for FunctionDeclaration
                return $this->CaseExpression();
            case $lookahead === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS:
                return $this->SimpleArithmeticExpression();
            // this check must be done before checking for a filed path expression
            case $this->isFunction():
                $this->lexer->peek();
                // "("
                switch (\true) {
                    case $this->isMathOperator($this->peekBeyondClosingParenthesis()):
                        // SUM(u.id) + COUNT(u.id)
                        return $this->SimpleArithmeticExpression();
                    case $this->isAggregateFunction($this->lexer->lookahead['type']):
                        return $this->AggregateExpression();
                    default:
                        // IDENTITY(u)
                        return $this->FunctionDeclaration();
                }
                break;
            // it is no function, so it must be a field path
            case $lookahead === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER:
                $this->lexer->peek();
                // lookahead => '.'
                $this->lexer->peek();
                // lookahead => token after '.'
                $peek = $this->lexer->peek();
                // lookahead => token after the token after the '.'
                $this->lexer->resetPeek();
                if ($this->isMathOperator($peek)) {
                    return $this->SimpleArithmeticExpression();
                }
                return $this->StateFieldPathExpression();
            default:
                $this->syntaxError();
        }
    }
    /**
     * CaseExpression ::= GeneralCaseExpression | SimpleCaseExpression | CoalesceExpression | NullifExpression
     * GeneralCaseExpression ::= "CASE" WhenClause {WhenClause}* "ELSE" ScalarExpression "END"
     * WhenClause ::= "WHEN" ConditionalExpression "THEN" ScalarExpression
     * SimpleCaseExpression ::= "CASE" CaseOperand SimpleWhenClause {SimpleWhenClause}* "ELSE" ScalarExpression "END"
     * CaseOperand ::= StateFieldPathExpression | TypeDiscriminator
     * SimpleWhenClause ::= "WHEN" ScalarExpression "THEN" ScalarExpression
     * CoalesceExpression ::= "COALESCE" "(" ScalarExpression {"," ScalarExpression}* ")"
     * NullifExpression ::= "NULLIF" "(" ScalarExpression "," ScalarExpression ")"
     *
     * @return mixed One of the possible expressions or subexpressions.
     */
    public function CaseExpression()
    {
        $lookahead = $this->lexer->lookahead['type'];
        switch ($lookahead) {
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NULLIF:
                return $this->NullIfExpression();
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COALESCE:
                return $this->CoalesceExpression();
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CASE:
                $this->lexer->resetPeek();
                $peek = $this->lexer->peek();
                if ($peek['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_WHEN) {
                    return $this->GeneralCaseExpression();
                }
                return $this->SimpleCaseExpression();
            default:
                // Do nothing
                break;
        }
        $this->syntaxError();
    }
    /**
     * CoalesceExpression ::= "COALESCE" "(" ScalarExpression {"," ScalarExpression}* ")"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\CoalesceExpression
     */
    public function CoalesceExpression()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COALESCE);
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
        // Process ScalarExpressions (1..N)
        $scalarExpressions = array();
        $scalarExpressions[] = $this->ScalarExpression();
        while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA);
            $scalarExpressions[] = $this->ScalarExpression();
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\CoalesceExpression($scalarExpressions);
    }
    /**
     * NullIfExpression ::= "NULLIF" "(" ScalarExpression "," ScalarExpression ")"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\NullIfExpression
     */
    public function NullIfExpression()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NULLIF);
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
        $firstExpression = $this->ScalarExpression();
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA);
        $secondExpression = $this->ScalarExpression();
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\NullIfExpression($firstExpression, $secondExpression);
    }
    /**
     * GeneralCaseExpression ::= "CASE" WhenClause {WhenClause}* "ELSE" ScalarExpression "END"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\GeneralCaseExpression
     */
    public function GeneralCaseExpression()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CASE);
        // Process WhenClause (1..N)
        $whenClauses = array();
        do {
            $whenClauses[] = $this->WhenClause();
        } while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_WHEN));
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_ELSE);
        $scalarExpression = $this->ScalarExpression();
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_END);
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\GeneralCaseExpression($whenClauses, $scalarExpression);
    }
    /**
     * SimpleCaseExpression ::= "CASE" CaseOperand SimpleWhenClause {SimpleWhenClause}* "ELSE" ScalarExpression "END"
     * CaseOperand ::= StateFieldPathExpression | TypeDiscriminator
     *
     * @return AST\SimpleCaseExpression
     */
    public function SimpleCaseExpression()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CASE);
        $caseOperand = $this->StateFieldPathExpression();
        // Process SimpleWhenClause (1..N)
        $simpleWhenClauses = array();
        do {
            $simpleWhenClauses[] = $this->SimpleWhenClause();
        } while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_WHEN));
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_ELSE);
        $scalarExpression = $this->ScalarExpression();
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_END);
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleCaseExpression($caseOperand, $simpleWhenClauses, $scalarExpression);
    }
    /**
     * WhenClause ::= "WHEN" ConditionalExpression "THEN" ScalarExpression
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\WhenClause
     */
    public function WhenClause()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_WHEN);
        $conditionalExpression = $this->ConditionalExpression();
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_THEN);
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\WhenClause($conditionalExpression, $this->ScalarExpression());
    }
    /**
     * SimpleWhenClause ::= "WHEN" ScalarExpression "THEN" ScalarExpression
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleWhenClause
     */
    public function SimpleWhenClause()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_WHEN);
        $conditionalExpression = $this->ScalarExpression();
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_THEN);
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleWhenClause($conditionalExpression, $this->ScalarExpression());
    }
    /**
     * SelectExpression ::= (
     *     IdentificationVariable | ScalarExpression | AggregateExpression | FunctionDeclaration |
     *     PartialObjectExpression | "(" Subselect ")" | CaseExpression | NewObjectExpression
     * ) [["AS"] ["HIDDEN"] AliasResultVariable]
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\SelectExpression
     */
    public function SelectExpression()
    {
        $expression = null;
        $identVariable = null;
        $peek = $this->lexer->glimpse();
        $lookaheadType = $this->lexer->lookahead['type'];
        switch (\true) {
            // ScalarExpression (u.name)
            case $lookaheadType === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER && $peek['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DOT:
                $expression = $this->ScalarExpression();
                break;
            // IdentificationVariable (u)
            case $lookaheadType === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER && $peek['type'] !== \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS:
                $expression = $identVariable = $this->IdentificationVariable();
                break;
            // CaseExpression (CASE ... or NULLIF(...) or COALESCE(...))
            case $lookaheadType === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CASE:
            case $lookaheadType === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COALESCE:
            case $lookaheadType === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NULLIF:
                $expression = $this->CaseExpression();
                break;
            // DQL Function (SUM(u.value) or SUM(u.value) + 1)
            case $this->isFunction():
                $this->lexer->peek();
                // "("
                switch (\true) {
                    case $this->isMathOperator($this->peekBeyondClosingParenthesis()):
                        // SUM(u.id) + COUNT(u.id)
                        $expression = $this->ScalarExpression();
                        break;
                    case $this->isAggregateFunction($lookaheadType):
                        // COUNT(u.id)
                        $expression = $this->AggregateExpression();
                        break;
                    default:
                        // IDENTITY(u)
                        $expression = $this->FunctionDeclaration();
                        break;
                }
                break;
            // PartialObjectExpression (PARTIAL u.{id, name})
            case $lookaheadType === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_PARTIAL:
                $expression = $this->PartialObjectExpression();
                $identVariable = $expression->identificationVariable;
                break;
            // Subselect
            case $lookaheadType === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS && $peek['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_SELECT:
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
                $expression = $this->Subselect();
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
                break;
            // Shortcut: ScalarExpression => SimpleArithmeticExpression
            case $lookaheadType === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS:
            case $lookaheadType === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INTEGER:
            case $lookaheadType === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_STRING:
            case $lookaheadType === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_FLOAT:
            // SimpleArithmeticExpression : (- u.value ) or ( + u.value )
            case $lookaheadType === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MINUS:
            case $lookaheadType === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_PLUS:
                $expression = $this->SimpleArithmeticExpression();
                break;
            // NewObjectExpression (New ClassName(id, name))
            case $lookaheadType === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NEW:
                $expression = $this->NewObjectExpression();
                break;
            default:
                $this->syntaxError('IdentificationVariable | ScalarExpression | AggregateExpression | FunctionDeclaration | PartialObjectExpression | "(" Subselect ")" | CaseExpression', $this->lexer->lookahead);
        }
        // [["AS"] ["HIDDEN"] AliasResultVariable]
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AS)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AS);
        }
        $hiddenAliasResultVariable = \false;
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_HIDDEN)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_HIDDEN);
            $hiddenAliasResultVariable = \true;
        }
        $aliasResultVariable = null;
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER)) {
            $token = $this->lexer->lookahead;
            $aliasResultVariable = $this->AliasResultVariable();
            // Include AliasResultVariable in query components.
            $this->queryComponents[$aliasResultVariable] = array('resultVariable' => $expression, 'nestingLevel' => $this->nestingLevel, 'token' => $token);
        }
        // AST
        $expr = new \MailPoetVendor\Doctrine\ORM\Query\AST\SelectExpression($expression, $aliasResultVariable, $hiddenAliasResultVariable);
        if ($identVariable) {
            $this->identVariableExpressions[$identVariable] = $expr;
        }
        return $expr;
    }
    /**
     * SimpleSelectExpression ::= (
     *      StateFieldPathExpression | IdentificationVariable | FunctionDeclaration |
     *      AggregateExpression | "(" Subselect ")" | ScalarExpression
     * ) [["AS"] AliasResultVariable]
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleSelectExpression
     */
    public function SimpleSelectExpression()
    {
        $peek = $this->lexer->glimpse();
        switch ($this->lexer->lookahead['type']) {
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER:
                switch (\true) {
                    case $peek['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DOT:
                        $expression = $this->StateFieldPathExpression();
                        return new \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleSelectExpression($expression);
                    case $peek['type'] !== \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS:
                        $expression = $this->IdentificationVariable();
                        return new \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleSelectExpression($expression);
                    case $this->isFunction():
                        // SUM(u.id) + COUNT(u.id)
                        if ($this->isMathOperator($this->peekBeyondClosingParenthesis())) {
                            return new \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleSelectExpression($this->ScalarExpression());
                        }
                        // COUNT(u.id)
                        if ($this->isAggregateFunction($this->lexer->lookahead['type'])) {
                            return new \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleSelectExpression($this->AggregateExpression());
                        }
                        // IDENTITY(u)
                        return new \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleSelectExpression($this->FunctionDeclaration());
                    default:
                }
                break;
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS:
                if ($peek['type'] !== \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_SELECT) {
                    // Shortcut: ScalarExpression => SimpleArithmeticExpression
                    $expression = $this->SimpleArithmeticExpression();
                    return new \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleSelectExpression($expression);
                }
                // Subselect
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
                $expression = $this->Subselect();
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
                return new \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleSelectExpression($expression);
            default:
        }
        $this->lexer->peek();
        $expression = $this->ScalarExpression();
        $expr = new \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleSelectExpression($expression);
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AS)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AS);
        }
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER)) {
            $token = $this->lexer->lookahead;
            $resultVariable = $this->AliasResultVariable();
            $expr->fieldIdentificationVariable = $resultVariable;
            // Include AliasResultVariable in query components.
            $this->queryComponents[$resultVariable] = array('resultvariable' => $expr, 'nestingLevel' => $this->nestingLevel, 'token' => $token);
        }
        return $expr;
    }
    /**
     * ConditionalExpression ::= ConditionalTerm {"OR" ConditionalTerm}*
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\ConditionalExpression
     */
    public function ConditionalExpression()
    {
        $conditionalTerms = array();
        $conditionalTerms[] = $this->ConditionalTerm();
        while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OR)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OR);
            $conditionalTerms[] = $this->ConditionalTerm();
        }
        // Phase 1 AST optimization: Prevent AST\ConditionalExpression
        // if only one AST\ConditionalTerm is defined
        if (\count($conditionalTerms) == 1) {
            return $conditionalTerms[0];
        }
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\ConditionalExpression($conditionalTerms);
    }
    /**
     * ConditionalTerm ::= ConditionalFactor {"AND" ConditionalFactor}*
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\ConditionalTerm
     */
    public function ConditionalTerm()
    {
        $conditionalFactors = array();
        $conditionalFactors[] = $this->ConditionalFactor();
        while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AND)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AND);
            $conditionalFactors[] = $this->ConditionalFactor();
        }
        // Phase 1 AST optimization: Prevent AST\ConditionalTerm
        // if only one AST\ConditionalFactor is defined
        if (\count($conditionalFactors) == 1) {
            return $conditionalFactors[0];
        }
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\ConditionalTerm($conditionalFactors);
    }
    /**
     * ConditionalFactor ::= ["NOT"] ConditionalPrimary
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\ConditionalFactor
     */
    public function ConditionalFactor()
    {
        $not = \false;
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT);
            $not = \true;
        }
        $conditionalPrimary = $this->ConditionalPrimary();
        // Phase 1 AST optimization: Prevent AST\ConditionalFactor
        // if only one AST\ConditionalPrimary is defined
        if (!$not) {
            return $conditionalPrimary;
        }
        $conditionalFactor = new \MailPoetVendor\Doctrine\ORM\Query\AST\ConditionalFactor($conditionalPrimary);
        $conditionalFactor->not = $not;
        return $conditionalFactor;
    }
    /**
     * ConditionalPrimary ::= SimpleConditionalExpression | "(" ConditionalExpression ")"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\ConditionalPrimary
     */
    public function ConditionalPrimary()
    {
        $condPrimary = new \MailPoetVendor\Doctrine\ORM\Query\AST\ConditionalPrimary();
        if (!$this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS)) {
            $condPrimary->simpleConditionalExpression = $this->SimpleConditionalExpression();
            return $condPrimary;
        }
        // Peek beyond the matching closing parenthesis ')'
        $peek = $this->peekBeyondClosingParenthesis();
        if (\in_array($peek['value'], array("=", "<", "<=", "<>", ">", ">=", "!=")) || \in_array($peek['type'], array(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_BETWEEN, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_LIKE, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IN, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IS, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_EXISTS)) || $this->isMathOperator($peek)) {
            $condPrimary->simpleConditionalExpression = $this->SimpleConditionalExpression();
            return $condPrimary;
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
        $condPrimary->conditionalExpression = $this->ConditionalExpression();
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
        return $condPrimary;
    }
    /**
     * SimpleConditionalExpression ::=
     *      ComparisonExpression | BetweenExpression | LikeExpression |
     *      InExpression | NullComparisonExpression | ExistsExpression |
     *      EmptyCollectionComparisonExpression | CollectionMemberExpression |
     *      InstanceOfExpression
     */
    public function SimpleConditionalExpression()
    {
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_EXISTS)) {
            return $this->ExistsExpression();
        }
        $token = $this->lexer->lookahead;
        $peek = $this->lexer->glimpse();
        $lookahead = $token;
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT)) {
            $token = $this->lexer->glimpse();
        }
        if ($token['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER || $token['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INPUT_PARAMETER || $this->isFunction()) {
            // Peek beyond the matching closing parenthesis.
            $beyond = $this->lexer->peek();
            switch ($peek['value']) {
                case '(':
                    // Peeks beyond the matched closing parenthesis.
                    $token = $this->peekBeyondClosingParenthesis(\false);
                    if ($token['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT) {
                        $token = $this->lexer->peek();
                    }
                    if ($token['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IS) {
                        $lookahead = $this->lexer->peek();
                    }
                    break;
                default:
                    // Peek beyond the PathExpression or InputParameter.
                    $token = $beyond;
                    while ($token['value'] === '.') {
                        $this->lexer->peek();
                        $token = $this->lexer->peek();
                    }
                    // Also peek beyond a NOT if there is one.
                    if ($token['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT) {
                        $token = $this->lexer->peek();
                    }
                    // We need to go even further in case of IS (differentiate between NULL and EMPTY)
                    $lookahead = $this->lexer->peek();
            }
            // Also peek beyond a NOT if there is one.
            if ($lookahead['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT) {
                $lookahead = $this->lexer->peek();
            }
            $this->lexer->resetPeek();
        }
        if ($token['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_BETWEEN) {
            return $this->BetweenExpression();
        }
        if ($token['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_LIKE) {
            return $this->LikeExpression();
        }
        if ($token['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IN) {
            return $this->InExpression();
        }
        if ($token['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INSTANCE) {
            return $this->InstanceOfExpression();
        }
        if ($token['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MEMBER) {
            return $this->CollectionMemberExpression();
        }
        if ($token['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IS && $lookahead['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NULL) {
            return $this->NullComparisonExpression();
        }
        if ($token['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IS && $lookahead['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_EMPTY) {
            return $this->EmptyCollectionComparisonExpression();
        }
        return $this->ComparisonExpression();
    }
    /**
     * EmptyCollectionComparisonExpression ::= CollectionValuedPathExpression "IS" ["NOT"] "EMPTY"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\EmptyCollectionComparisonExpression
     */
    public function EmptyCollectionComparisonExpression()
    {
        $emptyCollectionCompExpr = new \MailPoetVendor\Doctrine\ORM\Query\AST\EmptyCollectionComparisonExpression($this->CollectionValuedPathExpression());
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IS);
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT);
            $emptyCollectionCompExpr->not = \true;
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_EMPTY);
        return $emptyCollectionCompExpr;
    }
    /**
     * CollectionMemberExpression ::= EntityExpression ["NOT"] "MEMBER" ["OF"] CollectionValuedPathExpression
     *
     * EntityExpression ::= SingleValuedAssociationPathExpression | SimpleEntityExpression
     * SimpleEntityExpression ::= IdentificationVariable | InputParameter
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\CollectionMemberExpression
     */
    public function CollectionMemberExpression()
    {
        $not = \false;
        $entityExpr = $this->EntityExpression();
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT);
            $not = \true;
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MEMBER);
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OF)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OF);
        }
        $collMemberExpr = new \MailPoetVendor\Doctrine\ORM\Query\AST\CollectionMemberExpression($entityExpr, $this->CollectionValuedPathExpression());
        $collMemberExpr->not = $not;
        return $collMemberExpr;
    }
    /**
     * Literal ::= string | char | integer | float | boolean
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\Literal
     */
    public function Literal()
    {
        switch ($this->lexer->lookahead['type']) {
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_STRING:
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_STRING);
                return new \MailPoetVendor\Doctrine\ORM\Query\AST\Literal(\MailPoetVendor\Doctrine\ORM\Query\AST\Literal::STRING, $this->lexer->token['value']);
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INTEGER:
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_FLOAT:
                $this->match($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INTEGER) ? \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INTEGER : \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_FLOAT);
                return new \MailPoetVendor\Doctrine\ORM\Query\AST\Literal(\MailPoetVendor\Doctrine\ORM\Query\AST\Literal::NUMERIC, $this->lexer->token['value']);
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_TRUE:
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_FALSE:
                $this->match($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_TRUE) ? \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_TRUE : \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_FALSE);
                return new \MailPoetVendor\Doctrine\ORM\Query\AST\Literal(\MailPoetVendor\Doctrine\ORM\Query\AST\Literal::BOOLEAN, $this->lexer->token['value']);
            default:
                $this->syntaxError('Literal');
        }
    }
    /**
     * InParameter ::= Literal | InputParameter
     *
     * @return string | \MailPoetVendor\Doctrine\ORM\Query\AST\InputParameter
     */
    public function InParameter()
    {
        if ($this->lexer->lookahead['type'] == \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INPUT_PARAMETER) {
            return $this->InputParameter();
        }
        return $this->Literal();
    }
    /**
     * InputParameter ::= PositionalParameter | NamedParameter
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\InputParameter
     */
    public function InputParameter()
    {
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INPUT_PARAMETER);
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\InputParameter($this->lexer->token['value']);
    }
    /**
     * ArithmeticExpression ::= SimpleArithmeticExpression | "(" Subselect ")"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\ArithmeticExpression
     */
    public function ArithmeticExpression()
    {
        $expr = new \MailPoetVendor\Doctrine\ORM\Query\AST\ArithmeticExpression();
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS)) {
            $peek = $this->lexer->glimpse();
            if ($peek['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_SELECT) {
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
                $expr->subselect = $this->Subselect();
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
                return $expr;
            }
        }
        $expr->simpleArithmeticExpression = $this->SimpleArithmeticExpression();
        return $expr;
    }
    /**
     * SimpleArithmeticExpression ::= ArithmeticTerm {("+" | "-") ArithmeticTerm}*
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleArithmeticExpression
     */
    public function SimpleArithmeticExpression()
    {
        $terms = array();
        $terms[] = $this->ArithmeticTerm();
        while (($isPlus = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_PLUS)) || $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MINUS)) {
            $this->match($isPlus ? \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_PLUS : \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MINUS);
            $terms[] = $this->lexer->token['value'];
            $terms[] = $this->ArithmeticTerm();
        }
        // Phase 1 AST optimization: Prevent AST\SimpleArithmeticExpression
        // if only one AST\ArithmeticTerm is defined
        if (\count($terms) == 1) {
            return $terms[0];
        }
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleArithmeticExpression($terms);
    }
    /**
     * ArithmeticTerm ::= ArithmeticFactor {("*" | "/") ArithmeticFactor}*
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\ArithmeticTerm
     */
    public function ArithmeticTerm()
    {
        $factors = array();
        $factors[] = $this->ArithmeticFactor();
        while (($isMult = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MULTIPLY)) || $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DIVIDE)) {
            $this->match($isMult ? \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MULTIPLY : \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DIVIDE);
            $factors[] = $this->lexer->token['value'];
            $factors[] = $this->ArithmeticFactor();
        }
        // Phase 1 AST optimization: Prevent AST\ArithmeticTerm
        // if only one AST\ArithmeticFactor is defined
        if (\count($factors) == 1) {
            return $factors[0];
        }
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\ArithmeticTerm($factors);
    }
    /**
     * ArithmeticFactor ::= [("+" | "-")] ArithmeticPrimary
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\ArithmeticFactor
     */
    public function ArithmeticFactor()
    {
        $sign = null;
        if (($isPlus = $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_PLUS)) || $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MINUS)) {
            $this->match($isPlus ? \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_PLUS : \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MINUS);
            $sign = $isPlus;
        }
        $primary = $this->ArithmeticPrimary();
        // Phase 1 AST optimization: Prevent AST\ArithmeticFactor
        // if only one AST\ArithmeticPrimary is defined
        if ($sign === null) {
            return $primary;
        }
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\ArithmeticFactor($primary, $sign);
    }
    /**
     * ArithmeticPrimary ::= SingleValuedPathExpression | Literal | ParenthesisExpression
     *          | FunctionsReturningNumerics | AggregateExpression | FunctionsReturningStrings
     *          | FunctionsReturningDatetime | IdentificationVariable | ResultVariable
     *          | InputParameter | CaseExpression
     */
    public function ArithmeticPrimary()
    {
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
            $expr = $this->SimpleArithmeticExpression();
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
            return new \MailPoetVendor\Doctrine\ORM\Query\AST\ParenthesisExpression($expr);
        }
        switch ($this->lexer->lookahead['type']) {
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COALESCE:
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NULLIF:
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CASE:
                return $this->CaseExpression();
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER:
                $peek = $this->lexer->glimpse();
                if ($peek['value'] == '(') {
                    return $this->FunctionDeclaration();
                }
                if ($peek['value'] == '.') {
                    return $this->SingleValuedPathExpression();
                }
                if (isset($this->queryComponents[$this->lexer->lookahead['value']]['resultVariable'])) {
                    return $this->ResultVariable();
                }
                return $this->StateFieldPathExpression();
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INPUT_PARAMETER:
                return $this->InputParameter();
            default:
                $peek = $this->lexer->glimpse();
                if ($peek['value'] == '(') {
                    if ($this->isAggregateFunction($this->lexer->lookahead['type'])) {
                        return $this->AggregateExpression();
                    }
                    return $this->FunctionDeclaration();
                }
                return $this->Literal();
        }
    }
    /**
     * StringExpression ::= StringPrimary | ResultVariable | "(" Subselect ")"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\StringPrimary |
     *         \MailPoetVendor\Doctrine\ORM\Query\AST\Subselect |
     *         string
     */
    public function StringExpression()
    {
        $peek = $this->lexer->glimpse();
        // Subselect
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS) && $peek['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_SELECT) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
            $expr = $this->Subselect();
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
            return $expr;
        }
        // ResultVariable (string)
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER) && isset($this->queryComponents[$this->lexer->lookahead['value']]['resultVariable'])) {
            return $this->ResultVariable();
        }
        return $this->StringPrimary();
    }
    /**
     * StringPrimary ::= StateFieldPathExpression | string | InputParameter | FunctionsReturningStrings | AggregateExpression | CaseExpression
     */
    public function StringPrimary()
    {
        $lookaheadType = $this->lexer->lookahead['type'];
        switch ($lookaheadType) {
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER:
                $peek = $this->lexer->glimpse();
                if ($peek['value'] == '.') {
                    return $this->StateFieldPathExpression();
                }
                if ($peek['value'] == '(') {
                    // do NOT directly go to FunctionsReturningString() because it doesn't check for custom functions.
                    return $this->FunctionDeclaration();
                }
                $this->syntaxError("'.' or '('");
                break;
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_STRING:
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_STRING);
                return new \MailPoetVendor\Doctrine\ORM\Query\AST\Literal(\MailPoetVendor\Doctrine\ORM\Query\AST\Literal::STRING, $this->lexer->token['value']);
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INPUT_PARAMETER:
                return $this->InputParameter();
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CASE:
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COALESCE:
            case \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NULLIF:
                return $this->CaseExpression();
            default:
                if ($this->isAggregateFunction($lookaheadType)) {
                    return $this->AggregateExpression();
                }
        }
        $this->syntaxError('StateFieldPathExpression | string | InputParameter | FunctionsReturningStrings | AggregateExpression');
    }
    /**
     * EntityExpression ::= SingleValuedAssociationPathExpression | SimpleEntityExpression
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\SingleValuedAssociationPathExpression |
     *         \MailPoetVendor\Doctrine\ORM\Query\AST\SimpleEntityExpression
     */
    public function EntityExpression()
    {
        $glimpse = $this->lexer->glimpse();
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IDENTIFIER) && $glimpse['value'] === '.') {
            return $this->SingleValuedAssociationPathExpression();
        }
        return $this->SimpleEntityExpression();
    }
    /**
     * SimpleEntityExpression ::= IdentificationVariable | InputParameter
     *
     * @return string | \MailPoetVendor\Doctrine\ORM\Query\AST\InputParameter
     */
    public function SimpleEntityExpression()
    {
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INPUT_PARAMETER)) {
            return $this->InputParameter();
        }
        return $this->StateFieldPathExpression();
    }
    /**
     * AggregateExpression ::=
     *  ("AVG" | "MAX" | "MIN" | "SUM" | "COUNT") "(" ["DISTINCT"] SimpleArithmeticExpression ")"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\AggregateExpression
     */
    public function AggregateExpression()
    {
        $lookaheadType = $this->lexer->lookahead['type'];
        $isDistinct = \false;
        if (!\in_array($lookaheadType, array(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COUNT, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AVG, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MAX, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_MIN, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_SUM))) {
            $this->syntaxError('One of: MAX, MIN, AVG, SUM, COUNT');
        }
        $this->match($lookaheadType);
        $functionName = $this->lexer->token['value'];
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DISTINCT)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DISTINCT);
            $isDistinct = \true;
        }
        $pathExp = $this->SimpleArithmeticExpression();
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\AggregateExpression($functionName, $pathExp, $isDistinct);
    }
    /**
     * QuantifiedExpression ::= ("ALL" | "ANY" | "SOME") "(" Subselect ")"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\QuantifiedExpression
     */
    public function QuantifiedExpression()
    {
        $lookaheadType = $this->lexer->lookahead['type'];
        $value = $this->lexer->lookahead['value'];
        if (!\in_array($lookaheadType, array(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_ALL, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_ANY, \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_SOME))) {
            $this->syntaxError('ALL, ANY or SOME');
        }
        $this->match($lookaheadType);
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
        $qExpr = new \MailPoetVendor\Doctrine\ORM\Query\AST\QuantifiedExpression($this->Subselect());
        $qExpr->type = $value;
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
        return $qExpr;
    }
    /**
     * BetweenExpression ::= ArithmeticExpression ["NOT"] "BETWEEN" ArithmeticExpression "AND" ArithmeticExpression
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\BetweenExpression
     */
    public function BetweenExpression()
    {
        $not = \false;
        $arithExpr1 = $this->ArithmeticExpression();
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT);
            $not = \true;
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_BETWEEN);
        $arithExpr2 = $this->ArithmeticExpression();
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_AND);
        $arithExpr3 = $this->ArithmeticExpression();
        $betweenExpr = new \MailPoetVendor\Doctrine\ORM\Query\AST\BetweenExpression($arithExpr1, $arithExpr2, $arithExpr3);
        $betweenExpr->not = $not;
        return $betweenExpr;
    }
    /**
     * ComparisonExpression ::= ArithmeticExpression ComparisonOperator ( QuantifiedExpression | ArithmeticExpression )
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\ComparisonExpression
     */
    public function ComparisonExpression()
    {
        $this->lexer->glimpse();
        $leftExpr = $this->ArithmeticExpression();
        $operator = $this->ComparisonOperator();
        $rightExpr = $this->isNextAllAnySome() ? $this->QuantifiedExpression() : $this->ArithmeticExpression();
        return new \MailPoetVendor\Doctrine\ORM\Query\AST\ComparisonExpression($leftExpr, $operator, $rightExpr);
    }
    /**
     * InExpression ::= SingleValuedPathExpression ["NOT"] "IN" "(" (InParameter {"," InParameter}* | Subselect) ")"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\InExpression
     */
    public function InExpression()
    {
        $inExpression = new \MailPoetVendor\Doctrine\ORM\Query\AST\InExpression($this->ArithmeticExpression());
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT);
            $inExpression->not = \true;
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IN);
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_SELECT)) {
            $inExpression->subselect = $this->Subselect();
        } else {
            $literals = array();
            $literals[] = $this->InParameter();
            while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA)) {
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA);
                $literals[] = $this->InParameter();
            }
            $inExpression->literals = $literals;
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
        return $inExpression;
    }
    /**
     * InstanceOfExpression ::= IdentificationVariable ["NOT"] "INSTANCE" ["OF"] (InstanceOfParameter | "(" InstanceOfParameter {"," InstanceOfParameter}* ")")
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\InstanceOfExpression
     */
    public function InstanceOfExpression()
    {
        $instanceOfExpression = new \MailPoetVendor\Doctrine\ORM\Query\AST\InstanceOfExpression($this->IdentificationVariable());
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT);
            $instanceOfExpression->not = \true;
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INSTANCE);
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OF);
        $exprValues = array();
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
            $exprValues[] = $this->InstanceOfParameter();
            while ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA)) {
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COMMA);
                $exprValues[] = $this->InstanceOfParameter();
            }
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
            $instanceOfExpression->value = $exprValues;
            return $instanceOfExpression;
        }
        $exprValues[] = $this->InstanceOfParameter();
        $instanceOfExpression->value = $exprValues;
        return $instanceOfExpression;
    }
    /**
     * InstanceOfParameter ::= AbstractSchemaName | InputParameter
     *
     * @return mixed
     */
    public function InstanceOfParameter()
    {
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INPUT_PARAMETER)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INPUT_PARAMETER);
            return new \MailPoetVendor\Doctrine\ORM\Query\AST\InputParameter($this->lexer->token['value']);
        }
        return $this->AliasIdentificationVariable();
    }
    /**
     * LikeExpression ::= StringExpression ["NOT"] "LIKE" StringPrimary ["ESCAPE" char]
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\LikeExpression
     */
    public function LikeExpression()
    {
        $stringExpr = $this->StringExpression();
        $not = \false;
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT);
            $not = \true;
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_LIKE);
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INPUT_PARAMETER)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INPUT_PARAMETER);
            $stringPattern = new \MailPoetVendor\Doctrine\ORM\Query\AST\InputParameter($this->lexer->token['value']);
        } else {
            $stringPattern = $this->StringPrimary();
        }
        $escapeChar = null;
        if ($this->lexer->lookahead['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_ESCAPE) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_ESCAPE);
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_STRING);
            $escapeChar = new \MailPoetVendor\Doctrine\ORM\Query\AST\Literal(\MailPoetVendor\Doctrine\ORM\Query\AST\Literal::STRING, $this->lexer->token['value']);
        }
        $likeExpr = new \MailPoetVendor\Doctrine\ORM\Query\AST\LikeExpression($stringExpr, $stringPattern, $escapeChar);
        $likeExpr->not = $not;
        return $likeExpr;
    }
    /**
     * NullComparisonExpression ::= (InputParameter | NullIfExpression | CoalesceExpression | AggregateExpression | FunctionDeclaration | IdentificationVariable | SingleValuedPathExpression | ResultVariable) "IS" ["NOT"] "NULL"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\NullComparisonExpression
     */
    public function NullComparisonExpression()
    {
        switch (\true) {
            case $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INPUT_PARAMETER):
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_INPUT_PARAMETER);
                $expr = new \MailPoetVendor\Doctrine\ORM\Query\AST\InputParameter($this->lexer->token['value']);
                break;
            case $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NULLIF):
                $expr = $this->NullIfExpression();
                break;
            case $this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_COALESCE):
                $expr = $this->CoalesceExpression();
                break;
            case $this->isAggregateFunction($this->lexer->lookahead['type']):
                $expr = $this->AggregateExpression();
                break;
            case $this->isFunction():
                $expr = $this->FunctionDeclaration();
                break;
            default:
                // We need to check if we are in a IdentificationVariable or SingleValuedPathExpression
                $glimpse = $this->lexer->glimpse();
                if ($glimpse['type'] === \MailPoetVendor\Doctrine\ORM\Query\Lexer::T_DOT) {
                    $expr = $this->SingleValuedPathExpression();
                    // Leave switch statement
                    break;
                }
                $lookaheadValue = $this->lexer->lookahead['value'];
                // Validate existing component
                if (!isset($this->queryComponents[$lookaheadValue])) {
                    $this->semanticalError('Cannot add having condition on undefined result variable.');
                }
                // Validating ResultVariable
                if (!isset($this->queryComponents[$lookaheadValue]['resultVariable'])) {
                    $this->semanticalError('Cannot add having condition on a non result variable.');
                }
                $expr = $this->ResultVariable();
                break;
        }
        $nullCompExpr = new \MailPoetVendor\Doctrine\ORM\Query\AST\NullComparisonExpression($expr);
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_IS);
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT);
            $nullCompExpr->not = \true;
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NULL);
        return $nullCompExpr;
    }
    /**
     * ExistsExpression ::= ["NOT"] "EXISTS" "(" Subselect ")"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\ExistsExpression
     */
    public function ExistsExpression()
    {
        $not = \false;
        if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT)) {
            $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NOT);
            $not = \true;
        }
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_EXISTS);
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
        $existsExpression = new \MailPoetVendor\Doctrine\ORM\Query\AST\ExistsExpression($this->Subselect());
        $existsExpression->not = $not;
        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
        return $existsExpression;
    }
    /**
     * ComparisonOperator ::= "=" | "<" | "<=" | "<>" | ">" | ">=" | "!="
     *
     * @return string
     */
    public function ComparisonOperator()
    {
        switch ($this->lexer->lookahead['value']) {
            case '=':
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_EQUALS);
                return '=';
            case '<':
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_LOWER_THAN);
                $operator = '<';
                if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_EQUALS)) {
                    $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_EQUALS);
                    $operator .= '=';
                } else {
                    if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_GREATER_THAN)) {
                        $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_GREATER_THAN);
                        $operator .= '>';
                    }
                }
                return $operator;
            case '>':
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_GREATER_THAN);
                $operator = '>';
                if ($this->lexer->isNextToken(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_EQUALS)) {
                    $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_EQUALS);
                    $operator .= '=';
                }
                return $operator;
            case '!':
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_NEGATE);
                $this->match(\MailPoetVendor\Doctrine\ORM\Query\Lexer::T_EQUALS);
                return '<>';
            default:
                $this->syntaxError('=, <, <=, <>, >, >=, !=');
        }
    }
    /**
     * FunctionDeclaration ::= FunctionsReturningStrings | FunctionsReturningNumerics | FunctionsReturningDatetime
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\Functions\FunctionNode
     */
    public function FunctionDeclaration()
    {
        $token = $this->lexer->lookahead;
        $funcName = \strtolower($token['value']);
        // Check for built-in functions first!
        switch (\true) {
            case isset(self::$_STRING_FUNCTIONS[$funcName]):
                return $this->FunctionsReturningStrings();
            case isset(self::$_NUMERIC_FUNCTIONS[$funcName]):
                return $this->FunctionsReturningNumerics();
            case isset(self::$_DATETIME_FUNCTIONS[$funcName]):
                return $this->FunctionsReturningDatetime();
            default:
                return $this->CustomFunctionDeclaration();
        }
    }
    /**
     * Helper function for FunctionDeclaration grammar rule.
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\Functions\FunctionNode
     */
    private function CustomFunctionDeclaration()
    {
        $token = $this->lexer->lookahead;
        $funcName = \strtolower($token['value']);
        // Check for custom functions afterwards
        $config = $this->em->getConfiguration();
        switch (\true) {
            case $config->getCustomStringFunction($funcName) !== null:
                return $this->CustomFunctionsReturningStrings();
            case $config->getCustomNumericFunction($funcName) !== null:
                return $this->CustomFunctionsReturningNumerics();
            case $config->getCustomDatetimeFunction($funcName) !== null:
                return $this->CustomFunctionsReturningDatetime();
            default:
                $this->syntaxError('known function', $token);
        }
    }
    /**
     * FunctionsReturningNumerics ::=
     *      "LENGTH" "(" StringPrimary ")" |
     *      "LOCATE" "(" StringPrimary "," StringPrimary ["," SimpleArithmeticExpression]")" |
     *      "ABS" "(" SimpleArithmeticExpression ")" |
     *      "SQRT" "(" SimpleArithmeticExpression ")" |
     *      "MOD" "(" SimpleArithmeticExpression "," SimpleArithmeticExpression ")" |
     *      "SIZE" "(" CollectionValuedPathExpression ")" |
     *      "DATE_DIFF" "(" ArithmeticPrimary "," ArithmeticPrimary ")" |
     *      "BIT_AND" "(" ArithmeticPrimary "," ArithmeticPrimary ")" |
     *      "BIT_OR" "(" ArithmeticPrimary "," ArithmeticPrimary ")"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\Functions\FunctionNode
     */
    public function FunctionsReturningNumerics()
    {
        $funcNameLower = \strtolower($this->lexer->lookahead['value']);
        $funcClass = self::$_NUMERIC_FUNCTIONS[$funcNameLower];
        $function = new $funcClass($funcNameLower);
        $function->parse($this);
        return $function;
    }
    /**
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\Functions\FunctionNode
     */
    public function CustomFunctionsReturningNumerics()
    {
        // getCustomNumericFunction is case-insensitive
        $functionName = \strtolower($this->lexer->lookahead['value']);
        $functionClass = $this->em->getConfiguration()->getCustomNumericFunction($functionName);
        $function = \is_string($functionClass) ? new $functionClass($functionName) : \call_user_func($functionClass, $functionName);
        $function->parse($this);
        return $function;
    }
    /**
     * FunctionsReturningDateTime ::=
     *     "CURRENT_DATE" |
     *     "CURRENT_TIME" |
     *     "CURRENT_TIMESTAMP" |
     *     "DATE_ADD" "(" ArithmeticPrimary "," ArithmeticPrimary "," StringPrimary ")" |
     *     "DATE_SUB" "(" ArithmeticPrimary "," ArithmeticPrimary "," StringPrimary ")"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\Functions\FunctionNode
     */
    public function FunctionsReturningDatetime()
    {
        $funcNameLower = \strtolower($this->lexer->lookahead['value']);
        $funcClass = self::$_DATETIME_FUNCTIONS[$funcNameLower];
        $function = new $funcClass($funcNameLower);
        $function->parse($this);
        return $function;
    }
    /**
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\Functions\FunctionNode
     */
    public function CustomFunctionsReturningDatetime()
    {
        // getCustomDatetimeFunction is case-insensitive
        $functionName = $this->lexer->lookahead['value'];
        $functionClass = $this->em->getConfiguration()->getCustomDatetimeFunction($functionName);
        $function = \is_string($functionClass) ? new $functionClass($functionName) : \call_user_func($functionClass, $functionName);
        $function->parse($this);
        return $function;
    }
    /**
     * FunctionsReturningStrings ::=
     *   "CONCAT" "(" StringPrimary "," StringPrimary ")" |
     *   "SUBSTRING" "(" StringPrimary "," SimpleArithmeticExpression "," SimpleArithmeticExpression ")" |
     *   "TRIM" "(" [["LEADING" | "TRAILING" | "BOTH"] [char] "FROM"] StringPrimary ")" |
     *   "LOWER" "(" StringPrimary ")" |
     *   "UPPER" "(" StringPrimary ")" |
     *   "IDENTITY" "(" SingleValuedAssociationPathExpression {"," string} ")"
     *
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\Functions\FunctionNode
     */
    public function FunctionsReturningStrings()
    {
        $funcNameLower = \strtolower($this->lexer->lookahead['value']);
        $funcClass = self::$_STRING_FUNCTIONS[$funcNameLower];
        $function = new $funcClass($funcNameLower);
        $function->parse($this);
        return $function;
    }
    /**
     * @return \MailPoetVendor\Doctrine\ORM\Query\AST\Functions\FunctionNode
     */
    public function CustomFunctionsReturningStrings()
    {
        // getCustomStringFunction is case-insensitive
        $functionName = $this->lexer->lookahead['value'];
        $functionClass = $this->em->getConfiguration()->getCustomStringFunction($functionName);
        $function = \is_string($functionClass) ? new $functionClass($functionName) : \call_user_func($functionClass, $functionName);
        $function->parse($this);
        return $function;
    }
}
