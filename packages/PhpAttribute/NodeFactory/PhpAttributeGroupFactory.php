<?php

declare (strict_types=1);
namespace Rector\PhpAttribute\NodeFactory;

use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Use_;
use Rector\BetterPhpDocParser\PhpDoc\DoctrineAnnotationTagValueNode;
use Rector\Php80\ValueObject\AnnotationToAttribute;
use Rector\PhpAttribute\AnnotationToAttributeMapper;
use Rector\PhpAttribute\AttributeArrayNameInliner;
use Rector\PhpAttribute\NodeAnalyzer\ExprParameterReflectionTypeCorrector;
/**
 * @see \Rector\Tests\PhpAttribute\Printer\PhpAttributeGroupFactoryTest
 */
final class PhpAttributeGroupFactory
{
    /**
     * @readonly
     * @var \Rector\PhpAttribute\AnnotationToAttributeMapper
     */
    private $annotationToAttributeMapper;
    /**
     * @readonly
     * @var \Rector\PhpAttribute\NodeFactory\AttributeNameFactory
     */
    private $attributeNameFactory;
    /**
     * @readonly
     * @var \Rector\PhpAttribute\NodeFactory\NamedArgsFactory
     */
    private $namedArgsFactory;
    /**
     * @readonly
     * @var \Rector\PhpAttribute\NodeAnalyzer\ExprParameterReflectionTypeCorrector
     */
    private $exprParameterReflectionTypeCorrector;
    /**
     * @readonly
     * @var \Rector\PhpAttribute\AttributeArrayNameInliner
     */
    private $attributeArrayNameInliner;
    public function __construct(AnnotationToAttributeMapper $annotationToAttributeMapper, \Rector\PhpAttribute\NodeFactory\AttributeNameFactory $attributeNameFactory, \Rector\PhpAttribute\NodeFactory\NamedArgsFactory $namedArgsFactory, ExprParameterReflectionTypeCorrector $exprParameterReflectionTypeCorrector, AttributeArrayNameInliner $attributeArrayNameInliner)
    {
        $this->annotationToAttributeMapper = $annotationToAttributeMapper;
        $this->attributeNameFactory = $attributeNameFactory;
        $this->namedArgsFactory = $namedArgsFactory;
        $this->exprParameterReflectionTypeCorrector = $exprParameterReflectionTypeCorrector;
        $this->attributeArrayNameInliner = $attributeArrayNameInliner;
    }
    public function createFromSimpleTag(AnnotationToAttribute $annotationToAttribute) : AttributeGroup
    {
        return $this->createFromClass($annotationToAttribute->getAttributeClass());
    }
    public function createFromClass(string $attributeClass) : AttributeGroup
    {
        $fullyQualified = new FullyQualified($attributeClass);
        $attribute = new Attribute($fullyQualified);
        return new AttributeGroup([$attribute]);
    }
    /**
     * @param mixed[] $items
     */
    public function createFromClassWithItems(string $attributeClass, array $items) : AttributeGroup
    {
        $fullyQualified = new FullyQualified($attributeClass);
        $args = $this->createArgsFromItems($items, $attributeClass);
        $attribute = new Attribute($fullyQualified, $args);
        return new AttributeGroup([$attribute]);
    }
    /**
     * @param Use_[] $uses
     */
    public function create(DoctrineAnnotationTagValueNode $doctrineAnnotationTagValueNode, AnnotationToAttribute $annotationToAttribute, array $uses) : AttributeGroup
    {
        $values = $doctrineAnnotationTagValueNode->getValuesWithExplicitSilentAndWithoutQuotes();
        $args = $this->createArgsFromItems($values, $annotationToAttribute->getAttributeClass());
        $args = $this->attributeArrayNameInliner->inlineArrayToArgs($args);
        $attributeName = $this->attributeNameFactory->create($annotationToAttribute, $doctrineAnnotationTagValueNode, $uses);
        $attribute = new Attribute($attributeName, $args);
        return new AttributeGroup([$attribute]);
    }
    /**
     * @param mixed[] $items
     * @return Arg[]
     */
    public function createArgsFromItems(array $items, string $attributeClass) : array
    {
        /** @var Expr[]|Expr\Array_ $items */
        $items = $this->annotationToAttributeMapper->map($items);
        $items = $this->exprParameterReflectionTypeCorrector->correctItemsByAttributeClass($items, $attributeClass);
        return $this->namedArgsFactory->createFromValues($items);
    }
}
