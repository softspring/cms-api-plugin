<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Tests\Application\Operation;

use PHPUnit\Framework\TestCase;
use Softspring\CmsApiPlugin\Application\Operation\FormSchema;
use Softspring\Component\FormSchema\Schema\Extractor\CollectionFieldSchemaExtractor;
use Softspring\Component\FormSchema\Schema\Extractor\CompoundFieldSchemaExtractor;
use Softspring\Component\FormSchema\Schema\Extractor\OverrideFieldSchemaExtractor;
use Softspring\Component\FormSchema\Schema\Extractor\ScalarFieldSchemaExtractor;
use Softspring\Component\FormSchema\Schema\SchemaExtractor;
use Softspring\Component\FormSchema\Schema\SchemaMetadataApplier;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormSchemaTest extends TestCase
{
    public function testSharedExtractorOverridesAndRuntimeMetadataAreReturnedTogether(): void
    {
        $factory = Forms::createFormFactory();
        $metadata = new SchemaMetadataApplier();
        $extractor = new SchemaExtractor($factory, [new OverrideFieldSchemaExtractor(),
            new CollectionFieldSchemaExtractor($metadata), new CompoundFieldSchemaExtractor($metadata),
            new ScalarFieldSchemaExtractor($metadata)]);
        $form = $factory->createBuilder()
            ->add('title', TextType::class, ['data' => 'Existing title', 'label' => 'Title', 'help' => 'Article title'])
            ->add('status', ChoiceType::class, ['choices' => ['Draft' => 'draft', 'Published' => 'published']])
            ->add('tags', CollectionType::class, ['entry_type' => TextType::class, 'allow_add' => true])
            ->add('payload', SchemaOverrideType::class, ['json_schema' => ['type' => 'array', 'items' => ['type' => 'integer']]])
            ->add('password', PasswordType::class, ['data' => 'never-expose-this'])
            ->getForm();
        $result = new FormSchema($extractor)->describe($form);
        self::assertSame(['type' => 'array', 'items' => ['type' => 'integer']], $result['schema']['properties']['payload']);
        self::assertSame('Article title', $result['schema']['properties']['title']['description']);
        self::assertSame(['draft', 'published'], $result['schema']['properties']['status']['enum']);
        self::assertSame('array', $result['schema']['properties']['tags']['type']);
        self::assertSame('Existing title', $result['fields']['title']['value']);
        self::assertSame('draft', $result['fields']['status']['choices'][0]['value']);
        self::assertArrayHasKey('prototype', $result['fields']['tags']);
        self::assertTrue($result['fields']['tags']['allowAdd']);
        self::assertArrayNotHasKey('value', $result['fields']['password']);
    }
}

class SchemaOverrideType extends AbstractType
{
    public function getParent(): string
    {
        return TextType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['json_schema' => null]);
    }
}
