<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Query;

use Softspring\CmsBundle\Manager\MenuManagerInterface;
use Softspring\CmsBundle\Model\MenuInterface;
use Softspring\CmsBundle\Serialization\MenuSerializer;
use Throwable;

use function count;
use function mb_stripos;
use function trim;

class MenuQuery
{
    public function __construct(
        private readonly MenuManagerInterface $menuManager,
        private readonly MenuSerializer $menuSerializer,
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
        return $this->menuSerializer->summary($menu);
    }

    private function detail(MenuInterface $menu): array
    {
        return $this->menuSerializer->detail($menu);
    }
}
