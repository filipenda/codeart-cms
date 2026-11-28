<?php

namespace Codeart\Core\Publishing;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Publishing\Actions\PublishContent;
use Codeart\Core\Publishing\Actions\UnpublishContent;
use Codeart\Core\Tables\BulkAction;
use Codeart\Core\Tables\Column;
use Codeart\Core\Tables\DataTable;
use Codeart\Core\Tables\Filter;
use Codeart\Core\Tables\RowAction;
use Codeart\Core\Tables\TableDefinition;
use Codeart\Core\Translations\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * The list of a publishable module: title, languages, status, dates; edit / publish / unpublish / delete per row and in bulk.
 * A module's table class only names the model and the module. Without the workflow (`usesWorkflow()` false) there is no
 * status, no dates of publication and no publish actions.
 */
abstract class PublishableTable implements TableDefinition
{
    /** @return class-string<Model&PublishableModel> */
    abstract protected function model(): string;

    /** Module name: table key, route prefix (`pages.edit`), permission prefix, view and language namespace. */
    abstract protected function module(): string;

    /**
     * A short plain-text excerpt of the first rich-text (`editor`) field; none when the model has no such field.
     *
     * @param  Model&PublishableModel  $model
     * @return list<Column>
     */
    private function excerptColumns(Model $model, string $module, string $titleSql): array
    {
        $field = array_search('editor', $model->formFields(), true);
        if (! is_string($field)) {
            return [];
        }
        $label = Lang::has($module.'::module.fields.'.$field) ? (string) __($module.'::module.fields.'.$field) : Str::headline($field);

        return [Column::text('excerpt', $label, str_replace('t.'.array_key_first($model->formFields()), 't.'.$field, $titleSql))
            ->sortable(false)->searchable(false)->filterable(false)
            ->using(fn (Model $r): string => Str::limit(trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) preg_replace('/<\/(p|h\d|li|tr|blockquote)>/i', ' ', (string) $r->getAttribute($field)))))), 90))];
    }

    /** A small picture of the first media field (the published one): read from the usage rows, loaded with the page (no N+1). */
    private function thumbnailColumn(Model $model, string $module): ?Column
    {
        $fields = method_exists($model, 'mediaFields') ? array_keys($model->mediaFields()) : [];
        if ($fields === [] || ! method_exists($model, 'mediaUsages')) {
            return null;
        }
        $field = $fields[0];
        $label = Lang::has($module.'::module.fields.'.$field) ? (string) __($module.'::module.fields.'.$field) : Str::headline($field);

        return Column::thumbnail('thumbnail', $label)->sortable(false)->searchable(false)->filterable(false)
            ->using(function (Model $r) use ($field): ?string {
                $usage = $r->getRelation('mediaUsages')->where('field', $field)->sortBy('position')->first();

                return $usage?->media?->variantUrl('thumb');
            });
    }

    public function table(): DataTable
    {
        $class = $this->model();
        $model = new $class;
        $module = $this->module();
        $table = $model->getTable();
        $translations = (new ($model->translationModel()))->getTable();
        $titleField = (string) array_key_first($model->formFields());
        $default = addslashes(app(Locales::class)->default());
        $titleSql = "(SELECT t.{$titleField} FROM {$translations} t WHERE t.{$model->getForeignKey()} = {$table}.{$model->getKeyName()} AND t.locale = '{$default}' LIMIT 1)";
        $status = PublishStatus::options();
        $workflow = $model->usesWorkflow();

        $columns = [
            Column::titleSubtitle('title', (string) __($module.'::module.col.title'), $titleSql)
                ->using(fn (Model $r): array => [
                    'title' => $r instanceof PublishableModel ? $r->displayTitle() : '',
                    'subtitle' => $r instanceof PublishableModel && $r->hasDraft() && $r->isPublished() ? (string) __('codeart::publishing.has_draft') : null,
                    'url' => route($module.'.edit', $r->getKey()),
                ])->exportUsing(fn (Model $r): string => $r instanceof PublishableModel ? $r->displayTitle() : ''),
            Column::languageChips('languages', (string) __('codeart::publishing.languages'))
                ->using(fn (Model $r): array => $r->getRelation('translations')->pluck('locale')->all()),
        ];
        array_push($columns, ...$this->excerptColumns($model, $module, $titleSql));
        $thumbnail = $this->thumbnailColumn($model, $module);
        if ($thumbnail !== null) {
            array_splice($columns, 1, 0, [$thumbnail]);
        }
        $filters = [];
        $rowActions = [RowAction::make('edit', (string) __('codeart::table.edit'), 'pencil', fn (Model $r): string => route($module.'.edit', $r->getKey()), $module.'.update')];
        $bulkActions = [];

        if ($workflow) {
            $columns[] = Column::status('status', (string) __('codeart::publishing.status_label'), $status, $table.'.status')
                ->using(fn (Model $r): string => $r->getAttribute('status') instanceof PublishStatus ? $r->getAttribute('status')->value : (string) $r->getAttribute('status'))
                ->searchByOptions();
            $columns[] = Column::date('published_at', (string) __('codeart::publishing.published_at'), $table.'.published_at');
            $filters[] = Filter::select('status', (string) __('codeart::publishing.status_label'), array_map(fn (array $o): string => $o[0], $status), $table.'.status');
            $rowActions[] = RowAction::make('publish', (string) __('codeart::publishing.publish'), 'upload', fn (Model $r): string => route($module.'.publish', $r->getKey()), $module.'.publish', 'POST', (string) __('codeart::publishing.confirm_publish'))
                ->visibleWhen(fn (Model $r): bool => $r instanceof PublishableModel && ! $r->isPublished());
            $rowActions[] = RowAction::make('unpublish', (string) __('codeart::publishing.unpublish'), 'eye-off', fn (Model $r): string => route($module.'.unpublish', $r->getKey()), $module.'.publish', 'POST', (string) __('codeart::publishing.confirm_unpublish'))
                ->visibleWhen(fn (Model $r): bool => $r instanceof PublishableModel && $r->getAttribute('status') !== PublishStatus::Draft);
            $bulkActions[] = BulkAction::make('publish', (string) __('codeart::publishing.publish'), $this->each('publish', fn (Model $r, $actor) => app(PublishContent::class)($r, $actor)), $module.'.publish', (string) __('codeart::publishing.confirm_publish_bulk'), queueable: false);
            $bulkActions[] = BulkAction::make('unpublish', (string) __('codeart::publishing.unpublish'), $this->each('publish', fn (Model $r, $actor) => app(UnpublishContent::class)($r, $actor)), $module.'.publish', (string) __('codeart::publishing.confirm_unpublish_bulk'), queueable: false);
        }
        $columns[] = Column::date('updated_at', (string) __('codeart::publishing.updated_at'), $table.'.updated_at');
        $rowActions[] = RowAction::delete(fn (Model $r): string => route($module.'.destroy', $r->getKey()), $module.'.delete');
        $bulkActions[] = BulkAction::destructive('delete', (string) __('codeart::table.delete'), $this->each('delete', fn (Model $r) => $r->delete()), $module.'.delete', (string) __('codeart::publishing.confirm_delete_bulk'), queueable: false);

        return DataTable::make($module, $class::query())
            ->columns(...$columns)
            ->filters(...$filters)
            ->with('translations', ...($thumbnail !== null ? ['mediaUsages.media'] : []))
            ->defaultSort('updated_at', 'desc')
            ->permission($module.'.view')
            ->export($module)
            ->rowActions(...$rowActions)
            ->bulkActions(...$bulkActions);
    }

    /** One row: the policy decides on the server, a forbidden row is skipped and counted. */
    private function each(string $ability, \Closure $do): \Closure
    {
        return function (Model $row) use ($ability, $do): ?bool {
            $actor = Admin::currentUser();
            if ($actor === null || ! Gate::forUser($actor)->allows($ability, $row)) {
                return null;
            }
            $do($row, $actor);

            return true;
        };
    }
}
