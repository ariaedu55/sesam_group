<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Validation\ValidationException;
use Sesam\Core\Validation\Validator;
use Sesam\Models\Model;

final class ValidatorTest extends TestCase
{
    public function testRequiredAndMaxRules(): void
    {
        $validator = new Validator();

        $clean = $validator->validate(['name' => '  Ada  '], ['name' => 'required|string|max:20']);
        $this->assertSame('Ada', $clean['name']);

        $model = new Model($clean);
        $this->assertSame('Ada', $model->get('name'));
    }

    public function testMissingFieldIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        (new Validator())->validate([], ['name' => 'required|string']);
    }

    public function testMinRuleRejectsShortValue(): void
    {
        $this->expectException(ValidationException::class);
        (new Validator())->validate(['password' => 'short'], ['password' => 'required|string|min:12']);
    }
}
