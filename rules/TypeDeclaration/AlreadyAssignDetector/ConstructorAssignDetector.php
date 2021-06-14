<?php

declare (strict_types=1);
namespace Rector\TypeDeclaration\AlreadyAssignDetector;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeTraverser;
use PHPStan\Type\ObjectType;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\Core\ValueObject\MethodName;
use Rector\NodeTypeResolver\NodeTypeResolver;
use Rector\TypeDeclaration\Matcher\PropertyAssignMatcher;
use RectorPrefix20210614\Symplify\Astral\NodeTraverser\SimpleCallableNodeTraverser;
final class ConstructorAssignDetector
{
    /**
     * @var string
     */
    private const IS_FIRST_LEVEL_STATEMENT = 'first_level_stmt';
    /**
     * @var \Rector\NodeTypeResolver\NodeTypeResolver
     */
    private $nodeTypeResolver;
    /**
     * @var \Rector\TypeDeclaration\Matcher\PropertyAssignMatcher
     */
    private $propertyAssignMatcher;
    /**
     * @var \Symplify\Astral\NodeTraverser\SimpleCallableNodeTraverser
     */
    private $simpleCallableNodeTraverser;
    /**
     * @var \Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory
     */
    private $phpDocInfoFactory;
    public function __construct(\Rector\NodeTypeResolver\NodeTypeResolver $nodeTypeResolver, \Rector\TypeDeclaration\Matcher\PropertyAssignMatcher $propertyAssignMatcher, \RectorPrefix20210614\Symplify\Astral\NodeTraverser\SimpleCallableNodeTraverser $simpleCallableNodeTraverser, \Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory $phpDocInfoFactory)
    {
        $this->nodeTypeResolver = $nodeTypeResolver;
        $this->propertyAssignMatcher = $propertyAssignMatcher;
        $this->simpleCallableNodeTraverser = $simpleCallableNodeTraverser;
        $this->phpDocInfoFactory = $phpDocInfoFactory;
    }
    public function isPropertyAssigned(\PhpParser\Node\Stmt\ClassLike $classLike, string $propertyName) : bool
    {
        $initializeClassMethods = $this->matchInitializeClassMethod($classLike);
        if ($initializeClassMethods === []) {
            return \false;
        }
        $isAssignedInConstructor = \false;
        $this->decorateFirstLevelStatementAttribute($initializeClassMethods);
        foreach ($initializeClassMethods as $initializeClassMethod) {
            $this->simpleCallableNodeTraverser->traverseNodesWithCallable((array) $initializeClassMethod->stmts, function (\PhpParser\Node $node) use($propertyName, &$isAssignedInConstructor) : ?int {
                $expr = $this->matchAssignExprToPropertyName($node, $propertyName);
                if (!$expr instanceof \PhpParser\Node\Expr) {
                    return null;
                }
                /** @var Assign $assign */
                $assign = $node;
                $isFirstLevelStatement = $assign->getAttribute(self::IS_FIRST_LEVEL_STATEMENT);
                // cannot be nested
                if ($isFirstLevelStatement !== \true) {
                    return null;
                }
                $isAssignedInConstructor = \true;
                return \PhpParser\NodeTraverser::DONT_TRAVERSE_CURRENT_AND_CHILDREN;
            });
        }
        return $isAssignedInConstructor;
    }
    private function matchAssignExprToPropertyName(\PhpParser\Node $node, string $propertyName) : ?\PhpParser\Node\Expr
    {
        if (!$node instanceof \PhpParser\Node\Expr\Assign) {
            return null;
        }
        return $this->propertyAssignMatcher->matchPropertyAssignExpr($node, $propertyName);
    }
    /**
     * @param ClassMethod[] $classMethods
     */
    private function decorateFirstLevelStatementAttribute(array $classMethods) : void
    {
        foreach ($classMethods as $classMethod) {
            foreach ((array) $classMethod->stmts as $methodStmt) {
                $methodStmt->setAttribute(self::IS_FIRST_LEVEL_STATEMENT, \true);
                if ($methodStmt instanceof \PhpParser\Node\Stmt\Expression) {
                    $methodStmt->expr->setAttribute(self::IS_FIRST_LEVEL_STATEMENT, \true);
                }
            }
        }
    }
    /**
     * @return ClassMethod[]
     */
    private function matchInitializeClassMethod(\PhpParser\Node\Stmt\ClassLike $classLike) : array
    {
        $initializingClassMethods = [];
        $constructClassMethod = $classLike->getMethod(\Rector\Core\ValueObject\MethodName::CONSTRUCT);
        if ($constructClassMethod instanceof \PhpParser\Node\Stmt\ClassMethod) {
            $initializingClassMethods[] = $constructClassMethod;
        }
        $testCaseObjectType = new \PHPStan\Type\ObjectType('PHPUnit\\Framework\\TestCase');
        if ($this->nodeTypeResolver->isObjectType($classLike, $testCaseObjectType)) {
            $setUpClassMethod = $classLike->getMethod(\Rector\Core\ValueObject\MethodName::SET_UP);
            if ($setUpClassMethod instanceof \PhpParser\Node\Stmt\ClassMethod) {
                $initializingClassMethods[] = $setUpClassMethod;
            }
            $setUpBeforeClassMethod = $classLike->getMethod(\Rector\Core\ValueObject\MethodName::SET_UP_BEFORE_CLASS);
            if ($setUpBeforeClassMethod instanceof \PhpParser\Node\Stmt\ClassMethod) {
                $initializingClassMethods[] = $setUpBeforeClassMethod;
            }
        }
        foreach ($classLike->getMethods() as $classMethod) {
            $classMethodPhpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($classMethod);
            // @todo add support for PHP 8 attributes
            if (!$classMethodPhpDocInfo->hasByNames(['required', 'inject'])) {
                continue;
            }
            $initializingClassMethods[] = $classMethod;
        }
        return $initializingClassMethods;
    }
}
