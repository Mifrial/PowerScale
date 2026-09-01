<?php

declare(strict_types=1);

namespace MifrialCodingStandard\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

final class ControlStructureSpacingSniff implements Sniff
{
    /**
     * @return array<int, int>
     */
    public function register(): array
    {
        return [T_CLOSE_CURLY_BRACKET];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $closingToken = $tokens[$stackPtr];

        if (!isset($closingToken['scope_condition'])) {
            return;
        }

        $scopeCondition = $tokens[$closingToken['scope_condition']]['type'];
        if (!in_array($scopeCondition, $this->getControlStructureTypes(), true)) {
            return;
        }

        $nextToken = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);
        if ($nextToken === false || $this->isContinuation($tokens[$nextToken]['type'])) {
            return;
        }

        $lineDifference = $tokens[$nextToken]['line'] - $closingToken['line'];
        if ($lineDifference > 1) {
            return;
        }

        $error = 'Expected one blank line after a completed control structure';
        $fix = $phpcsFile->addFixableError($error, $stackPtr, 'MissingBlankLine');
        if ($fix) {
            $phpcsFile->fixer->addNewline($stackPtr);
        }
    }

    /**
     * @return array<int, int>
     */
    private function getControlStructureTypes(): array
    {
        return [
            'T_IF',
            'T_ELSE',
            'T_ELSEIF',
            'T_FOREACH',
            'T_TRY',
            'T_CATCH',
            'T_FINALLY',
        ];
    }

    private function isContinuation(string $tokenType): bool
    {
        return in_array($tokenType, ['T_ELSE', 'T_ELSEIF', 'T_CATCH', 'T_FINALLY', 'T_CLOSE_CURLY_BRACKET'], true);
    }
}
