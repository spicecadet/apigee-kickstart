<?php declare(strict_types = 1);

namespace SlevomatCodingStandard\Helpers\Annotation;

use InvalidArgumentException;
use PHPStan\PhpDocParser\Ast\PhpDoc\MethodTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\MethodTagValueParameterNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ThisTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use SlevomatCodingStandard\Helpers\AnnotationHelper;
use function in_array;
use function sprintf;

/**
 * @internal
 */
class MethodAnnotation extends Annotation
{

	/** @var MethodTagValueNode|null */
	private $contentNode;

	public function __construct(string $name, int $startPointer, int $endPointer, ?string $content, ?MethodTagValueNode $contentNode)
	{
		if (!in_array($name, ['@method', '@psalm-method', '@phpstan-method'], true)) {
			throw new InvalidArgumentException(sprintf('Unsupported annotation %s.', $name));
		}

		parent::__construct($name, $startPointer, $endPointer, $content);

		$this->contentNode = $contentNode;
	}

	public function isInvalid(): bool
	{
		return $this->contentNode === null;
	}

	public function getContentNode(): MethodTagValueNode
	{
		$this->errorWhenInvalid();

		return $this->contentNode;
	}

	public function hasDescription(): bool
	{
		return $this->getDescription() !== null;
	}

	public function getDescription(): ?string
	{
		$this->errorWhenInvalid();

		return $this->contentNode->description !== '' ? $this->contentNode->description : null;
	}

	public function getMethodName(): ?string
	{
		$this->errorWhenInvalid();

		return $this->contentNode->methodName !== '' ? $this->contentNode->methodName : null;
	}

	/**
	 * @return GenericTypeNode|CallableTypeNode|IntersectionTypeNode|UnionTypeNode|ArrayTypeNode|IdentifierTypeNode|ThisTypeNode
	 */
	public function getMethodReturnType(): ?TypeNode
	{
		$this->errorWhenInvalid();

		/** @var GenericTypeNode|CallableTypeNode|IntersectionTypeNode|UnionTypeNode|ArrayTypeNode|IdentifierTypeNode|ThisTypeNode $type */
		$type = $this->contentNode->returnType;
		return $type;
	}

	/**
	 * @return list<TemplateTagValueNode>
	 */
	public function getMethodTemplateTypes(): array
	{
		$this->errorWhenInvalid();

		return $this->contentNode->templateTypes;
	}

	/**
	 * @return list<MethodTagValueParameterNode>
	 */
	public function getMethodParameters(): array
	{
		$this->errorWhenInvalid();

		return $this->contentNode->parameters;
	}

	public function print(): string
	{
		return sprintf('%s %s', $this->name, AnnotationHelper::getPhpDocPrinter()->print($this->contentNode));
	}

}
