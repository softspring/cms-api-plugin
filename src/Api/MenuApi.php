<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Api;

use Softspring\CmsBundle\Manager\MenuManagerInterface;
use Softspring\CmsBundle\Model\MenuInterface;
use Softspring\CmsBundle\Model\MenuItemInterface;
use Throwable;

use function count;
use function mb_stripos;
use function trim;

class MenuApi
{
    public function __construct(
        private readonly MenuManagerInterface $menuManager,
    ) {}

    public function list(?string $type = null, string $query = '', int $limit = 50): array
    {
        $query = trim($query);
        $menus = [];

        foreach ($this->findMenus($limit) as $menu) {
            if ($type && $menu->getType() !== $type) {
                continue;
            }

            if ('' !== $query && false === mb_stripos((string) $menu->getName(), $query)) {
                continue;
            }

            $menus[] = $this->summary($menu);

            if (count($menus) >= $limit) {
                break;
            }
        }

        return [
            'filters' => [
                'type' => $type,
                'query' => $query,
            ],
            'count' => count($menus),
            'menus' => $menus,
        ];
    }

    public function get(string $id): ?array
    {
        try {
            $menu = $this->menuManager->getRepository()->find($id);
        } catch (Throwable) {
            return null;
        }

        if (!$menu instanceof MenuInterface) {
            return null;
        }

        return $this->detail($menu);
    }

    /**
     * @return list<MenuInterface>
     */
    private function findMenus(int $limit): array
    {
        try {
            return $this->menuManager->getRepository()->findBy([], ['name' => 'ASC'], $limit);
        } catch (Throwable) {
            return [];
        }
    }

    private function summary(MenuInterface $menu): array
    {
        return [
            'id' => $menu->getId(),
            'type' => $menu->getType(),
            'name' => $menu->getName(),
        ];
    }

    private function detail(MenuInterface $menu): array
    {
        return $this->summary($menu) + [
            'data' => $menu->getData(),
            'items' => array_map(
                fn(MenuItemInterface $item): array => $this->item($item),
                $menu->getItems()?->filter(static fn(MenuItemInterface $item): bool => null === $item->getParent())->toArray() ?? [],
            ),
        ];
    }

    private function item(MenuItemInterface $item): array
    {
        return [
            'id' => $item->getId(),
            'type' => $item->getType(),
            'text' => $item->getText(),
            'symfonyRoute' => $item->getSymfonyRoute(),
            'options' => $item->getOptions(),
            'items' => array_map(
                fn(MenuItemInterface $child): array => $this->item($child),
                $item->getItems()?->toArray() ?? [],
            ),
        ];
    }
}
