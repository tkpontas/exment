<?php

namespace Exceedone\Exment\DataItems\Grid;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Column;
use Encore\Admin\Grid\Linker;
use Exceedone\Exment\ColumnItems;
use Exceedone\Exment\Enums;
use Exceedone\Exment\Enums\PluginEventTrigger;
use Exceedone\Exment\Enums\SearchType;
use Exceedone\Exment\Enums\SystemColumn;
use Exceedone\Exment\Form\Tools;
use Exceedone\Exment\Form\Widgets\SelectItemBox;
use Exceedone\Exment\Grid\Tools as GridTools;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomView;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Model\RelationTable;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Model\Workflow;
use Exceedone\Exment\Services\DataImportExport;
use Exceedone\Exment\Services\PartialCrudService;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
     * Make a grid builder.
     *
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

        $grid->getDataCallback(function ($grid) {
            $customValueCollection = $grid->getOriginalCollection();

            $this->custom_table
                ->setSelectTableValues($customValueCollection);
        });

        if ($this->modal) {
            $this->appendSelectItemButton($grid);
        }

        return $grid;
    }

    /**
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Schema\Builder $query
     * @param array<string,mixed> $options
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Schema\Builder
     */
    public function getQuery($query, array $options = [])
    {
        return $this->custom_view
            ->filterSortModel($query, $options);
    }

    /**
     * @param mixed $grid
     * @return void
     */
    public function setGrid($grid): void
    {
        $custom_table = $this->custom_table;

        $grid->setHeaderAttributes(
            $this->custom_view->getHeaderOptions()
        );

        $custom_view_columns =
            $this->custom_view->custom_view_columns_cache;

        foreach ($custom_view_columns as $custom_view_column) {
            $item = $custom_view_column->column_item;

            if (!isset($item)) {
                continue;
            }

            $item = $item
                ->label(array_get($custom_view_column, 'view_column_name'))
                ->options([
                    'grid_column' => true,
                    'view_pivot_column'
                        => $custom_view_column->view_pivot_column_id ?? null,
                    'view_pivot_table'
                        => $custom_view_column->view_pivot_table_id ?? null,
                    'header_align'
                        => $this->custom_view->header_align ?? null,
                ]);

            $className = 'column-' . $item->name();

            $column = $grid->column(
                $item->uniqueName(),
                $item->label()
            )
                ->sort($item->sortable())
                ->sortName($item->getSortName())
                ->sortCallback(
                    /**
                     * @param EloquentBuilder|Model $query
                     * @param array<int,string> $args
                     * @return void
                     */
                    function (&$query, $args)
                    use ($custom_view_column): void {

                        if ($query instanceof Model) {
                            $query = $query->newQuery();
                        }

                        if (!$query instanceof EloquentBuilder) {
                            return;
                        }

                        $this->custom_view
                            ->getSearchService()
                            ->setQuery($query)
                            ->addSelect()
                            ->orderByCustomViewColumn(
                                $custom_view_column,
                                count($args) > 0
                                    ? $args[0]
                                    : 'asc'
                            );
                    }
                )
                ->style($item->gridStyle())
                ->setClasses([$className])
                ->setHeaderStyle($item->gridHeaderStyle())
                ->display(function ($v) use ($item) {
                    return $item
                        ->setCustomValue($this)
                        ->html();
                })
                ->escape(false);

            $this->setGridColumn($column, $custom_view_column);
        }

        $pager_count = $this->custom_view->pager_count;

        if (
            is_null(request()->get('per_page'))
            && isset($pager_count)
            && is_numeric($pager_count)
            && $pager_count > 0
        ) {
            $grid->paginate((int) $pager_count);
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
     * @return void
     */
    protected function setGridColumn(
        $column,
        $custom_view_column
    ): void {
    }

    /**
     * @param mixed $grid
     * @param callable|null $filter_func
     * @return void
     */
    protected function gridFilterForModal(
        $grid,
        $filter_func
    ): void {
        System::setRequestSession(
            Define::SYSTEM_KEY_SESSION_DISABLE_DATA_URL_TAG,
            true
        );

        $modal_target_view = CustomView::getEloquent(
            request()->get('target_view_id')
        );

        $this->custom_view = CustomView::getAllData(
            $this->custom_table
        );

        if (isset($modal_target_view)) {
            $modal_target_view->filterSortModel(
                $grid->model(),
                ['callback' => $filter_func]
            );
        }

        $modal_display_table = CustomTable::getEloquent(
            request()->get('display_table_id')
        );

        $modal_custom_column = CustomColumn::getEloquent(
            request()->get('target_column_id')
        );

        if (
            !empty($modal_display_table)
            && !empty($modal_custom_column)
        ) {
            $this->custom_table->filterDisplayTable(
                $grid->model(),
                $modal_display_table,
                [
                    'all'
                        => $modal_custom_column
                            ->isGetAllUserOrganization(),
                ]
            );
        }

        $expand = request()->get('linkage');

        if (!is_nullorempty($expand)) {
            RelationTable::setQuery(
                $grid->model(),
                array_get($expand, 'search_type'),
                array_get($expand, 'linkage_value_id'),
                [
                    'parent_table' => CustomTable::getEloquent(
                        array_get(
                            $expand,
                            'parent_select_table_id'
                        )
                    ),
                    'child_table' => CustomTable::getEloquent(
                        array_get(
                            $expand,
                            'child_select_table_id'
                        )
                    ),
                ]
            );
        }
    }

    protected function getFilterUrl(): string
    {
        if (!$this->modal) {
            $query = array_filter(
                request()->all([
                    '_scope_',
                ])
            );
        } else {
            $query = array_filter(
                request()->all([
                    'target_view_id',
                    'display_table_id',
                    'target_column_id',
                    'linkage',
                ])
            );

            $query['modal'] = 1;
        }

        return admin_urls_query(
            'data',
            $this->custom_table->table_name,
            $query
        );
    }

    /**
     * @return array<string,string|null>
     */
    public function getFilterHtml(): array
    {
        $classname = getModelName($this->custom_table);

        $grid = new Grid(new $classname());

        $this->setCustomGridFilters($grid, true);

        $html = null;

        $grid->filter(function ($filter) use (&$html) {
            $html = $filter->render();
        });

        return [
            'html' => $html,
            'script' => \Admin::purescript()->render(),
        ];
    }

    /**
     * @param mixed $grid
     * @param bool $ajax
     * @return void
     */
    protected function setCustomGridFilters(
        $grid,
        bool $ajax = false
    ): void {
        $grid->quickSearch(function ($model, $input) {

            $eloquent = $model->eloquent();

            if (
                is_object($eloquent)
                && method_exists(
                    $eloquent,
                    'setSearchQueryOrWhere'
                )
            ) {
                $eloquent->setSearchQueryOrWhere(
                    $model,
                    $input,
                    [
                        'searchDocument' => true,
                    ]
                );
            }
        }, 'left');

        $grid->filter(function ($filter) use ($ajax) {

            $filter->disableIdFilter();

            $filter->setAction($this->getFilterUrl());

            if (
                $this->custom_table->enableShowTrashed() === true
                && !$this->modal
            ) {
                $filter
                    ->scope(
                        'trashed',
                        exmtrans(
                            'custom_value.soft_deleted_data'
                        )
                    )
                    ->onlyTrashed();
            }

            if (
                config(
                    'exment.custom_value_filter_ajax',
                    true
                )
                && !$ajax
                && !$this->modal
                && !boolval(request()->get('execute_filter'))
            ) {
                $filter->setFilterAjax(
                    admin_urls_query(
                        'data',
                        $this->custom_table->table_name,
                        ['filter_ajax' => 1]
                    )
                );

                return;
            }

            $filterItems = $this->getFilterColumns($filter);

            if (count($filterItems) <= 6) {

                foreach ($filterItems as $filterItem) {
                    $filterItem->setAdminFilter($filter);
                }

            } else {

                $separate = (int) floor(
                    count($filterItems) / 2
                );

                $filter->column(
                    1 / 2,
                    function ($filter)
                    use ($filterItems, $separate) {

                        for ($i = 0; $i < $separate; $i++) {
                            $filterItems[$i]
                                ->setAdminFilter($filter);
                        }
                    }
                );

                $filter->column(
                    1 / 2,
                    function ($filter)
                    use ($filterItems, $separate) {

                        for (
                            $i = $separate;
                            $i < count($filterItems);
                            $i++
                        ) {
                            $filterItems[$i]
                                ->setAdminFilter($filter);
                        }
                    }
                );
            }
        });
    }

    /**
     * @param mixed $filter
     * @return Collection<int,mixed>
     */
    protected function getFilterColumns($filter): Collection
    {
        $filterItems = [];

        $custom_view_grid_filters =
            $this->custom_view->custom_view_grid_filters;

        if (count($custom_view_grid_filters) > 0) {

            $service = $this->custom_view
                ->getSearchService()
                ->setQuery($filter->model());

            foreach (
                $custom_view_grid_filters
                as $custom_view_grid_filter
            ) {
                $service->setRelationJoin(
                    $custom_view_grid_filter
                );

                $filterItems[] =
                    $custom_view_grid_filter->column_item;
            }

            return new Collection($filterItems);
        }

        foreach (
            SystemColumn::getOptions([
                'grid_filter' => true,
                'grid_filter_system' => true,
            ])
            as $filterKey => $filterType
        ) {

            if (
                $this->custom_table
                    ->gridFilterDisable($filterKey)
            ) {
                continue;
            }

            $filterItems[] =
                ColumnItems\SystemItem::getItem(
                    $this->custom_table,
                    $filterKey
                );
        }

        $this->setRelationFilter($filterItems);

        if (
            !is_null(
                Workflow::getWorkflowByTable(
                    $this->custom_table
                )
            )
        ) {
            foreach (
                SystemColumn::getOptions([
                    'grid_filter' => true,
                    'grid_filter_system' => false,
                ])
                as $filterKey => $filterType
            ) {

                if (
                    $this->custom_table
                        ->gridFilterDisable($filterKey)
                ) {
                    continue;
                }

                $filterItems[] =
                    ColumnItems\WorkflowItem::getItem(
                        $this->custom_table,
                        $filterKey
                    );
            }
        }

        $this->setColumnFilter($filterItems);

        return new Collection($filterItems);
    }
}
