<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\helpers\UrlHelper;
use craft\models\Site;
use rondodevs\toolkit\jobs\DeferredPurgeCacheJob;
use rondodevs\toolkit\redirects\RedirectMatcher;
use rondodevs\toolkit\support\SettingsTable;
use rondodevs\toolkit\Toolkit;
use yii\base\Component;
use yii\db\Schema;

/**
 * URL redirects for headless frontends: Craft never serves the pages, so the
 * frontend asks it (resolve()) before rendering, caches the answer and
 * clears it under the `redirects` cache tag when they change. Kept in the
 * database, like content: each environment has its own.
 *
 * Sources match like Ether SEO's (RedirectMatcher), in order; Ether's are
 * imported before it's uninstalled. When an entry's URI changes, its old
 * URI redirects to the new one.
 */
class RedirectsService extends Component
{
    /** The frontend's cache tag for its redirect lookups */
    public const CACHE_TAG = 'redirects';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_AUTO = 'auto';
    public const SOURCE_ETHER = 'ether';
    public const SOURCE_IMPORT = 'import';

    private const ETHER_TABLE = '{{%seo_redirects}}';

    private ?SettingsTable $table = null;

    /** @var array<string, string|null> Old URIs of the elements being saved, by "elementId-siteId" */
    private array $previousUris = [];

    public function table(): SettingsTable
    {
        return $this->table ??= new SettingsTable('{{%toolkit_redirects}}', [
            'siteId' => Schema::TYPE_INTEGER . ' DEFAULT NULL',
            'sourceUri' => Schema::TYPE_TEXT . ' NOT NULL',
            'destination' => Schema::TYPE_TEXT . ' NOT NULL',
            'statusCode' => Schema::TYPE_SMALLINT . ' NOT NULL DEFAULT 301',
            'sortOrder' => Schema::TYPE_INTEGER . ' NOT NULL DEFAULT 0',
            'origin' => Schema::TYPE_STRING . '(16) NOT NULL DEFAULT \'manual\'',
            'hits' => Schema::TYPE_INTEGER . ' NOT NULL DEFAULT 0',
            'lastHit' => Schema::TYPE_DATETIME . ' DEFAULT NULL',
        ], 'redirects.json');
    }

    /**
     * @return array<int, array{id: int, siteId: int|null, sourceUri: string, destination: string, statusCode: int, sortOrder: int, origin: string, hits: int, lastHit: string|null, dateCreated: string}>
     */
    public function all(): array
    {
        if (!$this->table()->isAvailable()) {
            return [];
        }

        return array_map(static fn(array $row) => [
            'id' => (int)$row['id'],
            'siteId' => $row['siteId'] !== null ? (int)$row['siteId'] : null,
            'sourceUri' => (string)$row['sourceUri'],
            'destination' => (string)$row['destination'],
            'statusCode' => (int)$row['statusCode'],
            'sortOrder' => (int)$row['sortOrder'],
            'origin' => (string)$row['origin'],
            'hits' => (int)$row['hits'],
            'lastHit' => $row['lastHit'],
            'dateCreated' => (string)$row['dateCreated'],
        ], (new Query())->from('{{%toolkit_redirects}}')->orderBy(['sortOrder' => SORT_ASC, 'id' => SORT_ASC])->all());
    }

    /**
     * The first redirect, in order, matching a URL on a site: its id, the
     * absolute destination and the status code.
     *
     * @return array{id: int, to: string, type: int}|null
     */
    public function resolve(string $url, Site $site): ?array
    {
        $parts = parse_url($url);

        if (!isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $path = trim(rawurldecode($parts['path'] ?? ''), '/');
        $fullUrl = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . rtrim('/' . $path, '/') . $query;

        foreach ($this->all() as $redirect) {
            if ($redirect['siteId'] !== null && $redirect['siteId'] !== $site->id) {
                continue;
            }

            $to = RedirectMatcher::match($redirect['sourceUri'], $redirect['destination'], $path . $query, $fullUrl);

            if ($to !== null && $to !== '') {
                return [
                    'id' => $redirect['id'],
                    'to' => str_contains($to, '://') ? $to : UrlHelper::siteUrl($to, null, null, $site->id),
                    'type' => $redirect['statusCode'],
                ];
            }
        }

        return null;
    }

    public function recordHit(int $id): void
    {
        if (!$this->table()->isAvailable()) {
            return;
        }

        Craft::$app->getDb()->createCommand()->update('{{%toolkit_redirects}}', [
            'hits' => new \yii\db\Expression('[[hits]] + 1'),
            'lastHit' => SettingsTable::now(),
        ], ['id' => $id], [], false)->execute();
    }

    /**
     * Adds or updates a redirect (by id), returning its id.
     */
    public function save(array $data, ?int $id = null, string $origin = self::SOURCE_MANUAL, bool $purge = true): int
    {
        $source = trim((string)($data['sourceUri'] ?? ''));
        $destination = trim((string)($data['destination'] ?? ''));
        $statusCode = (int)($data['statusCode'] ?? 301);
        $siteId = isset($data['siteId']) && $data['siteId'] !== '' && $data['siteId'] !== null ? (int)$data['siteId'] : null;

        if ($source === '' || $destination === '') {
            throw new \InvalidArgumentException('A redirect needs a source and a destination.');
        }

        if (!in_array($statusCode, [301, 302, 307, 308], true)) {
            throw new \InvalidArgumentException('The status code must be 301, 302, 307 or 308.');
        }

        if ($siteId !== null && Craft::$app->getSites()->getSiteById($siteId) === null) {
            throw new \InvalidArgumentException('Unknown site.');
        }

        if (!$this->table()->isAvailable()) {
            throw new \RuntimeException('The redirects table could not be created.');
        }

        $db = Craft::$app->getDb();
        $now = SettingsTable::now();
        $row = ['siteId' => $siteId, 'sourceUri' => $source, 'destination' => $destination, 'statusCode' => $statusCode, 'dateUpdated' => $now];

        if ($id !== null) {
            $db->createCommand()->update('{{%toolkit_redirects}}', $row, ['id' => $id])->execute();
        } else {
            $db->createCommand()->insert('{{%toolkit_redirects}}', $row + [
                'sortOrder' => $this->nextSortOrder(),
                'origin' => $origin,
                'dateCreated' => $now,
                'uid' => \craft\helpers\StringHelper::UUID(),
            ])->execute();
            $id = (int)$db->getLastInsertID();
        }

        if ($purge) {
            $this->purgeFrontend();
        }

        return $id;
    }

    /**
     * @param int[] $ids
     */
    public function delete(array $ids): int
    {
        if ($ids === [] || !$this->table()->isAvailable()) {
            return 0;
        }

        $count = Craft::$app->getDb()->createCommand()->delete('{{%toolkit_redirects}}', ['id' => array_map('intval', $ids)])->execute();
        $this->purgeFrontend();

        return $count;
    }

    /**
     * Saves the order of the redirects: the ids in their new order.
     *
     * @param int[] $ids
     */
    public function reorder(array $ids): void
    {
        $db = Craft::$app->getDb();

        foreach (array_values($ids) as $position => $id) {
            $db->createCommand()->update('{{%toolkit_redirects}}', ['sortOrder' => $position + 1], ['id' => (int)$id], [], false)->execute();
        }

        $this->purgeFrontend();
    }

    /**
     * Rows of a CSV (source, destination, status code, site handle), the
     * header row optional; returns how many were added and skipped.
     *
     * @return array{added: int, skipped: int}
     */
    public function importCsv(string $csv): array
    {
        $added = 0;
        $skipped = 0;
        $existing = $this->keys();
        $sites = Craft::$app->getSites();

        foreach (preg_split('/\R/', trim($csv)) ?: [] as $index => $line) {
            $cells = str_getcsv($line, str_contains($line, ';') && !str_contains($line, ',') ? ';' : ',');
            $source = trim($cells[0] ?? '');

            if ($source === '' || ($index === 0 && in_array(strtolower($source), ['source', 'from', 'uri', 'old url'], true))) {
                continue;
            }

            $handle = trim($cells[3] ?? '');
            $site = $handle !== '' ? $sites->getSiteByHandle($handle) : null;
            $siteId = $site?->id;

            if (($handle !== '' && $site === null) || isset($existing[$this->key($siteId, $source)])) {
                $skipped++;
                continue;
            }

            try {
                $this->save([
                    'sourceUri' => $source,
                    'destination' => $cells[1] ?? '',
                    'statusCode' => trim($cells[2] ?? '') !== '' ? $cells[2] : 301,
                    'siteId' => $siteId,
                ], null, self::SOURCE_IMPORT, false);
                $existing[$this->key($siteId, $source)] = true;
                $added++;
            } catch (\InvalidArgumentException) {
                $skipped++;
            }
        }

        if ($added > 0) {
            $this->purgeFrontend();
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    public function exportCsv(): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['source', 'destination', 'status', 'site', 'hits', 'last hit']);

        foreach ($this->all() as $redirect) {
            fputcsv($out, [
                $redirect['sourceUri'],
                $redirect['destination'],
                $redirect['statusCode'],
                $redirect['siteId'] !== null ? Craft::$app->getSites()->getSiteById($redirect['siteId'])?->handle : '',
                $redirect['hits'],
                $redirect['lastHit'] ?? '',
            ]);
        }

        rewind($out);

        return (string)stream_get_contents($out);
    }

    /**
     * Copies Ether SEO's redirects, in their order, skipping the ones already
     * here: run before Ether is uninstalled (its table goes with it), by the
     * migration and on any uninstall, project config included.
     */
    public function importFromEther(): int
    {
        $db = Craft::$app->getDb();

        if (!$db->tableExists(self::ETHER_TABLE) || !$this->table()->isAvailable()) {
            return 0;
        }

        $existing = $this->keys();
        $imported = 0;

        foreach ((new Query())->from(self::ETHER_TABLE)->orderBy(['order' => SORT_ASC, 'id' => SORT_ASC])->all() as $row) {
            $siteId = $row['siteId'] !== null ? (int)$row['siteId'] : null;

            if (isset($existing[$this->key($siteId, (string)$row['uri'])])) {
                continue;
            }

            try {
                $this->save([
                    'sourceUri' => $row['uri'],
                    'destination' => $row['to'],
                    'statusCode' => (int)$row['type'] ?: 301,
                    'siteId' => $siteId,
                ], null, self::SOURCE_ETHER, false);
                $imported++;
            } catch (\InvalidArgumentException $e) {
                Craft::warning("Toolkit: Ether redirect \"{$row['uri']}\" not imported - " . $e->getMessage(), __METHOD__);
            }
        }

        if ($imported > 0) {
            $this->purgeFrontend();
        }

        return $imported;
    }

    public function isAutoEnabled(): bool
    {
        return (bool)(Craft::$app->getProjectConfig()->get('toolkit.redirects.auto') ?? true);
    }

    public function setAutoEnabled(bool $enabled): void
    {
        Craft::$app->getProjectConfig()->set('toolkit.redirects.auto', $enabled, 'Toolkit: automatic redirects');
    }

    /**
     * Before an element is saved: its current URI on that site.
     */
    public function rememberUri(ElementInterface $element): void
    {
        if (!$this->tracks($element)) {
            return;
        }

        $this->previousUris[$element->id . '-' . $element->siteId] = (new Query())
            ->select('uri')
            ->from('{{%elements_sites}}')
            ->where(['elementId' => $element->id, 'siteId' => $element->siteId])
            ->scalar() ?: null;
    }

    /**
     * After it's saved: when the URI changed, the old one redirects to the
     * new one, redirects to the old one go to the new one too (no chains),
     * and a redirect from the new URI, which would hide the page, goes.
     */
    public function redirectChangedUri(ElementInterface $element): void
    {
        $key = $element->id . '-' . $element->siteId;
        $old = $this->previousUris[$key] ?? null;
        unset($this->previousUris[$key]);

        if (!$this->tracks($element) || $old === null || $element->uri === null || $old === $element->uri || $old === '__home__') {
            return;
        }

        $new = $element->uri === '__home__' ? '' : $element->uri;
        $db = Craft::$app->getDb();
        $siteId = $element->siteId;

        foreach ($this->all() as $redirect) {
            if ($redirect['siteId'] !== null && $redirect['siteId'] !== $siteId) {
                continue;
            }

            if (trim($redirect['sourceUri'], '/') === trim($new, '/')) {
                $db->createCommand()->delete('{{%toolkit_redirects}}', ['id' => $redirect['id']])->execute();
            } elseif (trim($redirect['destination'], '/') === trim($old, '/')) {
                $db->createCommand()->update('{{%toolkit_redirects}}', ['destination' => $new === '' ? '/' : $new], ['id' => $redirect['id']])->execute();
            }
        }

        if (!isset($this->keys()[$this->key($siteId, $old)])) {
            $this->save(['sourceUri' => $old, 'destination' => $new === '' ? '/' : $new, 'statusCode' => 301, 'siteId' => $siteId], null, self::SOURCE_AUTO, false);
        }

        $this->queuePurge();
    }

    /**
     * Clears the frontend's cached lookups, through the KV Cache settings.
     */
    public function purgeFrontend(): void
    {
        Toolkit::getInstance()->kvCache->purgeAfterSave([self::CACHE_TAG]);
    }

    /**
     * The same from an element save: in the queue, like the element's own purge.
     */
    private function queuePurge(): void
    {
        $kvCache = Toolkit::getInstance()->kvCache;

        if ($kvCache->isEnabled() && !$kvCache->isEndpointUnreachable()) {
            Craft::$app->getQueue()->push(new DeferredPurgeCacheJob(['elementTags' => [self::CACHE_TAG]]));
        }
    }

    /**
     * Live, canonical elements with URIs: drafts, revisions and propagation
     * copies of a new element have no old address worth keeping.
     */
    private function tracks(ElementInterface $element): bool
    {
        return $this->isAutoEnabled()
            && $element->id !== null
            && !$element->getIsDraft()
            && !$element->getIsRevision()
            && $element->getIsCanonical()
            && !$element->firstSave
            && $element->getStatus() === 'live';
    }

    /**
     * @return array<string, true>
     */
    private function keys(): array
    {
        $keys = [];

        foreach ($this->all() as $redirect) {
            $keys[$this->key($redirect['siteId'], $redirect['sourceUri'])] = true;
        }

        return $keys;
    }

    private function key(?int $siteId, string $source): string
    {
        return ($siteId ?? '*') . '|' . strtolower(trim($source, '/'));
    }

    private function nextSortOrder(): int
    {
        return (int)(new Query())->from('{{%toolkit_redirects}}')->max('[[sortOrder]]') + 1;
    }
}
