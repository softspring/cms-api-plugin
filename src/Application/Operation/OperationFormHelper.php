<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Operation;

use Softspring\Component\CrudlController\Event\FormPrepareEvent;
use Softspring\Component\CrudlController\Helper\FormActionActionHelper;

/** Keeps the existing CMS action events while submitting structured API input. */
final class OperationFormHelper extends FormActionActionHelper
{
    public function createForm(FormPrepareEvent $event): mixed
    {
        $options = $event->getFormOptions();
        $options['csrf_protection'] = false;
        $options['method'] = 'POST';
        $type = $event->getType();
        $this->form = $this->formFactory->create(is_object($type) ? $type::class : $type, $event->getData(), $options);

        return $this->form;
    }
}
