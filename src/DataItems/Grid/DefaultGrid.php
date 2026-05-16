<?php

namespace Exceedone\Exment\DataItems\Grid;

use Encore\Admin\Grid;
use Encore\Admin\Grid\Column;
use Encore\Admin\Grid\Linker;
use Exceedone\Exment\Grid\Tools as GridTools;
use Exceedone\Exment\Form\Tools;
use Exceedone\Exment\Form\Widgets\SelectItemBox;
use Exceedone\Exment\Model\RelationTable;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\CustomView;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Model\Workflow;
use Exceedone\Exment\Services\DataImportExport;
use Exceedone\Exment\ColumnItems;
use Exceedone\Exment\Enums;
use Exceedone\Exment\Enums\SystemColumn;
use Exceedone\Exment\Enums\SearchType;
use Exceedone\Exment\Enums\PluginEventTrigger;
use Exceedone\Exment\Services\PartialCrudService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Encore\Admin\Form;

class DefaultGrid extends GridBase
{
    public function __construct(
        CustomTable $custom_table,
        CustomView $custom_view
    ) {
        $this->custom_table = $custom_table;
        $this->custom_view = $custom_view;
    }

    /**
     * @return Grid
     */
    public function grid()
    {
        $classname = getModelName($this->custom_table);

        $grid = new Grid(new $classname());

        if ($this->modal) {
            $this->gridFilterForModal($grid, $this->callback);

            $db_table_name = getDBTableName($this->custom_table);
            $grid->model()->select("$db_table_name.*");
        } else {
            $this->custom_view->filterSortModel(
                $grid->model(),
                ['callback' => $this->callback]
            );
        }

        $this->setCustomGridFilters($grid);

        $this->setGrid($grid);

        $this->manageRowAction($grid);

        $this->manageMenuToolButton($grid);

        $grid->getDataCallback(function ($grid): void {
            $customValueCollection = $grid->getOriginalCollection();

            $this->custom_table->setSelectTableValues(
                $customValueCollection
            );
        });

        if ($this->modal) {
            $this->appendSelectItemButton($grid);
        }

        return $grid;
    }

    /**
     * @param mixed $query
     * @param array<string, mixed> $options
     * @return mixed
     */
    public function getQuery($query, array $options = [])
    {
        return $this->custom_view->filterSortModel($query, $options);
    }

    /**
     * @return void
     */
    public function setGrid(Grid $grid): void
    {
        $custom_table = $this->custom_table;

        $grid->setHeaderAttributes(
            $this->custom_view->getHeaderOptions()
        );

        /** @var iterable<int, mixed> $custom_view_columns */
        $custom_view_columns = $this->custom_view->custom_view_columns_cache;

        foreach ($custom_view_columns as $custom_view_column) {

            /** @var mixed $item */
            $item = $custom_view_column->column_item;

            if ($item === null) {
                continue;
            }

            $item = $item->label(
                array_get($custom_view_column, 'view_column_name')
            )->options([
                'grid_column' => true,
                'view_pivot_column' =>
                    $custom_view_column->view_pivot_column_id ?? null,
                'view_pivot_table' =>
                    $custom_view_column->view_pivot_table_id ?? null,
                'header_align' =>
                    $this->custom_view->header_align ?? null,
            ]);

            $className = 'column-' . $item->name();

            $column = $grid->column(
                $item->uniqueName(),
                $item->label()
            )
                ->sort($item->sortable())
                ->sortName($item->getSortName())
                /**
                 * @param EloquentBuilder|Model $query
                 * @param array<int, string> $args
                 */
                ->sortCallback(function (&$query, $args) use ($custom_view_column): void {

                    if ($query instanceof Model) {
                        $query = $query->newQuery();
                    }

                    if (!$query instanceof EloquentBuilder) {
                        return;
                    }

                    $direction = count($args) > 0
                        ? $args[0]
                        : 'asc';

                    $this->custom_view
                        ->getSearchService()
                        ->setQuery($query)
                        ->addSelect()
                        ->orderByCustomViewColumn(
                            $custom_view_column,
                            $direction
                        );
                })
                ->style($item->gridStyle())
                ->setClasses([$className])
                ->setHeaderStyle($item->gridHeaderStyle())
                /**
                 * @param mixed $v
                 */
                ->display(function ($v) use ($item): string {
                    return (string)$item
                        ->setCustomValue($this)
                        ->html();
                })
                ->escape(false);

            $this->setGridColumn($column, $custom_view_column);
        }

        $pager_count = $this->custom_view->pager_count;

        if (
            request()->get('per_page') === null
            && $pager_count !== null
            && is_numeric($pager_count)
            && (int)$pager_count > 0
        ) {
            $grid->paginate((int)$pager_count);
        }

        $grid_per_pages = stringToArray(
            config('exment.grid_per_pages')
        );

        if (empty($grid_per_pages)) {
            $grid_per_pages = Define::PAGER_GRID_COUNTS;
        }

        $grid->perPages($grid_per_pages);

        $custom_table->setQueryWith(
            $grid->model(),
            $this->custom_view
        );
    }

    /**
     * @param Column $column
     * @param mixed $custom_view_column
     */
    protected function setGridColumn(
        Column $column,
        $custom_view_column
    ): void {
    }
}
