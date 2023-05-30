<?php declare(strict_types = 1);

namespace SlevomatCodingStandard\Sniffs\Namespaces;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use SlevomatCodingStandard\Helpers\Annotation\GenericAnnotation;
use SlevomatCodingStandard\Helpers\Annotation\MethodAnnotation;
use SlevomatCodingStandard\Helpers\Annotation\ParameterAnnotation;
use SlevomatCodingStandard\Helpers\Annotation\PropertyAnnotation;
use SlevomatCodingStandard\Helpers\Annotation\ReturnAnnotation;
use SlevomatCodingStandard\Helpers\Annotation\ThrowsAnnotation;
use SlevomatCodingStandard\Helpers\Annotation\VariableAnnotation;
use SlevomatCodingStandard\Helpers\AnnotationConstantExpressionHelper;
use SlevomatCodingStandard\Helpers\AnnotationHelper;
use SlevomatCodingStandard\Helpers\AnnotationTypeHelper;
use SlevomatCodingStandard\Helpers\FixerHelper;
use SlevomatCodingStandard\Helpers\NamespaceHelper;
use SlevomatCodingStandard\Helpers\ReferencedName;
use SlevomatCodingStandard\Helpers\ReferencedNameHelper;
use SlevomatCodingStandard\Helpers\SniffSettingsHelper;
use SlevomatCodingStandard\Helpers\TokenHelper;
use SlevomatCodingStandard\Helpers\TypeHelper;
use SlevomatCodingStandard\Helpers\TypeHintHelper;
use SlevomatCodingStandard\Helpers\UseStatement;
use SlevomatCodingStandard\Helpers\UseStatementHelper;
use function array_diff_key;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_reverse;
use function count;
use function in_array;
use function preg_match;
use function preg_quote;
use function preg_split;
use function sprintf;
use const T_DOC_COMMENT_OPEN_TAG;
use const T_NAMESPACE;
use const T_OPEN_TAG;
use const T_SEMICOLON;

class UnusedUsesSniff implements Sniff
{

	public const CODE_UNUSED_USE = 'UnusedUse';

	/** @var bool */
	public $searchAnnotations = false;

	/** @var list<string> */
	public $ignoredAnnotationNames = [];

	/** @var list<string> */
	public $ignoredAnnotations = [];

	/** @var list<string>|null */
	private $normalizedIgnoredAnnotationNames;

	/** @var list<string>|null */
	private $normalizedIgnoredAnnotations;

	/**
	 * @return array<int, (int|string)>
	 */
	public function register(): array
	{
		return [
			T_OPEN_TAG,
		];
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
	 * @param int $openTagPointer
	 */
	public function process(File $phpcsFile, $openTagPointer): void
	{
		if (TokenHelper::findPrevious($phpcsFile, T_OPEN_TAG, $openTagPointer - 1) !== null) {
			return;
		}

		$startPointer = TokenHelper::findPrevious($phpcsFile, T_NAMESPACE, $openTagPointer - 1) ?? $openTagPointer;

		$fileUnusedNames = UseStatementHelper::getFileUseStatements($phpcsFile);
		$referencedNamesInCode = ReferencedNameHelper::getAllReferencedNames($phpcsFile, $startPointer);
		$referencedNamesInAttributes = ReferencedNameHelper::getAllReferencedNamesInAttributes($phpcsFile, $startPointer);

		$pointersBeforeUseStatements = array_reverse(NamespaceHelper::getAllNamespacesPointers($phpcsFile));

		$allUsedNames = [];

		foreach ([$referencedNamesInCode, $referencedNamesInAttributes] as $referencedNames) {
			foreach ($referencedNames as $referencedName) {
				$pointer = $referencedName->getStartPointer();

				$pointerBeforeUseStatements = $this->firstPointerBefore($pointer, $pointersBeforeUseStatements, $startPointer);

				$name = $referencedName->getNameAsReferencedInFile();
				$nameParts = NamespaceHelper::getNameParts($name);
				$nameAsReferencedInFile = $nameParts[0];
				$nameReferencedWithoutSubNamespace = count($nameParts) === 1;
				$uniqueId = $nameReferencedWithoutSubNamespace
					? UseStatement::getUniqueId($referencedName->getType(), $nameAsReferencedInFile)
					: UseStatement::getUniqueId(ReferencedName::TYPE_CLASS, $nameAsReferencedInFile);
				if (
					NamespaceHelper::isFullyQualifiedName($name)
					|| !array_key_exists($pointerBeforeUseStatements, $fileUnusedNames)
					|| !array_key_exists($uniqueId, $fileUnusedNames[$pointerBeforeUseStatements])
				) {
					continue;
				}

				$allUsedNames[$pointerBeforeUseStatements][$uniqueId] = true;
			}
		}

		if ($this->searchAnnotations) {
			$tokens = $phpcsFile->getTokens();
			$searchAnnotationsPointer = $startPointer + 1;
			while (true) {
				$docCommentOpenPointer = TokenHelper::findNext($phpcsFile, T_DOC_COMMENT_OPEN_TAG, $searchAnnotationsPointer);
				if ($docCommentOpenPointer === null) {
					break;
				}

				$annotations = AnnotationHelper::getAnnotations($phpcsFile, $docCommentOpenPointer);

				if (count($annotations) === 0) {
					$searchAnnotationsPointer = $tokens[$docCommentOpenPointer]['comment_closer'] + 1;
					continue;
				}

				$pointerBeforeUseStatements = $this->firstPointerBefore(
					$docCommentOpenPointer - 1,
					$pointersBeforeUseStatements,
					$startPointer
				);

				if (!array_key_exists($pointerBeforeUseStatements, $fileUnusedNames)) {
					$searchAnnotationsPointer = $tokens[$docCommentOpenPointer]['comment_closer'] + 1;
					continue;
				}

				foreach ($fileUnusedNames[$pointerBeforeUseStatements] as $useStatement) {
					if (!$useStatement->isClass()) {
						continue;
					}

					$nameAsReferencedInFile = $useStatement->getNameAsReferencedInFile();
					$uniqueId = UseStatement::getUniqueId($useStatement->getType(), $nameAsReferencedInFile);

					/** @var string $annotationName */
					foreach ($annotations as $annotationName => $annotationsByName) {
						if (in_array($annotationName, $this->getIgnoredAnnotations(), true)) {
							continue;
						}

						if (
							!in_array($annotationName, $this->getIgnoredAnnotationNames(), true)
							&& preg_match(
								'~^@(' . preg_quote($nameAsReferencedInFile, '~') . ')(?=[^-a-z\\d]|$)~i',
								$annotationName,
								$matches
							) !== 0
						) {
							$allUsedNames[$pointerBeforeUseStatements][$uniqueId] = true;
						}

						foreach ($annotationsByName as $annotation) {
							if (!$annotation instanceof GenericAnnotation) {
								continue;
							}

							if ($annotation->getParameters() === null) {
								continue;
							}

							if (
								preg_match(
									'~(?<=^|[^a-z\\\\])(' . preg_quote($nameAsReferencedInFile, '~') . ')(\\\\\\w+)*(?=::)~i',
									$annotation->getParameters(),
									$matches
								) === 0
								&& preg_match(
									'~(?<=@)(' . preg_quote($nameAsReferencedInFile, '~') . ')(?=[^\\w])~i',
									$annotation->getParameters(),
									$matches
								) === 0
							) {
								continue;
							}

							$allUsedNames[$pointerBeforeUseStatements][$uniqueId] = true;
						}

						/** @var VariableAnnotation|ParameterAnnotation|ReturnAnnotation|ThrowsAnnotation|PropertyAnnotation|MethodAnnotation|GenericAnnotation $annotation */
						foreach ($annotationsByName as $annotation) {
							if ($annotation->getContent() === null) {
								continue;
							}

							if ($annotation->isInvalid()) {
								continue;
							}

							$content = $annotation->getContent();

							$contentsToCheck = [];
							if (!$annotation instanceof GenericAnnotation) {
								foreach (AnnotationHelper::getAnnotationTypes($annotation) as $annotationType) {
									foreach (AnnotationTypeHelper::getIdentifierTypeNodes($annotationType) as $typeNode) {
										if (!$typeNode instanceof IdentifierTypeNode) {
											continue;
										}

										if (
											TypeHintHelper::isSimpleTypeHint($typeNode->name)
											|| TypeHintHelper::isSimpleUnofficialTypeHints($typeNode->name)
											|| !TypeHelper::isTypeName($typeNode->name)
										) {
											continue;
										}

										$contentsToCheck[] = $typeNode->name;
									}
								}
								foreach (AnnotationHelper::getAnnotationConstantExpressions($annotation) as $annotationConstantExpression) {
									$contentsToCheck = array_merge(
										$contentsToCheck,
										array_map(static function (ConstFetchNode $constFetchNode): string {
											return $constFetchNode->className;
										}, AnnotationConstantExpressionHelper::getConstantFetchNodes($annotationConstantExpression))
									);
								}
							} elseif ($annotationName === '@see') {
								$parts = preg_split('~(\\s+|::)~', $content);
								if ($parts !== false) {
									$contentsToCheck[] = $parts[0];
								}
							} else {
								$contentsToCheck[] = $content;
							}

							foreach ($contentsToCheck as $contentToCheck) {
								if (preg_match(
									'~(?<=^|\|)(' . preg_quote($nameAsReferencedInFile, '~') . ')(?=\\s|\\\\|\||\[|$)~i',
									$contentToCheck,
									$matches
								) === 0) {
									continue;
								}

								$allUsedNames[$pointerBeforeUseStatements][$uniqueId] = true;
							}
						}
					}
				}

				$searchAnnotationsPointer = $tokens[$docCommentOpenPointer]['comment_closer'] + 1;
			}
		}

		foreach ($fileUnusedNames as $pointerBeforeUnusedNames => $unusedNames) {
			$usedNames = $allUsedNames[$pointerBeforeUnusedNames] ?? [];
			foreach (array_diff_key($unusedNames, $usedNames) as $unusedUse) {
				$fullName = $unusedUse->getFullyQualifiedTypeName();
				if (
					$unusedUse->getNameAsReferencedInFile() !== $fullName
					&& $unusedUse->getNameAsReferencedInFile() !== NamespaceHelper::getUnqualifiedNameFromFullyQualifiedName($fullName)
				) {
					$fullName .= sprintf(' (as %s)', $unusedUse->getNameAsReferencedInFile());
				}
				$fix = $phpcsFile->addFixableError(sprintf(
					'Type %s is not used in this file.',
					$fullName
				), $unusedUse->getPointer(), self::CODE_UNUSED_USE);
				if (!$fix) {
					continue;
				}

				$endPointer = TokenHelper::findNext($phpcsFile, T_SEMICOLON, $unusedUse->getPointer()) + 1;

				$phpcsFile->fixer->beginChangeset();

				FixerHelper::removeBetweenIncluding($phpcsFile, $unusedUse->getPointer(), $endPointer);

				$phpcsFile->fixer->endChangeset();
			}
		}
	}

	/**
	 * @return list<string>
	 */
	private function getIgnoredAnnotationNames(): array
	{
		if ($this->normalizedIgnoredAnnotationNames === null) {
			$this->normalizedIgnoredAnnotationNames = array_merge(
				SniffSettingsHelper::normalizeArray($this->ignoredAnnotationNames),
				[
					'@param',
					'@throws',
					'@property',
					'@method',
				]
			);
		}

		return $this->normalizedIgnoredAnnotationNames;
	}

	/**
	 * @return list<string>
	 */
	private function getIgnoredAnnotations(): array
	{
		if ($this->normalizedIgnoredAnnotations === null) {
			$this->normalizedIgnoredAnnotations = SniffSettingsHelper::normalizeArray($this->ignoredAnnotations);
		}

		return $this->normalizedIgnoredAnnotations;
	}

	/**
	 * @param list<int> $pointersBeforeUseStatements
	 */
	private function firstPointerBefore(int $pointer, array $pointersBeforeUseStatements, int $startPointer): int
	{
		foreach ($pointersBeforeUseStatements as $pointerBeforeUseStatements) {
			if ($pointerBeforeUseStatements < $pointer) {
				return $pointerBeforeUseStatements;
			}
		}

		return $startPointer;
	}

}
