<?php declare(strict_types = 1);

namespace SlevomatCodingStandard\Helpers\Annotation;

use InvalidArgumentException;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypelessParamTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ThisTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use SlevomatCodingStandard\Helpers\AnnotationHelper;
use function in_array;
use function sprintf;

/**
 * @internal
 */
class ParameterAnnotation extends Annotation
{

	/** @var ParamTagValueNode|TypelessParamTagValueNode|null */
	private $contentNode;

	/**
	 * @param ParamTagValueNode|TypelessParamTagValueNode|null $contentNode
	 */
	public function __construct(string $name, int $startPointer, int $endPointer, ?string $content, $contentNode)
	{
		if (!in_array($name, ['@param', '@psalm-param', '@phpstan-param'], true)) {
			throw new InvalidArgumentException(sprintf('Unsupported annotation %s.', $name));
		}

		parent::__construct($name, $startPointer, $endPointer, $content);

		$this->contentNode = $contentNode;
	}

	public function isInvalid(): bool
	{
		return $this->contentNode === null;
	}

	/**
	 * @return ParamTagValueNode|TypelessParamTagValueNode|null
	 */
	public function getContentNode()
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

	public function getParameterName(): string
	{
		$this->errorWhenInvalid();

		return $this->contentNode->parameterName;
	}

	/**
	 * @return GenericTypeNode|CallableTypeNode|IntersectionTypeNode|UnionTypeNode|ArrayTypeNode|ArrayShapeNode|ObjectShapeNode|IdentifierTypeNode|ThisTypeNode|NullableTypeNode|ConstTypeNode|null
	 */
	public function getType(): ?TypeNode
	{
		$this->errorWhenInvalid();

		if ($this->contentNode instanceof TypelessParamTagValueNode) {
			return null;
		}

		/** @var GenericTypeNode|CallableTypeNode|IntersectionTypeNode|UnionTypeNode|ArrayTypeNode|ArrayShapeNode|ObjectShapeNode|IdentifierTypeNode|ThisTypeNode|NullableTypeNode|ConstTypeNode $type */
		$type = $this->contentNode->type;
		return $type;
	}

	public function print(): string
	{
		return sprintf('%s %s', $this->name, AnnotationHelper::getPhpDocPrinter()->print($this->contentNode));
	}

}
