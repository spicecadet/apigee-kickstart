<?php declare(strict_types = 1);

namespace SlevomatCodingStandard\Helpers\Annotation;

use InvalidArgumentException;
use PHPStan\PhpDocParser\Ast\PhpDoc\UsesTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use SlevomatCodingStandard\Helpers\AnnotationHelper;
use function in_array;
use function sprintf;

/**
 * @internal
 */
class UseAnnotation extends Annotation
{

	/** @var UsesTagValueNode|null */
	private $contentNode;

	public function __construct(string $name, int $startPointer, int $endPointer, ?string $content, ?UsesTagValueNode $contentNode)
	{
		if (!in_array($name, ['@use', '@template-use', '@phpstan-use'], true)) {
			throw new InvalidArgumentException(sprintf('Unsupported annotation %s.', $name));
		}

		parent::__construct($name, $startPointer, $endPointer, $content);

		$this->contentNode = $contentNode;
	}

	public function isInvalid(): bool
	{
		return $this->contentNode === null;
	}

	public function getContentNode(): UsesTagValueNode
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

	public function getType(): GenericTypeNode
	{
		$this->errorWhenInvalid();

		return $this->contentNode->type;
	}

	public function print(): string
	{
		return sprintf('%s %s', $this->name, AnnotationHelper::getPhpDocPrinter()->print($this->contentNode));
	}

}
