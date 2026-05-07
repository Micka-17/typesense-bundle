<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Attribute\TypesenseField;
use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use Micka17\TypesenseBundle\Service\SchemaGenerator;
use PHPUnit\Framework\TestCase;

// --- Fixtures ---

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

#[TypesenseIndexable(collection: 'invalid_books_type', defaultSortingField: 'title')]
class InvalidBookSortingType
{
    #[TypesenseField(type: 'string')]
    public string $title;
}

#[TypesenseIndexable(collection: 'invalid_books_missing', defaultSortingField: 'nonexistent')]
class InvalidBookMissingField
{
    #[TypesenseField]
    public string $name;
}

#[TypesenseIndexable(
    collection: 'reviews',
    metadata: ['owner' => 'catalog'],
    options: ['voice_query_model' => 'products-nl'],
)]
class Review
{
    #[TypesenseField(reference: 'products.id', asyncReference: true, cascadeDelete: false)]
    public string $productId;

    #[TypesenseField(
        type: 'string',
        truncate: 120,
        tokenSeparators: ['-', '/'],
        symbolsToIndex: ['+'],
    )]
    public string $body;

    #[TypesenseField(
        name: 'embedding',
        type: 'float[]',
        embed: ['from' => ['body'], 'model_config' => ['model_name' => 'ts/e5-small']],
        numDim: 384,
        vecDist: 'cosine',
        hnswParams: ['M' => 16],
    )]
    public array $vector;
}

#[TypesenseIndexable(collection: 'products')]
class ProductWithEmbedAutoType
{
    #[TypesenseField(
        embed: ['from' => ['name'], 'model_config' => ['model_name' => 'ts/e5-small']],
        vecDist: 'cosine',
    )]
    public array $embedding;
}

#[TypesenseIndexable(collection: 'products')]
class ProductWithIndexFalse
{
    #[TypesenseField(index: false)]
    public string $internalCode;
}

#[TypesenseIndexable(collection: 'products')]
class ProductWithBadCascadeDelete
{
    #[TypesenseField(cascadeDelete: true)]
    public string $relatedId;
}

#[TypesenseIndexable(collection: 'products')]
class ProductWithBadAsyncReference
{
    #[TypesenseField(asyncReference: true)]
    public string $relatedId;
}

#[TypesenseIndexable(collection: 'products')]
class ProductWithBadReferenceFormat
{
    #[TypesenseField(reference: 'invalid-no-dot')]
    public string $relatedId;
}

#[TypesenseIndexable(collection: 'products')]
class ProductWithBadTruncate
{
    #[TypesenseField(type: 'int32', truncate: 100)]
    public int $count;
}

#[TypesenseIndexable(collection: 'products')]
class ProductWithBadVecDist
{
    #[TypesenseField(type: 'float[]', numDim: 128, vecDist: 'euclidean')]
    public array $vector;
}

#[TypesenseIndexable(collection: 'products')]
class ProductWithBadSort
{
    #[TypesenseField(type: 'bool', sort: true)]
    public bool $active;
}

// --- Tests ---

class SchemaGeneratorTest extends TestCase
{
    private SchemaGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SchemaGenerator();
    }

    // --- Happy paths ---

    public function testGenerateValidSchema(): void
    {
        $schema = $this->generator->generate(Book::class);

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

    public function testAutoTypeDetection(): void
    {
        $schema = $this->generator->generate(Book::class);
        $fields = array_column($schema['fields'], null, 'name');

        $this->assertSame('string', $fields['title']['type']);
        $this->assertSame('int32', $fields['pages']['type']);
    }

    public function testGenerateSchemaWithTypesenseV30FieldOptions(): void
    {
        $schema = $this->generator->generate(Review::class);

        $this->assertSame('reviews', $schema['name']);
        $this->assertSame(['owner' => 'catalog'], $schema['metadata']);
        $this->assertSame('products-nl', $schema['voice_query_model']);

        $fields = array_column($schema['fields'], null, 'name');

        $this->assertSame('products.id', $fields['productId']['reference']);
        $this->assertTrue($fields['productId']['async_reference']);
        $this->assertFalse($fields['productId']['cascade_delete']);
        $this->assertSame(120, $fields['body']['truncate']);
        $this->assertSame(['-', '/'], $fields['body']['token_separators']);
        $this->assertSame(['+'], $fields['body']['symbols_to_index']);
        $this->assertSame(
            ['from' => ['body'], 'model_config' => ['model_name' => 'ts/e5-small']],
            $fields['embedding']['embed'],
        );
        $this->assertSame(384, $fields['embedding']['num_dim']);
        $this->assertSame('cosine', $fields['embedding']['vec_dist']);
        $this->assertSame(['M' => 16], $fields['embedding']['hnsw_params']);
    }

    public function testEmbedWithAutoTypeResolvesToFloatArray(): void
    {
        $schema = $this->generator->generate(ProductWithEmbedAutoType::class);
        $fields = array_column($schema['fields'], null, 'name');

        $this->assertSame('float[]', $fields['embedding']['type']);
    }

    public function testIndexFalseIsEmittedInSchema(): void
    {
        $schema = $this->generator->generate(ProductWithIndexFalse::class);
        $fields = array_column($schema['fields'], null, 'name');

        $this->assertArrayHasKey('index', $fields['internalCode']);
        $this->assertFalse($fields['internalCode']['index']);
    }

    public function testIndexTrueIsOmittedFromSchema(): void
    {
        $schema = $this->generator->generate(Book::class);

        foreach ($schema['fields'] as $field) {
            $this->assertArrayNotHasKey('index', $field);
        }
    }

    public function testCascadeDeleteFalseIsEmittedInSchema(): void
    {
        // Explicit false must appear in the schema so Typesense disables cascade on the JOIN field.
        $schema = $this->generator->generate(Review::class);
        $fields = array_column($schema['fields'], null, 'name');

        $this->assertArrayHasKey('cascade_delete', $fields['productId']);
        $this->assertFalse($fields['productId']['cascade_delete']);
    }

    public function testCascadeDeleteNullIsAbsentFromSchema(): void
    {
        // When cascadeDelete is not set (default null), the key must be absent so Typesense uses its default.
        $schema = $this->generator->generate(Book::class);

        foreach ($schema['fields'] as $field) {
            $this->assertArrayNotHasKey('cascade_delete', $field);
        }
    }

    // --- Error: missing attribute ---

    public function testMissingIndexableAttributeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("L'entité 'stdClass' n'a pas l'attribut #[TypesenseIndexable].");

        $this->generator->generate(\stdClass::class);
    }

    // --- Error: defaultSortingField ---

    public function testDefaultSortingFieldCannotBeOptional(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ de tri par défaut 'summary' ne peut pas être un champ optionnel.");

        $this->generator->generate(InvalidBook::class);
    }

    public function testDefaultSortingFieldMustBeNumericType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ de tri par défaut 'title'");

        $this->generator->generate(InvalidBookSortingType::class);
    }

    public function testDefaultSortingFieldMustExist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'nonexistent'");

        $this->generator->generate(InvalidBookMissingField::class);
    }

    // --- Error: invalid field combinations ---

    public function testCascadeDeleteWithoutReferenceThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'cascade_delete' requires 'reference' to be set");

        $this->generator->generate(ProductWithBadCascadeDelete::class);
    }

    public function testAsyncReferenceWithoutReferenceThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'async_reference' requires 'reference' to be set");

        $this->generator->generate(ProductWithBadAsyncReference::class);
    }

    public function testReferenceWithoutDotThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'collection.field' format");

        $this->generator->generate(ProductWithBadReferenceFormat::class);
    }

    public function testTruncateOnNonStringThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'truncate' is only valid for string fields");

        $this->generator->generate(ProductWithBadTruncate::class);
    }

    public function testInvalidVecDistThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'vec_dist' must be one of");

        $this->generator->generate(ProductWithBadVecDist::class);
    }

    public function testSortOnBoolThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'sort: true' is only valid for");

        $this->generator->generate(ProductWithBadSort::class);
    }
}
