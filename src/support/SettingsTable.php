<?php

namespace rondodevs\toolkit\support;

use Craft;
use craft\db\Query;
use craft\helpers\FileHelper;
use DateTime;
use DateTimeZone;
use yii\db\Schema;

/**
 * A settings table the toolkit creates on first use, with no install migration, so the plugin
 * works as soon as Composer adds it. Before the tables existed the same settings were a JSON file
 * in storage/toolkit: it is still read while the table is empty, and written when the table
 * can't be created.
 */
final class SettingsTable
{
    private ?bool $available = null;

    /**
     * @param array<string, string> $columns Besides id, dateCreated, dateUpdated and uid
     * @param array<string, string> $uniqueIndexes Column => index name
     * @param array<string, string> $laterColumns Added after the table was first released: added on existing installs
     */
    public function __construct(
        private readonly string $table,
        private readonly array $columns,
        private readonly string $file,
        private readonly array $uniqueIndexes = [],
        private readonly array $laterColumns = [],
    ) {
    }

    public static function now(): string
    {
        return (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    public function isAvailable(): bool
    {
        return $this->available ??= $this->ensure();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->isAvailable() ? (new Query())->from($this->table)->orderBy(['id' => SORT_ASC])->all() : [];
    }

    /**
     * The settings of a one-row table: the row, else the legacy file.
     */
    public function first(): ?array
    {
        return $this->rows()[0] ?? $this->readFile();
    }

    /**
     * Updates the row matching `$condition` (the only row when empty), or inserts it.
     */
    public function save(array $payload, array $condition = []): void
    {
        $db = Craft::$app->getDb();
        $exists = (new Query())->from($this->table)->where($condition)->exists();

        if ($exists) {
            $db->createCommand()->update($this->table, $payload, $condition)->execute();
        } else {
            $db->createCommand()->insert($this->table, $payload + $condition)->execute();
        }
    }

    /**
     * Saves the settings of a one-row table, to the legacy file when the table can't be created.
     */
    public function put(array $payload): void
    {
        if ($this->isAvailable()) {
            $this->save($payload);
        } else {
            $this->writeFile($payload);
        }
    }

    public function delete(array $condition): void
    {
        Craft::$app->getDb()->createCommand()->delete($this->table, $condition)->execute();
    }

    public function readFile(): ?array
    {
        $path = $this->filePath();

        if (!file_exists($path)) {
            return null;
        }

        $data = json_decode((string)file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    public function writeFile(array $data): void
    {
        $path = $this->filePath();
        FileHelper::createDirectory(dirname($path));
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function filePath(): string
    {
        return Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'toolkit' . DIRECTORY_SEPARATOR . $this->file;
    }

    private function ensure(): bool
    {
        $db = Craft::$app->getDb();

        if ($db->tableExists($this->table)) {
            return $this->ensureLaterColumns();
        }

        try {
            $db->createCommand()->createTable($this->table, [
                'id' => Schema::TYPE_PK,
                ...$this->columns,
                ...$this->laterColumns,
                'dateCreated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'dateUpdated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'uid' => Schema::TYPE_CHAR . '(36) DEFAULT NULL',
            ])->execute();

            $db->createCommand()->createIndex('idx_' . $this->rawName() . '_uid', $this->table, 'uid', true)->execute();

            foreach ($this->uniqueIndexes as $column => $name) {
                $db->createCommand()->createIndex($name, $this->table, $column, true)->execute();
            }

            return true;
        } catch (\Throwable $e) {
            Craft::warning("Toolkit table {$this->rawName()} could not be created automatically: " . $e->getMessage(), __METHOD__);

            return false;
        }
    }

    private function ensureLaterColumns(): bool
    {
        if ($this->laterColumns === []) {
            return true;
        }

        $db = Craft::$app->getDb();
        $missing = fn($schema) => array_filter(
            array_keys($this->laterColumns),
            static fn(string $name) => $schema && $schema->getColumn($name) === null,
        );

        // The cached schema answers the common case; it is refreshed only when it reports a gap
        if ($missing($db->getTableSchema($this->table)) === []) {
            return true;
        }

        try {
            foreach ($missing($db->getTableSchema($this->table, true)) as $name) {
                $db->createCommand()->addColumn($this->table, $name, $this->laterColumns[$name])->execute();
            }

            $db->getSchema()->refreshTableSchema($this->table);

            return true;
        } catch (\Throwable $e) {
            Craft::warning("Toolkit table {$this->rawName()} columns could not be added automatically: " . $e->getMessage(), __METHOD__);

            return false;
        }
    }

    private function rawName(): string
    {
        return trim($this->table, '{}%');
    }
}
