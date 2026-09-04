<?php

declare(strict_types=1);

namespace MifrialCodingStandard\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

final class MethodOrderSniff implements Sniff
{
    /**
     * @return array<int, int>
     */
    public function register(): array
    {
        return [T_FUNCTION];
    }

    /**
     * Проверяет порядок методов внутри класса.
     *
     * @param File $phpcsFile Проверяемый PHP-файл.
     * @param int $stackPtr Позиция токена метода.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $methodNameToken = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);

        if ($methodNameToken === false || $tokens[$methodNameToken]['code'] !== T_STRING) {
            return;
        }

        $classToken = $this->getClassOwner($tokens, $stackPtr);
        if ($classToken === null) {
            return;
        }

        $previousMethod = $this->findPreviousMethod($phpcsFile, $stackPtr, $classToken);
        if ($previousMethod === null) {
            return;
        }

        $currentRank = $this->getMethodRank($tokens, $stackPtr, $methodNameToken);
        $previousRank = $this->getMethodRank($tokens, $previousMethod['pointer'], $previousMethod['name']);

        if ($currentRank < $previousRank) {
            $phpcsFile->addError(
                sprintf(
                    'Method "%s" must be declared after "%s" according to visibility order',
                    $tokens[$methodNameToken]['content'],
                    $tokens[$previousMethod['name']]['content'],
                ),
                $stackPtr,
                'MethodOrder',
            );
        }
    }

    /**
     * Ищет предыдущий метод того же класса.
     *
     * @param File $phpcsFile Проверяемый PHP-файл.
     * @param int $stackPtr Позиция текущего метода.
     * @param int $classToken Позиция класса.
     *
     * @return array{pointer: int, name: int}|null Предыдущий метод или null.
     */
    private function findPreviousMethod(File $phpcsFile, int $stackPtr, int $classToken): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $previousFunction = $phpcsFile->findPrevious(T_FUNCTION, $stackPtr - 1);

        if ($previousFunction === false || $this->getClassOwner($tokens, $previousFunction) !== $classToken) {
            return null;
        }

        $methodName = $phpcsFile->findNext(Tokens::$emptyTokens, $previousFunction + 1, null, true);
        if ($methodName === false || $tokens[$methodName]['code'] !== T_STRING) {
            return null;
        }

        return ['pointer' => $previousFunction, 'name' => $methodName];
    }

    /**
     * Определяет класс-владелец метода.
     *
     * @param array<int, array<string, mixed>> $tokens Токены PHP-файла.
     * @param int $functionToken Позиция метода.
     *
     * @return int|null Позиция класса или null.
     */
    private function getClassOwner(array $tokens, int $functionToken): ?int
    {
        foreach ($tokens[$functionToken]['conditions'] ?? [] as $conditionToken => $conditionType) {
            if (in_array($conditionType, [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
                return (int) $conditionToken;
            }
        }

        return null;
    }

    /**
     * Возвращает ранг метода по видимости и имени.
     *
     * @param array<int, array<string, mixed>> $tokens Токены PHP-файла.
     * @param int $stackPtr Позиция метода.
     * @param int $methodNameToken Позиция имени метода.
     *
     * @return int Ранг метода.
     */
    private function getMethodRank(array $tokens, int $stackPtr, int $methodNameToken): int
    {
        if ($tokens[$methodNameToken]['content'] === '__construct') {
            return 0;
        }

        $visibilityToken = $this->findVisibilityToken($tokens, $stackPtr);

        return match ($visibilityToken) {
            T_PRIVATE => 3,
            T_PROTECTED => 2,
            default => 1,
        };
    }

    /**
     * Ищет модификатор видимости перед методом.
     *
     * @param array<int, array<string, mixed>> $tokens Токены PHP-файла.
     * @param int $stackPtr Позиция метода.
     *
     * @return int|null Код модификатора видимости.
     */
    private function findVisibilityToken(array $tokens, int $stackPtr): ?int
    {
        for ($tokenIndex = $stackPtr - 1; $tokenIndex >= 0; $tokenIndex--) {
            $tokenCode = $tokens[$tokenIndex]['code'];
            if (in_array($tokenCode, [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                return $tokenCode;
            }

            if (!in_array($tokenCode, Tokens::$emptyTokens, true)
                && $tokenCode !== T_FINAL
                && $tokenCode !== T_STATIC
                && $tokenCode !== T_ABSTRACT
            ) {
                break;
            }
        }

        return null;
    }
}
