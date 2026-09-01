<?php

declare(strict_types=1);

namespace MifrialCodingStandard\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Требует PHPDoc с русским описанием у классов, интерфейсов, трейтов и enum.
 */
final class ClassDocumentationSniff implements Sniff
{
    /**
     * Регистрирует типы объявлений.
     *
     * @return array<int, int>
     */
    public function register(): array
    {
        $tokens = [T_CLASS, T_INTERFACE, T_TRAIT];
        if (defined('T_ENUM')) {
            $tokens[] = T_ENUM;
        }

        return $tokens;
    }

    /**
     * Проверяет PHPDoc объявления типа.
     *
     * @param File $phpcsFile Проверяемый PHP-файл.
     * @param int $stackPtr Позиция токена объявления.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        if (($tokens[$stackPtr]['code'] ?? null) === T_ANON_CLASS) {
            return;
        }

        $docCommentToken = $this->findDocumentationToken($tokens, $stackPtr);
        if ($docCommentToken === false) {
            $phpcsFile->addError('Named type must have a PHPDoc block', $stackPtr, 'MissingClassDoc');

            return;
        }

        $docComment = $this->getDocComment($tokens, $docCommentToken);
        if (str_contains($docComment, '{@inheritdoc}')) {
            return;
        }

        $description = preg_replace('/^\/\*\*|\*\/$|^\s*\*\s?/m', '', $docComment);
        $description = preg_replace('/@\w+.*$/ms', '', (string) $description);
        if (!str_contains((string) $description, 'ё') && !preg_match('/[А-Яа-я]/u', (string) $description)) {
            $phpcsFile->addError(
                'Type PHPDoc must contain a short description in Russian',
                $docCommentToken,
                'MissingRussianDescription',
            );
        }
    }

    /**
     * Ищет PHPDoc непосредственно перед объявлением типа.
     *
     * @param array<int, array<string, mixed>> $tokens Токены PHP-файла.
     * @param int $stackPtr Позиция токена объявления.
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
                T_READONLY,
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
}
