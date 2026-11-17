<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Schema;

use Softspring\CmsBundle\Form\Module\ModuleCollectionType;
use Softspring\CmsBundle\Serialization\AiInstructionsSerializer;
use Softspring\Component\FormSchema\Schema\Extractor\FieldSchemaExtractorInterface;
use Softspring\Component\FormSchema\Schema\SchemaExtractor;
use Softspring\Component\FormSchema\Schema\SchemaMetadataApplier;
use Symfony\Component\Form\FormInterface;

final class ModuleCollectionSchemaExtractor implements FieldSchemaExtractorInterface
{
    public function __construct(private readonly SchemaMetadataApplier $metadata) {}

    public function supports(FormInterface $field): bool
    {
        return $field->getConfig()->getType()->getInnerType() instanceof ModuleCollectionType;
    }

    public function extract(FormInterface $field, SchemaExtractor $schemaExtractor): ?array
    {
        if ('module_prototypes_collection' === $field->getName()) {
            return null;
        }
        $options = $field->getConfig()->getOptions();
        $modules = $options['allowed_modules'] ?? array_keys($options['discriminator_map']);

        $schema = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => ['_module' => ['type' => 'string', 'enum' => array_values($modules)]],
                'required' => ['_module'],
            ],
        ];
        $instructions = AiInstructionsSerializer::summarize($options);
        if (isset($instructions['aiInstructions'])) {
            $schema['description'] = $instructions['aiInstructions'];
        }

        return $this->metadata->apply($schema, $field);
    }
}
