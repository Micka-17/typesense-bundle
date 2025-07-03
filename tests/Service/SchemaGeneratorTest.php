<?php

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Attribute\TypesenseField;
use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use Micka17\TypesenseBundle\Service\SchemaGenerator;
use PHPUnit\Framework\TestCase;

#[TypesenseIndexable(collection: 'books', defaultSortingField: 'pages', enableNestedFields: true)]
class Book
{
    #[TypesenseField]
    public string $title;

    #[TypesenseField(facet: true)]
    public string $genre;

    #[TypesenseField(optional: true)]
    public string $summary;

    #[TypesenseField]
    public int $pages;
}

#[TypesenseIndexable(collection: 'invalid_books', defaultSortingField: 'summary')]
class InvalidBook
{
    #[TypesenseField(optional: true)]
    public string $summary;
}

class SchemaGeneratorTest extends TestCase
{
    public function testGenerateValidSchema(): void
    {
        $generator = new SchemaGenerator();
        $schema = $generator->generate(Book::class);

        $this->assertSame('books', $schema['name']);
        $this->assertTrue($schema['enable_nested_fields']);
        $this->assertSame('pages', $schema['default_sorting_field']);

        $fieldNames = array_column($schema['fields'], 'name');
        $this->assertContains('title', $fieldNames);
        $this->assertContains('genre', $fieldNames);
        $this->assertContains('pages', $fieldNames);
        $this->assertContains('summary', $fieldNames);

        foreach ($schema['fields'] as $field) {
            $this->assertArrayHasKey('name', $field);
            $this->assertArrayHasKey('type', $field);
            $this->assertArrayHasKey('facet', $field);
            $this->assertArrayHasKey('sort', $field);
            $this->assertArrayHasKey('optional', $field);
        }
    }

    public function testMissingIndexableAttributeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("L'entité 'stdClass' n'a pas l'attribut #[TypesenseIndexable].");

        $generator = new SchemaGenerator();
        $generator->generate(\stdClass::class);
    }

    public function testDefaultSortingFieldCannotBeOptional(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ de tri par défaut 'summary' ne peut pas être un champ optionnel.");

        $generator = new SchemaGenerator();
        $generator->generate(InvalidBook::class);
    }
}
