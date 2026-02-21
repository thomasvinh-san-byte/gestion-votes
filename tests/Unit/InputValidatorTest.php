<?php

declare(strict_types=1);

use AgVote\Core\Validation\InputValidator;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/Core/Validation/InputValidator.php';

/**
 * Unit tests for InputValidator.
 */
class InputValidatorTest extends TestCase {
    public function testStringValidation(): void {
        $validator = InputValidator::schema()
            ->string('name')->required()->minLength(3)->maxLength(50);

        $result = $validator->validate(['name' => 'John Doe']);

        $this->assertTrue($result->isValid());
        $this->assertEquals('John Doe', $result->get('name'));
    }

    public function testStringValidationFailsOnMinLength(): void {
        $validator = InputValidator::schema()
            ->string('name')->required()->minLength(3);

        $result = $validator->validate(['name' => 'Jo']);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('name', $result->errors());
    }

    public function testStringValidationFailsOnMaxLength(): void {
        $validator = InputValidator::schema()
            ->string('name')->required()->maxLength(5);

        $result = $validator->validate(['name' => 'John Doe']);

        $this->assertFalse($result->isValid());
    }

    public function testRequiredFieldValidation(): void {
        $validator = InputValidator::schema()
            ->string('name')->required();

        $result = $validator->validate([]);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('requis', $result->firstError());
    }

    public function testOptionalFieldAllowsEmpty(): void {
        $validator = InputValidator::schema()
            ->string('nickname')->optional();

        $result = $validator->validate([]);

        $this->assertTrue($result->isValid());
    }

    public function testDefaultValue(): void {
        $validator = InputValidator::schema()
            ->string('role')->default('user');

        $result = $validator->validate([]);

        $this->assertTrue($result->isValid());
        $this->assertEquals('user', $result->get('role'));
    }

    public function testNullableField(): void {
        $validator = InputValidator::schema()
            ->string('middle_name')->nullable();

        $result = $validator->validate(['middle_name' => '']);

        $this->assertTrue($result->isValid());
        $this->assertNull($result->get('middle_name'));
    }

    public function testIntegerValidation(): void {
        $validator = InputValidator::schema()
            ->integer('age')->required()->min(0)->max(150);

        $result = $validator->validate(['age' => '25']);

        $this->assertTrue($result->isValid());
        $this->assertSame(25, $result->get('age'));
    }

    public function testIntegerValidationFailsOnMin(): void {
        $validator = InputValidator::schema()
            ->integer('age')->min(18);

        $result = $validator->validate(['age' => '15']);

        $this->assertFalse($result->isValid());
    }

    public function testIntegerValidationFailsOnMax(): void {
        $validator = InputValidator::schema()
            ->integer('quantity')->max(100);

        $result = $validator->validate(['quantity' => '150']);

        $this->assertFalse($result->isValid());
    }

    public function testNumberValidation(): void {
        $validator = InputValidator::schema()
            ->number('price')->required();

        $result = $validator->validate(['price' => '19.99']);

        $this->assertTrue($result->isValid());
        $this->assertEqualsWithDelta(19.99, $result->get('price'), 0.001);
    }

    public function testBooleanValidation(): void {
        $validator = InputValidator::schema()
            ->boolean('active')->required();

        $result = $validator->validate(['active' => '1']);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->get('active'));
    }

    public function testBooleanValidationWithFalseString(): void {
        $validator = InputValidator::schema()
            ->boolean('active');

        $result = $validator->validate(['active' => 'false']);

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->get('active'));
    }

    public function testBooleanValidationWithYes(): void {
        $validator = InputValidator::schema()
            ->boolean('active');

        $result = $validator->validate(['active' => 'yes']);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->get('active'));
    }

    public function testEmailValidation(): void {
        $validator = InputValidator::schema()
            ->email('email')->required();

        $result = $validator->validate(['email' => 'Test@Example.COM']);

        $this->assertTrue($result->isValid());
        $this->assertEquals('test@example.com', $result->get('email'));
    }

    public function testEmailValidationFails(): void {
        $validator = InputValidator::schema()
            ->email('email')->required();

        $result = $validator->validate(['email' => 'invalid-email']);

        $this->assertFalse($result->isValid());
    }

    public function testUuidValidation(): void {
        $validator = InputValidator::schema()
            ->uuid('id')->required();

        $result = $validator->validate(['id' => '550E8400-E29B-41D4-A716-446655440000']);

        $this->assertTrue($result->isValid());
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $result->get('id'));
    }

    public function testUuidValidationFails(): void {
        $validator = InputValidator::schema()
            ->uuid('id')->required();

        $result = $validator->validate(['id' => 'not-a-uuid']);

        $this->assertFalse($result->isValid());
    }

    public function testEnumValidation(): void {
        $validator = InputValidator::schema()
            ->enum('status', ['draft', 'published', 'archived'])->required();

        $result = $validator->validate(['status' => 'published']);

        $this->assertTrue($result->isValid());
        $this->assertEquals('published', $result->get('status'));
    }

    public function testEnumValidationFails(): void {
        $validator = InputValidator::schema()
            ->enum('status', ['draft', 'published'])->required();

        $result = $validator->validate(['status' => 'invalid']);

        $this->assertFalse($result->isValid());
    }

    public function testArrayValidation(): void {
        $validator = InputValidator::schema()
            ->array('tags')->required();

        $result = $validator->validate(['tags' => ['php', 'security']]);

        $this->assertTrue($result->isValid());
        $this->assertEquals(['php', 'security'], $result->get('tags'));
    }

    public function testArrayValidationFromJsonString(): void {
        $validator = InputValidator::schema()
            ->array('items');

        $result = $validator->validate(['items' => '["a","b","c"]']);

        $this->assertTrue($result->isValid());
        $this->assertEquals(['a', 'b', 'c'], $result->get('items'));
    }

    public function testDatetimeValidation(): void {
        $validator = InputValidator::schema()
            ->datetime('created_at')->required();

        $result = $validator->validate(['created_at' => '2024-01-15 10:30:00']);

        $this->assertTrue($result->isValid());
        $this->assertEquals('2024-01-15 10:30:00', $result->get('created_at'));
    }

    public function testDatetimeValidationWithIsoFormat(): void {
        $validator = InputValidator::schema()
            ->datetime('date');

        $result = $validator->validate(['date' => '2024-01-15T10:30:00']);

        $this->assertTrue($result->isValid());
    }

    public function testDatetimeValidationWithDateOnly(): void {
        $validator = InputValidator::schema()
            ->datetime('date');

        $result = $validator->validate(['date' => '2024-01-15']);

        $this->assertTrue($result->isValid());
    }

    public function testPatternValidation(): void {
        $validator = InputValidator::schema()
            ->string('code')->pattern('/^[A-Z]{3}-[0-9]{4}$/');

        $result = $validator->validate(['code' => 'ABC-1234']);

        $this->assertTrue($result->isValid());
    }

    public function testPatternValidationFails(): void {
        $validator = InputValidator::schema()
            ->string('code')->pattern('/^[A-Z]{3}-[0-9]{4}$/');

        $result = $validator->validate(['code' => 'abc-1234']);

        $this->assertFalse($result->isValid());
    }

    public function testInConstraint(): void {
        $validator = InputValidator::schema()
            ->string('color')->in(['red', 'green', 'blue']);

        $result = $validator->validate(['color' => 'red']);

        $this->assertTrue($result->isValid());
    }

    public function testInConstraintFails(): void {
        $validator = InputValidator::schema()
            ->string('color')->in(['red', 'green', 'blue']);

        $result = $validator->validate(['color' => 'yellow']);

        $this->assertFalse($result->isValid());
    }

    public function testXssSanitization(): void {
        $validator = InputValidator::schema()
            ->string('comment');

        $result = $validator->validate(['comment' => '<script>alert("xss")</script>']);

        $this->assertTrue($result->isValid());
        $this->assertStringNotContainsString('<script>', $result->get('comment'));
    }

    public function testRawOptionDisablesSanitization(): void {
        $validator = InputValidator::schema()
            ->string('html')->raw();

        $result = $validator->validate(['html' => '<b>Bold</b>']);

        $this->assertTrue($result->isValid());
        $this->assertEquals('<b>Bold</b>', $result->get('html'));
    }

    public function testMultipleFieldsValidation(): void {
        $validator = InputValidator::schema()
            ->string('name')->required()->minLength(2)
            ->email('email')->required()
            ->integer('age')->optional();

        $result = $validator->validate([
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $this->assertTrue($result->isValid());
        $this->assertEquals('John', $result->get('name'));
        $this->assertEquals('john@example.com', $result->get('email'));
    }

    public function testMultipleErrors(): void {
        $validator = InputValidator::schema()
            ->string('name')->required()
            ->email('email')->required();

        $result = $validator->validate([]);

        $this->assertFalse($result->isValid());
        $this->assertCount(2, $result->errors());
    }

    public function testDataMethod(): void {
        $validator = InputValidator::schema()
            ->string('a')->required()
            ->string('b')->required();

        $result = $validator->validate(['a' => 'x', 'b' => 'y']);

        $data = $result->data();

        $this->assertArrayHasKey('a', $data);
        $this->assertArrayHasKey('b', $data);
    }

    public function testGetWithDefault(): void {
        $validator = InputValidator::schema()
            ->string('name');

        $result = $validator->validate([]);

        $this->assertEquals('default', $result->get('name', 'default'));
    }

    public function testFirstError(): void {
        $validator = InputValidator::schema()
            ->string('name')->required();

        $result = $validator->validate([]);

        $this->assertNotNull($result->firstError());
        $this->assertIsString($result->firstError());
    }
}
