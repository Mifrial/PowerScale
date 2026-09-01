<?php

declare(strict_types=1);

namespace MifrialCodingStandard\Sniffs\Metrics;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

final class MethodQualitySniff implements Sniff
{
    private const MaximumReturnCount = 3;

    private const MaximumMethodLines = 30;

    private const MaximumCognitiveComplexity = 8;

    private const MaximumCyclomaticComplexity = 8;

    private const MaximumNestingLevel = 3;

    private const MaximumConditionLogicalOperators = 3;

    /**
     * @return array<int, int>
     */
    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $functionToken = $tokens[$stackPtr];

        if (!isset($functionToken['scope_opener'], $functionToken['scope_closer'])) {
            return;
        }

        $scopeCloser = $functionToken['scope_closer'];
        $methodLines = $tokens[$scopeCloser]['line'] - $functionToken['line'] + 1;
        $returnCount = 0;
        $cyclomaticComplexity = 1;
        $cognitiveComplexity = 0;
        $maximumNestingLevel = 0;

        for ($tokenIndex = $stackPtr + 1; $tokenIndex < $scopeCloser; $tokenIndex++) {
            $tokenType = $tokens[$tokenIndex]['type'];
            if ($tokenType === 'T_RETURN') {
                $returnCount++;
            }

            if (!$this->isComplexityToken($tokenType)) {
                continue;
            }

            $nestingLevel = $this->getNestingLevel($tokens, $tokenIndex, $stackPtr, $scopeCloser);
            $maximumNestingLevel = max($maximumNestingLevel, $nestingLevel);
            $cyclomaticComplexity++;
            $cognitiveComplexity += 1 + $nestingLevel;

            $logicalOperatorCount = $this->getConditionLogicalOperatorCount($tokens, $tokenIndex);
            if ($logicalOperatorCount > 0) {
                $cyclomaticComplexity += $logicalOperatorCount;
                $cognitiveComplexity += $logicalOperatorCount;
            }

            if ($logicalOperatorCount > self::MaximumConditionLogicalOperators) {
                $phpcsFile->addError(
                    sprintf(
                        'Condition contains %d logical operators; maximum is %d',
                        $logicalOperatorCount,
                        self::MaximumConditionLogicalOperators,
                    ),
                    $tokenIndex,
                    'ConditionTooComplex',
                );
            }
        }

        if ($returnCount > self::MaximumReturnCount) {
            $this->addMethodError(
                $phpcsFile,
                $stackPtr,
                sprintf('Method has %d return statements; maximum is %d', $returnCount, self::MaximumReturnCount),
                'TooManyReturns',
            );
        }

        if ($methodLines > self::MaximumMethodLines) {
            $this->addMethodError(
                $phpcsFile,
                $stackPtr,
                sprintf('Method has %d lines; maximum is %d', $methodLines, self::MaximumMethodLines),
                'MethodTooLong',
            );
        }

        if ($cognitiveComplexity > self::MaximumCognitiveComplexity) {
            $this->addMethodError(
                $phpcsFile,
                $stackPtr,
                sprintf(
                    'Method has cognitive complexity %d; maximum is %d',
                    $cognitiveComplexity,
                    self::MaximumCognitiveComplexity,
                ),
                'CognitiveComplexityTooHigh',
            );
        }

        if ($cyclomaticComplexity > self::MaximumCyclomaticComplexity) {
            $this->addMethodError(
                $phpcsFile,
                $stackPtr,
                sprintf(
                    'Method has cyclomatic complexity %d; maximum is %d',
                    $cyclomaticComplexity,
                    self::MaximumCyclomaticComplexity,
                ),
                'CyclomaticComplexityTooHigh',
            );
        }

        if ($maximumNestingLevel > self::MaximumNestingLevel) {
            $this->addMethodError(
                $phpcsFile,
                $stackPtr,
                sprintf(
                    'Method has nesting level %d; maximum is %d',
                    $maximumNestingLevel,
                    self::MaximumNestingLevel,
                ),
                'NestingLevelTooHigh',
            );
        }
    }

    private function isComplexityToken(string $tokenType): bool
    {
        return in_array($tokenType, [
            'T_IF',
            'T_ELSEIF',
            'T_ELSE',
            'T_FOR',
            'T_FOREACH',
            'T_WHILE',
            'T_DO',
            'T_SWITCH',
            'T_CATCH',
            'T_CASE',
        ], true);
    }

    /**
     * @param array<int, array<string, mixed>> $tokens
     */
    private function getNestingLevel(array $tokens, int $tokenIndex, int $scopeOpener, int $scopeCloser): int
    {
        $nestingLevel = 0;
        foreach ($tokens as $candidateIndex => $candidateToken) {
            if (
                $candidateIndex <= $scopeOpener
                || $candidateIndex >= $tokenIndex
                || !isset($candidateToken['scope_opener'], $candidateToken['scope_closer'])
                || $candidateToken['scope_opener'] <= $scopeOpener
                || $candidateToken['scope_closer'] <= $tokenIndex
                || $candidateToken['scope_closer'] >= $scopeCloser
            ) {
                continue;
            }

            $candidateType = $candidateToken['type'];
            if ($this->isComplexityToken($candidateType)) {
                $nestingLevel++;
            }
        }

        return $nestingLevel;
    }

    /**
     * @param array<int, array<string, mixed>> $tokens
     */
    private function getConditionLogicalOperatorCount(array $tokens, int $tokenIndex): int
    {
        if (!isset($tokens[$tokenIndex]['parenthesis_opener'], $tokens[$tokenIndex]['parenthesis_closer'])) {
            return 0;
        }

        $logicalOperatorCount = 0;
        $conditionStart = $tokens[$tokenIndex]['parenthesis_opener'];
        $conditionEnd = $tokens[$tokenIndex]['parenthesis_closer'];
        for ($conditionIndex = $conditionStart; $conditionIndex <= $conditionEnd; $conditionIndex++) {
            if (in_array($tokens[$conditionIndex]['type'], ['T_BOOLEAN_AND', 'T_BOOLEAN_OR'], true)) {
                $logicalOperatorCount++;
            }
        }

        return $logicalOperatorCount;
    }

    private function addMethodError(File $phpcsFile, int $stackPtr, string $message, string $code): void
    {
        $phpcsFile->addError($message, $stackPtr, $code);
    }
}
