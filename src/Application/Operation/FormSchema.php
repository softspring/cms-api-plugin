<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Operation;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

final class FormSchema
{
    public function describe(FormInterface $form): array
    {
        return $this->view($form->createView());
    }

    private function view(FormView $view, int $depth = 0): array
    {
        $vars = $view->vars;
        $schema = ['name' => $vars['name'], 'required' => $vars['required'] ?? false,
            'disabled' => $vars['disabled'] ?? false, 'types' => $vars['block_prefixes'] ?? []];
        if (in_array('password', $schema['types'], true)) {
            return $schema;
        }
        $value = in_array('checkbox', $schema['types'], true) ? (($vars['checked'] ?? false) ? '1' : null) : ($vars['value'] ?? null);
        if (isset($vars['multiple'])) {
            $schema['multiple'] = $vars['multiple'];
        }
        if (null === $value || is_scalar($value) || (is_array($value) && !array_filter($value, 'is_object'))) {
            $schema['value'] = $value;
        }
        if (isset($vars['choices'])) {
            $schema['choices'] = $this->choices($vars['choices']);
        }
        if ($depth < 12) {
            foreach ($view->children as $name => $child) {
                $schema['fields'][$name] = $this->view($child, $depth + 1);
            }
            if (isset($vars['prototype'])) {
                $schema['prototype'] = $this->view($vars['prototype'], $depth + 1);
                $schema['allowAdd'] = $vars['allow_add'] ?? false;
                $schema['allowDelete'] = $vars['allow_delete'] ?? false;
            }
        }

        return $schema;
    }

    private function choices(iterable $choices): array
    {
        $result = [];
        foreach ($choices as $choice) {
            if (count($result) >= 500) {
                break;
            }
            $result[] = isset($choice->choices) ? ['label' => $choice->label, 'choices' => $this->choices($choice->choices)]
                : ['label' => $choice->label, 'value' => $choice->value];
        }

        return $result;
    }
}
