<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Api;

use Softspring\CmsBundle\Manager\BlockManagerInterface;
use Softspring\CmsBundle\Model\BlockInterface;
use Throwable;

use function count;
use function json_encode;
use function mb_stripos;
use function trim;

use const DATE_ATOM;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class BlockApi
{
    public function __construct(
        private readonly BlockManagerInterface $blockManager,
    ) {}

    public function list(?string $type = null, string $query = '', int $limit = 50): array
    {
        $query = trim($query);
        $blocks = [];

        foreach ($this->findBlocks($limit) as $block) {
            if ($type && $block->getType() !== $type) {
                continue;
            }

            if ('' !== $query && !$this->matchesQuery($block, $query)) {
                continue;
            }

            $blocks[] = $this->summary($block);

            if (count($blocks) >= $limit) {
                break;
            }
        }

        return [
            'filters' => [
                'type' => $type,
                'query' => $query,
            ],
            'count' => count($blocks),
            'blocks' => $blocks,
        ];
    }

    public function get(string $id): ?array
    {
        try {
            $block = $this->blockManager->getRepository()->find($id);
        } catch (Throwable) {
            return null;
        }

        if (!$block instanceof BlockInterface) {
            return null;
        }

        return $this->detail($block);
    }

    /**
     * @return list<BlockInterface>
     */
    private function findBlocks(int $limit): array
    {
        try {
            return $this->blockManager->getRepository()->findBy([], ['name' => 'ASC'], $limit);
        } catch (Throwable) {
            return [];
        }
    }

    private function summary(BlockInterface $block): array
    {
        return [
            'id' => $block->getId(),
            'type' => $block->getType(),
            'name' => $block->getName(),
            'publishStartDate' => $block->getPublishStartDate()?->format(DATE_ATOM),
            'publishEndDate' => $block->getPublishEndDate()?->format(DATE_ATOM),
        ];
    }

    private function detail(BlockInterface $block): array
    {
        return $this->summary($block) + [
            'data' => $block->getData(),
        ];
    }

    private function matchesQuery(BlockInterface $block, string $query): bool
    {
        if (false !== mb_stripos((string) $block->getName(), $query)) {
            return true;
        }

        return false !== mb_stripos((string) json_encode($block->getData(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $query);
    }
}
