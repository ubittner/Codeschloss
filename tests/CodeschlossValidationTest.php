<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class CodeschlossValidationTest extends TestCaseSymconValidation
{
    public function testValidateCodeschloss(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateCodeschlossModule(): void
    {
        $this->validateModule(__DIR__ . '/../Codeschloss');
    }
}