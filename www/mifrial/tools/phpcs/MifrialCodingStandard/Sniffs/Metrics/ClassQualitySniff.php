<?php

declare(strict_types=1);

namespace MifrialCodingStandard\Sniffs\Metrics;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

final class ClassQualitySniff implements Sniff
{
    private const MaximumClassLines = 500;

    private const MaximumPublicMethods = 10;

    private const MaximumProtectedMethods = 5;

    private const MaximumConstructorDependencies = 6;

    private const MaximumClassComplexity = 40;

    /**
     * @return array<int, int>
     */
    public function register(): array
    {
        return [T_CLASS, T_INTERFACE, T_TRAIT];
    }

    /**
     * Проверяет размер и суммарную сложность класса.
     *
     * @param File $phpcsFile Проверяемый PHP-файл.
     * @param int $stackPtr Позиция класса.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $scopeCloser = $tokens[$stackPtr]['scope_closer'] ?? null;

        if ($scopeCloser === null) {
            return;
        }

        $classLines = $tokens[$scopeCloser]['line'] - $tokens[$stackPtr]['line'] + 1;
        $methodCounts = ['public' => 0, 'protected' => 0];
        $classComplexity = 0;
        $constructorDependencies = 0;

        for ($tokenIndex = $stackPtr + 1; $tokenIndex < $scopeCloser; $tokenIndex++) {
            if ($tokens[$tokenIndex]['code'] !== T_FUNCTION) {
                continue;
            }

            $methodVisibility = $this->getMethodVisibility($tokens, $tokenIndex);
            if ($methodVisibility === 'public' || $methodVisibility === 'protected') {
                $methodCounts[$methodVisibility]++;
            }

            $methodNameToken = $phpcsFile->findNext(Tokens::$emptyTokens, $tokenIndex + 1, null, true);
            if ($methodNameToken !== false && $tokens[$methodNameToken]['content'] === '__construct') {
                $constructorDependencies = $this->countParameters($tokens, $tokenIndex);
            }

            $classComplexity += $this->getMethodComplexity($tokens, $tokenIndex);
        }

        $this->checkLimit($phpcsFile, $stackPtr, $classLines, self::MaximumClassLines, 'ClassTooLong', 'lines');
        $this->checkLimit(
            $phpcsFile,
            $stackPtr,
            $methodCounts['public'],
            self::MaximumPublicMethods,
            'TooManyPublicMethods',
            'public methods',
        );
        $this->checkLimit(
            $phpcsFile,
            $stackPtr,
            $methodCounts['protected'],
            self::MaximumProtectedMethods,
            'TooManyProtectedMethods',
            'protected methods',
        );
        $this->checkLimit(
            $phpcsFile,
            $stackPtr,
            $constructorDependencies,
            self::MaximumConstructorDependencies,
            'TooManyConstructorDependencies',
            'constructor dependencies',
        );
        $this->checkLimit(
            $phpcsFile,
            $stackPtr,
            $classComplexity,
            self::MaximumClassComplexity,
            'ClassComplexityTooHigh',
            'complexity',
        );
    }

    /**
     * Определяет видимость метода.
     *
     * @param array<int, array<string, mixed>> $tokens Токены PHP-файла.
     * @param int $functionToken Позиция метода.
     *
     * @return string Видимость метода.
     */
    private function getMethodVisibility(array $tokens, int $functionToken): string
    {
        for ($tokenIndex = $functionToken - 1; $tokenIndex >= 0; $tokenIndex--) {
            $tokenCode = $tokens[$tokenIndex]['code'];
            if (in_array($tokenCode, [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                return match ($tokenCode) {
                    T_PROTECTED => 'protected',
                    T_PRIVATE => 'private',
                    default => 'public',
                };
            }

            if ($tokenCode !== T_WHITESPACE && $tokenCode !== T_FINAL && $tokenCode !== T_STATIC) {
                break;
            }
        }

        return 'public';
    }

    /**
     * Считает параметры конструктора.
     *
     * @param array<int, array<string, mixed>> $tokens Токены PHP-файла.
     * @param int $functionToken Позиция метода.
     *
     * @return int Количество параметров.
     */
    private function countParameters(array $tokens, int $functionToken): int
    {
        $parameterStart = $tokens[$functionToken]['parenthesis_opener'] ?? null;
        $parameterEnd = $tokens[$functionToken]['parenthesis_closer'] ?? null;

        if ($parameterStart === null || $parameterEnd === null) {
            return 0;
        }

        $parameterCount = 0;
        for ($tokenIndex = $parameterStart + 1; $tokenIndex < $parameterEnd; $tokenIndex++) {
            if ($tokens[$tokenIndex]['code'] === T_VARIABLE) {
                $parameterCount++;
            }
        }

        return $parameterCount;
    }

    /**
     * Считает упрощённую cyclomatic complexity метода.
     *
     * @param array<int, array<string, mixed>> $tokens Токены PHP-файла.
     * @param int $functionToken Позиция метода.
     *
     * @return int Complexity метода.
     */
    private function getMethodComplexity(array $tokens, int $functionToken): int
    {
        $scopeCloser = $tokens[$functionToken]['scope_closer'] ?? null;
        if ($scopeCloser === null) {
            return 1;
        }

        $complexity = 1;
        for ($tokenIndex = $functionToken + 1; $tokenIndex < $scopeCloser; $tokenIndex++) {
            if (in_array($tokens[$tokenIndex]['code'], [
                T_IF,
                T_ELSEIF,
                T_FOR,
                T_FOREACH,
                T_WHILE,
                T_CASE,
                T_CATCH,
            ], true)) {
                $complexity++;
            }
        }

        return $complexity;
    }

    /**
     * Добавляет ошибку при превышении порога.
     *
     * @param File $phpcsFile Проверяемый PHP-файл.
     * @param int $stackPtr Позиция класса.
     * @param int $actual Фактическое значение.
     * @param int $maximum Максимально допустимое значение.
     * @param string $code Код ошибки.
     * @param string $metric Название метрики.
     *
     * @return void
     */
    private function checkLimit(
        File $phpcsFile,
        int $stackPtr,
        int $actual,
        int $maximum,
        string $code,
        string $metric,
    ): void {
        if ($actual > $maximum) {
            $phpcsFile->addError(
                sprintf('Class has %d %s; maximum is %d', $actual, $metric, $maximum),
                $stackPtr,
                $code,
            );
        }
    }
}
