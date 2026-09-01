<?php

declare(strict_types=1);

namespace MifrialCodingStandard\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

final class MethodDocumentationSniff implements Sniff
{
    /**
     * @return array<int, int>
     */
    public function register(): array
    {
        return [T_FUNCTION];
    }

    /**
     * Проверяет обязательную документацию именованного метода.
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

        $docCommentToken = $this->findDocumentationToken($tokens, $stackPtr);
        if ($docCommentToken === false) {
            $phpcsFile->addError('Named method must have a PHPDoc block', $stackPtr, 'MissingMethodDoc');

            return;
        }

        $docComment = $this->getDocComment($tokens, $docCommentToken);
        if (str_contains($docComment, '{@inheritdoc}')) {
            return;
        }

        $this->checkRussianDescription($phpcsFile, $docCommentToken, $docComment);
        $this->checkParameterTags($phpcsFile, $stackPtr, $docCommentToken, $docComment);
        $this->checkReturnTag($phpcsFile, $docCommentToken, $docComment);

        if ($this->containsThrow($tokens, $stackPtr)) {
            $this->checkThrowsTag($phpcsFile, $docCommentToken, $docComment);
        }
    }

    /**
     * Проверяет наличие русского краткого описания.
     *
     * @param File $phpcsFile Проверяемый PHP-файл.
     * @param int $docCommentToken Позиция PHPDoc.
     * @param string $docComment Текст PHPDoc.
     *
     * @return void
     */
    private function checkRussianDescription(File $phpcsFile, int $docCommentToken, string $docComment): void
    {
        $description = preg_replace('/^\/\*\*|\*\/$|^\s*\*\s?/m', '', $docComment);
        $description = preg_replace('/@\w+.*$/ms', '', (string) $description);

        if (!str_contains((string) $description, 'ё') && !preg_match('/[А-Яа-я]/u', (string) $description)) {
            $phpcsFile->addError(
                'Method PHPDoc must contain a short description in Russian',
                $docCommentToken,
                'MissingRussianDescription',
            );
        }
    }

    /**
     * Проверяет соответствие @param параметрам метода.
     *
     * @param File $phpcsFile Проверяемый PHP-файл.
     * @param int $stackPtr Позиция токена метода.
     * @param int $docCommentToken Позиция PHPDoc.
     * @param string $docComment Текст PHPDoc.
     *
     * @return void
     */
    private function checkParameterTags(
        File $phpcsFile,
        int $stackPtr,
        int $docCommentToken,
        string $docComment,
    ): void {
        $tokens = $phpcsFile->getTokens();
        $parameterNames = [];
        $parameterStart = $tokens[$stackPtr]['parenthesis_opener'] ?? null;
        $parameterEnd = $tokens[$stackPtr]['parenthesis_closer'] ?? null;

        if ($parameterStart !== null && $parameterEnd !== null) {
            for ($tokenIndex = $parameterStart + 1; $tokenIndex < $parameterEnd; $tokenIndex++) {
                if ($tokens[$tokenIndex]['code'] === T_VARIABLE) {
                    $parameterNames[] = $tokens[$tokenIndex]['content'];
                }
            }
        }

        preg_match_all('/@param\s+.+?\s+(\$[A-Za-z_][A-Za-z0-9_]*)\s+(.+)/', $docComment, $matches);
        $documentedParameters = $matches[1] ?? [];
        $missingParameters = array_diff($parameterNames, $documentedParameters);

        if ($missingParameters !== []) {
            $phpcsFile->addError(
                sprintf('Missing @param tag for %s', implode(', ', $missingParameters)),
                $docCommentToken,
                'MissingParamTag',
            );
        }
    }

    /**
     * Проверяет обязательный @return.
     *
     * @param File $phpcsFile Проверяемый PHP-файл.
     * @param int $docCommentToken Позиция PHPDoc.
     * @param string $docComment Текст PHPDoc.
     *
     * @return void
     */
    private function checkReturnTag(File $phpcsFile, int $docCommentToken, string $docComment): void
    {
        if (!preg_match('/@return\s+\S+\s+.+/', $docComment)) {
            $phpcsFile->addError('Method PHPDoc must contain @return with a description', $docCommentToken, 'MissingReturnTag');
        }
    }

    /**
     * Проверяет обязательный @throws при прямом выбросе исключения.
     *
     * @param File $phpcsFile Проверяемый PHP-файл.
     * @param int $docCommentToken Позиция PHPDoc.
     * @param string $docComment Текст PHPDoc.
     *
     * @return void
     */
    private function checkThrowsTag(File $phpcsFile, int $docCommentToken, string $docComment): void
    {
        if (!preg_match('/@throws\s+\S+\s+.+/', $docComment)) {
            $phpcsFile->addError('Method PHPDoc must contain @throws with a description', $docCommentToken, 'MissingThrowsTag');
        }
    }

    /**
     * Ищет PHPDoc непосредственно перед методом.
     *
     * @param array<int, array<string, mixed>> $tokens Токены PHP-файла.
     * @param int $stackPtr Позиция токена метода.
     *
     * @return int|false Позиция PHPDoc или false.
     */
    private function findDocumentationToken(array $tokens, int $stackPtr): int|false
    {
        for ($tokenIndex = $stackPtr - 1; $tokenIndex >= 0; $tokenIndex--) {
            if (in_array($tokens[$tokenIndex]['type'], ['T_DOC_COMMENT', 'T_DOC_COMMENT_OPEN_TAG'], true)) {
                return $tokenIndex;
            }

            if (str_starts_with($tokens[$tokenIndex]['type'], 'T_DOC_COMMENT_')) {
                continue;
            }

            if (in_array($tokens[$tokenIndex]['code'], [
                T_WHITESPACE,
                T_PUBLIC,
                T_PROTECTED,
                T_PRIVATE,
                T_STATIC,
                T_ABSTRACT,
                T_FINAL,
            ], true)) {
                continue;
            }

            break;
        }

        return false;
    }

    /**
     * Собирает полный текст PHPDoc из токенов комментария.
     *
     * @param array<int, array<string, mixed>> $tokens Токены PHP-файла.
     * @param int $docCommentToken Позиция начала PHPDoc.
     *
     * @return string Текст PHPDoc.
     */
    private function getDocComment(array $tokens, int $docCommentToken): string
    {
        $docComment = '';
        foreach ($tokens as $tokenIndex => $token) {
            if ($tokenIndex < $docCommentToken) {
                continue;
            }

            if ($tokenIndex > $docCommentToken && $token['type'] === 'T_DOC_COMMENT_CLOSE_TAG') {
                return $docComment . $token['content'];
            }

            if (
                $tokenIndex === $docCommentToken
                || str_starts_with($token['type'], 'T_DOC_COMMENT_')
            ) {
                $docComment .= $token['content'];
            }
        }

        return $docComment;
    }

    /**
     * Определяет, содержит ли метод прямой throw.
     *
     * @param array<int, array<string, mixed>> $tokens Токены PHP-файла.
     * @param int $stackPtr Позиция токена метода.
     *
     * @return bool true, если в области метода есть throw.
     */
    private function containsThrow(array $tokens, int $stackPtr): bool
    {
        $scopeCloser = $tokens[$stackPtr]['scope_closer'] ?? null;
        if ($scopeCloser === null) {
            return false;
        }

        for ($tokenIndex = $stackPtr; $tokenIndex < $scopeCloser; $tokenIndex++) {
            if ($tokens[$tokenIndex]['code'] === T_THROW) {
                return true;
            }
        }

        return false;
    }
}
