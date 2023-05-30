<?php declare(strict_types = 1);

namespace SlevomatCodingStandard\Helpers\Annotation;

use InvalidArgumentException;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasImportTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use SlevomatCodingStandard\Helpers\AnnotationHelper;
use function in_array;
use function sprintf;

/**
 * @internal
 */
class TypeImportAnnotation extends Annotation
{

	/** @var TypeAliasImportTagValueNode|null */
	private $contentNode;

	public function __construct(
		string $name,
		int $startPointer,
		int $endPointer,
		?string $content,
		?TypeAliasImportTagValueNode $contentNode
	)
	{
		if (!in_array(
			$name,
			['@psalm-import-type', '@phpstan-import-type'],
			true
		)) {
			throw new InvalidArgumentException(sprintf('Unsupported annotation %s.', $name));
		}

		parent::__construct($name, $startPointer, $endPointer, $content);

		$this->contentNode = $contentNode;
	}

	public function isInvalid(): bool
	{
		return $this->contentNode === null;
	}

	public function getContentNode(): TypeAliasImportTagValueNode
	{
		$this->errorWhenInvalid();

		return $this->contentNode;
	}

	public function getAlias(): string
	{
		return $this->getImportedAs() ?? $this->getImportedAlias();
	}

	public function getImportedAlias(): string
	{
		$this->errorWhenInvalid();

		return $this->contentNode->importedAlias;
	}

	/**
	 * @return IdentifierTypeNode
	 */
	public function getImportedFrom(): TypeNode
	{
		$this->errorWhenInvalid();

		return $this->contentNode->importedFrom;
	}

	public function getImportedAs(): ?string
	{
		$this->errorWhenInvalid();

		return $this->contentNode->importedAs;
	}

	public function print(): string
	{
		return sprintf('%s %s', $this->name, AnnotationHelper::getPhpDocPrinter()->print($this->contentNode));
	}

}